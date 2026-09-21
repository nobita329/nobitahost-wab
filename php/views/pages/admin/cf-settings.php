<?php
/**
 * Ported from views/pages/admin/cf-settings.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="cf-sub-head">
  <div class="card cf-tabs-card"><div class="cf-tabs">
    <a class="cf-tab active" href="/admin/cloudflare/settings-page"><svg class="ic"><use href="#i-pencil"/></svg> Connection & Zone</a>
  </div></div>
  <a class="btn btn-sm" href="/admin/cloudflare">← Overview</a>
</div>
<?php if ($query->saved) { ?><div class="alert alert-success">Settings saved.</div><?php } ?>
<?php if ($query->error) { ?><div class="alert alert-error">⚠ <?= e($query->error) ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-error">⚠ <?= e($error) ?> — check connection below.</div><?php } ?>

<form method="POST" action="/admin/cloudflare/settings">
<div class="cf-grid">
  <div class="cf-main">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> API Connection</h2>
        <?php if ($data && $data->zone) { ?><span class="badge green"><?= e($data->zone->name) ?> · <?= e($data->zone->status) ?></span><?php } ?>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-6 col-sm-12"><div class="field"><label>Email</label><input type="email" name="email" value="<?= e($cfg->email) ?>" placeholder="you@example.com"></div></div>
          <div class="col-6 col-sm-12"><div class="field"><label>Auth Mode</label>
            <select name="authMode">
              <option value="token" <?= e($cfg->authMode !== 'global' ? 'selected' : '') ?>>API Token</option>
              <option value="global" <?= e($cfg->authMode === 'global' ? 'selected' : '') ?>>Global API Key</option>
            </select>
          </div></div>
        </div>
        <div class="field"><label><span id="cfKeyLabel"><?= e($cfg->authMode === 'global' ? 'Global API Key' : 'API Token') ?></span></label><input type="text" name="apiToken" value="<?= e($maskedApiToken) ?>" placeholder="<?= e($maskedApiToken ? 'Saved — paste new to replace' : 'Paste key') ?>"></div>
        <button type="button" class="btn btn-sm" id="cfTestBtn">Test Connection</button>
        <button type="button" class="btn btn-accent btn-sm" id="cfDetectBtn">Auto Detect</button>
        <span id="cfTestResult" class="hint"></span>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-pencil"/></svg> Zone Settings</h2></div>
      <div class="card-body">
        <p class="hint">Live settings for the active zone. Changes apply instantly via the Cloudflare API.</p>
        <div class="row">
          <div class="col-4 col-md-6 col-sm-12"><div class="field"><label>Security Level</label>
            <select name="security_level">
              <?php foreach (['off','essentially_off','low','medium','high','under_attack'] as $v) { ?>
              <option <?= e($settingsMap->security_level === $v ? 'selected' : '') ?>><?= e($v) ?></option>
              <?php } ?>
            </select>
          </div></div>
          <div class="col-4 col-md-6 col-sm-12"><div class="field"><label>SSL Mode</label>
            <select name="ssl">
              <?php foreach (['off','flexible','full','strict'] as $v) { ?>
              <option <?= e($settingsMap->ssl === $v ? 'selected' : '') ?>><?= e($v) ?></option>
              <?php } ?>
            </select>
          </div></div>
          <div class="col-4 col-md-6 col-sm-12"><div class="field"><label>Cache Level</label>
            <select name="cache_level">
              <?php foreach (['basic','simplified','aggressive'] as $v) { ?>
              <option <?= e($settingsMap->cache_level === $v ? 'selected' : '') ?>><?= e($v) ?></option>
              <?php } ?>
            </select>
          </div></div>
          <div class="col-4 col-md-6 col-sm-12"><div class="field"><label>Min TLS Version</label>
            <select name="min_tls_version">
              <?php foreach (['1.0','1.1','1.2','1.3'] as $v) { ?>
              <option <?= e($settingsMap->min_tls_version === $v ? 'selected' : '') ?>><?= e($v) ?></option>
              <?php } ?>
            </select>
          </div></div>
          <?php foreach ([['always_online','Always Online'],['browser_check','Browser Integrity Check'],['development_mode','Development Mode'],['automatic_https_rewrites','Automatic HTTPS Rewrites'],['brotli','Brotli Compression'],['early_hints','Early Hints'],['http2','HTTP/2'],['http3','HTTP/3 (QUIC)'],['0rtt','0-RTT'],['ipv6','IPv6'],['websockets','WebSockets']] as $t) { ?>
          <div class="col-4 col-md-6 col-sm-12">
            <div class="switch-row"><div><b><?= e($t[1]) ?></b></div>
              <label class="switch"><input type="checkbox" name="<?= e($t[0]) ?>" value="on" <?= e($settingsMap->{$t[0]} === 'on' ? 'checked' : '') ?>><span></span></label>
            </div>
          </div>
          <?php } ?>
        </div>
        <button class="btn btn-accent" type="submit"><svg class="ic"><use href="#i-shield"/></svg> Save All Settings</button>
      </div>
    </div>
  </div>

  <div class="cf-side">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-chart"/></svg> Web Analytics Beacon</h2></div>
      <div class="card-body">
        <div class="switch-row"><div><b>Enable beacon</b><small>Injects CF Insights on every page</small></div>
          <label class="switch"><input type="checkbox" name="analytics_enabled" <?= e($cfg->analyticsEnabled ? 'checked' : '') ?>><span></span></label>
        </div>
        <div class="field"><label>Beacon Token</label><input type="text" name="analyticsToken" value="<?= e($cfg->analyticsToken) ?>" placeholder="From CF dashboard → Web Analytics"></div>
        <div class="field"><label>Zero Trust Team Domain</label><input type="text" name="ztTeam" value="<?= e($cfg->ztTeam) ?>" placeholder="https://team.cloudflareaccess.com"></div>
        <div class="switch-row"><div><b>Zero Trust panel</b></div>
          <label class="switch"><input type="checkbox" name="zerotrust_enabled" <?= e($cfg->ztEnabled ? 'checked' : '') ?>><span></span></label>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-link"/></svg> User Tokens</h2></div>
      <div class="card-body">
        <?php if (!nh_count($userTokens)) { ?><p class="hint center">No user tokens yet.</p><?php } else { ?>
        <div class="table-card">
          <table>
            <thead><tr><th>User</th><th>Token</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($userTokens as $t) { ?>
              <tr>
                <td><?= e($t->username) ?></td>
                <td class="mono"><?= e($t->token) ?></td>
                <td class="ta-r">
                  <form method="POST" action="/profile/cf-token/<?= e($t->id) ?>/delete" class="inline">
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
</form>

<script>
var modeSel = document.querySelector('[name="authMode"]');
if (modeSel) modeSel.addEventListener('change', function () {
  document.getElementById('cfKeyLabel').textContent = this.value === 'global' ? 'Global API Key' : 'API Token';
});
document.getElementById('cfTestBtn').addEventListener('click', function () {
  var btn = this, out = document.getElementById('cfTestResult');
  btn.disabled = true; out.textContent = 'Testing…';
  fetch('/admin/cloudflare/test', { method: 'POST', credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      out.textContent = j.ok ? '✓ Active (' + j.status + ')' : '✗ ' + j.error;
      out.style.color = j.ok ? 'var(--success)' : 'var(--danger)';
    })
    .catch(function () { out.textContent = '✗ Request failed'; out.style.color = 'var(--danger)'; })
    .finally(function () { btn.disabled = false; });
});
document.getElementById('cfDetectBtn').addEventListener('click', function () {
  var btn = this, out = document.getElementById('cfTestResult');
  btn.disabled = true; out.textContent = 'Detecting…'; out.style.color = '';
  fetch('/admin/cloudflare/detect', { method: 'POST', credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      if (j.ok) {
        out.textContent = '✓ ' + j.zone + ' (' + j.zone_status + ') — reloading…';
        out.style.color = 'var(--success)';
        setTimeout(function () { location.reload(); }, 1200);
      } else {
        out.textContent = '✗ ' + j.error;
        out.style.color = 'var(--danger)';
      }
    })
    .catch(function () { out.textContent = '✗ Request failed'; out.style.color = 'var(--danger)'; })
    .finally(function () { btn.disabled = false; });
});
</script>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

