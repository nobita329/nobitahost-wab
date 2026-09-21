<?php
/** 4kwallpapers.com browser — PHP port of src/wallpapers.js. */
final class Wallpapers
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
    private const TTL = 600;

    public const CATEGORIES = [
        ['slug' => 'all', 'label' => 'All Categories', 'path' => '/'],
        ['slug' => 'most-popular-4k-wallpapers', 'label' => 'Most Popular'],
        ['slug' => 'best-4k-wallpapers', 'label' => 'Best 4K'],
        ['slug' => 'cool-wallpapers', 'label' => 'Cool'],
        ['slug' => 'aesthetic-wallpapers', 'label' => 'Aesthetic'],
        ['slug' => 'cute-kawaii-wallpapers', 'label' => 'Cute Kawaii'],
        ['slug' => 'black-dark', 'label' => 'Black & Dark'],
        ['slug' => 'pitch-black-wallpapers', 'label' => 'Pitch Black'],
        ['slug' => 'space', 'label' => 'Space'],
        ['slug' => 'nature', 'label' => 'Nature'],
        ['slug' => 'abstract', 'label' => 'Abstract'],
        ['slug' => 'anime', 'label' => 'Anime'],
        ['slug' => 'cars', 'label' => 'Cars'],
        ['slug' => 'games', 'label' => 'Games'],
        ['slug' => 'technology', 'label' => 'Technology'],
        ['slug' => 'cr7-wallpapers', 'label' => 'CR7'],
        ['slug' => 'aot-wallpapers', 'label' => 'Attack on Titan'],
        ['slug' => 'hollow-knight-wallpapers', 'label' => 'Hollow Knight'],
        ['slug' => 'random-wallpapers', 'label' => 'Random'],
        ['slug' => '5k-wallpapers', 'label' => '5K'],
        ['slug' => '8k-wallpapers', 'label' => '8K'],
        ['slug' => 'ultrawide-monitor-hd-wallpapers', 'label' => 'Ultrawide HD'],
        ['slug' => 'ultrawide-monitor-3440-wallpapers', 'label' => 'Ultrawide 3440'],
        ['slug' => 'dual-monitor-hd-wallpapers', 'label' => 'Dual Monitor'],
        ['slug' => 'windows-11-stock-wallpapers', 'label' => 'Windows 11 Stock'],
        ['slug' => 'macos-27-golden-wallpapers', 'label' => 'macOS Golden'],
        ['slug' => 'aluminium-os-stock-wallpapers', 'label' => 'Aluminium OS'],
        ['slug' => 'iphone-17-pro-wallpapers', 'label' => 'iPhone 17 Pro'],
        ['slug' => 'uncompressed-png-wallpapers', 'label' => 'Uncompressed PNG'],
    ];

    public static function buildUrl(?string $cat, int $page = 1, string $q = ''): string
    {
        $p = max(1, $page);
        $query = trim($q);
        if ($query !== '') {
            $url = 'https://4kwallpapers.com/search/?text=' . rawurlencode($query);
            if ($p > 1) $url .= '&page=' . $p;
        } elseif (!$cat || $cat === 'all' || $cat === 'none') {
            $url = 'https://4kwallpapers.com/';
            if ($p > 1) $url .= '?page=' . $p;
        } else {
            $url = 'https://4kwallpapers.com/' . rawurlencode($cat) . '/';
            if ($p > 1) $url .= '?page=' . $p;
        }
        return $url;
    }

    public static function parsePage(string $html, int $page): array
    {
        $items = [];
        $blocks = explode('<p itemprop="associatedMedia"', $html);
        foreach ($blocks as $b) {
            $hasContent = preg_match('/contentUrl" href="([^"]+)"/', $b, $contentM);
            $hasThumb = preg_match('/<img itemprop="thumbnail" src="([^"]+)"/', $b, $thumbM);
            if (!$hasContent || !$hasThumb) continue;
            $hasLink = preg_match('#href="(https://4kwallpapers\.com/[a-z0-9-]+/[a-z0-9-]+-(\d+)\.html)"#', $b, $linkM);
            $hasCapt = preg_match('/class="title(?:2| tags)">([^<]*)/', $b, $captM);
            $hasAlt = preg_match('/alt="([^"]+)"/', $b, $altM);

            $thumb = $thumbM[1];
            $ext = preg_match('/\.(jpg|png|webp)$/i', $thumb, $extM) ? $extM[1] : 'jpg';
            $id = $hasLink ? $linkM[2] : (preg_match('/(\d+)\.\w+$/', $contentM[1], $idM) ? $idM[1] : '');
            $full = $contentM[1];
            if (!str_contains($full, '/wallpapers/') && $hasLink) {
                $parts = explode('/', $linkM[1]);
                $slug = preg_replace('/-\d+\.html$/', '', (string) end($parts));
                $full = "https://4kwallpapers.com/images/wallpapers/{$slug}--{$id}.{$ext}";
            }
            $title = ($hasCapt && trim($captM[1]) !== '') ? trim(explode(',', trim($captM[1]))[0]) : '';
            if ($title === '' && $hasAlt) $title = trim(explode(',', $altM[1])[0]);
            if ($title === '') $title = 'Wallpaper';
            $catParts = $hasLink ? explode('/', $linkM[1]) : [];
            $items[] = [
                'id' => (string) $id,
                'title' => $title,
                'thumb' => $thumb,
                'full' => $full,
                'detail' => $hasLink ? $linkM[1] : '',
                'category' => $catParts[3] ?? '',
            ];
        }

        $nums = [];
        if (preg_match_all('/page="(\d+)"/', $html, $pm)) {
            $nums = array_map('intval', $pm[1]);
        }
        $maxPage = $nums ? max($nums) : 0;
        $hasNextCtrl = (bool) preg_match('/ctrl-right/', $html);
        $hasNext = $maxPage ? $maxPage > max(1, $page) : $hasNextCtrl;
        if ($maxPage === max(1, $page) && $hasNextCtrl) $hasNext = true;

        return [
            'items' => $items,
            'page' => max(1, $page),
            'totalPages' => $maxPage > 1 ? $maxPage : null,
            'hasNext' => $hasNext,
        ];
    }

    /** @return array{items:array,page:int,totalPages:?int,hasNext:bool} */
    public static function fetch(array $opts = []): array
    {
        $cat = (string) ($opts['category'] ?? 'all');
        $page = max(1, (int) ($opts['page'] ?? 1));
        $q = trim((string) ($opts['q'] ?? ''));

        $cacheFile = NH_ROOT . '/storage/cache/wp-' . sha1($cat . '|' . $page . '|' . strtolower($q)) . '.json';
        if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < self::TTL) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) return $cached;
        }

        $url = self::buildUrl($cat, $page, $q);
        $res = Http::request($url, ['headers' => ['User-Agent: ' . self::UA, 'Accept: text/html'], 'timeout' => 20]);
        if (!$res['ok']) throw new RuntimeException('4kwallpapers.com responded ' . $res['status']);
        $data = self::parsePage($res['body'], $page);
        @file_put_contents($cacheFile, json_encode($data));
        return $data;
    }

    public static function categoryLabel(string $slug): string
    {
        foreach (self::CATEGORIES as $c) {
            if ($c['slug'] === $slug) return $c['label'];
        }
        return $slug;
    }
}
