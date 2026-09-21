<?php
/**
 * System stats (CPU / RAM / disk / network / health) — PHP port of src/system.js.
 * Returns the same JSON shape the front-end widgets expect.
 */
final class System
{
    private const SECTOR = 512;
    private static ?array $prev = null;
    private static int $insertCount = 0;

    private static function readFile(string $p): string
    {
        $d = @file_get_contents($p);
        return $d === false ? '' : $d;
    }

    private static function state(): array
    {
        if (self::$prev !== null) return self::$prev;
        $f = NH_ROOT . '/storage/cache/system-state.json';
        if (is_file($f)) {
            $d = json_decode((string) file_get_contents($f), true);
            if (is_array($d)) return self::$prev = $d;
        }
        return self::$prev = [];
    }

    private static function saveState(array $state): void
    {
        self::$prev = $state;
        @file_put_contents(NH_ROOT . '/storage/cache/system-state.json', json_encode($state));
    }

    /* ---------- CPU ---------- */

    private static function cpuTimes(): array
    {
        $txt = self::readFile('/proc/stat');
        if ($txt === '' || !preg_match('/^cpu\s+(.*)$/m', $txt, $m)) return ['idle' => 0, 'total' => 0, 'cores' => 0];
        $f = array_map('intval', preg_split('/\s+/', trim($m[1])) ?: []);
        $idle = ($f[3] ?? 0) + ($f[4] ?? 0);
        return ['idle' => $idle, 'total' => array_sum($f), 'cores' => max(1, self::coreCount())];
    }

    private static function coreCount(): int
    {
        $txt = self::readFile('/proc/cpuinfo');
        if ($txt !== '') {
            $n = preg_match_all('/^processor\s*:/m', $txt);
            if ($n) return (int) $n;
        }
        return 1;
    }

    private static function cpuModel(): string
    {
        $txt = self::readFile('/proc/cpuinfo');
        if ($txt !== '' && preg_match('/model name\s*:\s*(.+)$/m', $txt, $m)) return trim($m[1]);
        return php_uname('m');
    }

    private static function cpuUsage(): float
    {
        $cur = self::cpuTimes();
        $st = self::state();
        $usage = 0.0;
        if (isset($st['cpu']) && $cur['total'] > $st['cpu']['total']) {
            $id = $cur['idle'] - $st['cpu']['idle'];
            $tot = $cur['total'] - $st['cpu']['total'];
            $usage = $tot > 0 ? 100 * (1 - $id / $tot) : 0;
        }
        $st['cpu'] = ['idle' => $cur['idle'], 'total' => $cur['total']];
        self::saveState($st);
        return max(0, min(100, $usage));
    }

    private static function loadAvg(): array
    {
        $txt = trim(self::readFile('/proc/loadavg'));
        if ($txt !== '') {
            $f = preg_split('/\s+/', $txt) ?: [];
            return [round((float) ($f[0] ?? 0), 1), round((float) ($f[1] ?? 0), 1), round((float) ($f[2] ?? 0), 1)];
        }
        if (function_exists('sys_getloadavg')) {
            $l = @sys_getloadavg() ?: [0, 0, 0];
            return [round($l[0], 1), round($l[1], 1), round($l[2], 1)];
        }
        return [0.0, 0.0, 0.0];
    }

    /* ---------- Memory ---------- */

    private static function memInfo(): array
    {
        $txt = self::readFile('/proc/meminfo');
        $kb = function (string $key) use ($txt): int {
            return preg_match('/' . $key . ':\s+(\d+) kB/', $txt, $m) ? (int) $m[1] * 1024 : 0;
        };
        $total = $kb('MemTotal');
        $avail = $kb('MemAvailable') ?: $kb('MemFree');
        if ($total === 0) {
            $total = 2 * 1024 * 1024 * 1024;
            $avail = (int) ($total * 0.55);
        }
        $used = max(0, $total - $avail);
        $swapTotal = $kb('SwapTotal');
        $swapFree = $kb('SwapFree');
        return [
            'total' => $total,
            'used' => $used,
            'free' => $avail,
            'usage' => $total ? ($used / $total) * 100 : 0,
            'swapTotal' => $swapTotal,
            'swapUsed' => max(0, $swapTotal - $swapFree),
            'swapUsage' => $swapTotal ? (($swapTotal - $swapFree) / $swapTotal) * 100 : 0,
        ];
    }

    /* ---------- Disk ---------- */

