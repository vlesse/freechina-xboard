# 仅安装支付插件（不使用 FreeChina 前端）

适用于：你已经有自己的 **Xboard / 类 Xboard** 站点，只想接入本仓库的支付能力。

联系：**Telegram [https://t.me/lngsuan](https://t.me/lngsuan)**

---

## 包含插件

| 目录 | 支付接口名（后台可选） | 依赖 |
|------|------------------------|------|
| `JeepayAbaQr` | JeepayAbaQr | Jeepay + ABA 个人 KHQR 中转 |
| `JeepayAbaPc` | JeepayAbaPc | Jeepay + ABA PayWay 官方 |
| `JeepayPaypal` | JeepayPaypal | Jeepay + PayPal |
| `JeepayMidtrans` | JeepayMidtrans | Jeepay + Midtrans（MID_PC / IDR） |
| `TokenPay` | TokenPay | 自建 TokenPay |

可选：`public/aba-khqr-pay.html`（仅 KHQR 手输金额说明页需要）。

---

## 环境要求

- 已运行的 **Xboard**（插件系统为 `plugins-core` + `PaymentInterface` 模型，与 cedar2025/Xboard 一致）  
- PHP 8.1+  
- 能访问外网（请求 Jeepay / TokenPay）  

---

## 一键安装插件

```bash
# 本仓库目录
cd freechina-xboard

# 参数：你的 Xboard 根目录（含 plugins-core、artisan 的目录）
bash scripts/install-plugins-only.sh /www/wwwroot/your-xboard
```

脚本会：

1. 复制 5 个支付插件到 `plugins-core/`（含 JeepayMidtrans）  
2. 可选复制 `aba-khqr-pay.html` 到 `public/`  
3. 执行 `php artisan optimize:clear`（插件启用请在后台或按下方 SQL 写入 `v2_plugins`）  

---

## 手动安装

```bash
XBOARD=/www/wwwroot/your-xboard

cp -a overlay/plugins-core/JeepayAbaQr     $XBOARD/plugins-core/
cp -a overlay/plugins-core/JeepayAbaPc     $XBOARD/plugins-core/
cp -a overlay/plugins-core/JeepayPaypal    $XBOARD/plugins-core/
cp -a overlay/plugins-core/JeepayMidtrans  $XBOARD/plugins-core/
cp -a overlay/plugins-core/TokenPay        $XBOARD/plugins-core/

# 可选：KHQR 说明页
cp -a overlay/public/aba-khqr-pay.html   $XBOARD/public/

chown -R www:www $XBOARD/plugins-core $XBOARD/public/aba-khqr-pay.html
cd $XBOARD && php artisan optimize:clear
```

### 数据库启用插件（若后台看不到）

```sql
INSERT INTO v2_plugins (name, code, type, version, is_enabled, config, installed_at, created_at, updated_at)
VALUES
('Jeepay ABA KHQR', 'jeepay_aba_qr', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW()),
('Jeepay ABA PayWay', 'jeepay_aba_pc', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW()),
('Jeepay PayPal', 'jeepay_paypal', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW()),
('Jeepay Midtrans', 'jeepay_midtrans', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW()),
('TokenPay', 'token_pay', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW());
```

若 `code` 已存在则改为 `UPDATE ... SET is_enabled=1`。

---

## 后台配置支付方式

**系统设置 → 支付配置 → 添加**

> **默认对接 FreeChina 已部署的 Jeepay**  
> - 商户后台：https://payment.free--china.com/  
> - 支付网关：`https://pay.free--china.com`  
> 密钥在 payment 后台「商户应用」中复制。

### 1）JeepayAbaQr（个人 KHQR）

| 配置项 | 示例 |
|--------|------|
| 支付接口 | JeepayAbaQr |
| Jeepay支付网关 | `https://pay.free--china.com` |
| mchNo / appId / appSecret | 从 https://payment.free--china.com/ 商户应用复制 |
| wayCode | `ABA_KHQR` |
| 人民币→瑞尔汇率 | `560` |
| 金额说明页URL | `https://你的Xboard域名/aba-khqr-pay.html` |

### 2）JeepayAbaPc（ABA PayWay 官方）

| 配置项 | 示例 |
|--------|------|
| 支付接口 | JeepayAbaPc |
| Jeepay支付网关 | `https://pay.free--china.com` |
| mchNo / appId / appSecret | 从 https://payment.free--china.com/ 复制 |
| wayCode | `ABA_PC` |
| 结算货币 | `USD` 或 `KHR` |
| 汇率 | USD 例 `0.14`；KHR 例 `560` |

### 3）JeepayPaypal

| 配置项 | 示例 |
|--------|------|
| 支付接口 | JeepayPaypal |
| Jeepay支付网关 | `https://pay.free--china.com` |
| mchNo / appId / appSecret | 从 https://payment.free--china.com/ 复制 |
| wayCode | `PP_PC` |
| 结算货币 | `USD` |
| 人民币→美元汇率 | `0.14` |

### 4）JeepayMidtrans（Midtrans）

| 配置项 | 示例 |
|--------|------|
| 支付接口 | JeepayMidtrans |
| Jeepay支付网关 | `https://pay.free--china.com` |
| mchNo / appId / appSecret | 从 https://payment.free--china.com/ 复制 |
| wayCode | `MID_PC` |
| 结算货币 | `IDR` |
| 人民币→印尼盾汇率 | `2200`（1 CNY = 2200 IDR，可按牌价改） |

### 5）TokenPay

| 配置项 | 示例 |
|--------|------|
| 支付接口 | TokenPay |
| API 地址 | `https://tokenpay.example.com`（无尾斜杠） |
| API Token | TokenPay 异步通知密钥 |
| 币种 | `USDT_TRC20` 或 `TRX` |

---

## 不需要改路由

只装插件时 **不必** 替换 `routes/web.php`，也 **不必** 使用 FreeChina 主站 / 登录页。  
用户仍使用你原来的 Xboard 前端下单即可。

---

## 回调地址

Xboard 会自动生成类似：

```text
https://你的域名/api/v1/guest/payment/notify/{PaymentMethod}/{uuid}
```

- Jeepay：下单时作为 `notifyUrl` 传入，无需在 Jeepay 写死  
- TokenPay：下单时作为 `NotifyUrl` 传入；成功响应体为 **`ok`**  

---

## 兼容性说明

- 针对 **cedar2025/Xboard** 插件架构（`plugins-core` + `App\Contracts\PaymentInterface`）  
- 若你的面板是旧版 V2board 的 `app/Payments/*.php` 结构，不能直接复制，需要自行移植或联系改造  

---

## 支持

Telegram：**[https://t.me/lngsuan](https://t.me/lngsuan)**
