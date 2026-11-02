<?php
require __DIR__ . '/lib.php';
require_login();

function norm_dt(?string $v): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    $v = str_replace('T', ' ', $v);
    if (strlen($v) === 16) $v .= ':00';
    return $v;
}
function dt_input(?string $v): string {
    $v = trim((string)$v);
    if ($v === '') return '';
    return h(substr(str_replace(' ', 'T', $v), 0, 16));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $act = (string)($_POST['act'] ?? 'save');
    $aid = (int)($_POST['id'] ?? 0);

    if ($act === 'save') {
        $f = [
            'slot_key'     => in_array($_POST['slot_key'] ?? 'A', ['A','B','C'], true) ? $_POST['slot_key'] : 'A',
            'page'         => in_array($_POST['page'] ?? 'both', ['list','detail','both'], true) ? $_POST['page'] : 'both',
            'type'         => in_array($_POST['type'] ?? 'banner', ['banner','adsense','pr_native'], true) ? $_POST['type'] : 'banner',
            'title'        => trim((string)($_POST['title'] ?? '')),
            'body'         => trim((string)($_POST['body'] ?? '')),
            'link_url'     => trim((string)($_POST['link_url'] ?? '')),
            'image_path'   => trim((string)($_POST['image_path'] ?? '')),
            'adsense_code' => (string)($_POST['adsense_code'] ?? ''),
            'sort'         => (int)($_POST['sort'] ?? 0),
            'active'       => isset($_POST['active']) ? 1 : 0,
            'starts_at'    => norm_dt($_POST['starts_at'] ?? ''),
            'ends_at'      => norm_dt($_POST['ends_at'] ?? ''),
        ];
        if ($aid > 0) {
            $set = implode(', ', array_map(fn($k) => "$k=:$k", array_keys($f)));
            $pdo->prepare("UPDATE ad_slots SET $set WHERE id=:id")->execute($f + ['id' => $aid]);
        } else {
            $cols = implode(',', array_keys($f));
            $ph   = ':' . implode(',:', array_keys($f));
            $pdo->prepare("INSERT INTO ad_slots ($cols) VALUES ($ph)")->execute($f);
        }
    } elseif ($act === 'delete' && $aid > 0) {
        $pdo->prepare("DELETE FROM ad_slots WHERE id=?")->execute([$aid]);
    } elseif ($act === 'toggle' && $aid > 0) {
        $pdo->prepare("UPDATE ad_slots SET active = 1 - active WHERE id=?")->execute([$aid]);
    }
    header('Location: ' . $ADMIN_BASE . '/ad_edit.php');
    exit;
}

$editId = $_GET['id'] ?? '';
$edit = null;
if ($editId !== '' && $editId !== 'new') {
    $st = $pdo->prepare("SELECT * FROM ad_slots WHERE id=?"); $st->execute([(int)$editId]);
    $edit = $st->fetch();
}
$isNew = ($editId === 'new');

admin_header('広告枠の管理');
?>
<div style="margin-bottom:14px"><a href="index.php" class="muted">← ダッシュボードに戻る</a></div>

<?php if ($edit || $isNew): $e = $edit ?: ['slot_key'=>'A','page'=>'both','type'=>'banner','title'=>'','body'=>'','link_url'=>'','image_path'=>'','adsense_code'=>'','sort'=>0,'active'=>1,'starts_at'=>'','ends_at'=>'']; ?>
<div class="card">
  <h2><?= $edit ? '広告枠を編集' : '広告枠を追加' ?></h2>
  <form method="post" action="ad_edit.php">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="save">
    <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
    <div class="row2">
      <div><label>枠（A=バナー / B=インフィード / C=サイド）</label>
        <select name="slot_key">
          <?php foreach (['A','B','C'] as $k): ?><option value="<?= $k ?>" <?= $e['slot_key']===$k?'selected':'' ?>><?= $k ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>表示ページ</label>
        <select name="page">
          <?php foreach (['both'=>'両方','list'=>'一覧','detail'=>'詳細'] as $v=>$lbl): ?><option value="<?= $v ?>" <?= $e['page']===$v?'selected':'' ?>><?= $lbl ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <label>種類</label>
    <select name="type">
      <?php foreach (['banner'=>'バナー','adsense'=>'AdSense','pr_native'=>'ネイティブ(PR)'] as $v=>$lbl): ?><option value="<?= $v ?>" <?= $e['type']===$v?'selected':'' ?>><?= $lbl ?></option><?php endforeach; ?>
    </select>
    <div class="row2">
      <div><label>タイトル（B枠の小見出し等）</label><input type="text" name="title" value="<?= h($e['title']) ?>"></div>
      <div><label>リンクURL</label><input type="text" name="link_url" value="<?= h($e['link_url']) ?>" placeholder="#"></div>
    </div>
    <label>本文</label><input type="text" name="body" value="<?= h($e['body']) ?>">
    <label>画像URL（任意）</label><input type="text" name="image_path" value="<?= h($e['image_path']) ?>">
    <label>AdSenseコード（type=AdSense のとき。HTMLそのまま）</label><textarea name="adsense_code"><?= h($e['adsense_code']) ?></textarea>
    <div class="row2">
      <div><label>並び順</label><input type="number" name="sort" value="<?= (int)$e['sort'] ?>"></div>
      <div><label>有効</label><br><label style="margin-top:8px"><input type="checkbox" name="active" value="1" <?= (int)$e['active']===1?'checked':'' ?> style="width:auto;margin-right:6px"> 表示する</label></div>
    </div>
    <div class="row2">
      <div><label>掲載開始（任意）</label><input type="datetime-local" name="starts_at" value="<?= dt_input($e['starts_at'] ?? '') ?>"></div>
      <div><label>掲載終了（任意）</label><input type="datetime-local" name="ends_at" value="<?= dt_input($e['ends_at'] ?? '') ?>"></div>
    </div>
    <div style="display:flex;gap:12px;margin-top:6px"><button class="btn" type="submit">保存する</button><a class="btn sec" href="ad_edit.php">キャンセル</a></div>
  </form>
</div>
<?php endif; ?>

<div style="margin-bottom:14px"><a class="btn" href="ad_edit.php?id=new">＋ 広告枠を追加</a></div>

<div class="card">
  <h2>広告枠一覧</h2>
  <table>
    <thead><tr><th>枠</th><th>ページ</th><th>種類</th><th>本文/タイトル</th><th>状態</th><th>操作</th></tr></thead>
    <tbody>
      <?php $rows = $pdo->query("SELECT * FROM ad_slots ORDER BY slot_key, page, sort, id")->fetchAll(); ?>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><b><?= h($r['slot_key']) ?></b></td>
        <td class="muted"><?= h($r['page']) ?></td>
        <td class="muted"><?= h($r['type']) ?></td>
        <td><?= h($r['title'] !== '' ? $r['title'] . ' / ' : '') ?><?= h(mbcut($r['body'], 40)) ?></td>
        <td><?php if ((int)$r['active']===1): ?><span class="pill pub">有効</span><?php else: ?><span class="pill draft">停止</span><?php endif; ?></td>
        <td style="white-space:nowrap">
          <a class="btn sec sm" href="ad_edit.php?id=<?= (int)$r['id'] ?>">編集</a>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="act" value="toggle"><button class="btn sec sm" type="submit"><?= (int)$r['active']===1?'停止':'有効化' ?></button></form>
          <form method="post" class="inline" onsubmit="return confirm('削除しますか？');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="act" value="delete"><button class="btn danger sm" type="submit">削除</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">広告枠がありません。</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php admin_footer(); ?>
