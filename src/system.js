const os = require('os');
const fs = require('fs');
const path = require('path');
const db = require('./db');

const NUM_RE = /^\d+$/;
const SECTOR = 512;

let prevCpu = null;
let prevDisk = null;
let prevNet = null;
let insertCount = 0;

function readFile(p) {
  try { return fs.readFileSync(p, 'utf8'); } catch (e) { return ''; }
}

/* ---------- CPU ---------- */
function sampleCpuTimes() {
  let idle = 0, total = 0;
  for (const c of os.cpus()) {
    idle += c.times.idle;
    total += c.times.user + c.times.nice + c.times.sys + c.times.idle + c.times.irq;
  }
  return { idle, total };
}

function cpuUsage() {
  const cur = sampleCpuTimes();
  let usage = 0;
  if (prevCpu) {
    const id = cur.idle - prevCpu.idle;
    const tot = cur.total - prevCpu.total;
    usage = tot > 0 ? (100 * (1 - id / tot)) : 0;
  }
  prevCpu = cur;
  return Math.max(0, Math.min(100, usage));
}

/* ---------- Memory ---------- */
function swapInfo() {
  const out = { total: 0, used: 0, usage: 0 };
  const txt = readFile('/proc/meminfo');
  const mT = txt.match(/SwapTotal:\s+(\d+) kB/);
  const mF = txt.match(/SwapFree:\s+(\d+) kB/);
  if (mT) {
    const total = parseInt(mT[1], 10) * 1024;
    const free = mF ? parseInt(mF[1], 10) * 1024 : total;
    out.total = total;
    out.used = total - free;
    out.usage = total ? (out.used / total) * 100 : 0;
  }
  return out;
}

/* ---------- Disk ---------- */
function diskFs() {
  const out = { total: 0, used: 0, free: 0, usage: 0 };
  try {
    const st = fs.statfsSync('/');
    out.total = st.blocks * st.bsize;
    out.free = st.bavail * st.bsize;
    out.used = out.total - out.free;
    out.usage = out.total ? (out.used / out.total) * 100 : 0;
  } catch (e) { /* noop */ }
  return out;
}

function rootDevice() {
  for (const line of readFile('/proc/self/mounts').split('\n')) {
    const f = line.split(/\s+/);
    if (f[1] === '/' && f[0] && f[0] !== 'none' && f[0] !== 'overlay') return path.basename(f[0]);
  }
  return null;
}

function sampleDiskIo() {
  const dev = rootDevice();
  if (!dev) return { readBytes: 0, writeBytes: 0 };
  for (const line of readFile('/proc/diskstats').split('\n')) {
    const f = line.trim().split(/\s+/);
    if (f[2] === dev) {
      return {
        readBytes: (parseInt(f[5], 10) || 0) * SECTOR,
        writeBytes: (parseInt(f[9], 10) || 0) * SECTOR
      };
    }
  }
  return { readBytes: 0, writeBytes: 0 };
}

/* ---------- Network ---------- */
function sampleNet() {
  const lines = readFile('/proc/net/dev').split('\n');
  let best = null;
  for (const line of lines) {
    const m = line.trim().match(/^(\S+):\s+(.+)$/);
    if (!m) continue;
    const iface = m[1].replace(':', '');
    if (iface === 'lo') continue;
    const f = m[2].trim().split(/\s+/);
    const rxBytes = parseInt(f[0], 10) || 0;
    const txBytes = parseInt(f[8], 10) || 0;
    if (!best || rxBytes + txBytes > best.rxBytes + best.txBytes) {
      best = { iface, rxBytes, txBytes };
    }
  }
  return best || { iface: '-', rxBytes: 0, txBytes: 0 };
}

function activeConnections() {
  let count = 0;
  for (const file of ['/proc/net/tcp', '/proc/net/tcp6']) {
    const lines = readFile(file).split('\n').slice(1);
    for (const line of lines) {
      const f = line.trim().split(/\s+/);
      if (f[3] === '01') count++;
    }
  }
  return count;
}

