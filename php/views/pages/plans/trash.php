<?php
/**
 * Ported from views/pages/plans/trash.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query && $query->restored) { ?><div class="alert alert-success">Plan restored.</div><?php } ?>
<?php if ($query && $query->permanently) { ?><div class="alert alert-success">Plan permanently deleted.</div><?php } ?>

<div class="card table-card">
  <div class="card-head">
    <h2><svg class="ic"><use href="#i-trash"/></svg> Trash <small class="badge"><?= e(nh_count($plans)) ?></small></h2>
    <a class="btn btn-ghost btn-sm" href="/plans/all">Back to Plans</a>
  </div>
  <div class="card-body">
    <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>Plan Name</th>
          <th>Price</th>
          <th>Billing</th>
          <th>Deleted</th>
          <th class="ta-r">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($plans as $p) { ?>
        <tr>
          <td><b><?= e($p->name) ?></b></td>
          <td><?= e($settings->plan_currency ?: '$') ?><?= e(number_format((float) $p->price, 2)) ?></td>
          <td><span class="badge"><?= e($p->billing) ?></span></td>
          <td><small style="color:var(--muted)"><?= e($p->updated_at) ?></small></td>
          <td class="ta-r">
            <div class="row-actions">
              <form method="POST" action="/plans/<?= e($p->id) ?>/restore" class="inline">
                <button class="icon-btn" type="submit" title="Restore"><svg class="ic"><use href="#i-shield"/></svg></button>
              </form>
              <form method="POST" action="/plans/<?= e($p->id) ?>/permanent-delete" onsubmit="return confirm('Permanently delete? This cannot be undone.')" class="inline">
                <button class="icon-btn danger" type="submit" title="Permanent Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
              </form>
            </div>
          </td>
        </tr>
        <?php } ?>
        <?php if (!nh_count($plans)) { ?>
        <tr><td colspan="5" class="empty">Trash is empty.</td></tr>
        <?php } ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

