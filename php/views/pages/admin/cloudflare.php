<?php
/**
 * Ported from views/pages/admin/cloudflare.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query->saved) { ?><div class="alert alert-success">Cloudflare settings saved.</div><?php } ?>

<div class="row">
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card"><div class="stat-icon accent1"><svg class="ic"><use href="#i-shield"/></svg></div><div class="stat-meta"><b><?= e($data->conn ? $data->conn->status : '—') ?></b><span>API Status</span></div></div>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card"><div class="stat-icon accent2"><svg class="ic"><use href="#i-link"/></svg></div><div class="stat-meta"><b><?= e($zonesCount) ?></b><span>Domains</span></div></div>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card"><div class="stat-icon accent3"><svg class="ic"><use href="#i-eye"/></svg></div><div class="stat-meta"><b><?= e($cfg->analyticsEnabled ? 'On' : 'Off') ?></b><span>Web Analytics</span></div></div>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card"><div class="stat-icon accent4"><svg class="ic"><use href="#i-users"/></svg></div><div class="stat-meta"><b><?= e(nh_count($userTokens)) ?></b><span>User Tokens</span></div></div>
  </div>
</div>

<div class="row cf-nav-grid">
  <div class="col-3 col-md-6 col-sm-12">
    <a class="card pe-hub-card" href="/admin/cloudflare/zerotrust">
      <span class="stat-icon accent1"><svg class="ic"><use href="#i-shield"/></svg></span>
      <b>Zero Trust</b>
      <p class="hint">Access apps, users & devices — manage and monitor.</p>
    </a>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <a class="card pe-hub-card" href="/admin/cloudflare/domains">
      <span class="stat-icon accent2"><svg class="ic"><use href="#i-link"/></svg></span>
      <b>Domains</b>
      <p class="hint">All <?= e($zonesCount) ?> domains with per-domain traffic analytics.</p>
    </a>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <a class="card pe-hub-card" href="/admin/cloudflare/dns">
      <span class="stat-icon accent3"><svg class="ic"><use href="#i-file"/></svg></span>
      <b>DNS / Records</b>
      <p class="hint">Full DNS management — create, edit, delete records live.</p>
    </a>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <a class="card pe-hub-card" href="/admin/cloudflare/ddos">
      <span class="stat-icon accent4"><svg class="ic"><use href="#i-shield"/></svg></span>
      <b>DDoS Protection</b>
      <p class="hint">Protection level control + threats blocked chart.</p>
    </a>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <a class="card pe-hub-card" href="/admin/cloudflare/security">
      <span class="stat-icon accent2"><svg class="ic"><use href="#i-lock"/></svg></span>
      <b>Security</b>
      <p class="hint">IP rules, WAF rules and security events analytics.</p>
    </a>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <a class="card pe-hub-card" href="/admin/cloudflare/settings-page">
      <span class="stat-icon accent1"><svg class="ic"><use href="#i-pencil"/></svg></span>
      <b>Settings</b>
      <p class="hint">Connection, zone settings (SSL/cache), beacon & tokens.</p>
    </a>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <a class="card pe-hub-card" href="/admin/cloudflare/accounts">
      <span class="stat-icon accent3"><svg class="ic"><use href="#i-users"/></svg></span>
      <b>Accounts</b>
      <p class="hint">Account info, members and aggregated traffic.</p>
    </a>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2><svg class="ic"><use href="#i-chart"/></svg> Active Zone · <?= e($data->zone ? $data->zone->name : 'Not set') ?></h2>
    <?php if ($data->zone) { ?><span class="badge green"><?= e($data->zone->status) ?></span><?php } ?>
  </div>
  <div class="card-body">
    <?php if ($data->connError) { ?><p class="hint" style="color: var(--danger)">⚠ <?= e($data->connError) ?></p><?php } ?>
    <?php if (!$cfg->apiToken) { ?>
      <p class="hint center">No API credentials configured. Add them in <a href="/admin/cloudflare/settings-page">Settings</a>.</p>
    <?php } else if (nh_count($data->analytics)) { ?>
    <?php $maxReq = max(array_map(fn($a) => $a->requests ?: 1, (array) $data->analytics));
      $tot = (object) [ 'requests' => 0, 'pageViews' => 0, 'visitors' => 0, 'threats' => 0 ];
      foreach ($data->analytics as $a) { $tot->requests += $a->requests; $tot->pageViews += $a->pageViews; $tot->visitors += $a->visitors; $tot->threats += $a->threats; }; ?>
    <div class="cf-chart">
      <?php foreach ($data->analytics as $a) { ?>
      <div class="cf-bar-col" title="<?= e($a->date) ?>: <?= e($a->requests) ?> requests">
        <div class="cf-bar" style="height: <?= e(max(4, round($a->requests / $maxReq * 100))) ?>%"></div>
        <span class="cf-bar-label"><?= e(nh_slice($a->date, 5)) ?></span>
      </div>
      <?php } ?>
    </div>
    <div class="row cf-totals">
      <div class="col-3 col-md-6"><b><?= e(nh_num($tot->requests)) ?></b><span>Requests (7d)</span></div>
      <div class="col-3 col-md-6"><b><?= e(nh_num($tot->pageViews)) ?></b><span>Page Views</span></div>
      <div class="col-3 col-md-6"><b><?= e(nh_num($tot->visitors)) ?></b><span>Visitors</span></div>
      <div class="col-3 col-md-6"><b><?= e(nh_num($tot->threats)) ?></b><span>Threats</span></div>
    </div>
    <?php } else { ?>
    <p class="hint center">No traffic data for this zone in the last 7 days.</p>
    <?php } ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-link"/></svg> User Tokens</h2></div>
  <div class="card-body">
    <?php if (!nh_count($userTokens)) { ?>
      <p class="hint center">Kisi user ne abhi token add nahi kiya. Har user apne profile → Cloudflare me apna token add kar sakta hai (max 5).</p>
    <?php } else { ?>
    <div class="table-card">
      <table>
        <thead><tr><th>User</th><th>Label</th><th>Token</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($userTokens as $t) { ?>
          <tr>
            <td><?= e($t->username) ?></td>
            <td><?= e($t->label ?: '—') ?></td>
            <td class="mono"><?= e($t->token) ?></td>
            <td class="ta-r">
              <form method="POST" action="/profile/cf-token/<?= e($t->id) ?>/delete" class="inline">
                <button class="icon-btn danger" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
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

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>


<!-- EJS2PHP notes: unconverted Math -->
