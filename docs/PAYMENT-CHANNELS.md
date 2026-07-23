# 支付通道说明

Telegram：**[https://t.me/lngsuan](https://t.me/lngsuan)**

---

## 总览

| 通道 | 用户体验 | 订单金额 | 后端 |
|------|----------|----------|------|
| JeepayAbaQr | 跳转说明页 + 扫固定 KHQR，手输瑞尔 | Xboard 人民币 → 换算 KHR | Jeepay `ABA_KHQR` |
| JeepayAbaPc | 跳转 ABA 官方收银台 | 人民币 → USD/KHR | Jeepay `ABA_PC` |
| JeepayPaypal | 跳转 PayPal | 人民币 → USD | Jeepay `PP_PC` |
| JeepayMidtrans | 跳转 Midtrans 收银台 | 人民币 → IDR | Jeepay `MID_PC` |
| TokenPay | 跳转 TokenPay 收银台 / 地址 | 人民币法币金额 | TokenPay `/CreateOrder` |

---

## 汇率

全部在 **Xboard 支付配置** 中修改，无需改代码：

| 通道 | 配置字段 | 示例 |
|------|----------|------|
| ABA KHQR | `cny_to_khr_rate` | `560`（1 CNY = 560 KHR） |
| ABA PayWay | `cny_to_settle_rate` + `settle_currency` | USD 用 `0.14`；KHR 用 `560` |
| PayPal | `cny_to_usd_rate` | `0.14` |
| Midtrans | `cny_to_idr_rate` | `2200`（1 CNY = 2200 IDR，按牌价改） |
| TokenPay | 无 Xboard 汇率 | 法币金额 = 订单元；链上数量由 TokenPay 按自身行情计算 |

**新订单**使用新汇率；已创建未支付订单不会自动重算。

---

## Jeepay 统一约定（FreeChina 已部署）

| 项目 | 地址 |
|------|------|
| **商户后台** | **https://payment.free--china.com/** |
| **支付网关**（插件 `gateway_url`） | **https://pay.free--china.com** |

- 接口：`POST https://pay.free--china.com/api/pay/unifiedOrder`  
- 签名：MD5 大写（Jeepay 官方算法）  
- 异步通知：`state=2` 为成功  
- 商户 `mchNo` / `appId` / `appSecret`：登录 [payment.free--china.com](https://payment.free--china.com/) 在商户应用中查看  

通道需在该 Jeepay 实例上已启用：`ABA_KHQR` / `ABA_PC` / `PP_PC` / `MID_PC`。

---

## TokenPay 约定

- 创建订单：`POST {API}/CreateOrder`  
- 签名：参数 ASCII 排序 + 密钥 + MD5  
- 回调成功响应：**纯文本 `ok`**  
- 币种示例：`USDT_TRC20`、`TRX`  

文档：https://github.com/LightCountry/TokenPay/blob/master/Wiki/docs.md  

---

## ABA 个人 KHQR 说明页

文件：`public/aba-khqr-pay.html`  

展示：

- 应付瑞尔（大号）  
- 汇率与计算公式  
- 二维码  
- 一键复制金额  

插件配置 `tip_page_url` 指向：

```text
https://你的域名/aba-khqr-pay.html
```
