<?php
declare(strict_types=1);
require __DIR__ . '/db.php';   // $pdo, h()
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$BASE       = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$UPLOAD_DIR = __DIR__ . '/uploads/shops';
$UPLOAD_URL = $BASE . '/uploads/shops';
$jstToday   = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');

if (empty($_SESSION['scsrf'])) { $_SESSION['scsrf'] = bin2hex(random_bytes(16)); }
function scsrf(): string { return '<input type="hidden" name="scsrf" value="' . h($_SESSION['scsrf']) . '">'; }
function scsrf_check(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            http_response_code(413);
            exit('アップロードサイズが大きすぎます。画像を小さくして再度お試しください。');
        }
        if (!hash_equals($_SESSION['scsrf'] ?? '', (string)($_POST['scsrf'] ?? ''))) {
            http_response_code(400);
            exit('セッションが切れた可能性があります。再読み込みしてもう一度お試しください。');
        }
    }
}

// ログアウト
if (isset($_GET['logout'])) { unset($_SESSION['staff_shop']); header('Location: ' . $BASE . '/staff.php'); exit; }

// ログイン（店舗キー）
$loginErr = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['login'])) {
    scsrf_check();
    if (login_locked('staff')) {
        $loginErr = '試行回数が多すぎます。しばらくしてからお試しください。';
    } else {
        $key = trim((string)($_POST['key'] ?? ''));
        if ($key !== '') {
            $st = $pdo->prepare("SELECT id FROM shops WHERE staff_key = ? AND staff_key <> '' LIMIT 1");
            $st->execute([$key]);
            $sid = (int)$st->fetchColumn();
            if ($sid > 0) {
                login_ok('staff');
                session_regenerate_id(true);
                $_SESSION['staff_shop'] = $sid;
                header('Location: ' . $BASE . '/staff.php');
                exit;
            }
        }
        login_fail('staff');
        $loginErr = '管理キーが正しくありません。';
    }
}

$shopId = (int)($_SESSION['staff_shop'] ?? 0);
$shop = null;
if ($shopId > 0) {
    $st = $pdo->prepare("SELECT * FROM shops WHERE id=?"); $st->execute([$shopId]);
    $shop = $st->fetch();
    if (!$shop) { unset($_SESSION['staff_shop']); $shopId = 0; }
}

// 対象日（出勤）
$d = (string)($_GET['d'] ?? $jstToday);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { $d = $jstToday; }

