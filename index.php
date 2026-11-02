<?php
require __DIR__ . '/auth.php';

// このページが実際に置かれているフォルダ基準の絶対パス（例: /machinavi）
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');

// 検索・絞り込み
$q    = trim((string)($_GET['q'] ?? ''));
$cat  = trim((string)($_GET['cat'] ?? ''));
$area = trim((string)($_GET['area'] ?? ''));
$openOnly = (($_GET['open'] ?? '') === '1');
$filtering = ($q !== '' || $cat !== '' || $area !== '' || $openOnly);

$where  = ["status = 'published'"];
$params = [];
if ($q !== '') {
    $where[] = "(name LIKE ? OR name_kana LIKE ? OR category LIKE ? OR area LIKE ? OR catchphrase LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($cat !== '') {
    $where[]  = "category = ?";
    $params[] = $cat;
}
if ($area !== '') {
    $where[]  = "area = ?";
    $params[] = $area;
}
$wsql = implode(' AND ', $where);

// 注目店（PR枠）は絞り込みなしのときだけ表示
// 承認済み口コミから評価（平均）と件数を算出するSQL断片
$RV = "(SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.shop_id=shops.id AND r.status='approved') AS rv_avg, "
    . "(SELECT COUNT(*) FROM reviews r WHERE r.shop_id=shops.id AND r.status='approved') AS rv_count";

$featured = null; $fid = 0; $ftags = [];
if (!$filtering) {
    $featured = $pdo->query(
        "SELECT *, $RV FROM shops WHERE status='published' AND plan_tier IN ('pr','premium')
         ORDER BY sort_priority DESC, id LIMIT 1"
    )->fetch() ?: null;
    $fid = (int)($featured['id'] ?? 0);
    if ($fid) {
        $st = $pdo->prepare("SELECT tag FROM shop_tags WHERE shop_id=? ORDER BY sort, id LIMIT 4");
        $st->execute([$fid]);
        $ftags = $st->fetchAll(PDO::FETCH_COLUMN);
    }
}

// 一覧（絞り込み中は全件、通常時は注目店を除く）
$sql = "SELECT *, $RV FROM shops WHERE $wsql";
if (!$filtering && $fid) { $sql .= " AND id <> " . $fid; }
$sql .= " ORDER BY sort_priority DESC, id";
$st = $pdo->prepare($sql);
$st->execute($params);
$shops = $st->fetchAll();

// 今営業中フィルタ（営業時間・定休日の文字列から判定）
if ($openOnly) {
    $shops = array_values(array_filter($shops, function ($s) {
        $os = shop_open_state((string)$s['hours'], (string)$s['closed']);
        return $os && $os['cls'] === 'open';
    }));
}
// ページネーション
$perPage    = 20;
$totalCnt   = count($shops);
$totalPages = max(1, (int)ceil($totalCnt / $perPage));
$page       = max(1, (int)($_GET['p'] ?? 1));
if ($page > $totalPages) { $page = $totalPages; }
$shops = array_slice($shops, ($page - 1) * $perPage, $perPage);

// チップ用の一覧
$cats  = $pdo->query("SELECT DISTINCT category FROM shops WHERE status='published' AND category <> '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$areas = $pdo->query("SELECT DISTINCT area FROM shops WHERE status='published' AND area <> '' ORDER BY area")->fetchAll(PDO::FETCH_COLUMN);

// 現在の絞り込みを保ったままのURLを生成（変更したい項目だけ上書き）
$buildUrl = function(array $over) use ($base, $q, $cat, $area, $openOnly) {
    $p = ['q' => $q, 'cat' => $cat, 'area' => $area, 'open' => $openOnly ? '1' : ''];
    foreach ($over as $k => $v) { $p[$k] = $v; }
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    $qs = http_build_query($p);
    return $base . '/index.php' . ($qs !== '' ? '?' . $qs : '');
};
$pageUrl = function(int $pp) use ($buildUrl) { return $buildUrl(['p' => (string)$pp]); };

$adA = ads($pdo, 'list', 'A');
$adC = ads($pdo, 'list', 'C');

// SEO / OGP
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin    = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? parse_url(SITE_URL, PHP_URL_HOST));
$siteName  = 'まちナビ';
$pageTitle = $siteName;
if ($q !== '' || $cat !== '' || $area !== '') {
    $bits = array_filter([$area, $cat, $q], fn($v) => $v !== '');
    $pageTitle = implode(' ', $bits) . '｜' . $siteName;
}
$metaDesc  = 'さくら町を中心に、加盟店をエリア・ジャンル・属性から探せる店舗ガイド。気になるお店の詳細・口コミ・クーポンをチェックできます。';
$canonical = $origin . $base . '/index.php';

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
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= h($siteName) ?>">
<meta property="og:title" content="<?= h($pageTitle) ?>">
<meta property="og:description" content="<?= h($metaDesc) ?>">
<meta property="og:url" content="<?= h($canonical) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="まちナビ">
<meta property="og:image" content="<?= h($origin . $base) ?>/image/ogp.png">
<meta name="twitter:card" content="summary_large_image">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#1b1b20;--paper:#24242a;--soft:#2d2d34;--ink:#f0f0f3;--muted:#9a9aa3;
  --line:#3a3a42;--ph:#33333b;--ph2:#3c3c45;--ph3:#45454f;--ph4:#4f4f59;--accent:#eeeef1;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Zen Kaku Gothic New",sans-serif;background:radial-gradient(900px 360px at 50% -20%,rgba(255,255,255,.035),transparent),var(--bg);color:var(--ink);line-height:1.7;-webkit-font-smoothing:antialiased}
