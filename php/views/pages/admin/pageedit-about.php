<?php
/**
 * Ported from views/pages/admin/pageedit-about.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query->saved) { ?><div class="alert alert-success">About page saved.</div><?php } ?>

<form method="POST" action="/admin/pages/about">
  <div class="pe-grid">
    <div class="pe-main">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-eye"/></svg> Hero</h2></div>
        <div class="card-body">
          <div class="row">
            <div class="col-6 col-sm-12"><div class="field"><label>Title</label><input type="text" name="hero_title" value="<?= e($d->hero->title) ?>" maxlength="150"></div></div>
            <div class="col-6 col-sm-12"><div class="field"><label>Image URL</label><input type="text" name="hero_image_url" value="<?= e($d->hero->image_url) ?>" placeholder="https://… or /uploads/…"></div></div>
          </div>
          <div class="field"><label>Subtitle</label><textarea name="hero_subtitle" rows="2" maxlength="300"><?= e($d->hero->subtitle) ?></textarea></div>
          <div class="row">
            <div class="col-3 col-md-6 col-sm-12"><div class="field"><label>Button 1 Label</label><input type="text" name="cta1_label" value="<?= e($d->hero->cta1_label) ?>"></div></div>
            <div class="col-3 col-md-6 col-sm-12"><div class="field"><label>Button 1 URL</label><input type="text" name="cta1_url" value="<?= e($d->hero->cta1_url) ?>"></div></div>
            <div class="col-3 col-md-6 col-sm-12"><div class="field"><label>Button 2 Label</label><input type="text" name="cta2_label" value="<?= e($d->hero->cta2_label) ?>"></div></div>
            <div class="col-3 col-md-6 col-sm-12"><div class="field"><label>Button 2 URL</label><input type="text" name="cta2_url" value="<?= e($d->hero->cta2_url) ?>"></div></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-book"/></svg> Story</h2></div>
        <div class="card-body">
          <div class="field"><label>Section Title</label><input type="text" name="story_title" value="<?= e($d->story_title) ?>"></div>
          <div class="field"><label>Paragraphs <small>(one per line)</small></label><textarea name="story_text" rows="5"><?= e($d->story_text) ?></textarea></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-heart"/></svg> Values</h2></div>
        <div class="card-body">
          <div class="field"><label>Section Title</label><input type="text" name="values_title" value="<?= e($d->values_title) ?>"></div>
          <div class="field"><label>Items <small>(one per line — Title | Text)</small></label><textarea name="values_text" rows="5" class="mono"><?= e($d->values_text) ?></textarea></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-users"/></svg> Team</h2></div>
        <div class="card-body">
          <div class="field"><label>Section Title</label><input type="text" name="team_title" value="<?= e($d->team_title) ?>"></div>
          <div class="field"><label>Members <small>(one per line — Name | Role | Image URL)</small></label><textarea name="team_text" rows="5" class="mono"><?= e($d->team_text) ?></textarea></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-clock"/></svg> Timeline</h2></div>
        <div class="card-body">
          <div class="field"><label>Section Title</label><input type="text" name="timeline_title" value="<?= e($d->timeline_title) ?>"></div>
          <div class="field"><label>Milestones <small>(one per line — Year | Title | Text)</small></label><textarea name="timeline_text" rows="4" class="mono"><?= e($d->timeline_text) ?></textarea></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-image"/></svg> Gallery</h2></div>
        <div class="card-body">
          <div class="field"><label>Section Title</label><input type="text" name="gallery_title" value="<?= e($d->gallery_title) ?>"></div>
          <div class="field"><label>Images <small>(one per line — Image URL | Alt text)</small></label><textarea name="gallery_text" rows="4" class="mono"><?= e($d->gallery_text) ?></textarea></div>
        </div>
      </div>
    </div>

    <div class="pe-side">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Publish</h2></div>
        <div class="card-body">
          <div class="switch-row"><div><b>Use structured About page</b><small>Replaces the classic content editor view</small></div>
            <label class="switch"><input type="checkbox" name="enabled" <?= e($d->enabled ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="field"><label>SEO / Browser Title</label><input type="text" name="seo_title" value="<?= e($d->seo_title) ?>"></div>
          <button class="btn btn-accent btn-block" type="submit"><svg class="ic"><use href="#i-shield"/></svg> Save About Page</button>
          <a class="btn btn-block" href="/about">View on site</a>
          <a class="btn btn-ghost btn-block" href="/admin/pages">← Page Editors</a>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-chart"/></svg> Stats</h2></div>
        <div class="card-body">
          <div class="switch-row"><div><b>Show stats row</b></div>
            <label class="switch"><input type="checkbox" name="stats_enabled" <?= e($d->stats_enabled ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="field"><label>Stats <small>(one per line — Value | Label)</small></label><textarea name="stats_text" rows="5" class="mono"><?= e($d->stats_text) ?></textarea></div>
        </div>
      </div>
    </div>
  </div>
</form>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

