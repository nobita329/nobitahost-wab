const express = require('express');
const path = require('path');
const bcrypt = require('bcryptjs');
const { authenticator } = require('otplib');
const qrcode = require('qrcode');
const db = require('../db');
const { getSettings, setSettingsMany, setSetting, resolveBackground, overlayAlpha, normalizeBlur, WALLPAPER_SOURCES } = require('../settings');
const { requireAuth, requireAdmin, logActivity } = require('../middleware/auth');
const { upload } = require('../middleware/upload');
const { getSystemStats, recordSample, getHistory } = require('../system');
const { getYouTubeData } = require('../youtube');
const { getRepos: getGithubRepos, getUserStats: getGithubStats } = require('../github');
const { getSocialData } = require('../social');
const { CATEGORIES, fetchWallpapers, getCategoryLabel } = require('../wallpapers');

const router = express.Router();
const CONTENT_SLUGS = ['home', 'team', 'tutorials', 'command', 'analytics', 'projects', 'links', 'github', 'about'];

const LINK_ICONS = ['🔗', '🌐', '🌍', '📧', '📬', '💬', '💭', '🎮', '🕹️', '🎯', '📱', '💻', '🖥️', '⌨️', '🎧', '🎵', '🎬', '📺', '📸', '🎥', '🎨', '🎭', '🏆', '🥇', '💎', '💰', '💳', '🛒', '🛍️', '🔒', '🔑', '⚙️', '🛠️', '🔧', '📦', '🚀', '☁️', '🗄️', '📊', '📈', '🧮', '📁', '📂', '📄', '📝', '📌', '📍', '🧭', '⭐', '❤️', '🔥', '⚡', '🎲', '🧩', '🤖', '👾', '🤝', '🌟', '🎉', '🎁'];

function getFavs(s) {
  try {
    const arr = JSON.parse(s.wallpaper_favs || '[]');
    return Array.isArray(arr) ? arr : [];
  } catch (e) {
    return [];
  }
}

function saveFavs(favs) {
  setSetting('wallpaper_favs', JSON.stringify(favs));
}

function track(page) {
  return (req, res, next) => {
    try {
      db.prepare('INSERT INTO analytics (page, date, hits) VALUES (?, date("now"), 1) ON CONFLICT(page, date) DO UPDATE SET hits = hits + 1').run(page);
    } catch (e) { /* noop */ }
    next();
  };
}

function pageView(req, res, view, opts = {}) {
  const s = res.locals.settings;
  const bg = resolveBackground(s);
  res.render(view, {
    title: opts.title || '',
    active: opts.active || '',
    bodyClass: opts.bodyClass || '',
    bg,
    ...opts
  });
}

function getUserPage(slug) {
  return db.prepare('SELECT * FROM content_pages WHERE slug = ?').get(slug);
}

router.get('/', track('home'), async (req, res) => {
  const cp = getUserPage('home');
  const stats = {
    team: db.prepare('SELECT COUNT(*) c FROM users WHERE owner_id = ?').get(req.user ? req.user.id : 0).c,
    tutorials: db.prepare('SELECT COUNT(*) c FROM tutorials').get().c,
    activity: db.prepare('SELECT COUNT(*) c FROM activity WHERE user_id = ?').get(req.user ? req.user.id : 0).c,
    online: req.user ? 1 : 0
  };
  const s = res.locals.settings;
  let social = { youtube: null, instagram: null, github: null, discord: null };
  try {
    social = await getSocialData(s);
  } catch (e) {
    social = { youtube: null, instagram: null, github: null, discord: null };
  }
  pageView(req, res, 'pages/user/home', { title: 'Home', active: 'home', page: cp, stats, social, extraScripts: '<script src="/js/home.js"></script>' });
});

router.get('/team', track('team'), (req, res) => {
  const cp = getUserPage('team');
  const isAdmin = req.user && req.user.role === 'admin';
  const members = isAdmin
    ? db.prepare('SELECT u.*, r.name AS custom_role, r.color AS custom_color FROM users u LEFT JOIN roles r ON r.id = u.custom_role_id ORDER BY u.created_at DESC').all()
    : db.prepare('SELECT u.*, r.name AS custom_role, r.color AS custom_color FROM users u LEFT JOIN roles r ON r.id = u.custom_role_id ORDER BY u.created_at DESC').all();
  const roles = db.prepare('SELECT * FROM roles ORDER BY sort_order, id').all();
  pageView(req, res, 'pages/user/team', { title: 'Team', active: 'team', page: cp, members, roles, isAdmin, query: req.query });
});

