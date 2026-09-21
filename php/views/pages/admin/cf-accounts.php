<?php
/**
 * Ported from views/pages/admin/cf-accounts.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php /* EJS2PHP: layout include 'partials/cf-head' handled by the PHP layout */ ?>


<?php if ($tab === 'management') { ?>
<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-users"/></svg> Accounts (<?= e(nh_count($accs)) ?>)</h2></div>
  <div class="card-body">
    <div class="table-card">
      <table>
        <thead><tr><th>Name</th><th>ID</th><th>Created</th></tr></thead>
        <tbody>
          <?php foreach ($accs as $a) { ?>
          <tr>
            <td><b><?= e($a->name) ?></b> <?= e($a->id === (isset($currentAccountId) ? $currentAccountId : '') ? '<span class="badge green">active</span>' : '') ?></td>
            <td class="mono"><?= e(nh_slice($a->id, 0, 16)) ?>…</td>
            <td><?= e(nh_slice(($a->created_on ?: ''), 0, 10)) ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-users"/></svg> Members</h2></div>
  <div class="card-body">
    <?php if (!nh_count($members)) { ?><p class="hint center">No members visible.</p><?php } else { ?>
    <div class="table-card">
      <table>
        <thead><tr><th>Email</th><th>Name</th><th>Status</th><th>Roles</th></tr></thead>
        <tbody>
          <?php foreach ($members as $m) { ?>
          <tr>
            <td><?= e($m->user && $m->user->email) ?></td>
            <td><?= e($m->user && ($m->user->first_name ?: $m->user->name ?: '—')) ?></td>
            <td><span class="badge <?= e($m->status === 'accepted' ? 'green' : 'orange') ?>"><?= e($m->status) ?></span></td>
            <td><?= e(nh_join(array_map(fn($r) => $r->name, (array) ($m->roles ?: [])), ', ')) ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
  </div>
</div>

<?php } else { ?>
<p class="hint">Aggregated traffic across your top domains (last 7 days).</p>
<?php $tot = [ 'requests' => 0, 'pageViews' => 0, 'visitors' => 0, 'threats' => 0, 'bytes' => 0 ];
  foreach ($stats as $s) { $tot->requests += $s->requests; $tot->pageViews += $s->pageViews; $tot->visitors += $s->visitors; $tot->threats += $s->threats; $tot->bytes += $s->bytes; }; ?>
<div class="row">
  <div class="col-3 col-md-6 col-sm-12"><div class="card stat-card"><div class="stat-icon accent1"><svg class="ic"><use href="#i-chart"/></svg></div><div class="stat-meta"><b><?= e(nh_num($tot->requests)) ?></b><span>Requests (7d)</span></div></div></div>
  <div class="col-3 col-md-6 col-sm-12"><div class="card stat-card"><div class="stat-icon accent2"><svg class="ic"><use href="#i-eye"/></svg></div><div class="stat-meta"><b><?= e(nh_num($tot->pageViews)) ?></b><span>Page Views</span></div></div></div>
  <div class="col-3 col-md-6 col-sm-12"><div class="card stat-card"><div class="stat-icon accent3"><svg class="ic"><use href="#i-users"/></svg></div><div class="stat-meta"><b><?= e(nh_num($tot->visitors)) ?></b><span>Visitors</span></div></div></div>
  <div class="col-3 col-md-6 col-sm-12"><div class="card stat-card"><div class="stat-icon accent4"><svg class="ic"><use href="#i-shield"/></svg></div><div class="stat-meta"><b><?= e(number_format((float) ($tot->bytes / 1073741824), 1)) ?> GB</b><span>Bandwidth</span></div></div></div>
</div>

<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-link"/></svg> Top Domains</h2></div>
  <div class="card-body">
    <?php if (!nh_count($stats)) { ?><p class="hint center">No data.</p><?php } else { ?>
    <div class="cf-domain-bars">
      <?php $maxR = max(array_map(fn($s) => $s->requests ?: 1, (array) $stats)); ?>
      <?php foreach (nh_slice($stats, 0, 10) as $s) { ?>
      <div class="cf-db-row">
        <span class="cf-db-name"><?= e($s->name) ?></span>
        <div class="cf-db-track"><div class="cf-db-fill" style="width: <?= e(max(2, round($s->requests / $maxR * 100))) ?>%"></div></div>
        <span class="cf-db-val"><?= e(nh_num($s->requests)) ?></span>
      </div>
      <?php } ?>
    </div>
    <?php } ?>
  </div>
</div>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>


<!-- EJS2PHP notes: arrow function left as-is; unconverted Math -->
