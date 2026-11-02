<?php
require __DIR__ . '/auth.php';

if (current_user()) { header('Location: ' . $BASE . '/mypage.php'); exit; }

function send_reset_mail(string $to, string $link): void {
    $subject = '【まちナビ】パスワード再設定のご案内';
    $body = "まちナビ パスワード再設定\n\n下記のリンクから、1時間以内に新しいパスワードを設定してください。\n\n{$link}\n\n※このメールに心当たりがない場合は、破棄してください。\n";
    send_mail_jp($to, $subject, $body);
}

$sent = isset($_GET['sent']);
$err  = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ucsrf_check();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'メールアドレスを正しく入力してください。';
    } else {
        $u = $pdo->prepare("SELECT id, email, status FROM users WHERE email=?");
        $u->execute([$email]);
        $row = $u->fetch();
        if ($row && ($row['status'] ?? 'active') === 'active') {
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);
            $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))")
                ->execute([(int)$row['id'], $hash]);
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? parse_url(SITE_URL, PHP_URL_HOST));
            send_reset_mail((string)$row['email'], $origin . $BASE . '/reset.php?token=' . $token);
        }
        // ユーザー列挙を避けるため、存在有無にかかわらず同じ表示
        header('Location: ' . $BASE . '/forgot.php?sent=1');
        exit;
    }
}

member_header('パスワード再設定');
?>
<div class="card">
  <h1>パスワード再設定</h1>
  <?php if ($sent): ?>
    <div class="ok">ご入力のメールアドレスにアカウントが存在する場合、再設定用リンクを送信しました。メールをご確認ください（届かない場合は迷惑メールもご確認ください）。</div>
    <p class="alt"><a href="<?= h($BASE) ?>/account.php">ログインへ戻る</a></p>
  <?php else: ?>
    <p class="lead">登録済みのメールアドレスを入力してください。再設定用のリンクをお送りします。</p>
    <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
    <form method="post" action="forgot.php">
      <?= ucsrf_field() ?>
      <label>メールアドレス</label>
      <input type="email" name="email" value="<?= h($email) ?>" required autocomplete="email">
      <button class="btn" type="submit">再設定リンクを送る</button>
    </form>
    <p class="alt"><a href="<?= h($BASE) ?>/account.php">ログインへ戻る</a></p>
  <?php endif; ?>
</div>
<?php member_footer(); ?>
