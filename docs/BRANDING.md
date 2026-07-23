# 品牌 / 标志修改说明（FreeChina）

如果你拉取了本仓库源码，想把 **FreeChina / FC** 换成自己的品牌，按下面位置修改即可。  
联系支持：[https://t.me/lngsuan](https://t.me/lngsuan)

> 本仓库默认使用 **模板二（Nord 风格主页）**：`overlay/public/landing/index.html`  
> 模板一备份：`overlay/public/landing/index-v1.html`（旧 Web3 深色页）

---

## 1. 改什么（清单）

| 元素 | 含义 | 默认值 |
|------|------|--------|
| 品牌全称 | 站点名称文字 | `FreeChina` |
| 角标字母 | Logo 方块里的缩写 | `FC` |
| 浏览器标题 | `<title>` | `FreeChina · …` |
| 域名文案 | 页脚版权 | `free--china.com` |
| 主题色 | 按钮/链接蓝 | `#4687ff`（模板二） |
| 用户中心标题/Logo | Xboard 后台配置 | 管理后台「站点设置」 |

---

## 2. 官网主站（最重要）

**文件路径（本仓库）：**

```text
overlay/public/landing/index.html
```

**安装到 Xboard 后的路径：**

```text
{你的Xboard根目录}/public/landing/index.html
```

### 2.1 Logo 角标 + 品牌名（顶栏）

搜索：

```html
<span class="brand-logo">FC</span>
FreeChina
```

改成例如：

```html
<span class="brand-logo">XX</span>
YourBrand
```

页脚同样搜索 `footer-brand` / `FreeChina` / `free--china.com` 一并替换。

### 2.2 浏览器标签标题与 SEO

文件头部：

```html
<title>FreeChina · 隐私优先的全球加速</title>
<meta name="description" content="FreeChina — …" />
```

改成你的站点名与简介。

### 2.3 文案中的品牌词

在同一 HTML 中全文搜索并替换：

- `FreeChina`
- `free--china.com`（若有）
- `获取 FreeChina`（主按钮文案，可改成「立即注册」等）

### 2.4 主题色（蓝按钮 / 链接）

在 `index.html` 顶部 CSS `:root` 中修改，例如：

```css
--blue: #4687ff;
--blue-hover: #3471e8;
--blue-soft: #e8f0ff;
--navy: #0b1220;
```

改完保存后强制刷新浏览器（Ctrl+F5）。

### 2.5 换成自己的图片 Logo（可选）

将角标 HTML：

```html
<span class="brand-logo">FC</span>
```

改为：

```html
<img class="brand-logo-img" src="/landing/logo.svg" alt="YourBrand" width="36" height="36" />
```

并把图片放到：

```text
public/landing/logo.svg   （或 logo.png）
```

并在 CSS 中增加（示例）：

```css
.brand-logo-img {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  object-fit: contain;
}
```

---

## 3. 登录页 / 注册页

| 页面 | 本仓库路径 | 部署后路径 |
|------|------------|------------|
| 登录 | `overlay/public/landing/login.html` | `public/landing/login.html` |
| 注册 | `overlay/public/landing/register.html` | `public/landing/register.html` |

修改位置与主站类似，搜索：

```html
<span class="brand-mark">FC</span>
<span>FreeChina</span>
```

以及：

- `<title>登录 · FreeChina</title>` / `<title>注册 · FreeChina</title>`
- 页脚 `© FreeChina · free--china.com`
- 左侧说明文案中的品牌名

主题色：在各自文件的 CSS 里改 `--cyan` / `--violet` 或主按钮渐变（登录/注册页为暗色 Web3 风格，与主站 Nord 蓝可分别调整）。

---

## 4. 模板一（旧主页，可选）

若你切换使用模板一：

```text
overlay/public/landing/index-v1.html
```

其中品牌在：

```html
<span class="brand-mark">FC</span>
<span>FreeChina</span>
```

以及标题、页脚中的 `FreeChina`。

切换为模板一的方法：将该文件复制覆盖为 `index.html`。

---

## 5. ABA KHQR 付款说明页

```text
overlay/public/aba-khqr-pay.html
→ 部署后：public/aba-khqr-pay.html
```

此页主要是支付引导，一般无 FreeChina Logo；若有文案含品牌名，全文搜索替换即可。

---

## 6. 用户中心（登录后的 Xboard 面板）

落地页改完后，**用户中心标题 / Logo** 仍由 Xboard 后台控制：

1. 登录管理后台  
2. **系统设置 / 站点配置**（或「前端设置」）  
3. 修改：  
   - **站点名称**（如 FreeChina → 你的名字）  
   - **站点 Logo**（上传图片 URL）  
   - **站点描述**  

主题文件也会读这些配置（`dashboard.blade.php` 中的 `$title`、`$logo` 等）。

---

## 7. 支付配置里的「商品标题前缀」

各 Jeepay 插件配置项 **product_name**（默认 `XBoard` / 可写 FreeChina）：

- 管理后台 → **支付配置** → 对应支付方式  
- 字段：**商品标题前缀**  

会体现在 Jeepay 订单标题里，与页面 Logo 无关，但建议与品牌统一。

---

## 8. 建议替换顺序

1. `public/landing/index.html`（主站）  
2. `login.html` + `register.html`  
3. Xboard 后台站点名称 / Logo  
4. 支付配置 `product_name`  
5. 清理缓存：`php artisan optimize:clear`，浏览器强刷  

---

## 9. 快速全文搜索关键词

在 Xboard 目录执行：

```bash
grep -R "FreeChina\|free--china\|brand-logo\|brand-mark" \
  public/landing public/aba-khqr-pay.html 2>/dev/null
```

把结果中的品牌相关字符串改成你的即可。

---

## 支持

修改品牌时若遇到路径不一致或主题被缓存，可联系：  
**Telegram → [https://t.me/lngsuan](https://t.me/lngsuan)**
