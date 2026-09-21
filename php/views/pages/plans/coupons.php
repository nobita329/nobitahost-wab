<?php
/**
 * Ported from views/pages/plans/coupons.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query && $query->saved) { ?><div class="alert alert-success">Coupon saved.</div><?php } ?>
<?php if ($query && $query->deleted) { ?><div class="alert alert-success">Coupon deleted.</div><?php } ?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-plus"/></svg> <?= e($editing ? 'Edit Coupon' : 'Create Coupon') ?></h2>
        <?php if ($editing) { ?><a class="btn btn-ghost btn-sm" href="/plans/coupons">Cancel</a><?php } ?>
      </div>
      <div class="card-body">
        <form method="POST" action="<?= e($editing ? '/plans/coupons/' . $editing->id . '/edit' : '/plans/coupons/add') ?>" class="form">
          <div class="form-row">
            <div class="field"><label>Code *</label><input type="text" name="code" value="<?= e($editing ? $editing->code : '') ?>" required placeholder="e.g. SAVE20" style="text-transform:uppercase"></div>
            <div class="field"><label>Discount Type</label>
              <select name="type">
                <option value="percent" <?= e($editing && $editing->type === 'percent' ? 'selected' : '') ?>>Percent (%)</option>
                <option value="fixed" <?= e($editing && $editing->type === 'fixed' ? 'selected' : '') ?>>Fixed Amount</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="field"><label>Value *</label><input type="number" step="0.01" name="value" value="<?= e($editing ? $editing->value : '') ?>" required placeholder="e.g. 20"></div>
            <div class="field"><label>Max Uses <small>(0 = unlimited)</small></label><input type="number" name="max_uses" value="<?= e($editing ? $editing->max_uses : '0') ?>"></div>
          </div>
          <div class="form-row">
            <div class="field"><label>Min Amount</label><input type="number" step="0.01" name="min_amount" value="<?= e($editing ? $editing->min_amount : '0') ?>" placeholder="0"></div>
            <div class="field"><label>Expiry Date</label><input type="date" name="expiry_date" value="<?= e($editing ? $editing->expiry_date : '') ?>"></div>
          </div>
          <div class="field"><label>Status</label>
            <select name="status">
              <option value="active" <?= e($editing && $editing->status === 'active' ? 'selected' : '') ?>>Active</option>
              <option value="inactive" <?= e($editing && $editing->status === 'inactive' ? 'selected' : '') ?>>Inactive</option>
            </select>
          </div>
          <button class="btn btn-accent" type="submit"><?= e($editing ? 'Save Changes' : 'Create Coupon') ?></button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card table-card" style="margin-top:16px">
  <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> All Coupons</h2></div>
  <div class="card-body">
    <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>Code</th>
          <th>Discount</th>
          <th>Uses</th>
          <th>Min Amount</th>
          <th>Expiry</th>
          <th>Status</th>
          <th class="ta-r">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($coupons as $c) { ?>
        <tr>
          <td><b style="font-family:monospace"><?= e($c->code) ?></b></td>
          <td><span class="badge"><?= e($c->type === 'percent' ? $c->value . '%' : ($settings->plan_currency ?: '$') + number_format((float) $c->value, 2)) ?></span></td>
          <td><?= e($c->used_count) ?><?php if ($c->max_uses > 0) { ?> / <?= e($c->max_uses) ?><?php } ?></td>
          <td><?= e($c->min_amount > 0 ? ($settings->plan_currency ?: '$') + number_format((float) $c->min_amount, 2) : '-') ?></td>
          <td><small><?= e($c->expiry_date ?: 'Never') ?></small></td>
          <td><span class="badge <?= e($c->status === 'active' ? 'green' : 'red') ?>"><?= e($c->status) ?></span></td>
          <td class="ta-r">
            <div class="row-actions">
              <a class="icon-btn" href="/plans/coupons?edit=<?= e($c->id) ?>" title="Edit"><svg class="ic"><use href="#i-pencil"/></svg></a>
              <form method="POST" action="/plans/coupons/<?= e($c->id) ?>/delete" onsubmit="return confirm('Delete this coupon?')" class="inline">
                <button class="icon-btn danger" type="submit" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
              </form>
            </div>
          </td>
        </tr>
        <?php } ?>
        <?php if (!nh_count($coupons)) { ?>
        <tr><td colspan="7" class="empty">No coupons yet. Create one above.</td></tr>
        <?php } ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

