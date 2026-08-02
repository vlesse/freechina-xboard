<?php

namespace App\Payments;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 经典 Xboard / v2board 风格：app/Payments/JeepayAbaQr.php
 * Jeepay ABA 个人 KHQR + 说明页
 */
class JeepayAbaQr
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'gateway_url' => [
                'label' => 'Jeepay支付网关',
                'description' => '例如 https://pay.free--china.com （不要末尾斜杠）',
                'type' => 'input',
            ],
            'mch_no' => [
                'label' => '商户号 mchNo',
                'description' => '',
                'type' => 'input',
            ],
            'app_id' => [
                'label' => '应用ID appId',
                'description' => '',
                'type' => 'input',
            ],
            'app_secret' => [
                'label' => '应用密钥 appSecret',
                'description' => '',
                'type' => 'input',
            ],
            'way_code' => [
                'label' => '支付方式 wayCode',
                'description' => '固定 ABA_KHQR',
                'type' => 'input',
            ],
            'cny_to_khr_rate' => [
                'label' => '人民币→瑞尔汇率',
                'description' => '1 CNY = ? KHR，例 560',
                'type' => 'input',
            ],
            'tip_page_url' => [
                'label' => '金额说明页URL',
                'description' => '推荐 https://你的域名/aba-khqr-pay.php',
                'type' => 'input',
            ],
            'product_name' => [
                'label' => '商品标题前缀',
                'description' => '',
                'type' => 'input',
            ],
        ];
    }

    public function pay($order)
    {
        $tradeNo = (string) $order['trade_no'];
        $cacheKey = 'jeepay_aba_qr_tip:' . $tradeNo;

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            Log::info('[JeepayAbaQr] reuse cached tip', ['trade_no' => $tradeNo]);
            return ['type' => 1, 'data' => $cached];
        }

        $cnyFen = (int) $order['total_amount'];
        $cny = round($cnyFen / 100, 2);
        $rate = (float) ($this->config['cny_to_khr_rate'] ?? 560);
        if ($rate <= 0) {
            throw new ApiException('汇率配置无效');
        }

        $khrMajor = (int) ceil($cny * $rate);
        if ($khrMajor < 1) {
            $khrMajor = 1;
        }
        $jeepayAmount = $khrMajor * 100;

        $gateway = rtrim((string) ($this->config['gateway_url'] ?? ''), '/');
        $mchNo = (string) ($this->config['mch_no'] ?? '');
        $appId = (string) ($this->config['app_id'] ?? '');
        $appSecret = (string) ($this->config['app_secret'] ?? '');
        $wayCode = (string) ($this->config['way_code'] ?? 'ABA_KHQR');
        $prefix = (string) ($this->config['product_name'] ?? 'XBoard');
        $tipPage = $this->normalizeTipPage((string) ($this->config['tip_page_url'] ?? ''));

        if ($gateway === '' || $mchNo === '' || $appId === '' || $appSecret === '') {
            throw new ApiException('Jeepay 配置不完整');
        }
        if ($tipPage === '') {
            throw new ApiException('请配置金额说明页 URL（tip_page_url）');
        }

        $baseParams = [
            'mchNo' => $mchNo,
            'appId' => $appId,
            'wayCode' => $wayCode ?: 'ABA_KHQR',
            'amount' => $jeepayAmount,
            'currency' => 'KHR',
            'subject' => sprintf('%s %s CNY(≈%s KHR)', $prefix, $this->fmtMoney($cny), $khrMajor),
            'body' => sprintf('CNY %s @ rate %s = KHR %s', $this->fmtMoney($cny), $rate, $khrMajor),
            'notifyUrl' => $order['notify_url'],
            'returnUrl' => $order['return_url'],
            'version' => '1.0',
            'signType' => 'MD5',
        ];

        $data = $this->tryUnifiedOrder($gateway, $appSecret, $baseParams, $tradeNo);
        if ($data === null) {
            $data = $this->queryPayOrder($gateway, $mchNo, $appId, $appSecret, $tradeNo);
        }
        if ($data === null) {
            $retryNo = $tradeNo . 'R' . substr((string) time(), -8) . random_int(10, 99);
            $data = $this->tryUnifiedOrder($gateway, $appSecret, $baseParams, $retryNo, true);
            if ($data === null) {
                throw new ApiException('Jeepay 下单失败: 无法创建或查询支付单');
            }
        }

        $tipUrl = $this->buildTipUrl($tipPage, $order, $cny, $khrMajor, $rate, $data);
        Cache::put($cacheKey, $tipUrl, now()->addHours(6));

        return ['type' => 1, 'data' => $tipUrl];
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
        $expect = $this->sign($check, $appSecret);
        if (strtoupper((string) $sign) !== $expect) {
            return false;
        }
        if ((string) ($params['state'] ?? '') !== '2') {
            return false;
        }
        $mchOrderNo = (string) ($params['mchOrderNo'] ?? '');
        $tradeNo = $this->resolveTradeNo($mchOrderNo);
        if ($tradeNo !== '') {
            Cache::forget('jeepay_aba_qr_tip:' . $tradeNo);
        }
        return [
            'trade_no' => $tradeNo,
            'callback_no' => $params['payOrderId'] ?? ($params['channelOrderNo'] ?? ''),
            'custom_result' => 'success',
        ];
    }

    private function tryUnifiedOrder($gateway, $appSecret, $baseParams, $mchOrderNo, $throwOnExists = false)
    {
        $params = $baseParams;
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
            return is_array($data) ? $data : [];
        }
        $msg = (string) ($json['msg'] ?? '');
        if (mb_strpos($msg, '已存在') !== false || stripos($msg, 'already exist') !== false) {
            if ($throwOnExists) {
                throw new ApiException('Jeepay 下单失败: ' . $msg);
            }
            return null;
        }
        throw new ApiException('Jeepay 下单失败: ' . $msg);
    }

    private function queryPayOrder($gateway, $mchNo, $appId, $appSecret, $mchOrderNo)
    {
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
            return null;
        }
        $json = json_decode($raw, true);
        if (!is_array($json) || ($json['code'] ?? -1) != 0) {
            return null;
        }
        $data = $json['data'] ?? [];
        if (is_object($data)) {
            $data = (array) $data;
        }
        return is_array($data) && !empty($data) ? $data : null;
    }

    private function buildTipUrl($tipPage, $order, $cny, $khrMajor, $rate, $data)
    {
        $payDataType = (string) ($data['payDataType'] ?? '');
        $payData = $data['payData'] ?? '';
        $expectAmount = '';
        if (!empty($data['channelAttach'])) {
            $attach = $data['channelAttach'];
            $decoded = is_string($attach) ? json_decode($attach, true) : (is_array($attach) ? $attach : null);
            if (is_array($decoded) && !empty($decoded['expectAmount'])) {
                $expectAmount = (string) $decoded['expectAmount'];
            }
        }
        $qrContent = '';
        if ($payDataType === 'codeUrl' || $payDataType === '' || $payDataType === 'codeImgUrl') {
            $qrContent = (string) $payData;
        } elseif ($payDataType === 'payUrl' && $payData) {
            return (string) $payData;
        }
        if ($qrContent === '') {
            throw new ApiException('Jeepay 未返回二维码数据');
        }
        $returnUrl = (string) ($order['return_url'] ?? '');
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
        return $tipPage . '?' . http_build_query($query);
    }

    private function normalizeTipPage($tipPage)
    {
        $tipPage = rtrim((string) $tipPage, '/');
        if ($tipPage === '') {
            return '';
        }
        if (!preg_match('/\.(php|html)$/i', $tipPage)) {
            $tipPage .= '/aba-khqr-pay.php';
        }
        if (preg_match('/aba-khqr-pay\.html$/i', $tipPage)) {
            $tipPage = preg_replace('/\.html$/i', '.php', $tipPage);
        }
        return $tipPage;
    }

    private function resolveTradeNo($mchOrderNo)
    {
        if (preg_match('/^(.*)R\d{8,}$/', $mchOrderNo, $m)) {
            return $m[1];
        }
        return $mchOrderNo;
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

    private function fmtMoney($n)
    {
        return number_format((float) $n, 2, '.', '');
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