    private static function diskFs(): array
    {
        $path = NH_ROOT;
        $total = (float) @disk_total_space($path);
        $free = (float) @disk_free_space($path);
        if (!$total) { $total = 100 * 1024 * 1024 * 1024; $free = $total * 0.6; }
        $used = max(0, $total - $free);
        return ['total' => $total, 'used' => $used, 'free' => $free, 'usage' => $total ? ($used / $total) * 100 : 0];
    }

    private static function rootDevice(): ?string
    {
        foreach (explode("\n", self::readFile('/proc/self/mounts')) as $line) {
            $f = preg_split('/\s+/', trim($line)) ?: [];
            if (($f[1] ?? '') === '/' && ($f[0] ?? '') !== '' && !in_array($f[0], ['none', 'overlay'], true)) {
                return basename($f[0]);
            }
        }
        return null;
    }

    private static function diskIo(): array
    {
        $dev = self::rootDevice();
        if (!$dev) return ['read' => 0, 'write' => 0];
        foreach (explode("\n", self::readFile('/proc/diskstats')) as $line) {
            $f = preg_split('/\s+/', trim($line)) ?: [];
            if (($f[2] ?? '') === $dev) {
                return ['read' => ((int) ($f[5] ?? 0)) * self::SECTOR, 'write' => ((int) ($f[9] ?? 0)) * self::SECTOR];
            }
        }
        return ['read' => 0, 'write' => 0];
    }

    /* ---------- Network ---------- */

    private static function netSample(): array
    {
        $best = null;
        foreach (explode("\n", self::readFile('/proc/net/dev')) as $line) {
            if (!preg_match('/^\s*(\S+):\s+(.+)$/', $line, $m)) continue;
            $iface = str_replace(':', '', $m[1]);
            if ($iface === 'lo') continue;
            $f = preg_split('/\s+/', trim($m[2])) ?: [];
            $rx = (int) ($f[0] ?? 0);
            $tx = (int) ($f[8] ?? 0);
            if (!$best || ($rx + $tx) > ($best['rx'] + $best['tx'])) $best = ['iface' => $iface, 'rx' => $rx, 'tx' => $tx];
        }
        return $best ?: ['iface' => '-', 'rx' => 0, 'tx' => 0];
    }

    private static function connections(): int
    {
        $count = 0;
        foreach (['/proc/net/tcp', '/proc/net/tcp6'] as $file) {
            $lines = array_slice(explode("\n", self::readFile($file)), 1);
            foreach ($lines as $line) {
                $f = preg_split('/\s+/', trim($line)) ?: [];
                if (($f[3] ?? '') === '01') $count++;
            }
        }
        return $count;
    }

    private static function processes(): int
    {
        $n = 0;
        foreach ((array) @scandir('/proc') as $entry) {
            if (ctype_digit((string) $entry)) $n++;
        }
        return $n;
    }

