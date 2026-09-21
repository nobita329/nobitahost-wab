<?php
/**
 * CasaOS desktop — the panel home screen.
 * Vars: $sys, $stats, $apps, $devices, $activity, $blogPosts, $page, $user, $settings
 */
$u = $user ?? null;
$hour = (int) date('G');
$greeting = $hour < 5 ? 'Still up' : ($hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : ($hour < 21 ? 'Good evening' : 'Good night')));
$sections = Nav::sections($u);

$healthScore = (int) ($sys->health->score ?? 100);
$healthTone = $healthScore >= 80 ? 'ok' : ($healthScore >= 55 ? 'warn' : 'err');
?>

<div class="cs-page-head">
  <div>
    <h1 class="cs-page-title"><?= e($greeting) ?><?= $u ? ', ' . e($u->username) : '' ?> 👋</h1>
    <div class="cs-page-sub">
      <?= e($settings->panel_name ?? 'NobitaHost') ?> · <?= e($settings->panel_tagline ?? '') ?>
    </div>
  </div>
  <div class="cs-page-actions">
    <div class="field" style="margin:0;min-width:180px">
      <input id="csAppSearch" type="search" placeholder="Search apps…" autocomplete="off">
    </div>
    <?php if (is_admin()): ?>
      <a class="btn btn-primary" href="/admin"><svg class="ic"><use href="#i-gauge"/></svg> Admin</a>
    <?php else: ?>
      <a class="btn btn-ghost" href="/profile"><svg class="ic"><use href="#i-user"/></svg> Profile</a>
    <?php endif; ?>
  </div>
</div>

