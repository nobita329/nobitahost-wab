<?php
/** Global template + app helpers. */

function e($v): string
{
    if ($v === null || $v === false) return '';
    if (is_array($v) || is_object($v)) $v = json_encode($v);
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Value getter that works for objects (PDO rows / normalized view data) and arrays. */
function v($row, string $key, $default = '')
{
    if (is_object($row)) {
        $val = $row->{$key} ?? null;
    } elseif (is_array($row)) {
        $val = $row[$key] ?? null;
    } else {
        return $default;
    }
    return $val === null ? $default : $val;
}

function setting(string $key, $default = ''): string
{
    $s = View::$globals['settings'] ?? null;
    $val = is_object($s) ? ($s->{$key} ?? null) : ($s[$key] ?? null);
    return (string) ($val === null || $val === '' ? $default : $val);
}

function setting_on(string $key): bool
{
    return setting($key) === 'on';
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

function url(string $path = '/'): string
{
    return '/' . ltrim($path, '/');
}

function current_user(): ?object
{
    return View::$globals['user'] ?? null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u && ($u->role ?? '') === 'admin';
}

/* -------- flash messages -------- */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* -------- CSRF -------- */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(?Request $req = null): bool
{
    $req = $req ?: Request::capture();
    $sent = (string) ($req->body['_csrf'] ?? $req->headers['x-csrf-token'] ?? '');
    return $sent !== '' && hash_equals(csrf_token(), $sent);
}

/* -------- formatting -------- */

function timeago(?string $ts): string
{
    if (!$ts) return '—';
    $t = strtotime(str_replace(' ', 'T', $ts) . 'Z');
    if (!$t) return $ts;
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return intdiv($d, 60) . 'm ago';
    if ($d < 86400) return intdiv($d, 3600) . 'h ago';
    if ($d < 86400 * 7) return intdiv($d, 86400) . 'd ago';
    return date('d M Y', $t);
}

function fmt_date(?string $ts, string $fmt = 'd M Y'): string
{
    if (!$ts) return '—';
    $t = strtotime(str_replace(' ', 'T', $ts) . 'Z');
    return $t ? date($fmt, $t) : $ts;
}

function fmt_bytes(float $bytes, int $dec = 1): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
    return round($bytes, $i === 0 ? 0 : $dec) . ' ' . $units[$i];
}

function excerpt(string $text, int $len = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len - 1) . '…' : $text;
}

function str_slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