router.get('/tutorials', track('tutorials'), async (req, res) => {
  const cp = getUserPage('tutorials');
  const tutorials = db.prepare('SELECT t.*, u.username AS author FROM tutorials t LEFT JOIN users u ON u.id = t.author_id ORDER BY t.created_at DESC').all();
  const s = res.locals.settings;
  let yt = null;
  if (s.youtube_enabled === 'on' && s.youtube_channel) {
    try {
      yt = await getYouTubeData(s);
    } catch (e) {
      yt = { error: e.message };
    }
  }
  pageView(req, res, 'pages/user/tutorials', { title: 'Tutorials', active: 'tutorials', page: cp, tutorials, yt, fmt: require('../youtube').fmt });
});

router.get('/command', track('command'), (req, res) => {
  const cp = getUserPage('command');
  pageView(req, res, 'pages/user/command', { title: 'Command', active: 'command', page: cp });
});

router.get('/analytics', track('analytics'), (req, res) => {
  const cp = getUserPage('analytics');
  const last7 = db.prepare("SELECT page, date, SUM(hits) AS hits FROM analytics WHERE date >= date('now', '-6 day') GROUP BY page, date ORDER BY date").all();
  const totalUsers = db.prepare('SELECT COUNT(*) c FROM users').get().c;
  const activeUsers = db.prepare("SELECT COUNT(*) c FROM users WHERE status = 'active'").get().c;
  const suspended = db.prepare("SELECT COUNT(*) c FROM users WHERE status = 'suspended'").get().c;
  const admins = db.prepare("SELECT COUNT(*) c FROM users WHERE role = 'admin'").get().c;
  const todayHits = db.prepare("SELECT COALESCE(SUM(hits),0) c FROM analytics WHERE date = date('now')").get().c;
  const todayLogins = db.prepare("SELECT COUNT(*) c FROM activity WHERE date(created_at) = date('now') AND action LIKE '%ogged in%'").get().c;
  pageView(req, res, 'pages/user/analytics', {
    title: 'Analytics', active: 'analytics', page: cp, last7, stats: { totalUsers, activeUsers, suspended, admins, todayHits, todayLogins },
    system: getSystemStats()
  });
});

router.get('/system-data', (req, res) => {
  const stats = getSystemStats();
  recordSample(stats);
  res.json({ success: true, system: stats });
});

router.get('/system-history', (req, res) => {
  res.json({ success: true, history: getHistory(req.query.range || '24h') });
});

router.get('/projects', track('projects'), (req, res) => {
  const cp = getUserPage('projects');
  const projects = db.prepare('SELECT * FROM projects ORDER BY sort_order, id').all();
  pageView(req, res, 'pages/user/projects', {
    title: 'Projects', active: 'projects', page: cp, projects, query: req.query,
    isAdmin: req.user && req.user.role === 'admin',
    extraScripts: '<script src="/js/projects.js"></script>'
  });
});

router.get('/links', track('links'), (req, res) => {
  const cp = getUserPage('links');
  const links = db.prepare('SELECT * FROM links ORDER BY sort_order, id').all();
  pageView(req, res, 'pages/user/links', {
    title: 'Links', active: 'links', page: cp, links, query: req.query,
    isAdmin: req.user && req.user.role === 'admin',
    icons: LINK_ICONS,
    extraScripts: '<script src="/js/links.js"></script>'
  });
});

router.get('/github', track('github'), async (req, res) => {
  const cp = getUserPage('github');
  const github = db.prepare('SELECT * FROM github_links ORDER BY sort_order, id').all();
  const s = res.locals.settings;
  const ghUsername = s.github_username || '';
  const ghToken = s.github_token || '';
  const users = ghUsername ? [ghUsername] : [];
  github.forEach((g) => { if (!users.includes(g.username)) users.push(g.username); });
  const reposByUser = [];
  for (const username of users) {
    reposByUser.push({ username, ...(await getGithubRepos(username, ghToken)) });
  }
  const mainStats = ghUsername ? await getGithubStats(ghUsername, ghToken) : { stats: null, error: null };
  pageView(req, res, 'pages/user/github', {
    title: 'GitHub', active: 'github', page: cp, github, reposByUser, stats: mainStats.stats, statsError: mainStats.error, query: req.query,
    ghConfigured: !!ghUsername,
    isAdmin: req.user && req.user.role === 'admin',
    extraScripts: '<script src="/js/github.js"></script>'
  });
});

router.post('/github/api-settings', requireAdmin, (req, res) => {
  const { github_username, github_token } = req.body;
  const upd = {};
  if (github_username != null) upd.github_username = github_username.trim();
  if (github_token) upd.github_token = github_token.trim();
  setSettingsMany(upd);
  logActivity(req.user, 'Updated GitHub API settings', req);
  res.redirect('/github?apikey=1');
});

router.get('/about', track('about'), (req, res) => {
  const cp = getUserPage('about');
  pageView(req, res, 'pages/user/about', { title: 'About', active: 'about', page: cp, query: req.query, isAdmin: req.user && req.user.role === 'admin' });
});

