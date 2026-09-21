<?php
/**
 * Ported from views/pages/admin/blog-form.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query->error === 'fields') { ?><div class="alert alert-error">Title and content are required.</div><?php } ?>

<form method="POST" action="<?= e($post ? '/admin/blog/' . $post->id . '/edit' : '/admin/blog/create') ?>">
  <div class="blog-form-grid">
    <div class="blog-form-main">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-pencil"/></svg> <?= e($post ? 'Edit Post' : 'New Post') ?></h2></div>
        <div class="card-body">
          <div class="field"><label>Title</label>
            <input type="text" name="title" value="<?= e($post ? $post->title : '') ?>" required placeholder="My awesome blog post">
          </div>
          <div class="field"><label>Short Description</label>
            <textarea name="description" rows="2" maxlength="255" placeholder="Shown on cards and search results"><?= e($post ? $post->description : '') ?></textarea>
          </div>
          <div class="field"><label>Content <small>(Markdown supported)</small></label>
            <textarea name="content" rows="18" class="mono" required placeholder="# Heading&#10;&#10;Write your article in **Markdown**…"><?= e($post ? $post->content : '') ?></textarea>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-search"/></svg> SEO</h2></div>
        <div class="card-body">
          <div class="row">
            <div class="col-6 col-sm-12">
              <div class="field"><label>SEO Title</label>
                <input type="text" name="seo_title" value="<?= e($post ? $post->seo_title : '') ?>" placeholder="Custom browser / search title">
              </div>
            </div>
            <div class="col-6 col-sm-12">
              <div class="field"><label>Keywords</label>
                <input type="text" name="seo_keywords" value="<?= e($post ? $post->seo_keywords : '') ?>" placeholder="hosting, minecraft, guide">
              </div>
            </div>
          </div>
          <div class="field"><label>Meta Description</label>
            <textarea name="seo_description" rows="2" maxlength="320" placeholder="Up to 320 characters for search engines"><?= e($post ? $post->seo_description : '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="blog-form-side">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-file"/></svg> Publish</h2></div>
        <div class="card-body">
          <div class="switch-row"><div><b>Published</b><small><?= e($post && $post->published_at ? 'On ' . nh_slice($post->published_at, 0, 16) : 'Will be dated now') ?></small></div>
            <label class="switch"><input type="checkbox" name="is_published" <?= e($post && $post->is_published ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="field"><label>URL Slug</label>
            <input type="text" name="slug" value="<?= e($post ? $post->slug : '') ?>" placeholder="auto-generated-from-title">
          </div>
          <div class="field"><label>Tags <small>(comma separated)</small></label>
            <input type="text" name="tags" value="<?= e($post ? $post->tags : '') ?>" placeholder="news, updates, guide">
          </div>
          <div class="field"><label>Cover Image URL</label>
            <input type="text" name="cover_image_url" value="<?= e($post ? $post->cover_image_url : '') ?>" placeholder="https://… or /uploads/…">
          </div>
          <button class="btn btn-accent btn-block" type="submit"><svg class="ic"><use href="#i-shield"/></svg> <?= e($post ? 'Save Changes' : 'Create Post') ?></button>
          <?php if ($post) { ?><a class="btn btn-block" href="/blog/<?= e($post->slug) ?>">View on site</a><?php } ?>
          <a class="btn btn-ghost btn-block" href="/admin/blog">Back to list</a>
        </div>
      </div>
    </div>
  </div>
</form>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

