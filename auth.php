<?php
declare(strict_types=1);

require __DIR__ . '/db.php';   // $pdo, h(), ads()

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');

// 会員フォーム用CSRF
if (empty($_SESSION['ucsrf'])) { $_SESSION['ucsrf'] = bin2hex(random_bytes(16)); }
function ucsrf_field(): string { return '<input type="hidden" name="ucsrf" value="' . h($_SESSION['ucsrf']) . '">'; }
function ucsrf_check(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!hash_equals($_SESSION['ucsrf'] ?? '', (string)($_POST['ucsrf'] ?? ''))) {
            http_response_code(400);
            exit('不正なリクエストです。');
        }
    }
}

// ログイン中の会員（なければ null）
function current_user(): ?array {
    global $pdo;
    static $u = false;
    if ($u !== false) { return $u; }
    $uid = (int)($_SESSION['uid'] ?? 0);
    if ($uid <= 0) { return $u = null; }
    $st = $pdo->prepare("SELECT id, email, nickname FROM users WHERE id=?");
    $st->execute([$uid]);
    return $u = ($st->fetch() ?: null);
}

// 会員ページ共通レイアウト（register/login/mypage 用）
function member_header(string $title): void {
    global $BASE;
    $cu = current_user();
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>' . h($title) . '｜まちナビ</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap" rel="stylesheet">';
    echo '<style>'
        . ':root{--bg:#1b1b20;--paper:#24242a;--soft:#2d2d34;--ink:#f0f0f3;--muted:#9a9aa3;--line:#3a3a42;--accent:#eeeef1}'
        . '*{box-sizing:border-box;margin:0;padding:0}'
        . 'body{font-family:"Zen Kaku Gothic New",sans-serif;background:var(--bg);color:var(--ink);line-height:1.7;-webkit-font-smoothing:antialiased}'
        . 'a{color:inherit;text-decoration:none}'
        . 'header{background:var(--paper);border-bottom:1px solid var(--line)}'
        . '.bar{max-width:760px;margin:0 auto;padding:14px 22px;display:flex;align-items:center;gap:14px}'
        . '.logo{display:flex;align-items:center;gap:11px}.logo .mark{width:38px;height:38px;border-radius:5px;background:var(--bg);border:1px solid var(--line);display:grid;place-items:center}.logo .name{font-weight:900;font-size:18px;letter-spacing:.02em}.logo .name small{display:block;font-weight:400;font-size:11px;color:var(--muted);letter-spacing:.18em}.sp{flex:1}'
        . '.bar a{font-size:13px;color:var(--muted)}.bar a:hover{color:var(--ink)}'
        . '.wrap{max-width:460px;margin:34px auto;padding:0 22px 60px}'
        . '.card{background:var(--paper);border:1px solid var(--line);border-radius:4px;padding:24px}'
        . 'h1{font-size:20px;margin-bottom:6px}'
        . '.lead{color:var(--muted);font-size:13px;margin-bottom:18px}'
        . 'label{display:block;font-size:12px;color:var(--muted);margin:14px 0 5px}'
        . 'input{width:100%;box-sizing:border-box;background:var(--bg);border:1px solid var(--line);color:var(--ink);border-radius:3px;padding:11px 12px;font-family:inherit;font-size:15px}'
        . '.btn{width:100%;margin-top:20px;background:var(--accent);color:var(--bg);border:none;font-weight:900;padding:12px;border-radius:3px;font-size:15px;cursor:pointer}'
        . '.alt{margin-top:16px;font-size:13px;color:var(--muted);text-align:center}.alt a{color:var(--accent)}'
        . '.err{background:#b5483f;color:#fff;padding:10px 13px;border-radius:3px;font-size:14px;margin-bottom:14px}'
        . '.ok{background:#2f6f4f;color:#dff5e9;padding:10px 13px;border-radius:3px;font-size:14px;margin-bottom:14px}'
        . '.list{font-size:14px}.list .row{border-top:1px solid var(--line);padding:12px 0}.list .row:first-child{border-top:none}'
        . '.muted{color:var(--muted);font-size:13px}'
        . '</style>' . ga_tag() . '</head><body>';
    echo '<header><div class="bar"><a class="logo" href="' . h($BASE) . '/index.php" style="text-decoration:none;color:inherit"><div class="mark"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#eeeef1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6.5 H20 L12 14 Z"/><path d="M12 14 V20"/><path d="M8 20 H16"/></svg></div><div class="name">まちナビ<small>SHOP GUIDE</small></div></a><span class="sp"></span>';
    if ($cu) {
        echo '<a href="' . h($BASE) . '/mypage.php">マイページ</a><a href="' . h($BASE) . '/logout.php">ログアウト</a>';
    } else {
        echo '<a href="' . h($BASE) . '/account.php" style="background:var(--accent);color:var(--bg);font-weight:700;padding:7px 13px;border-radius:3px">ログイン / 会員登録</a>';
    }
    echo '</div></header><div class="wrap">';
}
function member_footer(): void { echo '<p style="text-align:center;color:var(--muted);font-size:12px;margin-top:24px">まちナビ ／ 運営：Example Inc.</p></div></body></html>'; }

// next パラメータの安全化（自サイト内パスのみ許可）
function safe_next(string $base): string {
    $n = (string)($_GET['next'] ?? ($_POST['next'] ?? ''));
    if ($n !== '' && $n[0] === '/' && strpos($n, '//') !== 0) { return $n; }
    return $base . '/index.php';
}