router.get('/page/:slug', (req, res) => {
  const slug = String(req.params.slug).toLowerCase();
  const page = db.prepare('SELECT * FROM content_pages WHERE slug = ?').get(slug);
  if (!page) {
    return res.status(404).render('pages/errors/404', {
      title: '404', bodyClass: 'auth-page', bg: resolveBackground(res.locals.settings)
    });
  }
  if (CONTENT_SLUGS.includes(slug)) return res.redirect('/' + slug);
  try {
    db.prepare('INSERT INTO analytics (page, date, hits) VALUES (?, date("now"), 1) ON CONFLICT(page, date) DO UPDATE SET hits = hits + 1').run('page:' + slug);
  } catch (e) { /* noop */ }
  pageView(req, res, 'pages/user/custom-page', { title: page.title, active: slug, page });
});

router.post('/about/update', requireAdmin, (req, res) => {
  db.prepare("UPDATE content_pages SET content = ?, updated_at = datetime('now'), updated_by = ? WHERE slug = 'about'")
    .run(req.body.content || '', req.user.id);
  logActivity(req.user, 'Updated About page', req);
  res.redirect('/about?saved=1');
});

router.get('/activity', (req, res) => {
  const rows = req.user
    ? (req.user.role === 'admin'
        ? db.prepare('SELECT * FROM activity ORDER BY created_at DESC LIMIT 100').all()
        : db.prepare('SELECT * FROM activity WHERE user_id = ? ORDER BY created_at DESC LIMIT 100').all(req.user.id))
    : db.prepare('SELECT * FROM activity ORDER BY created_at DESC LIMIT 100').all();
  pageView(req, res, 'pages/user/activity', { title: 'Activity', active: 'activity', logs: rows });
});

router.get('/profile', requireAuth, (req, res) => {
  pageView(req, res, 'pages/user/profile', { title: 'My Profile', active: 'profile', query: req.query });
});

router.post('/profile', requireAuth, upload.single('profile_pic'), (req, res) => {
  const { bio } = req.body;
  const pic = req.file ? `/uploads/${req.file.filename}` : req.body.existing_pic || req.user.profile_pic || '';
  db.prepare('UPDATE users SET bio = ?, profile_pic = ? WHERE id = ?').run(bio || '', pic, req.user.id);
  logActivity(req.user, 'Updated profile', req);
  res.redirect('/profile?saved=1');
});

router.post('/profile/password', requireAuth, (req, res) => {
  if (req.user.is_demo) return res.redirect('/profile?error=demo');
  const { current, password, confirm } = req.body;
  if (!bcrypt.compareSync(current || '', req.user.password)) return res.redirect('/profile?error=current');
  if (!password || password.length < 6) return res.redirect('/profile?error=short');
  if (password !== confirm) return res.redirect('/profile?error=match');
  db.prepare('UPDATE users SET password = ? WHERE id = ?').run(bcrypt.hashSync(password, 10), req.user.id);
  logActivity(req.user, 'Changed password', req);
  res.redirect('/profile?password=1');
});

router.post('/profile/2fa/enable', requireAuth, (req, res) => {
  const { code } = req.body;
  const secret = req.body.secret || req.user.two_factor_secret;
  if (!code) return res.redirect('/profile?error=2fa-code');
  if (!authenticator.verify({ token: String(code).replace(/\s/g, ''), secret })) {
    return res.redirect('/profile?error=2fa-invalid');
  }
  db.prepare('UPDATE users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?').run(secret, req.user.id);
  logActivity(req.user, 'Enabled 2FA', req);
  res.redirect('/profile?2fa=on');
});

router.post('/profile/2fa/disable', requireAuth, (req, res) => {
  db.prepare('UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?').run(req.user.id);
  logActivity(req.user, 'Disabled 2FA', req);
  res.redirect('/profile?2fa=off');
});

router.get('/profile/2fa/setup', requireAuth, async (req, res) => {
  const secret = req.user.two_factor_secret || authenticator.generateSecret();
  const keyuri = authenticator.keyuri(req.user.email, req.user.username, secret);
  const qr = await qrcode.toDataURL(keyuri);
  pageView(req, res, 'pages/user/2fa-setup', { title: '2FA Setup', active: 'profile', secret, qr });
});

/* ---------- TEAM ---------- */

router.post('/team/create', requireAdmin, (req, res) => {
  const { username, email, password, custom_role_id } = req.body;
  if (!username || !email || !password || password.length < 6) return res.redirect('/team?error=1');
  const exists = db.prepare('SELECT id FROM users WHERE username = ? OR email = ?').get(username, email);
  if (exists) return res.redirect('/team?error=exists');
  const roleId = custom_role_id ? Number(custom_role_id) : null;
  const info = db.prepare('INSERT INTO users (username, email, password, role, owner_id, custom_role_id) VALUES (?, ?, ?, ?, ?, ?)')
    .run(username, email, bcrypt.hashSync(password, 10), 'user', req.user.id, roleId);
  const created = db.prepare('SELECT * FROM users WHERE id = ?').get(info.lastInsertRowid);
  logActivity(req.user, `Team member created by ${req.user.username}`, req);
  res.redirect('/team?created=1');
});

