<?php
require __DIR__ . '/lib.php';
require_login();

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

// ---------------- POST 処理 ----------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $act = (string)($_POST['act'] ?? 'save');

    if ($act === 'save') {
        $fields = [
            'name'         => trim((string)($_POST['name'] ?? '')),
            'name_kana'    => trim((string)($_POST['name_kana'] ?? '')),
            'area'         => trim((string)($_POST['area'] ?? '')),
            'category'     => trim((string)($_POST['category'] ?? '')),
            'catchphrase'  => trim((string)($_POST['catchphrase'] ?? '')),
            'intro'        => trim((string)($_POST['intro'] ?? '')),
            'rating'       => (float)($_POST['rating'] ?? 0),
            'review_count' => (int)($_POST['review_count'] ?? 0),
            'has_coupon'   => isset($_POST['has_coupon']) ? 1 : 0,
            'coupon_text'  => trim((string)($_POST['coupon_text'] ?? '')),
            'hours'        => trim((string)($_POST['hours'] ?? '')),
            'closed'       => trim((string)($_POST['closed'] ?? '')),
            'seats'        => trim((string)($_POST['seats'] ?? '')),
            'address'      => trim((string)($_POST['address'] ?? '')),
            'lat'          => (($_POST['lat'] ?? '') === '') ? null : (float)$_POST['lat'],
            'lng'          => (($_POST['lng'] ?? '') === '') ? null : (float)$_POST['lng'],
            'phone'        => trim((string)($_POST['phone'] ?? '')),
            'payment'      => trim((string)($_POST['payment'] ?? '')),
            'plan_tier'    => in_array($_POST['plan_tier'] ?? 'free', ['free','pr','premium'], true) ? $_POST['plan_tier'] : 'free',
            'sort_priority'=> (int)($_POST['sort_priority'] ?? 0),
            'status'       => ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
        ];

        if ($id > 0) {
            $set = implode(', ', array_map(fn($k) => "$k=:$k", array_keys($fields)));
            $st = $pdo->prepare("UPDATE shops SET $set WHERE id=:id");
            $st->execute($fields + ['id' => $id]);
        } else {
            $cols = implode(',', array_keys($fields));
            $ph   = ':' . implode(',:', array_keys($fields));
            $st = $pdo->prepare("INSERT INTO shops ($cols) VALUES ($ph)");
            $st->execute($fields);
            $id = (int)$pdo->lastInsertId();
        }

        // タグ（1行1件）
        $pdo->prepare("DELETE FROM shop_tags WHERE shop_id=?")->execute([$id]);
        $ins = $pdo->prepare("INSERT INTO shop_tags (shop_id,tag,sort) VALUES (?,?,?)");
        $n = 0;
        foreach (preg_split('/\r\n|\r|\n/', (string)($_POST['tags'] ?? '')) as $line) {
            $line = trim($line);
            if ($line !== '') { $ins->execute([$id, $line, $n++]); }
        }

        // 料金（name | price | note）
        $pdo->prepare("DELETE FROM shop_prices WHERE shop_id=?")->execute([$id]);
        $ins = $pdo->prepare("INSERT INTO shop_prices (shop_id,name,price,note,sort) VALUES (?,?,?,?,?)");
        $n = 0;
        foreach (preg_split('/\r\n|\r|\n/', (string)($_POST['prices'] ?? '')) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $p = array_map('trim', explode('|', $line));
            $ins->execute([$id, $p[0] ?? '', $p[1] ?? '', $p[2] ?? '', $n++]);
        }

        header('Location: ' . $ADMIN_BASE . '/shop_edit.php?id=' . $id . '&msg=saved');
        exit;
    }

    if ($act === 'upload' && $id > 0) {
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $map  = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_GIF=>'gif', IMAGETYPE_WEBP=>'webp'];
            $info = @getimagesize($_FILES['image']['tmp_name']);
            if ($info && isset($map[$info[2]]) && $_FILES['image']['size'] <= 8 * 1024 * 1024) {
                $ext = $map[$info[2]];
                if (!is_dir($UPLOAD_DIR)) { @mkdir($UPLOAD_DIR, 0755, true); }
                $fname = 'shop' . $id . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $UPLOAD_DIR . '/' . $fname)) {
                    $max = (int)$pdo->query("SELECT COALESCE(MAX(sort),-1) FROM shop_images WHERE shop_id=" . $id)->fetchColumn();
                    $st = $pdo->prepare("INSERT INTO shop_images (shop_id,image_path,label,sort) VALUES (?,?,?,?)");
                    $st->execute([$id, $UPLOAD_URL . '/' . $fname, trim((string)($_POST['label'] ?? '')), $max + 1]);
                }
            }
        }
        header('Location: ' . $ADMIN_BASE . '/shop_edit.php?id=' . $id . '&msg=uploaded');
        exit;
    }

    if ($act === 'add_staff' && $id > 0) {
        $img = save_uploaded_image('s_image', 'staff' . $id . '_', $UPLOAD_DIR, $UPLOAD_URL);
        $max = (int)$pdo->query("SELECT COALESCE(MAX(sort),-1) FROM shop_staff WHERE shop_id=" . $id)->fetchColumn();
        $pdo->prepare("INSERT INTO shop_staff (shop_id,name,role,message,image_path,sort) VALUES (?,?,?,?,?,?)")
            ->execute([$id, trim((string)($_POST['s_name'] ?? '')), trim((string)($_POST['s_role'] ?? '')), trim((string)($_POST['s_msg'] ?? '')), $img, $max + 1]);
        header('Location: ' . $ADMIN_BASE . '/shop_edit.php?id=' . $id . '&msg=staff');
        exit;
    }
    if ($act === 'upd_staff' && $id > 0) {
        $sid = (int)($_POST['staff_id'] ?? 0);
        $img = save_uploaded_image('s_image', 'staff' . $id . '_', $UPLOAD_DIR, $UPLOAD_URL);
        if ($img !== '') {
            $pdo->prepare("UPDATE shop_staff SET name=?,role=?,message=?,image_path=? WHERE id=? AND shop_id=?")
                ->execute([trim((string)($_POST['s_name'] ?? '')), trim((string)($_POST['s_role'] ?? '')), trim((string)($_POST['s_msg'] ?? '')), $img, $sid, $id]);
        } else {
            $pdo->prepare("UPDATE shop_staff SET name=?,role=?,message=? WHERE id=? AND shop_id=?")
                ->execute([trim((string)($_POST['s_name'] ?? '')), trim((string)($_POST['s_role'] ?? '')), trim((string)($_POST['s_msg'] ?? '')), $sid, $id]);
        }
        header('Location: ' . $ADMIN_BASE . '/shop_edit.php?id=' . $id . '&msg=staff');
        exit;
    }
    if ($act === 'del_staff' && $id > 0) {
        $sid = (int)($_POST['staff_id'] ?? 0);
        $st = $pdo->prepare("SELECT image_path FROM shop_staff WHERE id=? AND shop_id=?"); $st->execute([$sid, $id]);
        $path = (string)$st->fetchColumn();
        $pdo->prepare("DELETE FROM shop_staff WHERE id=? AND shop_id=?")->execute([$sid, $id]);
        if ($path !== '' && strpos($path, $UPLOAD_URL . '/') === 0) {
            $fs = $UPLOAD_DIR . '/' . basename($path); if (is_file($fs)) { @unlink($fs); }
        }
        header('Location: ' . $ADMIN_BASE . '/shop_edit.php?id=' . $id . '&msg=staff');
        exit;
    }

    if ($act === 'delimg' && $id > 0) {
        $imgId = (int)($_POST['img_id'] ?? 0);
        $st = $pdo->prepare("SELECT image_path FROM shop_images WHERE id=? AND shop_id=?");
        $st->execute([$imgId, $id]);
        $path = (string)$st->fetchColumn();
        $pdo->prepare("DELETE FROM shop_images WHERE id=? AND shop_id=?")->execute([$imgId, $id]);
        // アップロード配下のファイルなら実体も削除
        if ($path !== '' && strpos($path, $UPLOAD_URL . '/') === 0) {
            $fs = $UPLOAD_DIR . '/' . basename($path);
            if (is_file($fs)) { @unlink($fs); }
        }
        header('Location: ' . $ADMIN_BASE . '/shop_edit.php?id=' . $id . '&msg=deleted');
        exit;
    }

    if ($act === 'add_coupon' && $id > 0) {
        $ct = trim((string)($_POST['c_title'] ?? ''));
        $cd = trim((string)($_POST['c_desc'] ?? ''));
        if ($ct !== '') {
            $pdo->prepare("INSERT INTO coupons (shop_id,title,description,active) VALUES (?,?,?,1)")->execute([$id, $ct, $cd]);
        }
        header('Location: ' . $ADMIN_BASE . '/shop_edit.php?id=' . $id . '&msg=coupon');
        exit;
    }
    if ($act === 'toggle_coupon' && $id > 0) {
        $cid = (int)($_POST['coupon_id'] ?? 0);
        $pdo->prepare("UPDATE coupons SET active = 1 - active WHERE id=? AND shop_id=?")->execute([$cid, $id]);
        header('Location: ' . $ADMIN_BASE . '/shop_edit.php?id=' . $id . '&msg=coupon');
        exit;
    }
    if ($act === 'del_coupon' && $id > 0) {
        $cid = (int)($_POST['coupon_id'] ?? 0);
        $pdo->prepare("DELETE FROM coupons WHERE id=? AND shop_id=?")->execute([$cid, $id]);
        header('Location: ' . $ADMIN_BASE . '/shop_edit.php?id=' . $id . '&msg=coupon');
        exit;
    }

    if ($act === 'add_att' && $id > 0) {
        $wd = (string)($_POST['work_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $wd)) { $wd = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d'); }
        $nm = trim((string)($_POST['a_name'] ?? ''));
        if ($nm !== '') {
            $pdo->prepare("INSERT INTO attendance (shop_id,name,note,work_date) VALUES (?,?,?,?)")
                ->execute([$id, $nm, trim((string)($_POST['a_note'] ?? '')), $wd]);
        }
        header('Location: ' . $ADMIN_BASE . '/shop_edit.php?id=' . $id . '&adate=' . $wd . '&msg=att');
        exit;
    }
    if ($act === 'del_att' && $id > 0) {
        $aid = (int)($_POST['att_id'] ?? 0);
        $wd  = (string)($_POST['work_date'] ?? '');
        $pdo->prepare("DELETE FROM attendance WHERE id=? AND shop_id=?")->execute([$aid, $id]);
        $suffix = preg_match('/^\d{4}-\d{2}-\d{2}$/', $wd) ? '&adate=' . $wd : '';
        header('Location: ' . $ADMIN_BASE . '/shop_edit.php?id=' . $id . $suffix . '&msg=att');
        exit;
    }
    if ($act === 'gen_staffkey' && $id > 0) {
        $pdo->prepare("UPDATE shops SET staff_key=? WHERE id=?")->execute([substr(bin2hex(random_bytes(8)), 0, 12), $id]);
        header('Location: ' . $ADMIN_BASE . '/shop_edit.php?id=' . $id . '&msg=key');
        exit;
    }
}

