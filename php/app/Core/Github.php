<?php
/** GitHub API helper — PHP port of src/github.js (2 min file cache). */
final class Github
{
    private const TTL = 120;

    private static function toRepo(array $r): array
    {
        return [
            'name' => $r['name'] ?? '',
            'full_name' => $r['full_name'] ?? '',
            'description' => $r['description'] ?? '',
            'html_url' => $r['html_url'] ?? '',
            'language' => $r['language'] ?? '',
            'stars' => (int) ($r['stargazers_count'] ?? 0),
            'forks' => (int) ($r['forks_count'] ?? 0),
            'watchers' => (int) ($r['watchers_count'] ?? 0),
            'open_issues' => (int) ($r['open_issues_count'] ?? 0),
            'fork' => (bool) ($r['fork'] ?? false),
            'updated_at' => $r['updated_at'] ?? '',
        ];
    }

    private static function headers(string $token): array
    {
        $h = ['Accept: application/vnd.github+json', 'User-Agent: NobitaHost-PHP'];
        if ($token !== '') $h[] = 'Authorization: Bearer ' . $token;
        return $h;
    }

    /** @return array{repos:array<int,array>, error:?string} */
    public static function fetchReposRaw(string $username, string $token = ''): array
    {
        if ($username === '') return ['repos' => [], 'error' => null];
        $url = 'https://api.github.com/users/' . rawurlencode($username) . '/repos?per_page=100&sort=updated';
        $data = Http::cached($url, self::TTL, ['headers' => self::headers($token)]);
        if ($data === null) return ['repos' => [], 'error' => 'GitHub API unreachable'];
        if (isset($data['message']) && !isset($data[0])) {
            return ['repos' => [], 'error' => 'GitHub API: ' . $data['message']];
        }
        $repos = [];
        foreach ((array) $data as $row) {
            if (is_array($row)) $repos[] = (object) self::toRepo($row);
        }
        return ['repos' => $repos, 'error' => null];
    }

    /** @return array{repos:array<int,object>, error:?string} */
    public static function getRepos(string $username, string $token = ''): array
    {
        $raw = self::fetchReposRaw($username, $token);
        return [
            'repos' => array_values(array_filter($raw['repos'], fn($r) => !$r->fork)),
            'error' => $raw['error'],
        ];
    }

    /** @return array{stats:?object, error:?string} */
    public static function getUserStats(string $username, string $token = ''): array
    {
        if ($username === '') return ['stats' => null, 'error' => null];
        $u = Http::cached('https://api.github.com/users/' . rawurlencode($username), self::TTL, ['headers' => self::headers($token)]);
        if ($u === null || isset($u['message'])) {
            return ['stats' => null, 'error' => 'GitHub API ' . ($u['message'] ?? 'unreachable')];
        }
        $raw = self::fetchReposRaw($username, $token);
        $stars = $forks = $watchers = $issues = 0;
        foreach ($raw['repos'] as $r) {
            $stars += $r->stars; $forks += $r->forks; $watchers += $r->watchers; $issues += $r->open_issues;
        }
        $stats = (object) [
            'stars' => $stars, 'forks' => $forks, 'watchers' => $watchers, 'issues' => $issues,
            'repos' => (int) ($u['public_repos'] ?? 0),
            'followers' => (int) ($u['followers'] ?? 0),
            'following' => (int) ($u['following'] ?? 0),
            'avatar' => (string) ($u['avatar_url'] ?? ''),
            'name' => (string) ($u['name'] ?? $username),
            'bio' => (string) ($u['bio'] ?? ''),
            'blog' => (string) ($u['blog'] ?? ''),
            'location' => (string) ($u['location'] ?? ''),
            'created_at' => (string) ($u['created_at'] ?? ''),
        ];
        return ['stats' => $stats, 'error' => $raw['error']];
    }
}