// ---------------- POST 処理（ログイン店舗のみ） ----------------
if ($shopId > 0 && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !isset($_POST['login'])) {
    scsrf_check();
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'save') {
        $fields = [
            'name'        => trim((string)($_POST['name'] ?? '')),
            'name_kana'   => trim((string)($_POST['name_kana'] ?? '')),
            'area'        => trim((string)($_POST['area'] ?? '')),
            'category'    => trim((string)($_POST['category'] ?? '')),
            'catchphrase' => trim((string)($_POST['catchphrase'] ?? '')),
            'intro'       => trim((string)($_POST['intro'] ?? '')),
            'hours'       => trim((string)($_POST['hours'] ?? '')),
            'closed'      => trim((string)($_POST['closed'] ?? '')),
            'seats'       => trim((string)($_POST['seats'] ?? '')),
            'address'     => trim((string)($_POST['address'] ?? '')),
            'phone'       => trim((string)($_POST['phone'] ?? '')),
            'payment'     => trim((string)($_POST['payment'] ?? '')),
        ];
        $set = implode(', ', array_map(fn($k) => "$k=:$k", array_keys($fields)));
        $pdo->prepare("UPDATE shops SET $set WHERE id=:id")->execute($fields + ['id' => $shopId]);

        $pdo->prepare("DELETE FROM shop_tags WHERE shop_id=?")->execute([$shopId]);
        $ins = $pdo->prepare("INSERT INTO shop_tags (shop_id,tag,sort) VALUES (?,?,?)"); $n = 0;
        foreach (preg_split('/\r\n|\r|\n/', (string)($_POST['tags'] ?? '')) as $line) {
            $line = trim($line); if ($line !== '') { $ins->execute([$shopId, $line, $n++]); }
        }

        $pdo->prepare("DELETE FROM shop_prices WHERE shop_id=?")->execute([$shopId]);
        $ins = $pdo->prepare("INSERT INTO shop_prices (shop_id,name,price,note,sort) VALUES (?,?,?,?,?)"); $n = 0;
        foreach (preg_split('/\r\n|\r|\n/', (string)($_POST['prices'] ?? '')) as $line) {
            $line = trim($line); if ($line === '') continue;
            $p = array_map('trim', explode('|', $line));
            $ins->execute([$shopId, $p[0] ?? '', $p[1] ?? '', $p[2] ?? '', $n++]);
        }

        header('Location: ' . $BASE . '/staff.php?m=saved'); exit;
    }

    if ($act === 'add_staff') {
        $img = save_uploaded_image('s_image', 'staff' . $shopId . '_', $UPLOAD_DIR, $UPLOAD_URL);
        $max = (int)$pdo->query("SELECT COALESCE(MAX(sort),-1) FROM shop_staff WHERE shop_id=" . $shopId)->fetchColumn();
        $pdo->prepare("INSERT INTO shop_staff (shop_id,name,role,message,image_path,sort) VALUES (?,?,?,?,?,?)")
            ->execute([$shopId, trim((string)($_POST['s_name'] ?? '')), trim((string)($_POST['s_role'] ?? '')), trim((string)($_POST['s_msg'] ?? '')), $img, $max + 1]);
        header('Location: ' . $BASE . '/staff.php?m=staff'); exit;
    }
    if ($act === 'upd_staff') {
        $sid = (int)($_POST['staff_id'] ?? 0);
        $img = save_uploaded_image('s_image', 'staff' . $shopId . '_', $UPLOAD_DIR, $UPLOAD_URL);
        if ($img !== '') {
            $pdo->prepare("UPDATE shop_staff SET name=?,role=?,message=?,image_path=? WHERE id=? AND shop_id=?")
                ->execute([trim((string)($_POST['s_name'] ?? '')), trim((string)($_POST['s_role'] ?? '')), trim((string)($_POST['s_msg'] ?? '')), $img, $sid, $shopId]);
        } else {
            $pdo->prepare("UPDATE shop_staff SET name=?,role=?,message=? WHERE id=? AND shop_id=?")
                ->execute([trim((string)($_POST['s_name'] ?? '')), trim((string)($_POST['s_role'] ?? '')), trim((string)($_POST['s_msg'] ?? '')), $sid, $shopId]);
        }
        header('Location: ' . $BASE . '/staff.php?m=staff'); exit;
    }
    if ($act === 'del_staff') {
        $sid = (int)($_POST['staff_id'] ?? 0);
        $st = $pdo->prepare("SELECT image_path FROM shop_staff WHERE id=? AND shop_id=?"); $st->execute([$sid, $shopId]);
        $path = (string)$st->fetchColumn();
        $pdo->prepare("DELETE FROM shop_staff WHERE id=? AND shop_id=?")->execute([$sid, $shopId]);
        if ($path !== '' && strpos($path, $UPLOAD_URL . '/') === 0) {
            $fs = $UPLOAD_DIR . '/' . basename($path); if (is_file($fs)) { @unlink($fs); }
        }
        header('Location: ' . $BASE . '/staff.php?m=staff'); exit;
    }

    if ($act === 'upload') {
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $map  = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_GIF=>'gif', IMAGETYPE_WEBP=>'webp'];
            $info = @getimagesize($_FILES['image']['tmp_name']);
            if ($info && isset($map[$info[2]]) && $_FILES['image']['size'] <= 8 * 1024 * 1024) {
                $ext = $map[$info[2]];
                if (!is_dir($UPLOAD_DIR)) { @mkdir($UPLOAD_DIR, 0755, true); }
                $fname = 'shop' . $shopId . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $UPLOAD_DIR . '/' . $fname)) {
                    $max = (int)$pdo->query("SELECT COALESCE(MAX(sort),-1) FROM shop_images WHERE shop_id=" . $shopId)->fetchColumn();
                    $pdo->prepare("INSERT INTO shop_images (shop_id,image_path,label,sort) VALUES (?,?,?,?)")
                        ->execute([$shopId, $UPLOAD_URL . '/' . $fname, trim((string)($_POST['label'] ?? '')), $max + 1]);
                }
            }
        }
        header('Location: ' . $BASE . '/staff.php?m=uploaded'); exit;
    }

    if ($act === 'delimg') {
        $imgId = (int)($_POST['img_id'] ?? 0);
        $st = $pdo->prepare("SELECT image_path FROM shop_images WHERE id=? AND shop_id=?");
        $st->execute([$imgId, $shopId]);
        $path = (string)$st->fetchColumn();
        $pdo->prepare("DELETE FROM shop_images WHERE id=? AND shop_id=?")->execute([$imgId, $shopId]);
        if ($path !== '' && strpos($path, $UPLOAD_URL . '/') === 0) {
            $fs = $UPLOAD_DIR . '/' . basename($path);
            if (is_file($fs)) { @unlink($fs); }
        }
        header('Location: ' . $BASE . '/staff.php?m=deleted'); exit;
    }

    if (isset($_POST['att_action'])) {
        $wd = (string)($_POST['work_date'] ?? $jstToday);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $wd)) { $wd = $jstToday; }
        if ($_POST['att_action'] === 'add') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name !== '') {
                $pdo->prepare("INSERT INTO attendance (shop_id,name,note,work_date) VALUES (?,?,?,?)")
                    ->execute([$shopId, $name, trim((string)($_POST['note'] ?? '')), $wd]);
            }
        } elseif ($_POST['att_action'] === 'del') {
            $pdo->prepare("DELETE FROM attendance WHERE id=? AND shop_id=?")->execute([(int)($_POST['att_id'] ?? 0), $shopId]);
        }
        header('Location: ' . $BASE . '/staff.php?d=' . $wd); exit;
    }
}

