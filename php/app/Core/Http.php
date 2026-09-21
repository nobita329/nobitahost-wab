<?php
/** Small cURL wrapper with on-disk caching (used for GitHub / YouTube / wallpapers / Cloudflare). */
final class Http
{
    public static int $timeout = 12;

    public static function request(string $url, array $opts = []): array
    {
        $ch = curl_init();
        $headers = $opts['headers'] ?? ['User-Agent: NobitaHost-PHP/1.0'];
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => (int) ($opts['timeout'] ?? self::$timeout),
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => is_array($headers) ? $headers : [$headers],
            CURLOPT_CUSTOMREQUEST => strtoupper($opts['method'] ?? 'GET'),
        ]);
        if (isset($opts['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json']));
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        } elseif (isset($opts['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        return ['ok' => $body !== false && $status >= 200 && $status < 400, 'status' => $status, 'body' => (string) $body, 'error' => $err];
    }

    public static function json(string $url, array $opts = [])
    {
        $res = self::request($url, $opts);
        if (!$res['ok']) return null;
        $decoded = json_decode($res['body'], true);
        return is_array($decoded) ? $decoded : null;
    }

    /** Cached GET: returns decoded json (array) or null. */
    public static function cached(string $url, int $ttl = 900, array $opts = [])
    {
        $file = NH_ROOT . '/storage/cache/' . sha1($url) . '.json';
        if (is_file($file) && (time() - (int) filemtime($file)) < $ttl) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) return $data;
        }
        $data = self::json($url, $opts);
        if ($data !== null) @file_put_contents($file, json_encode($data));
        return $data;
    }

    public static function download(string $url, string $dest, int $ttl = 0): bool
    {
        $body = self::request($url, ['timeout' => 25]);
        if (!$body['ok'] || $body['body'] === '') return false;
        $dir = dirname($dest);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return (bool) @file_put_contents($dest, $body['body']);
    }
}