function canManage(target, req) {
  return req.user.role === 'admin' || target.owner_id === req.user.id;
}

router.post('/team/:id/edit', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM users WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/team');
  const { username, email, custom_role_id } = req.body;
  const roleId = custom_role_id ? Number(custom_role_id) : null;
  db.prepare('UPDATE users SET username = ?, email = ?, custom_role_id = ? WHERE id = ?').run(username || target.username, email || target.email, roleId, target.id);
  logActivity(req.user, `Edited team member ${target.username}`, req);
  res.redirect('/team?edited=1');
});

router.post('/team/:id/suspend', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM users WHERE id = ?').get(req.params.id);
  if (!target || target.id === req.user.id) return res.redirect('/team');
  const status = target.status === 'suspended' ? 'active' : 'suspended';
  db.prepare('UPDATE users SET status = ? WHERE id = ?').run(status, target.id);
  logActivity(req.user, `${status === 'suspended' ? 'Suspended' : 'Activated'} ${target.username}`, req);
  res.redirect('/team?suspend=1');
});

router.post('/team/:id/delete', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM users WHERE id = ?').get(req.params.id);
  if (!target || target.id === req.user.id) return res.redirect('/team');
  const admins = db.prepare("SELECT COUNT(*) c FROM users WHERE role = 'admin'").get().c;
  if (target.role === 'admin' && admins <= 1) return res.redirect('/team');
  db.prepare('DELETE FROM users WHERE id = ?').run(target.id);
  logActivity(req.user, `Deleted team member ${target.username}`, req);
  res.redirect('/team?deleted=1');
});

router.get('/team/member/:id', (req, res) => {
  const target = db.prepare('SELECT u.*, r.name AS custom_role, r.color AS custom_color FROM users u LEFT JOIN roles r ON r.id = u.custom_role_id WHERE u.id = ?').get(req.params.id);
  if (!target) return res.redirect('/team');
  pageView(req, res, 'pages/user/member-profile', { title: `Profile · ${target.username}`, active: 'team', member: target, isAdmin: req.user && req.user.role === 'admin' });
});

/* ---------- TEAM ROLES ---------- */

router.post('/team/roles/create', requireAdmin, (req, res) => {
  const { name, color } = req.body;
  const cleanName = (name || '').trim();
  if (!cleanName) return res.redirect('/team?error=role');
  const exists = db.prepare('SELECT id FROM roles WHERE name = ?').get(cleanName);
  if (exists) return res.redirect('/team?error=roleexists');
  const maxSort = db.prepare('SELECT COALESCE(MAX(sort_order),0) m FROM roles').get().m;
  db.prepare('INSERT INTO roles (name, color, sort_order) VALUES (?, ?, ?)').run(cleanName, color || '#3b82f6', maxSort + 1);
  logActivity(req.user, `Created role ${cleanName}`, req);
  res.redirect('/team?rolecreated=1');
});

router.post('/team/roles/:id/edit', requireAdmin, (req, res) => {
  const role = db.prepare('SELECT * FROM roles WHERE id = ?').get(req.params.id);
  if (!role) return res.redirect('/team');
  const { name, color } = req.body;
  db.prepare('UPDATE roles SET name = ?, color = ? WHERE id = ?').run((name || role.name).trim(), color || role.color, role.id);
  logActivity(req.user, `Edited role ${role.name}`, req);
  res.redirect('/team?roleedited=1');
});

router.post('/team/roles/:id/delete', requireAdmin, (req, res) => {
  const role = db.prepare('SELECT * FROM roles WHERE id = ?').get(req.params.id);
  if (!role) return res.redirect('/team');
  db.prepare('UPDATE users SET custom_role_id = NULL WHERE custom_role_id = ?').run(role.id);
  db.prepare('DELETE FROM roles WHERE id = ?').run(role.id);
  logActivity(req.user, `Deleted role ${role.name}`, req);
  res.redirect('/team?roledeleted=1');
});

/* ---------- ADMIN ---------- */

