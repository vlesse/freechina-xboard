# 只安装支付插件（小白版）

联系：**Telegram [https://t.me/lngsuan](https://t.me/lngsuan)**

---

## 这份文档适合谁？

| 适合 | 不适合 |
|------|--------|
| 你 **已经有一个能打开的 Xboard** | 服务器上还没有 Xboard（请看 [DEPLOY.md](DEPLOY.md) **路径 A**） |
| 你只想加支付（ABA / PayPal / Midtrans / TokenPay） | 你想换成 FreeChina 首页 + 登录页（请看 DEPLOY **路径 B-1** `install-overlay.sh`） |
| 你想 **保留自己现在的前端** | — |

**一句话：** 这里 **不会** 安装整站 Xboard，也 **不会** 换你的首页；只是把支付相关文件拷进你现有的 Xboard 目录。

---

## 开始前请确认

1. 能用浏览器打开你的 Xboard 用户端  
2. 知道服务器上的 **Xboard 根目录**（里面有 `artisan` 文件）  
   - 宝塔常见：`/www/wwwroot/你的域名`  
3. 有 SSH 权限，能执行命令  

---

## 第一步：下载本仓库

在服务器上任意目录执行（示例放到 `/root`）：

```bash
cd /root
git clone https://github.com/vlesse/freechina-xboard.git
cd freechina-xboard
```

---

## 第二步：运行「仅插件」安装脚本

把下面路径改成 **你自己的 Xboard 根目录**：

```bash
# 示例：你的 Xboard 在 /www/wwwroot/panel.example.com
bash scripts/install-plugins-only.sh /www/wwwroot/panel.example.com
```

### 脚本会做什么？

1. 把 5 个支付插件复制到：`你的Xboard/plugins-core/`  
2. 复制付款说明页：`public/aba-khqr-pay.html`  
3. 复制本地二维码库：`public/qrcode.min.js`（避免国外 CDN 导致二维码不显示）  
4. 尝试清理 Laravel 缓存  

### 脚本 **不会** 做什么？

- 不会安装 PHP / MySQL  
- 不会改你的首页、登录页  
- 不会自动在后台「点启用」——你还要在后台配置支付  

---

## 第三步：清理缓存（建议再执行一次）

```bash
cd /www/wwwroot/panel.example.com   # 改成你的目录
php artisan optimize:clear
```

---

## 第四步：后台启用插件

1. 登录 **Xboard 管理后台**  
2. 打开 **插件**  
3. 启用：

| code | 用途 |
|------|------|
| `jeepay_aba_qr` | ABA 个人 KHQR |
| `jeepay_aba_pc` | ABA PayWay |
| `jeepay_paypal` | PayPal |
| `jeepay_midtrans` | Midtrans |
| `token_pay` | TokenPay（可选） |

### 若后台完全没有这些插件

在 MySQL 里执行（`code` 已存在会报错，可忽略或改 UPDATE）：

```sql
INSERT INTO v2_plugins (name, code, type, version, is_enabled, config, installed_at, created_at, updated_at)
VALUES
('Jeepay ABA KHQR', 'jeepay_aba_qr', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW()),
('Jeepay ABA PayWay', 'jeepay_aba_pc', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW()),
('Jeepay PayPal', 'jeepay_paypal', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW()),
('Jeepay Midtrans', 'jeepay_midtrans', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW()),
('TokenPay', 'token_pay', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW());
```

然后刷新后台插件页。

---

## 第五步：添加支付方式（对接 Jeepay）

### 5.1 准备密钥（FreeChina 现成 Jeepay）

1. 打开 https://payment.free--china.com/ 登录  
2. 找到 **商户应用**  
3. 复制：`mchNo`、`appId`、`appSecret`  

支付网关统一填：

```text
https://pay.free--china.com
```

（不要末尾斜杠 `/`）

### 5.2 在 Xboard 里添加

**系统设置 → 支付配置 → 添加**

#### 示例 1：ABA 个人 KHQR

| 配置项 | 填写示例 |
|--------|----------|
| 显示名称 | 支付宝扫码 |
| 支付接口 | **JeepayAbaQr** |
| Jeepay支付网关 | `https://pay.free--china.com` |
| mchNo / appId / appSecret | 从 payment 后台复制 |
| wayCode | `ABA_KHQR` |
| 人民币→瑞尔汇率 | `560`（按你实际改） |
| 金额说明页 URL | `https://你的域名/aba-khqr-pay.html` |

#### 示例 2～4

| 接口 | wayCode | 其它 |
|------|---------|------|
| JeepayAbaPc | `ABA_PC` | 结算币 USD/KHR + 汇率 |
| JeepayPaypal | `PP_PC` | `cny_to_usd_rate` 如 0.14 |
| JeepayMidtrans | `MID_PC` | `cny_to_idr_rate` 默认 2200 |

保存后，到用户端 **下单 → 结账** 测试。

---

## 手动安装（不想用脚本时）

```bash
XBOARD=/www/wwwroot/你的xboard目录

cp -a overlay/plugins-core/JeepayAbaQr     $XBOARD/plugins-core/
cp -a overlay/plugins-core/JeepayAbaPc     $XBOARD/plugins-core/
cp -a overlay/plugins-core/JeepayPaypal    $XBOARD/plugins-core/
cp -a overlay/plugins-core/JeepayMidtrans  $XBOARD/plugins-core/
cp -a overlay/plugins-core/TokenPay        $XBOARD/plugins-core/

cp -a overlay/public/aba-khqr-pay.html     $XBOARD/public/
cp -a overlay/public/qrcode.min.js         $XBOARD/public/

chown -R www:www $XBOARD/plugins-core \
  $XBOARD/public/aba-khqr-pay.html \
  $XBOARD/public/qrcode.min.js

cd $XBOARD && php artisan optimize:clear
```

---

## 回调地址说明

Xboard 会自动生成类似：

```text
https://你的域名/api/v1/guest/payment/notify/{支付方式名}/{uuid}
```

- Jeepay：下单时作为 `notifyUrl` 传入，一般 **不用** 在 Jeepay 里写死  
- TokenPay：成功时接口需返回纯文本 `ok`（本插件已处理）  

---

## 兼容性

- 针对 **cedar2025/Xboard** 的 `plugins-core` + `PaymentInterface`  
- 老式 V2board 的 `app/Payments/*.php` **不能** 直接复制，需另做移植  

---

## 常见问题

**Q：脚本提示不是 Xboard 目录？**  
A：路径要指到含 `artisan` 的那一层，不要指到 `public` 里面。

**Q：结账后说明页没有二维码？**  
A：确认 `public/qrcode.min.js` 已复制；浏览器强制刷新；说明页 URL 配置正确。

**Q：后台没有支付接口可选？**  
A：插件未启用；先做第四步。

---

## 支持

Telegram：**[https://t.me/lngsuan](https://t.me/lngsuan)**  