    private static function temp(): ?float
    {
        for ($i = 0; $i < 8; $i++) {
            $type = trim(self::readFile("/sys/class/thermal/thermal_zone{$i}/type"));
            if ($type !== '' && !preg_match('/acpitz|fan/i', $type) && preg_match('/x86_pkg|cpu|core/i', $type)) {
                $v = (int) trim(self::readFile("/sys/class/thermal/thermal_zone{$i}/temp"));
                if ($v > 0) return $v / 1000;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $v = (int) trim(self::readFile("/sys/class/thermal/thermal_zone{$i}/temp"));
            if ($v > 0) return $v / 1000;
        }
        return null;
    }

    /* ---------- formatting ---------- */

    private static function round1(float $n): float
    {
        return round($n * 10) / 10;
    }

    public static function fmtBytes($n): string
    {
        if ($n === null || !is_numeric($n) || $n < 0) return '0 B';
        $u = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = 0;
        $v = (float) $n;
        while ($v >= 1024 && $i < count($u) - 1) { $v /= 1024; $i++; }
        return (($i === 0 || $v >= 100) ? (string) round($v) : number_format($v, 1)) . ' ' . $u[$i];
    }

    private static function fmtSpeed($n): string
    {
        return self::fmtBytes($n) . '/s';
    }

    private static function fmtUptime(float $sec): string
    {
        $d = intdiv((int) $sec, 86400);
        $h = intdiv(((int) $sec % 86400), 3600);
        $m = intdiv(((int) $sec % 3600), 60);
        if ($d) return "{$d}d {$h}h {$m}m";
        if ($h) return "{$h}h {$m}m";
        return $m . 'm ' . ((int) $sec % 60) . 's';
    }

    private static function healthScore(float $cpu, float $mem, float $disk, float $load1, int $cores): int
    {
        $loadPct = min(100, ($load1 / max(1, $cores)) * 100);
        $score = 100 - ($cpu * 0.45 + $mem * 0.25 + $disk * 0.15 + $loadPct * 0.15);
        return (int) max(0, min(100, round($score)));
    }

    /* ---------- snapshot ---------- */

    public static function stats(): array
    {
        $state = self::state();
        $now = (int) (microtime(true) * 1000);

        $cpu = self::cpuUsage();
        $cores = self::coreCount();
        $load = self::loadAvg();
        $mem = self::memInfo();
        $disk = self::diskFs();
        $net = self::netSample();
        $io = self::diskIo();
        $temp = self::temp();

        // rates from the previous snapshot
        $dt = isset($state['at']) ? max(0.001, ($now - $state['at']) / 1000) : 0;
        $rxRate = $dt > 0 && isset($state['net']) ? max(0, ($net['rx'] - $state['net']['rx']) / $dt) : 0;
        $txRate = $dt > 0 && isset($state['net']) ? max(0, ($net['tx'] - $state['net']['tx']) / $dt) : 0;
        $readRate = $dt > 0 && isset($state['io']) ? max(0, ($io['read'] - $state['io']['read']) / $dt) : 0;
        $writeRate = $dt > 0 && isset($state['io']) ? max(0, ($io['write'] - $state['io']['write']) / $dt) : 0;

        self::saveState(['at' => $now, 'net' => ['rx' => $net['rx'], 'tx' => $net['tx']], 'io' => $io]);

        $uptime = self::uptime();
        $bootTime = (int) (time() - $uptime);

        return [
            'hostname' => php_uname('n'),
            'platform' => PHP_OS_FAMILY === 'Windows' ? 'win32' : strtolower(PHP_OS_FAMILY ?: 'linux'),
            'arch' => php_uname('m'),
            'php' => PHP_VERSION,
            'uptime' => $uptime,
            'uptimeStr' => self::fmtUptime($uptime),
            'bootTime' => $bootTime,
            'bootTimeStr' => date('Y-m-d H:i', $bootTime),
            'now' => $now,
            'cpu' => [
                'usage' => self::round1($cpu),
                'load' => $load,
                'cores' => $cores,
                'freq' => 0,
                'model' => self::cpuModel(),
            ],
            'mem' => [
                'total' => $mem['total'], 'used' => $mem['used'], 'free' => $mem['free'],
                'usage' => self::round1($mem['usage']),
                'swapTotal' => $mem['swapTotal'], 'swapUsed' => $mem['swapUsed'], 'swapUsage' => self::round1($mem['swapUsage']),
                'totalStr' => self::fmtBytes($mem['total']), 'usedStr' => self::fmtBytes($mem['used']), 'freeStr' => self::fmtBytes($mem['free']),
                'swapStr' => self::fmtBytes($mem['swapUsed']) . ' / ' . self::fmtBytes($mem['swapTotal']),
            ],
            'disk' => [
                'total' => $disk['total'], 'used' => $disk['used'], 'free' => $disk['free'],
                'usage' => self::round1($disk['usage']),
                'ioRead' => $readRate, 'ioWrite' => $writeRate,
                'totalStr' => self::fmtBytes($disk['total']), 'usedStr' => self::fmtBytes($disk['used']), 'freeStr' => self::fmtBytes($disk['free']),
                'ioReadStr' => self::fmtSpeed($readRate), 'ioWriteStr' => self::fmtSpeed($writeRate),
            ],
            'network' => [
                'iface' => $net['iface'],
                'totalRecv' => $net['rx'], 'totalSent' => $net['tx'],
                'rxRate' => (int) round($rxRate), 'txRate' => (int) round($txRate),
                'connections' => self::connections(),
                'totalRecvStr' => self::fmtBytes($net['rx']), 'totalSentStr' => self::fmtBytes($net['tx']),
                'rxRateStr' => self::fmtSpeed($rxRate), 'txRateStr' => self::fmtSpeed($txRate),
            ],
            'health' => [
                'status' => 'online',
                'temp' => $temp,
                'tempStr' => $temp !== null ? number_format($temp, 1) . ' °C' : 'N/A',
                'processes' => self::processes(),
                'loadAvg' => self::round1($load[0]),
                'score' => self::healthScore($cpu, $mem['usage'], $disk['usage'], $load[0], $cores),
            ],
        ];
    }

    private static function uptime(): float
    {
        $txt = trim(self::readFile('/proc/uptime'));
        if ($txt !== '' && preg_match('/^([\d.]+)/', $txt, $m)) return (float) $m[1];
        return (float) (time() - ($_SERVER['REQUEST_TIME'] ?? time()));
    }

    public static function recordSample(array $stats): void
    {
        try {
            DB::run(
                'INSERT INTO system_stats (ts, cpu, mem, disk, rx, tx) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    (int) (microtime(true) * 1000),
                    self::round1((float) $stats['cpu']['usage']),
                    self::round1((float) $stats['mem']['usage']),
                    self::round1((float) $stats['disk']['usage']),
                    (int) round((float) $stats['network']['rxRate']),
                    (int) round((float) $stats['network']['txRate']),
                ]
            );
            if (++self::$insertCount % 100 === 0) {
                DB::run('DELETE FROM system_stats WHERE ts < ?', [(int) (microtime(true) * 1000) - 31 * 86400 * 1000]);
            }
        } catch (Throwable $e) { /* noop */ }
    }

