<?php
/** Request wrapper: method, path, query, body (json + form + files), cookies, ip. */
final class Request
{
    public string $method = 'GET';
    public string $path = '/';
    public array $query = [];
    public array $body = [];
    public array $files = [];
    public array $cookies = [];
    public array $headers = [];
    public string $ip = '';
    public string $ua = '';
    public string $referer = '';

    public static function boot(): void
    {
        self::$instance = null;
    }

    private static ?self $instance = null;

    /** Cached request instance for the current HTTP request. */
    public static function instance(): self
    {
        return self::$instance ??= self::capture();
    }

    public static function capture(): self
    {
        if (self::$instance) return self::$instance;
        $r = new self();
        $r->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $r->path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');
        if ($r->path !== '/' && str_ends_with($r->path, '/')) $r->path = rtrim($r->path, '/');
        $r->query = $_GET ?? [];
        $r->cookies = $_COOKIE ?? [];
        $r->files = $_FILES ?? [];

        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($r->method !== 'GET' && $r->method !== 'HEAD') {
            if (stripos($ct, 'application/json') !== false) {
                $raw = file_get_contents('php://input') ?: '';
                $decoded = json_decode($raw, true);
                $r->body = is_array($decoded) ? $decoded : [];
            } else {
                $r->body = $_POST ?? [];
            }
        }
        if ($r->method === 'POST' && isset($r->body['_method'])) {
            $r->method = strtoupper((string) $r->body['_method']);
        }

        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $r->headers[$name] = (string) $v;
            }
        }
        $r->headers['content-type'] = $ct;
        $r->ua = (string) ($r->headers['user-agent'] ?? '');
        $r->referer = (string) ($r->headers['referer'] ?? $r->headers['referrer'] ?? '');
        $xff = $r->headers['x-forwarded-for'] ?? '';
        $r->ip = $xff ? trim(explode(',', $xff)[0]) : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $r->ip = str_replace('::ffff:', '', $r->ip);
        return self::$instance = $r;
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function bool(string $key): bool
    {
        $v = $this->input($key);
        return in_array(strtolower((string) $v), ['1', 'on', 'true', 'yes', 'y'], true);
    }

    public function json(): bool
    {
        return stripos($this->headers['accept'] ?? '', 'application/json') !== false
            || str_starts_with($this->path, '/api/');
    }

    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) return null;
        return $f;
    }

    public function isBot(): bool
    {
        return (bool) preg_match('/bot|crawl|spider|slurp|archiver|wget|curl|python|java|perl|ruby|go-http|scrapy|headless|phantom|lighthouse|pagespeed|semrush|ahrefs|mj12bot|dotbot|bingbot|yandex|googlebot|applebot|facebookexternalhit|twitterbot|linkedinbot|pinterestbot|discordbot|slackbot|telegrambot|whatsapp|pagespeed/i', $this->ua);
    }
}
