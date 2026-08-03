#!/usr/bin/env bash
# 把 FreeChina 支付说明页装进「官方 Docker 镜像」Xboard（cedar2025/xboard）
#
# 官方 Caddy 默认：全部反代到 Octane，不会直接提供 public/*.html
# 所以必须：1) 文件拷进容器 /www/public  2) 改 Caddy 优先 file_server
#
# 用法：
#   bash scripts/install-xboard-docker.sh
#   bash scripts/install-xboard-docker.sh xboard-xboard-1
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
CONTAINER="${1:-xboard-xboard-1}"

if ! command -v docker >/dev/null; then
  echo "错误: 需要 docker 命令"
  exit 1
fi
if ! docker ps --format '{{.Names}}' | grep -qx "${CONTAINER}"; then
  echo "错误: 容器未运行: ${CONTAINER}"
  echo "当前运行中的容器："
  docker ps --format '  {{.Names}}\t{{.Image}}'
  exit 1
fi

echo "==> 容器: ${CONTAINER}"
echo "==> 检查容器内 /www ..."
docker exec "${CONTAINER}" ls -la /www/artisan /www/public

echo "==> 复制说明页到 /www/public"
for f in aba-khqr-pay.html aba-khqr-pay.php qrcode.min.js qr-img.php; do
  if [[ -f "${ROOT_DIR}/overlay/public/${f}" ]]; then
    docker cp "${ROOT_DIR}/overlay/public/${f}" "${CONTAINER}:/www/public/${f}"
    echo "    + /www/public/${f}"
  fi
done

if docker exec "${CONTAINER}" test -d /www/app/Payments; then
  echo "==> 复制经典支付类 app/Payments"
  for f in JeepayAbaQr JeepayAbaPc JeepayPaypal JeepayMidtrans TokenPay; do
    if [[ -f "${ROOT_DIR}/overlay/payments/${f}.php" ]]; then
      docker cp "${ROOT_DIR}/overlay/payments/${f}.php" "${CONTAINER}:/www/app/Payments/${f}.php"
      echo "    + /www/app/Payments/${f}.php"
    fi
  done
fi

if docker exec "${CONTAINER}" test -d /www/plugins-core 2>/dev/null; then
  echo "==> 复制 plugins-core"
  for d in JeepayAbaQr JeepayAbaPc JeepayPaypal JeepayMidtrans TokenPay; do
    if [[ -d "${ROOT_DIR}/overlay/plugins-core/${d}" ]]; then
      docker cp "${ROOT_DIR}/overlay/plugins-core/${d}" "${CONTAINER}:/www/plugins-core/" \
        && echo "    + plugins-core/${d}" || true
    fi
  done
fi

echo "==> 写入 FreeChina Caddyfile（静态 tip 页 + Octane）"
if [[ -f "${ROOT_DIR}/docker/Caddyfile.freechina" ]]; then
  docker exec "${CONTAINER}" sh -c 'cp -a /etc/caddy/Caddyfile /etc/caddy/Caddyfile.bak.freechina 2>/dev/null || true'
  docker cp "${ROOT_DIR}/docker/Caddyfile.freechina" "${CONTAINER}:/etc/caddy/Caddyfile"
  echo "    已替换 /etc/caddy/Caddyfile"
else
  echo "错误: 缺少 ${ROOT_DIR}/docker/Caddyfile.freechina"
  exit 1
fi

docker exec "${CONTAINER}" sh -c 'chown www:www /www/public/aba-khqr-pay.html /www/public/qrcode.min.js 2>/dev/null; chmod 644 /www/public/aba-khqr-pay.html /www/public/qrcode.min.js 2>/dev/null; true'

echo "==> 重启容器"
docker restart "${CONTAINER}"
echo "    等待 8 秒..."
sleep 8

echo "==> 容器内探测"
docker exec "${CONTAINER}" ls -la /www/public/aba-khqr-pay.html /www/public/qrcode.min.js || true
docker exec "${CONTAINER}" sh -c 'wget -S -O /dev/null http://127.0.0.1:7001/aba-khqr-pay.html 2>&1 | head -20' \
  || docker exec "${CONTAINER}" sh -c 'curl -sI http://127.0.0.1:7001/aba-khqr-pay.html | head -15' \
  || true

echo ""
echo "=========================================="
echo " 完成。"
echo " 1) 后台「金额说明页 URL」改成："
echo "      https://你的域名/aba-khqr-pay.html"
echo " 2) 浏览器打开该地址应不是 Laravel 404"
echo " 3) 重新下一笔订单测试"
echo "=========================================="