// ---------------- データ取得（編集時） ----------------
$shop = [
    'name'=>'','name_kana'=>'','area'=>'','category'=>'','catchphrase'=>'','intro'=>'',
    'rating'=>'0.0','review_count'=>0,'has_coupon'=>0,'coupon_text'=>'',
    'hours'=>'','closed'=>'','seats'=>'','address'=>'','lat'=>'','lng'=>'','phone'=>'','payment'=>'',
    'plan_tier'=>'free','sort_priority'=>0,'status'=>'published','staff_key'=>'',
];
$tagsTxt = $pricesTxt = '';
$staff = [];
$images = [];
if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM shops WHERE id=?"); $st->execute([$id]);
    $row = $st->fetch();
    if ($row) { $shop = $row; }

    $st = $pdo->prepare("SELECT tag FROM shop_tags WHERE shop_id=? ORDER BY sort,id"); $st->execute([$id]);
    $tagsTxt = implode("\n", $st->fetchAll(PDO::FETCH_COLUMN));

    $st = $pdo->prepare("SELECT * FROM shop_prices WHERE shop_id=? ORDER BY sort,id"); $st->execute([$id]);
    $pricesTxt = implode("\n", array_map(fn($p) => $p['name'] . ' | ' . $p['price'] . ' | ' . $p['note'], $st->fetchAll()));

    $st = $pdo->prepare("SELECT * FROM shop_staff WHERE shop_id=? ORDER BY sort,id"); $st->execute([$id]);
    $staff = $st->fetchAll();

    $st = $pdo->prepare("SELECT * FROM shop_images WHERE shop_id=? ORDER BY sort,id"); $st->execute([$id]);
    $images = $st->fetchAll();

    $st = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM coupon_uses u WHERE u.coupon_id=c.id) AS uses
                         FROM coupons c WHERE c.shop_id=? ORDER BY c.id DESC");
    $st->execute([$id]);
    $coupons = $st->fetchAll();
}
if (!isset($coupons)) { $coupons = []; }

