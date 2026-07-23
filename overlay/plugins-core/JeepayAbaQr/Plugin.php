<?php

namespace Plugin\JeepayAbaQr;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use App\Services\Plugin\AbstractPlugin;
use Illuminate\Support\Facades\Log;

/**
 * Xboard → Jeepay 统一下单 → ABA_KHQR 二维码
 *
 * Xboard 订单金额单位：人民币「分」（50 元 = 5000）
 * 下单到 Jeepay 前按汇率换算为 KHR。
 * 因个人固定商业码无法写死金额，支付后跳转说明页，醒目提示用户手输瑞尔金额。
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
                'default' => 'https://free--china.com/aba-khqr-pay.html',
                'description' => '展示「请输入多少瑞尔」的页面完整地址（public/aba-khqr-pay.html）',
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
        $cnyFen = (int) $order['total_amount'];
        $cny = round($cnyFen / 100, 2);
        $rate = (float) $this->getConfig('cny_to_khr_rate', 560);
        if ($rate <= 0) {
            throw new ApiException('汇率配置无效');
        }

        // 瑞尔一般按整数收取（ceil 避免少收）
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
        $tipPage = rtrim((string) $this->getConfig('tip_page_url', 'https://free--china.com/aba-khqr-pay.html'), '/');
        // 允许填到 .html 或目录；统一规范
        if (!preg_match('/\.html$/i', $tipPage)) {
            $tipPage = $tipPage . '/aba-khqr-pay.html';
        }

        $params = [
            'mchNo' => $mchNo,
            'appId' => $appId,
            'mchOrderNo' => $order['trade_no'],
            'wayCode' => $wayCode,
            'amount' => $jeepayAmount,
            'currency' => 'KHR',
            'subject' => sprintf('%s %s CNY(≈%s KHR)', $prefix, $this->fmtMoney($cny), $khrMajor),
            'body' => sprintf('CNY %s @ rate %s = KHR %s', $this->fmtMoney($cny), $rate, $khrMajor),
            'notifyUrl' => $order['notify_url'],
            'returnUrl' => $order['return_url'],
            'reqTime' => (string) (int) (microtime(true) * 1000),
            'version' => '1.0',
            'signType' => 'MD5',
        ];
        $params['sign'] = $this->sign($params, $appSecret);

        $raw = $this->httpPostJson($gateway . '/api/pay/unifiedOrder', $params);
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            Log::error('[JeepayAbaQr] bad response', ['raw' => $raw]);
            throw new ApiException('Jeepay 返回非 JSON');
        }

        if (($json['code'] ?? -1) != 0) {
            $msg = $json['msg'] ?? json_encode($json, JSON_UNESCAPED_UNICODE);
            Log::error('[JeepayAbaQr] unifiedOrder fail', $json);
            throw new ApiException('Jeepay 下单失败: ' . $msg);
        }

        $data = $json['data'] ?? [];
        if (is_object($data)) {
            $data = (array) $data;
        }
        $payDataType = $data['payDataType'] ?? '';
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
            Log::info('[JeepayAbaQr] channelAttach', [
                'trade_no' => $order['trade_no'],
                'cny' => $cny,
                'khr' => $khrMajor,
                'expect' => $expectAmount,
                'attach' => $data['channelAttach'],
            ]);
        }

        // 拿到二维码内容（codeUrl 为 KHQR 字符串；codeImgUrl 则退回跳转图片不理想，优先 codeUrl）
        $qrContent = '';
        if ($payDataType === 'codeUrl' || $payDataType === '' || $payDataType === 'codeImgUrl') {
            $qrContent = (string) $payData;
        } elseif ($payDataType === 'payUrl' && $payData) {
            // 兜底：直接跳转 Jeepay 返回的 URL
            return ['type' => 1, 'data' => $payData];
        }

        if ($qrContent === '') {
            throw new ApiException('Jeepay 未返回二维码数据');
        }

        // 用户必须手输的金额：优先中转 expectAmount（含尾数），否则整数瑞尔
        $payKhr = $expectAmount !== '' ? $expectAmount : (string) $khrMajor;

        $query = [
            'cny' => $this->fmtMoney($cny),
            'khr' => (string) $khrMajor,
            'rate' => (string) $rate,
            'qr' => $qrContent,
            'trade' => (string) $order['trade_no'],
        ];
        if ($expectAmount !== '') {
            $query['expect'] = $expectAmount;
        }

        // 说明页：醒目显示应付瑞尔 + 汇率算法 + 二维码
        $tipUrl = $tipPage . '?' . http_build_query($query);

        Log::info('[JeepayAbaQr] tip page', [
            'trade_no' => $order['trade_no'],
            'pay_khr' => $payKhr,
            'tip' => $tipUrl,
        ]);

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

        return [
            'trade_no' => $params['mchOrderNo'] ?? '',
            'callback_no' => $params['payOrderId'] ?? ($params['channelOrderNo'] ?? ''),
            'custom_result' => 'success',
        ];
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
