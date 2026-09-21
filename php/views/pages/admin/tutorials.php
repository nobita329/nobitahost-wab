<?php
/**
 * Ported from views/pages/admin/tutorials.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-play"/></svg> Add Tutorial</h2></div>
      <div class="card-body">
        <?php if ($query->saved) { ?><div class="alert alert-success">Tutorial added.</div><?php } ?>
        <?php if ($query->deleted) { ?><div class="alert alert-info">Tutorial deleted.</div><?php } ?>
        <?php if ($query->error) { ?><div class="alert alert-error">Title is required.</div><?php } ?>
        <form method="POST" action="/admin/settings/tutorials" class="form">
          <div class="row">
            <div class="col-4 col-md-6 col-sm-12"><div class="field"><label>Title</label><input type="text" name="title" required></div></div>
            <div class="col-8 col-md-6 col-sm-12"><div class="field"><label>Video URL <small>(YouTube)</small></label><input type="text" name="video_url" placeholder="https://youtube.com/watch?v=..."></div></div>
          </div>
          <div class="row">
            <div class="col-8 col-md-6 col-sm-12"><div class="field"><label>Description</label><input type="text" name="description"></div></div>
            <div class="col-4 col-md-6 col-sm-12"><div class="field"><label>Thumbnail URL <small>(optional)</small></label><input type="text" name="thumbnail"></div></div>
          </div>
          <button class="btn btn-accent" type="submit"><svg class="ic"><use href="#i-plus"/></svg> Add Tutorial</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card table-card">
  <div class="card-head"><h2><svg class="ic"><use href="#i-book"/></svg> Tutorials</h2></div>
  <table>
    <thead><tr><th>Tutorial</th><th>Author</th><th>Added</th><th class="ta-r">Actions</th></tr></thead>
    <tbody>
      <?php foreach (($tutorials ?: []) as $t) { ?>
      <tr>
        <td>
          <div class="t-user">
            <div><b><?= e($t->title) ?></b><small><?= e($t->description) ?></small></div>
          </div>
        </td>
        <td><?= e($t->author ?: '—') ?></td>
        <td><?= e(nh_slice(nh_replace($t->created_at, 'T', ' '), 0, 10)) ?></td>
        <td class="ta-r">
          <div class="row-actions">
            <?php if ($t->video_url) { ?><a class="icon-btn" href="<?= e($t->video_url) ?>" target="_blank" title="Watch"><svg class="ic"><use href="#i-play"/></svg></a><?php } ?>
            <form method="POST" action="/admin/settings/tutorials/<?= e($t->id) ?>/delete" class="inline" data-confirm="Delete this tutorial?">
              <button class="icon-btn danger" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
            </form>
          </div>
        </td>
      </tr>
      <?php } ?>
      <?php if (!$tutorials ?: !nh_count($tutorials)) { ?>
      <tr><td colspan="4"><div class="empty">No tutorials yet.</div></td></tr>
      <?php } ?>
    </tbody>
  </table>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

