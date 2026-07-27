<?php
/**
 * Same-origin KHQR image (no overseas CDN).
 * Usage: /qr-img.php?t=URL_ENCODED_TEXT
 * Requires: qrencode (libqrencode) on the server.
 */
declare(strict_types=1);

$text = isset($_GET['t']) ? (string) $_GET['t'] : '';
// Allow EMV / KHQR payloads; reject empty or huge
if ($text === '' || strlen($text) > 2048) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'bad request';
    exit;
}

// Prefer qrencode CLI (installed on FreeChina VPS)
$bin = trim((string) shell_exec('command -v qrencode 2>/dev/null'));
if ($bin === '') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'qrencode not installed';
    exit;
}

$cmd = escapeshellarg($bin)
    . ' -o - -t PNG -s 6 -m 2 -l L -- '
    . escapeshellarg($text);

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$proc = proc_open($cmd, $descriptors, $pipes, null, null);
if (!is_resource($proc)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'proc failed';
    exit;
}
fclose($pipes[0]);
$png = stream_get_contents($pipes[1]);
$err = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$code = proc_close($proc);

if ($code !== 0 || $png === false || strlen($png) < 32) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'qrencode failed';
    if ($err) {
        // do not leak much; optional log
    }
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=120');
header('X-Content-Type-Options: nosniff');
echo $png;
