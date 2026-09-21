<?php
/**
 * Auth / error layout — centred CasaOS glass card on the wallpaper.
 */
$s  = $settings ?? Settings::all();
$bg = View::normalize($bg ?? Settings::resolveBackground($s));
$bgUrl  = (string) ($bg->url ?? '');
$bgType = (string) ($bg->type ?? 'image');
$title  = $title ?? '';
$theme  = ($s->theme ?? 'dark') === 'light' ? 'light' : ($s->theme ?? 'dark');
$ui     = ($s->casaos_mode ?? 'on') === 'on' ? 'casaos' : 'classic';
$flashes = View::normalize(take_flashes());
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>" data-ui="<?= e($ui) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title !== '' ? e($title) . ' · ' : '' ?><?= e($s->panel_name ?? 'NobitaHost') ?></title>
<link rel="icon" href="<?= e($s->favicon_url ?: '/assets/img/favicon.svg') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/lucentui.css">
<link rel="stylesheet" href="/assets/css/casaos.css">
<style>
  :root {
    --accent: <?= e($s->accent_color ?: '#3388ff') ?>;
    --cs-accent: <?= e($s->accent_color ?: '#3388ff') ?>;
    --card-radius: <?= (int) ($s->card_radius ?: 16) ?>px;
    --glass: <?= Settings::normalizeBlur($s) ?>px;
    --overlay: <?= Settings::overlayAlpha($s) ?>;
  }
</style>
<?= section('head') ?>
</head>
<body class="auth-page cs-body">
<div class="bg-layer">
  <?php if ($bgUrl !== '' && $bgType === 'video'): ?>
    <video autoplay muted loop playsinline><source src="<?= e($bgUrl) ?>" type="video/mp4"></video>
  <?php elseif ($bgUrl !== ''): ?>
    <div class="bg-img" style="background-image:url('<?= e($bgUrl) ?>')"></div>
  <?php else: ?>
    <div class="bg-img bg-gradient"></div>
  <?php endif; ?>
  <div class="bg-overlay"></div>
</div>

<?php partial('partials/icons') ?>

<?php $u = $user ?? null; ?>
<div class="cs-shell<?= ($s->casaos_dock ?? 'on') === 'on' ? '' : ' no-dock' ?>">
  <?php partial('partials/topbar', ['settings' => $s, 'user' => $u, 'title' => $title, 'active' => $active ?? '']) ?>
  <?php partial('partials/dock', ['settings' => $s, 'user' => $u, 'active' => $active ?? '']) ?>
  <main class="cs-main cs-main-auth">
<div class="cs-auth-wrap">
  <div class="cs-auth">
    <div class="cs-auth-logo">
      <?php if (($s->logo_type ?? 'emoji') === 'url' && !empty($s->logo_url)): ?>
        <img src="<?= e($s->logo_url) ?>" alt="logo">
      <?php else: ?>
        <span><?= e($s->logo_emoji ?: '🔷') ?></span>
      <?php endif; ?>
    </div>
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f->type) ?>"><?= e($f->message) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </div>
</div>
  </main>
</div>

<div class="cs-toasts" id="csToasts"></div>
<span id="csFlash" data-flash='<?= json_attr($flashes) ?>'></span>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/casaos.js"></script>
<?php if (!empty($s->inject_body_code)): ?><?= $s->inject_body_code ?><?php endif; ?>
<?= section('scripts') ?>
</body>
</html>
