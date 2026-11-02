<?php
require __DIR__ . '/auth.php';

if (current_user()) { header('Location: ' . safe_next($BASE)); exit; }

$err = '';
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ucsrf_check();
    if (login_locked('member')) {
        $err = '試行回数が多すぎます。しばらくしてから再度お試しください。';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $pw    = (string)($_POST['password'] ?? '');
        $st = $pdo->prepare("SELECT id, password_hash, status FROM users WHERE email=?");
        $st->execute([$email]);
        $row = $st->fetch();
        if ($row && password_verify($pw, $row['password_hash'])) {
            if (($row['status'] ?? 'active') !== 'active') {
                login_fail('member');
                $err = 'このアカウントは退会済みです。再開をご希望の場合はお問い合わせください。';
            } else {
                login_ok('member');
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$row['id'];
                header('Location: ' . safe_next($BASE));
                exit;
            }
        } else {
            login_fail('member');
            $err = 'メールアドレスまたはパスワードが違います。';
        }
    }
}

$next = (string)($_GET['next'] ?? ($_POST['next'] ?? ''));
member_header('ログイン');
?>
<div class="card">
  <h1>ログイン</h1>
  <p class="lead">クーポンの利用にはログインが必要です。</p>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <form method="post" action="login.php">
    <?= ucsrf_field() ?>
    <input type="hidden" name="next" value="<?= h($next) ?>">
    <label>メールアドレス</label>
    <input type="email" name="email" value="<?= h($email) ?>" required>
    <label>パスワード</label>
    <input type="password" name="password" required autocomplete="current-password">
    <button class="btn" type="submit">ログイン</button>
  </form>
  <p class="alt">アカウントをお持ちでない方は <a href="register.php<?= $next !== '' ? '?next=' . h(rawurlencode($next)) : '' ?>">新規登録</a></p>
</div>
<?php member_footer(); ?>
