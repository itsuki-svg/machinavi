<?php
require __DIR__ . '/lib.php';
require_login();

$TYPES = [
    'listing'  => '掲載相談',
    'ad'       => '広告',
    'withdraw' => '退会・削除',
    'other'    => 'その他',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $act = (string)($_POST['act'] ?? '');
    $iid = (int)($_POST['id'] ?? 0);
    if ($iid > 0) {
        if ($act === 'done') {
            $pdo->prepare("UPDATE inquiries SET status='done' WHERE id=?")->execute([$iid]);
        } elseif ($act === 'reopen') {
            $pdo->prepare("UPDATE inquiries SET status='open' WHERE id=?")->execute([$iid]);
        } elseif ($act === 'delete') {
            $pdo->prepare("DELETE FROM inquiries WHERE id=?")->execute([$iid]);
        }
    }
    header('Location: ' . $ADMIN_BASE . '/inquiries.php');
    exit;
}

$rows = $pdo->query(
    "SELECT * FROM inquiries ORDER BY (status='open') DESC, created_at DESC, id DESC"
)->fetchAll();
$open = 0;
foreach ($rows as $r) { if ($r['status'] === 'open') $open++; }

admin_header('お問い合わせ');
?>
<div style="margin-bottom:14px"><a href="index.php" class="muted">← ダッシュボードに戻る</a></div>

<div class="card">
  <h2>お問い合わせ（未対応 <?= $open ?> 件 / 全 <?= count($rows) ?> 件）</h2>
  <?php if (!$rows): ?>
  <p class="muted">お問い合わせはまだありません。</p>
  <?php else: ?>
  <table>
    <thead><tr><th>種別</th><th>名前</th><th>メール</th><th>内容</th><th>状態</th><th>受信日</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><span class="pill <?= $r['status'] === 'open' ? 'pr' : 'draft' ?>"><?= h($TYPES[$r['type']] ?? $r['type']) ?></span></td>
        <td><?= h($r['name'] !== '' ? $r['name'] : '—') ?></td>
        <td><?php if ($r['email'] !== ''): ?><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a><?php else: ?>—<?php endif; ?></td>
        <td style="max-width:360px;white-space:pre-wrap"><?= h($r['body']) ?></td>
        <td><span class="pill <?= $r['status'] === 'open' ? 'draft' : 'pub' ?>"><?= $r['status'] === 'open' ? '未対応' : '対応済' ?></span></td>
        <td class="muted"><?= h(substr((string)$r['created_at'], 0, 16)) ?></td>
        <td>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <?php if ($r['status'] === 'open'): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="act" value="done"><button class="btn sm" type="submit">対応済に</button></form>
            <?php else: ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="act" value="reopen"><button class="btn sm sec" type="submit">未対応に戻す</button></form>
            <?php endif; ?>
            <form method="post" class="inline" onsubmit="return confirm('削除しますか？');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="act" value="delete"><button class="btn sm danger" type="submit">削除</button></form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
