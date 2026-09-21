<?php
/** YouTube channel + videos — PHP port of src/youtube.js (API key or scrape/RSS). */
final class Youtube
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125 Safari/537.36';
    private const TTL = 600;

    public static function parseNumber($str): ?int
    {
        if ($str === null) return null;
        $s = str_replace(',', '', trim((string) $str));
        if (!preg_match('/^([\d.]+)\s*([KMB])?$/i', $s, $m)) return null;
        $n = (float) $m[1];
        if (is_nan($n)) return null;
        $mult = ['k' => 1e3, 'm' => 1e6, 'b' => 1e9][strtolower($m[2] ?? '')] ?? 1;
        return (int) round($n * $mult);
    }

    public static function fmt($n): string
    {
        if ($n === null || !is_numeric($n)) return '—';
        $n = (float) $n;
        $fmt = function (float $v, string $suffix): string {
            $s = number_format($v, 1);
            return preg_replace('/\.0$/', '', $s) . $suffix;
        };
        if ($n >= 1e9) return $fmt($n / 1e9, 'B');
        if ($n >= 1e6) return $fmt($n / 1e6, 'M');
        if ($n >= 1e3) return $fmt($n / 1e3, 'K');
        return (string) (int) $n;
    }

    public static function decodeXml(string $s): string
    {
        if ($s === '') return '';
        $s = str_replace(['&amp;', '&lt;', '&gt;', '&quot;', '&#39;', '&apos;'], ['&', '<', '>', '"', "'", "'"], $s);
        return (string) preg_replace_callback('/&#(\d+);/', fn($m) => mb_chr((int) $m[1]), $s);
    }

    public static function extractHandle($channel): ?string
    {
        if (!$channel) return null;
        $h = trim((string) $channel);
        if (str_starts_with($h, 'http')) {
            if (preg_match('/youtube\.com\/@([\w.-]+)/', $h, $m)) return $m[1];
            if (preg_match('/youtube\.com\/channel\/(UC[\w-]{22})/', $h, $m)) return $m[1];
            return null;
        }
        return str_starts_with($h, '@') ? substr($h, 1) : $h;
    }

    private static function fetchText(string $url): string
    {
        $res = Http::request($url, ['headers' => ['User-Agent: ' . self::UA, 'Accept: text/html'], 'timeout' => 15]);
        if (!$res['ok']) throw new RuntimeException('YouTube request failed (' . $res['status'] . ')');
        return $res['body'];
    }

    private static function fetchJson(string $url): array
    {
        $res = Http::request($url, ['headers' => ['User-Agent: ' . self::UA], 'timeout' => 15]);
        if (!$res['ok']) throw new RuntimeException('YouTube API request failed (' . $res['status'] . ')');
        $data = json_decode($res['body'], true);
        if (!is_array($data)) throw new RuntimeException('YouTube API returned invalid JSON');
        return $data;
    }

    public static function parseRss(string $xml): array
    {
        $out = [];
        if (!preg_match_all('/<entry>([\s\S]*?)<\/entry>/', $xml, $entries)) return $out;
        foreach ($entries[1] as $e) {
            $vid = null;
            if (preg_match('/<yt:videoId>([\w-]{6,})<\/yt:videoId>/', $e, $m)) $vid = $m[1];
            elseif (preg_match('/yt:video:([\w-]{6,})/', $e, $m)) $vid = $m[1];
            $title = preg_match('/<title>([\s\S]*?)<\/title>/', $e, $m) ? self::decodeXml($m[1]) : '';
            $pub = preg_match('/<published>([\s\S]*?)<\/published>/', $e, $m) ? $m[1] : '';
            $thumb = preg_match('/<media:thumbnail url="([^"]*)"/', $e, $m) ? $m[1] : '';
            if (!$vid && $thumb && preg_match('/\/vi\/([\w-]+)\//', $thumb, $m)) $vid = $m[1];
            if (!$vid) continue;
            $out[] = [
                'id' => $vid,
                'title' => $title ?: 'Untitled',
                'published' => $pub,
                'thumb' => $thumb ?: 'https://i.ytimg.com/vi/' . $vid . '/hqdefault.jpg',
                'views' => null,
                'likes' => null,
            ];
        }
        return $out;
    }

    private static function resolveFromApi(string $handle, string $apiKey): array
    {
        $data = self::fetchJson('https://www.googleapis.com/youtube/v3/channels?part=snippet,statistics,contentDetails&forHandle=' . rawurlencode($handle) . '&key=' . rawurlencode($apiKey));
        $it = $data['items'][0] ?? null;
        if (!$it) throw new RuntimeException('Channel not found');
        $st = $it['statistics'] ?? [];
        $sn = $it['snippet'] ?? [];
        $thumbs = $sn['thumbnails'] ?? [];
        return [
            'id' => $it['id'] ?? '',
            'title' => $sn['title'] ?? '',
            'thumb' => $thumbs['high']['url'] ?? $thumbs['medium']['url'] ?? $thumbs['default']['url'] ?? '',
            'handle' => $handle,
            'subscribers' => (int) ($st['subscriberCount'] ?? 0),
            'views' => (int) ($st['viewCount'] ?? 0),
            'likes' => isset($st['likeCount']) ? (int) $st['likeCount'] : null,
            'videoCount' => (int) ($st['videoCount'] ?? 0),
            'uploadsPlaylist' => $it['contentDetails']['relatedPlaylists']['uploads'] ?? null,
        ];
    }

    private static function resolveFromScrape(string $handle): array
    {
        $html = self::fetchText('https://www.youtube.com/@' . $handle);
        $id = null;
        if (preg_match('/"browseId":"(UC[\w-]{22})"/', $html, $m)) $id = $m[1];
        elseif (preg_match('/youtube\.com\/channel\/(UC[\w-]{22})/', $html, $m)) $id = $m[1];
        if (!$id) throw new RuntimeException('Could not resolve channel id');

        $subs = preg_match('/([\d.,]+[KMB]?)\s*(?:subscribers|Subscribers)/', $html, $m) ? self::parseNumber($m[1]) : null;
        $views = null;
        if (preg_match_all('/([\d.,]+[KMB]?)\s*(?:views|Views)/', $html, $vm)) {
            $nums = array_map([self::class, 'parseNumber'], $vm[1]);
            $nums = array_values(array_filter($nums, fn($n) => $n !== null));
            if ($nums) $views = end($nums);
        }
        $avatar = '';
        if (preg_match('/"avatar":\{"thumbnails":\[\{"url":"(https:\\\\?\\\\?\/\\\\?\/?yt3[^"]+)"/', $html, $am)) {
            $avatar = str_replace(['\\u002F', '\\/'], ['/', '/'], $am[1]);
        }
        if ($avatar === '' && preg_match('/https:\/\/yt3\.googleusercontent\.com\/[\w-]+=s\d+/', $html, $ym)) {
            $avatar = $ym[0];
        }
        return ['id' => $id, 'handle' => $handle, 'subscribers' => $subs ?? 0, 'views' => $views, 'thumb' => $avatar, 'title' => '@' . $handle];
    }

    private static function apiVideos(string $uploadsPlaylist, string $apiKey): array
    {
        $out = [];
        $token = '';
        do {
            $url = 'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=' . $uploadsPlaylist
                . '&maxResults=50' . ($token !== '' ? '&pageToken=' . $token : '') . '&key=' . $apiKey;
            $data = self::fetchJson($url);
            $items = $data['items'] ?? [];
            $ids = [];
            foreach ($items as $i) {
                $vid = $i['snippet']['resourceId']['videoId'] ?? null;
                if ($vid) $ids[] = $vid;
            }
            if ($ids) {
                $stats = self::fetchJson('https://www.googleapis.com/youtube/v3/videos?part=snippet,statistics&id=' . implode(',', $ids) . '&key=' . $apiKey);
                $statMap = [];
                foreach ($stats['items'] ?? [] as $v) {
                    $statMap[$v['id']] = [
                        'views' => (int) ($v['statistics']['viewCount'] ?? 0),
                        'likes' => (int) ($v['statistics']['likeCount'] ?? 0),
                    ];
                }
                foreach ($items as $i) {
                    $sn = $i['snippet'] ?? [];
                    $vid = $sn['resourceId']['videoId'] ?? null;
                    if (!$vid) continue;
                    $thumbs = $sn['thumbnails'] ?? [];
                    $out[] = [
                        'id' => $vid,
                        'title' => $sn['title'] ?? '',
                        'published' => $sn['publishedAt'] ?? '',
                        'thumb' => $thumbs['high']['url'] ?? $thumbs['medium']['url'] ?? $thumbs['default']['url'] ?? ('https://i.ytimg.com/vi/' . $vid . '/hqdefault.jpg'),
                        'views' => $statMap[$vid]['views'] ?? 0,
                        'likes' => $statMap[$vid]['likes'] ?? 0,
                    ];
                }
            }
            $token = $data['nextPageToken'] ?? '';
        } while ($token !== '' && count($out) < 300);
        return $out;
    }

    /** @return array{channel:array, videos:array}|null */
    public static function getData($settings): ?array
    {
        $channelInput = is_object($settings) ? (string) ($settings->youtube_channel ?? '') : (string) ($settings['youtube_channel'] ?? '');
        $apiKey = is_object($settings) ? (string) ($settings->youtube_api_key ?? '') : (string) ($settings['youtube_api_key'] ?? '');
        if ($channelInput === '') return null;

        $cacheKey = ($apiKey !== '' ? $apiKey : 'nokey') . '::' . $channelInput;
        $cacheFile = NH_ROOT . '/storage/cache/yt-' . sha1($cacheKey) . '.json';
        if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < self::TTL) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) return $cached;
        }

        try {
            $handle = self::extractHandle($channelInput);
            if (!$handle) return null;
            if ($apiKey !== '') {
                $channel = self::resolveFromApi($handle, $apiKey);
                $videos = $channel['uploadsPlaylist'] ? self::apiVideos($channel['uploadsPlaylist'], $apiKey) : [];
            } else {
                $channel = self::resolveFromScrape($handle);
                $xml = '';
                try {
                    $xml = self::fetchText('https://www.youtube.com/feeds/videos.xml?channel_id=' . $channel['id']);
                } catch (Throwable $e) {
                    $xml = '';
                }
                $videos = $xml !== '' ? self::parseRss($xml) : [];
                if ($xml !== '' && preg_match('/<title>([\s\S]*?)<\/title>/', $xml, $tm)) {
                    $channel['title'] = self::decodeXml($tm[1]);
                }
            }
            $result = ['channel' => $channel, 'videos' => $videos];
            @file_put_contents($cacheFile, json_encode($result));
            return $result;
        } catch (Throwable $e) {
            error_log('[youtube] ' . $e->getMessage());
            return null;
        }
    }
}
