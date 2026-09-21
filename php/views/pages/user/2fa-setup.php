<?php
/**
 * Ported from views/pages/user/2fa-setup.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-6 col-md-10 col-sm-12" style="margin:0 auto">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-lock"/></svg> Setup Two-Factor Authentication</h2></div>
      <div class="card-body">
        <ol class="steps">
          <li><b>1.</b> Install an authenticator app (Google Authenticator, Authy, Microsoft Authenticator).</li>
          <li><b>2.</b> Scan the QR code below or enter the secret manually.</li>
          <li><b>3.</b> Enter the 6-digit code to confirm.</li>
        </ol>

        <div class="qr-box">
          <?php if (!empty($qr)): ?>
            <img src="<?= e($qr) ?>" alt="2FA QR Code">
          <?php else: ?>
            <img class="js-qr" data-qr="<?= e($keyuri ?? '') ?>" alt="2FA QR Code" width="228" height="228">
            <script src="/assets/js/vendor/qrcode-generator.js" defer></script>
            <script src="/assets/js/qr.js" defer></script>
          <?php endif; ?>
          <code class="secret"><?= e($secret) ?></code>
          <button class="btn btn-ghost btn-sm" id="copySecret">Copy Secret</button>
        </div>

        <form method="POST" action="/profile/2fa/enable" class="form">
          <input type="hidden" name="secret" value="<?= e($secret) ?>">
          <div class="field"><label>Verification Code</label>
            <input class="otp-input" type="text" name="code" maxlength="6" inputmode="numeric" placeholder="000000" required>
          </div>
          <button class="btn btn-accent btn-block" type="submit">Enable 2FA</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

