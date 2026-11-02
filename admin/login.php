<?php
require __DIR__ . '/lib.php';

$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    if (login_locked('admin')) {
        $err = '試行回数が多すぎます。しばらくしてからお試しください。';
    } else {
        $pass = (string)($_POST['pass'] ?? '');
        if (defined('ADMIN_KEY') && ADMIN_KEY !== '' && hash_equals((string)ADMIN_KEY, $pass)) {
            login_ok('admin');
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            header('Location: ' . $ADMIN_BASE . '/index.php');
            exit;
        }
        login_fail('admin');
        $err = 'パスワードが違います。';
    }
}

if (!empty($_SESSION['admin'])) {
    header('Location: ' . $ADMIN_BASE . '/index.php');
    exit;
}

admin_header('ログイン');
?>
<div class="card" style="max-width:420px;margin:40px auto">
  <h2>管理ログイン</h2>
  <?php if ($err): ?><div class="msg" style="background:#b5483f"><?= h($err) ?></div><?php endif; ?>
  <form method="post" action="login.php">
    <?= csrf_field() ?>
    <label>管理パスワード（ADMIN_KEY）</label>
    <input type="password" name="pass" autofocus autocomplete="current-password">
    <div style="margin-top:16px"><button class="btn" type="submit">ログイン</button></div>
  </form>
  <p class="muted" style="margin-top:14px">このページの手前に .htaccess の Basic 認証もかかります。</p>
</div>
<?php admin_footer(); ?>
