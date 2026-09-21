<?php
/**
 * Cloudflare API client — PHP port of src/cloudflare.js.
 *
 * REST (api.cloudflare.com/client/v4) + GraphQL analytics, with a 5 minute
 * file-backed cache (storage/cache/cf-*.json) mirroring the Node in-memory TTL map.
 * Every failure throws RuntimeException so routes can catch and flash a message.
 */
final class Cloudflare
{
    private const API = 'https://api.cloudflare.com/client/v4';
    private const GRAPHQL = 'https://api.cloudflare.com/client/v4/graphql';
    private const TTL = 300;

    /** @var array<string,mixed> per-request memo */
    private static array $mem = [];

    /* ---------------------------------------------------------- config */

    public static function getConfig(): array
    {
        return [
            'email'            => (string) Settings::get('cf_email'),
            'authMode'         => ((string) Settings::get('cf_auth_mode')) ?: 'token',
            'apiToken'         => (string) Settings::get('cf_api_token'),
            'accountId'        => (string) Settings::get('cf_account_id'),
            'zoneId'           => (string) Settings::get('cf_zone_id'),
            'analyticsEnabled' => Settings::get('cf_analytics_enabled') === 'on',
            'analyticsToken'   => (string) Settings::get('cf_analytics_token'),
            'ztEnabled'        => Settings::get('cf_zerotrust_enabled') === 'on',
            'ztTeam'           => (string) Settings::get('cf_zerotrust_team'),
        ];
    }

    public static function saveConfig(array $b): void
    {
        $fields = [
            'cf_email'           => $b['email'] ?? '',
            'cf_auth_mode'       => (($b['authMode'] ?? '') === 'global') ? 'global' : 'token',
            'cf_account_id'      => $b['accountId'] ?? '',
            'cf_zone_id'         => $b['zoneId'] ?? '',
            'cf_analytics_token' => $b['analyticsToken'] ?? '',
            'cf_zerotrust_team'  => $b['ztTeam'] ?? '',
        ];
        foreach ($fields as $k => $v) Settings::set($k, (string) ($v ?? ''));

        $newToken = trim((string) ($b['apiToken'] ?? ''));
        if ($newToken !== '' && !str_contains($newToken, '•')) Settings::set('cf_api_token', $newToken);

        Settings::set('cf_analytics_enabled', !empty($b['analytics_enabled']) ? 'on' : '');
        Settings::set('cf_zerotrust_enabled', !empty($b['zerotrust_enabled']) ? 'on' : '');
        self::bust();
    }

    public static function maskToken($t): string
    {
        $s = (string) ($t ?? '');
        if ($s === '') return '';
        return strlen($s) <= 8 ? '••••' : substr($s, 0, 4) . '••••••••' . substr($s, -4);
    }

    private static function authHeaders(array $cfg): array
    {
        if (($cfg['authMode'] ?? 'token') === 'global') {
            return [
                'X-Auth-Email: ' . (string) ($cfg['email'] ?? ''),
                'X-Auth-Key: ' . (string) ($cfg['apiToken'] ?? ''),
                'Content-Type: application/json',
            ];
        }
        return [
            'Authorization: Bearer ' . (string) ($cfg['apiToken'] ?? ''),
            'Content-Type: application/json',
        ];
    }

    public static function zid(): string  { return (string) self::getConfig()['zoneId']; }
    public static function accId(): string { return (string) self::getConfig()['accountId']; }

    /** Drop every cached Cloudflare payload (after a write). */
    public static function bust(): void
    {
        self::$mem = [];
        foreach (glob(NH_ROOT . '/storage/cache/cf-*.json') ?: [] as $file) @unlink($file);
    }

    /* ---------------------------------------------------------- transport */

    /**
     * @param array{method?:string, body?:array} $opts
     * @return mixed decoded `result`
     */
    public static function cfFetch(string $path, array $opts = [], int $timeoutMs = 12000)
    {
        $cfg = self::getConfig();
        if (($cfg['apiToken'] ?? '') === '' || (($cfg['authMode'] ?? '') === 'global' && ($cfg['email'] ?? '') === '')) {
            throw new RuntimeException('No credentials configured');
        }
        $req = [
            'method'  => strtoupper((string) ($opts['method'] ?? 'GET')),
            'headers' => self::authHeaders($cfg),
            'timeout' => max(1, (int) ceil($timeoutMs / 1000)),
        ];
        if (isset($opts['body']) && is_array($opts['body'])) $req['json'] = $opts['body'];

        $res = Http::request(self::API . $path, $req);
        $j = json_decode($res['body'], true);
        if (!is_array($j)) {
            throw new RuntimeException($res['error'] !== '' ? $res['error'] : ('Cloudflare API error (HTTP ' . $res['status'] . ')'));
        }
        if (empty($j['success'])) {
            throw new RuntimeException((string) ($j['errors'][0]['message'] ?? 'API error'));
        }
        return $j['result'] ?? null;
    }

