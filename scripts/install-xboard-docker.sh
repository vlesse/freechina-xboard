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
echo "==> 检查容器内路径 /www ..."
docker exec "${CONTAINER}" ls -la /www/artisan /www/public >/dev/null

echo "==> 复制说明页与二维码资源到 /www/public"
for f in aba-khqr-pay.html aba-khqr-pay.php qrcode.min.js qr-img.php; do
  if [[ -f "${ROOT_DIR}/overlay/public/${f}" ]]; then
    docker cp "${ROOT_DIR}/overlay/public/${f}" "${CONTAINER}:/www/public/${f}"
    echo "    + /www/public/${f}"
  fi
done

# 经典支付网关（若容器是 app/Payments 架构）
if docker exec "${CONTAINER}" test -d /www/app/Payments; then
  echo "==> 检测到 app/Payments，复制经典支付类"
  for f in JeepayAbaQr JeepayAbaPc JeepayPaypal JeepayMidtrans TokenPay; do
    if [[ -f "${ROOT_DIR}/overlay/payments/${f}.php" ]]; then
      docker cp "${ROOT_DIR}/overlay/payments/${f}.php" "${CONTAINER}:/www/app/Payments/${f}.php"
      echo "    + /www/app/Payments/${f}.php"
    fi
  done
fi

# plugins-core（若存在）
if docker exec "${CONTAINER}" test -d /www/plugins-core || docker exec "${CONTAINER}" test -f /www/app/Contracts/PaymentInterface.php 2>/dev/null; then
  if docker exec "${CONTAINER}" test -d /www/plugins-core 2>/dev/null || docker exec "${CONTAINER}" mkdir -p /www/plugins-core; then
    echo "==> 尝试复制 plugins-core（若目录可用）"
    for d in JeepayAbaQr JeepayAbaPc JeepayPaypal JeepayMidtrans TokenPay; do
      if [[ -d "${ROOT_DIR}/overlay/plugins-core/${d}" ]]; then
        docker cp "${ROOT_DIR}/overlay/plugins-core/${d}" "${CONTAINER}:/www/plugins-core/" 2>/dev/null \
          && echo "    + plugins-core/${d}" || true
      fi
    done
  fi
fi

echo "==> 修补 Caddy：让 /aba-khqr-pay.html 等走静态文件，其余仍反代 Octane"
docker exec "${CONTAINER}" sh -c '
set -e
CADDY=/etc/caddy/Caddyfile
if [ ! -f "$CADDY" ]; then
  echo "未找到 $CADDY"
  exit 1
fi
cp -a "$CADDY" "${CADDY}.bak.freechina.$(date +%Y%m%d%H%M%S)" 2>/dev/null || true

# 已打过补丁则跳过
if grep -q "FreeChina tip pages" "$CADDY" 2>/dev/null; then
  echo "Caddy 已包含 FreeChina 静态规则，跳过写入"
else
  # 在 reverse_proxy 到 Octane 之前插入静态 handle
  # 官方文件结构：@ws ... reverse_proxy @ws ... 然后 reverse_proxy 127.0.0.1:7002
  cat > /tmp/fc_caddy_snip.txt << "SNIP"

	# FreeChina tip pages (must be before Octane reverse_proxy)
	@fc_static {
		path /aba-khqr-pay.html /aba-khqr-pay.php /qrcode.min.js /qr-img.php
	}
	handle @fc_static {
		root * /www/public
		file_server
	}

SNIP
  # 插入到第一个「非 @ws 的 reverse_proxy」之前
  if grep -q "reverse_proxy 127.0.0.1" "$CADDY"; then
    # 用 awk 插入
    awk "
      /reverse_proxy 127.0.0.1:.*OCTANE|reverse_proxy 127.0.0.1:7002/ && !done {
        while ((getline line < \"/tmp/fc_caddy_snip.txt\") > 0) print line
        close(\"/tmp/fc_caddy_snip.txt\")
        done=1
      }
      { print }
    " "$CADDY" > /tmp/Caddyfile.new && mv /tmp/Caddyfile.new "$CADDY"
    echo "已写入静态 file_server 规则"
  else
    echo "警告: 未匹配到 Octane reverse_proxy 行，尝试追加到 server 块"
    # 兜底：在最后一个 } 前插入过复杂，打印提示
    cat /tmp/fc_caddy_snip.txt
    echo "请手动把以上片段插入 Caddyfile 中 reverse_proxy 127.0.0.1:7002 之前"
  fi
fi

chown -R www:www /www/public/aba-khqr-pay.html /www/public/qrcode.min.js 2>/dev/null || true
chmod 644 /www/public/aba-khqr-pay.html /www/public/qrcode.min.js 2>/dev/null || true

# 重载 Caddy
if command -v caddy >/dev/null; then
  caddy validate --config /etc/caddy/Caddyfile 2>/dev/null || true
  # supervisor 管理时发信号
  pkill -HUP caddy 2>/dev/null || caddy reload --config /etc/caddy/Caddyfile 2>/dev/null || true
fi
echo "Caddy 已尝试 reload"
'

# 再从宿主机触发 reload（有的环境 pkill 在 docker exec 里权限不足）
docker exec "${CONTAINER}" sh -c 'pkill -HUP caddy 2>/dev/null || kill -HUP $(pidof caddy) 2>/dev/null || true' || true

echo ""
echo "==> 容器内文件确认"
docker exec "${CONTAINER}" ls -la /www/public/aba-khqr-pay.html /www/public/qrcode.min.js 2>&1 || true

echo ""
echo "==> 本机经 7001 探测（容器内 curl）"
docker exec "${CONTAINER}" sh -c 'wget -q -S -O /dev/null http://127.0.0.1:7001/aba-khqr-pay.html 2>&1 | head -15' \
  || docker exec "${CONTAINER}" sh -c 'curl -sI http://127.0.0.1:7001/aba-khqr-pay.html | head -15' \
  || true

echo ""
echo "=========================================="
echo " 完成。请在后台把「金额说明页 URL」改成："
echo "   https://你的域名/aba-khqr-pay.html"
echo " 然后重新下一笔订单测试（旧链接不会变）。"
echo ""
echo " 若仍 404："
echo "   docker exec -it ${CONTAINER} cat /etc/caddy/Caddyfile"
echo "   确认 @fc_static / file_server 在 reverse_proxy 7002 之前"
echo "   docker restart ${CONTAINER}"
echo "=========================================="
