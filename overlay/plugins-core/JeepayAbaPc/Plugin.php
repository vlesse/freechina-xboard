<?php

namespace Plugin\JeepayAbaPc;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use App\Services\Plugin\AbstractPlugin;
use Illuminate\Support\Facades\Log;

/**
 * Xboard → Jeepay 统一下单 → ABA_PC 官方收银台（payUrl 跳转）
 *
 * Xboard 金额为人民币分；按配置汇率换算为 ABA 结算币（默认 KHR，也可 USD）后下单。
 */
class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['JeepayAbaPc'] = [
                    'name' => $this->getConfig('display_name', 'ABA PayWay'),
                    'icon' => $this->getConfig('icon', '🏦'),
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
                'description' => '例如 https://pay.free--china.com',
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
                'default' => 'ABA_PC',
                'description' => '官方 ABA 收银台：ABA_PC',
            ],
            'settle_currency' => [
                'label' => 'ABA结算货币',
                'type' => 'string',
                'required' => true,
                'default' => 'USD',
                'description' => '官方 PayWay 常用 USD；也可 KHR（须与商户开通币种一致）',
            ],
            'cny_to_settle_rate' => [
                'label' => '人民币→结算币汇率',
                'type' => 'string',
                'required' => true,
                'default' => '0.14',
                'description' => '1 CNY = ? 结算币。USD 例 0.14；KHR 例 560。汇率变了在此改。',
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
        $rate = (float) $this->getConfig('cny_to_settle_rate', 560);
        if ($rate <= 0) {
            throw new ApiException('汇率配置无效');
        }

        $currency = strtoupper(trim((string) $this->getConfig('settle_currency', 'USD')));
        if (!in_array($currency, ['KHR', 'USD'], true)) {
            $currency = 'USD';
        }

        if ($currency === 'KHR') {
            $major = (int) ceil($cny * $rate);
            if ($major < 1) {
                $major = 1;
            }
            $jeepayAmount = $major * 100;
            $majorText = (string) $major;
        } else {
            // USD 保留两位小数
            $major = round($cny * $rate, 2);
            if ($major < 0.01) {
                $major = 0.01;
            }
            $jeepayAmount = (int) round($major * 100);
            $majorText = number_format($major, 2, '.', '');
        }

        $gateway = rtrim((string) $this->getConfig('gateway_url'), '/');
        if ($gateway === '') {
            throw new ApiException('Jeepay 网关地址未配置');
        }
        $mchNo = (string) $this->getConfig('mch_no');
        $appId = (string) $this->getConfig('app_id');
        $appSecret = (string) $this->getConfig('app_secret');
        $wayCode = (string) $this->getConfig('way_code', 'ABA_PC');
        $prefix = (string) $this->getConfig('product_name', 'XBoard');

        $params = [
            'mchNo' => $mchNo,
            'appId' => $appId,
            'mchOrderNo' => $order['trade_no'],
            'wayCode' => $wayCode,
            'amount' => $jeepayAmount,
            'currency' => $currency,
            'subject' => sprintf('%s %s CNY(≈%s %s)', $prefix, number_format($cny, 2, '.', ''), $majorText, $currency),
            'body' => sprintf('CNY %s @ rate %s = %s %s', number_format($cny, 2, '.', ''), $rate, $majorText, $currency),
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
            Log::error('[JeepayAbaPc] bad response', ['raw' => $raw]);
            throw new ApiException('Jeepay 返回非 JSON');
        }

        if (($json['code'] ?? -1) != 0) {
            $msg = $json['msg'] ?? json_encode($json, JSON_UNESCAPED_UNICODE);
            Log::error('[JeepayAbaPc] unifiedOrder fail', $json);
            throw new ApiException('Jeepay 下单失败: ' . $msg);
        }

        $data = $json['data'] ?? [];
        if (is_object($data)) {
            $data = (array) $data;
        }
        $payDataType = $data['payDataType'] ?? '';
        $payData = $data['payData'] ?? '';

        if ($payData === '' || $payData === null) {
            throw new ApiException('Jeepay 未返回支付链接');
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
            Log::warning('[JeepayAbaPc] bad sign', ['expect' => $expect, 'got' => $sign]);
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
