# FreeChina Xboard 部署指南（小白一步一步版）

联系：**Telegram [https://t.me/lngsuan](https://t.me/lngsuan)**

---

## 先停！先看懂再动手（很多人卡在这里）

### 这个仓库到底是什么？

本仓库 **不是** 一个「已经装好的完整面板成品」。

它是：

```text
官方 Xboard（cedar2025/Xboard）
        +
本仓库的 FreeChina 定制文件（前端页面 + 支付插件）
        =
你要的 FreeChina Xboard 站点
```

所以：**一定要先有「官方 Xboard 的程序文件」**，再叠加上本仓库的内容。  
区别只在于——**是脚本帮你下载官方 Xboard，还是你机器上本来就有**。

---

### 一键安装脚本，到底装在哪里？

| 你现在的情况 | 该用哪个脚本 | 会不会自动装官方 Xboard？ | 会不会改你现在的网站？ |
|--------------|--------------|---------------------------|------------------------|
| **服务器上还没有 Xboard**，要从零搭 | `scripts/install.sh` | **会**（自动 `git clone` 官方仓库） | 装到新目录，例如 `/www/wwwroot/xboard` |
| **已经有正在跑的 Xboard**，想整套换成 FreeChina 前端 + 支付 | `scripts/install-overlay.sh` | **不会** | **会**覆盖落地页、登录注册页、部分路由，并装支付插件 |
| **已经有 Xboard**，只想加支付，**不换**首页 | `scripts/install-plugins-only.sh` | **不会** | **只**加支付相关文件，不改首页 |

#### 用大白话再说一遍

1. **没有 Xboard**  
   → 只跑 **一次** `install.sh`。  
   → 脚本会：**先下载官方 Xboard → 再把 FreeChina 文件拷进去**。  
   → **不需要**你事先手动装好 Xboard 再来「修改」。

2. **已经有 Xboard**  
   → **不要**再跑 `install.sh` 去覆盖整站（除非你很清楚自己在做什么）。  
   → 用 `install-overlay.sh`（要整套 FreeChina）或 `install-plugins-only.sh`（只要支付）。  
   → 这是「在现有 Xboard 上叠加」，不是重新装一套。

3. **常见错误**  
   - 以为「一键」= 域名、SSL、数据库全都不用管 → **错**。脚本只管 **程序文件**，网站环境你还要自己配。  
   - 已有站点还跑 `install.sh`，且目录指错 → 可能装到空目录或搞乱路径。  
   - 把本仓库目录当成网站根目录 → **错**。网站根目录应是 **Xboard 目录下的 `public`**。

---

### 30 秒选择表

问自己一句：

> **我这台服务器上，现在能不能打开一个已经在用的 Xboard 用户端？**

| 回答 | 你走哪条路 | 往下翻到哪 |
|------|------------|------------|
| **不能 / 还没有** | **路径 A：全新安装** | [路径 A](#路径-a从零全新安装推荐完全小白) |
| **能，我还想要 FreeChina 首页** | **路径 B-1：叠加整套** | [路径 B-1](#路径-b-1已有-xboard叠加整套-freechina) |
| **能，我只要支付** | **路径 B-2：只装插件** | [路径 B-2](#路径-b-2已有-xboard只装支付插件) 或 [PLUGINS-ONLY.md](PLUGINS-ONLY.md) |

---

## 路径 A：从零全新安装（推荐完全小白）

> **适合：** 空服务器，或打算新建一个目录专门装面板。  
> **不适合：** 已有正在运营的 Xboard（请走路径 B）。

### A0. 开始前你要准备什么？（脚本不会替你做这些）

请 **先** 准备好，再执行脚本：

| 序号 | 准备项 | 说明 |
|------|--------|------|
| 1 | Linux 服务器 | 建议 Ubuntu 22.04；2 核 4G 内存；有 root |
| 2 | 域名 | 例如 `panel.example.com`，A 记录已解析到服务器 IP |
| 3 | 宝塔或 Nginx + PHP 8.2 | 扩展：`bcmath redis mysql gd mbstring xml curl zip tokenizer` |
| 4 | MySQL | 建一个 **空库**（如 `xboard`），记下库名、用户名、密码 |
| 5 | Redis | Xboard 一般需要 |
| 6 | Git、Composer | 宝塔可装；命令行要能用 `git`、`php`、`composer` |

> `install.sh` **不会** 帮你：创建数据库账号、申请 SSL、写 Nginx 站点（除非你自己另有自动化）。

### A1. 用 SSH 登录服务器

Windows 可用：宝塔「终端」、FinalShell、Windows Terminal / PowerShell：

```bash
ssh root@你的服务器IP
```

能登录、能输入命令即可。

### A2. 下载 **本仓库**（FreeChina 定制包）

注意：这里下载的是 **freechina-xboard**（定制说明 + 脚本 + overlay），  
**还不是** 最终网站目录。

```bash
mkdir -p /www/wwwroot
cd /www/wwwroot
git clone https://github.com/vlesse/freechina-xboard.git
cd freechina-xboard
```

成功后，当前目录应类似：

```text
/www/wwwroot/freechina-xboard/
  ├── docs/
  ├── overlay/
  ├── scripts/
  │     ├── install.sh              ← 路径 A 用这个
  │     ├── install-overlay.sh
  │     └── install-plugins-only.sh
  └── README.md
```

### A3. 执行「完整一键」——`install.sh` 实际会做什么？

按顺序：

```text
① 检查：目标目录里有没有官方 Xboard？
   - 没有 → git clone 官方 https://github.com/cedar2025/Xboard.git
   - 已有且是 git 仓库 → 跳过 clone，只打补丁

② 尝试 composer install（装 PHP 依赖）

③ 调用 install-overlay.sh：
   - 复制支付插件 → Xboard/plugins-core/
   - 复制落地页 → Xboard/public/landing/
   - 复制 KHQR 说明页 + 本地二维码库
   - 备份并替换 routes/web.php
```

**一句话：**  
`install.sh` = **自动装官方 Xboard + 自动打上 FreeChina 补丁**。  
**不是**「你要先手动装好 Xboard 再跑脚本」——从零的人直接跑它即可。

执行（目录可按需改）：

```bash
cd /www/wwwroot/freechina-xboard

export XBOARD_DIR=/www/wwwroot/xboard
export XBOARD_REPO=https://github.com/cedar2025/Xboard.git
export XBOARD_BRANCH=master

sudo bash scripts/install.sh
```

跑完后大致结构：

```text
/www/wwwroot/xboard/          ← 这才是真正的 Xboard 程序根目录
  ├── artisan
  ├── public/                 ← Nginx/宝塔「运行目录」要指这里
  │     ├── landing/
  │     ├── aba-khqr-pay.html
  │     └── qrcode.min.js
  ├── plugins-core/           ← 支付插件在这里
  └── routes/web.php
```

### A4. 脚本跑完后，你还要手动做的事（必做）

#### A4-1. 宝塔 / Nginx 建站点

1. 添加网站，域名填你的域名。  
2. **网站根目录 / 运行目录** = `/www/wwwroot/xboard/public`  
   （是 `public`，不是 `xboard` 根，也不是 `freechina-xboard`）。  
3. PHP 选 **8.2**，打开上面列出的扩展。  
4. 伪静态选 **Laravel**，或写入：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

5. 申请 SSL（Let's Encrypt），开启强制 HTTPS。

#### A4-2. 配置 `.env` 并初始化 Xboard

```bash
cd /www/wwwroot/xboard

# 若还没有 .env
cp .env.example .env

# 用 nano 或宝塔文件管理编辑 .env
# 至少填写：数据库主机/库名/用户/密码、Redis、APP_URL=https://你的域名
```

然后按 **官方 Xboard 当前文档** 初始化（常见类似）：

```bash
cd /www/wwwroot/xboard
composer install --no-dev --optimize-autoloader   # 若之前失败再执行
php artisan xboard:install
# 具体命令以 https://github.com/cedar2025/Xboard 文档为准
```

记下安装过程输出的 **管理员账号**。

#### A4-3. 清理缓存

```bash
cd /www/wwwroot/xboard
php artisan optimize:clear
php artisan route:clear
```

#### A4-4. 浏览器验收

| 打开 | 预期 |
|------|------|
| `https://你的域名/` | FreeChina 风格主站（不是白屏） |
| `https://你的域名/login` | 定制登录页 |
| `https://你的域名/register` | 定制注册页 |
| 管理后台 | 按官方安装提示（常见 `/admin` 等） |

### A5. 配置支付

见下文 [配置支付](#配置支付所有路径通用)。

---

## 路径 B：已有 Xboard（在现有站点上叠加）

> **适合：** 面板已经能打开、能登录。  
> **不要** 再执行 `install.sh` 去「整站重装」，除非你有意把文件装到一个 **新的空目录**。

### B0. 先找到你的 Xboard 根目录

SSH 登录后，找含有 **`artisan`** 文件的那一层。  
宝塔常见：

```text
/www/wwwroot/你的域名/
/www/wwwroot/xboard/
```

快速确认：

```bash
ls /www/wwwroot/你的目录/artisan
# 能列出 artisan 文件 = 路径对了
```

**不要** 指到 `public` 里面。

### B0-1. 下载本仓库（可放任意目录，例如 `/root`）

```bash
cd /root
git clone https://github.com/vlesse/freechina-xboard.git
cd freechina-xboard
```

---

### 路径 B-1：已有 Xboard，叠加整套 FreeChina

会覆盖 / 写入：

| 内容 | 路径 |
|------|------|
| 支付插件 | `plugins-core/Jeepay*`、`TokenPay` |
| 主站 / 登录 / 注册 | `public/landing/` |
| KHQR 说明页 + 二维码库 | `public/aba-khqr-pay.html`、`public/qrcode.min.js` |
| 前端路由 | `routes/web.php`（**会先自动备份** 成 `web.php.bak.时间戳`） |

```bash
cd /root/freechina-xboard

# 把路径改成你自己的 Xboard 根目录
export XBOARD_DIR=/www/wwwroot/你的xboard目录
bash scripts/install-overlay.sh
```

然后：

```bash
cd /www/wwwroot/你的xboard目录
php artisan optimize:clear
php artisan route:clear
```

浏览器强制刷新（Ctrl+F5），再打开首页看是否变成 FreeChina 主站。

---

### 路径 B-2：已有 Xboard，只装支付插件

**不改** 首页、登录页、`routes/web.php`。  
只复制支付插件 + 说明页 + 二维码库。

```bash
cd /root/freechina-xboard
bash scripts/install-plugins-only.sh /www/wwwroot/你的xboard目录
```

更细的后台点选说明见 **[PLUGINS-ONLY.md](PLUGINS-ONLY.md)**。

---

## 配置支付（所有路径通用）

### 1. 在后台启用插件

1. 登录 **Xboard 管理后台**  
2. 打开 **插件**  
3. 启用下表中的插件（没有记录见下方 SQL）

| 插件 code | 用途 |
|-----------|------|
| `jeepay_aba_qr` | ABA 个人 KHQR + 说明页 |
| `jeepay_aba_pc` | ABA PayWay 官方 |
| `jeepay_paypal` | PayPal |
| `jeepay_midtrans` | Midtrans |
| `token_pay` | TokenPay（可选） |

若后台完全没有这些插件，在 MySQL 执行（已存在会报错，可忽略）：

```sql
INSERT INTO v2_plugins (name, code, type, version, is_enabled, config, installed_at, created_at, updated_at)
VALUES
('Jeepay ABA KHQR', 'jeepay_aba_qr', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW()),
('Jeepay ABA PayWay', 'jeepay_aba_pc', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW()),
('Jeepay PayPal', 'jeepay_paypal', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW()),
('Jeepay Midtrans', 'jeepay_midtrans', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW()),
('TokenPay', 'token_pay', 'payment', '1.0.0', 1, '[]', NOW(), NOW(), NOW());
```

刷新后台插件页，再启用。

### 2. 对接 Jeepay（推荐用 FreeChina 现成服务）

**默认不必自己再搭一套 Jeepay。**

| 用途 | 地址 |
|------|------|
| 商户后台（登录拿密钥） | https://payment.free--china.com/ |
| 支付网关（插件里填写） | `https://pay.free--china.com`（**不要**末尾 `/`） |

操作步骤：

1. 打开 https://payment.free--china.com/ 并登录  
2. 进入 **商户应用**，复制：`mchNo`、`appId`、`appSecret`  
3. 回到 Xboard → **系统设置 → 支付配置 → 添加**

#### 示例：ABA 个人 KHQR

| 配置项 | 填什么 |
|--------|--------|
| 显示名称 | 例如：支付宝扫码 |
| 支付接口 | **JeepayAbaQr** |
| Jeepay支付网关 | `https://pay.free--china.com` |
| mchNo / appId / appSecret | 从 payment 后台复制 |
| wayCode | `ABA_KHQR` |
| 人民币→瑞尔汇率 | 例如 `560`（按实际改） |
| 金额说明页 URL | `https://你的Xboard域名/aba-khqr-pay.html` |

#### 其他通道简表

| 支付接口 | wayCode | 其它 |
|----------|---------|------|
| JeepayAbaPc | `ABA_PC` | 结算币 USD/KHR + 汇率 |
| JeepayPaypal | `PP_PC` | `cny_to_usd_rate` 如 `0.14` |
| JeepayMidtrans | `MID_PC` | `cny_to_idr_rate` 默认 `2200` |
| TokenPay | — | 自备 TokenPay 的 API 与密钥 |

更细说明：[PAYMENT-CHANNELS.md](PAYMENT-CHANNELS.md)

### 3. 测一笔小额

1. 用户端登录 → 购买套餐 → 选择支付方式  
2. ABA KHQR：应打开说明页，显示 **瑞尔金额 + 二维码**  
3. 付完后等回调；说明页会轮询订单状态并尝试跳回订单页  

---

## 装完后各网址应是什么样

| 网址 | 路径 A / B-1（整套 overlay） | 路径 B-2（仅插件） |
|------|------------------------------|---------------------|
| `/` | FreeChina 主站 | 你原来的首页（不变） |
| `/login` `/register` | 定制页 | 你原来的页面 |
| `/dashboard` 等 | Xboard 用户中心 | 同左 |
| `/aba-khqr-pay.html` | KHQR 付款说明页 | 同左（脚本有复制） |
| `/api/*` | API | 同左 |

---

## 验收清单

### 站点

- [ ] 域名 HTTPS 正常  
- [ ] 运行目录是 `.../xboard/public`  
- [ ] 路径 A/B-1：首页是 FreeChina 主站  
- [ ] 能登录、能进用户中心  

### 支付

- [ ] 后台已启用对应插件  
- [ ] 支付配置里密钥、网关正确  
- [ ] 用户端能下单结账  
- [ ] ABA KHQR 说明页有金额和二维码  
- [ ] 支付成功后订单变为已支付  

---

## 常见问题（针对小白）

### Q1：一键安装是装在「原有 Xboard」上，还是直接部署脚本？

- **从零：** 直接跑 `install.sh`。脚本 **自己会下载官方 Xboard**，再叠加 FreeChina。  
  **不需要** 先手动装 Xboard 再改。  
- **已有站点：** 用 `install-overlay.sh` 或 `install-plugins-only.sh`，指定你现有目录。  
  **不要** 默认再跑一遍 `install.sh`。

### Q2：要不要先装好官方 Xboard，再来改？

- 路径 A：**不用**。`install.sh` 一步做完「下载 + 打补丁」。  
- 路径 B：**已经装好了**，只做「打补丁 / 拷插件」。

### Q3：脚本跑完就能打开网站吗？

**不能全自动。** 脚本主要管 **代码文件**。  
你还要：Nginx/宝塔站点、SSL、`.env`、数据库、官方 `artisan` 初始化。

### Q4：网站根目录填哪个？

填：**Xboard 根目录下的 `public`**。  
例如：`/www/wwwroot/xboard/public`  
**不要** 填 `/www/wwwroot/freechina-xboard`。

### Q5：打开 `/` 还是旧页面？

1. 是否执行了 **install-overlay**（不是只装插件）？  
2. `routes/web.php` 是否已替换？  
3. `php artisan optimize:clear`  
4. 浏览器 Ctrl+F5  

### Q6：二维码说明页空白？

确认存在：

- `public/aba-khqr-pay.html`  
- `public/qrcode.min.js`（本仓库自带，避免海外 CDN 失败）  

支付配置里说明页 URL 是否为：`https://你的域名/aba-khqr-pay.html`

### Q7：`/dashboard` 404？

Nginx 未把请求交给 Laravel。检查 `try_files`、运行目录是否为 `public`。

### Q8：Jeepay 下单失败？

- 网关是否为 `https://pay.free--china.com`（无尾 `/`）  
- 密钥是否从 payment 后台正确复制  
- 支付配置是否完整保存  

### Q9：想改成自己的品牌名 / Logo？

见 [BRANDING.md](BRANDING.md)。

---

## 三个脚本对照（备忘）

| 脚本 | 自动 clone 官方 Xboard？ | 改落地页 / 路由？ | 装支付插件？ | 给谁用 |
|------|--------------------------|-------------------|--------------|--------|
| `scripts/install.sh` | **是**（目录无仓库时） | **是** | **是** | 从零安装 |
| `scripts/install-overlay.sh` | 否 | **是** | **是** | 已有站，要整套 FreeChina |
| `scripts/install-plugins-only.sh` | 否 | **否** | **是** | 已有站，只要支付 |

---

## 可选：Docker

见仓库 `docker/docker-compose.yml`。适合熟悉 Docker 的用户；小白建议宝塔 + 路径 A/B。

---

## 支持

Telegram：**[https://t.me/lngsuan](https://t.me/lngsuan)**

部署卡住时请尽量说明：

1. 路径 A / B-1 / B-2 选的哪一个  
2. 执行的完整命令  
3. 终端报错全文或截图  