// ---------------- データ取得 ----------------
$tagsTxt = $pricesTxt = '';
$staff = [];
$images = $rows = [];
if ($shopId > 0) {
    $st = $pdo->prepare("SELECT tag FROM shop_tags WHERE shop_id=? ORDER BY sort,id"); $st->execute([$shopId]);
    $tagsTxt = implode("\n", $st->fetchAll(PDO::FETCH_COLUMN));
    $st = $pdo->prepare("SELECT * FROM shop_prices WHERE shop_id=? ORDER BY sort,id"); $st->execute([$shopId]);
    $pricesTxt = implode("\n", array_map(fn($p) => $p['name'] . ' | ' . $p['price'] . ' | ' . $p['note'], $st->fetchAll()));
    $st = $pdo->prepare("SELECT * FROM shop_staff WHERE shop_id=? ORDER BY sort,id"); $st->execute([$shopId]);
    $staff = $st->fetchAll();
    $st = $pdo->prepare("SELECT * FROM shop_images WHERE shop_id=? ORDER BY sort,id"); $st->execute([$shopId]);
    $images = $st->fetchAll();
    $st = $pdo->prepare("SELECT * FROM attendance WHERE shop_id=? AND work_date=? ORDER BY id"); $st->execute([$shopId, $d]);
    $rows = $st->fetchAll();
}
$msgMap = ['saved'=>'店舗情報を保存しました。','uploaded'=>'写真をアップロードしました。','deleted'=>'写真を削除しました。','staff'=>'スタッフを更新しました。'];
$msg = $msgMap[$_GET['m'] ?? ''] ?? '';

