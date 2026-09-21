<?php
/**
 * Navigation / app-grid definition — one source of truth for the CasaOS dock,
 * the desktop app grid and the mobile menu.
 */
final class Nav
{
    /** Each item: key, label, icon (sprite id or emoji), url, tile colour, admin-only flag. */
    public static function sections(?object $user = null): array
    {
        $isAdmin = $user && ($user->role ?? '') === 'admin';

        $sections = [];

        if ($isAdmin) {
            $sections[] = [
                'id' => 'admin',
                'label' => 'Admin',
                'items' => [
                    ['key' => 'admin', 'label' => 'Dashboard', 'icon' => 'i-gauge', 'url' => '/admin', 'color' => '#ff9f0a'],
                    ['key' => 'admin-create', 'label' => 'Create User', 'icon' => 'i-user-plus', 'url' => '/admin/create', 'color' => '#34c759'],
                    ['key' => 'admin-users', 'label' => 'Users', 'icon' => 'i-users', 'url' => '/admin/users', 'color' => '#3388ff'],
                    ['key' => 'admin-settings', 'label' => 'Settings', 'icon' => 'i-sliders', 'url' => '/admin/settings', 'color' => '#a55eea'],
                    ['key' => 'admin-content', 'label' => 'Content', 'icon' => 'i-file', 'url' => '/admin/settings/content', 'color' => '#00b8d9'],
                    ['key' => 'admin-tutorials', 'label' => 'Tutorials', 'icon' => 'i-play', 'url' => '/admin/settings/tutorials', 'color' => '#ff453a'],
                    ['key' => 'admin-blog', 'label' => 'Blog Manager', 'icon' => 'i-book', 'url' => '/admin/blog', 'color' => '#ff6482'],
                    ['key' => 'admin-pages', 'label' => 'Page Editors', 'icon' => 'i-pencil', 'url' => '/admin/pages', 'color' => '#5ac8fa'],
                    ['key' => 'admin-cloudflare', 'label' => 'Cloudflare', 'icon' => 'i-shield', 'url' => '/admin/cloudflare', 'color' => '#f38020'],
                    ['key' => 'admin-activity', 'label' => 'Activity Log', 'icon' => 'i-activity', 'url' => '/admin/activity', 'color' => '#8e8e93'],
                ],
            ];
        }

        $sections[] = [
            'id' => 'apps',
            'label' => 'Apps',
            'items' => [
                ['key' => 'home', 'label' => 'Home', 'icon' => 'i-home', 'url' => '/', 'color' => '#3388ff'],
                ['key' => 'team', 'label' => 'Team', 'icon' => 'i-users', 'url' => '/team', 'color' => '#34c759'],
                ['key' => 'tutorials', 'label' => 'Tutorials', 'icon' => 'i-play', 'url' => '/tutorials', 'color' => '#ff453a'],
                ['key' => 'command', 'label' => 'Command', 'icon' => 'i-terminal', 'url' => '/command', 'color' => '#30d158'],
                ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'i-chart', 'url' => '/analytics', 'color' => '#5e5ce6'],
                ['key' => 'projects', 'label' => 'Projects', 'icon' => 'i-folder', 'url' => '/projects', 'color' => '#ff9f0a'],
                ['key' => 'links', 'label' => 'Links', 'icon' => 'i-link', 'url' => '/links', 'color' => '#64d2ff'],
                ['key' => 'github', 'label' => 'GitHub', 'icon' => 'i-github', 'url' => '/github', 'color' => '#8e8e93'],
                ['key' => 'docs', 'label' => 'Docs', 'icon' => 'i-file', 'url' => '/docs', 'color' => '#0a84ff'],
                ['key' => 'blog', 'label' => 'Blog', 'icon' => 'i-book', 'url' => '/blog', 'color' => '#bf5af2'],
                ['key' => 'plans', 'label' => 'Plans', 'icon' => 'i-heart', 'url' => '/plans', 'color' => '#ff375f'],
                ['key' => 'about', 'label' => 'About', 'icon' => 'i-info', 'url' => '/about', 'color' => '#a55eea'],
                ['key' => 'activity', 'label' => 'Activity', 'icon' => 'i-clock', 'url' => '/activity', 'color' => '#ffd60a'],
            ],
        ];

        if ($user) {
            $sections[] = [
                'id' => 'account',
                'label' => 'Account',
                'items' => [
                    ['key' => 'profile', 'label' => 'My Profile', 'icon' => 'i-user', 'url' => '/profile', 'color' => '#3388ff'],
                    ['key' => '2fa', 'label' => 'Security', 'icon' => 'i-lock', 'url' => '/profile/2fa/setup', 'color' => '#34c759'],
                ],
            ];
        }

        // dynamic content pages flagged "in nav"
        $extra = [];
        try {
            foreach (DB::all('SELECT slug, title, icon FROM content_pages WHERE in_nav = 1 ORDER BY id') as $p) {
                $extra[] = ['key' => $p->slug, 'label' => $p->title, 'emoji' => $p->icon ?: '📄', 'icon' => '', 'url' => '/page/' . $p->slug, 'color' => '#66d4ff'];
            }
        } catch (Throwable $e) { /* noop */ }
        if ($extra) $sections[] = ['id' => 'pages', 'label' => 'Pages', 'items' => $extra];

        return View::normalize($sections);
    }

    public static function all(?object $user = null): array
    {
        $out = [];
        foreach (self::sections($user) as $s) {
            foreach ($s['items'] as $i) $out[$i['key']] = $i;
        }
        return $out;
    }
}
