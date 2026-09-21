<?php
/**
 * Ported from views/pages/admin/cf-domains.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php /* EJS2PHP: layout include 'partials/cf-head' handled by the PHP layout */ ?>


<?php if ($tab === 'management') { ?>
<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-link"/></svg> All Domains (<?= e(nh_count($zones)) ?>)</h2></div>
  <div class="card-body">
    <?php if (!nh_count($zones)) { ?><p class="hint center">No zones visible to this token.</p><?php } else { ?>
    <div class="table-card">
      <table>
        <thead><tr><th>Domain</th><th>Status</th><th>Plan</th><th>Since</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($zones as $z) { ?>
          <tr>
            <td><b><?= e($z->name) ?></b></td>
            <td><span class="badge <?= e($z->status === 'active' ? 'green' : '') ?>"><?= e($z->status) ?></span></td>
            <td><?= e(($z->plan && $z->plan->name) ?: 'Free') ?></td>
            <td><?= e(nh_slice(($z->created_on ?: ''), 0, 10)) ?></td>
            <td class="ta-r">
              <form method="POST" action="/admin/cloudflare/switch-zone" class="inline">
                <input type="hidden" name="zoneId" value="<?= e($z->id) ?>">
                <button class="btn btn-sm" title="Set as active zone">Use</button>
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
<p class="hint">Traffic for the last 7 days per domain (top <?= e(nh_count($stats) ?: 0) ?> by requests).</p>
<div class="card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-chart"/></svg> Domain Traffic (7d)</h2></div>
  <div class="card-body">
    <?php if (!nh_count($stats)) { ?><p class="hint center">No analytics data available.</p><?php } else { ?>
    <div class="table-card">
      <table>
        <thead><tr><th>Domain</th><th>Status</th><th>Requests</th><th>Page Views</th><th>Visitors</th><th>Threats</th><th>Bandwidth</th></tr></thead>
        <tbody>
          <?php foreach ($stats as $s) { ?>
          <tr>
            <td><b><?= e($s->name) ?></b></td>
            <td><span class="badge <?= e($s->status === 'active' ? 'green' : '') ?>"><?= e($s->status) ?></span></td>
            <td><?= e(nh_num($s->requests)) ?></td>
            <td><?= e(nh_num($s->pageViews)) ?></td>
            <td><?= e(nh_num($s->visitors)) ?></td>
            <td><?= e(nh_num($s->threats)) ?></td>
            <td><?= e(number_format((float) ($s->bytes / 1073741824), 2)) ?> GB</td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
  </div>
</div>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

