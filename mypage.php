<?php
require __DIR__ . '/auth.php';

$cu = current_user();
if (!$cu) { header('Location: ' . $BASE . '/account.php?next=' . rawurlencode($BASE . '/mypage.php')); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ucsrf_check();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'nick') {
        $nk = trim((string)($_POST['nickname'] ?? ''));
        if ($nk === '') { $nk = '会員'; }
        $nk = function_exists('mb_substr') ? mb_substr($nk, 0, 60) : substr($nk, 0, 180);
        $pdo->prepare("UPDATE users SET nickname=? WHERE id=?")->execute([$nk, (int)$cu['id']]);
        header('Location: ' . $BASE . '/mypage.php?m=nick'); exit;
    }
    if ($act === 'pw') {
        $cur = (string)($_POST['cur'] ?? '');
        $n1  = (string)($_POST['pw1'] ?? '');
        $n2  = (string)($_POST['pw2'] ?? '');
        $h = $pdo->prepare("SELECT password_hash FROM users WHERE id=?"); $h->execute([(int)$cu['id']]);
        $hash = (string)$h->fetchColumn();
        if (!password_verify($cur, $hash)) { header('Location: ' . $BASE . '/mypage.php?e=cur');   exit; }
        if (strlen($n1) < 8)               { header('Location: ' . $BASE . '/mypage.php?e=len');   exit; }
        if ($n1 !== $n2)                   { header('Location: ' . $BASE . '/mypage.php?e=match'); exit; }
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($n1, PASSWORD_DEFAULT), (int)$cu['id']]);
        header('Location: ' . $BASE . '/mypage.php?m=pw'); exit;
    }
    if ($act === 'email_req') {
        $newEmail = strtolower(trim((string)($_POST['new_email'] ?? '')));
        $pwc = (string)($_POST['cur'] ?? '');
        $h = $pdo->prepare("SELECT password_hash FROM users WHERE id=?"); $h->execute([(int)$cu['id']]);
        if (!password_verify($pwc, (string)$h->fetchColumn())) { header('Location: ' . $BASE . '/mypage.php?e=ecur#email'); exit; }
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL))     { header('Location: ' . $BASE . '/mypage.php?e=eemail#email'); exit; }
        if (strtolower((string)$cu['email']) === $newEmail)    { header('Location: ' . $BASE . '/mypage.php?e=esame#email'); exit; }
        $chk = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>?"); $chk->execute([$newEmail, (int)$cu['id']]);
        if ($chk->fetch()) { header('Location: ' . $BASE . '/mypage.php?e=eused#email'); exit; }
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pdo->prepare("DELETE FROM email_changes WHERE user_id=?")->execute([(int)$cu['id']]);
        $pdo->prepare("INSERT INTO email_changes (user_id,new_email,code_hash,expires_at) VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
            ->execute([(int)$cu['id'], $newEmail, hash('sha256', $code)]);
        send_mail_jp($newEmail, '【まちナビ】メールアドレス変更の認証コード',
            "新しいメールアドレスの確認コードです。\n\n認証コード： {$code}\n\n15分以内にマイページでご入力ください。\n心当たりがない場合は破棄してください。\n");
        header('Location: ' . $BASE . '/mypage.php?m=ereq#email'); exit;
    }
    if ($act === 'email_confirm') {
        $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
        $ec = $pdo->prepare("SELECT * FROM email_changes WHERE user_id=?"); $ec->execute([(int)$cu['id']]);
        $row = $ec->fetch();
        if (!$row)                                          { header('Location: ' . $BASE . '/mypage.php?e=enoreq#email'); exit; }
        if (strtotime((string)$row['expires_at']) < time()) { header('Location: ' . $BASE . '/mypage.php?e=eexp#email');   exit; }
        if ((int)$row['attempts'] >= 5)                     { header('Location: ' . $BASE . '/mypage.php?e=eatt#email');   exit; }
        if (!hash_equals((string)$row['code_hash'], hash('sha256', $code))) {
            $pdo->prepare("UPDATE email_changes SET attempts=attempts+1 WHERE user_id=?")->execute([(int)$cu['id']]);
            header('Location: ' . $BASE . '/mypage.php?e=ecode#email'); exit;
        }
        $chk = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>?"); $chk->execute([$row['new_email'], (int)$cu['id']]);
        if ($chk->fetch()) {
            $pdo->prepare("DELETE FROM email_changes WHERE user_id=?")->execute([(int)$cu['id']]);
            header('Location: ' . $BASE . '/mypage.php?e=eused#email'); exit;
        }
        $pdo->prepare("UPDATE users SET email=? WHERE id=?")->execute([$row['new_email'], (int)$cu['id']]);
        $pdo->prepare("DELETE FROM email_changes WHERE user_id=?")->execute([(int)$cu['id']]);
        header('Location: ' . $BASE . '/mypage.php?m=edone#email'); exit;
    }
}

