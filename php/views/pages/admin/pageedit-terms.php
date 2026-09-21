<?php
/**
 * Ported from views/pages/admin/pageedit-terms.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query->saved) { ?><div class="alert alert-success">Terms page saved.</div><?php } ?>

<form method="POST" action="/admin/pages/terms">
  <div class="pe-grid">
    <div class="pe-main">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-file"/></svg> Sections</h2></div>
        <div class="card-body">
          <div class="field"><label>Sections Content</label>
            <textarea name="sections_text" rows="22" class="mono" placeholder="## intro | Introduction&#10;Your paragraph text here.&#10;&#10;- list item&#10;> callout text&#10;&#10;## fair-use | Fair Use&#10;More content…"><?= e($d->sections_text) ?></textarea>
          </div>
          <p class="hint">Start a section with <code>## section-id | Section Title</code>. Inside a section, markdown works: paragraphs, <code>- lists</code>, <code>&gt; callouts</code>, <code>**bold**</code>, tables and code blocks. Sections are numbered automatically and appear in the table of contents.</p>
        </div>
      </div>
    </div>

    <div class="pe-side">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Publish</h2></div>
        <div class="card-body">
          <div class="switch-row"><div><b>Enable Terms page</b><small>Shows /terms in the footer legal links</small></div>
            <label class="switch"><input type="checkbox" name="enabled" <?= e($d->enabled ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="field"><label>Page Title</label><input type="text" name="title" value="<?= e($d->title) ?>"></div>
          <div class="field"><label>Summary</label><textarea name="summary" rows="2" maxlength="400"><?= e($d->summary) ?></textarea></div>
          <div class="field"><label>Last Updated</label><input type="date" name="last_updated" value="<?= e($d->last_updated) ?>"></div>
          <button class="btn btn-accent btn-block" type="submit"><svg class="ic"><use href="#i-shield"/></svg> Save Terms Page</button>
          <a class="btn btn-block" href="/terms">View on site</a>
          <a class="btn btn-ghost btn-block" href="/admin/pages">← Page Editors</a>
        </div>
      </div>
    </div>
  </div>
</form>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

