<?php
require __DIR__ . '/auth.php';

if (current_user()) { header('Location: ' . safe_next($BASE)); exit; }

$next = (string)($_GET['next'] ?? '');
$tab  = (($_GET['tab'] ?? '') === 'register') ? 'register' : 'login';
member_header('ログイン・会員登録');
?>
<style>
.authtabs{display:flex;border:1px solid var(--line);border-radius:4px;overflow:hidden;margin-bottom:18px}
.authtabs button{flex:1;background:transparent;color:var(--muted);border:none;padding:12px;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer}
.authtabs button.on{background:var(--accent);color:var(--bg)}
.pane{display:none}
.pane.on{display:block}
</style>
<div class="card">
  <h1>ログイン・会員登録</h1>
  <?php if (isset($_GET['reset'])): ?><div class="ok">パスワードを再設定しました。新しいパスワードでログインしてください。</div><?php endif; ?>
  <p class="lead">アカウントでログイン、または新規登録できます。</p>

  <div class="authtabs">
    <button type="button" id="tab-login-btn" class="<?= $tab === 'login' ? 'on' : '' ?>">ログイン</button>
    <button type="button" id="tab-register-btn" class="<?= $tab === 'register' ? 'on' : '' ?>">新規登録</button>
  </div>

  <div id="pane-login" class="pane <?= $tab === 'login' ? 'on' : '' ?>">
    <form method="post" action="login.php">
      <?= ucsrf_field() ?>
      <input type="hidden" name="next" value="<?= h($next) ?>">
      <label>メールアドレス</label>
      <input type="email" name="email" required>
      <label>パスワード</label>
      <input type="password" name="password" required autocomplete="current-password">
      <button class="btn" type="submit">ログイン</button>
    </form>
    <p class="alt"><a href="<?= h($BASE) ?>/forgot.php">パスワードをお忘れですか？</a></p>
  </div>

  <div id="pane-register" class="pane <?= $tab === 'register' ? 'on' : '' ?>">
    <form method="post" action="register.php">
      <?= ucsrf_field() ?>
      <input type="hidden" name="next" value="<?= h($next) ?>">
      <label>メールアドレス</label>
      <input type="email" name="email" required>
      <label>ニックネーム（任意）</label>
      <input type="text" name="nickname" maxlength="60">
      <label>パスワード（8文字以上）</label>
      <input type="password" name="password" required autocomplete="new-password">
      <label>パスワード（確認）</label>
      <input type="password" name="password2" required autocomplete="new-password">
      <button class="btn" type="submit">登録する</button>
    </form>
  </div>
</div>
<script>
(function(){
  var lb=document.getElementById('tab-login-btn'), rb=document.getElementById('tab-register-btn');
  var lp=document.getElementById('pane-login'), rp=document.getElementById('pane-register');
  function sel(which){
    var login = which==='login';
    lb.classList.toggle('on', login); rb.classList.toggle('on', !login);
    lp.classList.toggle('on', login); rp.classList.toggle('on', !login);
  }
  lb.addEventListener('click',function(){sel('login');});
  rb.addEventListener('click',function(){sel('register');});
})();
</script>
<?php member_footer(); ?>
