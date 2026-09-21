<?php
/**
 * Ported from views/pages/admin/pageedit-footer.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query->saved) { ?><div class="alert alert-success">Footer saved.</div><?php } ?>

<form method="POST" action="/admin/pages/footer">
  <div class="pe-grid">
    <div class="pe-main">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-link"/></svg> Link Columns</h2></div>
        <div class="card-body">
          <div class="field"><label>Columns</label>
            <textarea name="columns_text" rows="12" class="mono" placeholder="# Product&#10;Plans | /plans | internal | always&#10;Blog | /blog | internal | always&#10;&#10;# Community&#10;Discord | https://discord.gg/xyz | external | always"><?= e($d->columns_text) ?></textarea>
          </div>
          <p class="hint">Start each column with <code># Column Title</code>, then one link per line: <code>Label | URL | internal/external | always/guest/auth</code>. Visibility controls who sees the link — everyone, only logged-out guests, or only logged-in users.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Legal Links</h2></div>
        <div class="card-body">
          <div class="field"><label>Legal Links <small>(one per line)</small></label>
            <textarea name="legal_text" rows="4" class="mono"><?= e($d->legal_text) ?></textarea>
          </div>
          <p class="hint">Same format as column links. These appear in the bottom bar next to the copyright.</p>
        </div>
      </div>
    </div>

    <div class="pe-side">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Publish</h2></div>
        <div class="card-body">
          <div class="switch-row"><div><b>Use custom footer</b><small>Shows link columns above the bottom bar</small></div>
            <label class="switch"><input type="checkbox" name="enabled" <?= e($d->enabled ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="field"><label>Copyright Text</label>
            <input type="text" name="copyright" value="<?= e($d->copyright) ?>" placeholder="© <?= e(date('Y')) ?> <?= e($settings->panel_name) ?>. All rights reserved.">
          </div>
          <button class="btn btn-accent btn-block" type="submit"><svg class="ic"><use href="#i-shield"/></svg> Save Footer</button>
          <a class="btn btn-ghost btn-block" href="/admin/pages">← Page Editors</a>
        </div>
      </div>
    </div>
  </div>
</form>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