    /** GraphQL analytics query → `data` node. */
    public static function gql(string $query, array $variables = [], int $timeoutMs = 9000): array
    {
        $cfg = self::getConfig();
        if (($cfg['apiToken'] ?? '') === '' || ($cfg['zoneId'] ?? '') === '') {
            throw new RuntimeException('Credentials + Zone ID required');
        }
        $res = Http::request(self::GRAPHQL, [
            'method'  => 'POST',
            'headers' => self::authHeaders($cfg),
            'json'    => ['query' => $query, 'variables' => $variables],
            'timeout' => max(1, (int) ceil($timeoutMs / 1000)),
        ]);
        $j = json_decode($res['body'], true);
        if (!is_array($j)) {
            throw new RuntimeException($res['error'] !== '' ? $res['error'] : ('Cloudflare GraphQL error (HTTP ' . $res['status'] . ')'));
        }
        if (!empty($j['errors'])) {
            throw new RuntimeException((string) ($j['errors'][0]['message'] ?? 'GraphQL error'));
        }
        return is_array($j['data'] ?? null) ? $j['data'] : [];
    }

    /** @param callable():mixed $fn */
    private static function cached(string $key, callable $fn)
    {
        if (array_key_exists($key, self::$mem)) return self::$mem[$key];
        $file = NH_ROOT . '/storage/cache/cf-' . preg_replace('/[^A-Za-z0-9_.:-]/', '_', $key) . '.json';
        if (is_file($file) && (time() - (int) filemtime($file)) < self::TTL) {
            $data = json_decode((string) file_get_contents($file), true);
            if ($data !== null) return self::$mem[$key] = $data;
        }
        $data = $fn();
        @file_put_contents($file, (string) json_encode($data));
        return self::$mem[$key] = $data;
    }

    /* ---------------------------------------------------------- account / zone */

    public static function verifyToken(): array
    {
        return (array) self::cached('verify', function (): array {
            $cfg = self::getConfig();
            if (($cfg['authMode'] ?? '') === 'global') {
                $user = (array) self::cfFetch('/user');
                return ['status' => 'Active', 'id' => (string) ($user['id'] ?? '')];
            }
            $result = (array) self::cfFetch('/user/tokens/verify');
            return ['status' => (string) ($result['status'] ?? ''), 'id' => (string) ($result['id'] ?? '')];
        });
    }

    public static function listZones(): array
    {
        return (array) self::cached('zones', function (): array {
            $zones = (array) self::cfFetch('/zones?per_page=50');
            $out = [];
            foreach ($zones as $z) {
                $out[] = [
                    'id' => (string) ($z['id'] ?? ''),
                    'name' => (string) ($z['name'] ?? ''),
                    'status' => (string) ($z['status'] ?? ''),
                    'account_id' => $z['account']['id'] ?? null,
                    'account_name' => $z['account']['name'] ?? null,
                ];
            }
            return $out;
        });
    }

    public static function zoneInfo(): array
    {
        return (array) self::cached('zone', function (): array {
            $z = (array) self::cfFetch('/zones/' . self::getConfig()['zoneId']);
            return [
                'name' => (string) ($z['name'] ?? ''),
                'status' => (string) ($z['status'] ?? ''),
                'plan' => (string) ($z['plan']['name'] ?? ''),
            ];
        });
    }

    public static function webAnalytics(): array
    {
        $days = 7;
        $since = gmdate('Y-m-d', time() - ($days - 1) * 86400);
        $until = gmdate('Y-m-d');
        return (array) self::cached('analytics', function () use ($days, $since, $until): array {
            $query = 'query ($zone: String!, $since: Date!, $until: Date!) {'
                . ' viewer { zones(filter: { zoneTag: $zone }) {'
                . ' httpRequests1dGroups(limit: ' . $days . ', filter: { date_geq: $since, date_leq: $until }, orderBy: [date_ASC]) {'
                . ' dimensions { date } sum { requests pageViews bytes threats } uniq { uniques } } } } }';
            $data = self::gql($query, ['zone' => (string) self::getConfig()['zoneId'], 'since' => $since, 'until' => $until]);
            $zones = $data['viewer']['zones'] ?? [];
            $groups = is_array($zones) && count($zones) ? ($zones[0]['httpRequests1dGroups'] ?? []) : [];
            $out = [];
            foreach ((array) $groups as $g) {
                $out[] = [
                    'date' => $g['dimensions']['date'] ?? '',
                    'requests' => (int) ($g['sum']['requests'] ?? 0),
                    'pageViews' => (int) ($g['sum']['pageViews'] ?? 0),
                    'bytes' => (int) ($g['sum']['bytes'] ?? 0),
                    'threats' => (int) ($g['sum']['threats'] ?? 0),
                    'visitors' => (int) ($g['uniq']['uniques'] ?? 0),
                ];
            }
            return $out;
        });
    }