.mincho{font-weight:900;letter-spacing:.01em}

header{background:var(--paper);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:50}
.bar{max-width:1120px;margin:0 auto;padding:14px 24px;display:flex;align-items:center;justify-content:space-between}
.logo{display:flex;align-items:center;gap:11px}
.logo .mark{width:40px;height:40px;border-radius:5px;background:var(--bg);border:1px solid var(--line);display:grid;place-items:center}
.logo .name{font-weight:900;font-size:19px;letter-spacing:.02em}
.logo .name small{display:block;font-weight:400;font-size:11px;color:var(--muted);letter-spacing:.18em}
.nav{display:flex;gap:26px;font-size:14px;font-weight:500}
.nav a{color:var(--muted);text-decoration:none}
.nav a:hover{color:var(--ink)}
.nav .btn-acct{background:var(--accent);color:var(--bg);font-weight:700;font-size:13px;padding:8px 15px;border-radius:3px}
.nav .btn-acct:hover{color:var(--bg)}
.menu-btn{display:none;background:transparent;border:1px solid var(--line);border-radius:5px;width:42px;height:38px;cursor:pointer;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:0;margin-left:auto}
.menu-btn span{display:block;width:18px;height:2px;background:var(--ink);border-radius:2px}
@media(max-width:720px){
  .menu-btn{display:flex}
  .bar{position:relative}
  .nav{display:none;position:absolute;top:100%;left:0;right:0;background:var(--paper);border-top:1px solid var(--line);border-bottom:1px solid var(--line);flex-direction:column;gap:0;padding:6px 0;z-index:60}
  .nav.open{display:flex}
  .nav a{padding:12px 22px}
  .nav .btn-acct{align-self:flex-start;margin:8px 22px;padding:8px 15px}
}

