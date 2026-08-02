<?php

namespace App\Payments;

use App\Exceptions\ApiException;

/** Jeepay Midtrans MID_PC */
class JeepayMidtrans
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'gateway_url' => ['label' => 'Jeepay支付网关', 'description' => 'https://pay.free--china.com', 'type' => 'input'],
            'mch_no' => ['label' => '商户号 mchNo', 'description' => '', 'type' => 'input'],
            'app_id' => ['label' => '应用ID appId', 'description' => '', 'type' => 'input'],
            'app_secret' => ['label' => '应用密钥 appSecret', 'description' => '', 'type' => 'input'],
            'way_code' => ['label' => 'wayCode', 'description' => 'MID_PC', 'type' => 'input'],
            'cny_to_idr_rate' => ['label' => '人民币→印尼盾汇率', 'description' => '默认约 2200', 'type' => 'input'],
            'product_name' => ['label' => '商品标题前缀', 'description' => '', 'type' => 'input'],
        ];
    }

    public function pay($order)
    {
        $cny = round(((int) $order['total_amount']) / 100, 2);
        $rate = (float) ($this->config['cny_to_idr_rate'] ?? 2200);
        if ($rate <= 0) {
            throw new ApiException('汇率配置无效');
        }
        $idr = max(1, (int) ceil($cny * $rate));
        $jeepayAmount = $idr * 100;
        $gateway = rtrim((string) ($this->config['gateway_url'] ?? ''), '/');
        $base = [
            'mchNo' => (string) ($this->config['mch_no'] ?? ''),
            'appId' => (string) ($this->config['app_id'] ?? ''),
            'wayCode' => (string) ($this->config['way_code'] ?? 'MID_PC'),
            'amount' => $jeepayAmount,
            'currency' => 'IDR',
            'subject' => sprintf('%s %s CNY(≈%s IDR)', $this->config['product_name'] ?? 'XBoard', number_format($cny, 2, '.', ''), $idr),
            'body' => sprintf('CNY %s @ %s = IDR %s', number_format($cny, 2, '.', ''), $rate, $idr),
            'notifyUrl' => $order['notify_url'],
            'returnUrl' => $order['return_url'],
            'version' => '1.0',
            'signType' => 'MD5',
        ];
        $payData = $this->unifiedOrReuse($gateway, (string) ($this->config['app_secret'] ?? ''), $base, (string) $order['trade_no']);
        if ($payData === '') {
            throw new ApiException('Jeepay 未返回 Midtrans 支付链接');
        }
        return ['type' => 1, 'data' => $payData];
    }

    public function notify($params)
    {
        $appSecret = (string) ($this->config['app_secret'] ?? '');
        $sign = $params['sign'] ?? '';
        if ($sign === '') {
            return false;
        }
        $check = $params;
        unset($check['sign']);
        if (strtoupper((string) $sign) !== $this->sign($check, $appSecret)) {
            return false;
        }
        if ((string) ($params['state'] ?? '') !== '2') {
            return false;
        }
        $mch = (string) ($params['mchOrderNo'] ?? '');
        $tradeNo = preg_match('/^(.*)R\d{8,}$/', $mch, $m) ? $m[1] : $mch;
        return [
            'trade_no' => $tradeNo,
            'callback_no' => $params['payOrderId'] ?? ($params['channelOrderNo'] ?? ''),
            'custom_result' => 'success',
        ];
    }

    private function unifiedOrReuse($gateway, $appSecret, $base, $tradeNo)
    {
        $try = function ($mchOrderNo) use ($gateway, $appSecret, $base) {
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
                return [false, '', true];
            }
            throw new ApiException('Jeepay 下单失败: ' . $msg);
        };
        [$ok, $payData, $exists] = $try($tradeNo);
        if ($ok && $payData !== '') {
            return $payData;
        }
        if ($exists) {
            $retryNo = $tradeNo . 'R' . substr((string) time(), -8) . random_int(10, 99);
            [$ok2, $payData2] = $try($retryNo);
            if ($ok2 && $payData2 !== '') {
                return $payData2;
            }
        }
        return '';
    }

    private function sign($params, $key)
    {
        $list = [];
        foreach ($params as $k => $v) {
            if ($k === 'sign' || $v === null || $v === '') {
                continue;
            }
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }
            $list[] = $k . '=' . $v . '&';
        }
        usort($list, 'strcasecmp');
        return strtoupper(md5(implode('', $list) . 'key=' . $key));
    }

    private function httpPostJson($url, $body)
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
            throw new ApiException('Jeepay 网络异常: ' . $err);
        }
        return (string) $raw;
    }
}
