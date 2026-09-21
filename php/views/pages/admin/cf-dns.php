<?php
/**
 * Ported from views/pages/admin/cf-dns.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php /* EJS2PHP: layout include 'partials/cf-head' handled by the PHP layout */ ?>


<?php if ($tab === 'management') { ?>
<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-plus"/></svg> Add Record</h2></div>
  <div class="card-body">
    <form method="POST" action="/admin/cloudflare/dns" class="row cf-dns-form">
      <div class="col-2 col-md-6 col-sm-12"><select name="type">
        <?php foreach (['A','AAAA','CNAME','TXT','MX','NS','SRV','CAA'] as $t) { ?><option><?= e($t) ?></option><?php } ?>
      </select></div>
      <div class="col-3 col-md-6 col-sm-12"><input type="text" name="name" placeholder="Name (e.g. @ or www)" required></div>
      <div class="col-4 col-md-12 col-sm-12"><input type="text" name="content" placeholder="Content / target" required></div>
      <div class="col-1 col-md-6 col-sm-6"><label class="switch sm" title="Proxied"><input type="checkbox" name="proxied" checked><span></span></label></div>
      <div class="col-2 col-md-6 col-sm-6"><button class="btn btn-accent btn-block">Create</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-file"/></svg> DNS Records (<?= e(nh_count($records)) ?>)</h2></div>
  <div class="card-body">
    <?php if (!nh_count($records)) { ?><p class="hint center">No DNS records in this zone.</p><?php } else { ?>
    <div class="table-card">
      <table>
        <thead><tr><th>Type</th><th>Name</th><th>Content</th><th>Proxy</th><th>TTL</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($records as $r) { ?>
          <tr>
            <td><span class="badge"><?= e($r->type) ?></span></td>
            <td class="mono"><?= e($r->name) ?></td>
            <td class="mono cf-content-cell"><?= e(nh_count($r->content) > 60 ? nh_slice($r->content, 0, 60) . '…' : $r->content) ?></td>
            <td><?= e($r->proxied ? '<span class="badge orange">Proxied</span>' : 'DNS only') ?></td>
            <td><?= e($r->ttl === 1 ? 'Auto' : $r->ttl) ?></td>
            <td class="ta-r row-actions">
              <button class="icon-btn" type="button" title="Edit" onclick="var f=this.closest('tr').querySelector('.cf-edit-row'); f.hidden=!f.hidden;"><svg class="ic"><use href="#i-pencil"/></svg></button>
              <form method="POST" action="/admin/cloudflare/dns/<?= e($r->id) ?>/delete" class="inline" data-confirm="Delete this record?">
                <button class="icon-btn danger" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
              </form>
              <form method="POST" action="/admin/cloudflare/dns/<?= e($r->id) ?>/edit" class="cf-edit-row" hidden>
                <div class="row">
                  <div class="col-2 col-md-6"><select name="type"><?php foreach (['A','AAAA','CNAME','TXT','MX','NS','SRV','CAA'] as $t) { ?><option <?= e($r->type === $t ? 'selected' : '') ?>><?= e($t) ?></option><?php } ?></select></div>
                  <div class="col-3 col-md-6"><input type="text" name="name" value="<?= e($r->name) ?>"></div>
                  <div class="col-4 col-md-12"><input type="text" name="content" value="<?= e($r->content) ?>"></div>
                  <div class="col-1 col-md-6"><label class="switch sm"><input type="checkbox" name="proxied" <?= e($r->proxied ? 'checked' : '') ?>><span></span></label></div>
                  <div class="col-2 col-md-6"><button class="btn btn-accent btn-sm btn-block">Save</button></div>
                </div>
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

<?php } else { ?>
<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-chart"/></svg> Zone Traffic (7d)</h2></div>
  <div class="card-body">
    <?php if (!nh_count($analytics)) { ?><p class="hint center">No analytics data.</p><?php } else { ?>
    <?php $maxReq = max(array_map(fn($a) => $a->requests ?: 1, (array) $analytics));
      $tot = (object) [ 'requests' => 0, 'pageViews' => 0, 'visitors' => 0 ];
      foreach ($analytics as $a) { $tot->requests += $a->requests; $tot->pageViews += $a->pageViews; $tot->visitors += $a->visitors; }; ?>
    <div class="cf-chart">
      <?php foreach ($analytics as $a) { ?>
      <div class="cf-bar-col" title="<?= e($a->date) ?>: <?= e($a->requests) ?> requests">
        <div class="cf-bar" style="height: <?= e(max(4, round($a->requests / $maxReq * 100))) ?>%"></div>
        <span class="cf-bar-label"><?= e(nh_slice($a->date, 5)) ?></span>
      </div>
      <?php } ?>
    </div>
    <div class="row cf-totals">
      <div class="col-3 col-md-6"><b><?= e(nh_num($tot->requests)) ?></b><span>Requests</span></div>
      <div class="col-3 col-md-6"><b><?= e(nh_num($tot->pageViews)) ?></b><span>Page Views</span></div>
      <div class="col-3 col-md-6"><b><?= e(nh_num($tot->visitors)) ?></b><span>Visitors</span></div>
    </div>
    <?php } ?>
  </div>
</div>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>


<!-- EJS2PHP notes: unconverted Math -->
