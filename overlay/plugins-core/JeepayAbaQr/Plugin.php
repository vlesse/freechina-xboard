<?php

namespace Plugin\JeepayAbaQr;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use App\Services\Plugin\AbstractPlugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Xboard → Jeepay 统一下单 → ABA_KHQR 二维码
 *
 * Xboard 订单金额单位：人民币「分」（50 元 = 5000）
 * 下单到 Jeepay 前按汇率换算为 KHR。
 * 个人固定商业码无法写死金额 → 跳转说明页提示用户手输瑞尔。
 *
 * 再次结账：同一 trade_no 在 Jeepay 已存在时，优先查单复用 / 缓存 tip URL，
 * 否则使用 trade_no + R + 时间戳 作为新 mchOrderNo（回调时还原 trade_no）。
 */
class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['JeepayAbaQr'] = [
                    'name' => $this->getConfig('display_name', 'ABA KHQR扫码(自动换算瑞尔)'),
                    'icon' => $this->getConfig('icon', '🇰🇭'),
                    'plugin_code' => $this->getPluginCode(),
                    'type' => 'plugin',
                ];
            }
            return $methods;
        });
    }

    public function form(): array
    {
        return [
            'gateway_url' => [
                'label' => 'Jeepay支付网关',
                'type' => 'string',
                'required' => true,
                'description' => '例如 https://pay.free--china.com （不要末尾斜杠）',
                'default' => 'https://pay.free--china.com',
            ],
            'mch_no' => [
                'label' => '商户号 mchNo',
                'type' => 'string',
                'required' => true,
            ],
            'app_id' => [
                'label' => '应用ID appId',
                'type' => 'string',
                'required' => true,
            ],
            'app_secret' => [
                'label' => '应用密钥 appSecret',
                'type' => 'string',
                'required' => true,
            ],
            'way_code' => [
                'label' => '支付方式 wayCode',
                'type' => 'string',
                'required' => true,
                'default' => 'ABA_KHQR',
                'description' => '固定 ABA_KHQR（个人KHQR扫码）',
            ],
            'cny_to_khr_rate' => [
                'label' => '人民币→瑞尔汇率',
                'type' => 'string',
                'required' => true,
                'default' => '560',
                'description' => '1 人民币 = 多少瑞尔。例：560 表示 50 元 → 28000 瑞尔。',
            ],
            'tip_page_url' => [
                'label' => '金额说明页URL',
                'type' => 'string',
                'required' => true,
                'default' => 'https://free--china.com/aba-khqr-pay.php',
                'description' => '展示「请输入多少瑞尔」的页面完整地址（推荐 aba-khqr-pay.php，服务端渲染二维码）',
            ],
            'product_name' => [
                'label' => '商品标题前缀',
                'type' => 'string',
                'default' => 'XBoard',
            ],
        ];
    }

    public function pay($order): array
    {
        $tradeNo = (string) $order['trade_no'];
        $cacheKey = 'jeepay_aba_qr_tip:' . $tradeNo;

        // 同一订单再次点结账：直接返回上次说明页（避免「商户订单已存在」）
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            Log::info('[JeepayAbaQr] reuse cached tip', ['trade_no' => $tradeNo]);
            return ['type' => 1, 'data' => $cached];
        }

        $cnyFen = (int) $order['total_amount'];
        $cny = round($cnyFen / 100, 2);
        $rate = (float) $this->getConfig('cny_to_khr_rate', 560);
        if ($rate <= 0) {
            throw new ApiException('汇率配置无效');
        }

        $khrMajor = (int) ceil($cny * $rate);
        if ($khrMajor < 1) {
            $khrMajor = 1;
        }
        $jeepayAmount = $khrMajor * 100;

        $gateway = rtrim((string) $this->getConfig('gateway_url'), '/');
        $mchNo = (string) $this->getConfig('mch_no');
        $appId = (string) $this->getConfig('app_id');
        $appSecret = (string) $this->getConfig('app_secret');
        $wayCode = (string) $this->getConfig('way_code', 'ABA_KHQR');
        $prefix = (string) $this->getConfig('product_name', 'XBoard');
        $tipPage = $this->normalizeTipPage(
            (string) $this->getConfig('tip_page_url', 'https://free--china.com/aba-khqr-pay.php')
        );

        $baseParams = [
            'mchNo' => $mchNo,
            'appId' => $appId,
            'wayCode' => $wayCode,
            'amount' => $jeepayAmount,
            'currency' => 'KHR',
            'subject' => sprintf('%s %s CNY(≈%s KHR)', $prefix, $this->fmtMoney($cny), $khrMajor),
            'body' => sprintf('CNY %s @ rate %s = KHR %s', $this->fmtMoney($cny), $rate, $khrMajor),
            'notifyUrl' => $order['notify_url'],
            'returnUrl' => $order['return_url'],
            'version' => '1.0',
            'signType' => 'MD5',
        ];

        // 1) 首次：用 Xboard trade_no
        $data = $this->tryUnifiedOrder($gateway, $appSecret, $baseParams, $tradeNo);

        // 2) 已存在：查单复用
        if ($data === null) {
            $data = $this->queryPayOrder($gateway, $mchNo, $appId, $appSecret, $tradeNo);
            if (is_array($data)) {
                Log::info('[JeepayAbaQr] reuse query order', ['trade_no' => $tradeNo]);
            }
        }

        // 3) 仍失败：换唯一 mchOrderNo 再下单（回调 strip 后缀）
        if ($data === null) {
            $retryNo = $tradeNo . 'R' . substr((string) time(), -8) . random_int(10, 99);
            $data = $this->tryUnifiedOrder($gateway, $appSecret, $baseParams, $retryNo, true);
            if ($data === null) {
                throw new ApiException('Jeepay 下单失败: 无法创建或查询支付单，请稍后重试');
            }
            Log::info('[JeepayAbaQr] retry with new mchOrderNo', [
                'trade_no' => $tradeNo,
                'mchOrderNo' => $retryNo,
            ]);
        }

        $tipUrl = $this->buildTipUrl($tipPage, $order, $cny, $khrMajor, $rate, $data);
        Cache::put($cacheKey, $tipUrl, now()->addHours(6));

        return [
            'type' => 1,
            'data' => $tipUrl,
        ];
    }

    public function notify($params): array|bool
    {
        $appSecret = (string) $this->getConfig('app_secret');
        $sign = $params['sign'] ?? '';
        if ($sign === '') {
            return false;
        }

        $check = $params;
        unset($check['sign']);
        $expect = $this->sign($check, $appSecret);
        if (strtoupper((string) $sign) !== $expect) {
            Log::warning('[JeepayAbaQr] bad sign', ['expect' => $expect, 'got' => $sign]);
            return false;
        }

        if ((string) ($params['state'] ?? '') !== '2') {
            return false;
        }

        $mchOrderNo = (string) ($params['mchOrderNo'] ?? '');
        $tradeNo = $this->resolveTradeNo($mchOrderNo);

        // 支付成功后清 tip 缓存
        if ($tradeNo !== '') {
            Cache::forget('jeepay_aba_qr_tip:' . $tradeNo);
        }

        return [
            'trade_no' => $tradeNo,
            'callback_no' => $params['payOrderId'] ?? ($params['channelOrderNo'] ?? ''),
            'custom_result' => 'success',
        ];
    }

    /**
     * 尝试统一下单。成功返回 data 数组；「已存在」返回 null；其它错误抛异常。
     * @param bool $throwOnExists 重试单号时「已存在」也抛错
     */
    private function tryUnifiedOrder(
        string $gateway,
        string $appSecret,
        array $baseParams,
        string $mchOrderNo,
        bool $throwOnExists = false
    ): ?array {
        $params = $baseParams;
        $params['mchOrderNo'] = $mchOrderNo;
        $params['reqTime'] = (string) (int) (microtime(true) * 1000);
        $params['sign'] = $this->sign($params, $appSecret);

        $raw = $this->httpPostJson($gateway . '/api/pay/unifiedOrder', $params);
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            Log::error('[JeepayAbaQr] bad response', ['raw' => $raw]);
            throw new ApiException('Jeepay 返回非 JSON');
        }

        if (($json['code'] ?? -1) == 0) {
            $data = $json['data'] ?? [];
            if (is_object($data)) {
                $data = (array) $data;
            }
            return is_array($data) ? $data : [];
        }

        $msg = (string) ($json['msg'] ?? json_encode($json, JSON_UNESCAPED_UNICODE));
        if ($this->isOrderExistsError($msg)) {
            Log::info('[JeepayAbaQr] order exists', ['mchOrderNo' => $mchOrderNo, 'msg' => $msg]);
            if ($throwOnExists) {
                throw new ApiException('Jeepay 下单失败: ' . $msg);
            }
            return null;
        }

        Log::error('[JeepayAbaQr] unifiedOrder fail', $json);
        throw new ApiException('Jeepay 下单失败: ' . $msg);
    }

    /** 查询已存在的支付单，尽量拿到 payData / channelAttach */
    private function queryPayOrder(
        string $gateway,
        string $mchNo,
        string $appId,
        string $appSecret,
        string $mchOrderNo
    ): ?array {
        $params = [
            'mchNo' => $mchNo,
            'appId' => $appId,
            'mchOrderNo' => $mchOrderNo,
            'reqTime' => (string) (int) (microtime(true) * 1000),
            'version' => '1.0',
            'signType' => 'MD5',
        ];
        $params['sign'] = $this->sign($params, $appSecret);

        try {
            $raw = $this->httpPostJson($gateway . '/api/pay/query', $params);
        } catch (\Throwable $e) {
            Log::warning('[JeepayAbaQr] query network fail', ['err' => $e->getMessage()]);
            return null;
        }

        $json = json_decode($raw, true);
        if (!is_array($json) || ($json['code'] ?? -1) != 0) {
            Log::warning('[JeepayAbaQr] query fail', ['raw' => $raw]);
            return null;
        }

        $data = $json['data'] ?? [];
        if (is_object($data)) {
            $data = (array) $data;
        }
        if (!is_array($data) || empty($data)) {
            return null;
        }

        // 已支付则不要再给 tip（由 Xboard 订单状态处理）
        $state = (string) ($data['state'] ?? '');
        if ($state === '2') {
            Log::info('[JeepayAbaQr] query shows already paid', ['mchOrderNo' => $mchOrderNo]);
            // 仍返回 data，前端 tip 会轮询订单；或抛提示
        }

        return $data;
    }

    private function buildTipUrl(
        string $tipPage,
        array $order,
        float $cny,
        int $khrMajor,
        float $rate,
        array $data
    ): string {
        $payDataType = (string) ($data['payDataType'] ?? '');
        $payData = $data['payData'] ?? '';

        $expectAmount = '';
        if (!empty($data['channelAttach'])) {
            $attach = $data['channelAttach'];
            if (is_string($attach)) {
                $decoded = json_decode($attach, true);
            } elseif (is_array($attach)) {
                $decoded = $attach;
            } else {
                $decoded = null;
            }
            if (is_array($decoded) && !empty($decoded['expectAmount'])) {
                $expectAmount = (string) $decoded['expectAmount'];
            }
        }

        $qrContent = '';
        if ($payDataType === 'codeUrl' || $payDataType === '' || $payDataType === 'codeImgUrl') {
            $qrContent = (string) $payData;
        } elseif ($payDataType === 'payUrl' && $payData) {
            // 查单有时只返回 payUrl
            return (string) $payData;
        }

        if ($qrContent === '') {
            // 部分 query 不回 payData：用固定商业码无法恢复；抛错让用户走重试单号
            throw new ApiException('Jeepay 未返回二维码数据，请稍后再点结账');
        }

        $returnUrl = (string) ($order['return_url'] ?? '');
        if ($returnUrl === '') {
            $host = '';
            if (!empty($order['notify_url']) && preg_match('#^(https?://[^/]+)#', (string) $order['notify_url'], $hm)) {
                $host = $hm[1];
            }
            $returnUrl = ($host !== '' ? $host : '') . '/#/order/' . $order['trade_no'];
        }

        $query = [
            'cny' => $this->fmtMoney($cny),
            'khr' => (string) $khrMajor,
            'rate' => (string) $rate,
            'qr' => $qrContent,
            'trade' => (string) $order['trade_no'],
            'return' => $returnUrl,
        ];
        if ($expectAmount !== '') {
            $query['expect'] = $expectAmount;
        }

        $tipUrl = $tipPage . '?' . http_build_query($query);
        Log::info('[JeepayAbaQr] tip page', [
            'trade_no' => $order['trade_no'],
            'pay_khr' => $expectAmount !== '' ? $expectAmount : (string) $khrMajor,
            'tip' => $tipUrl,
        ]);

        return $tipUrl;
    }

    private function normalizeTipPage(string $tipPage): string
    {
        $tipPage = rtrim($tipPage, '/');
        if (!preg_match('/\.(php|html)$/i', $tipPage)) {
            $tipPage = $tipPage . '/aba-khqr-pay.php';
        }
        if (preg_match('/aba-khqr-pay\.html$/i', $tipPage)) {
            $tipPage = (string) preg_replace('/\.html$/i', '.php', $tipPage);
        }
        return $tipPage;
    }

    /** mchOrderNo → Xboard trade_no（去掉重试后缀 R########） */
    private function resolveTradeNo(string $mchOrderNo): string
    {
        if (preg_match('/^(.*)R\d{8,}$/', $mchOrderNo, $m)) {
            return $m[1];
        }
        return $mchOrderNo;
    }

    private function isOrderExistsError(string $msg): bool
    {
        if ($msg === '') {
            return false;
        }
        if (mb_strpos($msg, '已存在') !== false) {
            return true;
        }
        $lower = strtolower($msg);
        return str_contains($lower, 'already exist') || str_contains($lower, 'already exists');
    }

    private function sign(array $params, string $key): string
    {
        $list = [];
        foreach ($params as $k => $v) {
            if ($k === 'sign') {
                continue;
            }
            if ($v === null || $v === '') {
                continue;
            }
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }
            $list[] = $k . '=' . $v . '&';
        }
        usort($list, 'strcasecmp');
        $str = implode('', $list) . 'key=' . $key;
        return strtoupper(md5($str));
    }

    private function fmtMoney(float $n): string
    {
        return number_format($n, 2, '.', '');
    }

    private function httpPostJson(string $url, array $body): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new ApiException('Jeepay 下单网络异常: ' . $err);
        }
        return (string) $raw;
    }
}
