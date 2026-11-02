<?php
require __DIR__ . '/auth.php';
member_header('初回ガイド');
?>
<style>
.wrap{max-width:720px}
.legal h2{font-size:16px;margin:24px 0 8px}
.legal p{font-size:14px;color:#d4d4da;margin-bottom:8px}
.legal ol{margin:6px 0 6px 1.3em;font-size:14px;color:#d4d4da}
.legal li{margin:5px 0}
.legal .lead{font-size:14px;color:var(--muted);margin-bottom:8px}
.steps{display:flex;flex-direction:column;gap:12px;margin-top:10px}
.step{display:flex;gap:14px;align-items:flex-start;background:var(--soft);border:1px solid var(--line);border-radius:4px;padding:14px 16px}
.step .no{flex:0 0 auto;width:30px;height:30px;border-radius:50%;background:var(--accent);color:var(--bg);font-weight:900;display:grid;place-items:center;font-size:15px}
.step .tx{flex:1;min-width:0}
.step .tt{font-weight:700;font-size:15px;margin-bottom:2px}
.step .ds{font-size:13.5px;color:#cfcfd6}
.cta{display:inline-block;margin-top:8px;background:var(--accent);color:var(--bg);font-weight:900;text-decoration:none;padding:11px 20px;border-radius:3px}
</style>
<div class="card legal">
  <h1>初回ガイド</h1>
  <p class="lead">まちナビの使い方を、はじめての方向けにまとめました。</p>

  <h2>まちナビとは</h2>
  <p>まちナビは、さくら町エリアのお店を、エリア・ジャンル・属性から探せる店舗ガイドです。各店の詳細・写真・本日の出勤スタッフ・口コミ・会員限定クーポンを確認できます。</p>

  <h2>使い方</h2>
  <div class="steps">
    <div class="step"><div class="no">1</div><div class="tx"><div class="tt">お店を探す</div><div class="ds">トップのキーワード検索、またはエリア・ジャンルのタグでお店を絞り込めます。</div></div></div>
    <div class="step"><div class="no">2</div><div class="tx"><div class="tt">詳細を見る</div><div class="ds">気になるお店を開くと、写真・料金・本日の出勤スタッフ・口コミが見られます。写真はタップで拡大できます。</div></div></div>
    <div class="step"><div class="no">3</div><div class="tx"><div class="tt">会員登録（無料）</div><div class="ds">登録すると、各店の会員限定クーポンを利用できます。メールアドレスとパスワードだけで登録できます。</div></div></div>
    <div class="step"><div class="no">4</div><div class="tx"><div class="tt">クーポンを使う</div><div class="ds">ログイン後、お店の詳細ページでクーポンを「利用する」を押すと提示コードが発行されます。来店時にスタッフへお見せください。</div></div></div>
  </div>

  <h2>はじめての方へ</h2>
  <ol>
    <li>料金は各店の詳細ページに明記されています。来店前にご確認ください。</li>
    <li>本日の出勤スタッフは、お店が当日更新した情報です。</li>
    <li>口コミは実際のご利用に基づいて、マナーを守ってご投稿ください。</li>
  </ol>

  <p style="margin-top:14px"><a class="cta" href="<?= h($BASE) ?>/index.php">お店を探す</a></p>
  <p class="muted" style="margin-top:12px;font-size:12px">※本ページの記載はテスト用の仮内容です。正式な案内文は後日差し替えてください。</p>
</div>
<?php member_footer(); ?>