    public static function zeroTrust(): array
    {
        return (array) self::cached('zt', function (): array {
            $base = '/accounts/' . self::getConfig()['accountId'];
            $out = [];
            try {
                $apps = self::cfFetch($base . '/access/apps');
                $out['apps'] = is_array($apps) ? count($apps) : 0;
            } catch (Throwable $e) { $out['appsError'] = $e->getMessage(); }
            try {
                $users = self::cfFetch($base . '/access/users');
                $out['users'] = is_array($users) ? count($users) : 0;
            } catch (Throwable $e) { $out['usersError'] = $e->getMessage(); }
            try {
                $devices = self::cfFetch($base . '/devices');
                $out['devices'] = is_array($devices) ? count($devices) : 0;
            } catch (Throwable $e) { $out['devicesError'] = $e->getMessage(); }
            return $out;
        });
    }

    /* ---------------------------------------------------------- DNS */

    public static function dnsList(): array
    {
        return (array) self::cached('dns', fn() => (array) self::cfFetch('/zones/' . self::zid() . '/dns_records?per_page=100'));
    }

    public static function dnsCreate(array $b)
    {
        $proxied = ($b['proxied'] ?? '') === 'on';
        $r = self::cfFetch('/zones/' . self::zid() . '/dns_records', [
            'method' => 'POST',
            'body' => [
                'type' => (string) ($b['type'] ?? 'A'),
                'name' => (string) ($b['name'] ?? ''),
                'content' => (string) ($b['content'] ?? ''),
                'ttl' => $proxied ? 1 : ((int) ($b['ttl'] ?? 1) ?: 1),
                'proxied' => $proxied,
            ],
        ]);
        self::bust();
        return $r;
    }

    public static function dnsUpdate(string $id, array $b)
    {
        $proxied = ($b['proxied'] ?? '') === 'on';
        $r = self::cfFetch('/zones/' . self::zid() . '/dns_records/' . $id, [
            'method' => 'PATCH',
            'body' => [
                'type' => (string) ($b['type'] ?? 'A'),
                'name' => (string) ($b['name'] ?? ''),
                'content' => (string) ($b['content'] ?? ''),
                'ttl' => $proxied ? 1 : ((int) ($b['ttl'] ?? 1) ?: 1),
                'proxied' => $proxied,
            ],
        ]);
        self::bust();
        return $r;
    }

    public static function dnsDelete(string $id)
    {
        $r = self::cfFetch('/zones/' . self::zid() . '/dns_records/' . $id, ['method' => 'DELETE']);
        self::bust();
        return $r;
    }

    /* ---------------------------------------------------------- zone settings */

    public static function zoneSettings(): array
    {
        return (array) self::cached('settings', function (): array {
            $all = self::cfFetch('/zones/' . self::zid() . '/settings');
            $map = [];
            foreach ((array) $all as $s) $map[(string) ($s['id'] ?? '')] = $s['value'] ?? null;
            return $map;
        });
    }

    public static function zoneSetSetting(string $key, $value)
    {
        $r = self::cfFetch('/zones/' . self::zid() . '/settings/' . $key, ['method' => 'PATCH', 'body' => ['value' => $value]]);
        self::bust();
        return $r;
    }

    /* ---------------------------------------------------------- security */

    public static function fwRules(): array
    {
        return (array) self::cached('fw', function (): array {
            try {
                return (array) self::cfFetch('/zones/' . self::zid() . '/firewall/rules?per_page=100');
            } catch (Throwable $e) { return []; }
        });
    }

    public static function fwRuleToggle(string $id, bool $paused)
    {
        $r = self::cfFetch('/zones/' . self::zid() . '/firewall/rules/' . $id, ['method' => 'PATCH', 'body' => ['paused' => $paused]]);
        self::bust();
        return $r;
    }

    public static function fwRuleDelete(string $id)
    {
        $r = self::cfFetch('/zones/' . self::zid() . '/firewall/rules/' . $id, ['method' => 'DELETE']);
        self::bust();
        return $r;
    }

    public static function accessRules(): array
    {
        return (array) self::cached('ar', function (): array {
            try {
                return (array) self::cfFetch('/zones/' . self::zid() . '/firewall/access_rules/rules?per_page=100');
            } catch (Throwable $e) { return []; }
        });
    }

    public static function accessRuleCreate(array $b)
    {
        $r = self::cfFetch('/zones/' . self::zid() . '/firewall/access_rules/rules', [
            'method' => 'POST',
            'body' => [
                'mode' => (string) ($b['mode'] ?? 'block'),
                'notes' => (string) ($b['notes'] ?? ''),
                'configuration' => ['target' => 'ip', 'value' => (string) ($b['value'] ?? '')],
            ],
        ]);
        self::bust();
        return $r;
    }

