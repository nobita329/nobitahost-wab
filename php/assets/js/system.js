(function () {
  'use strict';

  var POLL_MS = 5000;
  var MAX_LIVE = 200;

  var state = {
    mode: 'live',
    history: [],
    live: [],
    charts: {}
  };

  function $(id) { return document.getElementById(id); }

  function setText(id, txt) {
    var el = $(id);
    if (el) el.textContent = txt;
  }

  function fmtBytes(n) {
    if (n == null || isNaN(n) || n < 0) return '0 B';
    var u = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    var i = 0, v = n;
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return ((i === 0 || v >= 100) ? Math.round(v) : v.toFixed(1)) + ' ' + u[i];
  }

  function fmtSpeed(n) { return fmtBytes(n) + '/s'; }

  function pctClass(v) { return v >= 90 ? 'danger' : v >= 70 ? 'warn' : 'ok'; }

  function setPbar(id, pct) {
    var el = $(id);
    if (!el) return;
    pct = Math.max(0, Math.min(100, pct || 0));
    var span = el.querySelector('span');
    if (span) span.style.width = pct + '%';
    el.className = 'pbar ' + pctClass(pct);
  }

  function fmtTime(ts) {
    var d = new Date(ts);
    var p = function (n) { return String(n).padStart(2, '0'); };
    var hh = p(d.getHours()), mm = p(d.getMinutes());
    if (state.mode === '7d' || state.mode === '30d') {
      return p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + hh + ':' + mm;
    }
    return hh + ':' + mm;
  }

  function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || '#3b82f6';
  }

  function render(d) {
    setText('sysStatus', d.health.status);
    var dot = $('sysStatusDot');
    if (dot) dot.className = 'status-dot ' + (d.health.status === 'online' ? 'online' : 'offline');
    setText('sysUptime', d.uptimeStr);
    setText('sysCores', d.cpu.cores);
    setText('sysFreq', d.cpu.freq ? d.cpu.freq + ' MHz' : '—');
    setText('hScore', d.health.score);
    setText('hScoreBig', d.health.score);
    setPbar('hScoreBar', d.health.score);

    setText('cpuUsagePct', Math.round(d.cpu.usage) + '%');
    setPbar('cpuBar', d.cpu.usage);
    setText('cpuLoad1', d.cpu.load[0]);
    setText('cpuLoad5', d.cpu.load[1]);
    setText('cpuLoad15', d.cpu.load[2]);
    setText('cpuCores', d.cpu.cores);
    setText('sysModel', d.cpu.model || '—');

    setText('memUsagePct', Math.round(d.mem.usage) + '%');
    setPbar('memBar', d.mem.usage);
    setText('memTotal', d.mem.totalStr || fmtBytes(d.mem.total));
    setText('memUsed', d.mem.usedStr || fmtBytes(d.mem.used));
    setText('memFree', d.mem.freeStr || fmtBytes(d.mem.free));
    setText('memSwap', d.mem.swapStr || (fmtBytes(d.mem.swapUsed) + ' / ' + fmtBytes(d.mem.swapTotal)));

    setText('diskUsagePct', Math.round(d.disk.usage) + '%');
    setPbar('diskBar', d.disk.usage);
    setText('diskTotal', d.disk.totalStr || fmtBytes(d.disk.total));
    setText('diskUsed', d.disk.usedStr || fmtBytes(d.disk.used));
    setText('diskFree', d.disk.freeStr || fmtBytes(d.disk.free));
    setText('diskRead', d.disk.ioReadStr || fmtSpeed(d.disk.ioRead));
    setText('diskWrite', d.disk.ioWriteStr || fmtSpeed(d.disk.ioWrite));

    setText('netRx', d.network.rxRateStr || fmtSpeed(d.network.rxRate));
    setText('netTx', d.network.txRateStr || fmtSpeed(d.network.txRate));
    setText('netTotalRx', d.network.totalRecvStr || fmtBytes(d.network.totalRecv));
    setText('netTotalTx', d.network.totalSentStr || fmtBytes(d.network.totalSent));
    setText('netConn', d.network.connections);
    setText('netIface', d.network.iface);

    setText('hTemp', d.health.tempStr || (d.health.temp != null ? d.health.temp.toFixed(1) + ' °C' : 'N/A'));
    setText('hProcs', d.health.processes);
    setText('hLoad', d.health.loadAvg);
    setText('sysBoot', d.bootTimeStr);
    setText('sysHost', d.hostname);
    setText('sysLastUpdate', 'Updated ' + new Date(d.now).toLocaleTimeString());
  }

  function makeChart(id, datasets, maxY) {
    var el = $(id);
    if (!el || typeof Chart === 'undefined') return null;
    var accent = cssVar('--accent');
    var alpha = /^#([0-9a-f]{6})$/i.test(accent) ? accent + '22' : 'rgba(59,130,246,.12)';
    var scales = {
      x: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#8b96ad', maxTicksLimit: 6, font: { size: 10 } } },
      y: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#8b96ad', font: { size: 10 } } }
    };
    if (maxY) scales.y.max = maxY;
    return new Chart(el, {
      type: 'line',
      data: { labels: [], datasets: datasets.map(function (ds) {
        return { label: ds.label, data: [], borderColor: ds.color, backgroundColor: alpha, borderWidth: 2, pointRadius: 0, tension: 0.3, fill: ds.fill !== false };
      }) },
      options: {
        responsive: true, maintainAspectRatio: false, animation: false,
        plugins: { legend: { display: false } },
        scales: scales
      }
    });
  }

  function initCharts() {
    var accent = cssVar('--accent');
    var green = '#22c55e';
    var violet = '#a78bfa';
    var amber = '#fbbf24';
    state.charts.cpu = makeChart('cpuChart', [{ label: 'CPU %', color: accent }], 100);
    state.charts.mem = makeChart('memChart', [{ label: 'RAM %', color: violet }], 100);
    state.charts.disk = makeChart('diskChart', [{ label: 'Disk %', color: amber }], 100);
    state.charts.net = makeChart('netChart', [
      { label: 'Down', color: green, fill: true },
      { label: 'Up', color: accent, fill: false }
    ]);
  }

  function rebuildCharts(points) {
    if (!points) return;
    var labels = points.map(function (p) { return fmtTime(p.ts); });
    var set = function (key, idx, data) {
      var c = state.charts[key];
      if (!c) return;
      c.data.labels = labels;
      c.data.datasets[idx].data = data;
      c.update('none');
    };
    set('cpu', 0, points.map(function (p) { return p.cpu; }));
    set('mem', 0, points.map(function (p) { return p.mem; }));
    set('disk', 0, points.map(function (p) { return p.disk; }));
    set('net', 0, points.map(function (p) { return p.rx; }));
    set('net', 1, points.map(function (p) { return p.tx; }));
  }

  function fetchHistory(range) {
    fetch('/system-history?range=' + encodeURIComponent(range), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success) {
          state.history = res.history || [];
          rebuildCharts(state.history);
        }
      })
      .catch(function () { /* noop */ });
  }

  function poll() {
    fetch('/system-data', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) return;
        var d = res.system;
        render(d);
        var pt = { ts: d.now, cpu: d.cpu.usage, mem: d.mem.usage, disk: d.disk.usage, rx: d.network.rxRate, tx: d.network.txRate };
        state.live.push(pt);
        if (state.live.length > MAX_LIVE) state.live.shift();
        if (state.mode === 'live') {
          rebuildCharts(state.live);
        } else {
          state.history.push(pt);
          rebuildCharts(state.history);
        }
      })
      .catch(function () { /* noop */ });
  }

  function wireRanges() {
    var wrap = $('sysRanges');
    if (!wrap) return;
    var buttons = wrap.querySelectorAll('button');
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        buttons.forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        state.mode = btn.getAttribute('data-range');
        if (state.mode === 'live') {
          rebuildCharts(state.live);
        } else {
          fetchHistory(state.mode);
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!$('sysMonitor')) return;
    initCharts();
    wireRanges();
    poll();
    setInterval(poll, POLL_MS);
  });
})();