$adate = (string)($_GET['adate'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $adate)) { $adate = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d'); }
$att = [];
if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM attendance WHERE shop_id=? AND work_date=? ORDER BY id");
    $st->execute([$id, $adate]);
    $att = $st->fetchAll();
}
$PUB_BASE = rtrim(dirname($ADMIN_BASE), '/');
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$staffUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $PUB_BASE . '/staff.php';

$msgMap = ['saved'=>'保存しました。','uploaded'=>'画像をアップロードしました。','deleted'=>'画像を削除しました。','coupon'=>'クーポンを更新しました。','att'=>'出勤情報を更新しました。','key'=>'管理キーを再発行しました。','staff'=>'スタッフを更新しました。'];
$msg = $msgMap[$_GET['msg'] ?? ''] ?? '';

admin_header($id > 0 ? '店舗編集' : '店舗の追加');
?>
<div style="margin-bottom:14px"><a href="index.php" class="muted">← ダッシュボードに戻る</a></div>
<?php if ($msg): ?><div class="msg"><?= h($msg) ?></div><?php endif; ?>

<form method="post" action="shop_edit.php">
  <?= csrf_field() ?>
  <input type="hidden" name="act" value="save">
  <input type="hidden" name="id" value="<?= $id ?>">

  <div class="card">
    <h2>基本情報</h2>
    <div class="row2">
      <div><label>店名</label><input type="text" name="name" value="<?= h($shop['name']) ?>" required></div>
      <div><label>フリガナ</label><input type="text" name="name_kana" value="<?= h($shop['name_kana']) ?>"></div>
    </div>
    <div class="row2">
      <div><label>エリア</label><input type="text" name="area" value="<?= h($shop['area']) ?>"></div>
      <div><label>ジャンル</label><input type="text" name="category" value="<?= h($shop['category']) ?>"></div>
    </div>
    <label>キャッチコピー</label><input type="text" name="catchphrase" value="<?= h($shop['catchphrase']) ?>">
    <label>紹介文</label><textarea name="intro"><?= h($shop['intro']) ?></textarea>
    <div class="row2">
      <div><label>評価（0.0〜5.0）</label><input type="number" step="0.1" min="0" max="5" name="rating" value="<?= h($shop['rating']) ?>"></div>
      <div><label>口コミ件数</label><input type="number" min="0" name="review_count" value="<?= (int)$shop['review_count'] ?>"></div>
    </div>
  </div>

  <div class="card">
    <h2>営業・店舗情報</h2>
    <div class="row2">
      <div><label>営業時間</label><input type="text" name="hours" value="<?= h($shop['hours']) ?>"></div>
      <div><label>定休日</label><input type="text" name="closed" value="<?= h($shop['closed']) ?>"></div>
    </div>
    <label>席数</label><input type="text" name="seats" value="<?= h($shop['seats']) ?>">
    <label>住所</label><input type="text" name="address" value="<?= h($shop['address']) ?>">
    <div class="row2">
      <div><label>緯度 lat（地図用・任意）</label><input type="text" name="lat" value="<?= h((string)($shop['lat'] ?? '')) ?>" placeholder="34.7055"></div>
      <div><label>経度 lng（地図用・任意）</label><input type="text" name="lng" value="<?= h((string)($shop['lng'] ?? '')) ?>" placeholder="135.5070"></div>
    </div>
    <p class="muted" style="font-size:12px;margin-top:4px">座標は<a href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode((string)$shop['address']) ?>" target="_blank" rel="noopener" style="color:var(--accent)">Googleマップで住所を検索</a>→ピンを右クリック「この場所について」で確認できます（緯度,経度の順）。</p>
    <div class="row2">
      <div><label>電話</label><input type="text" name="phone" value="<?= h($shop['phone']) ?>"></div>
      <div><label>決済</label><input type="text" name="payment" value="<?= h($shop['payment']) ?>"></div>
    </div>
  </div>

  <div class="card">
    <h2>クーポン・掲載設定</h2>
    <label><input type="checkbox" name="has_coupon" value="1" <?= (int)$shop['has_coupon'] === 1 ? 'checked' : '' ?> style="width:auto;margin-right:6px"> クーポンあり</label>
    <label>クーポン内容</label><input type="text" name="coupon_text" value="<?= h($shop['coupon_text']) ?>">
    <div class="row2">
      <div><label>プラン（PR枠）</label>
        <select name="plan_tier">
          <option value="free"    <?= $shop['plan_tier']==='free'?'selected':'' ?>>free（通常）</option>
          <option value="pr"      <?= $shop['plan_tier']==='pr'?'selected':'' ?>>pr（注目店に固定）</option>
          <option value="premium" <?= $shop['plan_tier']==='premium'?'selected':'' ?>>premium</option>
        </select>
      </div>
      <div><label>表示優先度（大きいほど上位）</label><input type="number" name="sort_priority" value="<?= (int)$shop['sort_priority'] ?>"></div>
    </div>
    <label>公開状態</label>
    <select name="status">
      <option value="published" <?= $shop['status']==='published'?'selected':'' ?>>公開</option>
      <option value="draft"      <?= $shop['status']==='draft'?'selected':'' ?>>下書き</option>
    </select>
  </div>

  <div class="card">
    <h2>属性タグ <span class="muted">（1行に1つ）</span></h2>
    <textarea name="tags" placeholder="ミックス（どなたでもOK）&#10;カラオケあり"><?= h($tagsTxt) ?></textarea>
  </div>

  <div class="card">
    <h2>料金システム <span class="muted">（1行＝ メニュー名 | 料金 | 備考）</span></h2>
    <textarea name="prices" placeholder="初回限定プラン | ¥3,500 | 90分 飲み放題"><?= h($pricesTxt) ?></textarea>
  </div>

  <div style="display:flex;gap:12px"><button class="btn" type="submit">保存する</button><a class="btn sec" href="index.php">キャンセル</a></div>
