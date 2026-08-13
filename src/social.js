const { getUserStats } = require('./github');
const { getYouTubeData } = require('./youtube');

const cache = new Map();
const TTL = 120000;

function cacheGet(key) {
  const hit = cache.get(key);
  if (!hit) return null;
  if (Date.now() - hit.ts > TTL) { cache.delete(key); return null; }
  return hit.data;
}

function cacheSet(key, data) {
  cache.set(key, { ts: Date.now(), data });
  if (cache.size > 20) {
    const oldest = cache.keys().next().value;
    if (oldest) cache.delete(oldest);
  }
}

async function getDiscordOnline(serverId) {
  if (!serverId) return { online: null, error: null };
  const hit = cacheGet('discord:' + serverId);
  if (hit) return hit;
  try {
    const r = await fetch(`https://discord.com/api/guilds/${encodeURIComponent(serverId)}/widget.json`, { headers: { 'User-Agent': 'NobitaHost' } });
    if (!r.ok) {
      const out = { online: null, error: `Discord API ${r.status}` };
      cacheSet('discord:' + serverId, out);
      return out;
    }
    const j = await r.json();
    const out = { online: j.presence_count || 0, error: null };
    cacheSet('discord:' + serverId, out);
    return out;
  } catch (e) {
    return { online: null, error: e.message };
  }
}

async function getSocialData(s) {
  const social = { youtube: null, instagram: null, github: null, discord: null };

  if (s.youtube_enabled === 'on' && s.youtube_channel) {
    try {
      const y = await getYouTubeData(s);
      if (y && y.channel) {
        const handle = (y.channel.handle || String(s.youtube_channel).replace(/^@/, ''));
        social.youtube = { subs: y.channel.subscribers, url: 'https://youtube.com/@' + handle };
      }
    } catch (e) {
      social.youtube = { subs: null, url: null, error: e.message };
    }
  }

  if (s.instagram_handle) {
    const h = String(s.instagram_handle).replace(/^@/, '');
    social.instagram = { handle: '@' + h, url: 'https://instagram.com/' + h };
  }

  if (s.github_username) {
    const g = await getUserStats(s.github_username, s.github_token);
    if (g.stats) social.github = { repos: g.stats.repos, url: 'https://github.com/' + s.github_username };
  }

  if (s.discord_enabled === 'on' && s.discord_server_id) {
    social.discord = await getDiscordOnline(s.discord_server_id);
  }

  return social;
}

module.exports = { getSocialData };