function initials(string $name): string
{
    $parts = preg_split('/[\s_.-]+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= mb_strtoupper(mb_substr($p, 0, 1));
    return $out ?: 'U';
}

function avatar_of(?object $u): string
{
    if ($u && !empty($u->profile_pic)) return $u->profile_pic;
    return '/assets/img/avatar.svg';
}

/* -------- safe redirect target -------- */

function safe_back($to = null, string $fallback = '/'): string
{
    $to = is_string($to) ? $to : '';
    if ($to === '' || preg_match('#^(https?:)?//#i', $to)) return $fallback;
    return str_starts_with($to, '/') ? $to : $fallback;
}

/* -------- view shortcuts (usable inside templates) -------- */

function partial(string $template, array $data = []): void
{
    View::show($template, $data);
}

function section_start(string $name): void { View::start($name); }
function section_end(): void { View::end(); }
function section(string $name, string $default = ''): string { return View::section($name, $default); }

function icon(string $name, string $class = 'ic'): string
{
    return '<svg class="' . e($class) . '"><use href="#i-' . e($name) . '"/></svg>';
}

function json_attr($data): string
{
    return e(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
}

/* -------- JS-parity helpers used by ported templates -------- */

/** JS `.length` parity: works on arrays, strings, objects and null. */
function nh_count($x): int
{
    if ($x === null || $x === false) return 0;
    if (is_array($x)) return count($x);
    if (is_string($x)) return mb_strlen($x);
    if (is_object($x)) return count(get_object_vars($x));
    return 1;
}

/** JS `.slice(a, b)` parity for strings and arrays. */
function nh_slice($x, int $start = 0, $end = null)
{
    if (is_array($x)) {
        $x = array_values($x);
        $len = $end === null ? null : max(0, (int) $end - $start);
        return $len === null ? array_slice($x, $start) : array_slice($x, $start, $len);
    }
    $s = (string) ($x ?? '');
    $len = $end === null ? null : max(0, (int) $end - $start);
    return $len === null ? mb_substr($s, $start) : mb_substr($s, $start, $len);
}

/** JS `.replace(a, b)` parity — plain string or /regex/ pattern. */
function nh_replace($subject, $from, $to): string
{
    $subject = (string) ($subject ?? '');
    if (is_string($from) && preg_match('#^/(.*)/([a-z]*)$#s', $from, $m)) {
        return (string) preg_replace('#' . str_replace('#', '\#', $m[1]) . '#' . $m[2], (string) $to, $subject);
    }
    return str_replace((string) $from, (string) $to, $subject);
}

/** JS `.join()` parity. */
function nh_join($arr, string $glue = ''): string
{
    if (!is_array($arr)) return (string) $arr;
    return implode($glue, array_map(fn($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v), $arr));
}

/** Always an array (JS code often assumes []). */
function nh_arr($x): array
{
    if (is_array($x)) return $x;
    if (is_object($x)) return [$x];
    return [];
}

/** JS `new Date(x).toLocaleDateString()` parity. */
function nh_locale_date($ts, string $fmt = 'd M Y'): string
{
    if ($ts === null || $ts === '') return '—';
    if (is_numeric($ts)) return date($fmt, (int) $ts);
    $t = strtotime(str_replace(' ', 'T', (string) $ts) . (preg_match('/[Zz]$/', (string) $ts) ? '' : 'Z'));
    return $t ? date($fmt, $t) : (string) $ts;
}

/** `a ? a : b` for values that may be missing. */
function nh_or($a, $b = '')
{
    return ($a === null || $a === '' || $a === false || $a === []) ? $b : $a;
}

/** Percent-safe division for charts. */
function nh_pct($part, $total): float
{
    $total = (float) $total;
    return $total > 0 ? round(((float) $part / $total) * 100, 1) : 0.0;
}

/* -------- YouTube-style compact numbers (views call fmt() like the EJS did) -------- */
if (!function_exists('fmt')) {
    function fmt($n): string { return Youtube::fmt($n); }
}

/** JS `n.toLocaleString()` parity — thousands separators, no decimals. */
if (!function_exists('nh_num')) {
    function nh_num($n): string
    {
        if ($n === null || $n === '') return '';
        return is_numeric($n) ? number_format((float) $n) : (string) $n;
    }
}

/** JS `timeAgo()` parity from the Node routes: 1y/2mo/3w/4d/5h/6m ago, else "just now". */
if (!function_exists('nh_time_ago')) {
    function nh_time_ago($s): string
    {
        $t = strtotime(str_replace(' ', 'T', (string) $s) . 'Z');
        if (!$t) return '';
        $sec = max(1, (int) floor(time() - $t));
        foreach ([[31536000, 'y'], [2592000, 'mo'], [604800, 'w'], [86400, 'd'], [3600, 'h'], [60, 'm']] as [$n, $l]) {
            if ($sec >= $n) return intdiv($sec, $n) . $l . ' ago';
        }
        return 'just now';
    }
}

/** Wrap an array (often empty) in an object so templates can read `$x->key` safely. */
if (!function_exists('nh_obj')) {
    function nh_obj($data): object
    {
        if (is_object($data)) return $data;
        $o = new NhObj();
        foreach ((array) $data as $k => $v) $o->{(string) $k} = $v;
        return $o;
    }
}

/**
 * UNIQUE-column guard: does another row already own this value?
 * Keeps edit forms from tripping a raw SQL integrity error (node threw a 500 here too).
 */
if (!function_exists('nh_taken')) {
    function nh_taken(string $table, string $column, string $value, ?int $exceptId = null): bool
    {
        if ($value === '') return false;
        $sql = 'SELECT id FROM ' . $table . ' WHERE ' . $column . ' = ?'
             . ($exceptId !== null ? ' AND id != ?' : '') . ' LIMIT 1';
        return DB::first($sql, $exceptId !== null ? [$value, $exceptId] : [$value]) !== null;
    }
}
