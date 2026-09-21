<?php
/**
 * CasaOS glass topbar.
 * Brand → crumb → obsidian navbar links → live system widgets → clock → theme → user menu.
 *
 * Expects: $settings, $user, $title, $active, $navData (parsed obsidian navbar)
 * Widget values are filled client-side by /assets/js/casaos.js polling /api/system.
 */
$s = $settings ?? Settings::all();
$u = $user ?? null;
$crumb = (string) ($title ?? '');
$navLinks = isset($navData) && is_array($navData) ? $navData : [];
$navVisible = Obsidian::visibleFor($navLinks, $u);
$logoType = (string) ($s->logo_type ?? 'emoji');
$logoUrl = (string) ($s->logo_url ?? '');
$logoEmoji = (string) ($s->logo_emoji ?? '🔷');
$widgetsOn = (string) ($s->casaos_widgets ?? 'on') === 'on';
$role = (string) ($u->role ?? '');
?>
<header class="cs-topbar">
  <button class="icon-btn cs-dock-toggle" id="csDockToggle" type="button" aria-label="Toggle dock">
    <svg class="ic"><use href="#i-menu"/></svg>
  </button>

  <a class="cs-brand" href="/" title="<?= e($s->panel_name ?? 'NobitaHost') ?>">
    <?php if ($logoType === 'image' && $logoUrl !== ''): ?>
      <img src="<?= e($logoUrl) ?>" alt="">
    <?php else: ?>
      <span class="cs-brand-emoji"><?= e($logoEmoji) ?></span>
    <?php endif; ?>
    <span class="cs-brand-name"><?= e($s->panel_name ?? 'NobitaHost') ?></span>
  </a>

  <?php if ($crumb !== ''): ?><span class="cs-crumb"><?= e($crumb) ?></span><?php endif; ?>

  <?php if ($navVisible): ?>
    <nav class="cs-topnav">
      <?php foreach ($navVisible as $l): ?>
        <a href="<?= e($l->url ?? '#') ?>"<?= ($l->type ?? 'internal') === 'external' ? ' target="_blank" rel="noopener"' : '' ?>><?= e($l->label ?? '') ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <div class="cs-spacer"></div>

  <?php if ($widgetsOn): ?>
    <div class="cs-widgets">
      <a class="cs-widget" id="csCpu" href="/system" title="CPU usage">
        <svg class="ic cs-w-ic"><use href="#i-cpu"/></svg><b>--%</b><span class="cs-meter"><i></i></span>
      </a>
      <a class="cs-widget" id="csMem" href="/system" title="Memory">
        <svg class="ic cs-w-ic"><use href="#i-memory"/></svg><b>—</b><span class="cs-meter"><i></i></span>
      </a>
      <a class="cs-widget" id="csDisk" href="/storage" title="Disk">
        <svg class="ic cs-w-ic"><use href="#i-hdd"/></svg><b>—</b><span class="cs-meter"><i></i></span>
      </a>
      <span class="cs-widget" id="csNet" title="Network">
        <svg class="ic cs-w-ic green"><use href="#i-network"/></svg><b>↓0 B/s ↑0 B/s</b>
      </span>
      <span class="cs-widget cs-w-temp" id="csTemp" title="Temperature">
        <svg class="ic cs-w-ic orange"><use href="#i-thermometer"/></svg><b>N/A</b>
      </span>
    </div>

    <div class="cs-clock" title="Local time">
      <b id="csClockTime">--:--</b>
      <span id="csClockDate">—</span>
    </div>
  <?php endif; ?>

  <button class="icon-btn" id="csThemeToggle" type="button" aria-label="Toggle theme" title="Toggle theme">
    <svg class="ic"><use href="#i-moon"/></svg>
  </button>

  <?php if ($u): ?>
    <button class="cs-avatar" type="button" data-cs-menu="#csUserMenu" aria-label="Account menu">
      <?php $av = avatar_of($u); ?>
      <?php if ($av !== ''): ?>
        <img src="<?= e($av) ?>" alt="">
      <?php else: ?>
        <span class="cs-avatar-fallback"><?= e(initials((string) ($u->username ?? 'U'))) ?></span>
      <?php endif; ?>
      <span class="cs-av-meta">
        <span class="cs-av-name"><?= e($u->username ?? 'user') ?></span>
        <span class="cs-av-role"><?= e($role === 'admin' ? 'Administrator' : ($u->is_demo ? 'Demo' : 'Member')) ?></span>
      </span>
    </button>

    <nav class="cs-menu" id="csUserMenu">
      <div class="cs-menu-head">
        <?php $av2 = avatar_of($u); ?>
        <?php if ($av2 !== ''): ?><img src="<?= e($av2) ?>" alt=""><?php else: ?><span class="cs-avatar-fallback lg"><?= e(initials((string) ($u->username ?? 'U'))) ?></span><?php endif; ?>
        <span>
          <b><?= e($u->username ?? 'user') ?></b>
          <span><?= e($u->email ?? '') ?></span>
        </span>
      </div>
      <hr>
      <a href="/profile"><svg class="ic"><use href="#i-user"/></svg> My profile</a>
      <a href="/profile/2fa/setup"><svg class="ic"><use href="#i-lock"/></svg> Security &amp; 2FA</a>
      <a href="/activity"><svg class="ic"><use href="#i-clock"/></svg> My activity</a>
      <a href="/storage"><svg class="ic"><use href="#i-hdd"/></svg> Storage</a>
      <a href="/system"><svg class="ic"><use href="#i-gauge"/></svg> System monitor</a>
      <?php if ($role === 'admin'): ?>
        <hr>
        <a href="/admin"><svg class="ic"><use href="#i-gauge"/></svg> Admin dashboard</a>
        <a href="/admin/users"><svg class="ic"><use href="#i-users"/></svg> Users</a>
        <a href="/admin/settings"><svg class="ic"><use href="#i-sliders"/></svg> Panel settings</a>
        <a href="/admin/cloudflare"><svg class="ic"><use href="#i-shield"/></svg> Cloudflare</a>
        <a href="/analytics"><svg class="ic"><use href="#i-chart"/></svg> Analytics</a>
      <?php endif; ?>
      <hr>
      <a href="/logout"><svg class="ic"><use href="#i-logout"/></svg> Log out</a>
    </nav>
  <?php else: ?>
    <a class="btn btn-sm" href="/login">Log in</a>
    <a class="btn btn-sm btn-accent" href="/register">Sign up</a>
  <?php endif; ?>
</header>