router.get('/admin', requireAdmin, (req, res) => {
  const totalUsers = db.prepare('SELECT COUNT(*) c FROM users').get().c;
  const activeUsers = db.prepare("SELECT COUNT(*) c FROM users WHERE status='active'").get().c;
  const suspended = db.prepare("SELECT COUNT(*) c FROM users WHERE status='suspended'").get().c;
  const admins = db.prepare("SELECT COUNT(*) c FROM users WHERE role='admin'").get().c;
  const tutorials = db.prepare('SELECT COUNT(*) c FROM tutorials').get().c;
  const todayHits = db.prepare("SELECT COALESCE(SUM(hits),0) c FROM analytics WHERE date = date('now')").get().c;
  const totalHits = db.prepare('SELECT COALESCE(SUM(hits),0) c FROM analytics').get().c;
  const recent = db.prepare('SELECT * FROM activity ORDER BY created_at DESC LIMIT 8').all();
  const last7 = db.prepare("SELECT page, date, SUM(hits) AS hits FROM analytics WHERE date >= date('now','-6 day') GROUP BY page, date ORDER BY date").all();
  pageView(req, res, 'pages/admin/dashboard', {
    title: 'Admin Dashboard', active: 'admin', stats: { totalUsers, activeUsers, suspended, admins, tutorials, todayHits, totalHits }, recent, last7
  });
});

router.get('/admin/create', requireAdmin, (req, res) => {
  pageView(req, res, 'pages/admin/create', { title: 'Create User', active: 'admin-create', error: null, success: null });
});

router.post('/admin/create', requireAdmin, (req, res) => {
  const { username, email, password, role } = req.body;
  if (!username || !email || !password || password.length < 6) {
    return pageView(req, res, 'pages/admin/create', { title: 'Create User', active: 'admin-create', error: 'All fields required, password min 6 chars', success: null });
  }
  const exists = db.prepare('SELECT id FROM users WHERE username = ? OR email = ?').get(username, email);
  if (exists) {
    return pageView(req, res, 'pages/admin/create', { title: 'Create User', active: 'admin-create', error: 'Username or email already exists', success: null });
  }
  const info = db.prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)')
    .run(username, email, bcrypt.hashSync(password, 10), role === 'admin' ? 'admin' : 'user');
  const created = db.prepare('SELECT * FROM users WHERE id = ?').get(info.lastInsertRowid);
  logActivity(created, 'Account created by admin', req);
  pageView(req, res, 'pages/admin/create', { title: 'Create User', active: 'admin-create', error: null, success: `User ${username} created successfully` });
});

router.get('/admin/users', requireAdmin, (req, res) => {
  const q = req.query.q || '';
  const users = q
    ? db.prepare('SELECT u.*, o.username AS owner FROM users u LEFT JOIN users o ON o.id = u.owner_id WHERE u.username LIKE ? OR u.email LIKE ? ORDER BY u.created_at DESC').all(`%${q}%`, `%${q}%`)
    : db.prepare('SELECT u.*, o.username AS owner FROM users u LEFT JOIN users o ON o.id = u.owner_id ORDER BY u.created_at DESC').all();
  pageView(req, res, 'pages/admin/users', { title: 'User Management', active: 'admin-users', users, q });
});

router.post('/admin/users/:id/edit', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM users WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/admin/users');
  const { username, email, role } = req.body;
  db.prepare('UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?')
    .run(username || target.username, email || target.email, role === 'admin' ? 'admin' : 'user', target.id);
  logActivity(req.user, `Edited user ${target.username}`, req);
  res.redirect('/admin/users?edited=1');
});

router.post('/admin/users/:id/suspend', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM users WHERE id = ?').get(req.params.id);
  if (!target || target.id === req.user.id) return res.redirect('/admin/users');
  const status = target.status === 'suspended' ? 'active' : 'suspended';
  db.prepare('UPDATE users SET status = ? WHERE id = ?').run(status, target.id);
  logActivity(req.user, `${status === 'suspended' ? 'Suspended' : 'Activated'} ${target.username}`, req);
  res.redirect('/admin/users?suspend=1');
});

router.post('/admin/users/:id/delete', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM users WHERE id = ?').get(req.params.id);
  if (!target || target.id === req.user.id) return res.redirect('/admin/users');
  db.prepare('DELETE FROM users WHERE id = ?').run(target.id);
  logActivity(req.user, `Deleted user ${target.username}`, req);
  res.redirect('/admin/users?deleted=1');
});

router.get('/admin/activity', requireAdmin, (req, res) => {
  const rows = db.prepare('SELECT * FROM activity ORDER BY created_at DESC LIMIT 200').all();
  pageView(req, res, 'pages/admin/activity', { title: 'Activity Log', active: 'admin-activity', logs: rows });
});

/* ---------- SETTINGS MANAGEMENT ---------- */

router.get('/admin/settings', requireAdmin, (req, res) => {
  const s = getSettings();
  pageView(req, res, 'pages/admin/settings', {
    title: 'Settings Management', active: 'admin-settings', settings: s, sources: WALLPAPER_SOURCES, saved: !!req.query.saved, overlay: overlayAlpha(s), glass: normalizeBlur(s), wpCats: CATEGORIES, favs: getFavs(s)
  });
});

