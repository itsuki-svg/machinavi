<?php
require __DIR__ . '/auth.php';

$token = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));
$row = null;
if ($token !== '' && ctype_xdigit($token) && strlen($token) >= 32) {
    $q = $pdo->prepare("SELECT id, user_id FROM password_resets WHERE token_hash=? AND used=0 AND expires_at>=NOW() ORDER BY id DESC LIMIT 1");
    $q->execute([hash('sha256', $token)]);
    $row = $q->fetch();
}
$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $row) {
    ucsrf_check();
    $p1 = (string)($_POST['pw1'] ?? '');
    $p2 = (string)($_POST['pw2'] ?? '');
    if (strlen($p1) < 8) {
        $err = 'パスワードは8文字以上にしてください。';
    } elseif ($p1 !== $p2) {
        $err = '確認用パスワードが一致しません。';
    } else {
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($p1, PASSWORD_DEFAULT), (int)$row['user_id']]);
        // このトークンと同ユーザーの未使用トークンを無効化
        $pdo->prepare("UPDATE password_resets SET used=1 WHERE user_id=? AND used=0")->execute([(int)$row['user_id']]);
        header('Location: ' . $BASE . '/account.php?reset=1');
        exit;
    }
}

member_header('パスワード再設定');
?>
<div class="card">
  <h1>新しいパスワードの設定</h1>
  <?php if (!$row): ?>
    <div class="err">リンクが無効か、有効期限が切れています。お手数ですが、もう一度お手続きください。</div>
    <p class="alt"><a href="<?= h($BASE) ?>/forgot.php">再設定をやり直す</a></p>
  <?php else: ?>
    <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
    <form method="post" action="reset.php">
      <?= ucsrf_field() ?>
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <label>新しいパスワード（8文字以上）</label>
      <input type="password" name="pw1" required autocomplete="new-password">
      <label>新しいパスワード（確認）</label>
      <input type="password" name="pw2" required autocomplete="new-password">
      <button class="btn" type="submit">パスワードを設定する</button>
    </form>
  <?php endif; ?>
</div>
<?php member_footer(); ?>