    public static function accessRuleDelete(string $id)
    {
        $r = self::cfFetch('/zones/' . self::zid() . '/firewall/access_rules/rules/' . $id, ['method' => 'DELETE']);
        self::bust();
        return $r;
    }

    public static function securityEvents(int $days = 7): array
    {
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - $days * 86400);
        return (array) self::cached('sec-events', function () use ($since): array {
            $query = 'query ($zone: String!, $since: Timestamp!) {'
                . ' viewer { zones(filter: { zoneTag: $zone }) {'
                . ' firewallEventsAdaptiveGroups(limit: 60, filter: { datetime_geq: $since }, orderBy: [count_DESC]) {'
                . ' count dimensions { action } } } } }';
            try {
                $data = self::gql($query, ['zone' => self::zid(), 'since' => $since]);
            } catch (Throwable $e) { return []; }
            $zones = $data['viewer']['zones'] ?? [];
            return is_array($zones) && count($zones) ? (array) ($zones[0]['firewallEventsAdaptiveGroups'] ?? []) : [];
        });
    }

    /* ---------------------------------------------------------- accounts */

    public static function accounts(): array
    {
        return (array) self::cached('accounts', fn() => (array) self::cfFetch('/accounts?per_page=50'));
    }

    public static function accountMembers(string $acc): array
    {
        return (array) self::cached('members:' . $acc, function () use ($acc): array {
            try {
                return (array) self::cfFetch('/accounts/' . $acc . '/members?per_page=50');
            } catch (Throwable $e) { return []; }
        });
    }

    /* ---------------------------------------------------------- zero trust lists */

    public static function ztApps(): array
    {
        return (array) self::cached('zt-apps', function (): array {
            try {
                return (array) self::cfFetch('/accounts/' . self::accId() . '/access/apps?per_page=100');
            } catch (Throwable $e) { return []; }
        });
    }

    public static function ztAppDelete(string $id)
    {
        $r = self::cfFetch('/accounts/' . self::accId() . '/access/apps/' . $id, ['method' => 'DELETE']);
        self::bust();
        return $r;
    }

    public static function ztUsers(): array
    {
        return (array) self::cached('zt-users', function (): array {
            try {
                return (array) self::cfFetch('/accounts/' . self::accId() . '/access/users?per_page=100');
            } catch (Throwable $e) { return []; }
        });
    }

    public static function ztDevices(): array
    {
        return (array) self::cached('zt-devices', function (): array {
            try {
                return (array) self::cfFetch('/accounts/' . self::accId() . '/devices');
            } catch (Throwable $e) { return []; }
        });
    }

    /* ---------------------------------------------------------- per-domain analytics */

    /** Sequential per-zone GraphQL totals (Node used Promise.allSettled; PHP loops). */
    public static function domainStats(int $limit = 20): array
    {
        return (array) self::cached('domain-stats', function () use ($limit): array {
            $zones = array_slice(self::listZones(), 0, $limit);
            $since = gmdate('Y-m-d', time() - 6 * 86400);
            $until = gmdate('Y-m-d');
            $query = 'query ($zone: String!, $since: Date!, $until: Date!) {'
                . ' viewer { zones(filter: { zoneTag: $zone }) {'
                . ' httpRequests1dGroups(limit: 7, filter: { date_geq: $since, date_leq: $until }) {'
                . ' sum { requests pageViews bytes threats } uniq { uniques } } } } }';
            $out = [];
            foreach ($zones as $z) {
                try {
                    $d = self::gql($query, ['zone' => (string) ($z['id'] ?? ''), 'since' => $since, 'until' => $until]);
                } catch (Throwable $e) { continue; }
                $groups = $d['viewer']['zones'][0]['httpRequests1dGroups'] ?? [];
                $req = $pv = $bytes = $thr = $vis = 0;
                foreach ((array) $groups as $g) {
                    $req += (int) ($g['sum']['requests'] ?? 0);
                    $pv += (int) ($g['sum']['pageViews'] ?? 0);
                    $bytes += (int) ($g['sum']['bytes'] ?? 0);
                    $thr += (int) ($g['sum']['threats'] ?? 0);
                    $vis += (int) ($g['uniq']['uniques'] ?? 0);
                }
                $out[] = [
                    'id' => (string) ($z['id'] ?? ''),
                    'name' => (string) ($z['name'] ?? ''),
                    'status' => (string) ($z['status'] ?? ''),
                    'requests' => $req,
                    'pageViews' => $pv,
                    'bytes' => $bytes,
                    'threats' => $thr,
                    'visitors' => $vis,
                ];
            }
            usort($out, fn($a, $b) => $b['requests'] <=> $a['requests']);
            return $out;
        });
    }
}
