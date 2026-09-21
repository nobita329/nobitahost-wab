<?php /** Maintenance mode. */ ?>
<h1>Be right back</h1>
<p class="cs-auth-sub"><?= e($settings->panel_name ?? 'NobitaHost') ?> is under maintenance.</p>
<div class="cs-empty" style="padding:18px 0">
  <div class="cs-empty-ic">🛠️</div>
  <p>We're upgrading things. Admins can still sign in.</p>
</div>
<a class="btn btn-primary" href="/login" style="width:100%;justify-content:center"><svg class="ic"><use href="#i-lock"/></svg> Admin login</a>
