<?php
/**
 * Ported from views/pages/plans/settings.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query && $query->saved) { ?><div class="alert alert-success">Settings saved.</div><?php } ?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-sliders"/></svg> Plan Settings</h2></div>
      <div class="card-body">
        <form method="POST" action="/plans/settings" class="form">
          <div class="form-row">
            <div class="field"><label>Currency Symbol</label><input type="text" name="plan_currency" value="<?= e($settings->plan_currency ?: '$') ?>" placeholder="$"></div>
            <div class="field"><label>Currency Code</label><input type="text" name="plan_currency_code" value="<?= e($settings->plan_currency_code ?: 'USD') ?>" placeholder="USD"></div>
          </div>
          <div class="form-row">
            <div class="field"><label>Tax Rate (%)</label><input type="number" step="0.01" name="plan_tax_rate" value="<?= e($settings->plan_tax_rate ?: '0') ?>" placeholder="0"></div>
            <div class="field"><label>Tax Name</label><input type="text" name="plan_tax_name" value="<?= e($settings->plan_tax_name ?: 'Tax') ?>" placeholder="GST / VAT"></div>
          </div>
          <div class="form-row">
            <div class="field"><label>Free Trial Days</label><input type="number" name="plan_trial_days" value="<?= e($settings->plan_trial_days ?: '0') ?>" placeholder="0 = no trial"></div>
            <div class="field"><label>Default Plan</label>
              <select name="plan_default_id">
                <option value="">None</option>
                <?php foreach ($plans as $p) { ?>
                <option value="<?= e($p->id) ?>" <?= e($settings->plan_default_id == $p->id ? 'selected' : '') ?>><?= e($p->name) ?></option>
                <?php } ?>
              </select>
            </div>
          </div>
          <h3 style="margin:20px 0 12px;font-size:16px">Payment Gateways</h3>
          <div class="form-row">
            <div class="field"><label>Stripe Public Key</label><input type="text" name="plan_stripe_pk" value="<?= e($settings->plan_stripe_pk ?: '') ?>" placeholder="pk_..."></div>
            <div class="field"><label>Stripe Secret Key</label><input type="password" name="plan_stripe_sk" value="<?= e($settings->plan_stripe_sk ?: '') ?>" placeholder="sk_..."></div>
          </div>
          <div class="form-row">
            <div class="field"><label>Razorpay Key</label><input type="text" name="plan_razorpay_key" value="<?= e($settings->plan_razorpay_key ?: '') ?>" placeholder="rzp_..."></div>
            <div class="field"><label>Razorpay Secret</label><input type="password" name="plan_razorpay_secret" value="<?= e($settings->plan_razorpay_secret ?: '') ?>" placeholder="..."></div>
          </div>
          <div class="form-row">
            <div class="field"><label>PayPal Client ID</label><input type="text" name="plan_paypal_client" value="<?= e($settings->plan_paypal_client ?: '') ?>" placeholder="..."></div>
            <div class="field"><label>PayPal Secret</label><input type="password" name="plan_paypal_secret" value="<?= e($settings->plan_paypal_secret ?: '') ?>" placeholder="..."></div>
          </div>
          <button class="btn btn-accent" type="submit">Save Settings</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

