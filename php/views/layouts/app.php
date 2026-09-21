<?php
/**
 * CasaOS shell layout: wallpaper → topbar → dock → main content → footer.
 * Available vars: $content, $settings, $user, $title, $active, $bg, $bodyClass,
 *                 $metaDescription, $navPages, $footerData, $extraScripts
 */
$s  = $settings ?? Settings::all();
$u  = $user ?? null;
$bg = View::normalize($bg ?? Settings::resolveBackground($s));
$bgUrl  = (string) ($bg->url ?? '');
$bgType = (string) ($bg->type ?? 'image');
$title  = $title ?? '';
$bodyClass = trim(($bodyClass ?? '') . ' cs-body');
$theme = ($s->theme ?? 'dark') === 'light' ? 'light' : ($s->theme ?? 'dark');
$ui = ($s->casaos_mode ?? 'on') === 'on' ? 'casaos' : 'classic';
$opacity = max(0, min(100, (int) ($s->casaos_opacity ?? 82)));
$flashes = View::normalize(take_flashes());
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>" data-ui="<?= e($ui) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title !== '' ? e($title) . ' · ' : '' ?><?= e($s->panel_name ?? 'NobitaHost') ?></title>
<?php if (!empty($metaDescription)): ?><meta name="description" content="<?= e($metaDescription) ?>"><?php endif; ?>
<link rel="icon" href="<?= e($s->favicon_url ?: '/assets/img/favicon.svg') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/lucentui.css">
<link rel="stylesheet" href="/assets/css/casaos.css">
<script>
  try { var m = localStorage.getItem('nh-theme'); if (m) document.documentElement.setAttribute('data-theme', m); } catch (e) {}
</script>
<style>
  :root {
    --accent: <?= e($s->accent_color ?: '#3388ff') ?>;
    --cs-accent: <?= e($s->accent_color ?: '#3388ff') ?>;
    --card-radius: <?= (int) ($s->card_radius ?: 16) ?>px;
    --cs-radius-lg: <?= max(10, (int) ($s->card_radius ?: 20)) ?>px;
    --glass: <?= Settings::normalizeBlur($s) ?>px;
    --cs-blur: <?= Settings::normalizeBlur($s) + 10 ?>px;
    --overlay: <?= Settings::overlayAlpha($s) ?>;
    --cs-wallpaper-opacity: <?= number_format($opacity / 100, 3, '.', '') ?>;
  }
</style>
<?= section('head') ?>
<?php if (!empty($s->inject_head_code)): ?><?= $s->inject_head_code ?><?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">

<div class="bg-layer">
  <?php if ($bgUrl !== '' && $bgType === 'video'): ?>
    <video autoplay muted loop playsinline><source src="<?= e($bgUrl) ?>" type="video/mp4">Your browser does not support video.</video>
  <?php elseif ($bgUrl !== ''): ?>
    <div class="bg-img" style="background-image:url('<?= e($bgUrl) ?>')"></div>
  <?php else: ?>
    <div class="bg-img bg-gradient"></div>
  <?php endif; ?>
  <div class="bg-overlay"></div>
</div>

<?php partial('partials/icons') ?>

<div class="cs-shell<?= ($s->casaos_dock ?? 'on') === 'on' ? '' : ' no-dock' ?>">
  <?php partial('partials/topbar', ['settings' => $s, 'user' => $u, 'title' => $title, 'active' => $active ?? '']) ?>
  <?php partial('partials/dock', ['settings' => $s, 'user' => $u, 'active' => $active ?? '']) ?>

  <main class="cs-main">
    <?php if ($flashes): ?>
      <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f->type) ?>"><?= e($f->message) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?= $content ?>
  </main>
</div>

<div class="cs-dock-backdrop"></div>

<a class="cs-storage-chip" href="/analytics" id="csStorageChip" title="Storage">
  <svg class="ic" style="width:16px;height:16px"><use href="#i-hdd"/></svg>
  <b>--%</b>
  <span class="cs-meter"><i></i></span>
</a>

<span id="csFlash" data-flash='<?= json_attr($flashes) ?>'></span>

<?php partial('partials/site-footer', ['settings' => $s, 'user' => $u, 'footerData' => $footerData ?? null, 'extraScripts' => $extraScripts ?? '']) ?>
</body>
</html>
