<?php
/**
 * Ported from views/pages/plans/subscribers.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query && $query->saved) { ?><div class="alert alert-success">Subscriber updated.</div><?php } ?>
<?php if ($query && $query->deleted) { ?><div class="alert alert-success">Subscriber removed.</div><?php } ?>

<div class="card table-card">
  <div class="card-head">
    <h2><svg class="ic"><use href="#i-users"/></svg> Subscribers <small class="badge"><?= e($total) ?></small></h2>
    <div class="head-actions">
      <form method="GET" action="/plans/subscribers" class="search-form">
        <input type="text" name="q" placeholder="Search subscribers..." value="<?= e($q ?: '') ?>">
      </form>
    </div>
  </div>
  <div class="card-body">
    <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Plan</th>
          <th>Start Date</th>
          <th>Expiry Date</th>
          <th>Status</th>
          <th class="ta-r">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subscribers as $s) { ?>
        <tr>
          <td><b><?= e($s->username ?: 'User #' . $s->user_id) ?></b></td>
          <td><span class="badge"><?= e($s->plan_name) ?></span></td>
          <td><small><?= e($s->start_date) ?></small></td>
          <td><small><?= e($s->expiry_date ?: 'Lifetime') ?></small></td>
          <td><span class="badge <?= e($s->status === 'active' ? 'green' : ($s->status === 'expired' ? 'red' : 'gold')) ?>"><?= e($s->status) ?></span></td>
          <td class="ta-r">
            <div class="row-actions">
              <form method="POST" action="/plans/subscribers/<?= e($s->id) ?>/status" class="inline">
                <input type="hidden" name="status" value="<?= e($s->status === 'active' ? 'expired' : 'active') ?>">
                <button class="icon-btn warn" type="submit" title="<?= e($s->status === 'active' ? 'Expire' : 'Activate') ?>"><svg class="ic"><use href="#i-shield"/></svg></button>
              </form>
              <form method="POST" action="/plans/subscribers/<?= e($s->id) ?>/delete" onsubmit="return confirm('Remove this subscriber?')" class="inline">
                <button class="icon-btn danger" type="submit" title="Remove"><svg class="ic"><use href="#i-trash"/></svg></button>
              </form>
            </div>
          </td>
        </tr>
        <?php } ?>
        <?php if (!nh_count($subscribers)) { ?>
        <tr><td colspan="6" class="empty">No subscribers yet.</td></tr>
        <?php } ?>
      </tbody>
    </table>
    </div>
    <?php if ($totalPages > 1) { ?>
    <div style="display:flex;gap:8px;justify-content:center;margin-top:16px">
      <?php if ($page > 1) { ?><a class="btn btn-ghost btn-sm" href="/plans/subscribers?page=<?= e($page - 1) ?>&q=<?= e($q ?: '') ?>">Prev</a><?php } ?>
      <span style="line-height:32px;color:var(--muted)">Page <?= e($page) ?> of <?= e($totalPages) ?></span>
      <?php if ($page < $totalPages) { ?><a class="btn btn-ghost btn-sm" href="/plans/subscribers?page=<?= e($page + 1) ?>&q=<?= e($q ?: '') ?>">Next</a><?php } ?>
    </div>
    <?php } ?>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

