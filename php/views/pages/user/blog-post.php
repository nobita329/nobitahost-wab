<?php
/**
 * Ported from views/pages/user/blog-post.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="blog-article">
  <nav class="blog-crumbs card">
    <a href="/"><svg class="ic"><use href="#i-home"/></svg></a>
    <span class="crumb-sep">/</span>
    <a href="/blog">Blog</a>
    <span class="crumb-sep">/</span>
    <span class="crumb-here"><?= e($post->title) ?></span>
  </nav>

  <div class="blog-article-grid">
    <div class="blog-main">
      <article class="card blog-post-full">
        <?php if ($post->cover_image_url) { ?>
        <div class="blog-cover"><img src="<?= e($post->cover_image_url) ?>" alt="<?= e($post->title) ?>"></div>
        <?php } ?>
        <div class="blog-post-body">
          <header class="blog-post-head">
            <div class="blog-post-meta hint">
              <span><svg class="ic"><use href="#i-calendar"/></svg> <?= e(fmtDate($post->published_at)) ?></span>
              <span><svg class="ic"><use href="#i-eye"/></svg> <?= e($post->views) ?> views</span>
            </div>
            <h1><?= e($post->title) ?></h1>
            <?php if ($post->description) { ?><p class="blog-post-desc"><?= e($post->description) ?></p><?php } ?>
            <?php if (nh_count($post->tag_list)) { ?>
            <div class="blog-card-tags">
              <?php foreach ($post->tag_list as $t) { ?><a class="chip" href="/blog?tag=<?= e(rawurlencode($t)) ?>"><?= e($t) ?></a><?php } ?>
            </div>
            <?php } ?>
          </header>
          <div class="md-body"><?= $post->html ?></div>
        </div>
      </article>

      <div class="card blog-feedback" id="feedback">
        <div class="bf-row">
          <div class="bf-info">
            <b>Did you find this article helpful?</b>
            <span class="hint"><?= e($post->feedback->helpful) ?> <?= e($post->feedback->helpful === 1 ? 'person' : 'people') ?> found this helpful.</span>
            <?php if ($myFeedback !== null) { ?><span class="hint bf-done"><svg class="ic"><use href="#i-shield"/></svg> <?= e($myFeedback ? 'You marked this as helpful.' : 'You told us this needs work.') ?></span><?php } ?>
          </div>
          <div class="bf-actions">
            <form method="POST" action="/blog/<?= e($post->slug) ?>/feedback" class="inline">
              <input type="hidden" name="back" value="<?= e('/blog/' . $post->slug) ?>">
              <input type="hidden" name="helpful" value="yes">
              <button class="btn btn-accent btn-sm" type="submit">👍 Yes</button>
            </form>
            <form method="POST" action="/blog/<?= e($post->slug) ?>/feedback" class="inline">
              <input type="hidden" name="back" value="<?= e('/blog/' . $post->slug) ?>">
              <input type="hidden" name="helpful" value="no">
              <button class="btn btn-sm" type="submit">👎 No</button>
            </form>
          </div>
        </div>
      </div>

      <?php if ($post->prev ?: $post->next) { ?>
      <div class="blog-prevnext">
        <?php if ($post->prev) { ?>
        <a class="card pn-card" href="/blog/<?= e($post->prev->slug) ?>"><span class="hint">← Previous</span><b><?= e($post->prev->title) ?></b></a>
        <?php } else { ?><div></div><?php } ?>
        <?php if ($post->next) { ?>
        <a class="card pn-card pn-right" href="/blog/<?= e($post->next->slug) ?>"><span class="hint">Next →</span><b><?= e($post->next->title) ?></b></a>
        <?php } ?>
      </div>
      <?php } ?>
    </div>

    <?php if (nh_count($post->toc)) { ?>
    <aside class="blog-side">
      <div class="card blog-toc">
        <div class="card-head"><h2><svg class="ic"><use href="#i-file"/></svg> On this page</h2></div>
        <div class="card-body toc-list">
          <?php foreach ($post->toc as $h) { ?>
            <a class="toc-l<?= e($h->level) ?>" href="#<?= e($h->id) ?>"><?= e($h->text) ?></a>
          <?php } ?>
        </div>
      </div>
    </aside>
    <?php } ?>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