router.post('/admin/settings', requireAdmin, upload.fields([
  { name: 'logo_file', maxCount: 1 },
  { name: 'favicon_file', maxCount: 1 },
  { name: 'background_file', maxCount: 1 },
  { name: 'music_file', maxCount: 1 }
]), (req, res) => {
  const b = req.body;
  const upd = {};

  upd.panel_name = b.panel_name;
  upd.panel_tagline = b.panel_tagline;
  upd.logo_type = b.logo_type || 'emoji';
  upd.logo_url = b.logo_url || '';
  upd.logo_emoji = b.logo_emoji || '🔷';
  upd.favicon_url = b.favicon_url || '';
  upd.background_type = b.background_type || 'image';
  upd.background_url = b.background_url || '';
  upd.background_source = b.background_source || 'none';
  upd.background_overlay = b.background_overlay ? 'on' : 'off';
  upd.panel_blur = normalizeBlur(b);
  upd.transparency = b.transparency || '100';
  upd.theme = b.theme === 'light' ? 'light' : 'dark';
  upd.music_type = b.music_type || 'none';
  upd.music_url = b.music_url || '';
  upd.music_volume = b.music_volume || '40';
  upd.transparent_bar = b.transparent_bar ? 'on' : 'off';
  upd.blur_bar = b.blur_bar ? 'on' : 'off';
  upd.card_radius = b.card_radius || '16';
  upd.accent_color = b.accent_color || '#3b82f6';
  upd.register_open = b.register_open ? 'on' : 'off';
  upd.maintenance = b.maintenance ? 'on' : 'off';
  upd.script_enabled = b.script_enabled ? 'on' : 'off';
  upd.script_badge = b.script_badge || '';
  upd.script_description = b.script_description || '';
  upd.script_command = b.script_command || '';
  upd.discord_enabled = b.discord_enabled ? 'on' : 'off';
  upd.discord_server_id = b.discord_server_id || '';
  upd.discord_channel = b.discord_channel || '';
  upd.discord_theme = b.discord_theme || 'dark';
  upd.youtube_enabled = b.youtube_enabled ? 'on' : 'off';
  upd.youtube_channel = b.youtube_channel || '';
  upd.youtube_api_key = b.youtube_api_key || '';
  upd.instagram_handle = b.instagram_handle || '';
  upd.smtp_host = b.smtp_host || '';
  upd.smtp_port = b.smtp_port || '587';
  upd.smtp_secure = b.smtp_secure ? 'true' : 'false';
  upd.smtp_user = b.smtp_user || '';
  upd.smtp_pass = b.smtp_pass || '';
  upd.mail_from = b.mail_from || '';
  upd.mail_enabled = b.mail_enabled ? 'on' : 'off';

  const files = req.files || {};
  if (files.logo_file && files.logo_file[0]) upd.logo_url = `/uploads/${files.logo_file[0].filename}`;
  if (files.favicon_file && files.favicon_file[0]) upd.favicon_url = `/uploads/${files.favicon_file[0].filename}`;
  if (files.background_file && files.background_file[0]) {
    const f = files.background_file[0];
    upd.background_url = `/uploads/${f.filename}`;
    upd.background_type = /video/i.test(f.mimetype) ? 'video' : 'image';
  }
  if (files.music_file && files.music_file[0]) {
    upd.music_url = `/uploads/${files.music_file[0].filename}`;
    upd.music_type = 'url';
  }

  setSettingsMany(upd);
  logActivity(req.user, 'Updated panel settings', req);
  res.redirect('/admin/settings?saved=1');
});

const AUTO_SAVE_KEYS = ['panel_blur', 'transparency', 'theme', 'card_radius', 'accent_color', 'background_url', 'background_type', 'background_source', 'panel_name', 'logo_type', 'logo_emoji', 'logo_url', 'favicon_url'];

router.post('/admin/settings/api', requireAdmin, (req, res) => {
  try {
    const b = req.body || {};
    for (const k of Object.keys(b)) {
      if (!AUTO_SAVE_KEYS.includes(k)) continue;
      if (k === 'theme') setSetting(k, b[k] === 'light' ? 'light' : 'dark');
      else if (k === 'panel_blur') setSetting(k, String(normalizeBlur(b)));
      else if (k === 'transparency') {
        const t = Math.min(100, Math.max(0, parseInt(b[k], 10) || 100));
        setSetting(k, String(t));
      } else setSetting(k, String(b[k]));
    }
    return res.json({ ok: true });
  } catch (e) {
    return res.status(500).json({ ok: false, error: e.message });
  }
});

