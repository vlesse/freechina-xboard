<?php
/**
 * ABA KHQR 付款说明页（服务端渲染，不依赖 JS 首屏）
 * 金额 + 二维码图在 PHP 里直接输出，Google Chrome / 支付宝内置浏览器都可显示。
 */
declare(strict_types=1);

function g(string $key): string
{
    if (!isset($_GET[$key])) {
        return '';
    }
    $v = $_GET[$key];
    if (is_array($v)) {
        $v = (string) reset($v);
    }
    return trim((string) $v);
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$qr     = g('qr');
$cny    = g('cny');
$khr    = g('khr');
$rate   = g('rate');
$expect = g('expect');
$trade  = g('trade');
$return = g('return');
$payKhr = $expect !== '' ? $expect : $khr;

$ok = ($qr !== '' && $payKhr !== '');

// 同源二维码（qrencode）
$qrImgSrc = $ok ? ('/qr-img.php?t=' . rawurlencode($qr)) : '';

// 展示用
$payKhrShow = $payKhr;
if ($payKhr !== '' && strpos($payKhr, '.') === false && ctype_digit($payKhr)) {
    $payKhrShow = number_format((int) $payKhr, 0, '.', ',');
}

$formula = '-';
if ($cny !== '' && $rate !== '') {
    $formula = h($cny) . ' × ' . h($rate) . ' = ' . h($khr !== '' ? $khr : $payKhr) . '（向上取整）';
}

if ($return === '' && $trade !== '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'free--china.com';
    $return = $scheme . '://' . $host . '/#/order/' . rawurlencode($trade);
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
  <title>支付宝扫码支付 · 请输入瑞尔金额</title>
  <style>
    :root {
      --bg: #0f1419; --card: #1a2332; --text: #e7ecf3; --muted: #8b9bb4;
      --danger: #ff4d4f; --warn: #faad14; --ok: #52c41a; --accent: #1677ff; --border: #2a3548;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", "Microsoft YaHei", sans-serif;
      background: radial-gradient(1200px 600px at 50% -10%, #1e3a5f 0%, var(--bg) 55%);
      color: var(--text); padding: 16px;
    }
    .wrap { max-width: 440px; margin: 0 auto; padding-bottom: 40px; }
    .badge {
      display: inline-block; background: rgba(255,77,79,.15); color: var(--danger);
      border: 1px solid rgba(255,77,79,.45); border-radius: 999px; padding: 4px 12px;
      font-size: 12px; font-weight: 700;
    }
    h1 { font-size: 20px; margin: 12px 0 8px; line-height: 1.35; }
    .card {
      background: var(--card); border: 1px solid var(--border); border-radius: 16px;
      padding: 18px; margin-top: 14px; box-shadow: 0 10px 30px rgba(0,0,0,.25);
    }
    .amount-box { text-align: center; padding: 8px 0 4px; }
    .amount-label { color: var(--muted); font-size: 13px; margin-bottom: 6px; }
    .amount-khr { font-size: 36px; font-weight: 800; color: var(--danger); line-height: 1.15; word-break: break-all; }
    .amount-khr small { font-size: 16px; font-weight: 600; margin-left: 4px; color: #ff7875; }
    .copy-btn, .status-btn {
      margin-top: 12px; width: 100%; border: 0; border-radius: 10px; padding: 12px 14px;
      background: var(--danger); color: #fff; font-size: 15px; font-weight: 700; cursor: pointer;
    }
    .status-btn { background: var(--accent); margin-top: 8px; }
    .hint-ok { display: none; margin-top: 8px; text-align: center; color: var(--ok); font-size: 13px; }
    .pay-status { margin-top: 12px; text-align: center; font-size: 14px; color: var(--warn); min-height: 22px; }
    .pay-status.ok { color: var(--ok); font-weight: 700; }
    .qr-wrap { display: flex; justify-content: center; margin: 8px 0 4px; }
    #qrcode {
      background: #fff; padding: 12px; border-radius: 12px; min-width: 264px; min-height: 264px;
      display: flex; align-items: center; justify-content: center;
    }
    #qrcode img { display: block; width: 240px; height: 240px; }
    .calc { font-size: 14px; line-height: 1.7; }
    .calc .row {
      display: flex; justify-content: space-between; gap: 12px; padding: 6px 0;
      border-bottom: 1px dashed var(--border);
    }
    .calc .row:last-child { border-bottom: 0; }
    .calc .k { color: var(--muted); }
    .calc .v { font-weight: 600; text-align: right; }
    .warn {
      margin-top: 12px; background: rgba(250,173,20,.1); border: 1px solid rgba(250,173,20,.35);
      border-radius: 12px; padding: 12px 14px; color: #ffe58f; font-size: 13px; line-height: 1.65;
    }
    .warn strong { color: #ffd666; }
    .steps { margin: 0; padding-left: 18px; color: var(--muted); font-size: 13px; line-height: 1.75; }
    .footer { margin-top: 16px; text-align: center; color: var(--muted); font-size: 12px; }
    .err {
      background: rgba(255,77,79,.12); border: 1px solid rgba(255,77,79,.4);
      color: #ffccc7; padding: 14px; border-radius: 12px; margin-top: 16px;
    }
    .success-overlay {
      display: none; position: fixed; inset: 0; background: rgba(0,0,0,.72); z-index: 99;
      align-items: center; justify-content: center; padding: 20px;
    }
    .success-overlay.show { display: flex; }
    .success-box {
      background: var(--card); border: 1px solid var(--ok); border-radius: 16px;
      padding: 28px 22px; max-width: 360px; width: 100%; text-align: center;
    }
    .success-box h2 { margin: 0 0 8px; color: var(--ok); font-size: 22px; }
    .success-box p { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.6; }
  </style>
</head>
<body>
  <div class="wrap">
    <span class="badge">重要 · 请手动输入瑞尔金额</span>
    <h1>ABA 个人收款码支付</h1>
    <p style="margin:0;color:var(--muted);font-size:13px;line-height:1.6">
      本二维码为<strong style="color:#fff">固定商业码</strong>，无法自动带入金额。
      请用 <strong style="color:#fff">支付宝</strong> 扫码后，在金额框<strong style="color:var(--danger)">原样输入下面瑞尔数字</strong>。
    </p>

<?php if (!$ok): ?>
    <div class="err">
      参数不完整：缺少二维码或应付金额。请返回订单页重新发起支付。
      <div style="margin-top:8px;font-size:12px;opacity:.85;word-break:break-all">
        debug: qr=<?= $qr === '' ? '(空)' : 'len'.strlen($qr) ?>,
        khr=<?= h($khr) ?>, expect=<?= h($expect) ?>
      </div>
    </div>
<?php else: ?>
    <div id="main">
      <div class="card amount-box">
        <div class="amount-label">请在支付宝 App 中输入（币种：瑞尔 KHR）</div>
        <div class="amount-khr">
          <span id="khrText"><?= h($payKhrShow) ?></span><small>KHR</small>
        </div>
        <button class="copy-btn" type="button" id="copyBtn" data-amount="<?= h($payKhr) ?>">一键复制瑞尔金额</button>
        <div class="hint-ok" id="copied">已复制，请粘贴到支付宝付款金额框</div>
        <div class="pay-status" id="payStatus">正在等待支付到账…</div>
        <button class="status-btn" type="button" id="openOrderBtn" style="display:none">查看订单</button>
      </div>

      <div class="card">
        <div class="qr-wrap">
          <div id="qrcode">
            <!-- 服务端直接输出图片，不依赖任何 JS 库 -->
            <img id="qrImg" src="<?= h($qrImgSrc) ?>" width="240" height="240" alt="KHQR 收款码"
                 onerror="this.onerror=null;this.src='https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=<?= rawurlencode($qr) ?>';" />
          </div>
        </div>
        <p style="text-align:center;margin:8px 0 0;color:var(--muted);font-size:12px">请使用支付宝 App 扫码</p>
      </div>

      <div class="card calc">
        <div class="row"><span class="k">订单人民币</span><span class="v"><?= $cny !== '' ? ('¥' . h($cny) . ' CNY') : '-' ?></span></div>
        <div class="row"><span class="k">汇率</span><span class="v"><?= $rate !== '' ? ('1 CNY = ' . h($rate) . ' KHR') : '-' ?></span></div>
        <div class="row"><span class="k">换算公式</span><span class="v"><?= $formula ?></span></div>
        <div class="row"><span class="k">应付瑞尔</span><span class="v" style="color:var(--danger)"><?= h($payKhrShow) ?> KHR</span></div>
<?php if ($expect !== ''): ?>
        <div class="row">
          <span class="k">系统匹配金额</span>
          <span class="v" style="color:var(--warn)"><?= h($expect) ?> KHR（请严格按此金额）</span>
        </div>
<?php endif; ?>
      </div>

      <div class="warn">
        <strong>务必注意：</strong><br>
        1. 金额币种是 <strong>瑞尔（KHR）</strong>，不要输入人民币数字。<br>
        2. 数字必须与上方完全一致（瑞尔为整数）。<br>
        3. 付款成功后本页会<strong>自动检测并跳转</strong>到订单成功页，请稍候几秒。
      </div>

      <div class="card">
        <div style="font-weight:700;margin-bottom:8px">操作步骤</div>
        <ol class="steps">
          <li>点击「一键复制瑞尔金额」</li>
          <li>打开支付宝 App，扫描上方二维码</li>
          <li>在金额输入框粘贴 / 输入瑞尔金额</li>
          <li>确认币种为 KHR 后完成支付</li>
          <li>回到本页等待自动跳转（也可点「查看订单」）</li>
        </ol>
      </div>

      <div class="footer"><?= $trade !== '' ? ('订单号：' . h($trade)) : '' ?></div>
    </div>
<?php endif; ?>
  </div>

  <div class="success-overlay" id="successOverlay">
    <div class="success-box">
      <h2>支付成功</h2>
      <p>订单已确认到账，正在跳转…</p>
    </div>
  </div>

<?php if ($ok): ?>
  <script>
  (function () {
    var payKhr = <?= json_encode($payKhr, JSON_UNESCAPED_UNICODE) ?>;
    var trade = <?= json_encode($trade, JSON_UNESCAPED_UNICODE) ?>;
    var returnUrl = <?= json_encode($return, JSON_UNESCAPED_UNICODE) ?>;
    var paid = false;
    var pollTimer = null;
    var statusEl = document.getElementById('payStatus');
    var openBtn = document.getElementById('openOrderBtn');

    function goOrder() {
      try { location.replace(returnUrl || (location.origin + '/#/dashboard')); }
      catch (e) { location.href = returnUrl || (location.origin + '/#/dashboard'); }
    }

    document.getElementById('copyBtn').addEventListener('click', function () {
      var t = payKhr || '';
      function ok() {
        var el = document.getElementById('copied');
        el.style.display = 'block';
        setTimeout(function () { el.style.display = 'none'; }, 2500);
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(t).then(ok).catch(function () {
          var ta = document.createElement('textarea');
          ta.value = t; document.body.appendChild(ta); ta.select();
          try { document.execCommand('copy'); } catch (e) {}
          document.body.removeChild(ta); ok();
        });
      } else {
        var ta = document.createElement('textarea');
        ta.value = t; document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta); ok();
      }
    });

    function getCookie(name) {
      var parts = (document.cookie || '').split(';');
      for (var i = 0; i < parts.length; i++) {
        var p = parts[i].trim();
        if (p.indexOf(name + '=') === 0) {
          try { return decodeURIComponent(p.substring(name.length + 1)); } catch (e) { return p.substring(name.length + 1); }
        }
      }
      return '';
    }
    function getAuthToken() {
      var t = getCookie('access_token');
      if (t) return t;
      try {
        t = localStorage.getItem('access_token')
          || sessionStorage.getItem('access_token')
          || localStorage.getItem('authorization') || '';
      } catch (e) {}
      return t || '';
    }
    function markPaid() {
      if (paid) return;
      paid = true;
      if (pollTimer) clearInterval(pollTimer);
      statusEl.className = 'pay-status ok';
      statusEl.textContent = '支付成功！即将返回订单页…';
      openBtn.style.display = 'block';
      openBtn.textContent = '立即查看订单';
      document.getElementById('successOverlay').classList.add('show');
      setTimeout(goOrder, 1500);
    }
    function checkOrderOnce() {
      if (!trade || paid) return;
      var token = getAuthToken();
      if (!token) {
        statusEl.textContent = '未检测到登录状态，付完款后请点「查看订单」或返回用户中心';
        openBtn.style.display = 'block';
        return;
      }
      var url = '/api/v1/user/order/check?trade_no=' + encodeURIComponent(trade);
      fetch(url, {
        method: 'GET',
        headers: { 'Authorization': token, 'Accept': 'application/json' },
        credentials: 'same-origin'
      }).then(function (res) { return res.json().catch(function () { return null; }); })
        .then(function (json) {
          var st = json && typeof json.data !== 'undefined' ? json.data : null;
          if (st === 1 || st === 3 || st === '1' || st === '3') markPaid();
          else if (st === 0 || st === '0') statusEl.textContent = '等待支付到账中…（到账后自动跳转）';
          else if (st === 2 || st === '2') {
            statusEl.textContent = '订单已取消，请返回重新下单';
            if (pollTimer) clearInterval(pollTimer);
          }
        }).catch(function () {});
    }

    openBtn.style.display = 'block';
    openBtn.textContent = '已付款？查看订单';
    openBtn.addEventListener('click', goOrder);
    checkOrderOnce();
    pollTimer = setInterval(checkOrderOnce, 2500);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') checkOrderOnce();
    });
    window.addEventListener('focus', checkOrderOnce);
  })();
  </script>
<?php endif; ?>
</body>
</html>
