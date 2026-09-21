<?php /** Two-factor verification step. */ ?>
<h1>Two-factor check</h1>
<p class="cs-auth-sub">Enter the 6-digit code from your authenticator app</p>

<form method="post" action="/verify-2fa">
  <?= csrf_field() ?>
  <div class="field">
    <label for="code">Authentication code</label>
    <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
           placeholder="000000" style="letter-spacing:8px;text-align:center;font-size:20px;font-weight:700">
  </div>
  <button class="btn btn-primary" type="submit"><svg class="ic"><use href="#i-shield"/></svg> Verify &amp; continue</button>
</form>

<div class="cs-auth-alt"><a href="/login">Use a different account</a></div>
