<?php
/** Panel settings (key/value in SQLite) + background/wallpaper helpers. */
final class Settings
{
    public const DEFAULTS = [
        'panel_name' => 'NobitaHost',
        'panel_tagline' => 'Full-Featured Hosting Panel',

        'logo_type' => 'emoji',
        'logo_url' => '',
        'logo_emoji' => '🔷',
        'favicon_url' => '',

        'background_type' => 'image',
        'background_url' => '',
        'background_source' => 'none',
        'background_overlay' => 'on',

        'panel_blur' => '16',
        'transparency' => '100',
        'theme' => 'dark',
        'wallpaper_favs' => '',

        'music_type' => 'none',
        'music_url' => '',
        'music_volume' => '40',

        'transparent_bar' => 'on',
        'blur_bar' => 'on',
        'card_radius' => '16',
        'accent_color' => '#3388ff',

        'register_open' => 'on',
        'maintenance' => 'off',

        /* CasaOS shell */
        'casaos_mode' => 'on',
        'casaos_dock' => 'on',
        'casaos_widgets' => 'on',
        'casaos_grid' => 'grid',
        'casaos_wallpaper' => '',
        'casaos_opacity' => '82',

        'script_enabled' => 'on',
        'script_badge' => 'All In One CMD',
        'script_description' => 'Execute the master script to install all dependencies instantly.',
        'script_command' => 'bash <(curl -s https://ptero.nobitahost.in)',

        'discord_enabled' => 'on',
        'discord_server_id' => '',
        'discord_channel' => '',
        'discord_theme' => 'dark',

        'youtube_enabled' => 'off',
        'youtube_channel' => '',
        'youtube_api_key' => '',

        'instagram_handle' => '',
        'github_username' => '',
        'github_token' => '',

        'cookie_banner' => 'off',
        'anti_adblock' => 'off',
        'inject_body_code' => '',
        'inject_head_code' => '',
        'cf_analytics_enabled' => 'off',
        'cf_analytics_token' => '',

        'cf_api_token' => '',
        'cf_zone_id' => '',
        'cf_account_id' => '',
        'cf_zerotrust_enabled' => 'off',

        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_secure' => 'false',
        'smtp_user' => '',
        'smtp_pass' => '',
        'mail_from' => 'NobitaHost <noreply@nobitahost.local>',
        'mail_enabled' => 'off',
    ];

    public const WALLPAPER_SOURCES = [
        ['slug' => 'none', 'label' => 'None (Solid dark)'],
        ['slug' => 'cute-kawaii-wallpapers', 'label' => 'Cute Kawaii'],
        ['slug' => 'ultrawide-monitor-hd-wallpapers', 'label' => 'Ultrawide Monitor HD'],
        ['slug' => 'cool-wallpapers', 'label' => 'Cool'],
        ['slug' => 'black-dark', 'label' => 'Black & Dark'],
        ['slug' => 'aesthetic-wallpapers', 'label' => 'Aesthetic'],
        ['slug' => 'space', 'label' => 'Space'],
        ['slug' => 'cr7-wallpapers', 'label' => 'CR7'],
        ['slug' => 'all', 'label' => 'All Categories'],
    ];

    private static ?object $cache = null;

    /** All settings as an object (defaults + DB overrides). */
    public static function all(bool $fresh = false): object
    {
        if (self::$cache && !$fresh) return self::$cache;
        $out = self::DEFAULTS;
        try {
            foreach (DB::all('SELECT key, value FROM settings') as $row) {
                $out[$row->key] = (string) $row->value;
            }
        } catch (Throwable $e) {
            // DB not ready yet — defaults only
        }
        $obj = new NhObj();
        foreach ($out as $k => $v) $obj->{(string) $k} = $v;
        return self::$cache = $obj;
    }

    public static function get(string $key, $default = ''): string
    {
        $s = self::all();
        $val = $s->{$key} ?? null;
        return (string) ($val === null || $val === '' ? $default : $val);
    }

    public static function set(string $key, $value): void
    {
        DB::run(
            'INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            [$key, (string) ($value ?? '')]
        );
        self::$cache = null;
    }

    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $k => $v) self::set((string) $k, $v);
    }

    /** JSON stored inside a setting key. */
    public static function json(string $key, $default = null)
    {
        $raw = self::get($key);
        if ($raw === '') return $default;
        $decoded = json_decode($raw, true);
        return $decoded === null ? $default : $decoded;
    }

    public static function resolveBackground(?object $s = null): array
    {
        $s = $s ?: self::all();
        $casaos = (string) ($s->casaos_wallpaper ?? '');
        if ($casaos !== '') return ['url' => $casaos, 'type' => 'image', 'source' => 'casaos'];

        if (!empty($s->background_url)) {
            return ['url' => $s->background_url, 'type' => ($s->background_type ?? 'image') === 'video' ? 'video' : 'image'];
        }
        $src = (string) ($s->background_source ?? 'none');
        if ($src && $src !== 'none') {
            $seed = rawurlencode($src);
            return [
                'url' => "https://picsum.photos/seed/{$seed}-nobitahost/1920/1080",
                'type' => 'image',
                'sourcePage' => 'https://4kwallpapers.com/' . ($src === 'all' ? '' : $src . '/'),
            ];
        }
        return ['url' => '', 'type' => 'image'];
    }

    public static function overlayAlpha(?object $s = null): string
    {
        $s = $s ?: self::all();
        $t = min(100, max(0, (int) ($s->transparency ?? 100)));
        return number_format((100 - $t) / 100 * 0.55, 3, '.', '');
    }

    public static function normalizeBlur(?object $s = null): int
    {
        $s = $s ?: self::all();
        return min(40, max(0, (int) ($s->panel_blur ?? 0)));
    }
}
