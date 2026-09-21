<?php
/**
 * Ported from views/pages/user/projects.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-folder"/></svg> Projects</h2></div>
      <div class="card-body content-area"><?= $page->content ?></div>
    </div>
  </div>
</div>

<?php if ($isAdmin) { ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-pencil"/></svg> Manage Projects</h2>
        <button class="btn btn-accent btn-sm" type="button" id="projectAddBtn"><svg class="ic"><use href="#i-plus"/></svg> Add Project</button>
      </div>
      <div class="card-body">
        <?php if ($query->saved) { ?><div class="alert alert-success">Project saved.</div><?php } ?>
        <?php if ($query->deleted) { ?><div class="alert alert-success">Project deleted.</div><?php } ?>
        <?php if ($query->error === 'name') { ?><div class="alert alert-error">Project name is required.</div><?php } ?>
        <form method="POST" action="/projects/add" class="form" id="projectAddForm" hidden>
          <div class="form-row">
            <div class="field"><label>Project Name</label><input type="text" name="name" required placeholder="e.g. My Website"></div>
            <div class="field"><label>Link (URL)</label><input type="url" name="url" placeholder="https://…"></div>
          </div>
          <div class="form-row">
            <div class="field"><label>Description</label><input type="text" name="description" placeholder="Short description"></div>
            <div class="field"><label>Button Text</label><input type="text" name="button" value="View Project" placeholder="View Project"></div>
          </div>
          <div class="field"><label>Thumbnail URL <small>(image)</small></label><input type="url" name="thumbnail" placeholder="https://…/image.png"></div>
          <div class="field"><label>HTML Content <small>(rendered in Live Preview)</small></label>
            <textarea name="html" rows="10" class="mono" placeholder="<h1>Hello</h1><p>This shows in the preview modal.</p>"></textarea>
          </div>
          <button class="btn btn-accent" type="submit">Save Project</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<div class="row">
  <?php foreach (($projects ?: []) as $p) { ?>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="project-card-wrap">
      <div class="card project-card">
        <div class="project-thumb">
          <?php if ($p->thumbnail) { ?>
            <img src="<?= e($p->thumbnail) ?>" alt="<?= e($p->name) ?>" loading="lazy">
          <?php } else { ?>
            <div class="project-thumb-ph"><svg class="ic"><use href="#i-folder"/></svg></div>
          <?php } ?>
        </div>
        <div class="project-body">
          <h3><?= e($p->name) ?></h3>
          <?php if ($p->description) { ?><p class="project-desc"><?= e($p->description) ?></p><?php } ?>
          <div class="project-actions">
            <button type="button" class="btn btn-ghost btn-sm project-preview" <?= e($p->html ? '' : 'disabled') ?>><svg class="ic"><use href="#i-eye"/></svg> Live Preview</button>
            <?php if ($p->url) { ?><a class="btn btn-accent btn-sm" href="<?= e($p->url) ?>" target="_blank" rel="noopener"><?= e($p->button ?: 'View Project') ?></a><?php } ?>
          </div>
          <?php if ($isAdmin) { ?>
          <div class="link-actions">
            <button class="icon-btn" type="button" data-toggle-edit="<?= e($p->id) ?>" title="Edit"><svg class="ic"><use href="#i-pencil"/></svg></button>
            <form method="POST" action="/projects/<?= e($p->id) ?>/delete" onsubmit="return confirm('Delete this project?')">
              <button class="icon-btn danger" type="submit" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
            </form>
          </div>
          <?php } ?>
        </div>
      </div>
      <?php if ($isAdmin) { ?>
      <form method="POST" action="/projects/<?= e($p->id) ?>/edit" class="link-edit" id="projectEdit-<?= e($p->id) ?>" hidden>
        <div class="field"><label>Name</label><input type="text" name="name" value="<?= e($p->name) ?>"></div>
        <div class="form-row">
          <div class="field"><label>Link</label><input type="url" name="url" value="<?= e($p->url) ?>"></div>
          <div class="field"><label>Button Text</label><input type="text" name="button" value="<?= e($p->button) ?>"></div>
        </div>
        <div class="field"><label>Description</label><input type="text" name="description" value="<?= e($p->description) ?>"></div>
        <div class="field"><label>Thumbnail URL</label><input type="url" name="thumbnail" value="<?= e($p->thumbnail) ?>"></div>
        <div class="field"><label>HTML Content</label><textarea name="html" rows="8" class="mono"><?= e($p->html) ?></textarea></div>
        <button class="btn btn-accent btn-sm" type="submit">Save</button>
        <button class="btn btn-ghost btn-sm" type="button" data-toggle-edit="<?= e($p->id) ?>">Cancel</button>
      </form>
      <?php } ?>
      <textarea class="project-html-src" hidden><?= e($p->html) ?></textarea>
    </div>
  </div>
  <?php } ?>
</div>

<?php if (!$projects ?: !nh_count($projects)) { ?>
<div class="card"><div class="empty">No projects yet. <?php if ($isAdmin) { ?>Click <b>Add Project</b> above to create one.<?php } else { ?>Check back soon.<?php } ?></div></div>
<?php } ?>

<div class="preview-overlay" id="previewOverlay" hidden>
  <div class="preview-box">
    <div class="preview-head">
      <h3 id="previewTitle">Preview</h3>
      <span class="preview-hint">↔ Drag bottom-right corner to resize</span>
      <button class="icon-btn" type="button" id="previewClose" title="Close"><svg class="ic"><use href="#i-x"/></svg></button>
    </div>
    <div class="preview-stage">
      <div class="preview-frame-wrap" id="previewWrap">
        <iframe id="previewFrame" class="preview-frame" title="Project preview"></iframe>
        <span class="resize-grip" id="previewResize" title="Drag to resize width"></span>
      </div>
    </div>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

