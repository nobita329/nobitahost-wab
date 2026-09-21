<?php
/**
 * Ported from views/pages/user/links.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-link"/></svg> Links</h2></div>
      <div class="card-body content-area"><?= $page->content ?></div>
    </div>
  </div>
</div>

<?php if ($isAdmin) { ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-pencil"/></svg> Manage Links</h2>
        <button class="btn btn-accent btn-sm" type="button" id="linkAddBtn"><svg class="ic"><use href="#i-plus"/></svg> Add Link</button>
      </div>
      <div class="card-body">
        <?php if ($query && $query->saved) { ?><div class="alert alert-success">Link saved.</div><?php } ?>
        <?php if ($query && $query->deleted) { ?><div class="alert alert-success">Link deleted.</div><?php } ?>
        <?php if ($query && $query->error === 'title') { ?><div class="alert alert-error">Title is required.</div><?php } ?>
        <form method="POST" action="/links/add" class="form" id="linkAddForm" hidden>
          <div class="form-row">
            <div class="field"><label>Title</label><input type="text" name="title" required placeholder="e.g. GitHub"></div>
            <div class="field"><label>URL</label><input type="url" name="url" placeholder="https://…"></div>
          </div>
          <div class="field"><label>3D Icon <small>(pick any)</small></label>
            <input type="hidden" name="icon" id="linkIcon" value="🔗">
            <div class="icon-picker" data-target="linkIcon">
              <?php foreach ($icons as $ic) { ?>
              <button type="button" class="icon-opt" data-icon="<?= e($ic) ?>"><?= e($ic) ?></button>
              <?php } ?>
            </div>
          </div>
          <button class="btn btn-accent" type="submit">Save Link</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-link"/></svg> Quick Access</h2></div>
      <div class="card-body">
        <div class="link-grid">
          <?php foreach (($links ?: []) as $l) { ?>
          <div class="link-card-wrap">
            <a class="link-card" href="<?= e($l->url) ?>" target="_blank" rel="noopener">
              <div class="link-tile"><?= e($l->icon) ?></div>
              <div class="link-meta">
                <h3><?= e($l->title) ?></h3>
                <span class="link-url"><?= e($l->url ?: '#') ?></span>
              </div>
              <svg class="ic link-arrow"><use href="#i-chevron-right"/></svg>
            </a>
            <?php if ($isAdmin) { ?>
            <div class="link-actions">
              <button class="icon-btn" type="button" data-toggle-edit="<?= e($l->id) ?>" title="Edit"><svg class="ic"><use href="#i-pencil"/></svg></button>
              <form method="POST" action="/links/<?= e($l->id) ?>/delete" onsubmit="return confirm('Delete this link?')">
                <button class="icon-btn danger" type="submit" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
              </form>
            </div>
            <form method="POST" action="/links/<?= e($l->id) ?>/edit" class="link-edit" id="linkEdit-<?= e($l->id) ?>" hidden>
              <div class="form-row">
                <div class="field"><label>Title</label><input type="text" name="title" value="<?= e($l->title) ?>"></div>
                <div class="field"><label>URL</label><input type="url" name="url" value="<?= e($l->url) ?>"></div>
              </div>
              <div class="field"><label>3D Icon</label>
                <input type="hidden" name="icon" id="linkIcon-<?= e($l->id) ?>" value="<?= e($l->icon) ?>">
                <div class="icon-picker" data-target="linkIcon-<?= e($l->id) ?>">
                  <?php foreach ($icons as $ic) { ?>
                  <button type="button" class="icon-opt" data-icon="<?= e($ic) ?>"><?= e($ic) ?></button>
                  <?php } ?>
                </div>
              </div>
              <button class="btn btn-accent btn-sm" type="submit">Save</button>
              <button class="btn btn-ghost btn-sm" type="button" data-toggle-edit="<?= e($l->id) ?>">Cancel</button>
            </form>
            <?php } ?>
          </div>
          <?php } ?>
        </div>
        <?php if (!$links ?: !nh_count($links)) { ?>
        <div class="empty">No links yet. <?php if ($isAdmin) { ?>Click <b>Add Link</b> above to create one.<?php } else { ?>Check back soon.<?php } ?></div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

