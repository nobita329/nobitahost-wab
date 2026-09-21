<?php
/**
 * Site footer + global script bundle (sprite is rendered in the layout head).
 * Expects: $settings, $user, $extraScripts (optional)
 */
$s = $settings ?? Settings::all();
$u = $user ?? null;
$year = date('Y');
$footerData = $footerData ?? null;
$fvis = function (?array $links) use ($u) {
    $out = [];
    foreach ($links ?: [] as $l) {
        $vis = $l->visibility ?? 'always';
        if ($vis === 'always' || ($vis === 'auth' && $u) || ($vis === 'guest' && !$u)) $out[] = $l;
    }
    return $out;
};
$musicOn = ($s->music_type ?? 'none') !== 'none' && !empty($s->music_url);
?>
<footer class="lucent-footer-wrap">
  <?php if ($footerData && !empty($footerData->enabled)): ?>
    <?php $cols = $footerData->columns ?? []; ?>
    <?php if ($cols || $fvis($footerData->legal ?? [])): ?>
    <div class="site-footer">
      <div class="sf-cols">
        <?php foreach ($cols as $col): $links = $fvis($col->links ?? []); if (empty($col->title) && !$links) continue; ?>
          <div class="sf-col">
            <b class="sf-title"><?= e($col->title ?? '') ?></b>
            <?php foreach ($links as $l): ?>
              <a href="<?= e($l->url ?? '#') ?>"<?= ($l->type ?? '') === 'external' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e($l->label ?? '') ?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="lucent-footer">
    <span>© <?= $year ?> <b><?= e($s->panel_name ?? 'NobitaHost') ?></b><?= !empty($footerData->copyright) ? ' · ' . e($footerData->copyright) : ' · All rights reserved' ?></span>
    <div class="lf-links">
      <a href="/tutorials">Tutorials</a>
      <a href="/docs">Docs</a>
      <a href="/blog">Blog</a>
      <?php if ($u && ($u->role ?? '') === 'admin'): ?><a href="/admin">Admin</a><?php endif; ?>
    </div>
  </div>
</footer>

<?php if ($musicOn): ?>
<div class="music-widget" id="musicWidget">
  <button class="icon-btn" id="musicToggle" aria-label="Music"><svg class="ic"><use href="#i-music"/></svg></button>
  <?php if (($s->music_type ?? '') === 'youtube' && preg_match('/(youtu\.be|youtube\.com)/', (string) $s->music_url)): ?>
    <?php preg_match('/(?:v=|youtu\.be\/|\/embed\/)([\w-]{6,})/', (string) $s->music_url, $vm); ?>
    <iframe class="music-frame" src="https://www.youtube.com/embed/<?= e($vm[1] ?? '') ?>?enablejsapi=1&rel=0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
  <?php else: ?>
    <audio id="musicPlayer" src="<?= e($s->music_url) ?>" loop preload="none"></audio>
  <?php endif; ?>
  <span class="music-tip" id="musicTip">Play music</span>
</div>
<?php endif; ?>

<div class="toasts" id="toasts"></div>
<div class="cs-toasts" id="csToasts"></div>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/casaos.js"></script>
<script>window.__NH_EXTRAS = { cookieBanner: <?= ($s->cookie_banner ?? 'off') === 'on' ? 'true' : 'false' ?>, antiAdblock: <?= ($s->anti_adblock ?? 'off') === 'on' ? 'true' : 'false' ?> };</script>
<script src="/assets/js/extras.js"></script>
<?php if ($musicOn): ?>
<script>window.__NH_VOLUME = <?= (int) ($s->music_volume ?? 40) ?: 40 ?>;</script>
<script src="/assets/js/music.js"></script>
<?php endif; ?>
<?php if (!empty($s->inject_body_code)): ?><?= $s->inject_body_code ?><?php endif; ?>
<?php if (($s->cf_analytics_enabled ?? 'off') === 'on' && !empty($s->cf_analytics_token)): ?>
<script defer src="https://static.cloudflareinsights.com/beacon.min.js" data-cf-beacon='{"token": "<?= e($s->cf_analytics_token) ?>"}'></script>
<?php endif; ?>
<?php if (!empty($extraScripts)): ?><?= $extraScripts ?><?php endif; ?>
<?= section('scripts') ?>