router.get('/admin/wallpapers', requireAdmin, async (req, res) => {
  const s = getSettings();
  if (req.query.fav === '1') {
    return res.json({ ok: true, favs: getFavs(s) });
  }
  const page = Math.max(1, parseInt(req.query.page, 10) || 1);
  try {
    const data = await fetchWallpapers({ category: req.query.category, page, q: req.query.q });
    const favs = getFavs(s);
    const favIds = new Set(favs.map((f) => f.id));
    const items = data.items.map((it) => ({ ...it, fav: favIds.has(it.id) }));
    res.json({
      ok: true,
      items,
      page: data.page,
      hasNext: data.hasNext,
      totalPages: data.totalPages,
      category: req.query.category || 'all',
      categories: CATEGORIES
    });
  } catch (e) {
    res.status(502).json({ ok: false, error: e.message || 'Wallpaper source unreachable' });
  }
});

router.post('/admin/wallpapers/fav', requireAdmin, (req, res) => {
  try {
    const s = getSettings();
    const favs = getFavs(s);
    const b = req.body || {};
    if (b.action === 'clear') {
      saveFavs([]);
      return res.json({ ok: true, favs: [] });
    }
    const item = b.item || {};
    const idx = favs.findIndex((f) => f.id === item.id);
    if (b.action === 'remove' || idx > -1) {
      if (idx > -1) favs.splice(idx, 1);
    } else if (item.id && item.full) {
      favs.push({
        id: item.id,
        title: item.title || 'Wallpaper',
        thumb: item.thumb,
        full: item.full,
        detail: item.detail || '',
        category: item.category || '',
        fav: true
      });
    }
    saveFavs(favs);
    res.json({ ok: true, favs });
  } catch (e) {
    res.status(500).json({ ok: false, error: e.message });
  }
});

router.post('/admin/settings/reset', requireAdmin, (req, res) => {
  try {
    setSettingsMany({
      background_url: '',
      background_source: 'none',
      background_type: 'image',
      panel_blur: '16',
      transparency: '100',
      theme: 'dark',
      card_radius: '16',
      accent_color: '#3b82f6'
    });
    logActivity(req.user, 'Reset panel appearance to defaults', req);
    res.json({ ok: true });
  } catch (e) {
    res.status(500).json({ ok: false, error: e.message });
  }
});

router.get('/admin/settings/content', requireAdmin, (req, res) => {
  const pages = db.prepare('SELECT * FROM content_pages ORDER BY id').all();
  const editing = req.query.edit ? db.prepare('SELECT * FROM content_pages WHERE slug = ?').get(req.query.edit) : null;
  pageView(req, res, 'pages/admin/content', { title: 'Content Management', active: 'admin-content', pages, editing, contentSlugs: CONTENT_SLUGS, query: req.query });
});

router.post('/admin/settings/content/create', requireAdmin, (req, res) => {
  const { title, slug, icon, content, in_nav } = req.body;
  if (!title || !slug) return res.redirect('/admin/settings/content?error=create');
  const cleanSlug = String(slug).toLowerCase().replace(/[^a-z0-9-]/g, '');
  if (!cleanSlug) return res.redirect('/admin/settings/content?error=create');
  const exists = db.prepare('SELECT id FROM content_pages WHERE slug = ?').get(cleanSlug);
  if (exists) return res.redirect('/admin/settings/content?error=exists');
  db.prepare('INSERT INTO content_pages (slug, title, content, icon, in_nav, updated_at, updated_by) VALUES (?, ?, ?, ?, ?, datetime(\'now\'), ?)')
    .run(cleanSlug, title, content || '', icon || '📄', in_nav ? 1 : 0, req.user.id);
  logActivity(req.user, `Created custom page "${cleanSlug}"`, req);
  res.redirect('/admin/settings/content?edit=' + cleanSlug + '&created=1');
});

router.post('/admin/settings/content/:slug', requireAdmin, (req, res) => {
  const { title, content } = req.body;
  db.prepare("UPDATE content_pages SET title = ?, content = ?, updated_at = datetime('now'), updated_by = ? WHERE slug = ?")
    .run(title, content, req.user.id, req.params.slug);
  logActivity(req.user, `Updated content page "${req.params.slug}"`, req);
  res.redirect('/admin/settings/content?edit=' + req.params.slug + '&saved=1');
});

router.post('/admin/settings/content/:slug/delete', requireAdmin, (req, res) => {
  const slug = String(req.params.slug).toLowerCase();
  if (CONTENT_SLUGS.includes(slug)) return res.redirect('/admin/settings/content?error=system');
  db.prepare('DELETE FROM content_pages WHERE slug = ?').run(slug);
  logActivity(req.user, `Deleted custom page "${slug}"`, req);
  res.redirect('/admin/settings/content?deleted=1');
});

