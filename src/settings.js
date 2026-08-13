const db = require('./db');

const DEFAULT_SETTINGS = {
  panel_name: 'NobitaHost',
  panel_tagline: 'Full-Featured Hosting Panel',

  logo_type: 'emoji',
  logo_url: '',
  logo_emoji: '🔷',

  favicon_url: '',

  background_type: 'image',
  background_url: '',
  background_source: 'none',
  background_overlay: 'on',

  panel_blur: '16',
  transparency: '100',
  theme: 'dark',
  wallpaper_favs: '',

  music_type: 'none',
  music_url: '',
  music_volume: '40',

  transparent_bar: 'on',
  blur_bar: 'on',
  card_radius: '16',
  accent_color: '#3b82f6',

  register_open: 'on',
  maintenance: 'off',

  script_enabled: 'on',
  script_badge: 'All In One CMD',
  script_description: 'Execute the master script to install all dependencies instantly.',
  script_command: 'bash <(curl -s https://ptero.nobitahost.in)',

  discord_enabled: 'on',
  discord_server_id: '1472654686174843012',
  discord_channel: '1525049151627333715',
  discord_theme: 'dark',

  youtube_enabled: 'on',
  youtube_channel: '@codinghub_studiyo',
  youtube_api_key: '',

  instagram_handle: '@nobita_dev.in',

  github_username: '',
  github_token: '',

  smtp_host: '',
  smtp_port: '587',
  smtp_secure: 'false',
  smtp_user: '',
  smtp_pass: '',
  mail_from: 'NobitaHost <noreply@nobitahost.local>',
  mail_enabled: 'off'
};

const WALLPAPER_SOURCES = [
  { slug: 'none', label: 'None (Solid dark)' },
  { slug: 'cute-kawaii-wallpapers', label: 'Cute Kawaii' },
  { slug: 'ultrawide-monitor-hd-wallpapers', label: 'Ultrawide Monitor HD' },
  { slug: 'cool-wallpapers', label: 'Cool' },
  { slug: 'black-dark', label: 'Black & Dark' },
  { slug: 'aesthetic-wallpapers', label: 'Aesthetic' },
  { slug: 'space', label: 'Space' },
  { slug: 'cr7-wallpapers', label: 'CR7' },
  { slug: 'all', label: 'All Categories' }
];

function getSettings() {
  const rows = db.prepare('SELECT key, value FROM settings').all();
  const out = { ...DEFAULT_SETTINGS };
  for (const r of rows) out[r.key] = r.value;
  return out;
}

function setSetting(key, value) {
  db.prepare(
    'INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value'
  ).run(key, String(value == null ? '' : value));
}

function setSettingsMany(obj) {
  const stmt = db.prepare(
    'INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value'
  );
  const tx = db.transaction((entries) => {
    for (const [k, v] of entries) stmt.run(k, String(v == null ? '' : v));
  });
  tx(Object.entries(obj));
}

function resolveBackground(s) {
  if (s.background_url) {
    return { url: s.background_url, type: s.background_type === 'video' ? 'video' : 'image' };
  }
  if (s.background_source && s.background_source !== 'none') {
    const seed = encodeURIComponent(s.background_source);
    const source = s.background_source === 'all' ? '' : s.background_source + '/';
    return {
      url: `https://picsum.photos/seed/${seed}-nobitahost/1920/1080`,
      type: 'image',
      sourcePage: `https://4kwallpapers.com/${source}`
    };
  }
  return { url: '', type: 'image' };
}

function overlayAlpha(s) {
  const t = Math.min(100, Math.max(0, parseInt(s.transparency, 10) || 100));
  return ((100 - t) / 100 * 0.55).toFixed(3);
}

function normalizeBlur(s) {
  const b = parseInt(s.panel_blur, 10) || 0;
  return Math.min(40, Math.max(0, b));
}

module.exports = {
  DEFAULT_SETTINGS,
  WALLPAPER_SOURCES,
  getSettings,
  setSetting,
  setSettingsMany,
  resolveBackground,
  overlayAlpha,
  normalizeBlur
};
