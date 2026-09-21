<?php
/**
 * CasaOS dock — vertical icon rail (left). Renders Nav sections.
 * Expects: $user, $active
 */
$u = $user ?? null;
$activeKey = $active ?? '';
$sections = Nav::sections($u);
$dockOn = ($settings->casaos_dock ?? 'on') === 'on';
if (!$dockOn) return;
?>
<aside class="cs-dock" id="csDock">
  <?php foreach ($sections as $si => $sec): ?>
    <?php if ($si > 0): ?><span class="cs-dock-sep"></span><?php endif; ?>
    <?php foreach ($sec->items as $item): ?>
      <?php
        $iconRef = $item->icon ?? '';
        $emoji = $item->emoji ?? '';
      ?>
      <a class="cs-dock-item<?= $activeKey === $item->key ? ' active' : '' ?>"
         href="<?= e($item->url) ?>" data-tip="<?= e($item->label) ?>"
         title="<?= e($item->label) ?>" aria-label="<?= e($item->label) ?>">
        <?php if ($iconRef !== ''): ?>
          <svg class="ic"><use href="#<?= e($iconRef) ?>"/></svg>
        <?php else: ?>
          <span class="cs-dock-emoji"><?= e($emoji ?: '📄') ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <span class="cs-dock-sep"></span>
  <?php if ($u): ?>
    <a class="cs-dock-item" href="/logout" data-tip="Logout" title="Logout"><svg class="ic"><use href="#i-logout"/></svg></a>
  <?php else: ?>
    <a class="cs-dock-item" href="/login" data-tip="Login" title="Login"><svg class="ic"><use href="#i-lock"/></svg></a>
  <?php endif; ?>
</aside>
