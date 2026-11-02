<?php
require __DIR__ . '/auth.php';
$updated = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y年n月j日');
member_header('プライバシーポリシー');
?>
<style>
.wrap{max-width:720px}
.legal h2{font-size:15px;margin:22px 0 6px}
.legal p{font-size:14px;color:#d4d4da;margin-bottom:6px}
.legal ol{margin:4px 0 4px 1.3em;font-size:14px;color:#d4d4da}
.legal li{margin:3px 0}
.legal .note{background:var(--soft);border:1px solid var(--line);border-radius:3px;padding:10px 12px;font-size:13px;color:var(--muted);margin-bottom:8px}
</style>
<div class="card legal">
  <h1>プライバシーポリシー</h1>
  <p class="muted">最終更新日：<?= h($updated) ?></p>
  <p class="note">※本ページはテスト用の仮文章です。正式な内容は後日差し替えてください。</p>

  <p>Example Inc.（以下「当社」といいます）は、店舗情報サービス「まちナビ」（以下「本サービス」といいます）における個人情報の取り扱いについて、以下のとおりプライバシーポリシーを定めます。</p>

  <h2>1. 取得する情報</h2>
  <ol>
    <li>会員登録時に入力されるメールアドレス、ニックネーム</li>
    <li>クーポン利用などのサービス利用履歴</li>
    <li>アクセスログ等の技術情報（テスト用）</li>
  </ol>

  <h2>2. 利用目的</h2>
  <p>取得した情報は、本サービスの提供・本人確認・お問い合わせ対応・サービス改善のために利用します。これはテスト用のサンプルテキストです。</p>

  <h2>3. 第三者提供</h2>
  <p>当社は、法令に基づく場合を除き、本人の同意なく個人情報を第三者に提供しません。（テスト用）</p>

  <h2>4. Cookie等の利用</h2>
  <p>本サービスは、ログイン状態の保持等のためにCookieを利用する場合があります。これはテスト用の仮文章です。</p>

  <h2>5. 開示・訂正・削除</h2>
  <p>利用者は、当社所定の方法により、自己の個人情報の開示・訂正・削除を求めることができます。（テスト用）</p>

  <h2>6. お問い合わせ窓口</h2>
  <p>個人情報の取り扱いに関するお問い合わせ先は、テスト用の仮記載です。</p>

  <h2>7. プライバシーポリシーの変更</h2>
  <p>当社は、必要に応じて本ポリシーを変更することがあります。変更後の内容は本ページに掲載した時点で効力を生じます。（テスト用）</p>

  <h2>運営者</h2>
  <p>Example Inc.</p>
</div>
<?php member_footer(); ?>