$MSG = ['nick'=>'ニックネームを変更しました。', 'pw'=>'パスワードを変更しました。', 'ereq'=>'確認コードを新しいメールアドレスに送信しました。', 'edone'=>'メールアドレスを変更しました。'];
$ERR = ['cur'=>'現在のパスワードが違います。', 'len'=>'新しいパスワードは8文字以上にしてください。', 'match'=>'新しいパスワード（確認）が一致しません。', 'ecur'=>'現在のパスワードが違います。', 'eemail'=>'メールアドレスの形式が正しくありません。', 'esame'=>'現在と同じメールアドレスです。', 'eused'=>'このメールアドレスは既に使用されています。', 'enoreq'=>'変更リクエストが見つかりません。', 'eexp'=>'認証コードの有効期限が切れました。', 'eatt'=>'入力回数の上限に達しました。', 'ecode'=>'認証コードが正しくありません。'];
$msg = $MSG[$_GET['m'] ?? ''] ?? '';
$err = $ERR[$_GET['e'] ?? ''] ?? '';

$st = $pdo->prepare(
    "SELECT cu.code, cu.used_at, c.title, s.id AS shop_id, s.name AS shop_name
     FROM coupon_uses cu
     JOIN coupons c ON c.id = cu.coupon_id
     JOIN shops s   ON s.id = c.shop_id
     WHERE cu.user_id = ?
     ORDER BY cu.used_at DESC"
);
$st->execute([(int)$cu['id']]);
$uses = $st->fetchAll();

$fq = $pdo->prepare("SELECT s.id, s.name, s.area, s.category FROM favorites f JOIN shops s ON s.id=f.shop_id WHERE f.user_id=? AND s.status='published' ORDER BY f.created_at DESC");
$fq->execute([(int)$cu['id']]);
$favs = $fq->fetchAll();

// お気に入り店舗の「本日の出勤」
$tq = $pdo->prepare(
    "SELECT s.id AS shop_id, s.name AS shop_name, a.name AS staff_name, a.note
     FROM favorites f
     JOIN shops s ON s.id = f.shop_id AND s.status='published'
     JOIN attendance a ON a.shop_id = s.id AND a.work_date = CURDATE()
     WHERE f.user_id = ?
     ORDER BY s.name, a.id"
);
$tq->execute([(int)$cu['id']]);
$favToday = [];
foreach ($tq->fetchAll() as $row) {
    $sid = (int)$row['shop_id'];
    if (!isset($favToday[$sid])) { $favToday[$sid] = ['name' => $row['shop_name'], 'staff' => []]; }
    $favToday[$sid]['staff'][] = ['name' => $row['staff_name'], 'note' => $row['note']];
}

$ecq = $pdo->prepare("SELECT new_email FROM email_changes WHERE user_id=?");
$ecq->execute([(int)$cu['id']]);
$emailPending = $ecq->fetch();

$rq = $pdo->prepare("SELECT r.shop_id, r.rating, r.body, r.status, r.created_at, s.name AS shop_name FROM reviews r JOIN shops s ON s.id=r.shop_id WHERE r.user_id=? ORDER BY r.created_at DESC");
$rq->execute([(int)$cu['id']]);
$myReviews = $rq->fetchAll();

