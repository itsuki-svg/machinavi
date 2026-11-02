<?php
require __DIR__ . '/auth.php';

$TYPES = [
    'listing'  => '掲載のご相談（店舗様）',
    'ad'       => '広告掲載のお問い合わせ',
    'withdraw' => '退会・アカウント削除の依頼',
    'other'    => 'その他',
];

$sent = isset($_GET['sent']);
$err  = '';
$type = (string)($_GET['type'] ?? 'other');
if (!isset($TYPES[$type])) { $type = 'other'; }
$name = $email = $body = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ucsrf_check();
    $hp    = trim((string)($_POST['website'] ?? ''));   // ハニーポット（人間は空）
    $type  = (string)($_POST['type'] ?? 'other');
    if (!isset($TYPES[$type])) { $type = 'other'; }
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $body  = trim((string)($_POST['body'] ?? ''));
    $cut = fn(string $s, int $n) => function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n);
    $name = $cut($name, 120); $email = $cut($email, 190); $body = $cut($body, 4000);

    if ($body === '') {
        $err = 'お問い合わせ内容を入力してください。';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'メールアドレスの形式が正しくありません。';
    } elseif ($hp === '') {
        $uid = ($cu = current_user()) ? (int)$cu['id'] : null;
        $st = $pdo->prepare("INSERT INTO inquiries (type,name,email,body,user_id) VALUES (?,?,?,?,?)");
        $st->execute([$type, $name, $email, $body, $uid]);
        header('Location: ' . $BASE . '/contact.php?sent=1');
        exit;
    } else {
        // ハニーポット作動：黙って成功扱い
        header('Location: ' . $BASE . '/contact.php?sent=1');
        exit;
    }
}

member_header('お問い合わせ');
?>
<style>
.wrap{max-width:560px}
select,textarea{width:100%;box-sizing:border-box;background:var(--bg);border:1px solid var(--line);color:var(--ink);border-radius:3px;padding:11px 12px;font-family:inherit;font-size:15px}
textarea{min-height:140px;resize:vertical}
.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.btn{width:auto;padding:12px 28px}
</style>
<div class="card">
  <h1>お問い合わせ</h1>
  <?php if ($sent): ?>
    <div class="ok">お問い合わせを受け付けました。内容を確認のうえ、必要に応じてご返信します。</div>
    <p class="muted">ご協力ありがとうございました。<a href="<?= h($BASE) ?>/index.php" style="color:var(--accent)">トップへ戻る</a></p>
  <?php else: ?>
    <p class="lead">掲載・広告・退会のご依頼など、こちらからお送りください。</p>
    <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
    <form method="post" action="contact.php">
      <?= ucsrf_field() ?>
      <label>お問い合わせ種別</label>
      <select name="type">
        <?php foreach ($TYPES as $k => $v): ?>
        <option value="<?= h($k) ?>"<?= $k === $type ? ' selected' : '' ?>><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
      <label>お名前 / 店舗名（任意）</label>
      <input type="text" name="name" value="<?= h($name) ?>" maxlength="120">
      <label>返信先メールアドレス（任意）</label>
      <input type="email" name="email" value="<?= h($email) ?>" maxlength="190" placeholder="reply@example.com">
      <label>お問い合わせ内容</label>
      <textarea name="body" required maxlength="4000" placeholder="ご用件をご記入ください。"><?= h($body) ?></textarea>
      <div class="hp"><label>Webサイト<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <button class="btn" type="submit">送信する</button>
    </form>
    <p class="alt"><a href="<?= h($BASE) ?>/index.php">トップへ戻る</a></p>
  <?php endif; ?>
</div>
<?php member_footer(); ?>