function layout_head(string $title): void {
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . h($title) . '</title>';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap" rel="stylesheet">';
    echo '<style>:root{--bg:#1b1b20;--paper:#24242a;--soft:#2d2d34;--ink:#f0f0f3;--muted:#9a9aa3;--line:#3a3a42;--accent:#eeeef1}'
        . '*{box-sizing:border-box;margin:0;padding:0}body{font-family:"Zen Kaku Gothic New",sans-serif;background:var(--bg);color:var(--ink);line-height:1.7}'
        . 'a{color:inherit}header{background:var(--paper);border-bottom:1px solid var(--line)}'
        . '.bar{max-width:760px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;gap:12px}.logo{font-weight:900}.sp{flex:1}.bar a{font-size:13px;color:var(--muted)}'
        . '.wrap{max-width:760px;margin:24px auto;padding:0 20px 60px}.card{background:var(--paper);border:1px solid var(--line);border-radius:4px;padding:20px;margin-bottom:18px}'
        . 'h1{font-size:19px;margin-bottom:4px}h2{font-size:15px;margin-bottom:12px}.muted{color:var(--muted);font-size:13px;font-weight:400}'
        . 'label{display:block;font-size:12px;color:var(--muted);margin:12px 0 5px}'
        . 'input,textarea,select{width:100%;background:var(--bg);border:1px solid var(--line);color:var(--ink);border-radius:3px;padding:10px 11px;font-family:inherit;font-size:15px}'
        . 'textarea{min-height:96px;resize:vertical;line-height:1.6}'
        . '.row2{display:flex;gap:12px;flex-wrap:wrap}.row2>div{flex:1;min-width:150px}'
        . '.row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}.row>div{flex:1;min-width:110px}'
        . '.btn{background:var(--accent);color:var(--bg);border:none;font-weight:900;padding:11px 18px;border-radius:3px;font-size:14px;cursor:pointer}'
        . '.btn.sec{background:transparent;color:var(--ink);border:1px solid var(--line)}.btn.sm{padding:7px 12px;font-size:13px}.btn.danger{background:#b5483f;color:#fff;border:none}'
        . 'table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:9px 8px;border-bottom:1px solid var(--line)}th{color:var(--muted);font-size:12px}'
        . '.thumbs{display:flex;flex-wrap:wrap;gap:12px;margin-top:14px}.thumbs .t{width:140px}.thumbs .im{height:96px;border:1px solid var(--line);border-radius:3px;background:var(--soft) center/cover no-repeat;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:12px;margin-bottom:6px}'
        . '.msg{background:#2f6f4f;color:#dff5e9;padding:10px 13px;border-radius:3px;font-size:14px;margin-bottom:16px}'
        . '.err{background:#b5483f;color:#fff;padding:10px 13px;border-radius:3px;font-size:14px;margin-bottom:12px}'
        . 'form.inline{display:inline}</style></head><body>';
}

// ---------------- ログイン画面 ----------------
if ($shopId === 0) {
    layout_head('店舗ログイン｜店舗管理');
    echo '<header><div class="bar"><span class="logo">まちナビ 店舗管理</span></div></header><div class="wrap">';
    echo '<div class="card" style="max-width:420px;margin:30px auto">';
    echo '<h1>店舗ログイン</h1><p class="muted" style="margin-bottom:14px">運営から配布された管理キーを入力してください。</p>';
    if ($loginErr) { echo '<div class="err">' . h($loginErr) . '</div>'; }
    echo '<form method="post" action="staff.php">' . scsrf() . '<input type="hidden" name="login" value="1">';
    echo '<label>管理キー</label><input type="text" name="key" autocomplete="off" required>';
    echo '<div style="margin-top:16px"><button class="btn" type="submit" style="width:100%">ログイン</button></div></form>';
    echo '</div></div></body></html>';
    exit;
}

