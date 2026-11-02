<?php
require __DIR__ . '/lib.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $act = (string)($_POST['act'] ?? '');
    $rid = (int)($_POST['id'] ?? 0);
    if ($rid > 0) {
        if ($act === 'approve') {
            $pdo->prepare("UPDATE reviews SET status='approved' WHERE id=?")->execute([$rid]);
        } elseif ($act === 'unapprove') {
            $pdo->prepare("UPDATE reviews SET status='pending' WHERE id=?")->execute([$rid]);
        } elseif ($act === 'delete') {
            $pdo->prepare("DELETE FROM reviews WHERE id=?")->execute([$rid]);
        }
    }
    header('Location: ' . $ADMIN_BASE . '/reviews.php');
    exit;
}

$rows = $pdo->query(
    "SELECT r.*, s.name AS shop_name
     FROM reviews r LEFT JOIN shops s ON s.id = r.shop_id
     ORDER BY (r.status='pending') DESC, r.created_at DESC, r.id DESC"
)->fetchAll();
$pending = 0;
foreach ($rows as $r) { if ($r['status'] === 'pending') $pending++; }

admin_header('口コミの管理');
?>
<div style="margin-bottom:14px"><a href="index.php" class="muted">← ダッシュボードに戻る</a></div>

<div class="card">
  <h2>口コミ（未承認 <?= $pending ?> 件 / 全 <?= count($rows) ?> 件）</h2>
  <table>
    <thead><tr><th>店舗</th><th>評価</th><th>ニックネーム</th><th>本文</th><th>状態</th><th>投稿日</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['shop_name'] ?? '(削除済)') ?></td>
        <td style="white-space:nowrap;color:#caa24a"><?= str_repeat('★', (int)$r['rating']) ?></td>
        <td><?= h($r['nickname']) ?></td>
        <td><?= h(mbcut((string)$r['body'], 60)) ?><?php if (!empty($r['image_path'])): ?><br><a href="<?= h($r['image_path']) ?>" target="_blank" rel="noopener"><img src="<?= h($r['image_path']) ?>" alt="写真" style="max-width:90px;max-height:90px;object-fit:cover;border:1px solid #3a3a42;border-radius:3px;margin-top:6px"></a><?php endif; ?></td>
        <td><?php if ($r['status']==='approved'): ?><span class="pill pub">公開</span><?php else: ?><span class="pill draft">未承認</span><?php endif; ?></td>
        <td class="muted" style="white-space:nowrap"><?= h(substr((string)$r['created_at'], 0, 16)) ?></td>
        <td style="white-space:nowrap">
          <?php if ($r['status']==='pending'): ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="act" value="approve"><button class="btn sm" type="submit">承認</button></form>
          <?php else: ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="act" value="unapprove"><button class="btn sec sm" type="submit">非公開に</button></form>
          <?php endif; ?>
          <form method="post" class="inline" onsubmit="return confirm('この口コミを削除しますか？');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="act" value="delete"><button class="btn danger sm" type="submit">削除</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" class="muted">口コミはまだありません。</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php admin_footer(); ?>
