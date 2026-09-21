<?php /** Register — CasaOS glass card. */ ?>
<h1>Create account</h1>
<p class="cs-auth-sub">Join <?= e($settings->panel_name ?? 'NobitaHost') ?> in seconds</p>

<form method="post" action="/register">
  <?= csrf_field() ?>

  <div class="field">
    <label for="username">Username</label>
    <input id="username" name="username" type="text" required minlength="3" maxlength="24" autofocus placeholder="yourname">
  </div>

  <div class="field">
    <label for="email">Email</label>
    <input id="email" name="email" type="email" required placeholder="you@example.com">
  </div>

  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required minlength="6" placeholder="At least 6 characters">
  </div>

  <div class="field">
    <label for="confirm_password">Confirm password</label>
    <input id="confirm_password" name="confirm_password" type="password" required minlength="6" placeholder="Repeat password">
  </div>

  <button class="btn btn-primary" type="submit" style="margin-top:6px"><svg class="ic"><use href="#i-user-plus"/></svg> Create account</button>
</form>

<div class="cs-auth-alt">Already registered? <a href="/login">Sign in</a></div>