    public static function history(string $range = '24h'): array
    {
        $windows = ['1h' => 3600, '24h' => 86400, '7d' => 604800, '30d' => 2592000];
        $secs = $windows[$range] ?? $windows['24h'];
        try {
            $rows = DB::all('SELECT ts, cpu, mem, disk, rx, tx FROM system_stats WHERE ts >= ? ORDER BY ts ASC', [(int) (microtime(true) * 1000) - $secs * 1000]);
        } catch (Throwable $e) { return []; }

        $MAX = 120;
        $n = count($rows);
        if ($n <= $MAX) {
            return array_map(fn($r) => ['ts' => (int) $r->ts, 'cpu' => (float) $r->cpu, 'mem' => (float) $r->mem, 'disk' => (float) $r->disk, 'rx' => (int) $r->rx, 'tx' => (int) $r->tx], $rows);
        }
        $out = [];
        $size = (int) ceil($n / $MAX);
        foreach (array_chunk($rows, $size) as $slice) {
            $c = count($slice);
            $sum = ['ts' => (int) $slice[0]->ts, 'cpu' => 0.0, 'mem' => 0.0, 'disk' => 0.0, 'rx' => 0, 'tx' => 0];
            foreach ($slice as $r) {
                $sum['cpu'] += (float) $r->cpu; $sum['mem'] += (float) $r->mem; $sum['disk'] += (float) $r->disk;
                $sum['rx'] += (int) $r->rx; $sum['tx'] += (int) $r->tx;
            }
            $out[] = [
                'ts' => $sum['ts'],
                'cpu' => self::round1($sum['cpu'] / $c),
                'mem' => self::round1($sum['mem'] / $c),
                'disk' => self::round1($sum['disk'] / $c),
                'rx' => (int) round($sum['rx'] / $c),
                'tx' => (int) round($sum['tx'] / $c),
            ];
        }
        return $out;
    }

    /** Storage devices list for the CasaOS "Storage" panel. */
    public static function storageDevices(): array
    {
        $devices = [];
        $mounts = self::readFile('/proc/self/mounts');
        if ($mounts !== '') {
            foreach (explode("\n", $mounts) as $line) {
                $f = preg_split('/\s+/', trim($line)) ?: [];
                if (count($f) < 3) continue;
                [$dev, $point, $fs] = [$f[0], $f[1], $f[2]];
                if (!str_starts_with($dev, '/') || in_array($fs, ['squashfs', 'tmpfs', 'devtmpfs', 'overlay'], true)) continue;
                if (isset($devices[$point])) continue;
                $total = (float) @disk_total_space($point);
                if ($total <= 0) continue;
                $free = (float) @disk_free_space($point);
                $devices[$point] = [
                    'device' => basename($dev),
                    'mount' => $point,
                    'fs' => $fs,
                    'total' => $total,
                    'free' => $free,
                    'used' => $total - $free,
                    'usage' => round((($total - $free) / $total) * 100, 1),
                    'totalStr' => self::fmtBytes($total),
                    'usedStr' => self::fmtBytes($total - $free),
                    'freeStr' => self::fmtBytes($free),
                ];
            }
        }
        if (!$devices) {
            $d = self::diskFs();
            $devices['/'] = [
                'device' => 'root', 'mount' => '/', 'fs' => 'ext4',
                'total' => $d['total'], 'free' => $d['free'], 'used' => $d['used'],
                'usage' => round($d['usage'], 1),
                'totalStr' => self::fmtBytes($d['total']), 'usedStr' => self::fmtBytes($d['used']), 'freeStr' => self::fmtBytes($d['free']),
            ];
        }
        return array_values($devices);
    }
}