.hero{background:var(--soft);border-bottom:1px solid var(--line)}
.hero-in{max-width:1120px;margin:0 auto;padding:42px 24px 34px}
.hero h1{font-weight:900;font-size:30px;line-height:1.4;letter-spacing:.01em}
.hero h1 .em{color:var(--ink);text-decoration:underline;text-decoration-color:var(--accent);text-decoration-thickness:3px;text-underline-offset:5px}
.hero p{color:var(--muted);font-size:14px;margin-top:8px}
.search{margin-top:22px;display:flex;gap:10px;max-width:620px}
.search input{flex:1;min-width:0;border:1.5px solid var(--line);background:var(--paper);border-radius:3px;padding:13px 16px;font-size:15px;font-family:inherit;color:var(--ink)}
.search input::placeholder{color:#85858e}
.search button{border:none;background:var(--accent);color:var(--bg);font-family:inherit;font-weight:700;padding:0 26px;border-radius:3px;font-size:15px;cursor:pointer}
.chips{margin-top:18px;display:flex;flex-wrap:wrap;gap:9px}
.chip{background:var(--paper);border:1.5px solid var(--line);color:var(--muted);padding:7px 15px;border-radius:3px;font-size:13px;font-weight:500;cursor:pointer}
.chip.on{background:var(--accent);color:var(--bg);border-color:var(--accent)}
.open-badge{display:inline-block;font-size:11px;font-weight:700;padding:2px 9px;border-radius:3px;border:1px solid var(--line);margin:2px 0}
.open-badge.open{background:#1e3a2a;color:#9fe0bb;border-color:#2f6f4f}
.open-badge.off{background:var(--soft);color:var(--muted)}
.open-badge.closed{background:#3a1e1e;color:#e6a3a3;border-color:#7a4444}
.pager{display:flex;justify-content:center;gap:6px;flex-wrap:wrap;margin:26px 0 6px}
.pager a,.pager .cur{min-width:38px;text-align:center;padding:8px 10px;border:1px solid var(--line);border-radius:3px;font-size:13px;text-decoration:none;color:var(--ink)}
.pager a:hover{border-color:var(--accent)}
.pager .cur{background:var(--accent);color:var(--bg);border-color:var(--accent);font-weight:700}

main{max-width:1120px;margin:0 auto;padding:34px 24px 60px}
.section-head{display:flex;align-items:baseline;gap:12px;margin:0 0 18px}
.section-head h2{font-weight:900;font-size:21px}
.section-head .line{flex:1;height:1px;background:var(--line)}

.slot-tag{font-size:11px;font-weight:700;letter-spacing:.12em;color:#85858e;display:inline-flex;align-items:center;gap:6px;margin-bottom:8px}
.slot-tag::before{content:"";width:8px;height:8px;border-radius:2px;background:var(--accent)}

.banner{border:2px dashed var(--line);border-radius:4px;background:var(--soft);height:118px;display:grid;place-items:center;color:var(--muted);font-size:14px;font-weight:600;text-align:center;margin-bottom:36px}

.pr{display:grid;grid-template-columns:300px 1fr;gap:0;background:var(--paper);border:1px solid var(--line);border-radius:4px;overflow:hidden;box-shadow:0 6px 22px rgba(0,0,0,.4);margin-bottom:40px;position:relative}
.pr .photo{background:var(--ph2);min-height:230px;position:relative}
.pr .photo .ph{position:absolute;inset:0;display:grid;place-items:center;color:#d4d4da;font-size:13px;letter-spacing:.1em}
.pr-badge{position:absolute;top:14px;left:14px;z-index:2;background:var(--accent);color:var(--bg);font-weight:900;font-size:12px;letter-spacing:.16em;padding:5px 13px;border-radius:2px}
.pr .body{padding:26px 30px}
.pr .cat{font-size:12px;color:var(--ink);font-weight:700;letter-spacing:.08em}
.pr .body h3{font-weight:900;font-size:25px;margin:5px 0 4px}
.pr .meta{font-size:13px;color:var(--muted)}
.pr .stars{color:var(--accent);font-size:16px;margin:10px 0 2px;letter-spacing:1px}
.pr .stars span{color:var(--muted);font-size:13px;margin-left:6px}
.pr .desc{font-size:14px;color:#d4d4da;margin-top:10px}
.pr .tags{margin-top:14px;display:flex;gap:7px;flex-wrap:wrap}
.tag{background:var(--soft);color:#d4d4da;font-size:11.5px;padding:4px 11px;border-radius:2px;font-weight:500;border:1px solid var(--line)}
@media(max-width:680px){.pr{grid-template-columns:1fr}.pr .photo{min-height:170px}}

.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
@media(max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.grid{grid-template-columns:1fr}}
.card{background:var(--paper);border:1px solid var(--line);border-radius:4px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.35);transition:transform .18s,box-shadow .18s;cursor:pointer}
.card:hover{transform:translateY(-4px);box-shadow:0 14px 30px rgba(0,0,0,.5)}
.card .photo{height:150px;position:relative}
.card .photo .ph{position:absolute;inset:0;display:grid;place-items:center;color:#d4d4da;font-size:12px;letter-spacing:.1em}
.c1{background:var(--ph)}
.c2{background:var(--ph2)}
.c3{background:var(--ph3)}
.c4{background:var(--ph4)}
.c5{background:var(--ph)}
.c6{background:var(--ph3)}
.card .body{padding:15px 17px 18px}
.card .cat{font-size:11px;color:var(--ink);font-weight:700;letter-spacing:.06em}
.card h3{font-weight:900;font-size:18px;margin:3px 0 5px}
.card .stars{color:var(--accent);font-size:14px;letter-spacing:1px}
.card .stars span{color:var(--muted);font-size:12px;margin-left:5px}
.card .meta{font-size:12.5px;color:var(--muted);margin-top:7px}
.card .stamp{margin-top:11px;display:inline-flex;align-items:center;gap:6px;font-size:11.5px;font-weight:700;color:var(--ink);background:var(--soft);border:1px solid var(--line);padding:4px 10px;border-radius:2px}

.adcell{grid-column:1/-1;border:2px dashed var(--line);border-radius:4px;background:var(--soft);min-height:110px;display:grid;place-items:center;color:var(--muted);font-size:13px;font-weight:600;text-align:center}

footer{background:#141418;border-top:1px solid var(--line);margin-top:10px}
.foot{max-width:1160px;margin:0 auto;padding:28px 24px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 22px;font-size:13px;color:#85858e}
.foot-brand{font-weight:900;color:var(--ink);font-size:16px}
.foot-links{display:flex;flex-wrap:wrap;gap:8px 18px}
.foot a{color:#85858e}.foot a:hover{color:var(--ink)}
.foot-cr{margin-left:auto;color:#6a6a73;font-size:12px}


.legend{max-width:1120px;margin:26px auto 0;padding:14px 24px;display:flex;gap:22px;flex-wrap:wrap;font-size:12px;color:var(--muted)}
.legend b{color:var(--ink)}
html,body{max-width:100%;overflow-x:hidden}
@media(max-width:600px){
  .bar{padding:12px 16px}
  .hero-in{padding:30px 18px 26px}
  .hero h1{font-size:23px;line-height:1.35}
  .search{max-width:none}
  main{padding:26px 16px 54px}
  .section-head h2{font-size:18px}
}
</style>
<?= ga_tag() ?>
</head>
<body>

<header>
  <div class="bar">
    <a class="logo" href="<?= h($base) ?>/index.php" style="text-decoration:none;color:inherit"><div class="mark"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#eeeef1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6.5 H20 L12 14 Z"/><path d="M12 14 V20"/><path d="M8 20 H16"/></svg></div><div class="name">まちナビ<small>SHOP GUIDE</small></div></a>
    <button class="menu-btn" aria-label="メニュー" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="nav">
      <a href="<?= h($base) ?>/index.php">探す</a>
      <a href="<?= h($base) ?>/guide.php">初回ガイド</a>
      <a href="<?= h($base) ?>/map.php">地図</a>
      <?php if ($cu = current_user()): ?>
      <a href="<?= h($base) ?>/mypage.php"><?= h($cu['nickname'] ?: $cu['email']) ?> さん</a>
      <a href="<?= h($base) ?>/logout.php">ログアウト</a>
      <?php else: ?>
      <a class="btn-acct" href="<?= h($base) ?>/account.php">ログイン / 会員登録</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<section class="hero">
  <div class="hero-in">
    <h1>さくら町の<span class="em">良い店</span>を、<br>気軽に見つけよう。</h1>
    <p>加盟店をエリア・ジャンル・属性から探せます。</p>
    <form class="search" method="get" action="<?= h($base) ?>/index.php">
      <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= h($cat) ?>"><?php endif; ?>
      <?php if ($area !== ''): ?><input type="hidden" name="area" value="<?= h($area) ?>"><?php endif; ?>
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="店名・ジャンル・エリアで検索">
      <button type="submit">検索</button>
    </form>
    <div class="chips">
      <span style="font-size:12px;color:var(--muted);align-self:center;margin-right:4px">ジャンル</span>
      <a class="chip <?= $cat === '' ? 'on' : '' ?>" href="<?= h($buildUrl(['cat'=>''])) ?>" style="text-decoration:none">すべて</a>
      <?php foreach ($cats as $c): ?>
      <a class="chip <?= $cat === $c ? 'on' : '' ?>" href="<?= h($buildUrl(['cat'=>$c])) ?>" style="text-decoration:none"><?= h($c) ?></a>
      <?php endforeach; ?>
    </div>
    <?php if ($areas): ?>
    <div class="chips" style="margin-top:10px">
      <span style="font-size:12px;color:var(--muted);align-self:center;margin-right:4px">エリア</span>
      <a class="chip <?= $area === '' ? 'on' : '' ?>" href="<?= h($buildUrl(['area'=>''])) ?>" style="text-decoration:none">すべて</a>
      <?php foreach ($areas as $a): ?>
      <a class="chip <?= $area === $a ? 'on' : '' ?>" href="<?= h($buildUrl(['area'=>$a])) ?>" style="text-decoration:none"><?= h($a) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="chips" style="margin-top:10px">
      <span style="font-size:12px;color:var(--muted);align-self:center;margin-right:4px">表示</span>
      <a class="chip <?= $openOnly ? 'on' : '' ?>" href="<?= h($buildUrl(['open'=> $openOnly ? '' : '1'])) ?>" style="text-decoration:none">今営業中のみ</a>
    </div>
  </div>
</section>

<main>

  <?php if ($adA): $a = $adA[0]; ?>
  <div class="slot-tag">広告枠 A ｜ スポンサーバナー</div>
  <div class="banner"><?= h($a['body']) ?></div>
  <?php endif; ?>

  <?php if ($featured): ?>
  <div class="section-head"><h2>注目の加盟店</h2><div class="line"></div></div>
  <div class="slot-tag">広告枠 B ｜ 加盟店PR枠</div>
  <a class="pr" href="<?= h($base) ?>/shop.php?id=<?= $fid ?>" style="color:inherit;text-decoration:none">
    <div class="photo"><span class="pr-badge">PR</span><div class="ph">店舗写真</div></div>
    <div class="body">
      <div class="cat"><?= h($featured['category']) ?> ／ <?= h($featured['area']) ?></div>
      <h3 class="mincho"><?= h($featured['name']) ?></h3>
      <div class="stars"><?= stars_html((float)($featured['rv_avg'] ?? 0)) ?> <span><?= (int)$featured['rv_count'] > 0 ? h(number_format((float)$featured['rv_avg'], 1)) . '（' . (int)$featured['rv_count'] . '件）' : '評価なし' ?></span></div>
      <p class="desc"><?= h($featured['catchphrase']) ?></p>
      <?php if ($ftags): ?>
      <div class="tags"><?php foreach ($ftags as $t): ?><span class="tag"><?= h($t) ?></span><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
  </a>
  <?php endif; ?>

  <?php if ($filtering): ?>
  <div class="section-head"><h2>検索結果（<?= (int)$totalCnt ?>件）</h2><div class="line"></div><a href="<?= h($base) ?>/index.php" style="font-size:13px;color:var(--muted);text-decoration:none">条件をクリア</a></div>
  <?php else: ?>
  <div class="section-head"><h2>加盟店一覧</h2><div class="line"></div></div>
  <?php endif; ?>
  <div class="grid">
    <?php $i = 0; foreach ($shops as $s): $i++; ?>
    <a class="card" href="<?= h($base) ?>/shop.php?id=<?= (int)$s['id'] ?>" style="color:inherit;text-decoration:none">
      <div class="photo c<?= (($i - 1) % 6) + 1 ?>"><div class="ph">店舗写真</div></div>
      <div class="body">
        <div class="cat"><?= h($s['category']) ?> ／ <?= h($s['area']) ?></div>
        <h3 class="mincho"><?= h($s['name']) ?></h3>
        <?php $cardOs = shop_open_state((string)$s['hours'], (string)$s['closed']); ?>
        <?php if ($cardOs): ?><span class="open-badge <?= h($cardOs['cls']) ?>"><?= h($cardOs['label']) ?></span><?php endif; ?>
        <div class="stars"><?= stars_html((float)($s['rv_avg'] ?? 0)) ?> <span><?= (int)$s['rv_count'] > 0 ? h(number_format((float)$s['rv_avg'], 1)) . '（' . (int)$s['rv_count'] . '）' : '評価なし' ?></span></div>
        <div class="meta"><?= h($s['hours']) ?></div>
        <?php if ((int)$s['has_coupon'] === 1): ?><span class="stamp">クーポンあり</span><?php endif; ?>
      </div>
    </a>
    <?php if ($i === 3 && $adC): ?>
    <div class="adcell">広告枠 C ｜ Google AdSense</div>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!$shops): ?><p style="grid-column:1/-1;color:var(--muted)">該当する店舗がありません。<a href="<?= h($base) ?>/index.php" style="color:var(--accent)">条件をクリア</a></p><?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <nav class="pager" aria-label="ページ送り">
    <?php if ($page > 1): ?><a href="<?= h($pageUrl($page - 1)) ?>">前へ</a><?php endif; ?>
    <?php for ($pp = 1; $pp <= $totalPages; $pp++): ?>
      <?php if ($pp === $page): ?><span class="cur"><?= $pp ?></span>
      <?php else: ?><a href="<?= h($pageUrl($pp)) ?>"><?= $pp ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="<?= h($pageUrl($page + 1)) ?>">次へ</a><?php endif; ?>
  </nav>
  <?php endif; ?>

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