</form>

<?php if ($id > 0): ?>
<div class="card" style="margin-top:22px">
  <h2>スタッフ</h2>
  <p class="muted" style="margin-bottom:12px">画像・名前・肩書・一言を1人ずつ登録できます。詳細ページのスタッフ紹介に表示されます。</p>
  <?php foreach ($staff as $m): ?>
  <div style="border:1px solid var(--line);border-radius:4px;padding:14px;margin-bottom:12px">
    <form method="post" action="shop_edit.php" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="act" value="upd_staff">
      <input type="hidden" name="staff_id" value="<?= (int)$m['id'] ?>">
      <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
        <div style="flex:0 0 auto;text-align:center">
          <div style="width:72px;height:72px;border-radius:6px;border:1px solid var(--line);background:var(--soft) center/cover no-repeat<?= $m['image_path'] !== '' ? ";background-image:url('" . h($m['image_path']) . "')" : '' ?>"></div>
          <label style="font-size:11px;color:var(--muted);margin:6px 0 0">画像変更</label>
          <input type="file" name="s_image" accept="image/*" style="font-size:11px;width:130px">
        </div>
        <div style="flex:1;min-width:180px">
          <div class="row2">
            <div><label>名前</label><input type="text" name="s_name" value="<?= h($m['name']) ?>"></div>
            <div><label>肩書</label><input type="text" name="s_role" value="<?= h($m['role']) ?>"></div>
          </div>
          <label>一言</label><input type="text" name="s_msg" value="<?= h($m['message']) ?>">
          <div style="margin-top:10px"><button class="btn sm" type="submit">更新</button></div>
        </div>
      </div>
    </form>
    <form method="post" action="shop_edit.php" style="margin-top:8px" onsubmit="return confirm('このスタッフを削除しますか？');">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="act" value="del_staff">
      <input type="hidden" name="staff_id" value="<?= (int)$m['id'] ?>">
      <button class="btn danger sm" type="submit">削除</button>
    </form>
  </div>
  <?php endforeach; ?>
  <?php if (!$staff): ?><p class="muted">スタッフはまだ登録されていません。</p><?php endif; ?>

  <h3 style="font-size:14px;margin:16px 0 8px">スタッフを追加</h3>
  <form method="post" action="shop_edit.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="act" value="add_staff">
    <div class="row2">
      <div><label>名前</label><input type="text" name="s_name" placeholder="タカシ"></div>
      <div><label>肩書</label><input type="text" name="s_role" placeholder="ママ"></div>
    </div>
    <label>一言</label><input type="text" name="s_msg" placeholder="お酒とトークで盛り上がりましょう">
    <label>画像（任意）</label><input type="file" name="s_image" accept="image/*">
    <div style="margin-top:12px"><button class="btn" type="submit">スタッフを追加</button></div>
  </form>
