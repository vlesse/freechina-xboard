# FreeChina Xboard 完整部署指南

联系：**Telegram [https://t.me/lngsuan](https://t.me/lngsuan)**

本指南目标：部署出与 FreeChina 演示站 **相同功能** 的 Xboard（官网主站 + 登录注册 + 支付插件）。

---

## 1. 环境要求

### 最低配置

| 项目 | 建议 |
|------|------|
| 系统 | Ubuntu 22.04 / Debian 11+ / CentOS 7+ |
| CPU / 内存 | 2 核 / 4 GB 起 |
| 磁盘 | 40 GB+ SSD |
| 网络 | 公网 IP + 可解析域名 |

### 软件依赖

| 软件 | 版本建议 |
|------|----------|
| Nginx | 1.18+ |
| PHP | **8.2**（含 `php-fpm`、`bcmath`、`redis`、`mysql`、`gd`、`mbstring`、`xml`、`curl`、`zip`、`tokenizer`） |
| Composer | 2.x |
| MySQL / MariaDB | 8.0 / 10.6+ |
| Redis | 6+ |
| Node.js | 可选（仅自建主题编译时需要；本仓库落地页为纯静态） |
| Git | 最新 |

> 宝塔面板 / 1Panel 用户：创建站点时选 PHP 8.2，开启上述扩展，伪静态选 Laravel。

### 域名与 HTTPS

- 示例：`panel.example.com` 指向服务器  
- 申请 SSL（Let's Encrypt）  
- 生产务必全站 HTTPS  

---

## 2. 一键安装（推荐）

```bash
# 以 root 或具备 sudo 的用户执行
git clone https://github.com/<你的GitHub用户名>/freechina-xboard.git
cd freechina-xboard

# 安装参数（可按需修改）
export XBOARD_DIR=/www/wwwroot/xboard
export XBOARD_REPO=https://github.com/cedar2025/Xboard.git
export XBOARD_BRANCH=master   # 以官方当前默认分支为准

sudo bash scripts/install.sh
```

脚本会：

1. 克隆官方 Xboard 到 `XBOARD_DIR`  
2. 复制本仓库 `overlay/` 定制文件  
3. 合并路由、落地页、支付插件  
4. 提示你配置 `.env`、数据库、执行 `php artisan` 初始化  

**一键脚本不会替你创建数据库账号密码**，请事先准备好 MySQL 库。

---

## 3. 手动安装（逐步）

### 3.1 安装官方 Xboard

请先按官方文档完成基础安装：

- 仓库：https://github.com/cedar2025/Xboard  
- 常见方式：Composer 安装依赖 → 配置 `.env` → `php artisan xboard:install`（或官方当前推荐命令）

确认能打开默认用户端后再继续。

### 3.2 应用 FreeChina 定制

在本仓库目录执行：

```bash
export XBOARD_DIR=/www/wwwroot/your-xboard   # 你的 Xboard 根目录
bash scripts/install-overlay.sh
```

或手动：

```bash
# 支付插件
cp -a overlay/plugins-core/*  $XBOARD_DIR/plugins-core/

# 落地页
mkdir -p $XBOARD_DIR/public/landing
cp -a overlay/public/landing/* $XBOARD_DIR/public/landing/
cp -a overlay/public/aba-khqr-pay.html $XBOARD_DIR/public/

# 路由（先备份）
cp $XBOARD_DIR/routes/web.php $XBOARD_DIR/routes/web.php.bak
cp overlay/routes-web.php $XBOARD_DIR/routes/web.php

chown -R www:www $XBOARD_DIR/plugins-core $XBOARD_DIR/public/landing $XBOARD_DIR/public/aba-khqr-pay.html
```

### 3.3 Nginx 伪静态（Laravel）

站点根目录指向：`.../public`

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

参考：`docker/nginx-rewrite.conf`

### 3.4 清理缓存

```bash
cd $XBOARD_DIR
php artisan optimize:clear
php artisan route:clear
```

### 3.5 启用插件与支付

1. 登录 **Xboard 管理后台**  
2. **插件** 中确认以下插件已启用（若未自动出现，执行下方 SQL 或重装插件）：  
   - `jeepay_aba_qr`  
   - `jeepay_aba_pc`  
   - `jeepay_paypal`  
   - `token_pay`  
3. **支付配置** → 添加支付方式：  

| 显示名称示例 | 支付接口 | 关键配置 |
|--------------|----------|----------|
| 聚合支付-支付宝 | JeepayAbaQr | Jeepay 网关、mchNo、appId、appSecret、汇率、说明页 URL |
| ABA PayWay | JeepayAbaPc | Jeepay 网关、商户密钥、结算币 USD/KHR、汇率 |
| PayPal | JeepayPaypal | Jeepay 网关、商户密钥、CNY→USD 汇率 |
| TokenPay USDT | TokenPay | TokenPay API 地址、密钥、币种 `USDT_TRC20` |

说明页默认：

```text
https://你的域名/aba-khqr-pay.html
```

---

## 4. 路由说明

应用 `overlay/routes-web.php` 后：

| URL | 说明 |
|-----|------|
| `/` | FreeChina 官网主站 |
| `/login` | 定制登录页 |
| `/register` | 定制注册页 |
| `/dashboard` 等 | 原 Xboard SPA 用户中心 |
| `/api/*` | API 不变 |

---

## 5. 配套系统

### 5.1 Jeepay（ABA / PayPal）

1. 部署 Jeepay（Docker 或源码）  
2. 开通通道：`abakhqr` / `abapay` / `pppay`  
3. 创建商户应用，拿到：  
   - `mchNo`  
   - `appId`  
   - `appSecret`  
4. 支付网关对外地址示例：`https://pay.example.com`  
5. 填入 Xboard 对应支付配置  

个人 KHQR 还需：

- aba-bridge 中转  
- 手机监听 App  
- 中转 `callbackUrl` 指向 Jeepay payment  

### 5.2 TokenPay

1. 部署 [TokenPay](https://github.com/LightCountry/TokenPay)  
2. 配置异步通知密钥、收款地址  
3. Xboard 支付配置填写 API 根地址（无尾斜杠）与密钥  

---

## 6. Docker 参考（可选）

见 `docker/docker-compose.yml`。  
适合熟悉 Docker 的用户；数据库与 Redis 可内置或外挂。

```bash
cd docker
# 按注释修改环境变量后
docker compose up -d
```

> 官方 Xboard 镜像/版本可能变化，请以实际可运行版本为准，再挂载 `overlay`。

---

## 7. 验收清单

- [ ] 打开 `https://域名/` 看到 FreeChina 主站  
- [ ] `/login` `/register` 为新 UI，可注册登录进入 `/dashboard`  
- [ ] 支付列表出现已启用通道  
- [ ] 测试小额：ABA KHQR / ABA PayWay / PayPal / TokenPay  
- [ ] 回调后订单状态变为已支付  

---

## 8. 常见问题

**Q：打开 `/` 仍是旧登录页？**  
A：确认已替换 `routes/web.php`，并 `php artisan optimize:clear`；浏览器强制刷新。

**Q：`/dashboard` 404？**  
A：Nginx 未配置 `try_files` 到 `index.php`。

**Q：Jeepay 下单网络异常 URL bad？**  
A：支付配置 JSON 损坏或 `gateway_url` 为空，重新保存支付配置。

**Q：TokenPay 回调失败？**  
A：回调须返回纯文本 `ok`；本插件已处理。检查防火墙与 HTTPS。

---

## 9. 支持

Telegram：**[https://t.me/lngsuan](https://t.me/lngsuan)**  
欢迎部署反馈与定制需求。
