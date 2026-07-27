<?php

namespace Plugin\JeepayMidtrans;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use App\Services\Plugin\AbstractPlugin;
use Illuminate\Support\Facades\Log;

/**
 * Xboard → Jeepay 统一下单 → Midtrans (MID_PC)
 *
 * Xboard 金额为人民币分；默认换算为 IDR 后跳转 Midtrans 收银台。
 * Jeepay 网关默认：https://pay.free--china.com
 * 商户后台：https://payment.free--china.com/
 */
class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['JeepayMidtrans'] = [
                    'name' => $this->getConfig('display_name', 'Midtrans'),
                    'icon' => $this->getConfig('icon', '🇮🇩'),
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
                'description' => '默认 https://pay.free--china.com （FreeChina Jeepay）',
                'default' => 'https://pay.free--china.com',
            ],
            'mch_no' => [
                'label' => '商户号 mchNo',
                'type' => 'string',
                'required' => true,
                'description' => '从 https://payment.free--china.com/ 商户应用复制',
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
                'default' => 'MID_PC',
                'description' => 'Jeepay Midtrans 固定 MID_PC',
            ],
            'settle_currency' => [
                'label' => '结算货币',
                'type' => 'string',
                'required' => true,
                'default' => 'IDR',
                'description' => 'Midtrans 印尼通道一般为 IDR',
            ],
            'cny_to_idr_rate' => [
                'label' => '人民币→印尼盾汇率',
                'type' => 'string',
                'required' => true,
                'default' => '2200',
                'description' => '1 CNY = ? IDR。例：2200 表示 100 元 → 220000 盾。汇率变了在此修改。',
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
        $rate = (float) $this->getConfig('cny_to_idr_rate', 2200);
        if ($rate <= 0) {
            throw new ApiException('汇率配置无效');
        }

        $currency = strtoupper(trim((string) $this->getConfig('settle_currency', 'IDR')));
        if ($currency === '') {
            $currency = 'IDR';
        }

        // IDR 通常无小数：向上取整到整盾
        $idr = (int) ceil($cny * $rate);
        if ($idr < 1) {
            $idr = 1;
        }
        // Jeepay amount 为「分」制：主币 * 100
        $jeepayAmount = $idr * 100;

        $gateway = rtrim((string) $this->getConfig('gateway_url'), '/');
        if ($gateway === '') {
            throw new ApiException('Jeepay 网关地址未配置');
        }

        $mchNo = (string) $this->getConfig('mch_no');
        $appId = (string) $this->getConfig('app_id');
        $appSecret = (string) $this->getConfig('app_secret');
        $wayCode = (string) $this->getConfig('way_code', 'MID_PC');
        $prefix = (string) $this->getConfig('product_name', 'XBoard');

        $tradeNo = (string) $order['trade_no'];
        $base = [
            'mchNo' => $mchNo,
            'appId' => $appId,
            'wayCode' => $wayCode,
            'amount' => $jeepayAmount,
            'currency' => $currency,
            'subject' => sprintf('%s %s CNY(≈%s %s)', $prefix, number_format($cny, 2, '.', ''), $idr, $currency),
            'body' => sprintf('CNY %s @ rate %s = %s %s', number_format($cny, 2, '.', ''), $rate, $idr, $currency),
            'notifyUrl' => $order['notify_url'],
            'returnUrl' => $order['return_url'],
            'version' => '1.0',
            'signType' => 'MD5',
        ];

        $payData = $this->unifiedOrReuse($gateway, $appSecret, $base, $tradeNo, '[JeepayMidtrans]');
        if ($payData === '' || $payData === null) {
            throw new ApiException('Jeepay 未返回 Midtrans 支付链接');
        }

        return [
            'type' => 1,
            'data' => $payData,
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
            Log::warning('[JeepayMidtrans] bad sign', ['expect' => $expect, 'got' => $sign]);
            return false;
        }

        if ((string) ($params['state'] ?? '') !== '2') {
            return false;
        }

        $mchOrderNo = (string) ($params['mchOrderNo'] ?? '');
        $tradeNo = preg_match('/^(.*)R\d{8,}$/', $mchOrderNo, $m) ? $m[1] : $mchOrderNo;

        return [
            'trade_no' => $tradeNo,
            'callback_no' => $params['payOrderId'] ?? ($params['channelOrderNo'] ?? ''),
            'custom_result' => 'success',
        ];
    }

    private function unifiedOrReuse(string $gateway, string $appSecret, array $base, string $tradeNo, string $tag): string
    {
        $try = function (string $mchOrderNo) use ($gateway, $appSecret, $base, $tag) {
            $params = $base;
            $params['mchOrderNo'] = $mchOrderNo;
            $params['reqTime'] = (string) (int) (microtime(true) * 1000);
            $params['sign'] = $this->sign($params, $appSecret);
            $raw = $this->httpPostJson($gateway . '/api/pay/unifiedOrder', $params);
            $json = json_decode($raw, true);
            if (!is_array($json)) {
                throw new ApiException('Jeepay 返回非 JSON');
            }
            if (($json['code'] ?? -1) == 0) {
                $data = $json['data'] ?? [];
                if (is_object($data)) {
                    $data = (array) $data;
                }
                return [true, (string) ($data['payData'] ?? ''), false];
            }
            $msg = (string) ($json['msg'] ?? '');
            $exists = (mb_strpos($msg, '已存在') !== false) || (stripos($msg, 'already exist') !== false);
            if ($exists) {
                Log::info($tag . ' order exists', ['mchOrderNo' => $mchOrderNo, 'msg' => $msg]);
                return [false, '', true];
            }
            Log::error($tag . ' unifiedOrder fail', $json);
            throw new ApiException('Jeepay 下单失败: ' . $msg);
        };

        [$ok, $payData, $exists] = $try($tradeNo);
        if ($ok && $payData !== '') {
            return $payData;
        }
        if ($exists) {
            $q = [
                'mchNo' => $base['mchNo'],
                'appId' => $base['appId'],
                'mchOrderNo' => $tradeNo,
                'reqTime' => (string) (int) (microtime(true) * 1000),
                'version' => '1.0',
                'signType' => 'MD5',
            ];
            $q['sign'] = $this->sign($q, $appSecret);
            try {
                $raw = $this->httpPostJson($gateway . '/api/pay/query', $q);
                $json = json_decode($raw, true);
                if (is_array($json) && ($json['code'] ?? -1) == 0) {
                    $data = $json['data'] ?? [];
                    if (is_object($data)) {
                        $data = (array) $data;
                    }
                    $pd = (string) ($data['payData'] ?? '');
                    if ($pd !== '') {
                        return $pd;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning($tag . ' query fail', ['err' => $e->getMessage()]);
            }
            $retryNo = $tradeNo . 'R' . substr((string) time(), -8) . random_int(10, 99);
            [$ok2, $payData2] = $try($retryNo);
            if ($ok2 && $payData2 !== '') {
                return $payData2;
            }
        }
        return '';
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

    private function httpPostJson(string $url, array $body): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
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
