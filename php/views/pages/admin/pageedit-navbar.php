<?php
/**
 * Ported from views/pages/admin/pageedit-navbar.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query->saved) { ?><div class="alert alert-success">Navbar links saved.</div><?php } ?>

<form method="POST" action="/admin/pages/navbar">
  <div class="pe-grid">
    <div class="pe-main">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-menu"/></svg> Custom Links</h2></div>
        <div class="card-body">
          <div class="field"><label>Links</label>
            <textarea name="links_text" rows="10" class="mono" placeholder="Status | /status | internal | always&#10;Discord | https://discord.gg/xyz | external | always"><?= e($d->links_text) ?></textarea>
          </div>
          <p class="hint">One link per line: <code>Label | URL | internal/external | always/guest/auth</code>. Custom links appear in the sidebar after the built-in pages. External links open in a new tab.</p>
        </div>
      </div>
    </div>

    <div class="pe-side">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Publish</h2></div>
        <div class="card-body">
          <div class="switch-row"><div><b>Show custom links</b><small>Appends your links to the sidebar navigation</small></div>
            <label class="switch"><input type="checkbox" name="enabled" <?= e($d->enabled ? 'checked' : '') ?>><span></span></label>
          </div>
          <button class="btn btn-accent btn-block" type="submit"><svg class="ic"><use href="#i-shield"/></svg> Save Navbar</button>
          <a class="btn btn-ghost btn-block" href="/admin/pages">← Page Editors</a>
        </div>
      </div>
    </div>
  </div>
</form>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

