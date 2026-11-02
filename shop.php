<?php
require __DIR__ . '/auth.php';

$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM shops WHERE id=? AND status='published'");
$st->execute([$id]);
$shop = $st->fetch();

if (!$shop) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$ostate = shop_open_state((string)$shop['hours'], (string)$shop['closed']);

// 口コミ投稿（承認待ちで保存）
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['review_submit'])) {
    $hp   = trim((string)($_POST['website'] ?? ''));        // ハニーポット（人間は空）
    $nick = trim((string)($_POST['nickname'] ?? ''));
    $rate = (int)($_POST['rating'] ?? 5);
    $body = trim((string)($_POST['body'] ?? ''));
    $cut  = fn(string $s, int $n) => function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n);
    if ($rate < 1) $rate = 1;
    if ($rate > 5) $rate = 5;
    $nick = $nick === '' ? '匿名' : $cut($nick, 60);
    $body = $cut($body, 2000);
    if ($hp === '' && $body !== '') {
        $rcu = current_user();
        $rimg = save_uploaded_image('review_image', 'rv_', __DIR__ . '/uploads/reviews', $base . '/uploads/reviews');
        $st = $pdo->prepare("INSERT INTO reviews (shop_id,user_id,nickname,rating,body,image_path,status) VALUES (?,?,?,?,?,?,'pending')");
        $st->execute([$id, $rcu ? (int)$rcu['id'] : null, $nick, $rate, $body, $rimg]);
    }
    header('Location: ' . $base . '/shop.php?id=' . $id . '&posted=1#sec-review');
    exit;
}

// お気に入り登録/解除（ログイン必須）
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['fav_toggle'])) {
    ucsrf_check();
    $cu = current_user();
    if (!$cu) {
        header('Location: ' . $base . '/account.php?next=' . rawurlencode($base . '/shop.php?id=' . $id));
        exit;
    }
    $chk = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id=? AND shop_id=?");
    $chk->execute([(int)$cu['id'], $id]);
    if ($chk->fetchColumn()) {
        $pdo->prepare("DELETE FROM favorites WHERE user_id=? AND shop_id=?")->execute([(int)$cu['id'], $id]);
    } else {
        $pdo->prepare("INSERT INTO favorites (user_id, shop_id) VALUES (?,?)")->execute([(int)$cu['id'], $id]);
    }
    header('Location: ' . $base . '/shop.php?id=' . $id);
    exit;
}

// クーポン利用（ログイン必須・1人1回）
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['use_coupon'])) {
    ucsrf_check();
    $cu  = current_user();
    $cid = (int)($_POST['coupon_id'] ?? 0);
    if (!$cu) {
        header('Location: ' . $base . '/login.php?next=' . rawurlencode($base . '/shop.php?id=' . $id) . '#sec-map');
        exit;
    }
    $st = $pdo->prepare("SELECT id FROM coupons WHERE id=? AND shop_id=? AND active=1");
    $st->execute([$cid, $id]);
    if ($st->fetch()) {
        $chk = $pdo->prepare("SELECT id FROM coupon_uses WHERE coupon_id=? AND user_id=?");
        $chk->execute([$cid, (int)$cu['id']]);
        if (!$chk->fetch()) {
            $code = strtoupper(bin2hex(random_bytes(3)));
            $pdo->prepare("INSERT INTO coupon_uses (coupon_id, user_id, code) VALUES (?,?,?)")
                ->execute([$cid, (int)$cu['id'], $code]);
        }
    }
    header('Location: ' . $base . '/shop.php?id=' . $id . '#sec-map');
    exit;
}

$st = $pdo->prepare("SELECT * FROM shop_images WHERE shop_id=? ORDER BY sort, id");
$st->execute([$id]); $images = $st->fetchAll();
$st = $pdo->prepare("SELECT tag FROM shop_tags WHERE shop_id=? ORDER BY sort, id");
$st->execute([$id]); $tags = $st->fetchAll(PDO::FETCH_COLUMN);
$st = $pdo->prepare("SELECT * FROM shop_prices WHERE shop_id=? ORDER BY sort, id");
$st->execute([$id]); $prices = $st->fetchAll();
$st = $pdo->prepare("SELECT * FROM shop_staff WHERE shop_id=? ORDER BY sort, id");
$st->execute([$id]); $staff = $st->fetchAll();

$st = $pdo->prepare("SELECT * FROM reviews WHERE shop_id=? AND status='approved' ORDER BY created_at DESC, id DESC");
$st->execute([$id]); $reviews = $st->fetchAll();
$approvedCount = count($reviews);

