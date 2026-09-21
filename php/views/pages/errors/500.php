<?php /** 500 — rendered inside the app shell. */ ?>
<div class="cs-panel" style="margin:auto;max-width:560px">
  <div class="cs-empty" style="padding:44px 24px">
    <div class="cs-empty-ic" style="width:72px;height:72px;font-size:32px;border-radius:24px">⚠️</div>
    <b style="font-size:20px">500 · Something broke</b>
    <p>The panel hit an unexpected error. It has been logged — try again in a moment.</p>
    <?php if (!empty($error) && Env::get('APP_DEBUG') === 'true'): ?>
      <pre class="cs-mono" style="text-align:left;background:var(--cs-chip);padding:10px;border-radius:10px;max-height:160px;overflow:auto;width:100%"><?= e($error) ?></pre>
    <?php endif; ?>
    <div class="cs-row" style="justify-content:center;margin-top:10px">
      <a class="btn btn-primary" href="/"><svg class="ic"><use href="#i-home"/></svg> Back to desktop</a>
      <button class="btn btn-ghost" onclick="location.reload()"><svg class="ic"><use href="#i-refresh"/></svg> Retry</button>
    </div>
  </div>
</div>
