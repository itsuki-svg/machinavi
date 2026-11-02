<?php
require __DIR__ . '/auth.php';

if (current_user()) { header('Location: ' . safe_next($BASE)); exit; }

$email = (string)($_SESSION['pending_email'] ?? '');
if ($email === '') { header('Location: ' . $BASE . '/account.php?tab=register'); exit; }

$err = '';
$info = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ucsrf_check();
    $act = (string)($_POST['act'] ?? 'verify');

    if ($act === 'resend') {
        // 60秒に1回まで
        if (time() - (int)($_SESSION['pending_sent'] ?? 0) < 60) {
            $err = 'コードの再送は1分以上あけてお試しください。';
        } else {
            $ev = $pdo->prepare("SELECT password_hash, nickname FROM email_verifications WHERE email=?");
            $ev->execute([$email]); $rowEv = $ev->fetch();
            if ($rowEv) {
                $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE email_verifications SET code_hash=?, attempts=0, expires_at=DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE email=?")
                    ->execute([hash('sha256', $code), $email]);
                send_mail_jp($email, '【まちナビ】認証コード（再送）',
                    "まちナビ 会員登録の認証コードです。\n\n認証コード： {$code}\n\n15分以内にご入力ください。\n");
                $_SESSION['pending_sent'] = time();
                $info = '認証コードを再送しました。メールをご確認ください。';
            } else {
                header('Location: ' . $BASE . '/account.php?tab=register'); exit;
            }
        }
    } else {
        $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
        $ev = $pdo->prepare("SELECT * FROM email_verifications WHERE email=?");
        $ev->execute([$email]); $row = $ev->fetch();
        if (!$row) {
            $err = '認証情報が見つかりません。お手数ですが、もう一度登録してください。';
        } elseif (strtotime((string)$row['expires_at']) < time()) {
            $err = '認証コードの有効期限が切れました。「コードを再送する」からやり直してください。';
        } elseif ((int)$row['attempts'] >= 5) {
            $err = '入力回数の上限に達しました。「コードを再送する」から新しいコードを取得してください。';
        } elseif (!hash_equals((string)$row['code_hash'], hash('sha256', $code))) {
            $pdo->prepare("UPDATE email_verifications SET attempts=attempts+1 WHERE email=?")->execute([$email]);
            $err = '認証コードが正しくありません。';
        } else {
            // 本登録
            try {
                $ins = $pdo->prepare("INSERT INTO users (email, password_hash, nickname, status) VALUES (?,?,?, 'active')");
                $ins->execute([$row['email'], $row['password_hash'], $row['nickname']]);
                $uid = (int)$pdo->lastInsertId();
            } catch (PDOException $e) {
                // 既に登録済み（競合）
                $u = $pdo->prepare("SELECT id FROM users WHERE email=?"); $u->execute([$email]);
                $uid = (int)$u->fetchColumn();
            }
            $pdo->prepare("DELETE FROM email_verifications WHERE email=?")->execute([$email]);
            $next = (string)($_SESSION['pending_next'] ?? '');
            unset($_SESSION['pending_email'], $_SESSION['pending_next'], $_SESSION['pending_sent']);
            if ($uid > 0) {
                session_regenerate_id(true);
                $_SESSION['uid'] = $uid;
            }
            $dest = $next !== '' ? $next : ($BASE . '/mypage.php');
            // safe_next 相当の簡易チェック
            if (strpos($dest, $BASE) !== 0) { $dest = $BASE . '/mypage.php'; }
            header('Location: ' . $dest);
            exit;
        }
    }
}

member_header('メール認証');
?>
<div class="card">
  <h1>メール認証</h1>
  <p class="lead"><strong><?= h($email) ?></strong> 宛に6桁の認証コードを送信しました。メールに記載のコードを入力してください（有効期限15分）。</p>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <?php if ($info): ?><div class="ok"><?= h($info) ?></div><?php endif; ?>
  <form method="post" action="verify.php">
    <?= ucsrf_field() ?>
    <input type="hidden" name="act" value="verify">
    <label>認証コード（6桁）</label>
    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*" placeholder="123456" required style="letter-spacing:.4em;font-size:20px;text-align:center">
    <button class="btn" type="submit">認証して登録を完了</button>
  </form>
  <form method="post" action="verify.php" style="margin-top:14px">
    <?= ucsrf_field() ?>
    <input type="hidden" name="act" value="resend">
    <button type="submit" style="background:none;border:none;color:var(--accent);font-family:inherit;font-size:13px;cursor:pointer;padding:0">コードを再送する</button>
  </form>
  <p class="alt"><a href="<?= h($BASE) ?>/account.php?tab=register">メールアドレスを変えてやり直す</a></p>
</div>
<?php member_footer(); ?>
