<?php
/**
 * Ported from views/pages/user/custom-page.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><span class="nav-emoji"><?= e($page->icon) ?></span> <?= e($page->title) ?></h2></div>
      <div class="card-body content-area"><?= $page->content ?></div>
    </div>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

