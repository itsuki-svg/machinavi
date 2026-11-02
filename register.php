<?php
require __DIR__ . '/auth.php';

if (current_user()) { header('Location: ' . safe_next($BASE)); exit; }

$err = '';
$email = '';
$nick  = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ucsrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    $nick  = trim((string)($_POST['nickname'] ?? ''));
    $pw    = (string)($_POST['password'] ?? '');
    $pw2   = (string)($_POST['password2'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'メールアドレスの形式が正しくありません。';
    } elseif (strlen($pw) < 8) {
        $err = 'パスワードは8文字以上にしてください。';
    } elseif ($pw !== $pw2) {
        $err = 'パスワード（確認）が一致しません。';
    } else {
        $st = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $st->execute([$email]);
        if ($st->fetch()) {
            $err = 'このメールアドレスは既に登録されています。';
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            if ($nick === '') { $nick = '会員'; }
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $pdo->prepare("DELETE FROM email_verifications WHERE email=?")->execute([$email]);
            $pdo->prepare("INSERT INTO email_verifications (email,password_hash,nickname,code_hash,expires_at) VALUES (?,?,?,?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
                ->execute([$email, $hash, $nick, hash('sha256', $code)]);
            send_mail_jp(
                $email,
                '【まちナビ】認証コード',
                "まちナビ 会員登録の認証コードです。\n\n認証コード： {$code}\n\n15分以内に登録画面でご入力ください。\n※心当たりがない場合は破棄してください。\n"
            );
            $_SESSION['pending_email'] = $email;
            $_SESSION['pending_next']  = (string)($_POST['next'] ?? '');
            $_SESSION['pending_sent']  = time();
            header('Location: ' . $BASE . '/verify.php');
            exit;
        }
    }
}

$next = (string)($_GET['next'] ?? ($_POST['next'] ?? ''));
member_header('新規登録');
?>
<div class="card">
  <h1>新規登録</h1>
  <p class="lead">アカウントを作るとクーポンが利用できます。</p>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <form method="post" action="register.php">
    <?= ucsrf_field() ?>
    <input type="hidden" name="next" value="<?= h($next) ?>">
    <label>メールアドレス</label>
    <input type="email" name="email" value="<?= h($email) ?>" required>
    <label>ニックネーム（任意）</label>
    <input type="text" name="nickname" value="<?= h($nick) ?>" maxlength="60">
    <label>パスワード（8文字以上）</label>
    <input type="password" name="password" required autocomplete="new-password">
    <label>パスワード（確認）</label>
    <input type="password" name="password2" required autocomplete="new-password">
    <button class="btn" type="submit">登録する</button>
  </form>
  <p class="alt">すでにアカウントをお持ちの方は <a href="login.php<?= $next !== '' ? '?next=' . h(rawurlencode($next)) : '' ?>">ログイン</a></p>
</div>
<?php member_footer(); ?>