$base = $BASE;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>マイページ｜まちナビ</title>
<link rel="icon" href="<?= h($base) ?>/image/favicon.ico" sizes="any">
<link rel="icon" type="image/svg+xml" href="<?= h($base) ?>/image/favicon.svg">
<link rel="apple-touch-icon" href="<?= h($base) ?>/image/apple-touch-icon.png">
<meta name="theme-color" content="#1b1b20">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap" rel="stylesheet">
<style>
:root{--bg:#1b1b20;--paper:#24242a;--soft:#2d2d34;--ink:#f0f0f3;--muted:#9a9aa3;--line:#3a3a42;--accent:#eeeef1}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Zen Kaku Gothic New",sans-serif;background:radial-gradient(900px 360px at 50% -20%,rgba(255,255,255,.035),transparent),var(--bg);color:var(--ink);line-height:1.7;-webkit-font-smoothing:antialiased}
a{color:inherit}
html,body{max-width:100%;overflow-x:hidden}
header{background:var(--paper);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:50}
.bar{max-width:1120px;margin:0 auto;padding:14px 24px;display:flex;align-items:center;justify-content:space-between}
.logo{display:flex;align-items:center;gap:11px;text-decoration:none;color:inherit}
.logo .mark{width:40px;height:40px;border-radius:5px;background:var(--bg);border:1px solid var(--line);display:grid;place-items:center}
.logo .name{font-weight:900;font-size:19px;letter-spacing:.02em}
.logo .name small{display:block;font-weight:400;font-size:11px;color:var(--muted);letter-spacing:.18em}
.nav{display:flex;gap:26px;font-size:14px;font-weight:500;align-items:center}
.nav a{color:var(--muted);text-decoration:none}
.nav a:hover{color:var(--ink)}
.nav .uname{color:var(--ink);font-weight:700}
.menu-btn{display:none;background:transparent;border:1px solid var(--line);border-radius:5px;width:42px;height:38px;cursor:pointer;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:0;margin-left:auto}
.menu-btn span{display:block;width:18px;height:2px;background:var(--ink);border-radius:2px}
@media(max-width:720px){
  .menu-btn{display:flex}
  .bar{position:relative}
  .nav{display:none;position:absolute;top:100%;left:0;right:0;background:var(--paper);border-top:1px solid var(--line);border-bottom:1px solid var(--line);flex-direction:column;gap:0;padding:6px 0;z-index:60}
  .nav.open{display:flex}
  .nav a,.nav .uname{padding:12px 22px}
}
main{max-width:760px;margin:0 auto;padding:34px 24px 60px}
h1{font-size:22px;margin-bottom:4px}
.lead{color:var(--muted);font-size:13px;margin-bottom:18px}
.card{background:var(--paper);border:1px solid var(--line);border-radius:4px;padding:22px 24px}
.card h2{font-size:15px;margin:0 0 10px}
.list{font-size:14px}
.list .row{border-top:1px solid var(--line);padding:12px 0}
.list .row:first-child{border-top:none}
.muted{color:var(--muted);font-size:13px}
.card+.card{margin-top:20px}
label{display:block;font-size:12px;color:var(--muted);margin:12px 0 5px}
input[type=text],input[type=password]{width:100%;background:var(--bg);border:1px solid var(--line);border-radius:3px;padding:11px 13px;font-size:15px;font-family:inherit;color:var(--ink)}
.btn{display:inline-block;margin-top:14px;background:var(--accent);color:var(--bg);font-weight:700;border:none;border-radius:3px;padding:11px 20px;font-size:14px;font-family:inherit;cursor:pointer;text-decoration:none}
.btn.sec{background:transparent;color:var(--ink);border:1px solid var(--line)}
.msg{background:#1e2b1e;border:1px solid #3c6b3c;color:#bfe6bf;padding:11px 14px;border-radius:3px;font-size:13.5px;margin-bottom:16px}
.err{background:#2b1e1e;border:1px solid #6e3b3b;color:#e7a3a3;padding:11px 14px;border-radius:3px;font-size:13.5px;margin-bottom:16px}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.rv-badge{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:2px;margin-left:8px;vertical-align:middle}
.rv-badge.ok{background:#1e3a2a;color:#9fe0bb;border:1px solid #2f6f4f}
.rv-badge.wait{background:#3a341e;color:#e6d49f;border:1px solid #6f5f2f}
@media(max-width:520px){.row2{grid-template-columns:1fr}}
footer{background:#141418;border-top:1px solid var(--line);margin-top:10px}
.foot{max-width:1160px;margin:0 auto;padding:28px 24px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 22px;font-size:13px;color:#85858e}
.foot-brand{font-weight:900;color:var(--ink);font-size:16px}
.foot-links{display:flex;flex-wrap:wrap;gap:8px 18px}
.foot a{color:#85858e}.foot a:hover{color:var(--ink)}
.foot-cr{margin-left:auto;color:#6a6a73;font-size:12px}
</style>
<?= ga_tag() ?>
</head>
<body>
<header>
  <div class="bar">
    <a class="logo" href="<?= h($base) ?>/index.php"><div class="mark"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#eeeef1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6.5 H20 L12 14 Z"/><path d="M12 14 V20"/><path d="M8 20 H16"/></svg></div><div class="name">まちナビ<small>SHOP GUIDE</small></div></a>
    <button class="menu-btn" aria-label="メニュー" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="nav">
      <a href="<?= h($base) ?>/index.php">探す</a>
      <a href="<?= h($base) ?>/guide.php">初回ガイド</a>
      <span class="uname"><?= h($cu['nickname'] ?: $cu['email']) ?> さん</span>
      <a href="<?= h($base) ?>/logout.php">ログアウト</a>
    </nav>
  </div>
</header>

<main>
  <h1>マイページ</h1>
  <p class="lead"><?= h($cu['nickname'] ?: $cu['email']) ?> さん（<?= h($cu['email']) ?>）</p>

  <?php if ($msg): ?><div class="msg"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <div class="card">
    <h2>プロフィール</h2>
    <form method="post" action="mypage.php">
      <?= ucsrf_field() ?>
      <input type="hidden" name="act" value="nick">
      <label>ニックネーム</label>
      <input type="text" name="nickname" value="<?= h($cu['nickname']) ?>" maxlength="60" placeholder="会員">
      <button class="btn" type="submit">ニックネームを保存</button>
    </form>
  </div>

  <div class="card">
    <h2>パスワード変更</h2>
    <form method="post" action="mypage.php">
      <?= ucsrf_field() ?>
      <input type="hidden" name="act" value="pw">
      <label>現在のパスワード</label>
      <input type="password" name="cur" required autocomplete="current-password">
      <div class="row2">
        <div><label>新しいパスワード（8文字以上）</label><input type="password" name="pw1" required autocomplete="new-password"></div>
        <div><label>新しいパスワード（確認）</label><input type="password" name="pw2" required autocomplete="new-password"></div>
      </div>
      <button class="btn" type="submit">パスワードを変更</button>
    </form>
  </div>

  <div class="card" id="email">
    <h2>メールアドレス変更</h2>
    <p class="muted" style="margin-bottom:10px">現在のメールアドレス: <strong><?= h($cu['email']) ?></strong></p>
    <form method="post" action="mypage.php#email">
      <?= ucsrf_field() ?>
      <input type="hidden" name="act" value="email_req">
      <label>新しいメールアドレス</label>
      <input type="email" name="new_email" required autocomplete="email">
      <label>現在のパスワード（確認のため）</label>
      <input type="password" name="cur" required autocomplete="current-password">
      <button class="btn" type="submit">確認コードを送る</button>
    </form>
    <?php if ($emailPending): ?>
    <div style="border-top:1px solid var(--line);margin-top:16px;padding-top:14px">
      <p class="muted" style="margin-bottom:8px"><strong><?= h($emailPending['new_email']) ?></strong> に確認コードを送信済みです。コードを入力してください（有効期限15分）。</p>
      <form method="post" action="mypage.php#email">
        <?= ucsrf_field() ?>
        <input type="hidden" name="act" value="email_confirm">
        <label>認証コード（6桁）</label>
        <input type="text" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]*" placeholder="123456" required style="letter-spacing:.3em;text-align:center">
        <button class="btn" type="submit">変更を確定する</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>お気に入り店舗の本日の出勤</h2>
    <?php if ($favToday): ?>
    <div class="list">
      <?php foreach ($favToday as $sid => $info): ?>
      <div class="row">
        <a href="<?= h($base) ?>/shop.php?id=<?= (int)$sid ?>#sec-today" style="font-weight:700;color:var(--accent);text-decoration:none"><?= h($info['name']) ?></a>
        <div class="muted" style="margin-top:4px">
          <?php foreach ($info['staff'] as $stf): ?>
            <span style="display:inline-block;margin:2px 8px 2px 0"><?= h($stf['name']) ?><?= $stf['note'] !== '' ? '（' . h($stf['note']) . '）' : '' ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="muted">本日、お気に入り店舗の出勤情報はまだありません。</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>お気に入り店舗</h2>
    <?php if ($favs): ?>
    <div class="list">
      <?php foreach ($favs as $f): ?>
      <div class="row">
        <a href="<?= h($base) ?>/shop.php?id=<?= (int)$f['id'] ?>" style="font-weight:700;color:var(--accent);text-decoration:none"><?= h($f['name']) ?></a>
        <div class="muted"><?= h($f['area']) ?> ／ <?= h($f['category']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="muted">お気に入り店舗はまだありません。店舗ページの「お気に入り」から追加できます。</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>自分の口コミ</h2>
    <?php if ($myReviews): ?>
    <div class="list">
      <?php foreach ($myReviews as $r): $snip = function_exists('mb_substr') ? mb_substr((string)$r['body'], 0, 80) : substr((string)$r['body'], 0, 80); ?>
      <div class="row">
        <div>
          <a href="<?= h($base) ?>/shop.php?id=<?= (int)$r['shop_id'] ?>" style="font-weight:700;color:var(--accent);text-decoration:none"><?= h($r['shop_name']) ?></a>
          <span class="rv-badge <?= $r['status'] === 'approved' ? 'ok' : 'wait' ?>"><?= $r['status'] === 'approved' ? '公開中' : '承認待ち' ?></span>
        </div>
        <div class="muted">評価 <?= (int)$r['rating'] ?>/5 ／ <?= h(substr((string)$r['created_at'], 0, 16)) ?></div>
        <div style="font-size:13.5px;margin-top:4px"><?= h($snip) ?><?= (function_exists('mb_strlen') ? mb_strlen((string)$r['body']) : strlen((string)$r['body'])) > 80 ? '…' : '' ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="muted">投稿した口コミはまだありません。</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>利用したクーポン</h2>
    <?php if ($uses): ?>
    <div class="list">
      <?php foreach ($uses as $u): ?>
      <div class="row">
        <div><a href="<?= h($base) ?>/shop.php?id=<?= (int)$u['shop_id'] ?>" style="font-weight:700;color:var(--accent);text-decoration:none"><?= h($u['shop_name']) ?></a> — <?= h($u['title']) ?></div>
        <div class="muted">コード <?= h($u['code']) ?> ／ <?= h(substr((string)$u['used_at'], 0, 16)) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="muted">まだ利用したクーポンはありません。</p>
    <?php endif; ?>
  </div>
</main>

<footer>
  <div class="foot">
    <div class="foot-brand">まちナビ</div>
    <nav class="foot-links">
      <a href="<?= h($base) ?>/terms.php">利用規約</a>
      <a href="<?= h($base) ?>/privacy.php">プライバシーポリシー</a>
      <a href="<?= h($base) ?>/contact.php?type=listing">掲載ご希望の店舗様へ</a>
      <a href="<?= h($base) ?>/contact.php?type=ad">広告掲載のお問い合わせ</a>
      <a href="<?= h($base) ?>/contact.php">お問い合わせ</a>
    </nav>
    <div class="foot-cr">Copyright (c) 2026 まちナビ（運営：Example Inc.）All Rights Reserved.</div>
  </div>
</footer>

<script>
(function(){
  var b=document.querySelector('.menu-btn'), n=document.querySelector('.nav');
  if(!b||!n) return;
  b.addEventListener('click',function(e){ e.stopPropagation(); var o=n.classList.toggle('open'); b.setAttribute('aria-expanded', o?'true':'false'); });
  document.addEventListener('click',function(e){ if(n.classList.contains('open') && !n.contains(e.target) && !b.contains(e.target)){ n.classList.remove('open'); b.setAttribute('aria-expanded','false'); } });
})();
</script>
</body>
</html>
