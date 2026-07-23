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
git clone https://github.com/<你的用户名>/freechina-xboard.git
cd freechina-xboard

# 2. 一键安装（会克隆官方 Xboard 并打上本仓库 overlay）
sudo bash scripts/install.sh
```

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

本发行版 **前端 + 面板** 可单独部署；若要使用 Jeepay / TokenPay 收款，还需自行准备：

| 系统 | 用途 | 说明 |
|------|------|------|
| **Jeepay** | ABA / PayPal 等通道 | 需自行部署，配置商户 `mchNo` / `appId` / `appSecret` |
| **ABA 个人桥 / aba-bridge** | 个人 KHQR 到账监听 | 配合 `JeepayAbaQr` |
| **TokenPay** | USDT/TRX | 独立部署，填 API 地址与密钥 |

这些 **不包含在本仓库二进制里**，请按各自官方文档安装，再在 Xboard「支付配置」中填写地址与密钥。

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
