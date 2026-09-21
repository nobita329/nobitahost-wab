<?php
/**
 * Ported from views/pages/admin/pageedit-hub.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-3 col-md-6 col-sm-12">
    <a class="card pe-hub-card" href="/admin/pages/about">
      <span class="stat-icon accent1"><svg class="ic"><use href="#i-info"/></svg></span>
      <b>About Page</b>
      <p class="hint">Hero, stats, story, values, team, timeline & gallery sections.</p>
    </a>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <a class="card pe-hub-card" href="/admin/pages/terms">
      <span class="stat-icon accent2"><svg class="ic"><use href="#i-file"/></svg></span>
      <b>Terms & Conditions</b>
      <p class="hint">Numbered sections with markdown blocks and a table of contents.</p>
    </a>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <a class="card pe-hub-card" href="/admin/pages/footer">
      <span class="stat-icon accent3"><svg class="ic"><use href="#i-link"/></svg></span>
      <b>Footer Editor</b>
      <p class="hint">Link columns, legal links and copyright text.</p>
    </a>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <a class="card pe-hub-card" href="/admin/pages/navbar">
      <span class="stat-icon accent4"><svg class="ic"><use href="#i-menu"/></svg></span>
      <b>Navbar Editor</b>
      <p class="hint">Custom sidebar links with visibility control.</p>
    </a>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

