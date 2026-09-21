<?php
/**
 * Obsidian content blocks — PHP port of src/obsidian.js.
 *
 * About / Terms / Footer / Navbar are stored as JSON blobs in the `settings` table and
 * parsed into the shapes the views render. `default*()` returns the editable raw form,
 * `parse*()` returns the render-ready structure.
 */
final class Obsidian
{
    public const KEYS = [
        'about'  => 'obsidian_about',
        'terms'  => 'obsidian_terms',
        'footer' => 'obsidian_footer',
        'navbar' => 'obsidian_navbar',
    ];

    /* ---------------------------------------------------------- storage */

    /** Raw stored data (assoc array) or null. */
    public static function load(string $name): ?array
    {
        $key = self::KEYS[$name] ?? null;
        if ($key === null) return null;
        $raw = (string) Settings::get($key);
        if (trim($raw) === '') return null;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function save(string $name, array $data): void
    {
        $key = self::KEYS[$name] ?? null;
        if ($key === null) return;
        Settings::set($key, (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /* ---------------------------------------------------------- helpers */

    /** @return string[] non-empty trimmed lines */
    public static function splitLines($text): array
    {
        $out = [];
        foreach (explode("\n", (string) ($text ?? '')) as $line) {
            $line = trim($line);
            if ($line !== '') $out[] = $line;
        }
        return $out;
    }

    /** Split a `a | b | c` line, padded to $n parts. */
    public static function parsePipe(string $line, int $n): array
    {
        $parts = array_map('trim', explode('|', $line));
        while (count($parts) < $n) $parts[] = '';
        return $parts;
    }

    /* ---------------------------------------------------------- about */

    public static function defaultAbout(): array
    {
        $panel = (string) Settings::get('panel_name', 'Us') ?: 'Us';
        return [
            'enabled' => false,
            'seo_title' => 'About',
            'hero' => [
                'title' => 'About ' . $panel,
                'subtitle' => '',
                'image_url' => '',
                'cta1_label' => 'Contact us',
                'cta1_url' => '/links',
                'cta2_label' => 'View plans',
                'cta2_url' => '/plans',
            ],
            'stats_enabled' => true,
            'stats_text' => "99.9% | Uptime\n24/7 | Support\n10k+ | Customers\n1ms | Latency",
            'story_enabled' => true,
            'story_title' => 'Our story',
            'story_text' => 'Write your story here...',
            'values_enabled' => true,
            'values_title' => 'What we stand for',
            'values_text' => "Speed | Fast setup and fast support.\nQuality | Reliable infrastructure.\nHonesty | Clear pricing and policies.\nCare | We treat customers like humans.",
            'team_enabled' => true,
            'team_title' => 'Meet the team',
            'team_text' => "PJ | Owner |\nSupport | Customer Success |\nOps | Infrastructure |",
            'timeline_enabled' => true,
            'timeline_title' => 'Milestones',
            'timeline_text' => date('Y') . ' | Launched | First customers onboarded.',
            'gallery_enabled' => true,
            'gallery_title' => 'Behind the scenes',
            'gallery_text' => '',
        ];
    }

    public static function parseAbout(?array $data = null): array
    {
        $d = array_merge(self::defaultAbout(), is_array($data) ? $data : []);
        $hero = is_array($d['hero'] ?? null) ? $d['hero'] : [];

        $stats = [];
        foreach (self::splitLines($d['stats_text'] ?? '') as $line) {
            [$value, $label] = self::parsePipe($line, 2);
            $stats[] = ['value' => $value, 'label' => $label];
        }

        $values = [];
        foreach (self::splitLines($d['values_text'] ?? '') as $line) {
            [$title, $text] = self::parsePipe($line, 2);
            $values[] = ['title' => $title, 'text' => $text];
        }

        $team = [];
        foreach (self::splitLines($d['team_text'] ?? '') as $line) {
            [$name, $role, $image] = self::parsePipe($line, 3);
            $team[] = ['name' => $name, 'role' => $role, 'image' => $image];
        }

        $timeline = [];
        foreach (self::splitLines($d['timeline_text'] ?? '') as $line) {
            [$year, $title, $text] = self::parsePipe($line, 3);
            $timeline[] = ['year' => $year, 'title' => $title, 'text' => $text];
        }

        $gallery = [];
        foreach (self::splitLines($d['gallery_text'] ?? '') as $line) {
            [$url, $alt] = self::parsePipe($line, 2);
            $gallery[] = ['url' => $url, 'alt' => $alt];
        }

        return [
            'enabled' => !empty($d['enabled']),
            'seo_title' => (string) (($d['seo_title'] ?? '') ?: 'About'),
            'hero' => [
                'title' => (string) ($hero['title'] ?? ''),
                'subtitle' => (string) ($hero['subtitle'] ?? ''),
                'image_url' => (string) ($hero['image_url'] ?? ''),
                'cta1_label' => (string) ($hero['cta1_label'] ?? ''),
                'cta1_url' => (string) ($hero['cta1_url'] ?? ''),
                'cta2_label' => (string) ($hero['cta2_label'] ?? ''),
                'cta2_url' => (string) ($hero['cta2_url'] ?? ''),
            ],
            'stats_enabled' => !empty($d['stats_enabled']),
            'stats' => $stats,
            'story_enabled' => !empty($d['story_enabled']),
            'story_title' => (string) ($d['story_title'] ?? ''),
            'story_paragraphs' => self::splitLines($d['story_text'] ?? ''),
            'values_enabled' => !empty($d['values_enabled']),
            'values_title' => (string) ($d['values_title'] ?? ''),
            'values_items' => $values,
            'team_enabled' => !empty($d['team_enabled']),
            'team_title' => (string) ($d['team_title'] ?? ''),
            'team_members' => $team,
            'timeline_enabled' => !empty($d['timeline_enabled']),
            'timeline_title' => (string) ($d['timeline_title'] ?? ''),
            'timeline_items' => $timeline,
            'gallery_enabled' => !empty($d['gallery_enabled']),
            'gallery_title' => (string) ($d['gallery_title'] ?? ''),
            'gallery_images' => $gallery,
        ];
    }

    /* ---------------------------------------------------------- terms */

    public static function defaultTerms(): array
    {
        return [
            'enabled' => true,
            'title' => 'Terms & Conditions',
            'summary' => '',
            'last_updated' => gmdate('Y-m-d'),
            'sections_text' => "## introduction | Introduction\nReplace this with your own terms content.\n\n## fair-use | Fair Use\n- Do not abuse the services.\n- No illegal activity.",
        ];
    }

    public static function parseTerms(?array $data = null): array
    {
        $d = array_merge(self::defaultTerms(), is_array($data) ? $data : []);

        $sections = [];
        $cur = null;
        foreach (self::splitLines($d['sections_text'] ?? '') as $line) {
            if (preg_match('/^##\s+([a-z0-9\-_]+)\s*\|\s*(.+)$/i', $line, $m)) {
                if ($cur !== null) $sections[] = $cur;
                $cur = ['id' => strtolower($m[1]), 'title' => trim($m[2]), 'body' => []];
            } elseif ($cur !== null) {
                $cur['body'][] = $line;
            }
        }
        if ($cur !== null) $sections[] = $cur;

        $out = [];
        foreach ($sections as $s) {
            $out[] = [
                'id' => $s['id'],
                'title' => $s['title'],
                'body' => $s['body'],
                'html' => Markdown::html(implode("\n", $s['body'])),
            ];
        }

        return [
            'enabled' => array_key_exists('enabled', $d) ? !empty($d['enabled']) : true,
            'title' => (string) (($d['title'] ?? '') ?: 'Terms & Conditions'),
            'summary' => (string) ($d['summary'] ?? ''),
            'last_updated' => (string) ($d['last_updated'] ?? ''),
            'sections' => $out,
        ];
    }

    /* ---------------------------------------------------------- footer */

    public static function defaultFooter(): array
    {
        return [
            'enabled' => false,
            'copyright' => '',
            'columns_text' => "# Product\nPlans | /plans | internal | always\nBlog | /blog | internal | always\n# Company\nAbout | /about | internal | always\nTeam | /team | internal | always",
            'legal_text' => 'Terms & Conditions | /terms | internal | always',
        ];
    }

    /** @return array<int,array{label:string,url:string,type:string,visibility:string}> */
    public static function parseFooterLinks($text): array
    {
        $out = [];
        foreach (self::splitLines($text) as $line) {
            [$label, $url, $type, $visibility] = self::parsePipe($line, 4);
            $out[] = [
                'label' => $label,
                'url' => $url,
                'type' => $type === 'external' ? 'external' : 'internal',
                'visibility' => in_array($visibility, ['guest', 'auth'], true) ? $visibility : 'always',
            ];
        }
        return $out;
    }

    public static function parseFooterColumns($text): array
    {
        $columns = [];
        $cur = null;
        foreach (self::splitLines($text) as $line) {
            if (preg_match('/^#\s+/', $line)) {
                if ($cur !== null) $columns[] = $cur;
                $cur = ['title' => trim((string) preg_replace('/^#\s+/', '', $line)), 'links' => []];
            } elseif ($cur !== null) {
                [$label, $url, $type, $visibility] = self::parsePipe($line, 4);
                $cur['links'][] = [
                    'label' => $label,
                    'url' => $url,
                    'type' => $type === 'external' ? 'external' : 'internal',
                    'visibility' => in_array($visibility, ['guest', 'auth'], true) ? $visibility : 'always',
                ];
            }
        }
        if ($cur !== null) $columns[] = $cur;
        return $columns;
    }

    public static function parseFooter(?array $data = null): array
    {
        $d = array_merge(self::defaultFooter(), is_array($data) ? $data : []);
        return [
            'enabled' => !empty($d['enabled']),
            'copyright' => (string) ($d['copyright'] ?? ''),
            'columns' => self::parseFooterColumns($d['columns_text'] ?? ''),
            'legal' => self::parseFooterLinks($d['legal_text'] ?? ''),
        ];
    }

    /* ---------------------------------------------------------- navbar */

    public static function defaultNavbar(): array
    {
        return [
            'enabled' => false,
            'links_text' => "Status | /status | internal | always\nDiscord | https://discord.gg/yourinvite | external | always",
        ];
    }

    public static function parseNavbar(?array $data = null): array
    {
        $d = array_merge(self::defaultNavbar(), is_array($data) ? $data : []);
        return [
            'enabled' => !empty($d['enabled']),
            'links' => self::parseFooterLinks($d['links_text'] ?? ''),
        ];
    }

    /* ---------------------------------------------------------- visibility */

    /**
     * Filter parsed links for the current visitor.
     * @param array<int,object|array> $links
     * @return array<int,object>
     */
    public static function visibleFor($links, ?object $user = null): array
    {
        if (!is_array($links)) return [];
        $out = [];
        foreach ($links as $l) {
            $vis = (string) (is_object($l) ? ($l->visibility ?? 'always') : ($l['visibility'] ?? 'always'));
            if ($vis === 'always' || ($vis === 'auth' && $user) || ($vis === 'guest' && !$user)) {
                $out[] = View::normalize($l);
            }
        }
        return $out;
    }

    /** Navbar links for the topbar (empty when the block is disabled). */
    public static function navbarLinks(?object $user = null): array
    {
        $nav = self::parseNavbar(self::load('navbar'));
        return !empty($nav['enabled']) ? $nav['links'] : [];
    }
}
