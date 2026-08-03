<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <title>跳转中…</title>
  <script>
    // Docker/Caddy 下 .php 不会被 PHP 执行，只当静态文件。
    // 统一跳到纯静态说明页 .html
    (function () {
      var q = location.search || '';
      var h = location.hash || '';
      location.replace('/aba-khqr-pay.html' + q + h);
    })();
  </script>
  <meta http-equiv="refresh" content="0;url=/aba-khqr-pay.html" />
</head>
<body style="background:#0f1419;color:#e7ecf3;font-family:sans-serif;padding:24px;text-align:center">
  <p>正在打开付款说明…</p>
  <p><a id="a" style="color:#1677ff" href="/aba-khqr-pay.html">点此继续</a></p>
  <script>
    document.getElementById('a').href = '/aba-khqr-pay.html' + (location.search || '') + (location.hash || '');
  </script>
</body>
</html>
