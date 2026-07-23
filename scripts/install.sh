#!/usr/bin/env bash
# FreeChina Xboard 一键安装：克隆官方 Xboard + 应用 overlay
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
XBOARD_DIR="${XBOARD_DIR:-/www/wwwroot/xboard}"
XBOARD_REPO="${XBOARD_REPO:-https://github.com/cedar2025/Xboard.git}"
XBOARD_BRANCH="${XBOARD_BRANCH:-master}"

echo "=========================================="
echo " FreeChina Xboard 安装"
echo " 目标目录: ${XBOARD_DIR}"
echo " 上游仓库: ${XBOARD_REPO} (${XBOARD_BRANCH})"
echo " 支持: https://t.me/lngsuan"
echo "=========================================="

if [[ "$(id -u)" -ne 0 ]]; then
  echo "建议使用 root 执行（或确保对 ${XBOARD_DIR} 有写权限）"
fi

command -v git >/dev/null || { echo "请先安装 git"; exit 1; }
command -v php >/dev/null || { echo "请先安装 php 8.2+"; exit 1; }
command -v composer >/dev/null || echo "警告: 未检测到 composer，请稍后自行 composer install"

if [[ -d "${XBOARD_DIR}/.git" ]]; then
  echo "==> 目录已存在，跳过 clone，直接打 overlay"
else
  echo "==> 克隆官方 Xboard..."
  mkdir -p "$(dirname "${XBOARD_DIR}")"
  git clone --depth 1 -b "${XBOARD_BRANCH}" "${XBOARD_REPO}" "${XBOARD_DIR}" \
    || git clone --depth 1 "${XBOARD_REPO}" "${XBOARD_DIR}"
fi

if [[ -f "${XBOARD_DIR}/composer.json" ]] && command -v composer >/dev/null; then
  echo "==> composer install"
  (cd "${XBOARD_DIR}" && composer install --no-dev --optimize-autoloader --no-interaction || true)
fi

echo "==> 应用 FreeChina overlay"
export XBOARD_DIR
bash "${ROOT_DIR}/scripts/install-overlay.sh" "${XBOARD_DIR}"

echo ""
echo "=========================================="
echo " 文件层安装完成。接下来请手动："
echo " 1) 配置 Nginx 站点根目录为: ${XBOARD_DIR}/public"
echo " 2) 配置伪静态 try_files（见 docker/nginx-rewrite.conf）"
echo " 3) 复制 .env 并填写数据库 / Redis"
echo " 4) 执行官方初始化，例如:"
echo "      cd ${XBOARD_DIR} && php artisan xboard:install"
echo "    （以官方当前文档命令为准）"
echo " 5) 后台启用支付插件并填写 Jeepay / TokenPay"
echo " 详细: docs/DEPLOY.md"
echo " 支持: https://t.me/lngsuan"
echo "=========================================="