$st = $pdo->prepare("SELECT * FROM coupons WHERE shop_id=? AND active=1 ORDER BY id");
$st->execute([$id]); $coupons = $st->fetchAll();
$couponUses = [];
$cu = current_user();
if ($cu && $coupons) {
    $ids = array_column($coupons, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare("SELECT coupon_id, code, used_at FROM coupon_uses WHERE user_id=? AND coupon_id IN ($in)");
    $st->execute(array_merge([(int)$cu['id']], $ids));
    foreach ($st->fetchAll() as $r) { $couponUses[(int)$r['coupon_id']] = $r; }
}
$hasCoupon = !empty($coupons);
$isFav = false;
if ($cu) {
    $fq = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id=? AND shop_id=?");
    $fq->execute([(int)$cu['id'], $id]);
    $isFav = (bool)$fq->fetchColumn();
}

$st = $pdo->prepare("SELECT * FROM attendance WHERE shop_id=? AND work_date=CURDATE() ORDER BY id");
$st->execute([$id]); $todayStaff = $st->fetchAll();
$todayDate = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y年n月j日');

$adA = ads($pdo, 'detail', 'A');
$adB = ads($pdo, 'detail', 'B');
$adC = ads($pdo, 'detail', 'C');

if (!$images) {
    $images = [
        ['image_path'=>'','label'=>'メイン写真'],
        ['image_path'=>'','label'=>'サブ写真 1'],
        ['image_path'=>'','label'=>'サブ写真 2'],
        ['image_path'=>'','label'=>'サブ写真 3'],
    ];
}
$slideCount = count($images);

// SEO / OGP / 構造化データ
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin    = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? parse_url(SITE_URL, PHP_URL_HOST));
$siteName  = 'まちナビ';
$canonical = $origin . $base . '/shop.php?id=' . $id;
$pageTitle = $shop['name'] . '（' . trim($shop['area'] . ' ' . $shop['category']) . '）｜' . $siteName;
$rawDesc   = $shop['catchphrase'] !== '' ? $shop['catchphrase'] : (string)$shop['intro'];
$rawDesc   = trim(preg_replace('/\s+/u', ' ', $rawDesc));
$cut       = function_exists('mb_substr') ? 'mb_substr' : 'substr';
$metaDesc  = $cut($rawDesc, 0, 110);
$ogImage   = '';
foreach ($images as $im) {
    if (!empty($im['image_path'])) {
        $ogImage = strpos($im['image_path'], 'http') === 0 ? $im['image_path'] : $origin . $im['image_path'];
        break;
    }
}
if ($ogImage === '') { $ogImage = $origin . $base . '/image/ogp.png'; }
$revAvg = 0.0;
if ($approvedCount > 0) {
    $sum = 0; foreach ($reviews as $rv) { $sum += (int)$rv['rating']; }
    $revAvg = round($sum / $approvedCount, 1);
}
$ld = ['@context' => 'https://schema.org', '@type' => 'BarOrPub', 'name' => $shop['name'], 'url' => $canonical];
if ($shop['address'] !== '') { $ld['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $shop['address'], 'addressLocality' => $shop['area'], 'addressCountry' => 'JP']; }
if ($shop['phone'] !== '')   { $ld['telephone'] = $shop['phone']; }
if ($ogImage !== '')         { $ld['image'] = [$ogImage]; }
if ($approvedCount > 0)       { $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $revAvg, 'reviewCount' => $approvedCount]; }
$ldJson = json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function stars_html(float $r): string {
    $full = (int)floor($r);
    $half = ($r - $full) >= 0.5;
    $out = str_repeat("★", $full);
    if ($half && $full < 5) { $out .= "☆"; $full++; }
    $out .= str_repeat("☆", max(0, 5 - $full));
    return $out;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="<?= h($base) ?>/image/favicon.ico" sizes="any">
<link rel="icon" type="image/svg+xml" href="<?= h($base) ?>/image/favicon.svg">
<link rel="apple-touch-icon" href="<?= h($base) ?>/image/apple-touch-icon.png">
<link rel="manifest" href="<?= h($base) ?>/image/site.webmanifest">
<meta name="theme-color" content="#1b1b20">
<title><?= h($pageTitle) ?></title>
<meta name="description" content="<?= h($metaDesc) ?>">
<link rel="canonical" href="<?= h($canonical) ?>">
<meta property="og:type" content="article">
<meta property="og:site_name" content="<?= h($siteName) ?>">
<meta property="og:title" content="<?= h($pageTitle) ?>">
<meta property="og:description" content="<?= h($metaDesc) ?>">
<meta property="og:url" content="<?= h($canonical) ?>">
<?php if ($ogImage !== ''): ?><meta property="og:image" content="<?= h($ogImage) ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= $ogImage !== '' ? 'summary_large_image' : 'summary' ?>">
<script type="application/ld+json"><?= $ldJson ?></script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#1b1b20;--paper:#24242a;--soft:#2d2d34;--ink:#f0f0f3;--muted:#9a9aa3;
  --line:#3a3a42;--ph:#33333b;--ph2:#3c3c45;--ph3:#45454f;--ph4:#4f4f59;--accent:#eeeef1;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Zen Kaku Gothic New",sans-serif;background:radial-gradient(900px 380px at 50% -20%,rgba(255,255,255,.035),transparent),var(--bg);color:var(--ink);line-height:1.75;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
.dela{font-weight:900;letter-spacing:.01em}

header{background:rgba(20,18,16,.92);border-bottom:1px solid var(--line);backdrop-filter:blur(8px);position:sticky;top:0;z-index:60}
.bar{max-width:1160px;margin:0 auto;padding:13px 22px;display:flex;align-items:center;gap:20px}
.logo{display:flex;align-items:center;gap:9px}
.logo .mark{width:34px;height:34px;border-radius:5px;background:var(--bg);border:1px solid var(--line);display:grid;place-items:center}
.logo .name{font-weight:900;font-size:18px;letter-spacing:.02em;color:var(--ink)}
.nav{display:flex;gap:20px;font-size:13.5px;font-weight:500;margin-left:8px}
.nav a{color:var(--muted)}.nav a:hover{color:var(--ink)}
.hsearch{margin-left:auto;display:flex;gap:8px;align-items:center}
.hsearch input{background:var(--soft);border:1px solid var(--line);border-radius:3px;padding:8px 13px;color:var(--ink);font-family:inherit;font-size:13px;width:160px}
.btn-login{font-size:13px;font-weight:700;border:1px solid var(--accent);color:var(--ink);padding:7px 14px;border-radius:3px}
.btn-reg{font-size:13px;font-weight:700;background:var(--accent);color:var(--bg);padding:8px 15px;border-radius:3px}
.menu-btn{display:none;background:transparent;border:1px solid var(--line);border-radius:5px;width:40px;height:36px;cursor:pointer;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:0}
.menu-btn span{display:block;width:17px;height:2px;background:var(--ink);border-radius:2px}
@media(max-width:860px){
  .hsearch input{display:none}
  .menu-btn{display:flex}
  .bar{position:relative}
  .nav{display:none;position:absolute;top:100%;left:0;right:0;background:var(--paper);border-top:1px solid var(--line);border-bottom:1px solid var(--line);flex-direction:column;gap:0;margin:0;padding:6px 0;z-index:60}
  .nav.open{display:flex}
  .nav a{padding:12px 22px}
}

.wrap{max-width:1160px;margin:0 auto;padding:24px 22px 70px;display:grid;grid-template-columns:1fr 320px;gap:30px}
@media(max-width:920px){.wrap{grid-template-columns:1fr}}

.slot-tag{font-size:10.5px;font-weight:700;letter-spacing:.14em;color:#85858e;display:flex;align-items:center;gap:6px;margin-bottom:7px}
.slot-tag::before{content:"";width:7px;height:7px;border-radius:2px;background:var(--accent)}

.crumb{font-size:12px;color:var(--muted);margin-bottom:10px}
.crumb .area{color:var(--ink);font-weight:700}
.title-row{display:flex;align-items:flex-end;flex-wrap:wrap;gap:12px}
h1.shop{font-weight:900;font-size:33px;letter-spacing:.01em}
.shop-jp{font-size:13px;color:var(--muted);align-self:center}
.open-badge{align-self:center;font-size:12px;font-weight:700;padding:4px 11px;border-radius:3px;border:1px solid var(--line)}
.open-badge.open{background:#1e3a2a;color:#9fe0bb;border-color:#2f6f4f}
.open-badge.off{background:var(--soft);color:var(--muted)}
.open-badge.closed{background:#3a1e1e;color:#e6a3a3;border-color:#7a4444}
.fav-form{margin-left:auto}
.fav-btn{display:inline-flex;align-items:center;gap:7px;background:transparent;border:1px solid var(--line);color:var(--muted);font-family:inherit;font-size:13px;font-weight:700;padding:8px 14px;border-radius:3px;cursor:pointer;text-decoration:none}
.fav-btn:hover{color:var(--ink);border-color:var(--muted)}
.fav-btn.on{color:#e6b8c2;border-color:#7a4450;background:rgba(122,68,80,.12)}
a.fav-btn{align-self:center}
.rate{display:flex;align-items:center;gap:14px;margin:10px 0 4px;flex-wrap:wrap}
.stars{color:var(--accent);font-size:19px;letter-spacing:1px}
.rate .num{font-weight:900;font-size:20px}
.rate .cnt{color:var(--muted);font-size:13px}
.coupon-flag{background:var(--accent);color:var(--bg);font-weight:900;font-size:12px;padding:5px 12px;border-radius:2px}
.catch{font-size:15px;color:#d4d4da;margin:12px 0 18px;font-weight:500}

.slider{position:relative;border-radius:4px;overflow:hidden;height:330px;background:var(--ph2)}
.slides{display:flex;height:100%;transition:transform .5s ease}
.slide{min-width:100%;height:100%;position:relative;display:grid;place-items:center;color:#d4d4da;font-size:13px;letter-spacing:.1em}
.g1{background:var(--ph)}.g2{background:var(--ph2)}.g3{background:var(--ph3)}.g4{background:var(--ph4)}
.slider .arrow{position:absolute;top:50%;transform:translateY(-50%);width:40px;height:40px;border:none;border-radius:5px;background:rgba(0,0,0,.5);color:#fff;font-size:22px;line-height:1;cursor:pointer;display:grid;place-items:center;z-index:3;transition:background .2s}
.slider .arrow:hover{background:rgba(0,0,0,.75)}
.slider .prev{left:12px}.slider .next{right:12px}
.slider .dots{position:absolute;bottom:14px;left:0;right:0;display:flex;justify-content:center;gap:8px;z-index:3}
.slider .dot{width:9px;height:9px;border-radius:50%;border:none;background:rgba(255,255,255,.55);cursor:pointer;padding:0;transition:background .2s,transform .2s}
.slider .dot.on{background:#fff;transform:scale(1.25)}
.slider .count{position:absolute;top:12px;right:14px;background:rgba(0,0,0,.55);color:#fff;font-size:12px;padding:3px 10px;border-radius:3px;z-index:3}

.banner-a{margin:24px 0;border:1.5px dashed var(--line);border-radius:4px;background:var(--soft);padding:16px 20px;display:flex;align-items:center;gap:16px}
.banner-a .pr{font-size:10px;font-weight:900;letter-spacing:.1em;color:var(--ink);border:1px solid var(--accent);padding:2px 7px;border-radius:2px;flex-shrink:0}
.banner-a .txt{font-size:13.5px;color:#d4d4da}.banner-a .txt b{color:var(--ink)}
.banner-a .more{margin-left:auto;font-size:12.5px;font-weight:700;color:var(--ink);white-space:nowrap}

.card{background:var(--paper);border:1px solid var(--line);border-radius:4px;padding:22px 24px;margin-bottom:22px;box-shadow:0 4px 16px rgba(0,0,0,.35)}
.card h2{font-size:17px;font-weight:900;margin-bottom:4px;display:flex;align-items:center;gap:9px}
.card h2::before{content:"";width:5px;height:18px;border-radius:3px;background:var(--accent)}
.card .sub{font-size:12.5px;color:var(--muted);margin-bottom:18px}
.stags{display:flex;flex-wrap:wrap;gap:8px}
.stag{font-size:12px;font-weight:700;background:var(--soft);border:1px solid var(--line);color:#d4d4da;padding:6px 12px;border-radius:3px}

.tabs{display:flex;gap:4px;border-bottom:1px solid var(--line);margin-bottom:22px;overflow-x:auto}
.tab{font-size:13.5px;font-weight:700;color:var(--muted);padding:12px 16px;white-space:nowrap;border-bottom:2px solid transparent}
.tab.on{color:var(--ink);border-bottom-color:var(--accent)}

.intro{font-size:14px;color:#d4d4da;margin-bottom:8px}
.price{width:100%;border-collapse:collapse;margin-top:8px;font-size:13.5px}
.price th,.price td{text-align:left;padding:11px 12px;border-bottom:1px solid var(--line)}
.price thead th{font-size:11.5px;letter-spacing:.06em;color:var(--muted);font-weight:700}
.price tbody th{font-weight:700;color:var(--ink);width:38%}
.price .yen{color:var(--ink);font-weight:900;white-space:nowrap}
.price td:last-child{color:var(--muted);font-size:12.5px}

.infeed{border:1.5px dashed var(--line);border-radius:4px;padding:16px;background:var(--soft)}
.infeed-items{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:6px}
.feed{display:flex;gap:11px;align-items:center;background:var(--paper);border:1px solid var(--line);border-radius:3px;padding:10px}
.feed .thumb{width:54px;height:54px;border-radius:2px;flex-shrink:0;background:var(--ph2)}
.feed .ftxt{font-size:12.5px;line-height:1.5;color:#d4d4da}
.feed .ftxt small{display:block;color:var(--muted);font-weight:700;font-size:10px;letter-spacing:.08em;margin-bottom:2px}
@media(max-width:560px){.infeed-items{grid-template-columns:1fr}}

.staff{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
@media(max-width:560px){.staff{grid-template-columns:1fr}}
.scard{background:var(--soft);border:1px solid var(--line);border-radius:3px;overflow:hidden;text-align:center;padding-bottom:14px}
.scard .av{height:96px;background:var(--ph2)}
.scard:nth-child(2) .av{background:var(--ph)}
.scard:nth-child(3) .av{background:var(--ph3)}
.scard .nm{font-weight:900;font-size:15px;margin:11px 0 4px}
.scard .nm span{font-size:11px;color:var(--muted);font-weight:500;margin-left:4px}
.scard .msg{font-size:12px;color:#d4d4da;padding:0 12px}
.coupon{margin-top:20px;border:2px dashed var(--accent);border-radius:4px;background:var(--soft);padding:18px 20px;display:flex;align-items:center;gap:16px}
.coupon .ic{font-weight:900;font-size:13px;color:var(--bg);background:var(--accent);padding:8px 12px;border-radius:3px;white-space:nowrap}
.coupon .ct{font-size:13.5px;color:#d4d4da}.coupon .ct b{color:var(--ink)}

.side .box{background:var(--paper);border:1px solid var(--line);border-radius:4px;padding:20px;margin-bottom:18px;box-shadow:0 4px 16px rgba(0,0,0,.35)}
.side h3{font-size:14px;font-weight:900;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.side h3::before{content:"";width:4px;height:15px;border-radius:2px;background:var(--accent)}
.info{font-size:13px}
.info .row{display:flex;gap:10px;padding:8px 0;border-bottom:1px dashed var(--line)}
.info .row:last-child{border-bottom:none}
.info .k{color:var(--muted);width:72px;flex-shrink:0;font-size:12px}
.info .v{color:var(--ink)}
.cta{display:block;text-align:center;background:var(--accent);color:var(--bg);font-weight:900;padding:13px;border-radius:3px;font-size:14px;margin-top:4px}
.banner-c{border:1.5px dashed var(--line);border-radius:4px;background:var(--soft);min-height:330px;display:grid;place-items:center;text-align:center;color:var(--muted);font-size:12px;font-weight:600;padding:18px}

footer{background:#141418;border-top:1px solid var(--line);margin-top:10px}
.foot{max-width:1160px;margin:0 auto;padding:28px 24px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 22px;font-size:13px;color:#85858e}
.foot-brand{font-weight:900;color:var(--ink);font-size:16px}
.foot-links{display:flex;flex-wrap:wrap;gap:8px 18px}
.foot a{color:#85858e}.foot a:hover{color:var(--ink)}
.foot-cr{margin-left:auto;color:#6a6a73;font-size:12px}
html{scroll-behavior:smooth}
[id^="sec-"]{scroll-margin-top:86px}
.review{border-top:1px solid var(--line);padding:14px 0}
.star-pick{display:inline-flex;gap:2px}
.star-pick span{font-size:26px;line-height:1;color:var(--line);cursor:pointer;user-select:none}
.star-pick span.on{color:#eecb5a}
.rv-photo{margin-top:8px}
.rv-photo img{max-width:160px;max-height:160px;object-fit:cover;border:1px solid var(--line);border-radius:3px;display:block}
.reviews .review:first-child{border-top:none;padding-top:0}
.review .rh{display:flex;align-items:center;gap:10px;font-size:13px;flex-wrap:wrap}
.review .rh .nm{font-weight:700}
.review .rh .st{color:var(--accent);letter-spacing:1px}
.review .rh .dt{color:var(--muted);font-size:12px;margin-left:auto}
.review .bd{font-size:14px;color:#d4d4da;margin-top:6px}
.rv-form{margin-top:18px;border-top:1px solid var(--line);padding-top:16px}
.rv-form label{display:block;font-size:12px;color:var(--muted);margin:10px 0 4px}
.rv-form input[type=text],.rv-form select,.rv-form textarea{width:100%;box-sizing:border-box;background:var(--bg);border:1px solid var(--line);color:var(--ink);border-radius:3px;padding:9px 11px;font-family:inherit;font-size:14px}
.rv-form textarea{min-height:90px;resize:vertical}
.rv-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}@media(max-width:560px){.rv-row{grid-template-columns:1fr}}
.rv-form .hp{display:none}
.rv-btn{background:var(--accent);color:var(--bg);border:none;font-weight:900;padding:10px 18px;border-radius:3px;cursor:pointer;font-size:14px;margin-top:12px}
.coupons{display:flex;flex-direction:column;gap:12px}
.coupon-card{border:1px solid var(--accent);border-radius:4px;padding:14px 16px;background:rgba(238,238,241,.04)}
.coupon-card .cc-title{font-weight:900;font-size:15px}
.coupon-card .cc-desc{font-size:13.5px;color:#d4d4da;margin-top:6px}
.coupon-card .cc-lock{color:var(--muted)}
.coupon-card .cc-actions{display:flex;gap:10px;margin-top:10px}
.cc-btn{display:inline-block;background:var(--accent);color:var(--bg);border:none;font-weight:900;font-size:13px;padding:9px 16px;border-radius:3px;cursor:pointer;text-decoration:none}
.cc-btn.ghost{background:transparent;color:var(--ink);border:1px solid var(--line)}
.coupon-card form{margin-top:10px}
.coupon-card .cc-used{margin-top:10px;background:#2f6f4f;color:#dff5e9;padding:10px 12px;border-radius:3px;font-size:13.5px}
.coupon-card .cc-used b{font-size:16px;letter-spacing:1px}
.coupon-card .cc-note{font-size:12px;color:#cfe9dd}
.today-list{display:flex;flex-direction:column;gap:8px}
.today-row{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;padding:9px 12px;background:var(--soft);border:1px solid var(--line);border-radius:3px}
.today-row .tname{font-weight:700;font-size:15px}
.today-row .ttime{font-size:13px;color:var(--accent)}
.today-row .tnote{font-size:12.5px;color:var(--muted)}
html,body{max-width:100%;overflow-x:hidden}
img,video{max-width:100%;height:auto}
iframe{max-width:100%}
.banner-a .txt,.title-row,.rate{min-width:0}
.main,.side{min-width:0}
.slider,.price,.stags,.today-list,.coupons{max-width:100%}
.price{table-layout:fixed;word-break:break-word}
@media(max-width:640px){
  .bar{padding:12px 16px;gap:12px}
  .wrap{padding:18px 16px 60px;gap:22px}
  h1.shop{font-size:24px}
  .shop-jp{font-size:12px}
  .slider{height:230px}
  .card{padding:18px 16px;margin-bottom:18px}
  .card h2{font-size:16px}
  .banner-a{flex-wrap:wrap}
  .banner-a .more{margin-left:0}
  .price th,.price td{padding:9px 8px;font-size:13px}
}
.lightbox{position:fixed;inset:0;background:rgba(0,0,0,.92);display:none;align-items:center;justify-content:center;z-index:1000}
.lightbox.open{display:flex}
.lightbox .lb-img{max-width:92vw;max-height:88vh;object-fit:contain;border-radius:4px;box-shadow:0 12px 48px rgba(0,0,0,.6)}
.lightbox .lb-close{position:absolute;top:16px;right:18px;width:44px;height:44px;border:none;background:rgba(255,255,255,.12);color:#fff;font-size:26px;border-radius:6px;cursor:pointer;line-height:1}
.lightbox .lb-arrow{position:absolute;top:50%;transform:translateY(-50%);width:48px;height:48px;border:none;background:rgba(255,255,255,.12);color:#fff;font-size:30px;border-radius:6px;cursor:pointer;display:grid;place-items:center}
.lightbox .lb-prev{left:16px}.lightbox .lb-next{right:16px}
.lightbox .lb-arrow:hover,.lightbox .lb-close:hover{background:rgba(255,255,255,.26)}
@media(max-width:640px){.lightbox .lb-arrow{width:40px;height:40px;font-size:24px}.lightbox .lb-close{top:10px;right:10px}}
</style>
<?= ga_tag() ?>
</head>
<body>

<header>
  <div class="bar">
    <a class="logo" href="<?= h($base) ?>/index.php" style="text-decoration:none;color:inherit"><div class="mark"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#eeeef1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6.5 H20 L12 14 Z"/><path d="M12 14 V20"/><path d="M8 20 H16"/></svg></div><div class="name">まちナビ</div></a>
    <button class="menu-btn" aria-label="メニュー" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="nav">
      <a href="<?= h($base) ?>/index.php">探す</a>
      <a href="<?= h($base) ?>/guide.php">初回ガイド</a>
      <a href="<?= h($base) ?>/map.php">地図</a>
    </nav>
    <div class="hsearch">
      <input type="text" placeholder="店名・エリアで検索">
      <?php if ($cu = current_user()): ?>
      <a class="btn-login" href="<?= h($base) ?>/mypage.php"><?= h($cu['nickname'] ?: 'マイページ') ?></a>
      <a class="btn-reg" href="<?= h($base) ?>/logout.php">ログアウト</a>
      <?php else: ?>
      <a class="btn-reg" href="<?= h($base) ?>/account.php?next=<?= rawurlencode($base . '/shop.php?id=' . $id) ?>">ログイン / 会員登録</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="wrap">

  <div class="main">

    <div class="crumb" id="sec-top"><span class="area"><?= h($shop['area']) ?></span> ／ <?= h($shop['category']) ?></div>
    <div class="title-row">
      <h1 class="shop"><?= h($shop['name']) ?></h1>
      <?php if ($shop['name_kana'] !== ''): ?><span class="shop-jp"><?= h($shop['name_kana']) ?></span><?php endif; ?>
      <?php if ($ostate): ?><span class="open-badge <?= h($ostate['cls']) ?>"><?= h($ostate['label']) ?></span><?php endif; ?>
      <?php if ($cu): ?>
      <form method="post" action="shop.php?id=<?= $id ?>" class="fav-form">
        <?= ucsrf_field() ?>
        <input type="hidden" name="fav_toggle" value="1">
        <button type="submit" class="fav-btn<?= $isFav ? ' on' : '' ?>" aria-pressed="<?= $isFav ? 'true' : 'false' ?>" title="<?= $isFav ? 'お気に入り解除' : 'お気に入りに追加' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="<?= $isFav ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
          <span><?= $isFav ? 'お気に入り' : 'お気に入り' ?></span>
        </button>
      </form>
      <?php else: ?>
      <a class="fav-btn" href="<?= h($base) ?>/account.php?next=<?= rawurlencode($base . '/shop.php?id=' . $id) ?>" title="ログインしてお気に入り">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
        <span>お気に入り</span>
      </a>
      <?php endif; ?>
    </div>
    <div class="rate">
      <span class="stars"><?= stars_html((float)$revAvg) ?></span>
      <span class="num"><?= $approvedCount > 0 ? h(number_format($revAvg, 1)) : '—' ?></span>
      <span class="cnt">口コミ <?= $approvedCount ?>件</span>
      <?php if ($hasCoupon): ?><span class="coupon-flag">クーポンあり</span><?php endif; ?>
    </div>
    <?php if ($shop['catchphrase'] !== ''): ?><p class="catch"><?= h($shop['catchphrase']) ?></p><?php endif; ?>

    <div class="slider" id="shopSlider">
      <div class="slides">
        <?php foreach ($images as $k => $img): $g = ($k % 4) + 1; ?>
        <?php if ($img['image_path'] !== ''): ?>
        <div class="slide g<?= $g ?>" data-full="<?= h($img['image_path']) ?>" style="background-image:url('<?= h($img['image_path']) ?>');background-size:cover;background-position:center;cursor:zoom-in"></div>
        <?php else: ?>
        <div class="slide g<?= $g ?>"><?= h($img['label']) ?></div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <button class="arrow prev" aria-label="前の写真">&#8249;</button>
      <button class="arrow next" aria-label="次の写真">&#8250;</button>
      <div class="count"><span class="cur">1</span> / <?= $slideCount ?></div>
      <div class="dots"></div>
    </div>

    <div id="lightbox" class="lightbox" aria-hidden="true">
      <button class="lb-close" aria-label="閉じる">&times;</button>
      <button class="lb-arrow lb-prev" aria-label="前の写真">&#8249;</button>
      <img class="lb-img" src="" alt="店舗写真">
      <button class="lb-arrow lb-next" aria-label="次の写真">&#8250;</button>
    </div>
    <script>
    (function(){
      var lb=document.getElementById('lightbox'); if(!lb) return;
      var imgEl=lb.querySelector('.lb-img');
      var slides=Array.prototype.slice.call(document.querySelectorAll('#shopSlider .slide[data-full]'));
      var urls=slides.map(function(s){return s.getAttribute('data-full');}).filter(Boolean);
      if(!urls.length){ return; }
      var idx=0;
      function show(i){ idx=(i+urls.length)%urls.length; imgEl.src=urls[idx]; }
      function open(i){ show(i); lb.classList.add('open'); lb.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
      function close(){ lb.classList.remove('open'); lb.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }
      slides.forEach(function(s,i){ s.addEventListener('click',function(){ open(i); }); });
      lb.querySelector('.lb-close').addEventListener('click',close);
      var prev=lb.querySelector('.lb-prev'), next=lb.querySelector('.lb-next');
      prev.addEventListener('click',function(e){ e.stopPropagation(); show(idx-1); });
      next.addEventListener('click',function(e){ e.stopPropagation(); show(idx+1); });
      if(urls.length<2){ prev.style.display='none'; next.style.display='none'; }
      lb.addEventListener('click',function(e){ if(e.target===lb) close(); });
      document.addEventListener('keydown',function(e){
        if(!lb.classList.contains('open')) return;
        if(e.key==='Escape') close();
        else if(e.key==='ArrowLeft') show(idx-1);
        else if(e.key==='ArrowRight') show(idx+1);
      });
    })();
    </script>

    <?php if ($adA): $a = $adA[0]; ?>
    <div class="slot-tag">広告枠 A ｜ プレミアムヘッダーバナー</div>
    <a class="banner-a" href="<?= h($a['link_url'] ?: '#') ?>" style="color:inherit;text-decoration:none">
      <span class="pr">PR</span>
      <span class="txt"><?= h($a['body']) ?></span>
      <span class="more">詳しく見る ＞</span>
    </a>
    <?php endif; ?>

    <?php if ($tags): ?>
    <div class="card">
      <h2>お店の特徴・属性</h2>
      <p class="sub">お店選びで重視される「ノリ・客層・特徴」をタグで表示しています。</p>
      <div class="stags"><?php foreach ($tags as $t): ?><span class="stag"><?= h($t) ?></span><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <div class="tabs">
      <a class="tab on" href="#sec-top" style="text-decoration:none">トップ</a>
      <a class="tab" href="#sec-today" style="text-decoration:none">本日の出勤</a>
      <a class="tab" href="#sec-price" style="text-decoration:none">メニュー・料金</a>
      <a class="tab" href="#sec-staff" style="text-decoration:none">スタッフ紹介</a>
      <a class="tab" href="#sec-review" style="text-decoration:none">口コミ (<?= $approvedCount ?>)</a>
      <a class="tab" href="#sec-map" style="text-decoration:none">クーポン・地図</a>
    </div>

    <?php if ($shop['intro'] !== '' && $shop['intro'] !== null): ?>
    <div class="card">
      <h2>お店からのご紹介</h2>
      <p class="intro"><?= nl2br(h($shop['intro'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="card" id="sec-today">
      <h2>本日の出勤スタッフ<span style="font-size:12px;color:var(--muted);font-weight:400;margin-left:8px"><?= h($todayDate) ?></span></h2>
      <?php if ($todayStaff): ?>
      <div class="today-list">
        <?php foreach ($todayStaff as $t): ?>
        <div class="today-row">
          <span class="tname"><?= h($t['name']) ?></span>
          <?php if ($t['note'] !== ''): ?><span class="tnote"><?= h($t['note']) ?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p style="color:var(--muted)">本日の出勤情報はまだ登録されていません。</p>
      <?php endif; ?>
    </div>

    <?php if ($prices): ?>
    <div class="card" id="sec-price">
      <h2>料金システム（明朗会計）</h2>
      <table class="price">
        <thead><tr><th>メニュー・システム</th><th>料金（税込）</th><th>備考</th></tr></thead>
        <tbody>
          <?php foreach ($prices as $p): ?>
          <tr><th><?= h($p['name']) ?></th><td class="yen"><?= h($p['price']) ?></td><td><?= h($p['note']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($adB): ?>
    <div class="slot-tag">広告枠 B ｜ 記事内インフィード広告</div>
    <div class="infeed">
      <div style="font-size:13px;font-weight:700;color:var(--ink)">あなたにおすすめの周辺情報 <span style="color:var(--muted);font-size:10px">PR</span></div>
      <div class="infeed-items">
        <?php foreach ($adB as $b): ?>
        <a class="feed" href="<?= h($b['link_url'] ?: '#') ?>" style="color:inherit;text-decoration:none">
          <div class="thumb"<?= $b['image_path'] !== '' ? ' style="background-image:url(\''.h($b['image_path']).'\');background-size:cover"' : '' ?>></div>
          <div class="ftxt"><small>PR ・ <?= h($b['title']) ?></small><?= h($b['body']) ?></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($staff): ?>
    <div class="card" id="sec-staff" style="margin-top:22px">
      <h2>スタッフ紹介</h2>
      <div class="staff">
        <?php foreach ($staff as $m): ?>
        <div class="scard"><div class="av"<?php if (($m['image_path'] ?? '') !== ''): ?> style="background-image:url('<?= h($m['image_path']) ?>');background-size:cover;background-position:center"<?php endif; ?>></div><div class="nm"><?= h($m['name']) ?><span><?= h($m['role']) ?></span></div><p class="msg"><?= h($m['message']) ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card" id="sec-review">
      <h2>口コミ (<?= $approvedCount ?>)</h2>
      <?php if (isset($_GET['posted'])): ?>
      <div style="background:#2f6f4f;color:#dff5e9;padding:10px 14px;border-radius:3px;margin-bottom:14px;font-size:14px">口コミを投稿しました。確認後に掲載されます。</div>
      <?php endif; ?>

      <?php if ($reviews): ?>
      <div class="reviews">
        <?php foreach ($reviews as $rv): ?>
        <div class="review">
          <div class="rh"><span class="nm"><?= h($rv['nickname']) ?></span><span class="st"><?= stars_html((float)$rv['rating']) ?></span><span class="dt"><?= h(substr((string)$rv['created_at'], 0, 10)) ?></span></div>
          <div class="bd"><?= nl2br(h($rv['body'])) ?></div>
          <?php if (!empty($rv['image_path'])): ?><div class="rv-photo"><a href="<?= h($rv['image_path']) ?>" target="_blank" rel="noopener"><img src="<?= h($rv['image_path']) ?>" alt="口コミ写真" loading="lazy"></a></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p style="color:var(--muted)">まだ口コミがありません。最初の口コミを投稿してみませんか。</p>
      <?php endif; ?>

      <form class="rv-form" method="post" enctype="multipart/form-data" action="<?= h($base) ?>/shop.php?id=<?= $id ?>#sec-review">
        <input type="hidden" name="review_submit" value="1">
        <input type="text" class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="rv-row">
          <div><label>ニックネーム（任意）</label><input type="text" name="nickname" placeholder="匿名" maxlength="60"></div>
          <div><label>評価</label>
            <div class="star-pick" id="starPick">
              <input type="hidden" name="rating" id="ratingInput" value="5">
              <span data-v="1">★</span><span data-v="2">★</span><span data-v="3">★</span><span data-v="4">★</span><span data-v="5">★</span>
            </div>
          </div>
        </div>
        <label>口コミ本文</label>
        <textarea name="body" maxlength="2000" required placeholder="お店の感想を書いてください"></textarea>
        <label style="margin-top:10px">写真（任意）</label>
        <input type="file" name="review_image" id="reviewImage" accept="image/*">
        <button class="rv-btn" type="submit">口コミを投稿する</button>
        <p style="font-size:12px;color:var(--muted);margin-top:8px">投稿いただいた口コミは、確認後に掲載されます。写真は自動的に縮小されます。</p>
      </form>
    </div>

    <div class="card" id="sec-map">
      <h2>クーポン・地図</h2>
      <?php if ($coupons): ?>
      <div class="coupons">
        <?php $cu = current_user(); foreach ($coupons as $cp): $used = $couponUses[(int)$cp['id']] ?? null; ?>
        <div class="coupon-card">
          <div class="cc-title"><?= h($cp['title']) ?></div>
          <?php if (!$cu): ?>
            <p class="cc-desc cc-lock">内容を見る・利用するには会員登録（無料）が必要です。</p>
            <div class="cc-actions">
              <a class="cc-btn" href="<?= h($base) ?>/login.php?next=<?= rawurlencode($base . '/shop.php?id=' . $id) ?>">ログイン</a>
              <a class="cc-btn ghost" href="<?= h($base) ?>/register.php?next=<?= rawurlencode($base . '/shop.php?id=' . $id) ?>">新規登録</a>
            </div>
          <?php elseif ($used): ?>
            <?php if ($cp['description'] !== ''): ?><p class="cc-desc"><?= h($cp['description']) ?></p><?php endif; ?>
            <div class="cc-used">利用済み ／ 提示コード <b><?= h($used['code']) ?></b>（<?= h(substr((string)$used['used_at'], 0, 16)) ?>）<br><span class="cc-note">来店時にこのコードをスタッフにお見せください。</span></div>
          <?php else: ?>
            <?php if ($cp['description'] !== ''): ?><p class="cc-desc"><?= h($cp['description']) ?></p><?php endif; ?>
            <form method="post" action="<?= h($base) ?>/shop.php?id=<?= $id ?>#sec-map">
              <?= ucsrf_field() ?>
              <input type="hidden" name="use_coupon" value="1">
              <input type="hidden" name="coupon_id" value="<?= (int)$cp['id'] ?>">
              <button class="cc-btn" type="submit">このクーポンを利用する</button>
            </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($shop['address'] !== ''): ?>
      <div style="border-radius:4px;overflow:hidden;border:1px solid var(--line);margin-top:<?= $coupons ? '16px' : '0' ?>">
        <iframe title="地図" width="100%" height="260" style="border:0;display:block" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=<?= rawurlencode($shop['address']) ?>&amp;output=embed"></iframe>
      </div>
      <p style="font-size:12px;color:var(--muted);margin-top:8px"><?= h($shop['address']) ?></p>
      <?php else: ?>
      <p style="color:var(--muted)">住所が未登録です。</p>
      <?php endif; ?>
    </div>

  </div>

  <aside class="side">

    <div class="box">
      <h3>営業・店舗情報</h3>
      <div class="info">
        <?php if ($shop['hours'] !== ''):   ?><div class="row"><span class="k">営業時間</span><span class="v"><?= h($shop['hours']) ?></span></div><?php endif; ?>
        <?php if ($shop['closed'] !== ''):  ?><div class="row"><span class="k">定休日</span><span class="v"><?= h($shop['closed']) ?></span></div><?php endif; ?>
        <?php if ($shop['seats'] !== ''):   ?><div class="row"><span class="k">席数</span><span class="v"><?= h($shop['seats']) ?></span></div><?php endif; ?>
        <?php if ($shop['address'] !== ''): ?><div class="row"><span class="k">住所</span><span class="v"><?= h($shop['address']) ?></span></div><?php endif; ?>
        <?php if ($shop['phone'] !== ''):   ?><div class="row"><span class="k">電話</span><span class="v"><?= h($shop['phone']) ?></span></div><?php endif; ?>
        <?php if ($shop['payment'] !== ''): ?><div class="row"><span class="k">決済</span><span class="v"><?= h($shop['payment']) ?></span></div><?php endif; ?>
      </div>
      <?php if ($hasCoupon): ?><a class="cta" href="#sec-map">クーポンを見る</a><?php endif; ?>
    </div>

    <?php if ($adC): $c = $adC[0]; ?>
    <div class="slot-tag">広告枠 C ｜ 地域密着ローカルバナー</div>
    <div class="banner-c"><?= h($c['body']) ?></div>
    <?php endif; ?>

  </aside>

</div>

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
  var root=document.getElementById('shopSlider');
  if(!root)return;
  var track=root.querySelector('.slides');
  var slides=root.querySelectorAll('.slide');
  var dotsWrap=root.querySelector('.dots');
  var curLabel=root.querySelector('.cur');
  var n=slides.length,idx=0,timer=null,DELAY=4000;
  for(var i=0;i<n;i++){
    var b=document.createElement('button');
    b.className='dot'+(i===0?' on':'');
    b.setAttribute('aria-label',(i+1)+'枚目へ');
    (function(k){b.addEventListener('click',function(){go(k);restart();});})(i);
    dotsWrap.appendChild(b);
  }
  var dots=dotsWrap.querySelectorAll('.dot');
  function render(){
    track.style.transform='translateX(-'+(idx*100)+'%)';
    for(var i=0;i<n;i++)dots[i].classList.toggle('on',i===idx);
    if(curLabel)curLabel.textContent=idx+1;
  }
  function go(i){idx=(i+n)%n;render();}
  function next(){go(idx+1);}
  function prev(){go(idx-1);}
  function start(){timer=setInterval(next,DELAY);}
  function stop(){clearInterval(timer);}
  function restart(){stop();start();}
  root.querySelector('.next').addEventListener('click',function(){next();restart();});
  root.querySelector('.prev').addEventListener('click',function(){prev();restart();});
  root.addEventListener('mouseenter',stop);
  root.addEventListener('mouseleave',start);
  var sx=0,dx=0,sw=false;
  root.addEventListener('touchstart',function(e){sx=e.touches[0].clientX;dx=0;sw=true;stop();},{passive:true});
  root.addEventListener('touchmove',function(e){if(sw)dx=e.touches[0].clientX-sx;},{passive:true});
  root.addEventListener('touchend',function(){if(sw){if(dx<-40)next();else if(dx>40)prev();sw=false;start();}});
  render();start();
})();
</script>
<script>
(function(){
  var tabs = document.querySelectorAll('.tabs a.tab');
  tabs.forEach(function(t){
    t.addEventListener('click', function(){
      tabs.forEach(function(x){ x.classList.remove('on'); });
      t.classList.add('on');
    });
  });
})();
</script>
<script>
(function(){
  var b=document.querySelector('.menu-btn'), n=document.querySelector('.bar .nav');
  if(!b||!n) return;
  b.addEventListener('click',function(e){ e.stopPropagation(); var o=n.classList.toggle('open'); b.setAttribute('aria-expanded', o?'true':'false'); });
  document.addEventListener('click',function(e){ if(n.classList.contains('open') && !n.contains(e.target) && !b.contains(e.target)){ n.classList.remove('open'); b.setAttribute('aria-expanded','false'); } });
})();
</script>
<script>
(function(){
  var f=document.querySelector('.rv-form'); if(!f) return;
  var pick=document.getElementById('starPick'), inp=document.getElementById('ratingInput');
  if(pick&&inp){
    var spans=[].slice.call(pick.querySelectorAll('span'));
    function paint(v){spans.forEach(function(s){s.classList.toggle('on', parseInt(s.getAttribute('data-v'),10)<=v);});}
    spans.forEach(function(s){
      s.addEventListener('click',function(){inp.value=s.getAttribute('data-v'); paint(parseInt(inp.value,10));});
      s.addEventListener('mouseenter',function(){paint(parseInt(s.getAttribute('data-v'),10));});
    });
    pick.addEventListener('mouseleave',function(){paint(parseInt(inp.value,10));});
    paint(parseInt(inp.value,10));
  }
  var fileInput=document.getElementById('reviewImage'), done=false;
  f.addEventListener('submit',function(ev){
    if(done||!fileInput||!fileInput.files||!fileInput.files[0])return;
    var file=fileInput.files[0]; if(!/^image\//.test(file.type))return;
    if(typeof DataTransfer==='undefined')return;
    ev.preventDefault();
    var img=new Image(), url=URL.createObjectURL(file);
    img.onload=function(){
      URL.revokeObjectURL(url);
      var max=1280,w=img.width,h=img.height;
      if(w>max||h>max){ if(w>h){h=Math.round(h*max/w);w=max;}else{w=Math.round(w*max/h);h=max;} }
      var c=document.createElement('canvas');c.width=w;c.height=h;
      c.getContext('2d').drawImage(img,0,0,w,h);
      c.toBlob(function(blob){
        try{var dt=new DataTransfer();dt.items.add(new File([blob],'review.jpg',{type:'image/jpeg'}));fileInput.files=dt.files;}catch(e){}
        done=true; f.submit();
      },'image/jpeg',0.85);
    };
    img.onerror=function(){done=true; f.submit();};
    img.src=url;
  });
})();
</script>
</body>
</html>