</div>

<div class="card" style="margin-top:22px">
  <h2>写真（カルーセル）</h2>
  <form method="post" action="shop_edit.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="upload">
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="row2">
      <div><label>画像ファイル（jpg/png/webp/gif・8MBまで）</label><input type="file" name="image" accept="image/*" required></div>
      <div><label>ラベル（任意）</label><input type="text" name="label" placeholder="メイン写真 など"></div>
    </div>
    <div style="margin-top:12px"><button class="btn sec" type="submit">アップロード</button></div>
  </form>

  <?php if ($images): ?>
  <div class="thumbs">
    <?php foreach ($images as $img): ?>
    <div class="t">
      <div class="im" <?= $img['image_path'] !== '' ? 'style="background-image:url(\'' . h($img['image_path']) . '\')"' : '' ?>><?= $img['image_path'] === '' ? h($img['label'] ?: '画像') : '' ?></div>
      <form method="post" action="shop_edit.php" onsubmit="return confirm('この画像を削除しますか？');">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="delimg">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="img_id" value="<?= (int)$img['id'] ?>">
        <button class="btn danger sm" type="submit">削除</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="muted" style="margin-top:10px">写真がありません。アップロードすると詳細ページのカルーセルに表示されます（未登録時はプレースホルダ表示）。</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>クーポン（会員限定）</h2>
  <p class="muted" style="margin-bottom:12px">登録したクーポンは、ログイン済みの会員のみ詳細ページで利用できます。利用数は会員が「利用する」を押した回数です。</p>
  <table>
    <thead><tr><th>タイトル</th><th>内容</th><th>状態</th><th>利用数</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($coupons as $c): ?>
      <tr>
        <td><?= h($c['title']) ?></td>
        <td class="muted"><?= h(mbcut((string)$c['description'], 40)) ?></td>
        <td><?php if ((int)$c['active']===1): ?><span class="pill pub">有効</span><?php else: ?><span class="pill draft">停止</span><?php endif; ?></td>
        <td><?= (int)$c['uses'] ?></td>
        <td style="white-space:nowrap">
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="coupon_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="act" value="toggle_coupon"><button class="btn sec sm" type="submit"><?= (int)$c['active']===1?'停止':'有効化' ?></button></form>
          <form method="post" class="inline" onsubmit="return confirm('このクーポンを削除しますか？利用履歴も削除されます。');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="coupon_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="act" value="del_coupon"><button class="btn danger sm" type="submit">削除</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$coupons): ?><tr><td colspan="5" class="muted">クーポンはまだありません。</td></tr><?php endif; ?>
    </tbody>
  </table>

  <form method="post" action="shop_edit.php" style="margin-top:16px">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="act" value="add_coupon">
    <div class="row2">
      <div><label>新しいクーポンのタイトル</label><input type="text" name="c_title" placeholder="初回限定ドリンク1杯無料 など"></div>
      <div><label>内容（任意）</label><input type="text" name="c_desc" placeholder="来店時にコードを提示"></div>
    </div>
    <div style="margin-top:12px"><button class="btn" type="submit">クーポンを追加</button></div>
  </form>
