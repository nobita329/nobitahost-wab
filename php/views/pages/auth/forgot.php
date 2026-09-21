<?php /** Forgot password. */ ?>
<h1>Reset password</h1>
<p class="cs-auth-sub">We'll email you a secure reset link</p>

<form method="post" action="/forgot">
  <?= csrf_field() ?>
  <div class="field">
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" required autofocus placeholder="you@example.com">
  </div>
  <button class="btn btn-primary" type="submit"><svg class="ic"><use href="#i-mail"/></svg> Send reset link</button>
</form>

<div class="cs-auth-alt">Remembered it? <a href="/login">Back to login</a></div>