function processCount() {
  try {
    return fs.readdirSync('/proc').filter((n) => NUM_RE.test(n)).length;
  } catch (e) { return 0; }
}

function cpuTemp() {
  for (let i = 0; i < 8; i++) {
    const type = readFile(`/sys/class/thermal/thermal_zone${i}/type`).trim();
    if (type && !/acpitz|fan/i.test(type) && /x86_pkg|cpu|core/i.test(type)) {
      const v = parseInt(readFile(`/sys/class/thermal/thermal_zone${i}/temp`).trim(), 10);
      if (v > 0) return v / 1000;
    }
  }
  for (let i = 0; i < 8; i++) {
    const v = parseInt(readFile(`/sys/class/thermal/thermal_zone${i}/temp`).trim(), 10);
    if (v > 0) return v / 1000;
  }
  return null;
}

/* ---------- Formatting ---------- */
function round1(n) { return Math.round(n * 10) / 10; }

function fmtBytes(n) {
  if (n == null || isNaN(n) || n < 0) return '0 B';
  const u = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
  let i = 0, v = n;
  while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
  return ((i === 0 || v >= 100) ? Math.round(v) : v.toFixed(1)) + ' ' + u[i];
}

function fmtSpeed(n) { return fmtBytes(n) + '/s'; }

function fmtUptime(sec) {
  const d = Math.floor(sec / 86400);
  const h = Math.floor((sec % 86400) / 3600);
  const m = Math.floor((sec % 3600) / 60);
  if (d) return `${d}d ${h}h ${m}m`;
  if (h) return `${h}h ${m}m`;
  return `${m}m ${Math.floor(sec % 60)}s`;
}