</div>

<div class="card">
  <h2>本日の出勤スタッフ</h2>
  <p class="muted" style="margin-bottom:10px">日付ごとに登録できます。店舗詳細ページには当日（日本時間）の分が表示されます。</p>
  <form method="get" action="shop_edit.php" class="row2" style="align-items:flex-end;max-width:360px">
    <input type="hidden" name="id" value="<?= $id ?>">
    <div><label>表示する日付</label><input type="date" name="adate" value="<?= h($adate) ?>"></div>
    <div style="flex:0 0 auto"><button class="btn sec sm" type="submit">表示</button></div>
  </form>
  <table style="margin-top:14px">
    <thead><tr><th>名前</th><th>備考</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($att as $a): ?>
      <tr>
        <td><?= h($a['name']) ?></td>
        <td class="muted"><?= h($a['note']) ?></td>
        <td>
          <form method="post" class="inline" onsubmit="return confirm('削除しますか？');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="del_att"><input type="hidden" name="att_id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="work_date" value="<?= h($adate) ?>"><button class="btn danger sm" type="submit">削除</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$att): ?><tr><td colspan="3" class="muted"><?= h($adate) ?> の出勤はまだありません。</td></tr><?php endif; ?>
    </tbody>
  </table>
  <form method="post" action="shop_edit.php" style="margin-top:16px">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="act" value="add_att">
    <input type="hidden" name="work_date" value="<?= h($adate) ?>">
    <div class="row2">
      <div><label>名前</label><input type="text" name="a_name" placeholder="スタッフ名"></div>
      <div><label>備考（任意）</label><input type="text" name="a_note" placeholder="カウンター担当 など"></div>
    </div>
    <div style="margin-top:12px"><button class="btn" type="submit"><?= h($adate) ?> に追加</button></div>
  </form>

  <h2 style="margin-top:22px">店舗専用ログイン（この店舗だけ管理）</h2>
  <p class="muted" style="margin-bottom:8px">下記キーを店舗に渡すと、店舗側が専用ページから自店の出勤のみ更新できます。</p>
  <p style="font-size:14px">ログインURL: <span class="muted"><?= h($staffUrl) ?></span></p>
  <p style="font-size:14px;margin-top:4px">管理キー: <strong style="letter-spacing:1px;font-size:16px"><?= $shop['staff_key'] !== '' ? h($shop['staff_key']) : '（未発行）' ?></strong></p>
  <form method="post" action="shop_edit.php" style="margin-top:10px" onsubmit="return confirm('キーを再発行しますか？以前のキーは使えなくなります。');">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="act" value="gen_staffkey">
    <button class="btn sec sm" type="submit"><?= $shop['staff_key'] !== '' ? 'キーを再発行' : 'キーを発行' ?></button>
  </form>
