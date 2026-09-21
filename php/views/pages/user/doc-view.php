<?php
/**
 * Ported from views/pages/user/doc-view.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-file"/></svg> <?= e($doc->title) ?></h2>
        <div class="head-actions">
          <a class="btn btn-ghost btn-sm" href="/docs"><svg class="ic"><use href="#i-chevron-left"/></svg> Back</a>
          <?php if (isset($isAdmin) && $isAdmin) { ?>
          <button class="btn btn-accent btn-sm" type="button" id="docEditBtn"><svg class="ic"><use href="#i-pencil"/></svg> Edit</button>
          <?php } ?>
        </div>
      </div>
      <div class="card-body">
        <?php if ($query && $query->saved) { ?><div class="alert alert-success">Document updated.</div><?php } ?>

        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
          <?php if ($doc->category) { ?><span class="badge"><?= e($doc->category) ?></span><?php } ?>
          <span class="badge <?= e($doc->status === 'published' ? 'green' : 'gold') ?>"><?= e($doc->status) ?></span>
          <?php if ($doc->tags) { foreach (explode(',', (string) $doc->tags) as $t) { $t = trim((string) $t); if ($t) { ?><span class="badge"><?= e($t) ?></span><?php } }; } ?>
          <small style="color:var(--muted)">by <?= e($doc->author ?: 'Unknown') ?> &middot; <?= e($doc->updated_at) ?></small>
        </div>

        <?php if ($doc->description) { ?>
        <p style="color:var(--muted);margin-bottom:16px"><?= e($doc->description) ?></p>
        <?php } ?>

        <div class="content-area"><?= nh_replace($doc->content, '/\n/g', '<br>') ?></div>
      </div>
    </div>
  </div>
</div>

<?php if (isset($isAdmin) && $isAdmin) { ?>
<div class="row" id="docEditSection" hidden>
  <div class="col-12">
    <div class="card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-pencil"/></svg> Edit Document</h2>
      </div>
      <div class="card-body">
        <form method="POST" action="/docs/<?= e($doc->id) ?>/edit" class="form">
          <div class="form-row">
            <div class="field"><label>Title</label><input type="text" name="title" value="<?= e($doc->title) ?>"></div>
            <div class="field"><label>Category</label><input type="text" name="category" value="<?= e($doc->category) ?>"></div>
          </div>
          <div class="field"><label>Description</label><input type="text" name="description" value="<?= e($doc->description) ?>"></div>
          <div class="field"><label>Content</label><textarea name="content" rows="12"><?= e($doc->content) ?></textarea></div>
          <div class="form-row">
            <div class="field"><label>Tags</label><input type="text" name="tags" value="<?= e($doc->tags) ?>" placeholder="Comma separated"></div>
            <div class="field"><label>Status</label>
              <select name="status">
                <option value="published" <?= e($doc->status === 'published' ? 'selected' : '') ?>>Published</option>
                <option value="draft" <?= e($doc->status === 'draft' ? 'selected' : '') ?>>Draft</option>
              </select>
            </div>
          </div>
          <button class="btn btn-accent" type="submit">Save Changes</button>
          <button class="btn btn-ghost" type="button" id="docCancelEdit">Cancel</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<script>
(function(){
  var btn = document.getElementById('docEditBtn');
  var sec = document.getElementById('docEditSection');
  var cancel = document.getElementById('docCancelEdit');
  if (btn && sec) btn.addEventListener('click', function(){ sec.hidden = false; sec.scrollIntoView({behavior:'smooth'}); });
  if (cancel && sec) cancel.addEventListener('click', function(){ sec.hidden = true; });
})();
</script>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

