<?php
/**
 * Ported from views/pages/user/tutorials.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-book"/></svg> Tutorials</h2></div>
      <div class="card-body content-area"><?= $page->content ?></div>
    </div>
  </div>
</div>

<?php if ($yt && $yt->channel) { ?>
<div class="row">
  <div class="col-12">
    <div class="card yt-hero">
      <div class="yt-hero-inner">
        <a class="yt-avatar xl" href="https://www.youtube.com/@<?= e($yt->channel->handle) ?>" target="_blank" rel="noopener">
          <?php if ($yt->channel->thumb) { ?><img src="<?= e($yt->channel->thumb) ?>" alt=""><?php } else { ?>▶<?php } ?>
        </a>
        <div class="yt-hero-meta">
          <h2><?= e($yt->channel->title) ?></h2>
          <span class="badge badge-accent"><?= e($yt->channel->handle ? '@' . $yt->channel->handle : '') ?></span>
        </div>
        <div class="yt-stats">
          <div class="yt-stat"><b><?= e($yt->channel->subscribers ? fmt($yt->channel->subscribers) : '—') ?></b><span>Subscribers</span></div>
          <div class="yt-stat"><b><?= e($yt->channel->views != null ? fmt($yt->channel->views) : '—') ?></b><span>Views</span></div>
          <div class="yt-stat"><b><?= e($yt->channel->likes != null ? fmt($yt->channel->likes) : '—') ?></b><span>Likes</span></div>
          <div class="yt-stat"><b><?= e($yt->channel->videoCount ? fmt($yt->channel->videoCount) : nh_count($yt->videos)) ?></b><span>Videos</span></div>
        </div>
        <a class="btn btn-accent" href="https://www.youtube.com/@<?= e($yt->channel->handle) ?>?sub_confirmation=1" target="_blank" rel="noopener"><svg class="ic"><use href="#i-play"/></svg> Subscribe</a>
      </div>
    </div>
  </div>
</div>

<?php if (!$yt->error) { ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-play"/></svg> Latest Videos</h2></div>
      <div class="card-body">
        <div class="yt-grid">
          <?php foreach (($yt->videos ?: []) as $v) { ?>
          <a class="yt-video" href="https://www.youtube.com/watch?v=<?= e($v->id) ?>" target="_blank" rel="noopener">
            <div class="yt-thumb">
              <img src="<?= e($v->thumb) ?>" alt="" loading="lazy">
              <span class="yt-play"><svg class="ic"><use href="#i-play"/></svg></span>
            </div>
            <div class="yt-video-body">
              <h3><?= e($v->title) ?></h3>
              <div class="yt-video-meta">
                <span><?= e($v->views != null ? fmt($v->views) . ' views' : '—') ?></span>
                <span><?= e($v->likes != null ? '♥ ' . fmt($v->likes) : '') ?></span>
                <?php if (!empty($v->published)) { ?><span><?= e(nh_locale_date($v->published)) ?></span><?php } ?>
              </div>
            </div>
          </a>
          <?php } ?>
        </div>
        <?php if ($yt->videos && !nh_count($yt->videos)) { ?>
        <div class="empty">No videos found.</div>
        <?php } ?>
        <div class="hint center mt">Showing <b><?= e(nh_count($yt->videos)) ?></b> videos <?php if (!$yt->channel->videoCount ?: $yt->channel->videoCount > nh_count($yt->videos)) { ?>(latest <?= e(nh_count($yt->videos)) ?>)<?php } ?></div>
      </div>
    </div>
  </div>
</div>
<?php } else { ?>
<div class="alert alert-error"><?= e($yt->error) ?></div>
<?php } ?>
<?php } ?>

<div class="row">
  <?php foreach (($tutorials ?: []) as $t) { ?>
  <div class="col-4 col-md-6 col-sm-12">
    <div class="card t-card">
      <div class="t-thumb">
        <?php if ($t->video_url && (bool) preg_match('/youtu\.be|youtube\.com/', (string) $t->video_url)) { ?>
          <img src="https://i.ytimg.com/vi/<?= e(nh_match_group($t->video_url, '/(?:v=|youtu\.be\/|\/embed\/)([\w-]{6,})/', 1)) ?>/hqdefault.jpg" alt="" loading="lazy">
        <?php } else if ($t->thumbnail) { ?>
          <img src="<?= e($t->thumbnail) ?>" alt="" loading="lazy">
        <?php } else { ?>
          <div class="t-thumb-ph"><svg class="ic"><use href="#i-play"/></svg></div>
        <?php } ?>
        <span class="t-play"><svg class="ic"><use href="#i-play"/></svg></span>
      </div>
      <div class="t-body">
        <h3><?= e($t->title) ?></h3>
        <p><?= e($t->description) ?></p>
        <div class="t-meta">
          <?php if ($t->video_url) { ?><a class="btn btn-accent btn-sm" href="<?= e($t->video_url) ?>" target="_blank" rel="noopener">Watch</a><?php } ?>
          <span class="badge">by <?= e($t->author ?: 'Admin') ?></span>
        </div>
      </div>
    </div>
  </div>
  <?php } ?>
</div>

<?php if (!$tutorials ?: !nh_count($tutorials)) { ?>
<div class="card"><div class="empty">No tutorials yet. Admin can add them from <b>Settings → Tutorials</b>.</div></div>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

