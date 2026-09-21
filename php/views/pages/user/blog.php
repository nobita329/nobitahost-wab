<?php
/**
 * Ported from views/pages/user/blog.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="blog-archive">
  <div class="card blog-head-card">
    <div class="blog-head-row">
      <div>
        <h1 class="grad-text">Blog</h1>
        <p class="hint">Guides, updates and news from <?= e($settings->panel_name) ?>.</p>
      </div>
      <form method="GET" action="/blog" class="blog-controls">
        <?php if ($query->tag) { ?><input type="hidden" name="tag" value="<?= e($query->tag) ?>"><?php } ?>
        <div class="blog-search">
          <svg class="ic"><use href="#i-search"/></svg>
          <input type="search" name="search" value="<?= e($query->search ?: '') ?>" placeholder="Search posts…" onchange="this.form.submit()">
        </div>
        <select name="sort" onchange="this.form.submit()">
          <option value="latest" <?= e(($query->sort ?: 'latest') === 'latest' ? 'selected' : '') ?>>Latest</option>
          <option value="oldest" <?= e($query->sort === 'oldest' ? 'selected' : '') ?>>Oldest</option>
          <option value="updated" <?= e($query->sort === 'updated' ? 'selected' : '') ?>>Updated</option>
          <option value="popular" <?= e($query->sort === 'popular' ? 'selected' : '') ?>>Popular</option>
        </select>
      </form>
    </div>

    <?php if (nh_count($tags)) { ?>
    <div class="blog-chips">
      <a class="chip <?= e(!$query->tag ? 'chip-active' : '') ?>" href="/blog<?= e($query->search ? '?search=' . rawurlencode($query->search) : '') ?>">All</a>
      <?php foreach ($tags as $t) { ?>
        <a class="chip <?= e($query->tag === $t->label ? 'chip-active' : '') ?>" href="/blog?tag=<?= e(rawurlencode($t->label)) ?><?= e($query->search ? '&search=' . rawurlencode($query->search) : '') ?>"><?= e($t->label) ?> <span><?= e($t->count) ?></span></a>
      <?php } ?>
    </div>
    <?php } ?>
  </div>

  <?php if (!nh_count($posts)) { ?>
    <div class="card blog-empty">
      <span class="badge">🔍</span>
      <b><?= e(($query->search ?: $query->tag) ? 'No posts found' : 'No posts have been published yet') ?></b>
      <p class="hint"><?= e(($query->search ?: $query->tag) ? 'Try adjusting your search or filters.' : 'Check back later for new updates.') ?></p>
      <?php if ($query->search ?: $query->tag) { ?><a class="btn btn-sm" href="/blog">Clear filters</a><?php } ?>
    </div>
  <?php } else { ?>
  <div class="row">
    <?php foreach ($posts as $p) {
         $isNew = $p->published_at && (Date.now() - nh_locale_date((string) nh_replace(($p->published_at), ' ', 'T') . 'Z').getTime()) < 7 * 86400000; ?>
    <div class="col-4 col-md-6 col-sm-12">
      <a href="/blog/<?= e($p->slug) ?>" class="blog-post-card">
        <article class="card blog-card-inner">
          <div class="blog-card-media">
            <?php if ($p->cover_image_url) { ?>
              <img src="<?= e($p->cover_image_url) ?>" alt="<?= e($p->title) ?>" loading="lazy" decoding="async">
            <?php } else { ?>
              <div class="blog-card-placeholder"><svg class="ic"><use href="#i-image"/></svg></div>
            <?php } ?>
            <span class="blog-card-arrow"><svg class="ic"><use href="#i-chevron-right"/></svg></span>
          </div>
          <div class="blog-card-body">
            <div class="blog-card-tags">
              <?php if ($isNew) { ?><span class="badge green">New</span><?php } ?>
              <?php foreach (nh_slice($p->tag_list, 0, 2) as $t) { ?><span class="badge"><?= e($t) ?></span><?php } ?>
            </div>
            <h2><?= e($p->title) ?></h2>
            <p><?= e($p->excerpt) ?></p>
            <div class="blog-card-foot">
              <span class="hint"><svg class="ic"><use href="#i-calendar"/></svg> <?= e(fmtDate($p->published_at)) ?></span>
              <span class="blog-read">Read article →</span>
            </div>
          </div>
        </article>
      </a>
    </div>
    <?php } ?>
  </div>

  <?php if ($pages > 1) { ?>
  <nav class="blog-pager">
    <?php function pageUrl($n) {
         $params = [];
         if ($query->search) $params[] = 'search=' . rawurlencode($query->search);
         if ($query->tag) $params[] = 'tag=' . rawurlencode($query->tag);
         if ($query->sort && $query->sort !== 'latest') $params[] = 'sort=' . $query->sort;
         if ($n > 1) $params[] = 'page=' . $n;
         return '/blog' . (nh_count($params) ? '?' . nh_join($params, '&') : '');
       } ?>
    <?php if ($pageNum > 1) { ?><a class="btn btn-sm" href="<?= e(pageUrl($pageNum - 1)) ?>">← Prev</a><?php } ?>
    <?php for ($n = 1; $n <= $pages; $n++) {
         if ($n === 1 ?: $n === $pages ?: abs($n - $pageNum) <= 1) { ?>
          <a class="btn btn-sm <?= e($n === $pageNum ? 'btn-accent' : '') ?>" href="<?= e(pageUrl($n)) ?>"><?= e($n) ?></a>
        <?php } else if (abs($n - $pageNum) === 2) { ?><span class="hint">…</span><?php }
       } ?>
    <?php if ($pageNum < $pages) { ?><a class="btn btn-sm" href="<?= e(pageUrl($pageNum + 1)) ?>">Next →</a><?php } ?>
  </nav>
  <?php } ?>
  <?php } ?>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