<div class="cs-apps">

  <!-- ---------- system tiles ---------- -->
  <div class="cs-stats">
    <div class="cs-stat" style="--cs-tile: <?= e($settings->accent_color ?: '#3388ff') ?>">
      <div class="cs-stat-ic"><svg class="ic"><use href="#i-cpu"/></svg></div>
      <div style="flex:1;min-width:0">
        <b><?= e((string) ($sys->cpu->usage ?? 0)) ?>%</b>
        <span>CPU · <?= (int) ($sys->cpu->cores ?? 1) ?> cores</span>
        <div class="cs-stat-bar"><i style="width: <?= min(100, (float) ($sys->cpu->usage ?? 0)) ?>%"></i></div>
      </div>
    </div>

    <div class="cs-stat" style="--cs-tile: #34c759">
      <div class="cs-stat-ic"><svg class="ic"><use href="#i-memory"/></svg></div>
      <div style="flex:1;min-width:0">
        <b><?= e($sys->mem->usedStr ?? '—') ?></b>
        <span>of <?= e($sys->mem->totalStr ?? '—') ?> RAM</span>
        <div class="cs-stat-bar"><i style="width: <?= min(100, (float) ($sys->mem->usage ?? 0)) ?>%"></i></div>
      </div>
    </div>

    <div class="cs-stat" style="--cs-tile: #ff9f0a">
      <div class="cs-stat-ic"><svg class="ic"><use href="#i-hdd"/></svg></div>
      <div style="flex:1;min-width:0">
        <b><?= e($sys->disk->usedStr ?? '—') ?></b>
        <span>of <?= e($sys->disk->totalStr ?? '—') ?> disk</span>
        <div class="cs-stat-bar"><i style="width: <?= min(100, (float) ($sys->disk->usage ?? 0)) ?>%"></i></div>
      </div>
    </div>

    <div class="cs-stat" style="--cs-tile: #5e5ce6">
      <div class="cs-stat-ic"><svg class="ic"><use href="#i-clock"/></svg></div>
      <div style="flex:1;min-width:0">
        <b><?= e($sys->uptimeStr ?? '—') ?></b>
        <span>uptime · <?= e($sys->hostname ?? '') ?></span>
      </div>
    </div>

    <div class="cs-stat" style="--cs-tile: <?= $healthTone === 'ok' ? '#34c759' : ($healthTone === 'warn' ? '#ff9f0a' : '#ff453a') ?>">
      <div class="cs-stat-ic"><svg class="ic"><use href="#i-activity"/></svg></div>
      <div style="flex:1;min-width:0">
        <b><?= $healthScore ?><small style="font-size:12px;color:var(--cs-muted)">/100</small></b>
        <span>health · <?= e($sys->health->tempStr ?? 'N/A') ?></span>
      </div>
    </div>
  </div>

  <?php if ($page && trim((string) $page->content) !== ''): ?>
    <div class="cs-panel">
      <div class="cs-panel-body"><?= $page->content ?></div>
    </div>
  <?php endif; ?>

  <!-- ---------- app grid ---------- -->
  <?php foreach ($sections as $sec): ?>
    <section class="cs-apps-section">
      <div class="cs-apps-head">
        <h2><?= e($sec->label) ?></h2>
        <span class="cs-count"><?= count($sec->items) ?></span>
      </div>
      <div class="cs-app-grid">
        <?php foreach ($sec->items as $item): ?>
          <a class="cs-app" href="<?= e($item->url) ?>" data-name="<?= e($item->label) ?>" style="--cs-tile: <?= e($item->color ?? '#3388ff') ?>" title="<?= e($item->label) ?>">
            <span class="cs-app-icon">
              <?php if (!empty($item->icon)): ?>
                <svg class="ic"><use href="#<?= e($item->icon) ?>"/></svg>
              <?php else: ?>
                <?= e($item->emoji ?? '📄') ?>
              <?php endif; ?>
            </span>
            <span class="cs-app-name"><?= e($item->label) ?></span>
          </a>
        <?php endforeach; ?>
        <?php if (is_admin() && $sec->id === 'apps'): ?>
          <a class="cs-app add" href="/admin/apps" data-name="add app" title="Add app">
            <span class="cs-app-icon"><svg class="ic"><use href="#i-plus"/></svg></span>
            <span class="cs-app-name">Add</span>
          </a>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <?php if (!empty($apps)): ?>
    <section class="cs-apps-section">
      <div class="cs-apps-head">
        <h2>Installed</h2>
        <span class="cs-count"><?= count($apps) ?></span>
      </div>
      <div class="cs-app-grid">
        <?php foreach ($apps as $app): ?>
          <a class="cs-app" href="<?= e($app->url ?: '#') ?>" data-name="<?= e($app->title) ?>" style="--cs-tile: <?= e($app->color ?: '#3388ff') ?>" title="<?= e($app->description ?: $app->title) ?>">
            <span class="cs-app-icon"><?= e($app->icon ?: '📦') ?></span>
            <span class="cs-app-name"><?= e($app->title) ?></span>
            <?php if (!empty($app->category) && $app->category !== 'apps'): ?>
              <span class="cs-app-flag"><?= e($app->category) ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- ---------- storage + activity ---------- -->
  <div class="cs-grid-2">
    <div class="cs-panel">
      <div class="cs-panel-head">
        <h3>Storage</h3>
        <span class="cs-panel-sub"><?= count($devices) ?> drive<?= count($devices) === 1 ? '' : 's' ?></span>
        <div class="cs-panel-tools"><a class="btn btn-ghost btn-sm" href="/storage">Manage</a></div>
      </div>
      <div class="cs-panel-body cs-storage">
        <?php if (!$devices): ?>
          <div class="cs-empty"><div class="cs-empty-ic">💽</div><b>No drives detected</b></div>
        <?php endif; ?>
        <?php foreach (array_slice($devices, 0, 4) as $d): $pct = (float) $d->usage; ?>
          <div class="cs-drive">
            <div class="cs-drive-ic"><svg class="ic"><use href="#i-hdd"/></svg></div>
            <div class="cs-drive-meta">
              <b><?= e($d->mount) ?></b>
              <span><?= e($d->usedStr) ?> used of <?= e($d->totalStr) ?> · <?= e($d->fs) ?></span>
              <div class="cs-drive-bar"><i class="<?= $pct >= 90 ? 'crit' : ($pct >= 75 ? 'warn' : '') ?>" style="width: <?= min(100, $pct) ?>%"></i></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="cs-panel">
      <div class="cs-panel-head">
        <h3>Recent activity</h3>
        <div class="cs-panel-tools"><a class="btn btn-ghost btn-sm" href="/activity">View all</a></div>
      </div>
      <div class="cs-panel-body tight">
        <?php if (!$activity): ?>
          <div class="cs-empty"><div class="cs-empty-ic">🕒</div><b>Nothing yet</b><p>Activity from you and your team shows up here.</p></div>
        <?php else: ?>
          <?php foreach ($activity as $a): ?>
            <div style="display:flex;gap:12px;align-items:flex-start;padding:12px 18px;border-bottom:1px solid var(--cs-border)">
              <span class="cs-pill" style="flex:none"><?= e(initials((string) ($a->username ?: 'sys'))) ?></span>
              <div style="min-width:0;flex:1">
                <div class="cs-strong" style="font-size:13.5px"><?= e($a->action) ?></div>
                <div class="cs-muted" style="font-size:12px"><?= e($a->username ?: 'system') ?> · <?= e(timeago($a->created_at)) ?><?= $a->ip ? ' · ' . e($a->ip) : '' ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($blogPosts)): ?>
    <section class="cs-apps-section">
      <div class="cs-apps-head">
        <h2>From the blog</h2>
        <div class="cs-apps-tools"><a class="btn btn-ghost btn-sm" href="/blog">All posts</a></div>
      </div>
      <div class="cs-grid-3">
        <?php foreach ($blogPosts as $post): ?>
          <a class="cs-panel" style="display:block;text-decoration:none;color:inherit" href="/blog/<?= e($post->slug) ?>">
            <?php if (!empty($post->cover_image_url)): ?>
              <div style="height:120px;background:url('<?= e($post->cover_image_url) ?>') center/cover"></div>
            <?php endif; ?>
            <div class="cs-panel-body">
              <div class="cs-strong" style="font-size:15px;margin-bottom:4px"><?= e($post->title) ?></div>
              <div class="cs-muted" style="font-size:12.5px"><?= e(excerpt((string) ($post->description ?: $post->content), 110)) ?></div>
              <div style="margin-top:10px;display:flex;gap:8px;align-items:center">
                <span class="cs-pill"><svg class="ic" style="width:12px;height:12px"><use href="#i-eye"/></svg> <?= (int) $post->views ?></span>
                <span class="cs-pill"><?= e(fmt_date($post->published_at ?: $post->created_at)) ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

</div>
