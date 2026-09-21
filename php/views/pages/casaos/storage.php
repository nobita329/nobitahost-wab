<?php
/**
 * CasaOS Storage panel.
 * Vars: $sys, $devices
 */
$uploadBytes = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(NH_UPLOADS, FilesystemIterator::SKIP_DOTS)) as $f) {
    if ($f->isFile()) $uploadBytes += $f->getSize();
}
$dbBytes = is_file(DB::$path ?: '') ? (int) filesize(DB::$path) : 0;
?>
<div class="cs-page-head">
  <div>
    <h1 class="cs-page-title">Storage</h1>
    <div class="cs-page-sub">Drives, panel files and disk activity</div>
  </div>
  <div class="cs-page-actions">
    <a class="btn btn-ghost" href="/"><svg class="ic"><use href="#i-chevron-left"/></svg> Back</a>
    <button class="btn btn-ghost" onclick="location.reload()"><svg class="ic"><use href="#i-refresh"/></svg> Refresh</button>
  </div>
</div>

<div class="cs-grid-2">
  <?php foreach ($devices as $d): $pct = (float) $d->usage; ?>
    <div class="cs-panel">
      <div class="cs-panel-head">
        <div class="cs-drive-ic" style="width:34px;height:34px;border-radius:11px"><svg class="ic" style="width:18px;height:18px"><use href="#i-hdd"/></svg></div>
        <div>
          <h3><?= e($d->device) ?></h3>
          <span class="cs-panel-sub">mounted at <?= e($d->mount) ?> · <?= e($d->fs) ?></span>
        </div>
        <div class="cs-panel-tools"><span class="cs-pill <?= $pct >= 90 ? 'err' : ($pct >= 75 ? 'warn' : 'ok') ?>"><?= number_format($pct, 1) ?>%</span></div>
      </div>
      <div class="cs-panel-body">
        <div class="cs-drive-bar" style="height:8px"><i class="<?= $pct >= 90 ? 'crit' : ($pct >= 75 ? 'warn' : '') ?>" style="width: <?= min(100, $pct) ?>%"></i></div>
        <div class="cs-row" style="justify-content:space-between;margin-top:12px">
          <div><div class="cs-muted" style="font-size:11.5px;font-weight:700">USED</div><div class="cs-strong"><?= e($d->usedStr) ?></div></div>
          <div><div class="cs-muted" style="font-size:11.5px;font-weight:700">FREE</div><div class="cs-strong"><?= e($d->freeStr) ?></div></div>
          <div><div class="cs-muted" style="font-size:11.5px;font-weight:700">TOTAL</div><div class="cs-strong"><?= e($d->totalStr) ?></div></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="cs-panel">
    <div class="cs-panel-head"><h3>Panel files</h3><span class="cs-panel-sub">what NobitaHost stores locally</span></div>
    <div class="cs-panel-body">
      <div class="cs-storage">
        <div class="cs-drive">
          <div class="cs-drive-ic"><svg class="ic"><use href="#i-image"/></svg></div>
          <div class="cs-drive-meta">
            <b>Uploads</b>
            <span><?= e(fmt_bytes($uploadBytes)) ?> · /storage/uploads</span>
          </div>
        </div>
        <div class="cs-drive">
          <div class="cs-drive-ic"><svg class="ic"><use href="#i-database"/></svg></div>
          <div class="cs-drive-meta">
            <b>Database</b>
            <span><?= e(fmt_bytes($dbBytes)) ?> · SQLite</span>
          </div>
        </div>
        <div class="cs-drive">
          <div class="cs-drive-ic"><svg class="ic"><use href="#i-download"/></svg></div>
          <div class="cs-drive-meta">
            <b>Disk I/O</b>
            <span>read <?= e($sys->disk->ioReadStr ?? '0 B/s') ?> · write <?= e($sys->disk->ioWriteStr ?? '0 B/s') ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
