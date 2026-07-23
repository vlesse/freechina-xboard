#!/usr/bin/env bash
# 将 FreeChina overlay 应用到已存在的 Xboard 目录
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
XBOARD_DIR="${XBOARD_DIR:-${1:-}}"

if [[ -z "${XBOARD_DIR}" ]]; then
  echo "用法: XBOARD_DIR=/path/to/xboard bash scripts/install-overlay.sh"
  echo "  或: bash scripts/install-overlay.sh /path/to/xboard"
  exit 1
fi

if [[ ! -f "${XBOARD_DIR}/artisan" ]]; then
  echo "错误: ${XBOARD_DIR} 不是有效的 Xboard 根目录（缺少 artisan）"
  exit 1
fi

echo "==> 目标 Xboard: ${XBOARD_DIR}"
echo "==> 仓库根目录: ${ROOT_DIR}"

mkdir -p "${XBOARD_DIR}/plugins-core"
mkdir -p "${XBOARD_DIR}/public/landing"

echo "==> 复制支付插件"
cp -a "${ROOT_DIR}/overlay/plugins-core/." "${XBOARD_DIR}/plugins-core/"

echo "==> 复制落地页与 KHQR 说明页"
cp -a "${ROOT_DIR}/overlay/public/landing/." "${XBOARD_DIR}/public/landing/"
cp -a "${ROOT_DIR}/overlay/public/aba-khqr-pay.html" "${XBOARD_DIR}/public/aba-khqr-pay.html"

echo "==> 备份并替换 routes/web.php"
if [[ -f "${XBOARD_DIR}/routes/web.php" ]]; then
  cp -a "${XBOARD_DIR}/routes/web.php" "${XBOARD_DIR}/routes/web.php.bak.$(date +%Y%m%d%H%M%S)"
fi
cp -a "${ROOT_DIR}/overlay/routes-web.php" "${XBOARD_DIR}/routes/web.php"

if id www &>/dev/null; then
  chown -R www:www \
    "${XBOARD_DIR}/plugins-core/JeepayAbaQr" \
    "${XBOARD_DIR}/plugins-core/JeepayAbaPc" \
    "${XBOARD_DIR}/plugins-core/JeepayPaypal" \
    "${XBOARD_DIR}/plugins-core/TokenPay" \
    "${XBOARD_DIR}/public/landing" \
    "${XBOARD_DIR}/public/aba-khqr-pay.html" \
    "${XBOARD_DIR}/routes/web.php" 2>/dev/null || true
elif id www-data &>/dev/null; then
  chown -R www-data:www-data \
    "${XBOARD_DIR}/plugins-core/JeepayAbaQr" \
    "${XBOARD_DIR}/plugins-core/JeepayAbaPc" \
    "${XBOARD_DIR}/plugins-core/JeepayPaypal" \
    "${XBOARD_DIR}/plugins-core/TokenPay" \
    "${XBOARD_DIR}/public/landing" \
    "${XBOARD_DIR}/public/aba-khqr-pay.html" \
    "${XBOARD_DIR}/routes/web.php" 2>/dev/null || true
fi

if command -v php &>/dev/null; then
  echo "==> 清理 Laravel 缓存"
  (cd "${XBOARD_DIR}" && php artisan optimize:clear || true)
  (cd "${XBOARD_DIR}" && php artisan route:clear || true)
fi

echo ""
echo "Overlay 安装完成。"
echo "请到管理后台启用插件并配置支付方式。"
echo "文档: docs/DEPLOY.md 与 docs/PLUGINS-ONLY.md"
echo "支持: https://t.me/lngsuan"
