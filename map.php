<?php
require __DIR__ . '/auth.php';

$shops = $pdo->query(
    "SELECT id, name, area, category, lat, lng
     FROM shops
     WHERE status='published' AND lat IS NOT NULL AND lng IS NOT NULL
     ORDER BY name"
)->fetchAll();

$pts = [];
foreach ($shops as $s) {
    $pts[] = [
        'id'   => (int)$s['id'],
        'name' => $s['name'],
        'area' => $s['area'],
        'cat'  => $s['category'],
        'lat'  => (float)$s['lat'],
        'lng'  => (float)$s['lng'],
    ];
}

member_header('店舗マップ');
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
<div class="card">
  <h1>店舗マップ</h1>
  <?php if ($pts): ?>
  <p class="muted" style="margin-bottom:12px">ピンをタップすると店舗ページへ移動できます。</p>
  <div id="map" style="height:460px;border-radius:4px;overflow:hidden;border:1px solid var(--line)"></div>
  <div class="list" style="margin-top:16px">
    <?php foreach ($pts as $p): ?>
    <div class="row">
      <a href="<?= h($base ?? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')) ?>/shop.php?id=<?= (int)$p['id'] ?>" style="font-weight:700;color:var(--accent);text-decoration:none"><?= h($p['name']) ?></a>
      <span class="muted" style="margin-left:8px"><?= h($p['area']) ?> ／ <?= h($p['cat']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="muted">地図に表示できる店舗がまだありません。管理画面で店舗の緯度・経度を登録すると、ここに表示されます。</p>
  <?php endif; ?>
</div>
<?php if ($pts): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script>
(function(){
  var pts = <?= json_encode($pts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var base = "<?= h(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')) ?>";
  if (typeof L === 'undefined' || !pts.length) return;
  var map = L.map('map');
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  var bounds = [];
  pts.forEach(function(p){
    var m = L.marker([p.lat, p.lng]).addTo(map);
    var html = '<a href="' + base + '/shop.php?id=' + p.id + '" style="font-weight:700">' +
               (p.name || '') + '</a><br><span style="color:#888">' +
               (p.area || '') + ' / ' + (p.cat || '') + '</span>';
    m.bindPopup(html);
    bounds.push([p.lat, p.lng]);
  });
  if (bounds.length === 1) { map.setView(bounds[0], 17); }
  else { map.fitBounds(bounds, { padding: [30, 30] }); }
})();
</script>
<?php endif; ?>
<?php member_footer(); ?>
