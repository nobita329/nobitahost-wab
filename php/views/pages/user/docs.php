<?php
/**
 * Ported from views/pages/user/docs.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query && $query->saved) { ?><div class="alert alert-success">Document saved.</div><?php } ?>
<?php if ($query && $query->edited) { ?><div class="alert alert-success">Document updated.</div><?php } ?>
<?php if ($query && $query->deleted) { ?><div class="alert alert-success">Document(s) deleted.</div><?php } ?>
<?php if ($query && $query->error === 'title') { ?><div class="alert alert-error">Title is required.</div><?php } ?>

<?php if ($isAdmin) { ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-file"/></svg> Add Document</h2>
        <button class="btn btn-accent btn-sm" type="button" id="docAddBtn"><svg class="ic"><use href="#i-plus"/></svg> New Document</button>
      </div>
      <div class="card-body">
        <form method="POST" action="/docs/add" class="form" id="docAddForm" hidden>
          <div class="form-row">
            <div class="field"><label>Title</label><input type="text" name="title" required placeholder="Document title"></div>
            <div class="field"><label>Category</label><input type="text" name="category" placeholder="e.g. API, Guide, FAQ"></div>
          </div>
          <div class="field"><label>Description</label><input type="text" name="description" placeholder="Short description"></div>
          <div class="field"><label>Content</label><textarea name="content" rows="8" placeholder="Write your document content here..."></textarea></div>
          <div class="form-row">
            <div class="field"><label>Tags</label><input type="text" name="tags" placeholder="Comma separated tags"></div>
            <div class="field"><label>Status</label>
              <select name="status">
                <option value="published">Published</option>
                <option value="draft">Draft</option>
              </select>
            </div>
          </div>
          <button class="btn btn-accent" type="submit">Save Document</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<div class="row">
  <div class="col-12">
    <div class="card table-card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-file"/></svg> All Documents <small class="badge"><?= e($total) ?></small></h2>
        <div class="head-actions">
          <?php if ($isAdmin && nh_count($docs) > 0) { ?>
          <form method="POST" action="/docs/bulk-delete" id="bulkDeleteForm" onsubmit="return confirm('Delete selected documents?')">
            <input type="hidden" name="ids" id="bulkIds">
            <button class="btn btn-danger btn-sm" type="submit" id="bulkDeleteBtn" disabled><svg class="ic"><use href="#i-trash"/></svg> Delete Selected</button>
          </form>
          <?php } ?>
          <form method="GET" action="/docs" class="search-form">
            <input type="text" name="q" placeholder="Search docs..." value="<?= e($q ?: '') ?>">
            <?php if ($cat) { ?><input type="hidden" name="category" value="<?= e($cat) ?>"><?php } ?>
          </form>
        </div>
      </div>
      <div class="card-body">
        <?php if ($categories && nh_count($categories) > 0) { ?>
        <div class="filter-row" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
          <a class="btn btn-sm <?= e(!$cat ? 'btn-accent' : 'btn-ghost') ?>" href="/docs?q=<?= e($q ?: '') ?>">All</a>
          <?php foreach ($categories as $c) { ?>
          <a class="btn btn-sm <?= e($cat === $c ? 'btn-accent' : 'btn-ghost') ?>" href="/docs?q=<?= e($q ?: '') ?>&category=<?= e(rawurlencode($c)) ?>"><?= e($c) ?></a>
          <?php } ?>
        </div>
        <?php } ?>

        <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <?php if ($isAdmin) { ?><th style="width:40px"><input type="checkbox" id="checkAll"></th><?php } ?>
              <th>Title</th>
              <th>Category</th>
              <th>Author</th>
              <th>Status</th>
              <th>Updated</th>
              <th class="ta-r">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($docs as $d) { ?>
            <tr>
              <?php if ($isAdmin) { ?><td><input type="checkbox" class="doc-check" value="<?= e($d->id) ?>"></td><?php } ?>
              <td>
                <div>
                  <b><?= e($d->title) ?></b>
                  <?php if ($d->description) { ?><br><small style="color:var(--muted)"><?= e(nh_slice($d->description, 0, 80)) ?><?= e(nh_count($d->description) > 80 ? '...' : '') ?></small><?php } ?>
                </div>
              </td>
              <td><?php if ($d->category) { ?><span class="badge"><?= e($d->category) ?></span><?php } else { ?><span style="color:var(--muted)">-</span><?php } ?></td>
              <td><?= e($d->author ?: '-') ?></td>
              <td><span class="badge <?= e($d->status === 'published' ? 'green' : 'gold') ?>"><?= e($d->status) ?></span></td>
              <td><small style="color:var(--muted)"><?= e($d->updated_at) ?></small></td>
              <td class="ta-r">
                <div class="row-actions">
                  <a class="icon-btn" href="/docs/view/<?= e($d->id) ?>" title="View"><svg class="ic"><use href="#i-eye"/></svg></a>
                  <?php if ($isAdmin) { ?>
                  <button class="icon-btn" type="button" data-toggle-edit="doc<?= e($d->id) ?>" title="Edit"><svg class="ic"><use href="#i-pencil"/></svg></button>
                  <form method="POST" action="/docs/<?= e($d->id) ?>/delete" onsubmit="return confirm('Delete this document?')" class="inline">
                    <button class="icon-btn danger" type="submit" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
                  </form>
                  <?php } ?>
                </div>
              </td>
            </tr>
            <?php if ($isAdmin) { ?>
            <tr id="docEdit-doc<?= e($d->id) ?>" hidden>
              <td colspan="6" style="padding:12px">
                <form method="POST" action="/docs/<?= e($d->id) ?>/edit" class="form">
                  <div class="form-row">
                    <div class="field"><label>Title</label><input type="text" name="title" value="<?= e($d->title) ?>"></div>
                    <div class="field"><label>Category</label><input type="text" name="category" value="<?= e($d->category) ?>"></div>
                  </div>
                  <div class="field"><label>Description</label><input type="text" name="description" value="<?= e($d->description) ?>"></div>
                  <div class="field"><label>Content</label><textarea name="content" rows="6"><?= e($d->content) ?></textarea></div>
                  <div class="form-row">
                    <div class="field"><label>Tags</label><input type="text" name="tags" value="<?= e($d->tags) ?>"></div>
                    <div class="field"><label>Status</label>
                      <select name="status">
                        <option value="published" <?= e($d->status === 'published' ? 'selected' : '') ?>>Published</option>
                        <option value="draft" <?= e($d->status === 'draft' ? 'selected' : '') ?>>Draft</option>
                      </select>
                    </div>
                  </div>
                  <button class="btn btn-accent btn-sm" type="submit">Save Changes</button>
                  <button class="btn btn-ghost btn-sm" type="button" data-toggle-edit="doc<?= e($d->id) ?>">Cancel</button>
                </form>
              </td>
            </tr>
            <?php } ?>
            <?php } ?>
            <?php if (!nh_count($docs)) { ?>
            <tr><td colspan="6" class="empty">No documents found. <?php if ($isAdmin) { ?>Click <b>New Document</b> to create one.<?php } else { ?>Check back soon.<?php } ?></td></tr>
            <?php } ?>
          </tbody>
        </table>
        </div>

        <?php if ($totalPages > 1) { ?>
        <div class="pagination" style="display:flex;gap:8px;justify-content:center;margin-top:16px">
          <?php if ($page > 1) { ?><a class="btn btn-ghost btn-sm" href="/docs?page=<?= e($page - 1) ?>&q=<?= e($q ?: '') ?><?= e($cat ? '&category=' . rawurlencode($cat) : '') ?>">Prev</a><?php } ?>
          <span style="line-height:32px;color:var(--muted)">Page <?= e($page) ?> of <?= e($totalPages) ?></span>
          <?php if ($page < $totalPages) { ?><a class="btn btn-ghost btn-sm" href="/docs?page=<?= e($page + 1) ?>&q=<?= e($q ?: '') ?><?= e($cat ? '&category=' . rawurlencode($cat) : '') ?>">Next</a><?php } ?>
        </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

