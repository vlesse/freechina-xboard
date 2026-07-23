<?php

namespace Plugin\TokenPay;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use App\Services\Plugin\AbstractPlugin;
use Illuminate\Support\Facades\Log;

/**
 * TokenPay（LightCountry）区块链支付
 *
 * 文档: https://github.com/LightCountry/TokenPay/blob/master/Wiki/docs.md
 * 下单: POST {api}/CreateOrder  JSON
 * 回调: POST NotifyUrl  JSON，成功须响应字符串 ok
 */
class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['TokenPay'] = [
                    'name' => $this->getConfig('display_name', 'TokenPay'),
                    'icon' => $this->getConfig('icon', '🪙'),
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
            'token_pay_url' => [
                'label' => 'TokenPay API 地址',
                'type' => 'string',
                'required' => true,
                'description' => '例如 https://token-pay.xxx.com （末尾不要斜杠）',
            ],
            'token_pay_apitoken' => [
                'label' => '异步通知密钥 / API Token',
                'type' => 'string',
                'required' => true,
                'description' => 'TokenPay 配置文件中的异步通知密钥，用于签名与验签',
            ],
            'token_pay_currency' => [
                'label' => '币种',
                'type' => 'string',
                'required' => true,
                'default' => 'USDT_TRC20',
                'description' => '如 USDT_TRC20、TRX。多币种请各建一条支付方式',
            ],
            'display_name' => [
                'label' => '前台显示名称覆盖',
                'type' => 'string',
                'required' => false,
                'description' => '可选，覆盖支付方式显示名（一般在支付配置的名称里改即可）',
            ],
        ];
    }

    public function pay($order): array
    {
        $apiBase = rtrim((string) $this->getConfig('token_pay_url'), '/');
        $apiToken = (string) $this->getConfig('token_pay_apitoken');
        $currency = trim((string) $this->getConfig('token_pay_currency', 'USDT_TRC20'));

        if ($apiBase === '' || $apiToken === '') {
            throw new ApiException('TokenPay 未配置 API 地址或密钥');
        }
        if ($currency === '') {
            throw new ApiException('TokenPay 币种未配置');
        }

        // Xboard total_amount 为人民币分；TokenPay ActualAmount 为法币元（BaseCurrency 由 TokenPay 配置决定，通常 CNY）
        $actualAmount = round(((int) $order['total_amount']) / 100, 2);
        if ($actualAmount < 0.01) {
            $actualAmount = 0.01;
        }

        $params = [
            'OutOrderId' => (string) $order['trade_no'],
            'OrderUserKey' => (string) ($order['user_id'] ?? $order['trade_no']),
            'ActualAmount' => $actualAmount,
            'Currency' => $currency,
            'NotifyUrl' => (string) $order['notify_url'],
            'RedirectUrl' => (string) $order['return_url'],
        ];

        $params['Signature'] = $this->sign($params, $apiToken);

        $url = $apiBase . '/CreateOrder';
        $raw = $this->httpPostJson($url, $params);
        $result = json_decode($raw);

        if (!$result) {
            Log::error('[TokenPay] bad response', ['raw' => $raw]);
            throw new ApiException('TokenPay 返回非 JSON');
        }

        if (empty($result->success)) {
            $msg = $result->message ?? '创建订单失败';
            Log::error('[TokenPay] CreateOrder fail', ['msg' => $msg, 'raw' => $raw]);
            throw new ApiException('TokenPay: ' . $msg);
        }

        $payUrl = $result->data ?? null;
        if (!$payUrl || !is_string($payUrl)) {
            throw new ApiException('TokenPay 未返回支付链接');
        }

        // type=1 跳转收银台
        return [
            'type' => 1,
            'data' => $payUrl,
        ];
    }

    public function notify($params): array|bool
    {
        // JSON 回调字段可能是嵌套或字符串；统一成一维数组
        if (!is_array($params)) {
            return false;
        }

        $apiToken = (string) $this->getConfig('token_pay_apitoken');
        $sign = $params['Signature'] ?? $params['signature'] ?? '';
        if ($sign === '') {
            Log::warning('[TokenPay] notify missing Signature');
            return false;
        }

        $check = $params;
        unset($check['Signature'], $check['signature']);

        $expect = $this->sign($check, $apiToken);
        if (strtolower((string) $sign) !== strtolower($expect)) {
            Log::warning('[TokenPay] bad signature', ['expect' => $expect, 'got' => $sign]);
            return false;
        }

        // 0 等待 1 已支付 2 过期
        $status = $params['Status'] ?? $params['status'] ?? null;
        if ((string) $status !== '1') {
            return false;
        }

        return [
            'trade_no' => $params['OutOrderId'] ?? $params['outOrderId'] ?? '',
            'callback_no' => $params['Id'] ?? $params['id'] ?? '',
            // TokenPay 要求响应体为字符串 ok
            'custom_result' => 'ok',
        ];
    }

    /**
     * TokenPay 签名：参数按 key ASCII 升序，key=value& 拼接，忽略空值，末尾直接拼密钥，再 MD5
     * 与官方文档 / v2board 插件一致（http_build_query + 密钥）
     */
    private function sign(array $params, string $apiToken): string
    {
        $filtered = [];
        foreach ($params as $k => $v) {
            if ($k === 'Signature' || $k === 'signature') {
                continue;
            }
            if ($v === null || $v === '') {
                continue;
            }
            // 统一转字符串，避免 15 与 15.0 差异过大；金额保持原样
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            } elseif (is_float($v) || is_int($v)) {
                // ActualAmount 等：去掉多余 0 的风险——用与官方示例一致的裸数字字符串
                $v = (string) $v;
            } else {
                $v = (string) $v;
            }
            $filtered[$k] = $v;
        }

        ksort($filtered);
        // 与 v2board 官方插件一致
        $str = stripslashes(urldecode(http_build_query($filtered))) . $apiToken;
        return md5($str);
    }

    private function httpPostJson(string $url, array $body): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: TokenPay-Xboard',
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new ApiException('TokenPay 网络异常: ' . $err);
        }
        if ($code >= 500) {
            throw new ApiException('TokenPay HTTP ' . $code);
        }
        return (string) $raw;
    }
}
