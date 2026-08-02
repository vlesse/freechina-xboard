#!/usr/bin/env bash
# 仅安装支付插件到现有 Xboard（自动识别新旧架构）
#
# 架构 A（新/部分发行版）：plugins-core/Xxx/Plugin.php  + PaymentInterface
# 架构 B（经典 v2board/本机 10.0.0.195 新装）：app/Payments/Xxx.php
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
XBOARD_DIR="${XBOARD_DIR:-${1:-}}"

if [[ -z "${XBOARD_DIR}" ]]; then
  echo "用法: bash scripts/install-plugins-only.sh /path/to/xboard"
  exit 1
fi

if [[ ! -f "${XBOARD_DIR}/artisan" ]]; then
  echo "错误: ${XBOARD_DIR} 不是有效的 Xboard 根目录（缺少 artisan）"
  exit 1
fi

# 探测架构
MODE=""
if [[ -f "${XBOARD_DIR}/app/Services/PaymentService.php" ]] \
  && grep -q "App\\\\Payments" "${XBOARD_DIR}/app/Services/PaymentService.php" 2>/dev/null; then
  # 经典：PaymentService 加载 app/Payments/*
  if [[ -d "${XBOARD_DIR}/app/Payments" ]]; then
    MODE="payments"
  fi
fi

if [[ -z "${MODE}" ]]; then
  if [[ -d "${XBOARD_DIR}/plugins-core" ]] \
    || [[ -f "${XBOARD_DIR}/app/Contracts/PaymentInterface.php" ]] \
    || [[ -f "${XBOARD_DIR}/app/Services/Plugin/AbstractPlugin.php" ]]; then
    MODE="plugins-core"
  fi
fi

# 若两种都像：优先看 PaymentService 实际加载路径
if [[ -f "${XBOARD_DIR}/app/Services/PaymentService.php" ]]; then
  if grep -q "plugins-core\\|Plugin\\\\" "${XBOARD_DIR}/app/Services/PaymentService.php" 2>/dev/null; then
    MODE="plugins-core"
  elif grep -q "App\\\\Payments" "${XBOARD_DIR}/app/Services/PaymentService.php" 2>/dev/null; then
    MODE="payments"
  fi
fi

if [[ -z "${MODE}" ]]; then
  # 默认：有 app/Payments 用经典，否则 plugins-core
  if [[ -d "${XBOARD_DIR}/app/Payments" ]]; then
    MODE="payments"
  else
    MODE="plugins-core"
  fi
fi

echo "==> 检测到 Xboard 支付架构: ${MODE}"
echo "==> 目标目录: ${XBOARD_DIR}"

WEB_USER=""
if id www &>/dev/null; then WEB_USER=www
elif id www-data &>/dev/null; then WEB_USER=www-data
fi

if [[ "${MODE}" == "payments" ]]; then
  mkdir -p "${XBOARD_DIR}/app/Payments"
  echo "==> 复制经典支付网关到 app/Payments/"
  for f in JeepayAbaQr JeepayAbaPc JeepayPaypal JeepayMidtrans TokenPay; do
    src="${ROOT_DIR}/overlay/payments/${f}.php"
    if [[ ! -f "${src}" ]]; then
      echo "错误: 缺少 ${src}"
      exit 1
    fi
    cp -a "${src}" "${XBOARD_DIR}/app/Payments/${f}.php"
    echo "    + app/Payments/${f}.php"
  done
  if [[ -n "${WEB_USER}" ]]; then
    chown -R "${WEB_USER}:${WEB_USER}" \
      "${XBOARD_DIR}/app/Payments/JeepayAbaQr.php" \
      "${XBOARD_DIR}/app/Payments/JeepayAbaPc.php" \
      "${XBOARD_DIR}/app/Payments/JeepayPaypal.php" \
      "${XBOARD_DIR}/app/Payments/JeepayMidtrans.php" \
      "${XBOARD_DIR}/app/Payments/TokenPay.php" 2>/dev/null || true
  fi
else
  mkdir -p "${XBOARD_DIR}/plugins-core"
  echo "==> 复制 plugins-core 插件"
  for d in JeepayAbaQr JeepayAbaPc JeepayPaypal JeepayMidtrans TokenPay; do
    cp -a "${ROOT_DIR}/overlay/plugins-core/${d}" "${XBOARD_DIR}/plugins-core/"
    echo "    + plugins-core/${d}"
  done
  if [[ -n "${WEB_USER}" ]]; then
    chown -R "${WEB_USER}:${WEB_USER}" \
      "${XBOARD_DIR}/plugins-core/JeepayAbaQr" \
      "${XBOARD_DIR}/plugins-core/JeepayAbaPc" \
      "${XBOARD_DIR}/plugins-core/JeepayPaypal" \
      "${XBOARD_DIR}/plugins-core/JeepayMidtrans" \
      "${XBOARD_DIR}/plugins-core/TokenPay" 2>/dev/null || true
  fi
fi

# 公共静态页（说明页 / 二维码）
if [[ -d "${XBOARD_DIR}/public" ]]; then
  for f in aba-khqr-pay.html aba-khqr-pay.php qrcode.min.js qr-img.php; do
    if [[ -f "${ROOT_DIR}/overlay/public/${f}" ]]; then
      cp -a "${ROOT_DIR}/overlay/public/${f}" "${XBOARD_DIR}/public/${f}"
      echo "==> 已复制 public/${f}"
      if [[ -n "${WEB_USER}" ]]; then
        chown "${WEB_USER}:${WEB_USER}" "${XBOARD_DIR}/public/${f}" 2>/dev/null || true
      fi
    fi
  done
fi

if [[ -f "${XBOARD_DIR}/artisan" ]] && command -v php &>/dev/null; then
  (cd "${XBOARD_DIR}" && php artisan optimize:clear || true)
fi

echo ""
echo "=========================================="
echo " 安装完成（架构: ${MODE}）"
if [[ "${MODE}" == "payments" ]]; then
  echo " 后台 → 支付配置 → 添加，接口下拉应出现："
  echo "   JeepayAbaQr / JeepayAbaPc / JeepayPaypal / JeepayMidtrans / TokenPay"
  echo " 无需「启用插件」菜单（经典版没有 plugins-core）"
else
  echo " 后台 → 插件 启用 jeepay_aba_qr 等，再 支付配置 添加"
fi
echo " KHQR 说明页: https://你的域名/aba-khqr-pay.php"
echo " 文档: docs/PLUGINS-ONLY.md"
echo " 支持: https://t.me/lngsuan"
echo "=========================================="
