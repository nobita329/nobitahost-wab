<?php
/**
 * Ported from views/pages/user/about.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($about->enabled) { ?>
<div class="obs-about">
  <div class="card obs-hero">
    <div class="obs-hero-inner">
      <div class="obs-hero-text">
        <h1><?= e($about->hero->title) ?></h1>
        <?php if ($about->hero->subtitle) { ?><p><?= e($about->hero->subtitle) ?></p><?php } ?>
        <div class="obs-hero-ctas">
          <?php if ($about->hero->cta1_label && $about->hero->cta1_url) { ?><a class="btn btn-accent" href="<?= e($about->hero->cta1_url) ?>"><?= e($about->hero->cta1_label) ?></a><?php } ?>
          <?php if ($about->hero->cta2_label && $about->hero->cta2_url) { ?><a class="btn" href="<?= e($about->hero->cta2_url) ?>"><?= e($about->hero->cta2_label) ?></a><?php } ?>
        </div>
      </div>
      <?php if ($about->hero->image_url) { ?>
      <div class="obs-hero-art"><img src="<?= e($about->hero->image_url) ?>" alt="<?= e($about->hero->title) ?>"></div>
      <?php } ?>
    </div>
  </div>

  <?php if ($about->stats_enabled && nh_count($about->stats)) { ?>
  <div class="row obs-stats">
    <?php foreach ($about->stats as $s) { ?>
    <div class="col-3 col-md-6 col-sm-12">
      <div class="card stat-card"><div class="stat-meta"><b><?= e($s->value) ?></b><span><?= e($s->label) ?></span></div></div>
    </div>
    <?php } ?>
  </div>
  <?php } ?>

  <?php if ($about->story_enabled && nh_count($about->story_paragraphs)) { ?>
  <div class="card obs-section">
    <div class="card-head"><h2><svg class="ic"><use href="#i-book"/></svg> <?= e($about->story_title) ?></h2></div>
    <div class="card-body obs-story">
      <?php foreach ($about->story_paragraphs as $p) { ?><p><?= e($p) ?></p><?php } ?>
    </div>
  </div>
  <?php } ?>

  <?php if ($about->values_enabled && nh_count($about->values_items)) { ?>
  <div class="card obs-section">
    <div class="card-head"><h2><svg class="ic"><use href="#i-heart"/></svg> <?= e($about->values_title) ?></h2></div>
    <div class="card-body">
      <div class="row">
        <?php foreach ($about->values_items as $v) { ?>
        <div class="col-6 col-md-6 col-sm-12">
          <div class="obs-value-card">
            <b><?= e($v->title) ?></b>
            <p><?= e($v->text) ?></p>
          </div>
        </div>
        <?php } ?>
      </div>
    </div>
  </div>
  <?php } ?>

  <?php if ($about->team_enabled && nh_count($about->team_members)) { ?>
  <div class="card obs-section">
    <div class="card-head"><h2><svg class="ic"><use href="#i-users"/></svg> <?= e($about->team_title) ?></h2></div>
    <div class="card-body">
      <div class="row">
        <?php foreach ($about->team_members as $m) { ?>
        <div class="col-4 col-md-6 col-sm-12">
          <div class="obs-team-card">
            <?php if ($m->image) { ?><img src="<?= e($m->image) ?>" alt="<?= e($m->name) ?>"><?php } else { ?><span class="avatar xl"><svg class="ic"><use href="#i-user"/></svg></span><?php } ?>
            <b><?= e($m->name) ?></b>
            <span class="hint"><?= e($m->role) ?></span>
          </div>
        </div>
        <?php } ?>
      </div>
    </div>
  </div>
  <?php } ?>

  <?php if ($about->timeline_enabled && nh_count($about->timeline_items)) { ?>
  <div class="card obs-section">
    <div class="card-head"><h2><svg class="ic"><use href="#i-clock"/></svg> <?= e($about->timeline_title) ?></h2></div>
    <div class="card-body">
      <div class="obs-timeline">
        <?php foreach ($about->timeline_items as $t) { ?>
        <div class="obs-tl-item">
          <span class="obs-tl-year"><?= e($t->year) ?></span>
          <div class="obs-tl-body"><b><?= e($t->title) ?></b><p><?= e($t->text) ?></p></div>
        </div>
        <?php } ?>
      </div>
    </div>
  </div>
  <?php } ?>

  <?php if ($about->gallery_enabled && nh_count($about->gallery_images)) { ?>
  <div class="card obs-section">
    <div class="card-head"><h2><svg class="ic"><use href="#i-image"/></svg> <?= e($about->gallery_title) ?></h2></div>
    <div class="card-body">
      <div class="obs-gallery">
        <?php foreach ($about->gallery_images as $g) { ?>
          <?php if ($g->url) { ?><img src="<?= e($g->url) ?>" alt="<?= e($g->alt ?: 'Gallery image') ?>" loading="lazy"><?php } ?>
        <?php } ?>
      </div>
    </div>
  </div>
  <?php } ?>
</div>
<?php } else { ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-info"/></svg> About</h2>
        <?php if ($isAdmin) { ?>
        <button class="btn btn-accent btn-sm" type="button" onclick="document.getElementById('aboutEdit').hidden = !document.getElementById('aboutEdit').hidden">✏️ Edit About</button>
        <?php } ?>
      </div>
      <div class="card-body content-area"><?= $page->content ?></div>
      <?php if ($isAdmin) { ?>
      <div class="card-body" id="aboutEdit" hidden>
        <?php if ($query->saved) { ?><div class="alert alert-success">About page saved.</div><?php } ?>
        <form method="POST" action="/about/update" class="form">
          <div class="field"><label>About Content <small>(HTML allowed)</small></label>
            <textarea name="content" rows="16" class="mono"><?= e($page->content) ?></textarea>
          </div>
          <button class="btn btn-accent" type="submit">Save About</button>
        </form>
      </div>
      <?php } ?>
    </div>
  </div>
</div>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

