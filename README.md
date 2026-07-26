# FreeChina Xboard

基于 [Xboard](https://github.com/cedar2025/Xboard) 的二次定制，提供：

- **Web3 风格官网主站** + 现代化登录 / 注册页  
- **Jeepay 支付插件**：ABA 个人 KHQR、ABA PayWay、PayPal、**Midtrans**  
- **TokenPay 支付插件**：USDT-TRC20 / TRX 等  
- 对接已部署的 FreeChina Jeepay，也可填自己的网关  

联系 / 技术交流：**Telegram → [https://t.me/lngsuan](https://t.me/lngsuan)**

---

## 先读懂：本仓库不是「成品面板」

```text
官方 Xboard 程序
    +  本仓库 overlay（前端 + 支付插件）
    =  FreeChina Xboard 站点
```

| 你的情况 | 该做什么 | 详细文档 |
|----------|----------|----------|
| **服务器上还没有 Xboard** | 运行 `scripts/install.sh`：脚本会 **自动下载官方 Xboard**，再打上 FreeChina 补丁 | [docs/DEPLOY.md 路径 A](docs/DEPLOY.md) |
| **已有 Xboard，要整套 FreeChina 前端 + 支付** | 运行 `scripts/install-overlay.sh`（**不要**再整站 `install.sh`） | [docs/DEPLOY.md 路径 B-1](docs/DEPLOY.md) |
| **已有 Xboard，只要支付、不换首页** | 运行 `scripts/install-plugins-only.sh` | [docs/PLUGINS-ONLY.md](docs/PLUGINS-ONLY.md) |

### 一键脚本会不会「在原有 Xboard 上改」？

- **`install.sh`（完整一键）**：给 **从零** 用的。目录里没有 Xboard 时会 `git clone` 官方仓库，**不是**要求你先手动装好再改。  
- **已有在跑的站点**：请用 `install-overlay.sh` 或 `install-plugins-only.sh`，并指定你的 Xboard 根目录。

> 脚本只处理 **程序文件**。域名、SSL、MySQL、Redis、`.env`、官方初始化命令，需要你按文档手动完成。

---

## 文档目录

| 文档 | 说明 |
|------|------|
| **[docs/DEPLOY.md](docs/DEPLOY.md)** | **小白一步一步部署**（路径怎么选、每个命令干什么） |
| [docs/PLUGINS-ONLY.md](docs/PLUGINS-ONLY.md) | 只装 5 个支付插件 |
| [docs/PAYMENT-CHANNELS.md](docs/PAYMENT-CHANNELS.md) | 各支付通道、汇率、回调 |
| [docs/BRANDING.md](docs/BRANDING.md) | 改 FreeChina 名称 / Logo |

---

## 快速开始

### 情况 1：从零安装（路径 A）

```bash
# 1. 下载本仓库（定制脚本 + overlay，还不是最终网站目录）
git clone https://github.com/vlesse/freechina-xboard.git
cd freechina-xboard

# 2. 完整一键：自动克隆官方 Xboard + 叠加 FreeChina
export XBOARD_DIR=/www/wwwroot/xboard
sudo bash scripts/install.sh

# 3. 之后还必须手动：Nginx 指向 $XBOARD_DIR/public、SSL、.env、php artisan 初始化
#    详见 docs/DEPLOY.md「路径 A」
```

网站运行目录应是：`/www/wwwroot/xboard/public`（示例），**不是** `freechina-xboard` 本身。

### 情况 2：已有 Xboard，只要支付（路径 B-2）

```bash
git clone https://github.com/vlesse/freechina-xboard.git
cd freechina-xboard
bash scripts/install-plugins-only.sh /www/wwwroot/你的xboard目录
```

### 情况 3：已有 Xboard，要整套前端 + 支付（路径 B-1）

```bash
git clone https://github.com/vlesse/freechina-xboard.git
cd freechina-xboard
export XBOARD_DIR=/www/wwwroot/你的xboard目录
bash scripts/install-overlay.sh
```

---

## 功能一览

### 前端（install.sh / install-overlay 后）

| 路径 | 说明 |
|------|------|
| `/` | FreeChina Web3 风格官网主站 |
| `/login` | 定制登录页 |
| `/register` | 定制注册页 |
| `/dashboard` 等 | 原 Xboard 用户中心 |
| `/aba-khqr-pay.html` | ABA KHQR 付款说明页（本地二维码库，不依赖海外 CDN） |

### 支付插件（`overlay/plugins-core/`）

| 插件目录 | 标识 | Jeepay wayCode | 说明 |
|----------|------|----------------|------|
| `JeepayAbaQr` | JeepayAbaQr | `ABA_KHQR` | 个人 KHQR，CNY→KHR，说明页 |
| `JeepayAbaPc` | JeepayAbaPc | `ABA_PC` | ABA PayWay 官方 |
| `JeepayPaypal` | JeepayPaypal | `PP_PC` | PayPal |
| `JeepayMidtrans` | JeepayMidtrans | `MID_PC` | Midtrans，CNY→IDR |
| `TokenPay` | TokenPay | — | USDT/TRX 等 |

---

## 仓库结构

```text
freechina-xboard/
├── README.md
├── docs/                      # 部署与支付文档（小白向）
├── overlay/                   # 覆盖到官方 Xboard 上的文件
│   ├── plugins-core/          # 支付插件
│   ├── public/
│   │   ├── landing/           # 主站 / 登录 / 注册
│   │   ├── aba-khqr-pay.html  # KHQR 说明页
│   │   └── qrcode.min.js      # 本地二维码库
│   └── routes-web.php         # 替换 routes/web.php
├── scripts/
│   ├── install.sh             # 从零：clone 官方 + overlay
│   ├── install-overlay.sh     # 已有站：整套 FreeChina
│   └── install-plugins-only.sh# 已有站：只装支付
└── docker/                    # 可选 Docker 参考
```

---

## 对接 FreeChina Jeepay（默认）

不必自己再搭 Jeepay：

| 用途 | 地址 |
|------|------|
| 商户后台（拿 mchNo / appId / appSecret） | https://payment.free--china.com/ |
| 插件里「Jeepay支付网关」 | `https://pay.free--china.com`（**无尾斜杠**） |

也可改成你自己的 Jeepay 域名。细节见 [docs/DEPLOY.md](docs/DEPLOY.md) 与 [docs/PAYMENT-CHANNELS.md](docs/PAYMENT-CHANNELS.md)。

---

## 如何修改品牌 / Logo

默认品牌为 **FreeChina（角标 FC）**。改名、换图、主题色：

👉 **[docs/BRANDING.md](docs/BRANDING.md)**

用户中心站点名在 **Xboard 管理后台 → 站点设置**（与落地页 HTML 分开）。

---

## 安全提示

- 仓库 **不含** 生产密钥、数据库密码、商户密钥。  
- 密钥只放在 `.env` / 后台配置，勿提交 Git。  
- 生产环境务必 HTTPS，并定期备份数据库。

---

## 许可

- 本仓库定制部分（overlay / 脚本 / 文档）：[MIT License](LICENSE)  
- 上游 [Xboard](https://github.com/cedar2025/Xboard)、Jeepay、TokenPay 遵循各自许可证  

---

## 支持

- Telegram：**[@lngsuan](https://t.me/lngsuan)**  
- 部署问题请说明：选的是路径 A / B-1 / B-2、完整命令、报错全文  

---

## 致谢

- [Xboard](https://github.com/cedar2025/Xboard)  
- [Jeepay](https://github.com/jeequan/jeepay)  
- [TokenPay](https://github.com/LightCountry/TokenPay)  
