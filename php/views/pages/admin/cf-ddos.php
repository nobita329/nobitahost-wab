<?php
/**
 * Ported from views/pages/admin/cf-ddos.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php /* EJS2PHP: layout include 'partials/cf-head' handled by the PHP layout */ ?>


<?php if ($tab === 'management') { ?>
<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Protection Level</h2></div>
  <div class="card-body">
    <p class="hint">Security Level decides how aggressively Cloudflare challenges suspicious visitors — higher = stronger DDoS/abuse protection.</p>
    <form method="POST" action="/admin/cloudflare/ddos/level" class="row cf-dns-form">
      <div class="col-6 col-md-12">
        <select name="level">
          <?php foreach ([['off','Off'],['essentially_off','Essentially Off'],['low','Low'],['medium','Medium (recommended)'],['high','High'],['under_attack',"I'm Under Attack!"]] as $lv) { ?>
          <option value="<?= e($lv[0]) ?>" <?= e($settingsMap->security_level === $lv[0] ? 'selected' : '') ?>><?= e($lv[1]) ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="col-3 col-md-12"><button class="btn btn-accent btn-block">Apply</button></div>
    </form>
    <?php if ($settingsMap->security_level) { ?><p>Current level: <b class="badge orange"><?= e($settingsMap->security_level) ?></b></p><?php } ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Other Protections</h2></div>
  <div class="card-body">
    <div class="table-card">
      <table>
        <thead><tr><th>Setting</th><th>Status</th></tr></thead>
        <tbody>
          <tr><td>Browser Integrity Check</td><td><span class="badge <?= e($settingsMap->browser_check === 'on' ? 'green' : '') ?>"><?= e($settingsMap->browser_check ?: '—') ?></span></td></tr>
          <tr><td>Challenge Passage</td><td><?= e($settingsMap->challenge_ttl ?: '—') ?>s</td></tr>
          <tr><td>Bot Fight Mode</td><td><span class="hint">Manage in Security tab / CF dashboard</span></td></tr>
          <tr><td>DDoS Protection (L7)</td><td><span class="badge green">Always on</span></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php } else { ?>
<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-chart"/></svg> Threats Blocked (7d)</h2></div>
  <div class="card-body">
    <?php if (!nh_count($analytics)) { ?><p class="hint center">No analytics data.</p><?php } else { ?>
    <?php $maxThr = max(array_map(fn($a) => $a->threats ?: 0, (array) $analytics)) ?: 1;
      $totThr = 0; foreach ($analytics as $a) { $totThr += $a->threats; }; ?>
    <div class="cf-chart cf-threats">
      <?php foreach ($analytics as $a) { ?>
      <div class="cf-bar-col" title="<?= e($a->date) ?>: <?= e($a->threats) ?> threats blocked">
        <div class="cf-bar red" style="height: <?= e(max(4, round($a->threats / $maxThr * 100))) ?>%"></div>
        <span class="cf-bar-label"><?= e(nh_slice($a->date, 5)) ?></span>
      </div>
      <?php } ?>
    </div>
    <div class="row cf-totals">
      <div class="col-4 col-md-4"><b><?= e(nh_num($totThr)) ?></b><span>Total threats (7d)</span></div>
    </div>
    <?php } ?>
  </div>
</div>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>


<!-- EJS2PHP notes: unconverted Math -->
