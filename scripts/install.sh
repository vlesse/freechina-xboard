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
echo " 文件层安装完成（官方 Xboard + FreeChina overlay）。"
echo ""
echo " 重要说明（小白必读）："
echo "  - 本脚本已自动：下载/使用官方 Xboard，并叠加上前端与支付插件"
echo "  - 本脚本不会：创建数据库、申请 SSL、写宝塔站点"
echo "  - 网站运行目录必须是: ${XBOARD_DIR}/public"
echo "    （不是 freechina-xboard 仓库目录本身）"
echo ""
echo " 接下来请手动："
echo "  1) Nginx/宝塔：站点根目录 = ${XBOARD_DIR}/public"
echo "  2) 伪静态 try_files（见 docker/nginx-rewrite.conf 或 docs/DEPLOY.md）"
echo "  3) 复制 .env，填写数据库 / Redis / APP_URL"
echo "  4) 官方初始化，例如:"
echo "       cd ${XBOARD_DIR} && php artisan xboard:install"
echo "     （以官方当前文档为准）"
echo "  5) 后台启用支付插件；Jeepay:"
echo "       商户后台 https://payment.free--china.com/"
echo "       网关 https://pay.free--china.com （无尾斜杠）"
echo ""
echo " 详细一步一步: docs/DEPLOY.md （路径 A）"
echo " 支持: https://t.me/lngsuan"
echo "=========================================="
