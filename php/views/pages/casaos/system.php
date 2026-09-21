<?php
/**
 * CasaOS system monitor.
 * Vars: $sys, $history
 */
$cpuPts = [];
$memPts = [];
foreach ($history as $h) {
    $cpuPts[] = (float) $h->cpu;
    $memPts[] = (float) $h->mem;
}
$path = function (array $pts, int $w = 600, int $h = 120): string {
    if (!$pts) return '';
    $n = count($pts);
    $step = $n > 1 ? $w / ($n - 1) : $w;
    $d = '';
    foreach ($pts as $i => $p) {
        $x = round($i * $step, 1);
        $y = round($h - (min(100, max(0, $p)) / 100) * $h, 1);
        $d .= ($i === 0 ? "M$x,$y" : " L$x,$y");
    }
    return $d;
};
?>
<div class="cs-page-head">
  <div>
    <h1 class="cs-page-title">System</h1>
    <div class="cs-page-sub"><?= e($sys->hostname ?? '') ?> · <?= e($sys->platform ?? '') ?>/<?= e($sys->arch ?? '') ?> · PHP <?= e($sys->php ?? PHP_VERSION) ?></div>
  </div>
  <div class="cs-page-actions">
    <a class="btn btn-ghost" href="/"><svg class="ic"><use href="#i-chevron-left"/></svg> Back</a>
  </div>
</div>

<div class="cs-panel">
  <div class="cs-panel-head">
    <h3>CPU &amp; memory · last 24h</h3>
    <div class="cs-panel-tools">
      <span class="cs-pill"><svg class="ic" style="width:12px;height:12px"><use href="#i-cpu"/></svg> CPU</span>
      <span class="cs-pill"><svg class="ic" style="width:12px;height:12px"><use href="#i-memory"/></svg> RAM</span>
    </div>
  </div>
  <div class="cs-panel-body">
    <?php if ($cpuPts): ?>
      <svg viewBox="0 0 600 120" preserveAspectRatio="none" style="width:100%;height:140px">
        <path d="<?= e($path($cpuPts)) ?>" fill="none" stroke="var(--cs-accent)" stroke-width="2" stroke-linejoin="round"/>
        <path d="<?= e($path($memPts)) ?>" fill="none" stroke="#34c759" stroke-width="2" stroke-linejoin="round" opacity=".85"/>
      </svg>
    <?php else: ?>
      <div class="cs-empty"><div class="cs-empty-ic">📈</div><b>No samples yet</b><p>Samples are recorded every time a page loads — check back shortly.</p></div>
    <?php endif; ?>
  </div>
</div>

<div class="cs-grid-2">
  <div class="cs-panel">
    <div class="cs-panel-head"><h3>Processor</h3></div>
    <div class="cs-panel-body">
      <div class="cs-strong" style="font-size:14px"><?= e($sys->cpu->model ?? 'CPU') ?></div>
      <div class="cs-muted" style="font-size:12.5px;margin-bottom:14px"><?= (int) ($sys->cpu->cores ?? 1) ?> cores · load <?= e(implode(' / ', array_map(fn($l) => number_format((float) $l, 2), $sys->cpu->load ?? [0]))) ?></div>
      <div class="cs-drive-bar"><i style="width: <?= min(100, (float) ($sys->cpu->usage ?? 0)) ?>%"></i></div>
      <div class="cs-row" style="justify-content:space-between;margin-top:8px">
        <span class="cs-muted">usage</span><span class="cs-strong"><?= e((string) ($sys->cpu->usage ?? 0)) ?>%</span>
      </div>
    </div>
  </div>

  <div class="cs-panel">
    <div class="cs-panel-head"><h3>Memory</h3></div>
    <div class="cs-panel-body">
      <div class="cs-drive-bar"><i style="width: <?= min(100, (float) ($sys->mem->usage ?? 0)) ?>%"></i></div>
      <div class="cs-row" style="justify-content:space-between;margin-top:10px">
        <span class="cs-muted">used</span><span class="cs-strong"><?= e($sys->mem->usedStr ?? '—') ?> / <?= e($sys->mem->totalStr ?? '—') ?></span>
      </div>
      <div class="cs-row" style="justify-content:space-between">
        <span class="cs-muted">free</span><span class="cs-strong"><?= e($sys->mem->freeStr ?? '—') ?></span>
      </div>
      <div class="cs-row" style="justify-content:space-between">
        <span class="cs-muted">swap</span><span class="cs-strong"><?= e($sys->mem->swapStr ?? '—') ?></span>
      </div>
    </div>
  </div>

  <div class="cs-panel">
    <div class="cs-panel-head"><h3>Network</h3></div>
    <div class="cs-panel-body">
      <div class="cs-row" style="justify-content:space-between"><span class="cs-muted">interface</span><span class="cs-strong"><?= e($sys->network->iface ?? '—') ?></span></div>
      <div class="cs-row" style="justify-content:space-between"><span class="cs-muted">↓ download</span><span class="cs-strong"><?= e($sys->network->rxRateStr ?? '0 B/s') ?></span></div>
      <div class="cs-row" style="justify-content:space-between"><span class="cs-muted">↑ upload</span><span class="cs-strong"><?= e($sys->network->txRateStr ?? '0 B/s') ?></span></div>
      <div class="cs-row" style="justify-content:space-between"><span class="cs-muted">total</span><span class="cs-strong"><?= e($sys->network->totalRecvStr ?? '0 B') ?> ↓ / <?= e($sys->network->totalSentStr ?? '0 B') ?> ↑</span></div>
      <div class="cs-row" style="justify-content:space-between"><span class="cs-muted">connections</span><span class="cs-strong"><?= (int) ($sys->network->connections ?? 0) ?></span></div>
    </div>
  </div>

  <div class="cs-panel">
    <div class="cs-panel-head"><h3>Health</h3></div>
    <div class="cs-panel-body">
      <div class="cs-row" style="justify-content:space-between"><span class="cs-muted">status</span><span class="cs-pill ok">online</span></div>
      <div class="cs-row" style="justify-content:space-between"><span class="cs-muted">score</span><span class="cs-strong"><?= (int) ($sys->health->score ?? 0) ?>/100</span></div>
      <div class="cs-row" style="justify-content:space-between"><span class="cs-muted">temperature</span><span class="cs-strong"><?= e($sys->health->tempStr ?? 'N/A') ?></span></div>
      <div class="cs-row" style="justify-content:space-between"><span class="cs-muted">processes</span><span class="cs-strong"><?= (int) ($sys->health->processes ?? 0) ?></span></div>
      <div class="cs-row" style="justify-content:space-between"><span class="cs-muted">uptime</span><span class="cs-strong"><?= e($sys->uptimeStr ?? '—') ?></span></div>
      <div class="cs-row" style="justify-content:space-between"><span class="cs-muted">booted</span><span class="cs-strong"><?= e($sys->bootTimeStr ?? '—') ?></span></div>
    </div>
  </div>
</div>
