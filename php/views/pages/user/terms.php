<?php
/**
 * Ported from views/pages/user/terms.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="obs-terms">
  <div class="card obs-hero obs-terms-hero">
    <h1><?= e($terms->title) ?></h1>
    <?php if ($terms->summary) { ?><p><?= e($terms->summary) ?></p><?php } ?>
    <?php if ($terms->last_updated) { ?><span class="badge"><svg class="ic"><use href="#i-calendar"/></svg> Last updated: <?= e($terms->last_updated) ?></span><?php } ?>
  </div>

  <div class="obs-terms-grid">
    <div class="obs-terms-main">
      <?php foreach (array_values($terms->sections) as $i => $s) { ?>
      <div class="card obs-section obs-term-section" id="<?= e($s->id) ?>">
        <div class="card-head"><h2><span class="term-num"><?= e($i + 1) ?></span> <?= e($s->title) ?></h2></div>
        <div class="card-body md-body"><?= $s->html ?></div>
      </div>
      <?php } ?>
    </div>

    <aside class="blog-side">
      <div class="card blog-toc">
        <div class="card-head"><h2><svg class="ic"><use href="#i-file"/></svg> Table of contents</h2></div>
        <div class="card-body toc-list">
          <?php foreach (array_values($terms->sections) as $i => $s) { ?>
            <a href="#<?= e($s->id) ?>"><span class="toc-num"><?= e($i + 1) ?>.</span> <?= e($s->title) ?></a>
          <?php } ?>
        </div>
      </div>
    </aside>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>


<!-- EJS2PHP notes: arrow function left as-is -->
