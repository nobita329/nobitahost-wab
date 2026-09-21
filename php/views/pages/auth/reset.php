<?php /** Reset password (with valid token). Vars: $token */ ?>
<h1>Choose a new password</h1>
<p class="cs-auth-sub">Make it strong — you'll use it to sign in</p>

<form method="post" action="/reset/<?= e(rawurlencode((string) ($token ?? ''))) ?>">
  <?= csrf_field() ?>
  <div class="field">
    <label for="password">New password</label>
    <input id="password" name="password" type="password" required minlength="6" autofocus>
  </div>
  <div class="field">
    <label for="confirm_password">Confirm new password</label>
    <input id="confirm_password" name="confirm_password" type="password" required minlength="6">
  </div>
  <button class="btn btn-primary" type="submit"><svg class="ic"><use href="#i-key"/></svg> Update password</button>
</form>

<div class="cs-auth-alt"><a href="/login">Back to login</a></div>