router.get('/admin/settings/tutorials', requireAdmin, (req, res) => {
  const tutorials = db.prepare('SELECT t.*, u.username AS author FROM tutorials t LEFT JOIN users u ON u.id = t.author_id ORDER BY t.created_at DESC').all();
  pageView(req, res, 'pages/admin/tutorials', { title: 'Tutorials Management', active: 'admin-tutorials', tutorials, query: req.query });
});

router.post('/admin/settings/tutorials', requireAdmin, (req, res) => {
  const { title, description, video_url, thumbnail } = req.body;
  if (!title) return res.redirect('/admin/settings/tutorials?error=1');
  db.prepare('INSERT INTO tutorials (title, description, video_url, thumbnail, author_id) VALUES (?, ?, ?, ?, ?)')
    .run(title, description || '', video_url || '', thumbnail || '', req.user.id);
  logActivity(req.user, `Added tutorial "${title}"`, req);
  res.redirect('/admin/settings/tutorials?saved=1');
});

router.post('/admin/settings/tutorials/:id/delete', requireAdmin, (req, res) => {
  db.prepare('DELETE FROM tutorials WHERE id = ?').run(req.params.id);
  logActivity(req.user, 'Deleted a tutorial', req);
  res.redirect('/admin/settings/tutorials?deleted=1');
});

/* ---------- LINKS MANAGEMENT ---------- */

router.post('/links/add', requireAdmin, (req, res) => {
  const { title, url, icon } = req.body;
  if (!title) return res.redirect('/links?error=title');
  db.prepare('INSERT INTO links (title, url, icon) VALUES (?, ?, ?)').run(title, url || '', icon || '🔗');
  logActivity(req.user, `Added link "${title}"`, req);
  res.redirect('/links?saved=1');
});

router.post('/links/:id/edit', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM links WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/links');
  const { title, url, icon } = req.body;
  db.prepare('UPDATE links SET title = ?, url = ?, icon = ? WHERE id = ?')
    .run(title || target.title, url || '', icon || '🔗', target.id);
  logActivity(req.user, `Edited link "${target.title}"`, req);
  res.redirect('/links?saved=1');
});

router.post('/links/:id/delete', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM links WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/links');
  db.prepare('DELETE FROM links WHERE id = ?').run(target.id);
  logActivity(req.user, `Deleted link "${target.title}"`, req);
  res.redirect('/links?deleted=1');
});

/* ---------- GITHUB MANAGEMENT ---------- */

router.post('/github/add', requireAdmin, (req, res) => {
  const { username, url, note } = req.body;
  if (!username) return res.redirect('/github?error=username');
  db.prepare('INSERT INTO github_links (username, url, note) VALUES (?, ?, ?)')
    .run(username, url || '', note || '');
  logActivity(req.user, `Added GitHub user "${username}"`, req);
  res.redirect('/github?saved=1');
});

router.post('/github/:id/edit', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM github_links WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/github');
  const { username, url, note } = req.body;
  db.prepare('UPDATE github_links SET username = ?, url = ?, note = ? WHERE id = ?')
    .run(username || target.username, url || '', note || '', target.id);
  logActivity(req.user, `Edited GitHub user "${target.username}"`, req);
  res.redirect('/github?saved=1');
});

router.post('/github/:id/delete', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM github_links WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/github');
  db.prepare('DELETE FROM github_links WHERE id = ?').run(target.id);
  logActivity(req.user, `Deleted GitHub user "${target.username}"`, req);
  res.redirect('/github?deleted=1');
});

/* ---------- PROJECTS MANAGEMENT ---------- */

router.post('/projects/add', requireAdmin, (req, res) => {
  const { name, description, url, button, thumbnail, html } = req.body;
  if (!name) return res.redirect('/projects?error=name');
  db.prepare('INSERT INTO projects (name, description, url, button, thumbnail, html) VALUES (?, ?, ?, ?, ?, ?)')
    .run(name, description || '', url || '', button || 'View Project', thumbnail || '', html || '');
  logActivity(req.user, `Added project "${name}"`, req);
  res.redirect('/projects?saved=1');
});

router.post('/projects/:id/edit', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM projects WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/projects');
  const { name, description, url, button, thumbnail, html } = req.body;
  db.prepare('UPDATE projects SET name = ?, description = ?, url = ?, button = ?, thumbnail = ?, html = ? WHERE id = ?')
    .run(name || target.name, description || '', url || '', button || 'View Project', thumbnail || '', html || '', target.id);
  logActivity(req.user, `Edited project "${target.name}"`, req);
  res.redirect('/projects?saved=1');
});

router.post('/projects/:id/delete', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM projects WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/projects');
  db.prepare('DELETE FROM projects WHERE id = ?').run(target.id);
  logActivity(req.user, `Deleted project "${target.name}"`, req);
  res.redirect('/projects?deleted=1');
});

module.exports = router;
module.exports.CONTENT_SLUGS = CONTENT_SLUGS;
