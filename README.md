# FreeChina Xboard

基于 [Xboard](https://github.com/cedar2025/Xboard) 的二次定制发行版，提供：

- **Web3 风格官网主站** + 现代化登录 / 注册页  
- **Jeepay 支付插件**：ABA 个人 KHQR、ABA PayWay 官方、PayPal  
- **TokenPay 支付插件**：USDT-TRC20 / TRX 等链上支付  
- 与 [Jeepay](https://github.com/jeequan/jeepay) / TokenPay 等收款系统配合使用  

联系 / 技术交流：**Telegram → [https://t.me/lngsuan](https://t.me/lngsuan)**

---

## 目录

| 文档 | 说明 |
|------|------|
| [docs/DEPLOY.md](docs/DEPLOY.md) | **完整部署**（推荐）：一键脚本 + 手动步骤、环境要求、配套系统 |
| [docs/PLUGINS-ONLY.md](docs/PLUGINS-ONLY.md) | **只装支付插件**：不用本仓库前端，只把 4 个支付通道接到你现有的 Xboard |
| [docs/PAYMENT-CHANNELS.md](docs/PAYMENT-CHANNELS.md) | 各支付通道说明、汇率配置、回调注意点 |

---

## 功能一览

### 前端

| 路径 | 说明 |
|------|------|
| `/` | FreeChina Web3 风格官网主站 |
| `/login` | 现代化登录页（对接 Xboard Passport API） |
| `/register` | 现代化注册页 |
| `/dashboard` 等 | 原 Xboard 用户中心 SPA |

### 支付插件（`overlay/plugins-core/`）

| 插件目录 | 支付方式标识 | 对接后端 | 说明 |
|----------|--------------|----------|------|
| `JeepayAbaQr` | `JeepayAbaQr` | Jeepay `ABA_KHQR` | 个人 KHQR 扫码，CNY→KHR 自动换算，说明页手输瑞尔 |
| `JeepayAbaPc` | `JeepayAbaPc` | Jeepay `ABA_PC` | ABA PayWay 官方收银台 / API |
| `JeepayPaypal` | `JeepayPaypal` | Jeepay `PP_PC` | PayPal |
| `TokenPay` | `TokenPay` | TokenPay 自建 | USDT/TRX 等 |

---

## 快速开始（完整部署）

> 推荐：已有一台 Linux VPS（Ubuntu 22.04 / Debian 11+），2 核 4G 起。

```bash
# 1. 克隆本仓库
git clone https://github.com/vlesse/freechina-xboard.git
cd freechina-xboard

# 2. 一键安装（会克隆官方 Xboard 并打上本仓库 overlay）
sudo bash scripts/install.sh
```

仓库地址：https://github.com/vlesse/freechina-xboard  

详细环境、域名、HTTPS、支付配置见 **[docs/DEPLOY.md](docs/DEPLOY.md)**。

---

## 只要支付插件

```bash
# 在你已有的 Xboard 根目录执行
bash /path/to/freechina-xboard/scripts/install-plugins-only.sh /www/wwwroot/your-xboard
```

说明见 **[docs/PLUGINS-ONLY.md](docs/PLUGINS-ONLY.md)**。

---

## 仓库结构

```text
freechina-xboard/
├── README.md                 # 本文件
├── LICENSE
├── docs/                     # 部署与插件文档
├── overlay/                  # 覆盖到官方 Xboard 上的定制文件
│   ├── plugins-core/         # 支付插件
│   ├── public/
│   │   ├── landing/          # 主站 / 登录 / 注册
│   │   └── aba-khqr-pay.html # KHQR 金额说明页
│   └── routes-web.php        # 路由（替换 routes/web.php）
├── scripts/
│   ├── install.sh            # 完整安装
│   └── install-plugins-only.sh
└── docker/                   # 可选 Docker 参考
```

---

## 配套系统（完整收款能力）

本发行版 **Xboard 面板** 可单独部署；支付走已部署好的 **FreeChina Jeepay**，不必自己再搭一套 Jeepay。

### FreeChina Jeepay（已就绪，直接对接）

| 用途 | 地址 | 说明 |
|------|------|------|
| **Jeepay 商户后台** | **https://payment.free--china.com/** | 登录后查看/配置商户、应用、通道（ABA / PayPal 等） |
| **Jeepay 支付网关 API** | **https://pay.free--china.com** | Xboard 支付插件里的「Jeepay支付网关」填此地址（**无尾斜杠**） |

在 Xboard「支付配置」中填写：

- **Jeepay支付网关**：`https://pay.free--china.com`  
- **mchNo / appId / appSecret**：到 [payment.free--china.com](https://payment.free--china.com/) 商户应用里复制  

| 系统 | 用途 | 说明 |
|------|------|------|
| **Jeepay（FreeChina）** | ABA KHQR / ABA PayWay / PayPal | 使用上方已部署地址，**默认对接 payment.free--china.com** |
| **ABA 个人桥 / aba-bridge** | 个人 KHQR 到账监听 | 配合 `JeepayAbaQr`（与 FreeChina Jeepay 同环境） |
| **TokenPay** | USDT/TRX | 需自备 TokenPay 实例，在支付配置填 API 与密钥 |

> 若你要使用**自己的** Jeepay，也可把网关改成你的域名；默认文档与插件示例均指向 FreeChina 现成服务。

---

## 安全提示

- 仓库内 **不含** 任何生产密钥、数据库密码、商户密钥。  
- 请使用 `.env` / 后台配置填写密钥，勿提交到 Git。  
- 生产环境务必 HTTPS + 定期备份数据库。

---

## 许可

- 本仓库 **定制部分**（overlay / 脚本 / 文档）：[MIT License](LICENSE)  
- 上游 **Xboard** 请遵循其自身开源协议（见 [cedar2025/Xboard](https://github.com/cedar2025/Xboard)）  
- Jeepay / TokenPay 等第三方项目遵循各自许可证  

---

## 支持

- Telegram：**[@lngsuan](https://t.me/lngsuan)**  
- 部署问题、二次开发、支付联调可私聊联系  

---

## 致谢

- [Xboard](https://github.com/cedar2025/Xboard)  
- [Jeepay](https://github.com/jeequan/jeepay)  
- [TokenPay](https://github.com/LightCountry/TokenPay)  
