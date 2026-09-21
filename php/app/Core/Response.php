<?php
/** Response helpers: html, json, redirect, status, cookies. */
final class Response
{
    public static int $status = 200;
    private static array $headers = [];
    private static array $cookies = [];

    public static function status(int $code): void
    {
        self::$status = $code;
        if (!headers_sent()) http_response_code($code);
    }

    public static function header(string $name, string $value): void
    {
        self::$headers[$name] = $value;
        if (!headers_sent()) header("$name: $value");
    }

    public static function cookie(string $name, string $value, int $ttl = 0, bool $httpOnly = true): void
    {
        if (headers_sent()) return;
        setcookie($name, $value, [
            'expires' => $ttl ? time() + $ttl : 0,
            'path' => '/',
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ]);
    }

    public static function html(string $html, int $status = 200): string
    {
        self::status($status);
        self::header('Content-Type', 'text/html; charset=utf-8');
        echo $html;
        return $html;
    }

    public static function json($data, int $status = 200): string
    {
        self::status($status);
        self::header('Content-Type', 'application/json; charset=utf-8');
        $out = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo $out;
        return $out;
    }

    public static function text(string $text, int $status = 200, string $type = 'text/plain; charset=utf-8'): void
    {
        self::status($status);
        self::header('Content-Type', $type);
        echo $text;
    }

    public static function redirect(string $to, int $status = 302): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Location: ' . $to);
        }
        exit;
    }

    /** JSON shortcut used by API + AJAX endpoints. */
    public static function ok($data = []): string
    {
        return self::json(array_merge(['success' => true], is_array($data) ? $data : ['data' => $data]));
    }

    public static function fail(string $message, int $status = 400): string
    {
        return self::json(['success' => false, 'error' => $message], $status);
    }
}
