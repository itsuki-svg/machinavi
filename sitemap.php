<?php
require __DIR__ . '/db.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? parse_url(SITE_URL, PHP_URL_HOST));

header('Content-Type: application/xml; charset=UTF-8');

$urls = [];
$urls[] = [$origin . $base . '/index.php', '1.0', 'daily', null];
foreach (['guide.php', 'terms.php', 'privacy.php'] as $p) {
    $urls[] = [$origin . $base . '/' . $p, '0.3', 'monthly', null];
}
try {
    $st = $pdo->query("SELECT id, updated_at FROM shops WHERE status='published' ORDER BY id");
    foreach ($st as $r) {
        $urls[] = [$origin . $base . '/shop.php?id=' . (int)$r['id'], '0.8', 'weekly', $r['updated_at'] ?? null];
    }
} catch (Throwable $e) { /* shopsが無い場合もindexだけは出す */ }

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . htmlspecialchars($u[0], ENT_QUOTES, 'UTF-8') . '</loc>';
    if (!empty($u[3])) { echo '<lastmod>' . date('Y-m-d', strtotime((string)$u[3])) . '</lastmod>'; }
    echo '<changefreq>' . $u[1+1] . '</changefreq><priority>' . $u[1] . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