layout_head($shop['name'] . '｜店舗管理');
?>
<header><div class="bar"><span class="logo"><?= h($shop['name']) ?></span><span class="sp"></span><a href="<?= h($BASE) ?>/shop.php?id=<?= (int)$shop['id'] ?>" target="_blank">公開ページを見る</a><a href="staff.php?logout=1">ログアウト</a></div></header>
<div class="wrap">
  <?php if ($msg): ?><div class="msg"><?= h($msg) ?></div><?php endif; ?>

  <form method="post" action="staff.php">
    <?= scsrf() ?>
    <input type="hidden" name="act" value="save">

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
        <div><label>電話</label><input type="text" name="phone" value="<?= h($shop['phone']) ?>"></div>
        <div><label>決済</label><input type="text" name="payment" value="<?= h($shop['payment']) ?>"></div>
      </div>
    </div>

    <div class="card">
      <h2>属性タグ <span class="muted">（1行に1つ）</span></h2>
      <textarea name="tags" placeholder="ミックス（どなたでもOK）&#10;カラオケあり"><?= h($tagsTxt) ?></textarea>
    </div>

    <div class="card">
      <h2>料金システム <span class="muted">（1行＝ メニュー名 | 料金 | 備考）</span></h2>
      <textarea name="prices" placeholder="初回限定プラン | ¥3,500 | 90分 飲み放題"><?= h($pricesTxt) ?></textarea>
    </div>

    <div style="margin-bottom:18px"><button class="btn" type="submit">店舗情報を保存</button></div>
  </form>

  <div class="card">
    <h2>スタッフ</h2>
    <p class="muted" style="margin-bottom:12px">画像・名前・肩書・一言を1人ずつ登録できます。詳細ページのスタッフ紹介に表示されます。</p>
    <?php foreach ($staff as $m): ?>
    <div style="border:1px solid var(--line);border-radius:4px;padding:14px;margin-bottom:12px">
      <form method="post" action="staff.php" enctype="multipart/form-data">
        <?= scsrf() ?>
        <input type="hidden" name="act" value="upd_staff">
        <input type="hidden" name="staff_id" value="<?= (int)$m['id'] ?>">
        <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
          <div style="flex:0 0 auto;text-align:center">
            <div style="width:72px;height:72px;border-radius:6px;border:1px solid var(--line);background:var(--soft) center/cover no-repeat<?= $m['image_path'] !== '' ? ";background-image:url('" . h($m['image_path']) . "')" : '' ?>"></div>
            <label style="margin:6px 0 0">画像変更</label>
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
      <form method="post" action="staff.php" style="margin-top:8px" onsubmit="return confirm('このスタッフを削除しますか？');">
        <?= scsrf() ?>
        <input type="hidden" name="act" value="del_staff">
        <input type="hidden" name="staff_id" value="<?= (int)$m['id'] ?>">
        <button class="btn danger sm" type="submit">削除</button>
      </form>
    </div>
    <?php endforeach; ?>
    <?php if (!$staff): ?><p class="muted">スタッフはまだ登録されていません。</p><?php endif; ?>

    <h3 style="font-size:14px;margin:16px 0 8px">スタッフを追加</h3>
    <form method="post" action="staff.php" enctype="multipart/form-data">
      <?= scsrf() ?>
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

  <div class="card">
    <h2>写真（カルーセル）</h2>
    <form method="post" action="staff.php" enctype="multipart/form-data">
      <?= scsrf() ?>
      <input type="hidden" name="act" value="upload">
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
        <form method="post" action="staff.php" onsubmit="return confirm('この写真を削除しますか？');">
          <?= scsrf() ?>
          <input type="hidden" name="act" value="delimg">
          <input type="hidden" name="img_id" value="<?= (int)$img['id'] ?>">
          <button class="btn danger sm" type="submit">削除</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="muted" style="margin-top:10px">写真がありません。アップロードすると詳細ページのカルーセルに表示されます。</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>本日の出勤スタッフ</h2>
    <p class="muted">日付ごとに登録できます。詳細ページには当日分が表示されます（本日 <?= h($jstToday) ?>）。</p>
    <form method="get" action="staff.php" class="row" style="margin-top:12px">
      <div style="flex:0 1 200px"><label>日付</label><input type="date" name="d" value="<?= h($d) ?>"></div>
      <div style="flex:0 0 auto"><button class="btn sec sm" type="submit">表示</button></div>
    </form>
    <table style="margin-top:14px">
      <thead><tr><th>名前</th><th>備考</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r['name']) ?></td>
          <td class="muted"><?= h($r['note']) ?></td>
          <td style="text-align:right">
            <form method="post" action="staff.php" class="inline" onsubmit="return confirm('削除しますか？');">
              <?= scsrf() ?><input type="hidden" name="att_action" value="del"><input type="hidden" name="att_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="work_date" value="<?= h($d) ?>">
              <button class="btn danger sm" type="submit">削除</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="3" class="muted">この日の出勤はまだ登録されていません。</td></tr><?php endif; ?>
      </tbody>
    </table>
    <form method="post" action="staff.php" style="margin-top:16px">
      <?= scsrf() ?>
      <input type="hidden" name="att_action" value="add">
      <input type="hidden" name="work_date" value="<?= h($d) ?>">
      <div class="row">
        <div><label>名前</label><input type="text" name="name" placeholder="スタッフ名" required></div>
        <div><label>備考（任意）</label><input type="text" name="note" placeholder="カウンター担当 など"></div>
      </div>
      <div style="margin-top:14px"><button class="btn" type="submit">この日に追加</button></div>
    </form>
  </div>
</div>
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
</body></html>
