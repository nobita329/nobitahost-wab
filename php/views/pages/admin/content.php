<?php
/**
 * Ported from views/pages/admin/content.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($editing) { ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-pencil"/></svg> Edit Page · /<?= e($editing->slug) ?></h2>
        <div class="head-actions">
          <a class="btn btn-ghost btn-sm" href="<?= e(!in_array($editing->slug, $contentSlugs, true) ? '/page/' . $editing->slug : '/' . $editing->slug) ?>" target="_blank"><svg class="ic"><use href="#i-eye"/></svg> View Page</a>
          <a class="btn btn-ghost btn-sm" href="/admin/settings/content">← Back to list</a>
        </div>
      </div>
      <div class="card-body">
        <?php if ($query->saved ?: $query->created) { ?><div class="alert alert-success"><?= e($query->created ? 'Page created.' : 'Page saved.') ?></div><?php } ?>
        <form method="POST" action="/admin/settings/content/<?= e($editing->slug) ?>" class="form">
          <div class="form-row">
            <div class="field"><label>Page Title</label><input type="text" name="title" value="<?= e($editing->title) ?>" required></div>
            <div class="field"><label>Icon <small>(emoji)</small></label><input type="text" name="icon" value="<?= e($editing->icon ?: '📄') ?>" placeholder="📄"></div>
          </div>
          <div class="field"><label>Sidebar</label>
            <div class="switch-row" style="padding: 6px 0">
              <div>
                <b>Show in sidebar</b>
                <small>Adds the page to the navigation</small>
              </div>
              <label class="switch"><input type="checkbox" name="in_nav" <?= e($editing->in_nav ? 'checked' : '') ?>><span></span></label>
            </div>
          </div>
          <div class="field"><label>Content <small>(HTML allowed)</small></label>
            <textarea name="content" rows="18" class="mono"><?= e($editing->content) ?></textarea>
          </div>
          <button class="btn btn-accent" type="submit">Save Page</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php } else { ?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-plus"/></svg> Create New Custom Page</h2></div>
      <div class="card-body">
        <?php if ($query->error === 'create') { ?><div class="alert alert-error">Title and slug are required.</div><?php } ?>
        <?php if ($query->error === 'exists') { ?><div class="alert alert-error">A page with that slug already exists.</div><?php } ?>
        <?php if ($query->error === 'system') { ?><div class="alert alert-error">System pages cannot be deleted.</div><?php } ?>
        <?php if ($query->deleted) { ?><div class="alert alert-success">Custom page deleted.</div><?php } ?>
        <form method="POST" action="/admin/settings/content/create" class="form">
          <div class="form-row">
            <div class="field"><label>Page Title</label><input type="text" name="title" required placeholder="e.g. FAQ"></div>
            <div class="field"><label>Slug <small>(URL: /page/slug)</small></label><input type="text" name="slug" required pattern="[a-z0-9-]+" placeholder="faq"></div>
          </div>
          <div class="form-row">
            <div class="field"><label>Icon <small>(emoji)</small></label><input type="text" name="icon" value="📄" placeholder="📄"></div>
            <div class="field">
              <label>Sidebar</label>
              <div class="switch-row" style="padding: 6px 0">
                <div>
                  <b>Show in sidebar</b>
                  <small>Adds the page to the navigation</small>
                </div>
                <label class="switch"><input type="checkbox" name="in_nav" checked><span></span></label>
              </div>
            </div>
          </div>
          <div class="field"><label>Content <small>(HTML allowed)</small></label>
            <textarea name="content" rows="10" class="mono" placeholder="<h1>My Page</h1><p>Custom HTML content…</p>"></textarea>
          </div>
          <button class="btn btn-accent" type="submit">Create Page</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card table-card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-file"/></svg> Content Pages</h2>
    <span class="hint">Click edit to change what users see on each page.</span>
  </div>
  <table>
    <thead><tr><th>Page</th><th>Title</th><th>Type</th><th>Updated</th><th class="ta-r">Actions</th></tr></thead>
    <tbody>
      <?php foreach ($pages as $p) { ?>
      <tr>
        <td><span class="badge">/<?= e($p->slug) ?></span></td>
        <td><b><?= e($p->icon) ?> <?= e($p->title) ?></b></td>
        <td><?php if (!in_array($p->slug, $contentSlugs, true)) { ?><span class="badge badge-accent">Custom</span><?php } else { ?><span class="badge">System</span><?php } ?></td>
        <td><?= e($p->updated_at ? nh_slice(nh_replace($p->updated_at, 'T', ' '), 0, 16) : '—') ?></td>
        <td class="ta-r">
          <a class="btn btn-ghost btn-sm" href="/admin/settings/content?edit=<?= e($p->slug) ?>"><svg class="ic"><use href="#i-pencil"/></svg> Edit</a>
          <a class="btn btn-ghost btn-sm" href="<?= e(!in_array($p->slug, $contentSlugs, true) ? '/page/' . $p->slug : '/' . $p->slug) ?>" target="_blank"><svg class="ic"><use href="#i-eye"/></svg> View</a>
          <?php if (!in_array($p->slug, $contentSlugs, true)) { ?>
          <form method="POST" action="/admin/settings/content/<?= e($p->slug) ?>/delete" class="inline" onsubmit="return confirm('Delete this custom page?')">
            <button class="btn btn-danger btn-sm" type="submit"><svg class="ic"><use href="#i-trash"/></svg> Delete</button>
          </form>
          <?php } ?>
        </td>
      </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

