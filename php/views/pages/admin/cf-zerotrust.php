<?php
/**
 * Ported from views/pages/admin/cf-zerotrust.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php /* EJS2PHP: layout include 'partials/cf-head' handled by the PHP layout */ ?>


<?php if ($tab === 'management') { ?>
<div class="row">
  <div class="col-4 col-md-4 col-sm-12"><div class="card stat-card"><div class="stat-icon accent1"><svg class="ic"><use href="#i-shield"/></svg></div><div class="stat-meta"><b><?= e(nh_count($apps)) ?></b><span>Access Apps</span></div></div></div>
  <div class="col-4 col-md-4 col-sm-12"><div class="card stat-card"><div class="stat-icon accent2"><svg class="ic"><use href="#i-users"/></svg></div><div class="stat-meta"><b><?= e(nh_count($users)) ?></b><span>ZT Users</span></div></div></div>
  <div class="col-4 col-md-4 col-sm-12"><div class="card stat-card"><div class="stat-icon accent3"><svg class="ic"><use href="#i-link"/></svg></div><div class="stat-meta"><b><?= e(nh_count($devices)) ?></b><span>Devices</span></div></div></div>
</div>

<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Access Applications</h2></div>
  <div class="card-body">
    <?php if (!nh_count($apps)) { ?><p class="hint center">No Access apps found.</p><?php } else { ?>
    <div class="table-card">
      <table>
        <thead><tr><th>Name</th><th>Domain</th><th>Type</th><th>Created</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($apps as $a) { ?>
          <tr>
            <td><?= e($a->name) ?></td>
            <td class="mono"><?= e(($a->domain ?: '—')) ?></td>
            <td><?= e($a->type) ?></td>
            <td><?= e(nh_slice(($a->created_at ?: ''), 0, 10)) ?></td>
            <td class="ta-r">
              <form method="POST" action="/admin/cloudflare/zerotrust/app/<?= e($a->id) ?>/delete" class="inline" data-confirm="Delete this Access app?">
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

<div class="row">
  <div class="col-6 col-md-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-users"/></svg> Users</h2></div>
      <div class="card-body">
        <?php if (!nh_count($users)) { ?><p class="hint center">No Zero Trust users.</p><?php } else { ?>
        <div class="table-card">
          <table>
            <thead><tr><th>Email</th><th>Name</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach (nh_slice($users, 0, 15) as $u) { ?>
              <tr><td><?= e($u->email) ?></td><td><?= e($u->name ?: '—') ?></td><td><span class="badge"><?= e($u->state ?: '—') ?></span></td></tr>
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
      <div class="card-head"><h2><svg class="ic"><use href="#i-link"/></svg> Devices</h2></div>
      <div class="card-body">
        <?php if (!nh_count($devices)) { ?><p class="hint center">No managed devices.</p><?php } else { ?>
        <div class="table-card">
          <table>
            <thead><tr><th>Name</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach (nh_slice($devices, 0, 15) as $d) { ?>
              <tr><td><?= e($d->name ?: $d->id) ?></td><td><?= e($d->type ?: '—') ?></td><td><?= e($d->status ?: '—') ?></td></tr>
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
<div class="row">
  <div class="col-4 col-md-4 col-sm-12"><div class="card stat-card"><div class="stat-icon accent1"><svg class="ic"><use href="#i-shield"/></svg></div><div class="stat-meta"><b><?= e($summary && $summary->apps != null ? $summary->apps : '—') ?></b><span>Access Apps<?= e($summary && $summary->appsError ? ' ⚠' : '') ?></span></div></div></div>
  <div class="col-4 col-md-4 col-sm-12"><div class="card stat-card"><div class="stat-icon accent2"><svg class="ic"><use href="#i-users"/></svg></div><div class="stat-meta"><b><?= e($summary && $summary->users != null ? $summary->users : '—') ?></b><span>ZT Users<?= e($summary && $summary->usersError ? ' ⚠' : '') ?></span></div></div></div>
  <div class="col-4 col-md-4 col-sm-12"><div class="card stat-card"><div class="stat-icon accent3"><svg class="ic"><use href="#i-link"/></svg></div><div class="stat-meta"><b><?= e($summary && $summary->devices != null ? $summary->devices : '—') ?></b><span>Devices<?= e($summary && $summary->devicesError ? ' ⚠' : '') ?></span></div></div></div>
</div>
<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-chart"/></svg> Zero Trust Overview</h2></div>
  <div class="card-body">
    <p class="hint">Live counts from your Cloudflare Zero Trust organization. Detailed usage analytics are available in the Cloudflare dashboard → Zero Trust → Analytics.</p>
    <?php if ($summary) { ?>
    <p>Team domain: <b class="mono"><?= e(isset($cfg) ? '' : '') ?>cloudflareaccess.com</b></p>
    <?php } ?>
  </div>
</div>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