</div>
<?php else: ?>
<p class="muted" style="margin-top:14px">写真・クーポン・出勤の登録は、いったん保存して店舗を作成したあとに可能になります。</p>
<?php endif; ?>

<script>
(function(){
  var MAX = 1600, Q = 0.85;
  function resize(file){
    return new Promise(function(res){
      if(!file || file.type.indexOf('image/')!==0) return res(null);
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function(){
        URL.revokeObjectURL(url);
        var w = img.naturalWidth, h = img.naturalHeight;
        if(w<=MAX && h<=MAX && file.size < 1.5*1024*1024) return res(null);
        var s = Math.min(1, MAX/Math.max(w,h));
        var cw = Math.round(w*s), ch = Math.round(h*s);
        var c = document.createElement('canvas'); c.width=cw; c.height=ch;
        c.getContext('2d').drawImage(img,0,0,cw,ch);
        c.toBlob(function(b){
          if(!b) return res(null);
          var name = (file.name.replace(/\.[^.]+$/,'')||'image') + '.jpg';
          res(new File([b], name, {type:'image/jpeg'}));
        }, 'image/jpeg', Q);
      };
      img.onerror = function(){ URL.revokeObjectURL(url); res(null); };
      img.src = url;
    });
  }
  Array.prototype.forEach.call(document.querySelectorAll('form'), function(form){
    var input = form.querySelector('input[type=file]');
    if(!input) return;
    var done = false;
    form.addEventListener('submit', function(e){
      if(done) return;
      if(!input.files || !input.files.length) return;
      e.preventDefault();
      resize(input.files[0]).then(function(nf){
        if(nf && window.DataTransfer){ var dt=new DataTransfer(); dt.items.add(nf); input.files=dt.files; }
        done = true; form.submit();
      });
    });
  });
})();
</script>

<?php admin_footer(); ?>
