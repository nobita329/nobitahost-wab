<?php
/**
 * Ported from views/pages/user/analytics.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card"><div class="stat-icon accent1"><svg class="ic"><use href="#i-users"/></svg></div>
      <div class="stat-meta"><b><?= e($stats->totalUsers) ?></b><span>Total Users</span></div></div>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card"><div class="stat-icon accent2"><svg class="ic"><use href="#i-shield"/></svg></div>
      <div class="stat-meta"><b><?= e($stats->activeUsers) ?></b><span>Active</span></div></div>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card"><div class="stat-icon accent3"><svg class="ic"><use href="#i-eye"/></svg></div>
      <div class="stat-meta"><b><?= e($stats->todayHits) ?></b><span>Views Today</span></div></div>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card"><div class="stat-icon accent4"><svg class="ic"><use href="#i-clock"/></svg></div>
      <div class="stat-meta"><b><?= e($stats->todayLogins) ?></b><span>Logins Today</span></div></div>
  </div>
</div>

<?php if ($system) { ?>
<section class="sys-mon" id="sysMonitor">

  <div class="row">
    <div class="col-3 col-md-6 col-sm-12">
      <div class="card stat-card"><div class="stat-icon accent2"><span class="status-dot online" id="sysStatusDot"></span></div>
        <div class="stat-meta"><b id="sysStatus"><?= e($system->health->status) ?></b><span>Server Status</span></div></div>
    </div>
    <div class="col-3 col-md-6 col-sm-12">
      <div class="card stat-card"><div class="stat-icon accent1"><svg class="ic"><use href="#i-clock"/></svg></div>
        <div class="stat-meta"><b id="sysUptime"><?= e($system->uptimeStr) ?></b><span>Uptime</span></div></div>
    </div>
    <div class="col-3 col-md-6 col-sm-12">
      <div class="card stat-card"><div class="stat-icon accent3"><svg class="ic"><use href="#i-gauge"/></svg></div>
        <div class="stat-meta"><b id="sysCores"><?= e($system->cpu->cores) ?></b><span>CPU Cores</span></div></div>
    </div>
    <div class="col-3 col-md-6 col-sm-12">
      <div class="card stat-card"><div class="stat-icon accent4"><svg class="ic"><use href="#i-shield"/></svg></div>
        <div class="stat-meta"><b id="hScore"><?= e($system->health->score) ?></b><span>Health Score</span></div></div>
    </div>
  </div>

  <div class="row">
    <div class="col-4 col-md-12">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-activity"/></svg> CPU</h2></div>
        <div class="card-body">
          <div class="metric-main">
            <div class="num"><span id="cpuUsagePct"><?= e(round($system->cpu->usage)) ?>%</span><small>Usage</small></div>
            <div class="pbar" id="cpuBar"><span style="width:<?= e($system->cpu->usage) ?>%"></span></div>
          </div>
          <div class="metric-grid">
            <div class="metric"><span>Load 1m</span><b id="cpuLoad1"><?= e($system->cpu->load[0]) ?></b></div>
            <div class="metric"><span>Load 5m</span><b id="cpuLoad5"><?= e($system->cpu->load[1]) ?></b></div>
            <div class="metric"><span>Load 15m</span><b id="cpuLoad15"><?= e($system->cpu->load[2]) ?></b></div>
            <div class="metric"><span>Cores</span><b id="cpuCores"><?= e($system->cpu->cores) ?></b></div>
            <div class="metric"><span>Frequency</span><b id="sysFreq"><?= e($system->cpu->freq ? $system->cpu->freq . ' MHz' : '—') ?></b></div>
            <div class="metric"><span>Model</span><b id="sysModel" class="small"><?= e($system->cpu->model ?: '—') ?></b></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-4 col-md-12">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-gauge"/></svg> Memory (RAM)</h2></div>
        <div class="card-body">
          <div class="metric-main">
            <div class="num"><span id="memUsagePct"><?= e(round($system->mem->usage)) ?>%</span><small>Used</small></div>
            <div class="pbar" id="memBar"><span style="width:<?= e($system->mem->usage) ?>%"></span></div>
          </div>
          <div class="metric-grid">
            <div class="metric"><span>Total</span><b id="memTotal"><?= e($system->mem->totalStr) ?></b></div>
            <div class="metric"><span>Used</span><b id="memUsed"><?= e($system->mem->usedStr) ?></b></div>
            <div class="metric"><span>Free</span><b id="memFree"><?= e($system->mem->freeStr) ?></b></div>
            <div class="metric"><span>Swap</span><b id="memSwap"><?= e($system->mem->swapStr) ?></b></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-4 col-md-12">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-folder"/></svg> Disk</h2></div>
        <div class="card-body">
          <div class="metric-main">
            <div class="num"><span id="diskUsagePct"><?= e(round($system->disk->usage)) ?>%</span><small>Used</small></div>
            <div class="pbar" id="diskBar"><span style="width:<?= e($system->disk->usage) ?>%"></span></div>
          </div>
          <div class="metric-grid">
            <div class="metric"><span>Total</span><b id="diskTotal"><?= e($system->disk->totalStr) ?></b></div>
            <div class="metric"><span>Used</span><b id="diskUsed"><?= e($system->disk->usedStr) ?></b></div>
            <div class="metric"><span>Free</span><b id="diskFree"><?= e($system->disk->freeStr) ?></b></div>
            <div class="metric"><span>I/O Read</span><b id="diskRead"><?= e($system->disk->ioReadStr) ?></b></div>
            <div class="metric"><span>I/O Write</span><b id="diskWrite"><?= e($system->disk->ioWriteStr) ?></b></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-6 col-md-12">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-link"/></svg> Network</h2></div>
        <div class="card-body">
          <div class="metric-grid">
            <div class="metric"><span>Download Speed</span><b id="netRx"><?= e($system->network->rxRateStr) ?></b></div>
            <div class="metric"><span>Upload Speed</span><b id="netTx"><?= e($system->network->txRateStr) ?></b></div>
            <div class="metric"><span>Data Received</span><b id="netTotalRx"><?= e($system->network->totalRecvStr) ?></b></div>
            <div class="metric"><span>Data Sent</span><b id="netTotalTx"><?= e($system->network->totalSentStr) ?></b></div>
            <div class="metric"><span>Active Connections</span><b id="netConn"><?= e($system->network->connections) ?></b></div>
            <div class="metric"><span>Interface</span><b id="netIface"><?= e($system->network->iface) ?></b></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-12">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> VPS Health</h2></div>
        <div class="card-body">
          <div class="metric-main">
            <div class="num"><span id="hScoreBig"><?= e($system->health->score) ?></span><small>/ 100</small></div>
            <div class="pbar" id="hScoreBar"><span style="width:<?= e($system->health->score) ?>%"></span></div>
          </div>
          <div class="metric-grid">
            <div class="metric"><span>Status</span><b id="hStatus"><span class="status-dot online"></span><?= e($system->health->status) ?></b></div>
            <div class="metric"><span>Temperature</span><b id="hTemp"><?= e($system->health->tempStr) ?></b></div>
            <div class="metric"><span>Running Processes</span><b id="hProcs"><?= e($system->health->processes) ?></b></div>
            <div class="metric"><span>Load Average</span><b id="hLoad"><?= e($system->health->loadAvg) ?></b></div>
            <div class="metric"><span>Last Reboot</span><b id="sysBoot" class="small"><?= e($system->bootTimeStr) ?></b></div>
            <div class="metric"><span>Hostname</span><b id="sysHost" class="small"><?= e($system->hostname) ?></b></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-head">
          <h2><svg class="ic"><use href="#i-chart"/></svg> Performance Statistics</h2>
          <div class="head-actions">
            <div class="seg" id="sysRanges">
              <button type="button" data-range="live" class="active">Live</button>
              <button type="button" data-range="1h">1h</button>
              <button type="button" data-range="24h">24h</button>
              <button type="button" data-range="7d">7d</button>
              <button type="button" data-range="30d">30d</button>
            </div>
            <span class="hint" id="sysLastUpdate">—</span>
          </div>
        </div>
        <div class="card-body">
          <div class="chart-grid">
            <div class="chart-box"><canvas id="cpuChart"></canvas></div>
            <div class="chart-box"><canvas id="memChart"></canvas></div>
            <div class="chart-box"><canvas id="diskChart"></canvas></div>
            <div class="chart-box"><canvas id="netChart"></canvas></div>
          </div>
        </div>
      </div>
    </div>
  </div>

</section>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

