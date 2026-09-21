<?php
/**
 * Ported from views/pages/admin/blog.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card"><div class="stat-icon accent1"><svg class="ic"><use href="#i-file"/></svg></div><div class="stat-meta"><b><?= e($stats->total) ?></b><span>Total Posts</span></div></div>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card"><div class="stat-icon accent2"><svg class="ic"><use href="#i-eye"/></svg></div><div class="stat-meta"><b><?= e($stats->published) ?></b><span>Published</span></div></div>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card"><div class="stat-icon accent3"><svg class="ic"><use href="#i-chart"/></svg></div><div class="stat-meta"><b><?= e($stats->views) ?></b><span>Total Views</span></div></div>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card"><div class="stat-icon accent4"><svg class="ic"><use href="#i-heart"/></svg></div><div class="stat-meta"><b><?= e($stats->helpful) ?></b><span>Helpful Votes</span></div></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2><svg class="ic"><use href="#i-book"/></svg> Blog Posts</h2>
    <a class="btn btn-accent btn-sm" href="/admin/blog/create"><svg class="ic"><use href="#i-plus"/></svg> New Post</a>
  </div>
  <div class="card-body">
    <?php if (!nh_count($posts)) { ?>
      <p class="hint center">No posts yet. Create your first blog post!</p>
    <?php } else { ?>
    <div class="table-card">
      <table>
        <thead><tr><th>Title</th><th>Tags</th><th>Status</th><th>Views</th><th>Published</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($posts as $p) { ?>
          <tr>
            <td><b><?= e($p->title) ?></b><br><span class="hint">/blog/<?= e($p->slug) ?></span></td>
            <td><?php foreach (nh_slice($p->tag_list, 0, 3) as $t) { ?><span class="badge"><?= e($t) ?></span> <?php } ?></td>
            <td><span class="badge <?= e($p->is_published ? 'green' : 'red') ?>"><?= e($p->is_published ? 'published' : 'draft') ?></span></td>
            <td><?= e($p->views) ?></td>
            <td class="hint"><?= e(Blog::fmtDate($p->published_at) ?: '—') ?></td>
            <td class="ta-r">
              <div class="row-actions">
                <a class="icon-btn" href="/blog/<?= e($p->slug) ?>" title="View"><svg class="ic"><use href="#i-eye"/></svg></a>
                <a class="icon-btn" href="/admin/blog/<?= e($p->id) ?>/edit" title="Edit"><svg class="ic"><use href="#i-pencil"/></svg></a>
                <form method="POST" action="/admin/blog/<?= e($p->id) ?>/delete" class="inline" data-confirm="Delete this post?">
                  <button class="icon-btn danger" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
                </form>
              </div>
            </td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

