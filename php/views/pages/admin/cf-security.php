<?php
/**
 * Ported from views/pages/admin/cf-security.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php /* EJS2PHP: layout include 'partials/cf-head' handled by the PHP layout */ ?>


<?php if ($tab === 'management') { ?>
<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-plus"/></svg> Add IP Access Rule</h2></div>
  <div class="card-body">
    <form method="POST" action="/admin/cloudflare/security/access" class="row cf-dns-form">
      <div class="col-3 col-md-6 col-sm-12"><select name="mode">
        <option value="block">Block</option>
        <option value="challenge">Challenge</option>
        <option value="js_challenge">JS Challenge</option>
        <option value="managed_challenge">Managed Challenge</option>
        <option value="allow">Allow (whitelist)</option>
      </select></div>
      <div class="col-4 col-md-6 col-sm-12"><input type="text" name="value" placeholder="IP address (e.g. 1.2.3.4)" required></div>
      <div class="col-3 col-md-6 col-sm-12"><input type="text" name="notes" placeholder="Notes (optional)"></div>
      <div class="col-2 col-md-12 col-sm-12"><button class="btn btn-accent btn-block">Add Rule</button></div>
    </form>
  </div>
</div>

<div class="row">
  <div class="col-6 col-md-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> IP Access Rules (<?= e(nh_count($arules)) ?>)</h2></div>
      <div class="card-body">
        <?php if (!nh_count($arules)) { ?><p class="hint center">No IP rules.</p><?php } else { ?>
        <div class="table-card">
          <table>
            <thead><tr><th>IP</th><th>Mode</th><th>Notes</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($arules as $r) { ?>
              <tr>
                <td class="mono"><?= e($r->configuration && $r->configuration->value) ?></td>
                <td><span class="badge <?= e($r->mode === 'block' ? 'red' : ($r->mode === 'allow' ? 'green' : 'orange')) ?>"><?= e($r->mode) ?></span></td>
                <td><?= e($r->notes ?: '—') ?></td>
                <td class="ta-r">
                  <form method="POST" action="/admin/cloudflare/security/access/<?= e($r->id) ?>/delete" class="inline" data-confirm="Remove this rule?">
                    <button class="icon-btn danger"><svg class="ic"><use href="#i-trash"/></svg></button>
                  </form>
                </td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
        <?php } ?>
      </div>
    </div>
  </div>

  <div class="col-6 col-md-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-file"/></svg> WAF Firewall Rules (<?= e(nh_count($rules)) ?>)</h2></div>
      <div class="card-body">
        <?php if (!nh_count($rules)) { ?><p class="hint center">No custom firewall rules. Create them in the Cloudflare dashboard → Security → WAF.</p><?php } else { ?>
        <div class="table-card">
          <table>
            <thead><tr><th>Rule</th><th>Action</th><th>State</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($rules as $r) { ?>
              <tr>
                <td title="<?= e($r->expression) ?>"><?= e($r->description ?: nh_slice($r->expression, 0, 40) . '…') ?></td>
                <td><span class="badge orange"><?= e($r->action) ?></span></td>
                <td><span class="badge <?= e($r->paused ? '' : 'green') ?>"><?= e($r->paused ? 'Paused' : 'Active') ?></span></td>
                <td class="ta-r row-actions">
                  <form method="POST" action="/admin/cloudflare/security/rule/<?= e($r->id) ?>/toggle" class="inline">
                    <input type="hidden" name="paused" value="<?= e($r->paused ? '0' : '1') ?>">
                    <button class="icon-btn" title="<?= e($r->paused ? 'Resume' : 'Pause') ?>"><svg class="ic"><use href="#i-eye"/></svg></button>
                  </form>
                  <form method="POST" action="/admin/cloudflare/security/rule/<?= e($r->id) ?>/delete" class="inline" data-confirm="Delete this rule?">
                    <button class="icon-btn danger"><svg class="ic"><use href="#i-trash"/></svg></button>
                  </form>
                </td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<?php } else { ?>
<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-chart"/></svg> Security Events by Action (7d)</h2></div>
  <div class="card-body">
    <?php if (!nh_count($events)) { ?><p class="hint center">No security events recorded (or not available on this plan).</p><?php } else { ?>
    <?php $maxC = max(array_map(fn($e) => $e->count ?: 1, (array) $events));
      $tot = 0; foreach ($events as $e) { $tot += $e->count; }; ?>
    <div class="cf-chart">
      <?php foreach ($events as $e) { ?>
      <div class="cf-bar-col" title="<?= e($e->dimensions->action) ?>: <?= e($e->count) ?>">
        <div class="cf-bar red" style="height: <?= e(max(4, round($e->count / $maxC * 100))) ?>%"></div>
        <span class="cf-bar-label"><?= e($e->dimensions->action) ?></span>
      </div>
      <?php } ?>
    </div>
    <div class="row cf-totals">
      <div class="col-4 col-md-4"><b><?= e(nh_num($tot)) ?></b><span>Total events (7d)</span></div>
    </div>
    <?php } ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Current Posture</h2></div>
  <div class="card-body">
    <div class="table-card">
      <table>
        <thead><tr><th>Setting</th><th>Value</th></tr></thead>
        <tbody>
          <tr><td>Security Level</td><td><span class="badge orange"><?= e($settingsMap->security_level ?: '—') ?></span></td></tr>
          <tr><td>Browser Integrity Check</td><td><span class="badge <?= e($settingsMap->browser_check === 'on' ? 'green' : '') ?>"><?= e($settingsMap->browser_check ?: '—') ?></span></td></tr>
          <tr><td>WAF Managed Rules</td><td><span class="hint">See CF dashboard for per-rule status</span></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>


<!-- EJS2PHP notes: unconverted Math -->
