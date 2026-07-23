#!/usr/bin/env bash
# 仅安装支付插件到现有 Xboard（不改路由/前端；含 ABA/PayPal/Midtrans/TokenPay）
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
XBOARD_DIR="${XBOARD_DIR:-${1:-}}"

if [[ -z "${XBOARD_DIR}" ]]; then
  echo "用法: bash scripts/install-plugins-only.sh /path/to/xboard"
  exit 1
fi

if [[ ! -d "${XBOARD_DIR}/plugins-core" ]] && [[ ! -f "${XBOARD_DIR}/artisan" ]]; then
  echo "错误: ${XBOARD_DIR} 看起来不是 Xboard 根目录"
  exit 1
fi

mkdir -p "${XBOARD_DIR}/plugins-core"
echo "==> 复制插件到 ${XBOARD_DIR}/plugins-core"
cp -a "${ROOT_DIR}/overlay/plugins-core/JeepayAbaQr" "${XBOARD_DIR}/plugins-core/"
cp -a "${ROOT_DIR}/overlay/plugins-core/JeepayAbaPc" "${XBOARD_DIR}/plugins-core/"
cp -a "${ROOT_DIR}/overlay/plugins-core/JeepayPaypal" "${XBOARD_DIR}/plugins-core/"
cp -a "${ROOT_DIR}/overlay/plugins-core/JeepayMidtrans" "${XBOARD_DIR}/plugins-core/"
cp -a "${ROOT_DIR}/overlay/plugins-core/TokenPay" "${XBOARD_DIR}/plugins-core/"

if [[ -d "${XBOARD_DIR}/public" ]]; then
  cp -a "${ROOT_DIR}/overlay/public/aba-khqr-pay.html" "${XBOARD_DIR}/public/aba-khqr-pay.html"
  echo "==> 已复制 aba-khqr-pay.html"
fi

if id www &>/dev/null; then
  chown -R www:www "${XBOARD_DIR}/plugins-core/JeepayAbaQr" \
    "${XBOARD_DIR}/plugins-core/JeepayAbaPc" \
    "${XBOARD_DIR}/plugins-core/JeepayPaypal" \
    "${XBOARD_DIR}/plugins-core/JeepayMidtrans" \
    "${XBOARD_DIR}/plugins-core/TokenPay" 2>/dev/null || true
elif id www-data &>/dev/null; then
  chown -R www-data:www-data "${XBOARD_DIR}/plugins-core/JeepayAbaQr" \
    "${XBOARD_DIR}/plugins-core/JeepayAbaPc" \
    "${XBOARD_DIR}/plugins-core/JeepayPaypal" \
    "${XBOARD_DIR}/plugins-core/JeepayMidtrans" \
    "${XBOARD_DIR}/plugins-core/TokenPay" 2>/dev/null || true
fi

if [[ -f "${XBOARD_DIR}/artisan" ]] && command -v php &>/dev/null; then
  (cd "${XBOARD_DIR}" && php artisan optimize:clear || true)
fi

echo ""
echo "插件文件已安装。请："
echo "1) 后台启用插件 jeepay_aba_qr / jeepay_aba_pc / jeepay_paypal / jeepay_midtrans / token_pay"
echo "2) 支付配置中添加对应支付方式并填写密钥"
echo "详见 docs/PLUGINS-ONLY.md"
echo "支持: https://t.me/lngsuan"
