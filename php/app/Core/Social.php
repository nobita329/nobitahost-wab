<?php
/** Aggregated social presence (YouTube / Instagram / GitHub / Discord) — port of src/social.js. */
final class Social
{
    public static function discordOnline(string $serverId): array
    {
        if ($serverId === '') return ['online' => null, 'error' => null];
        $data = Http::cached(
            'https://discord.com/api/guilds/' . rawurlencode($serverId) . '/widget.json',
            120,
            ['headers' => ['User-Agent: NobitaHost-PHP']]
        );
        if ($data === null) return ['online' => null, 'error' => 'Discord API unreachable'];
        return ['online' => (int) ($data['presence_count'] ?? 0), 'error' => null, 'name' => $data['name'] ?? ''];
    }

    /** @return array{youtube:?array,instagram:?array,github:?array,discord:?array} */
    public static function data($settings): array
    {
        $s = is_object($settings) ? $settings : (object) (array) $settings;
        $social = ['youtube' => null, 'instagram' => null, 'github' => null, 'discord' => null];

        if (($s->youtube_enabled ?? 'off') === 'on' && !empty($s->youtube_channel)) {
            try {
                $y = Youtube::getData($s);
                if ($y && !empty($y['channel'])) {
                    $handle = $y['channel']['handle'] ?? preg_replace('/^@/', '', (string) $s->youtube_channel);
                    $social['youtube'] = [
                        'subs' => $y['channel']['subscribers'] ?? null,
                        'subsStr' => Youtube::fmt($y['channel']['subscribers'] ?? null),
                        'url' => 'https://youtube.com/@' . $handle,
                    ];
                }
            } catch (Throwable $e) {
                $social['youtube'] = ['subs' => null, 'url' => null, 'error' => $e->getMessage()];
            }
        }

        if (!empty($s->instagram_handle)) {
            $h = preg_replace('/^@/', '', (string) $s->instagram_handle);
            $social['instagram'] = ['handle' => '@' . $h, 'url' => 'https://instagram.com/' . $h];
        }

        if (!empty($s->github_username)) {
            $g = Github::getUserStats((string) $s->github_username, (string) ($s->github_token ?? ''));
            if ($g['stats']) {
                $social['github'] = ['repos' => $g['stats']->repos, 'url' => 'https://github.com/' . $s->github_username];
            }
        }

        if (($s->discord_enabled ?? 'off') === 'on' && !empty($s->discord_server_id)) {
            $social['discord'] = self::discordOnline((string) $s->discord_server_id);
        }

        return $social;
    }
}
