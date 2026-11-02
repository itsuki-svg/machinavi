<?php
if (!headers_sent()) { http_response_code(404); }
$root = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/machinavi/404.php'), '/');
if ($root === '' || $root === '.') { $root = '/machinavi'; }
$esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>ページが見つかりません｜まちナビ</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;700;900&display=swap" rel="stylesheet">
<style>
:root{--bg:#1b1b20;--paper:#24242a;--ink:#f0f0f3;--muted:#9a9aa3;--line:#3a3a42;--accent:#eeeef1}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Zen Kaku Gothic New",sans-serif;background:radial-gradient(900px 360px at 50% -20%,rgba(255,255,255,.035),transparent),var(--bg);color:var(--ink);min-height:100vh;display:flex;flex-direction:column}
header{background:var(--paper);border-bottom:1px solid var(--line)}
.bar{max-width:1120px;margin:0 auto;padding:14px 24px;display:flex;align-items:center;gap:11px}
.mark{width:38px;height:38px;border-radius:5px;background:var(--bg);border:1px solid var(--line);display:grid;place-items:center}
.name{font-weight:900;font-size:18px;letter-spacing:.02em}
.name small{display:block;font-weight:400;font-size:11px;color:var(--muted);letter-spacing:.18em}
main{flex:1;display:grid;place-items:center;padding:60px 24px;text-align:center}
.code{font-size:72px;font-weight:900;letter-spacing:.04em;line-height:1}
h1{font-size:20px;margin:14px 0 8px}
p{color:var(--muted);font-size:14px;margin-bottom:22px}
.btn{display:inline-block;background:var(--accent);color:var(--bg);font-weight:900;text-decoration:none;padding:12px 26px;border-radius:3px}
</style>
</head>
<body>
<header><div class="bar">
  <a href="<?= $esc($root) ?>/index.php" style="display:flex;align-items:center;gap:11px;text-decoration:none;color:inherit">
    <div class="mark"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#eeeef1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6.5 H20 L12 14 Z"/><path d="M12 14 V20"/><path d="M8 20 H16"/></svg></div>
    <div class="name">まちナビ<small>SHOP GUIDE</small></div>
  </a>
</div></header>
<main>
  <div>
    <div class="code">404</div>
    <h1>ページが見つかりません</h1>
    <p>お探しのページは移動または削除された可能性があります。</p>
    <a class="btn" href="<?= $esc($root) ?>/index.php">トップへ戻る</a>
  </div>
</main>
</body>
</html>
