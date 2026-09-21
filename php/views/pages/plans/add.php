<?php
/**
 * Ported from views/pages/plans/add.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query && $query->error) { ?><div class="alert alert-error"><?= e($query->error) ?></div><?php } ?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-plus"/></svg> <?= e($editing ? 'Edit Plan' : 'Add New Plan') ?></h2>
        <a class="btn btn-ghost btn-sm" href="/plans/all">Back to Plans</a>
      </div>
      <div class="card-body">
        <form method="POST" action="<?= e($editing ? '/plans/' . $editing->id . '/edit' : '/plans/add') ?>" class="form">
          <div class="form-row">
            <div class="field"><label>Plan Name *</label><input type="text" name="name" value="<?= e($editing ? $editing->name : '') ?>" required placeholder="e.g. Pro Plan"></div>
            <div class="field"><label>Category</label>
              <select name="category_id">
                <option value="">None</option>
                <?php foreach ($categories as $c) { ?>
                <option value="<?= e($c->id) ?>" <?= e($editing && $editing->category_id == $c->id ? 'selected' : '') ?>><?= e($c->name) ?></option>
                <?php } ?>
              </select>
            </div>
          </div>
          <div class="field"><label>Description</label><textarea name="description" rows="3" placeholder="Plan description"><?= e($editing ? $editing->description : '') ?></textarea></div>
          <div class="form-row">
            <div class="field"><label>Price *</label><input type="number" step="0.01" name="price" value="<?= e($editing ? $editing->price : '0') ?>" required placeholder="0.00"></div>
            <div class="field"><label>Billing Cycle</label>
              <select name="billing">
                <option value="monthly" <?= e($editing && $editing->billing === 'monthly' ? 'selected' : '') ?>>Monthly</option>
                <option value="yearly" <?= e($editing && $editing->billing === 'yearly' ? 'selected' : '') ?>>Yearly</option>
                <option value="lifetime" <?= e($editing && $editing->billing === 'lifetime' ? 'selected' : '') ?>>Lifetime</option>
              </select>
            </div>
          </div>
          <div class="field"><label>Features <small>(one per line)</small></label><textarea name="features" rows="6" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"><?= e($editing ? $editing->features : '') ?></textarea></div>
          <div class="form-row">
            <div class="field"><label>Storage Limit</label><input type="text" name="storage_limit" value="<?= e($editing ? $editing->storage_limit : '') ?>" placeholder="e.g. 10GB"></div>
            <div class="field"><label>User Limit</label><input type="number" name="user_limit" value="<?= e($editing ? $editing->user_limit : '0') ?>" placeholder="0 = unlimited"></div>
          </div>
          <div class="form-row">
            <div class="field"><label>API Limit</label><input type="text" name="api_limit" value="<?= e($editing ? $editing->api_limit : '') ?>" placeholder="e.g. 10000/day"></div>
            <div class="field"><label>Trial Days</label><input type="number" name="trial_days" value="<?= e($editing ? $editing->trial_days : '0') ?>" placeholder="0 = no trial"></div>
          </div>
          <div class="form-row">
            <div class="field"><label>Status</label>
              <select name="status">
                <option value="active" <?= e($editing && $editing->status === 'active' ? 'selected' : '') ?>>Active</option>
                <option value="inactive" <?= e($editing && $editing->status === 'inactive' ? 'selected' : '') ?>>Inactive</option>
              </select>
            </div>
            <div class="field"><label>Badge Color</label><input type="color" name="badge_color" value="<?= e($editing ? $editing->badge_color : '#3b82f6') ?>"></div>
          </div>
          <div class="form-row">
            <div class="field"><label>Badge Text</label><input type="text" name="badge" value="<?= e($editing ? $editing->badge : '') ?>" placeholder="e.g. Popular, Premium"></div>
            <div class="field" style="display:flex;align-items:end;padding-bottom:8px">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="popular" value="1" <?= e($editing && $editing->popular ? 'checked' : '') ?>> Mark as Popular
              </label>
            </div>
          </div>
          <button class="btn btn-accent" type="submit"><?= e($editing ? 'Save Changes' : 'Create Plan') ?></button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