function fmtDateTime(epochSec) {
  const d = new Date(epochSec * 1000);
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

/* ---------- Snapshot ---------- */
function healthScore(cpu, mem, disk, load1, cores) {
  const loadPct = Math.min(100, (load1 / Math.max(1, cores)) * 100);
  const score = 100 - (cpu * 0.45 + mem * 0.25 + disk * 0.15 + loadPct * 0.15);
  return Math.max(0, Math.min(100, Math.round(score)));
}

function getSystemStats() {
  const cpus = os.cpus();
  const cpu = cpuUsage();
  const cores = cpus.length;
  const load = os.loadavg();
  const totalMem = os.totalmem();
  const freeMem = os.freemem();
  const usedMem = totalMem - freeMem;
  const memUsage = totalMem ? (usedMem / totalMem) * 100 : 0;
  const swap = swapInfo();
  const disk = diskFs();
  const netCur = sampleNet();
  const netRate = netRates(netCur);
  const diskCur = sampleDiskIo();
  const diskRate = diskRates(diskCur);
  const temp = cpuTemp();
  const uptime = os.uptime();
  const bootTime = Math.floor(Date.now() / 1000 - uptime);

  return {
    hostname: os.hostname(),
    platform: process.platform,
    arch: os.arch(),
    uptime,
    uptimeStr: fmtUptime(uptime),
    bootTime,
    bootTimeStr: fmtDateTime(bootTime),
    now: Date.now(),
    cpu: {
      usage: round1(cpu),
      load: load.map(round1),
      cores,
      freq: cpus[0] ? cpus[0].speed : 0,
      model: cpus[0] ? cpus[0].model.trim() : ''
    },
    mem: {
      total: totalMem, used: usedMem, free: freeMem,
      usage: round1(memUsage),
      swapTotal: swap.total, swapUsed: swap.used, swapUsage: round1(swap.usage),
      totalStr: fmtBytes(totalMem), usedStr: fmtBytes(usedMem), freeStr: fmtBytes(freeMem),
      swapStr: `${fmtBytes(swap.used)} / ${fmtBytes(swap.total)}`
    },
    disk: {
      total: disk.total, used: disk.used, free: disk.free,
      usage: round1(disk.usage),
      ioRead: diskRate.readRate, ioWrite: diskRate.writeRate,
      totalStr: fmtBytes(disk.total), usedStr: fmtBytes(disk.used), freeStr: fmtBytes(disk.free),
      ioReadStr: fmtSpeed(diskRate.readRate), ioWriteStr: fmtSpeed(diskRate.writeRate)
    },
    network: {
      iface: netCur.iface,
      totalRecv: netCur.rxBytes, totalSent: netCur.txBytes,
      rxRate: Math.round(netRate.rxRate), txRate: Math.round(netRate.txRate),
      connections: activeConnections(),
      totalRecvStr: fmtBytes(netCur.rxBytes), totalSentStr: fmtBytes(netCur.txBytes),
      rxRateStr: fmtSpeed(netRate.rxRate), txRateStr: fmtSpeed(netRate.txRate)
    },
    health: {
      status: 'online',
      temp,
      tempStr: temp != null ? `${temp.toFixed(1)} °C` : 'N/A',
      processes: processCount(),
      loadAvg: round1(load[0]),
      score: healthScore(cpu, memUsage, disk.usage, load[0], cores)
    }
  };
}

function netRates(cur) {
  if (!prevNet) { prevNet = { ...cur, at: Date.now() }; return { rxRate: 0, txRate: 0 }; }
  const dt = (Date.now() - prevNet.at) / 1000;
  const out = {
    rxRate: dt > 0 ? Math.max(0, (cur.rxBytes - prevNet.rxBytes) / dt) : 0,
    txRate: dt > 0 ? Math.max(0, (cur.txBytes - prevNet.txBytes) / dt) : 0
  };
  prevNet = { ...cur, at: Date.now() };
  return out;
}

function diskRates(cur) {
  if (!prevDisk) { prevDisk = { ...cur, at: Date.now() }; return { readRate: 0, writeRate: 0 }; }
  const dt = (Date.now() - prevDisk.at) / 1000;
  const out = {
    readRate: dt > 0 ? Math.max(0, (cur.readBytes - prevDisk.readBytes) / dt) : 0,
    writeRate: dt > 0 ? Math.max(0, (cur.writeBytes - prevDisk.writeBytes) / dt) : 0
  };
  prevDisk = { ...cur, at: Date.now() };
  return out;
}

/* ---------- History ---------- */
const WINDOWS = { '1h': 3600, '24h': 86400, '7d': 604800, '30d': 2592000 };

function recordSample(stats) {
  try {
    db.prepare('INSERT INTO system_stats (ts, cpu, mem, disk, rx, tx) VALUES (?, ?, ?, ?, ?, ?)')
      .run(Date.now(), round1(stats.cpu.usage), round1(stats.mem.usage), round1(stats.disk.usage), Math.round(stats.network.rxRate), Math.round(stats.network.txRate));
    if (++insertCount % 100 === 0) {
      db.prepare('DELETE FROM system_stats WHERE ts < ?').run(Date.now() - 31 * 86400 * 1000);
    }
  } catch (e) { /* noop */ }
}

function getHistory(range) {
  const secs = WINDOWS[range] || WINDOWS['24h'];
  let rows = [];
  try {
    rows = db.prepare('SELECT ts, cpu, mem, disk, rx, tx FROM system_stats WHERE ts >= ? ORDER BY ts ASC')
      .all(Date.now() - secs * 1000);
  } catch (e) { return []; }
  const MAX = 120;
  if (rows.length <= MAX) return rows;
  const out = [];
  const size = Math.ceil(rows.length / MAX);
  for (let i = 0; i < rows.length; i += size) {
    const slice = rows.slice(i, i + size);
    const n = slice.length;
    const sum = { ts: slice[0].ts, cpu: 0, mem: 0, disk: 0, rx: 0, tx: 0 };
    for (const r of slice) {
      sum.cpu += r.cpu; sum.mem += r.mem; sum.disk += r.disk; sum.rx += r.rx; sum.tx += r.tx;
    }
    out.push({
      ts: sum.ts,
      cpu: round1(sum.cpu / n),
      mem: round1(sum.mem / n),
      disk: round1(sum.disk / n),
      rx: Math.round(sum.rx / n),
      tx: Math.round(sum.tx / n)
    });
  }
  return out;
}

module.exports = {
  getSystemStats,
  recordSample,
  getHistory
};
