<?php
/**
 * Ported from views/pages/admin/activity.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="card table-card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-activity"/></svg> Full Activity Log</h2></div>
  <table>
    <thead><tr><th>User</th><th>Action</th><th>IP Address</th><th>When</th></tr></thead>
    <tbody>
      <?php foreach (($logs ?: []) as $l) { ?>
      <tr>
        <td><b><?= e($l->username) ?></b></td>
        <td><span class="badge <?= e((int) strpos((string) $l->action, 'ogged in') >= 0 ? 'green' : '') ?>"><?= e($l->action) ?></span></td>
        <td><code><?= e($l->ip ?: '—') ?></code></td>
        <td><?= e(nh_slice(nh_replace($l->created_at, 'T', ' '), 0, 19)) ?></td>
      </tr>
      <?php } ?>
      <?php if (!$logs ?: !nh_count($logs)) { ?>
      <tr><td colspan="4"><div class="empty">No activity recorded.</div></td></tr>
      <?php } ?>
    </tbody>
  </table>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

