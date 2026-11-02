<?php
require __DIR__ . '/lib.php';
require_login();

// クイック操作
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $act = (string)($_POST['act'] ?? '');
    $sid = (int)($_POST['id'] ?? 0);
    if ($sid > 0) {
        if ($act === 'toggle_status') {
            $pdo->prepare("UPDATE shops SET status = CASE status WHEN 'published' THEN 'draft' ELSE 'published' END WHERE id=?")->execute([$sid]);
        } elseif ($act === 'toggle_tier') {
            // free <-> pr のトグル（premium はそのまま）
            $pdo->prepare("UPDATE shops SET plan_tier = CASE plan_tier WHEN 'pr' THEN 'free' WHEN 'free' THEN 'pr' ELSE plan_tier END WHERE id=?")->execute([$sid]);
        } elseif ($act === 'delete_shop') {
            $pdo->prepare("DELETE FROM shops WHERE id=?")->execute([$sid]);
        }
    }
    header('Location: ' . $ADMIN_BASE . '/index.php');
    exit;
}

$shops = $pdo->query("SELECT * FROM shops ORDER BY sort_priority DESC, id")->fetchAll();
$adCount = (int)$pdo->query("SELECT COUNT(*) FROM ad_slots")->fetchColumn();

admin_header('ダッシュボード');
?>
<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
  <a class="btn" href="shop_edit.php">＋ 店舗を追加</a>
  <a class="btn sec" href="ad_edit.php">広告枠の管理（<?= $adCount ?>）</a>
  <span class="sp" style="flex:1"></span>
  <a class="muted" href="<?= h($APP_BASE) ?>/index.php" target="_blank">公開ページを開く</a>
</div>

<div class="card">
  <h2>加盟店一覧（<?= count($shops) ?>件）</h2>
  <table>
    <thead><tr><th>店名</th><th>ジャンル/エリア</th><th>評価</th><th>状態</th><th>PR</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($shops as $s): ?>
      <tr>
        <td><a href="shop_edit.php?id=<?= (int)$s['id'] ?>"><?= h($s['name']) ?></a></td>
        <td class="muted"><?= h($s['category']) ?> / <?= h($s['area']) ?></td>
        <td><?= h($s['rating']) ?></td>
        <td>
          <?php if ($s['status'] === 'published'): ?><span class="pill pub">公開</span><?php else: ?><span class="pill draft">下書き</span><?php endif; ?>
        </td>
        <td>
          <?php if ($s['plan_tier'] === 'pr'): ?><span class="pill pr">PR</span>
          <?php elseif ($s['plan_tier'] === 'premium'): ?><span class="pill pr">PREMIUM</span>
          <?php else: ?><span class="muted">-</span><?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="act" value="toggle_status"><button class="btn sec sm" type="submit"><?= $s['status'] === 'published' ? '下書きに' : '公開に' ?></button></form>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="act" value="toggle_tier"><button class="btn sec sm" type="submit">PR切替</button></form>
          <a class="btn sec sm" href="shop_edit.php?id=<?= (int)$s['id'] ?>">編集</a>
          <form method="post" class="inline" onsubmit="return confirm('「<?= h($s['name']) ?>」を削除します。よろしいですか？');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="act" value="delete_shop"><button class="btn danger sm" type="submit">削除</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$shops): ?><tr><td colspan="6" class="muted">まだ店舗がありません。「＋ 店舗を追加」から登録してください。</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php admin_footer(); ?>
