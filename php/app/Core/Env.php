<?php
/** Minimal .env loader (dotenv style). */
final class Env
{
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) return;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            if (strlen($v) > 1 && ($v[0] === '"' || $v[0] === "'") && $v[0] === substr($v, -1)) {
                $v = substr($v, 1, -1);
            }
            self::$vars[$k] = $v;
            if (getenv($k) === false) putenv("$k=$v");
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (isset(self::$vars[$key])) return self::$vars[$key];
        $v = getenv($key);
        return $v === false ? $default : $v;
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) (self::get($key) ?: $default);
    }
}
