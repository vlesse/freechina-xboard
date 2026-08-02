<?php

namespace App\Payments;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;

/** TokenPay 链上支付（USDT/TRX 等） */
class TokenPay
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'token_pay_url' => [
                'label' => 'TokenPay API 地址',
                'description' => '例如 https://token-pay.xxx.com（无末尾斜杠）',
                'type' => 'input',
            ],
            'token_pay_apitoken' => [
                'label' => '异步通知密钥',
                'description' => 'TokenPay 配置中的密钥',
                'type' => 'input',
            ],
            'token_pay_currency' => [
                'label' => '币种',
                'description' => '如 USDT_TRC20、TRX',
                'type' => 'input',
            ],
        ];
    }

    public function pay($order)
    {
        $apiBase = rtrim((string) ($this->config['token_pay_url'] ?? ''), '/');
        $apiToken = (string) ($this->config['token_pay_apitoken'] ?? '');
        $currency = trim((string) ($this->config['token_pay_currency'] ?? 'USDT_TRC20'));
        if ($apiBase === '' || $apiToken === '') {
            throw new ApiException('TokenPay 未配置');
        }
        $actualAmount = max(0.01, round(((int) $order['total_amount']) / 100, 2));
        $params = [
            'OutOrderId' => (string) $order['trade_no'],
            'OrderUserKey' => (string) ($order['user_id'] ?? $order['trade_no']),
            'ActualAmount' => $actualAmount,
            'Currency' => $currency,
            'NotifyUrl' => (string) $order['notify_url'],
            'RedirectUrl' => (string) $order['return_url'],
        ];
        $params['Signature'] = $this->sign($params, $apiToken);
        $raw = $this->httpPostJson($apiBase . '/CreateOrder', $params);
        $result = json_decode($raw);
        if (!$result) {
            throw new ApiException('TokenPay 返回非 JSON');
        }
        if (empty($result->success)) {
            throw new ApiException('TokenPay: ' . ($result->message ?? '创建订单失败'));
        }
        $payUrl = $result->data ?? null;
        if (!$payUrl || !is_string($payUrl)) {
            throw new ApiException('TokenPay 未返回支付链接');
        }
        return ['type' => 1, 'data' => $payUrl];
    }

    public function notify($params)
    {
        if (!is_array($params)) {
            return false;
        }
        $apiToken = (string) ($this->config['token_pay_apitoken'] ?? '');
        $sign = $params['Signature'] ?? $params['signature'] ?? '';
        if ($sign === '') {
            return false;
        }
        $check = $params;
        unset($check['Signature'], $check['signature']);
        $expect = $this->sign($check, $apiToken);
        if (strtolower((string) $sign) !== strtolower($expect)) {
            return false;
        }
        $status = $params['Status'] ?? $params['status'] ?? null;
        if ((string) $status !== '1') {
            return false;
        }
        return [
            'trade_no' => $params['OutOrderId'] ?? $params['outOrderId'] ?? '',
            'callback_no' => $params['Id'] ?? $params['id'] ?? '',
            'custom_result' => 'ok',
        ];
    }

    private function sign($params, $apiToken)
    {
        $filtered = [];
        foreach ($params as $k => $v) {
            if ($k === 'Signature' || $k === 'signature') {
                continue;
            }
            if ($v === null || $v === '') {
                continue;
            }
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            } else {
                $v = (string) $v;
            }
            $filtered[$k] = $v;
        }
        ksort($filtered);
        $str = '';
        foreach ($filtered as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }
        $str = rtrim($str, '&') . $apiToken;
        return md5($str);
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
            throw new ApiException('TokenPay 网络异常: ' . $err);
        }
        return (string) $raw;
    }
}
