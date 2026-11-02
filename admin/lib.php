<?php
declare(strict_types=1);

require __DIR__ . '/../db.php';   // $pdo, h(), ads(), ADMIN_KEY

session_start();

// パス基準
$ADMIN_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/'); // 例: /machinavi/admin
$APP_BASE   = rtrim(dirname($ADMIN_BASE), '/');                   // 例: /machinavi
$ADMIN_DIR  = __DIR__;
$APP_DIR    = dirname($ADMIN_DIR);
$UPLOAD_DIR = $APP_DIR . '/uploads/shops';                        // 物理パス
$UPLOAD_URL = $APP_BASE . '/uploads/shops';                       // Web パス

// ログアウト
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . $ADMIN_BASE . '/login.php');
    exit;
}

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">'; }
function csrf_check(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        // POST本文がサイズ上限を超えると $_POST が空になる（アップロード失敗）
        if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            http_response_code(413);
            exit('アップロードサイズが大きすぎます。画像を小さくして再度お試しください。');
        }
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            exit('セッションが切れた可能性があります。前の画面に戻って再読み込みし、もう一度お試しください。');
        }
    }
}

// ログイン必須
function require_login(): void {
    global $ADMIN_BASE;
    if (empty($_SESSION['admin'])) {
        header('Location: ' . $ADMIN_BASE . '/login.php');
        exit;
    }
}

function admin_header(string $title): void {
    global $ADMIN_BASE;
    $loggedIn = !empty($_SESSION['admin']);
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>' . h($title) . '｜まちナビ 管理</title>';
    echo '<style>'
        . 'body{font-family:"Zen Kaku Gothic New",system-ui,sans-serif;background:#1b1b20;color:#f0f0f3;margin:0;line-height:1.6}'
        . 'a{color:#9ab7e0}'
        . '.wrap{max-width:1000px;margin:0 auto;padding:20px 18px 70px}'
        . '.top{display:flex;align-items:center;gap:16px;border-bottom:1px solid #3a3a42;padding:14px 0;margin-bottom:22px}'
        . '.top h1{font-size:18px;margin:0}.top .sp{flex:1}.top a{font-size:13px}'
        . '.btn{display:inline-block;background:#eeeef1;color:#1b1b20;border:none;font-weight:700;padding:9px 16px;border-radius:3px;cursor:pointer;text-decoration:none;font-size:14px}'
        . '.btn.sec{background:transparent;color:#f0f0f3;border:1px solid #3a3a42}'
        . '.btn.danger{background:#b5483f;color:#fff}.btn.sm{padding:5px 10px;font-size:12px}'
        . 'table{width:100%;border-collapse:collapse;font-size:14px}'
        . 'th,td{text-align:left;padding:9px 10px;border-bottom:1px solid #2d2d34;vertical-align:middle}'
        . 'th{color:#9a9aa3;font-size:12px;font-weight:700}'
        . '.card{background:#24242a;border:1px solid #3a3a42;border-radius:4px;padding:18px 20px;margin-bottom:18px}'
        . '.card h2{font-size:15px;margin:0 0 14px}'
        . 'label{display:block;font-size:12px;color:#9a9aa3;margin:12px 0 4px}'
        . 'input[type=text],input[type=number],input[type=password],input[type=datetime-local],select,textarea{width:100%;box-sizing:border-box;background:#1b1b20;border:1px solid #3a3a42;color:#f0f0f3;border-radius:3px;padding:9px 11px;font-family:inherit;font-size:14px}'
        . 'textarea{min-height:90px;resize:vertical}'
        . '.row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}@media(max-width:640px){.row2{grid-template-columns:1fr}}'
        . '.pill{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:3px}'
        . '.pill.pub{background:#2f6f4f;color:#dff5e9}.pill.draft{background:#5c5c64;color:#fff}.pill.pr{background:#caa24a;color:#1b1b20}'
        . '.msg{background:#2f6f4f;color:#dff5e9;padding:10px 14px;border-radius:3px;margin-bottom:16px;font-size:14px}'
        . '.muted{color:#9a9aa3;font-size:12px}'
        . '.thumbs{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px}'
        . '.thumbs .t{width:130px;border:1px solid #3a3a42;border-radius:3px;padding:6px;text-align:center}'
        . '.thumbs .t .im{height:78px;background:#3c3c45;background-size:cover;background-position:center;border-radius:2px;margin-bottom:6px;display:grid;place-items:center;color:#9a9aa3;font-size:11px}'
        . '.inline{display:inline}'
        . '</style></head><body><div class="wrap">';
    echo '<div class="top"><h1>まちナビ 管理</h1>';
    if ($loggedIn) {
        echo '<a href="' . h($ADMIN_BASE) . '/index.php">ダッシュボード</a>';
        echo '<a href="' . h($ADMIN_BASE) . '/ad_edit.php">広告枠</a>';
        echo '<a href="' . h($ADMIN_BASE) . '/reviews.php">口コミ</a>';
        echo '<a href="' . h($ADMIN_BASE) . '/inquiries.php">お問い合わせ</a>';
        echo '<span class="sp"></span><a href="' . h($ADMIN_BASE) . '/?logout=1">ログアウト</a>';
    }
    echo '</div>';
}

function admin_footer(): void {
    echo '</div></body></html>';
}

// mbstring が無い環境でも安全に短縮
function mbcut(string $s, int $n): string {
    if (function_exists('mb_substr') && function_exists('mb_strlen')) {
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '…' : $s;
    }
    return strlen($s) > $n ? substr($s, 0, $n) . '…' : $s;
}
