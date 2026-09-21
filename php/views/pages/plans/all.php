<?php
/**
 * Ported from views/pages/plans/all.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query && $query->saved) { ?><div class="alert alert-success">Plan saved.</div><?php } ?>
<?php if ($query && $query->deleted) { ?><div class="alert alert-success">Plan deleted.</div><?php } ?>
<?php if ($query && $query->error) { ?><div class="alert alert-error"><?= e($query->error) ?></div><?php } ?>

<div class="card table-card">
  <div class="card-head">
    <h2><svg class="ic"><use href="#i-folder"/></svg> All Plans <small class="badge"><?= e($total) ?></small></h2>
    <div class="head-actions">
      <form method="GET" action="/plans/all" class="search-form">
        <input type="text" name="q" placeholder="Search plans..." value="<?= e($q ?: '') ?>">
      </form>
      <a class="btn btn-accent btn-sm" href="/plans/add"><svg class="ic"><use href="#i-plus"/></svg> Add Plan</a>
    </div>
  </div>
  <div class="card-body">
    <?php if ($catFilter) { ?><div style="margin-bottom:12px"><a class="btn btn-ghost btn-sm" href="/plans/all?q=<?= e($q ?: '') ?>">Clear filter: <?= e($catFilter) ?> x</a></div><?php } ?>
    <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>Plan Name</th>
          <th>Category</th>
          <th>Price</th>
          <th>Billing</th>
          <th>Subscribers</th>
          <th>Status</th>
          <th>Created</th>
          <th class="ta-r">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($plans as $p) { ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:8px;height:8px;border-radius:50%;background:<?= e($p->badge_color ?: 'var(--accent)') ?>"></div>
              <div>
                <b><?= e($p->name) ?></b>
                <?php if ($p->popular) { ?><span class="badge gold" style="margin-left:4px">Popular</span><?php } ?>
              </div>
            </div>
          </td>
          <td><?php if ($p->category_name) { ?><span class="badge"><?= e($p->category_name) ?></span><?php } else { ?><span style="color:var(--muted)">-</span><?php } ?></td>
          <td><b><?= e($settings->plan_currency ?: '$') ?><?= e(number_format((float) $p->price, 2)) ?></b></td>
          <td><span class="badge"><?= e($p->billing) ?></span></td>
          <td><?= e($p->sub_count ?: 0) ?></td>
          <td><span class="badge <?= e($p->status === 'active' ? 'green' : 'red') ?>"><?= e($p->status) ?></span></td>
          <td><small style="color:var(--muted)"><?= e($p->created_at) ?></small></td>
          <td class="ta-r">
            <div class="row-actions">
              <a class="icon-btn" href="/plans/all?view=<?= e($p->id) ?>" title="View"><svg class="ic"><use href="#i-eye"/></svg></a>
              <a class="icon-btn" href="/plans/edit/<?= e($p->id) ?>" title="Edit"><svg class="ic"><use href="#i-pencil"/></svg></a>
              <form method="POST" action="/plans/<?= e($p->id) ?>/delete" onsubmit="return confirm('Delete this plan?')" class="inline">
                <button class="icon-btn danger" type="submit" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
              </form>
            </div>
          </td>
        </tr>
        <?php } ?>
        <?php if (!nh_count($plans)) { ?>
        <tr><td colspan="8" class="empty">No plans found. <a href="/plans/add">Create one</a>.</td></tr>
        <?php } ?>
      </tbody>
    </table>
    </div>
    <?php if ($totalPages > 1) { ?>
    <div style="display:flex;gap:8px;justify-content:center;margin-top:16px">
      <?php if ($page > 1) { ?><a class="btn btn-ghost btn-sm" href="/plans/all?page=<?= e($page - 1) ?>&q=<?= e($q ?: '') ?>">Prev</a><?php } ?>
      <span style="line-height:32px;color:var(--muted)">Page <?= e($page) ?> of <?= e($totalPages) ?></span>
      <?php if ($page < $totalPages) { ?><a class="btn btn-ghost btn-sm" href="/plans/all?page=<?= e($page + 1) ?>&q=<?= e($q ?: '') ?>">Next</a><?php } ?>
    </div>
    <?php } ?>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

