<?php
declare(strict_types=1);

// ===== セキュリティ基本設定 =====
ini_set('display_errors', '0');   // 本番では画面に出さない（ログには記録）
ini_set('log_errors', '1');
error_reporting(E_ALL);

$__https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (session_status() === PHP_SESSION_NONE) {
    session_name('portal_sess');                 // 他アプリとセッションを分離
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    if ($__https) { ini_set('session.cookie_secure', '1'); }
    @session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true,
        'secure'   => $__https, 'samesite' => 'Lax',
    ]);
}

// 公開ディレクトリ外の設定を読み込む（複数の候補を順に探索）
$__candidates = [
    dirname(__DIR__) . '/../config.php', // 公開ディレクトリ外（推奨）
    __DIR__ . '/config.php',          // スクリプトと同じフォルダ
    dirname(__DIR__) . '/config.php', // 一つ上のフォルダ（例: ~/www/config.php）
];
$__cfg = null;
foreach ($__candidates as $__c) {
    if (is_file($__c)) { $__cfg = $__c; break; }
}
if ($__cfg === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "設定ファイル（config.php）が見つかりません。以下のいずれかに設置してください:\n";
    foreach ($__candidates as $__c) { echo " - {$__c}\n"; }
    exit;
}
require $__cfg;

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $pdo->exec("SET time_zone = '+09:00'");
} catch (PDOException $e) {
    http_response_code(500);
    exit('データベースに接続できませんでした。');
}

/** HTMLエスケープ */
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** ログイン試行制限（セッション単位・簡易ブルートフォース対策） */
function login_locked(string $scope, int $max = 5, int $window = 300): bool {
    $s = $_SESSION['lt_' . $scope] ?? null;
    return $s && $s['n'] >= $max && (time() - $s['t']) < $window;
}
function login_fail(string $scope): void {
    $k = 'lt_' . $scope; $now = time();
    $s = $_SESSION[$k] ?? ['n' => 0, 't' => 0];
    if ($now - $s['t'] >= 300) { $s = ['n' => 0, 't' => $now]; }
    $s['n']++; $s['t'] = $now; $_SESSION[$k] = $s;
    usleep(400000); // 失敗時にわずかに遅延
}
function login_ok(string $scope): void { unset($_SESSION['lt_' . $scope]); }

/** アップロード画像を検証して保存し、公開URLパスを返す（失敗時は ''） */
function save_uploaded_image(string $field, string $prefix, string $dir, string $urlBase): string {
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) { return ''; }
    $map  = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_GIF=>'gif', IMAGETYPE_WEBP=>'webp'];
    $info = @getimagesize($_FILES[$field]['tmp_name']);
    if (!$info || !isset($map[$info[2]]) || $_FILES[$field]['size'] > 8 * 1024 * 1024) { return ''; }
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $fn = $prefix . bin2hex(random_bytes(5)) . '.' . $map[$info[2]];
    return move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $fn) ? $urlBase . '/' . $fn : '';
}

/**
 * 指定ページ・枠で表示すべき広告を取得（期間・有効フラグを考慮）
 * @return array<int,array<string,mixed>>
 */
function ads(PDO $pdo, string $page, string $key): array {
    $sql = "SELECT * FROM ad_slots
            WHERE active = 1 AND slot_key = ? AND (page = ? OR page = 'both')
              AND (starts_at IS NULL OR starts_at <= NOW())
              AND (ends_at   IS NULL OR ends_at   >= NOW())
            ORDER BY sort, id";
    $st = $pdo->prepare($sql);
    $st->execute([$key, $page]);
    return $st->fetchAll();
}

/**
 * GA4 計測タグ（config で define('GA4_ID','G-XXXX') されている場合のみ出力）
 */
function ga_tag(): string {
    if (!defined('GA4_ID') || GA4_ID === '') { return ''; }
    $id = preg_replace('/[^A-Za-z0-9\-]/', '', (string)GA4_ID);
    if ($id === '') { return ''; }
    return "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$id}\"></script>"
        . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$id}');</script>";
}

/**
 * 営業時間・定休日の文字列から、現在(JST)の営業状態を推定。
 * 解析できない場合は null。 返値: ['label'=>..,'cls'=>'open'|'off'|'closed']
 */
function shop_open_state(string $hours, string $closed): ?array {
    try { $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')); }
    catch (Exception $e) { return null; }
    $wd = ['日','月','火','水','木','金','土'][(int)$now->format('w')];
    if ($closed !== '' && strpos($closed, '不定') === false && strpos($closed, '無休') === false) {
        if (strpos($closed, $wd . '曜') !== false) {
            return ['label' => '本日定休日', 'cls' => 'closed'];
        }
    }
    if (!preg_match('/(\d{1,2}):(\d{2})\s*[〜~\-\x{ff5e}\x{2212}]\s*(翌)?\s*(\d{1,2}):(\d{2})/u', $hours, $m)) {
        return null;
    }
    $sh = (int)$m[1]; $smin = (int)$m[2]; $eh = (int)$m[4]; $emin = (int)$m[5];
    $overnight = ($m[3] !== '') || (($eh * 60 + $emin) <= ($sh * 60 + $smin));
    foreach ([0, 1] as $back) {
        $start = $now->setTime($sh, $smin, 0)->modify('-' . $back . ' day');
        $end = $start->setTime($eh, $emin, 0);
        if ($overnight) { $end = $end->modify('+1 day'); }
        if ($now >= $start && $now < $end) {
            return ['label' => '営業中', 'cls' => 'open'];
        }
    }
    return ['label' => '営業時間外', 'cls' => 'off'];
}

/** 日本語メール送信（mb_send_mail優先） */
function send_mail_jp(string $to, string $subject, string $body): bool {
    $from = defined('MAIL_FROM') ? MAIL_FROM : ('noreply@' . ($_SERVER['HTTP_HOST'] ?? parse_url(SITE_URL, PHP_URL_HOST)));
    $name = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader('まちナビ') : 'まちナビ';
    $headers = 'From: ' . $name . ' <' . $from . '>' . "\r\n" . 'Reply-To: ' . $from;
    $send = static function (string $param) use ($to, $subject, $body, $headers): bool {
        if (function_exists('mb_send_mail')) {
            mb_language('Japanese'); mb_internal_encoding('UTF-8');
            return (bool)@mb_send_mail($to, $subject, $body, $headers, $param);
        }
        return (bool)@mail($to, $subject, $body, $headers, $param);
    };
    // まずエンベロープ送信者(-f)付きで送る（到達率が良い）。拒否されたら既定送信者で再送。
    if ($send('-f' . $from)) { return true; }
    return $send('');
}
