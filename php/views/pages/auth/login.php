<?php
/** Login — CasaOS glass card. Vars: $next, $error, $settings */
$next = $next ?? '/';
$demo = DB::get('SELECT id FROM users WHERE is_demo = 1 OR username = ? LIMIT 1', ['demo']);
?>
<h1>Welcome back</h1>
<p class="cs-auth-sub">Sign in to <?= e($settings->panel_name ?? 'NobitaHost') ?></p>

<?php if (!empty($error)): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="/login" autocomplete="on">
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">

  <div class="field">
    <label for="username">Username or email</label>
    <input id="username" name="username" type="text" required autofocus autocomplete="username" placeholder="admin">
  </div>

  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••">
  </div>

  <div class="cs-row" style="justify-content:space-between;margin:4px 0 14px">
    <label class="cs-row" style="gap:6px;font-size:12.5px;color:var(--cs-muted);font-weight:600">
      <input type="checkbox" name="remember" value="1" checked> Remember me
    </label>
    <a href="/forgot" style="font-size:12.5px;color:var(--cs-accent);font-weight:700">Forgot password?</a>
  </div>

  <button class="btn btn-primary" type="submit"><svg class="ic"><use href="#i-lock"/></svg> Sign in</button>
</form>

<?php if ($demo): ?>
  <a class="btn btn-ghost" style="width:100%;justify-content:center;margin-top:10px" href="/login/demo">
    <svg class="ic"><use href="#i-eye"/></svg> Try the demo account
  </a>
<?php endif; ?>

<div class="cs-auth-alt">
  <?php if (setting('register_open') === 'on'): ?>
    New here? <a href="/register">Create an account</a>
  <?php else: ?>
    Registration is closed — ask an admin for an account.
  <?php endif; ?>
</div>
