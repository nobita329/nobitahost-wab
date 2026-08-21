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
const blog = require('../blog');
const obsidian = require('../obsidian');
const cf = require('../cloudflare');
const { CATEGORIES, fetchWallpapers, getCategoryLabel } = require('../wallpapers');
const { isValidEmail, isValidUsername } = require('../validate');

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

const BOT_RE = /bot|crawl|spider|slurp|archiver|wget|curl|python|java|perl|ruby|go-http|scrapy|headless|phantom|lighthouse|pagespeed|semrush|ahrefs|mj12bot|dotbot|bingbot|yandex|googlebot|applebot|facebookexternalhit|twitterbot|linkedinbot|pinterestbot|discordbot|slackbot|telegrambot| whatsapp|signal|skype|zoom|teams|slack|mattermost|ciscoumbot|hubspot|drift|intercom|zendesk|freshdesk|crisp|tawk|olark|livechat|tidio|convertkit|mailchimp|sendgrid|postmark|mandrill|sparkpost|mailgun|ses\b/i;

function track(page) {
  return (req, res, next) => {
    try {
      db.prepare(`INSERT INTO analytics (page, date, hits) VALUES (?, date('now'), 1) ON CONFLICT(page, date) DO UPDATE SET hits = hits + 1`).run(page);
      const ip = req.headers['x-forwarded-for'] ? req.headers['x-forwarded-for'].split(',')[0].trim() : (req.ip || req.connection.remoteAddress || '').replace('::ffff:', '');
      const ua = (req.headers['user-agent'] || '').slice(0, 500);
      const ref = (req.headers['referer'] || req.headers['referrer'] || '').slice(0, 500);
      const userId = req.user ? req.user.id : null;
      if (!BOT_RE.test(ua)) {
        db.prepare(`INSERT INTO page_views (page, ip, user_agent, referrer, user_id, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))`).run(page, ip, ua, ref, userId);
      }
    } catch (e) { console.error('[track]', page, e.message); }
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
  pageView(req, res, 'pages/user/home', { title: 'Home', active: 'home', page: cp, stats, social, blogPosts: blog.latest(3), extraScripts: '<script src="/js/home.js"></script>' });
});

/* ---------- BLOG (Extras port) ---------- */

router.get('/blog', track('blog'), (req, res) => {
  const data = blog.listPublished({ search: String(req.query.search || '').trim(), tag: String(req.query.tag || '').trim(), sort: ['latest', 'oldest', 'updated', 'popular'].includes(req.query.sort) ? req.query.sort : 'latest', page: req.query.page });
  pageView(req, res, 'pages/user/blog', { title: 'Blog', active: 'blog', query: req.query, posts: data.posts, total: data.total, pages: data.pages, pageNum: data.page, tags: data.tags, fmtDate: blog.fmtDate });
});

router.get('/blog/:slug', track('blog'), (req, res) => {
  const post = blog.getPublished(req.params.slug);
  if (!post) return res.status(404).render('pages/errors/404', { title: '404', user: req.user, settings: res.locals.settings, bg: resolveBackground(res.locals.settings) });
  const vKey = `nh_vb_${post.id}`;
  if (!req.cookies[vKey]) {
    blog.recordView(post);
    res.cookie(vKey, '1', { maxAge: 30 * 24 * 3600 * 1000, httpOnly: true, sameSite: 'lax' });
  }
  let myFeedback = null;
  if (req.cookies.nh_sid) {
    const row = db.prepare('SELECT is_helpful FROM blog_post_feedback WHERE post_id = ? AND session_id = ?').get(post.id, req.cookies.nh_sid);
    if (row) myFeedback = !!row.is_helpful;
  }
  pageView(req, res, 'pages/user/blog-post', {
    title: post.seo_title || post.title,
    active: 'blog',
    post,
    myFeedback,
    metaDescription: post.seo_description || post.description || post.excerpt,
    fmtDate: blog.fmtDate
  });
});

router.post('/blog/:slug/feedback', (req, res) => {
  const back = safeBack(req.body.back) !== '/' ? safeBack(req.body.back) : `/blog/${req.params.slug}`;
  const post = db.prepare('SELECT id FROM blog_posts WHERE slug = ? AND is_published = 1').get(req.params.slug);
  if (!post) return res.redirect('/blog');
  let sid = req.cookies.nh_sid;
  if (!sid || sid.length > 64) {
    sid = require('crypto').randomBytes(16).toString('hex');
    res.cookie('nh_sid', sid, { maxAge: 365 * 24 * 3600 * 1000, httpOnly: true, sameSite: 'lax' });
  }
  const helpful = req.body.helpful === 'no' ? 0 : 1;
  const changed = blog.saveFeedback(post.id, helpful, sid);
  res.redirect(`${back}${back.includes('?') ? '&' : '?'}fb=${changed ? (helpful ? 'yes' : 'no') : 'same'}`);
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
  const { CATEGORIES, COMMANDS } = require('../commands');
  const cmdData = JSON.stringify(COMMANDS).replace(/<\//g, '<\\/');
  pageView(req, res, 'pages/user/command', { title: 'Command', active: 'command', page: cp, cmdData, catCounts: CATEGORIES });
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

router.get('/traffic-data', requireAdmin, (req, res) => {
  try {
    const todayHits = db.prepare("SELECT COALESCE(SUM(hits),0) c FROM analytics WHERE date = date('now')").get().c;
    const totalHits = db.prepare('SELECT COALESCE(SUM(hits),0) c FROM analytics').get().c;
    const todayUniques = db.prepare("SELECT COUNT(DISTINCT ip) c FROM page_views WHERE date(created_at) = date('now')").get().c;
    const totalUniques = db.prepare('SELECT COUNT(DISTINCT ip) c FROM page_views').get().c;
    const todayViews = db.prepare("SELECT COUNT(*) c FROM page_views WHERE date(created_at) = date('now')").get().c;
    const totalViews = db.prepare('SELECT COUNT(*) c FROM page_views').get().c;
    const last7 = db.prepare("SELECT date(created_at) AS date, COUNT(*) AS views, COUNT(DISTINCT ip) AS visitors FROM page_views WHERE date(created_at) >= date('now', '-6 day') GROUP BY date(created_at) ORDER BY date(created_at)").all();
    const pages = db.prepare("SELECT page, COUNT(*) AS views, COUNT(DISTINCT ip) AS visitors FROM page_views WHERE date(created_at) >= date('now', '-6 day') GROUP BY page ORDER BY views DESC LIMIT 10").all();
    const referrers = db.prepare("SELECT CASE WHEN referrer = '' THEN 'Direct' ELSE CASE WHEN referrer LIKE '%google%' THEN 'Google' WHEN referrer LIKE '%facebook%' OR referrer LIKE '%fb.com%' THEN 'Facebook' WHEN referrer LIKE '%twitter%' OR referrer LIKE '%x.com%' THEN 'Twitter' WHEN referrer LIKE '%instagram%' THEN 'Instagram' WHEN referrer LIKE '%youtube%' THEN 'YouTube' WHEN referrer LIKE '%reddit%' THEN 'Reddit' WHEN referrer LIKE '%github%' THEN 'GitHub' WHEN referrer LIKE '%t.me%' OR referrer LIKE '%telegram%' THEN 'Telegram' WHEN referrer LIKE '%discord%' THEN 'Discord' WHEN referrer LIKE '%linkedin%' THEN 'LinkedIn' WHEN referrer LIKE '%pinterest%' THEN 'Pinterest' WHEN referrer LIKE '%tiktok%' THEN 'TikTok' ELSE substr(referrer, 1, 40) END END AS source, COUNT(*) AS views, COUNT(DISTINCT ip) AS visitors FROM page_views WHERE date(created_at) >= date('now', '-6 day') GROUP BY source ORDER BY views DESC LIMIT 10").all();
    const hourly = db.prepare("SELECT strftime('%H', created_at) AS hour, COUNT(*) AS views, COUNT(DISTINCT ip) AS visitors FROM page_views WHERE date(created_at) = date('now') GROUP BY hour ORDER BY hour").all();
    const recentViews = db.prepare("SELECT id, page, ip, substr(user_agent, 1, 80) AS ua, substr(referrer, 1, 60) AS ref, created_at FROM page_views ORDER BY created_at DESC LIMIT 15").all();
    res.json({ ok: true, todayHits, totalHits, todayUniques, totalUniques, todayViews, totalViews, last7, pages, referrers, hourly, recentViews });
  } catch (e) {
    res.status(500).json({ ok: false, error: e.message });
  }
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
  const about = obsidian.parseAbout(obsidian.load('about'));
  pageView(req, res, 'pages/user/about', { title: about.enabled ? (about.seo_title || 'About') : 'About', active: 'about', page: cp, about, query: req.query, isAdmin: req.user && req.user.role === 'admin' });
});

/* ---------- OBSIDIAN · TERMS PAGE ---------- */

router.get('/terms', track('terms'), (req, res) => {
  const terms = obsidian.parseTerms(obsidian.load('terms'));
  if (!terms.enabled || !terms.sections.length) return res.status(404).render('pages/errors/404', { title: '404', user: req.user, settings: res.locals.settings, bg: resolveBackground(res.locals.settings) });
  pageView(req, res, 'pages/user/terms', { title: terms.title, active: '', terms });
});

/* ---------- OBSIDIAN · PAGE EDITORS (admin) ---------- */

function adminEditor(req, res, view, opts) {
  pageView(req, res, view, { active: 'admin-pages', ...opts });
}

router.get('/admin/pages', requireAdmin, (req, res) => {
  adminEditor(req, res, 'pages/admin/pageedit-hub', { title: 'Page Editors' });
});

router.get('/admin/pages/about', requireAdmin, (req, res) => {
  const d = { ...obsidian.defaultAbout(), ...(obsidian.load('about') || {}) };
  adminEditor(req, res, 'pages/admin/pageedit-about', { title: 'About Editor', d, query: req.query });
});

router.post('/admin/pages/about', requireAdmin, (req, res) => {
  const b = req.body;
  const clip = (v, n) => String(v || '').trim().slice(0, n);
  obsidian.save('about', {
    enabled: !!b.enabled,
    seo_title: clip(b.seo_title, 120),
    hero: {
      title: clip(b.hero_title, 150), subtitle: clip(b.hero_subtitle, 300), image_url: clip(b.hero_image_url, 500),
      cta1_label: clip(b.cta1_label, 60), cta1_url: clip(b.cta1_url, 400),
      cta2_label: clip(b.cta2_label, 60), cta2_url: clip(b.cta2_url, 400)
    },
    stats_enabled: !!b.stats_enabled, stats_title: '', stats_text: clip(b.stats_text, 3000),
    story_enabled: !!b.story_enabled, story_title: clip(b.story_title, 150), story_text: clip(b.story_text, 6000),
    values_enabled: !!b.values_enabled, values_title: clip(b.values_title, 150), values_text: clip(b.values_text, 4000),
    team_enabled: !!b.team_enabled, team_title: clip(b.team_title, 150), team_text: clip(b.team_text, 4000),
    timeline_enabled: !!b.timeline_enabled, timeline_title: clip(b.timeline_title, 150), timeline_text: clip(b.timeline_text, 4000),
    gallery_enabled: !!b.gallery_enabled, gallery_title: clip(b.gallery_title, 150), gallery_text: clip(b.gallery_text, 4000)
  });
  logActivity(req.user, 'Updated About page (editor)', req);
  res.redirect('/admin/pages/about?saved=1');
});

router.get('/admin/pages/terms', requireAdmin, (req, res) => {
  const d = { ...obsidian.defaultTerms(), ...(obsidian.load('terms') || {}) };
  adminEditor(req, res, 'pages/admin/pageedit-terms', { title: 'Terms Editor', d, query: req.query });
});

router.post('/admin/pages/terms', requireAdmin, (req, res) => {
  const b = req.body;
  const clip = (v, n) => String(v || '').trim().slice(0, n);
  obsidian.save('terms', {
    enabled: b.enabled === undefined ? true : !!b.enabled,
    title: clip(b.title, 150) || 'Terms & Conditions',
    summary: clip(b.summary, 400),
    last_updated: clip(b.last_updated, 20),
    sections_text: clip(b.sections_text, 60000)
  });
  logActivity(req.user, 'Updated Terms page (editor)', req);
  res.redirect('/admin/pages/terms?saved=1');
});

router.get('/admin/pages/footer', requireAdmin, (req, res) => {
  const d = { ...obsidian.defaultFooter(), ...(obsidian.load('footer') || {}) };
  adminEditor(req, res, 'pages/admin/pageedit-footer', { title: 'Footer Editor', d, query: req.query });
});

router.post('/admin/pages/footer', requireAdmin, (req, res) => {
  const b = req.body;
  const clip = (v, n) => String(v || '').trim().slice(0, n);
  obsidian.save('footer', {
    enabled: !!b.enabled,
    copyright: clip(b.copyright, 200),
    columns_text: clip(b.columns_text, 8000),
    legal_text: clip(b.legal_text, 2000)
  });
  logActivity(req.user, 'Updated Footer (editor)', req);
  res.redirect('/admin/pages/footer?saved=1');
});

router.get('/admin/pages/navbar', requireAdmin, (req, res) => {
  const d = { ...obsidian.defaultNavbar(), ...(obsidian.load('navbar') || {}) };
  adminEditor(req, res, 'pages/admin/pageedit-navbar', { title: 'Navbar Editor', d, query: req.query });
});

router.post('/admin/pages/navbar', requireAdmin, (req, res) => {
  const b = req.body;
  const clip = (v, n) => String(v || '').trim().slice(0, n);
  obsidian.save('navbar', {
    enabled: !!b.enabled,
    links_text: clip(b.links_text, 4000)
  });
  logActivity(req.user, 'Updated Navbar links (editor)', req);
  res.redirect('/admin/pages/navbar?saved=1');
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
    db.prepare(`INSERT INTO analytics (page, date, hits) VALUES (?, date('now'), 1) ON CONFLICT(page, date) DO UPDATE SET hits = hits + 1`).run('page:' + slug);
    const ip = req.headers['x-forwarded-for'] ? req.headers['x-forwarded-for'].split(',')[0].trim() : (req.ip || req.connection.remoteAddress || '').replace('::ffff:', '');
    const ua = (req.headers['user-agent'] || '').slice(0, 500);
    const ref = (req.headers['referer'] || req.headers['referrer'] || '').slice(0, 500);
    const userId = req.user ? req.user.id : null;
    if (!BOT_RE.test(ua)) {
      db.prepare(`INSERT INTO page_views (page, ip, user_agent, referrer, user_id, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))`).run('page:' + slug, ip, ua, ref, userId);
    }
  } catch (e) { console.error('[track:page]', slug, e.message); }
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
  const social = getProfileComments(req.user.id, req.user.id);
  const cfTokens = db.prepare('SELECT * FROM user_cf_tokens WHERE user_id = ? ORDER BY created_at DESC').all(req.user.id)
    .map((t) => ({ ...t, token: cf.maskToken(t.token) }));
  pageView(req, res, 'pages/user/profile', { title: 'My Profile', active: 'profile', query: req.query, comments: social.tree, commentsTotal: social.total, cfTokens });
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

/* ---------- SOCIAL · PROFILE COMMENTS (SocialBase port) ---------- */

const REACTION_TYPES = ['like', 'love', 'laugh'];

function safeBack(v) {
  return typeof v === 'string' && v.startsWith('/') && !v.startsWith('//') ? v : '/';
}

function timeAgo(s) {
  const t = new Date(String(s).replace(' ', 'T') + 'Z').getTime();
  if (isNaN(t)) return '';
  const sec = Math.max(1, Math.floor((Date.now() - t) / 1000));
  const steps = [[31536000, 'y'], [2592000, 'mo'], [604800, 'w'], [86400, 'd'], [3600, 'h'], [60, 'm']];
  for (const [n, l] of steps) if (sec >= n) return Math.floor(sec / n) + l + ' ago';
  return 'just now';
}

function getProfileComments(profileUserId, meId) {
  const rows = db.prepare(`
    SELECT c.*, u.username, u.profile_pic, u.role
    FROM profile_comments c JOIN users u ON u.id = c.author_id
    WHERE c.profile_user_id = ?
    ORDER BY c.created_at ASC, c.id ASC`).all(profileUserId);
  const rx = {};
  if (rows.length) {
    const ids = rows.map((r) => r.id);
    const q = ids.map(() => '?').join(',');
    const rrows = db.prepare(`
      SELECT comment_id, type, COUNT(*) n, SUM(user_id = ?) mine
      FROM comment_reactions WHERE comment_id IN (${q})
      GROUP BY comment_id, type`).all(meId || -1, ...ids);
    for (const r of rrows) (rx[r.comment_id] = rx[r.comment_id] || []).push({ type: r.type, count: r.n, mine: !!r.mine });
  }
  const map = {};
  for (const r of rows) map[r.id] = { ...r, time_ago: timeAgo(r.created_at), reactions: rx[r.id] || [], replies: [] };
  const roots = [];
  for (const r of rows) {
    const node = map[r.id];
    if (r.parent_id && map[r.parent_id]) map[r.parent_id].replies.push(node);
    else roots.push(node);
  }
  return { tree: roots.reverse(), total: rows.length };
}

function canDeleteComment(c, user) {
  return !!user && (user.role === 'admin' || user.id === c.author_id || user.id === c.profile_user_id);
}

router.post('/profile/:id/comment', requireAuth, (req, res) => {
  const back = safeBack(req.body.back);
  const target = db.prepare('SELECT id FROM users WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect(back);
  const content = String(req.body.content || '').trim();
  if (content.length < 3 || content.length > 1000) return res.redirect(back + (back.includes('?') ? '&' : '?') + 'social_error=len');
  let parent = null;
  if (req.body.parent_id) {
    const p = db.prepare('SELECT id, parent_id FROM profile_comments WHERE id = ? AND profile_user_id = ?').get(req.body.parent_id, target.id);
    if (p) parent = p.parent_id || p.id;
  }
  db.prepare('INSERT INTO profile_comments (profile_user_id, author_id, parent_id, content) VALUES (?, ?, ?, ?)')
    .run(target.id, req.user.id, parent, content);
  logActivity(req.user, `Commented on profile #${target.id}`, req);
  res.redirect(back);
});

router.post('/profile/comment/:id/edit', requireAuth, (req, res) => {
  const back = safeBack(req.body.back);
  const c = db.prepare('SELECT * FROM profile_comments WHERE id = ?').get(req.params.id);
  if (!c || c.author_id !== req.user.id) return res.redirect(back);
  const content = String(req.body.content || '').trim();
  if (content.length >= 3 && content.length <= 1000) {
    db.prepare("UPDATE profile_comments SET content = ?, is_edited = 1, updated_at = datetime('now') WHERE id = ?").run(content, c.id);
  }
  res.redirect(back);
});

router.post('/profile/comment/:id/delete', requireAuth, (req, res) => {
  const back = safeBack(req.body.back);
  const c = db.prepare('SELECT * FROM profile_comments WHERE id = ?').get(req.params.id);
  if (!c || !canDeleteComment(c, req.user)) return res.redirect(back);
  const ids = db.prepare('SELECT id FROM profile_comments WHERE id = ? OR parent_id = ?').all(c.id, c.id).map((r) => r.id);
  const q = ids.map(() => '?').join(',');
  db.prepare(`DELETE FROM comment_reactions WHERE comment_id IN (${q})`).run(...ids);
  db.prepare('DELETE FROM profile_comments WHERE id = ? OR parent_id = ?').run(c.id, c.id);
  res.redirect(back);
});

router.post('/profile/comment/:id/react', requireAuth, (req, res) => {
  const back = safeBack(req.body.back);
  const c = db.prepare('SELECT id FROM profile_comments WHERE id = ?').get(req.params.id);
  if (!c) return res.redirect(back);
  const type = REACTION_TYPES.includes(req.body.type) ? req.body.type : 'like';
  const existing = db.prepare('SELECT * FROM comment_reactions WHERE comment_id = ? AND user_id = ?').get(c.id, req.user.id);
  if (!existing) db.prepare('INSERT INTO comment_reactions (comment_id, user_id, type) VALUES (?, ?, ?)').run(c.id, req.user.id, type);
  else if (existing.type === type) db.prepare('DELETE FROM comment_reactions WHERE id = ?').run(existing.id);
  else db.prepare('UPDATE comment_reactions SET type = ? WHERE id = ?').run(type, existing.id);
  res.redirect(back);
});

/* ---------- TEAM ---------- */

router.post('/team/create', requireAdmin, (req, res) => {
  const { username, email, password, custom_role_id } = req.body;
  if (!username || !email || !password) return res.redirect('/team?error=1');
  if (!isValidUsername(username)) return res.redirect('/team?error=invalid_username');
  if (!isValidEmail(email)) return res.redirect('/team?error=invalid_email');
  if (password.length < 6) return res.redirect('/team?error=short_password');
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
  const social = getProfileComments(target.id, req.user ? req.user.id : null);
  pageView(req, res, 'pages/user/member-profile', { title: `Profile · ${target.username}`, active: 'team', member: target, isAdmin: req.user && req.user.role === 'admin', comments: social.tree, commentsTotal: social.total });
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
  const docs = db.prepare('SELECT COUNT(*) c FROM docs').get().c;
  const plans = db.prepare('SELECT COUNT(*) c FROM plans').get().c;
  const subscribers = db.prepare('SELECT COUNT(*) c FROM subscribers').get().c;
  const todayHits = db.prepare("SELECT COALESCE(SUM(hits),0) c FROM analytics WHERE date = date('now')").get().c;
  const totalHits = db.prepare('SELECT COALESCE(SUM(hits),0) c FROM analytics').get().c;
  const recent = db.prepare('SELECT * FROM activity ORDER BY created_at DESC LIMIT 8').all();
  const last7 = db.prepare("SELECT page, date, SUM(hits) AS hits FROM analytics WHERE date >= date('now','-6 day') GROUP BY page, date ORDER BY date").all();
  let sys;
  try { sys = getSystemStats(); } catch (e) { sys = null; }
  pageView(req, res, 'pages/admin/dashboard', {
    title: 'Admin Dashboard', active: 'admin',
    stats: { totalUsers, activeUsers, suspended, admins, tutorials, docs, todayHits, totalHits, plans, subscribers },
    sys, recent, last7
  });
});

router.get('/admin/create', requireAdmin, (req, res) => {
  pageView(req, res, 'pages/admin/create', { title: 'Create User', active: 'admin-create', error: null, success: null });
});

router.post('/admin/create', requireAdmin, (req, res) => {
  const { username, email, password, role } = req.body;
  if (!username || !email || !password) {
    return pageView(req, res, 'pages/admin/create', { title: 'Create User', active: 'admin-create', error: 'All fields are required', success: null });
  }
  if (!isValidUsername(username)) {
    return pageView(req, res, 'pages/admin/create', { title: 'Create User', active: 'admin-create', error: 'Username must be 2-32 chars (letters, numbers, _.-)', success: null });
  }
  if (!isValidEmail(email)) {
    return pageView(req, res, 'pages/admin/create', { title: 'Create User', active: 'admin-create', error: 'Please enter a valid email address (e.g. user@example.com)', success: null });
  }
  if (password.length < 6) {
    return pageView(req, res, 'pages/admin/create', { title: 'Create User', active: 'admin-create', error: 'Password must be at least 6 characters', success: null });
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
  upd.theme = ['dark','light','rainbow','neon','sunset','ocean','nature','candy','fire','galaxy','luxury','pastel'].includes(b.theme) ? b.theme : 'dark';
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
  upd.cookie_banner = b.cookie_banner ? 'on' : 'off';
  upd.anti_adblock = b.anti_adblock ? 'on' : 'off';
  upd.inject_body_code = String(b.inject_body_code || '').slice(0, 5000);

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
      if (k === 'theme') { const t = String(b[k]); setSetting(k, ['dark','light','rainbow','neon','sunset','ocean','nature','candy','fire','galaxy','luxury','pastel'].includes(t) ? t : 'dark'); }
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
  const cleanTitle = (title || '').trim();
  if (!cleanTitle || !slug) return res.redirect('/admin/settings/content?error=create');
  const cleanSlug = String(slug).toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/-{2,}/g, '-').replace(/^-|-$/g, '');
  if (!cleanSlug || cleanSlug.length > 60) return res.redirect('/admin/settings/content?error=create');
  const exists = db.prepare('SELECT id FROM content_pages WHERE slug = ?').get(cleanSlug);
  if (exists) return res.redirect('/admin/settings/content?error=exists');
  db.prepare('INSERT INTO content_pages (slug, title, content, icon, in_nav, updated_at, updated_by) VALUES (?, ?, ?, ?, ?, datetime(\'now\'), ?)')
    .run(cleanSlug, cleanTitle, content || '', (icon || '').trim().slice(0, 8) || '📄', in_nav ? 1 : 0, req.user.id);
  logActivity(req.user, `Created custom page "${cleanSlug}"`, req);
  res.redirect('/admin/settings/content?edit=' + cleanSlug + '&created=1');
});

router.post('/admin/settings/content/:slug', requireAdmin, (req, res) => {
  const page = db.prepare('SELECT * FROM content_pages WHERE slug = ?').get(req.params.slug);
  if (!page) return res.redirect('/admin/settings/content');
  const { title, content, icon, in_nav } = req.body;
  const cleanTitle = (title || '').trim() || page.title;
  db.prepare("UPDATE content_pages SET title = ?, content = ?, icon = ?, in_nav = ?, updated_at = datetime('now'), updated_by = ? WHERE slug = ?")
    .run(cleanTitle, content || '', (icon || '').trim().slice(0, 8) || page.icon || '📄', in_nav ? 1 : 0, req.user.id, req.params.slug);
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

/* ---------- CLOUDFLARE ---------- */

router.get('/admin/cloudflare', requireAdmin, async (req, res) => {
  const cfg = cf.getConfig();
  const data = { conn: null, connError: null, zone: null, analytics: [], analyticsError: null, zt: null, ztError: null };
  let zonesCount = 0;
  if (cfg.apiToken) {
    try { data.conn = await cf.verifyToken(); } catch (e) { data.connError = e.message; }
    try { zonesCount = (await cf.listZones()).length; } catch (e) { /* optional */ }
    try { data.zone = await cf.zoneInfo(); } catch (e) { /* zone optional */ }
  }
  if (cfg.apiToken && cfg.zoneId) {
    try { data.analytics = await cf.webAnalytics(); } catch (e) { data.analyticsError = e.message; }
  }
  const userTokens = db.prepare('SELECT t.*, u.username FROM user_cf_tokens t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC').all()
    .map((t) => ({ ...t, token: cf.maskToken(t.token) }));
  pageView(req, res, 'pages/admin/cloudflare', { title: 'Cloudflare', active: 'admin-cloudflare', cfg, data, userTokens, zonesCount, query: req.query });
});

router.post('/admin/cloudflare/settings', requireAdmin, (req, res) => {
  const b = req.body;
  const clip = (v, n) => String(v || '').trim().slice(0, n);
  cf.saveConfig({
    email: clip(b.email, 200),
    authMode: b.authMode === 'global' ? 'global' : 'token',
    apiToken: clip(b.apiToken, 200),
    accountId: clip(b.accountId, 64),
    zoneId: clip(b.zoneId, 64),
    analytics_enabled: !!b.analytics_enabled,
    analyticsToken: clip(b.analyticsToken, 100),
    zerotrust_enabled: !!b.zerotrust_enabled,
    ztTeam: clip(b.ztTeam, 200)
  });
  logActivity(req.user, 'Updated Cloudflare settings', req);
  res.redirect('/admin/cloudflare?saved=1');
});

router.post('/admin/cloudflare/test', requireAdmin, async (req, res) => {
  try {
    const result = await cf.verifyToken();
    res.json({ ok: true, status: result.status });
  } catch (e) {
    res.json({ ok: false, error: e.message });
  }
});

router.post('/admin/cloudflare/detect', requireAdmin, async (req, res) => {
  try {
    const cfg = cf.getConfig();
    if (!cfg.apiToken) return res.json({ ok: false, error: 'Save an API token first' });
    const zones = await cf.listZones();
    if (!zones.length) return res.json({ ok: false, error: 'No zones visible to this token' });
    const z = zones[0];
    cf.saveConfig({
      accountId: z.account_id || '',
      zoneId: z.id,
      email: cfg.email,
      analyticsToken: cfg.analyticsToken,
      ztTeam: cfg.ztTeam,
      analytics_enabled: true,
      zerotrust_enabled: cfg.ztEnabled
    });
    logActivity(req.user, `Auto-detected Cloudflare zone ${z.name}`, req);
    res.json({ ok: true, zone: z.name, zone_status: z.status, account_name: z.account_name || '', zones: zones.map((x) => x.name) });
  } catch (e) {
    res.json({ ok: false, error: e.message });
  }
});

/* ---------- CLOUDFLARE MANAGEMENT · sub-pages ---------- */

function cfZoneSwitcher(req) {
  return { zones: [], current: cf.getConfig().zoneId };
}

router.post('/admin/cloudflare/switch-zone', requireAdmin, (req, res) => {
  const zid = String(req.body.zoneId || '').trim().slice(0, 64);
  const cfg = cf.getConfig();
  cf.saveConfig({ email: cfg.email, authMode: cfg.authMode, accountId: cfg.accountId, zoneId: zid, analyticsToken: cfg.analyticsToken, ztTeam: cfg.ztTeam, analytics_enabled: true, zerotrust_enabled: cfg.ztEnabled });
  res.redirect(req.get('referer') || '/admin/cloudflare/domains');
});

/* Zero Trust */
router.get('/admin/cloudflare/zerotrust', requireAdmin, async (req, res) => {
  const tab = req.query.tab === 'analytics' ? 'analytics' : 'management';
  let apps = [], users = [], devices = [], summary = null, error = null;
  try {
    if (tab === 'management') {
      [apps, users, devices] = await Promise.all([cf.ztApps(), cf.ztUsers(), cf.ztDevices()]);
    } else {
      summary = await cf.zeroTrust();
    }
  } catch (e) { error = e.message; }
  pageView(req, res, 'pages/admin/cf-zerotrust', { title: 'Zero Trust', active: 'admin-cloudflare', tab, apps, users, devices, summary, error, query: req.query });
});

router.post('/admin/cloudflare/zerotrust/app/:id/delete', requireAdmin, async (req, res) => {
  try { await cf.ztAppDelete(req.params.id); } catch (e) { return res.redirect('/admin/cloudflare/zerotrust?error=' + encodeURIComponent(e.message)); }
  logActivity(req.user, 'Deleted a Cloudflare Access app', req);
  res.redirect('/admin/cloudflare/zerotrust?deleted=1');
});

/* Domains */
router.get('/admin/cloudflare/domains', requireAdmin, async (req, res) => {
  const tab = req.query.tab === 'analytics' ? 'analytics' : 'management';
  let zones = [], stats = [], error = null;
  try {
    if (tab === 'management') zones = await cf.listZones();
    else stats = await cf.domainStats();
  } catch (e) { error = e.message; }
  pageView(req, res, 'pages/admin/cf-domains', { title: 'Domains', active: 'admin-cloudflare', tab, zones, stats, error, query: req.query });
});

/* DNS */
router.get('/admin/cloudflare/dns', requireAdmin, async (req, res) => {
  const tab = req.query.tab === 'analytics' ? 'analytics' : 'management';
  let records = [], analytics = [], error = null;
  try {
    if (tab === 'management') records = await cf.dnsList();
    else analytics = await cf.webAnalytics();
  } catch (e) { error = e.message; }
  let zonesList = []; try { zonesList = await cf.listZones(); } catch (e) {}
  pageView(req, res, 'pages/admin/cf-dns', { title: 'DNS Records', active: 'admin-cloudflare', tab, records, analytics, error, query: req.query, zonesList, currentZoneId: cf.getConfig().zoneId });
});

router.post('/admin/cloudflare/dns', requireAdmin, async (req, res) => {
  const b = req.body;
  try {
    await cf.dnsCreate({ type: b.type, name: b.name, content: b.content, ttl: b.ttl, proxied: b.proxied });
    logActivity(req.user, `Created DNS ${b.type} record for ${b.name}`, req);
  } catch (e) { return res.redirect('/admin/cloudflare/dns?error=' + encodeURIComponent(e.message)); }
  res.redirect('/admin/cloudflare/dns?created=1');
});

router.post('/admin/cloudflare/dns/:id/edit', requireAdmin, async (req, res) => {
  const b = req.body;
  try {
    await cf.dnsUpdate(req.params.id, { type: b.type, name: b.name, content: b.content, ttl: b.ttl, proxied: b.proxied });
    logActivity(req.user, `Updated DNS record ${b.name}`, req);
  } catch (e) { return res.redirect('/admin/cloudflare/dns?error=' + encodeURIComponent(e.message)); }
  res.redirect('/admin/cloudflare/dns?saved=1');
});

router.post('/admin/cloudflare/dns/:id/delete', requireAdmin, async (req, res) => {
  try { await cf.dnsDelete(req.params.id); logActivity(req.user, 'Deleted a DNS record', req); }
  catch (e) { return res.redirect('/admin/cloudflare/dns?error=' + encodeURIComponent(e.message)); }
  res.redirect('/admin/cloudflare/dns?deleted=1');
});

/* DDoS */
router.get('/admin/cloudflare/ddos', requireAdmin, async (req, res) => {
  const tab = req.query.tab === 'management' ? 'management' : 'analytics';
  let analytics = [], settingsMap = {}, error = null;
  try {
    if (tab === 'analytics') analytics = await cf.webAnalytics();
    else settingsMap = await cf.zoneSettings();
  } catch (e) { error = e.message; }
  let zonesList = []; try { zonesList = await cf.listZones(); } catch (e) {}
  pageView(req, res, 'pages/admin/cf-ddos', { title: 'DDoS Protection', active: 'admin-cloudflare', tab, analytics, settingsMap, error, query: req.query, zonesList, currentZoneId: cf.getConfig().zoneId });
});

router.post('/admin/cloudflare/ddos/level', requireAdmin, async (req, res) => {
  try { await cf.zoneSetSetting('security_level', req.body.level || 'medium'); logActivity(req.user, `Set Cloudflare security level to ${req.body.level}`, req); }
  catch (e) { return res.redirect('/admin/cloudflare/ddos?tab=management&error=' + encodeURIComponent(e.message)); }
  res.redirect('/admin/cloudflare/ddos?tab=management&saved=1');
});

/* Security */
router.get('/admin/cloudflare/security', requireAdmin, async (req, res) => {
  const tab = req.query.tab === 'analytics' ? 'analytics' : 'management';
  let rules = [], arules = [], events = [], settingsMap = {}, error = null;
  try {
    if (tab === 'management') [rules, arules] = await Promise.all([cf.fwRules(), cf.accessRules()]);
    else {
      [events, settingsMap] = await Promise.all([cf.securityEvents(), cf.zoneSettings()]);
    }
  } catch (e) { error = e.message; }
  let zonesList = []; try { zonesList = await cf.listZones(); } catch (e) {}
  pageView(req, res, 'pages/admin/cf-security', { title: 'Security', active: 'admin-cloudflare', tab, rules, arules, events, settingsMap, error, query: req.query, zonesList, currentZoneId: cf.getConfig().zoneId });
});

router.post('/admin/cloudflare/security/access', requireAdmin, async (req, res) => {
  try {
    await cf.accessRuleCreate({ mode: req.body.mode, value: req.body.value, notes: req.body.notes });
    logActivity(req.user, `Added Cloudflare IP access rule (${req.body.mode})`, req);
  } catch (e) { return res.redirect('/admin/cloudflare/security?error=' + encodeURIComponent(e.message)); }
  res.redirect('/admin/cloudflare/security?created=1');
});

router.post('/admin/cloudflare/security/access/:id/delete', requireAdmin, async (req, res) => {
  try { await cf.accessRuleDelete(req.params.id); logActivity(req.user, 'Removed a Cloudflare IP access rule', req); }
  catch (e) { return res.redirect('/admin/cloudflare/security?error=' + encodeURIComponent(e.message)); }
  res.redirect('/admin/cloudflare/security?deleted=1');
});

router.post('/admin/cloudflare/security/rule/:id/toggle', requireAdmin, async (req, res) => {
  try { await cf.fwRuleToggle(req.params.id, req.body.paused === '1'); }
  catch (e) { return res.redirect('/admin/cloudflare/security?error=' + encodeURIComponent(e.message)); }
  res.redirect('/admin/cloudflare/security');
});

router.post('/admin/cloudflare/security/rule/:id/delete', requireAdmin, async (req, res) => {
  try { await cf.fwRuleDelete(req.params.id); logActivity(req.user, 'Deleted a Cloudflare firewall rule', req); }
  catch (e) { return res.redirect('/admin/cloudflare/security?error=' + encodeURIComponent(e.message)); }
  res.redirect('/admin/cloudflare/security?deleted=1');
});

/* Settings */
router.get('/admin/cloudflare/settings-page', requireAdmin, async (req, res) => {
  const cfg = cf.getConfig();
  let settingsMap = {}, error = null, zone = null;
  try { settingsMap = await cf.zoneSettings(); } catch (e) { error = e.message; }
  try { zone = await cf.zoneInfo(); } catch (e) { /* optional */ }
  const userTokens = db.prepare('SELECT t.*, u.username FROM user_cf_tokens t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC').all()
    .map((t) => ({ ...t, token: cf.maskToken(t.token) }));
  const maskedApiToken = cfg.apiToken ? cf.maskToken(cfg.apiToken) : '';
  pageView(req, res, 'pages/admin/cf-settings', { title: 'CF Settings', active: 'admin-cloudflare', cfg, settingsMap, error, userTokens, maskedApiToken, data: { zone }, query: req.query });
});

router.post('/admin/cloudflare/settings-page/update', requireAdmin, async (req, res) => {
  const allowed = ['security_level', 'ssl', 'cache_level', 'always_online', 'browser_check', 'development_mode', 'automatic_https_rewrites', 'brotli', 'early_hints', 'http2', 'http3', '0rtt', 'ipv6', 'websockets', 'min_tls_version'];
  const updates = [];
  Object.keys(req.body).forEach((k) => { if (allowed.includes(k)) updates.push([k, req.body[k]]); });
  const errors = [];
  for (const [k, v] of updates) {
    try { await cf.zoneSetSetting(k, v); } catch (e) { errors.push(k + ': ' + e.message); }
  }
  if (updates.length) logActivity(req.user, `Updated Cloudflare zone settings (${updates.map((u) => u[0]).join(', ')})`, req);
  if (errors.length) return res.redirect('/admin/cloudflare/settings-page?error=' + encodeURIComponent(errors[0]));
  res.redirect('/admin/cloudflare/settings-page?saved=1');
});

/* Accounts */
router.get('/admin/cloudflare/accounts', requireAdmin, async (req, res) => {
  const tab = req.query.tab === 'analytics' ? 'analytics' : 'management';
  let accs = [], members = [], stats = [], error = null;
  try {
    accs = await cf.accounts();
    if (tab === 'management') members = await cf.accountMembers(cf.getConfig().accountId || (accs[0] && accs[0].id));
    else stats = await cf.domainStats(20);
  } catch (e) { error = e.message; }
  pageView(req, res, 'pages/admin/cf-accounts', { title: 'Accounts', active: 'admin-cloudflare', tab, accs, members, stats, error, currentAccountId: cf.getConfig().accountId, query: req.query });
});

/* ---------- USER CF TOKENS (any logged-in user) ---------- */

router.post('/profile/cf-token', requireAuth, (req, res) => {
  const token = String(req.body.token || '').trim().slice(0, 200);
  const label = String(req.body.label || '').trim().slice(0, 60);
  if (!token) return res.redirect('/profile?cferror=1');
  const count = db.prepare('SELECT COUNT(*) c FROM user_cf_tokens WHERE user_id = ?').get(req.user.id).c;
  if (count >= 5) return res.redirect('/profile?cflimit=1');
  db.prepare('INSERT INTO user_cf_tokens (user_id, label, token) VALUES (?, ?, ?)').run(req.user.id, label, token);
  logActivity(req.user, 'Added a Cloudflare API token', req);
  res.redirect('/profile?cfsaved=1#cloudflare');
});

router.post('/profile/cf-token/:id/delete', requireAuth, (req, res) => {
  const row = db.prepare('SELECT * FROM user_cf_tokens WHERE id = ?').get(req.params.id);
  if (!row || (row.user_id !== req.user.id && req.user.role !== 'admin')) return res.redirect('/profile?cferror=1');
  db.prepare('DELETE FROM user_cf_tokens WHERE id = ?').run(row.id);
  logActivity(req.user, 'Removed a Cloudflare API token', req);
  res.redirect('/profile?cfdeleted=1#cloudflare');
});

/* ---------- BLOG MANAGEMENT ---------- */

router.get('/admin/blog', requireAdmin, (req, res) => {
  const posts = db.prepare('SELECT * FROM blog_posts ORDER BY created_at DESC, id DESC').all()
    .map((p) => ({ ...p, tag_list: blog.parseTags(p), excerpt: blog.excerpt(p, 120), fmt: blog.fmtDate }));
  const fb = db.prepare('SELECT SUM(is_helpful = 1) helpful, SUM(is_helpful = 0) unhelpful FROM blog_post_feedback').get();
  pageView(req, res, 'pages/admin/blog', { title: 'Blog Manager', active: 'admin-blog', posts, query: req.query, stats: { total: posts.length, published: posts.filter((p) => p.is_published).length, views: posts.reduce((a, p) => a + p.views, 0), helpful: fb.helpful || 0 } });
});

router.get('/admin/blog/create', requireAdmin, (req, res) => {
  pageView(req, res, 'pages/admin/blog-form', { title: 'New Post', active: 'admin-blog', post: null, query: req.query });
});

function blogFields(b) {
  return {
    title: String(b.title || '').trim(),
    slug: String(b.slug || '').trim(),
    description: String(b.description || '').trim().slice(0, 255),
    cover_image_url: String(b.cover_image_url || '').trim(),
    seo_title: String(b.seo_title || '').trim().slice(0, 255),
    seo_description: String(b.seo_description || '').trim().slice(0, 320),
    seo_keywords: String(b.seo_keywords || '').trim().slice(0, 500),
    content: String(b.content || ''),
    tags: String(b.tags || '').split(',').map((t) => t.trim()).filter(Boolean).join(', '),
    is_published: b.is_published ? 1 : 0
  };
}

router.post('/admin/blog/create', requireAdmin, (req, res) => {
  const f = blogFields(req.body);
  if (!f.title || !f.content) return res.redirect('/admin/blog/create?error=fields');
  const slug = f.slug ? blog.uniqueSlug(f.slug) : blog.uniqueSlug(f.title);
  db.prepare(`INSERT INTO blog_posts (title, slug, description, cover_image_url, seo_title, seo_description, seo_keywords, content, tags, is_published, published_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`)
    .run(f.title, slug, f.description, f.cover_image_url, f.seo_title, f.seo_description, f.seo_keywords, f.content, f.tags, f.is_published, f.is_published ? new Date().toISOString().slice(0, 19).replace('T', ' ') : null);
  logActivity(req.user, `Created blog post "${f.title}"`, req);
  res.redirect('/admin/blog?created=1');
});

router.get('/admin/blog/:id/edit', requireAdmin, (req, res) => {
  const post = db.prepare('SELECT * FROM blog_posts WHERE id = ?').get(req.params.id);
  if (!post) return res.redirect('/admin/blog');
  pageView(req, res, 'pages/admin/blog-form', { title: `Edit · ${post.title}`, active: 'admin-blog', post, query: req.query });
});

router.post('/admin/blog/:id/edit', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM blog_posts WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/admin/blog');
  const f = blogFields(req.body);
  if (!f.title || !f.content) return res.redirect(`/admin/blog/${target.id}/edit?error=fields`);
  const slug = f.slug ? blog.uniqueSlug(f.slug, target.id) : blog.uniqueSlug(f.title, target.id);
  const publishedAt = f.is_published
    ? (target.published_at || new Date().toISOString().slice(0, 19).replace('T', ' '))
    : null;
  db.prepare(`UPDATE blog_posts SET title = ?, slug = ?, description = ?, cover_image_url = ?, seo_title = ?, seo_description = ?, seo_keywords = ?, content = ?, tags = ?, is_published = ?, published_at = ?, updated_at = datetime('now') WHERE id = ?`)
    .run(f.title, slug, f.description, f.cover_image_url, f.seo_title, f.seo_description, f.seo_keywords, f.content, f.tags, f.is_published, publishedAt, target.id);
  logActivity(req.user, `Edited blog post "${f.title}"`, req);
  res.redirect('/admin/blog?saved=1');
});

router.post('/admin/blog/:id/delete', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM blog_posts WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/admin/blog');
  db.prepare('DELETE FROM blog_post_feedback WHERE post_id = ?').run(target.id);
  db.prepare('DELETE FROM blog_posts WHERE id = ?').run(target.id);
  logActivity(req.user, `Deleted blog post "${target.title}"`, req);
  res.redirect('/admin/blog?deleted=1');
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

/* ---------- PLANS MANAGEMENT ---------- */

router.get('/plans', requireAdmin, track('plans'), (req, res) => {
  const totalPlans = db.prepare('SELECT COUNT(*) c FROM plans WHERE deleted = 0').get().c;
  const activePlans = db.prepare("SELECT COUNT(*) c FROM plans WHERE deleted = 0 AND status = 'active'").get().c;
  const totalSubscribers = db.prepare('SELECT COUNT(*) c FROM subscribers').get().c;
  const activeSubscribers = db.prepare("SELECT COUNT(*) c FROM subscribers WHERE status = 'active'").get().c;
  const expiredSubscriptions = db.prepare("SELECT COUNT(*) c FROM subscribers WHERE status = 'expired'").get().c;
  const totalCoupons = db.prepare('SELECT COUNT(*) c FROM coupons').get().c;
  const activeCoupons = db.prepare("SELECT COUNT(*) c FROM coupons WHERE status = 'active'").get().c;
  const monthlyRevenue = db.prepare("SELECT COALESCE(SUM(total),0) c FROM transactions WHERE status = 'completed' AND date(created_at) >= date('now','start of month')").get().c;
  const totalRevenue = db.prepare("SELECT COALESCE(SUM(total),0) c FROM transactions WHERE status = 'completed'").get().c;
  const recentTransactions = db.prepare('SELECT * FROM transactions ORDER BY created_at DESC LIMIT 5').all();
  pageView(req, res, 'pages/plans/dashboard', {
    title: 'Plans Dashboard', active: 'plans',
    stats: { totalPlans, activePlans, totalSubscribers, activeSubscribers, expiredSubscriptions, totalCoupons, activeCoupons, monthlyRevenue, totalRevenue },
    recentTransactions
  });
});

router.get('/plans/all', requireAdmin, track('plans'), (req, res) => {
  const q = (req.query.q || '').trim();
  const catFilter = (req.query.category || '').trim();
  const page = Math.max(1, parseInt(req.query.page, 10) || 1);
  const limit = 15;
  const offset = (page - 1) * limit;
  let where = 'WHERE p.deleted = 0';
  const params = [];
  if (q) { where += ' AND (p.name LIKE ? OR p.description LIKE ?)'; params.push('%' + q + '%', '%' + q + '%'); }
  if (catFilter) { where += ' AND pc.name = ?'; params.push(catFilter); }
  const total = db.prepare('SELECT COUNT(*) c FROM plans p LEFT JOIN plan_categories pc ON pc.id = p.category_id ' + where).get(...params).c;
  const totalPages = Math.max(1, Math.ceil(total / limit));
  const plans = db.prepare(`SELECT p.*, pc.name AS category_name, (SELECT COUNT(*) FROM subscribers s WHERE s.plan_id = p.id AND s.status = 'active') AS sub_count FROM plans p LEFT JOIN plan_categories pc ON pc.id = p.category_id ${where} ORDER BY p.sort_order, p.created_at DESC LIMIT ? OFFSET ?`).all(...params, limit, offset);
  pageView(req, res, 'pages/plans/all', {
    title: 'All Plans', active: 'plans', plans, q, catFilter, page, totalPages, total, query: req.query
  });
});

router.get('/plans/add', requireAdmin, (req, res) => {
  const categories = db.prepare('SELECT * FROM plan_categories ORDER BY sort_order, id').all();
  const editing = null;
  pageView(req, res, 'pages/plans/add', { title: 'Add Plan', active: 'plans', categories, editing, query: req.query });
});

router.post('/plans/add', requireAdmin, (req, res) => {
  const { name, category_id, description, price, billing, features, storage_limit, user_limit, api_limit, trial_days, status, badge, badge_color, popular } = req.body;
  if (!name) return res.redirect('/plans/add?error=Name is required');
  db.prepare('INSERT INTO plans (name, category_id, description, price, billing, features, storage_limit, user_limit, api_limit, trial_days, status, badge, badge_color, popular) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    .run(name, category_id || null, description || '', parseFloat(price) || 0, billing || 'monthly', features || '', storage_limit || '', parseInt(user_limit) || 0, api_limit || '', parseInt(trial_days) || 0, status === 'inactive' ? 'inactive' : 'active', badge || '', badge_color || '#3b82f6', popular ? 1 : 0);
  logActivity(req.user, `Created plan "${name}"`, req);
  res.redirect('/plans/all?saved=1');
});

router.get('/plans/edit/:id', requireAdmin, (req, res) => {
  const editing = db.prepare('SELECT * FROM plans WHERE id = ?').get(req.params.id);
  if (!editing) return res.redirect('/plans/all');
  const categories = db.prepare('SELECT * FROM plan_categories ORDER BY sort_order, id').all();
  pageView(req, res, 'pages/plans/add', { title: 'Edit Plan', active: 'plans', categories, editing, query: req.query });
});

router.post('/plans/:id/edit', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM plans WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/plans/all');
  const { name, category_id, description, price, billing, features, storage_limit, user_limit, api_limit, trial_days, status, badge, badge_color, popular } = req.body;
  db.prepare("UPDATE plans SET name=?, category_id=?, description=?, price=?, billing=?, features=?, storage_limit=?, user_limit=?, api_limit=?, trial_days=?, status=?, badge=?, badge_color=?, popular=?, updated_at=datetime('now') WHERE id=?")
    .run(name || target.name, category_id || null, description != null ? description : target.description, parseFloat(price) || target.price, billing || target.billing, features != null ? features : target.features, storage_limit != null ? storage_limit : target.storage_limit, parseInt(user_limit) || target.user_limit, api_limit != null ? api_limit : target.api_limit, parseInt(trial_days) || target.trial_days, status === 'inactive' ? 'inactive' : 'active', badge != null ? badge : target.badge, badge_color || target.badge_color, popular ? 1 : 0, target.id);
  logActivity(req.user, `Edited plan "${target.name}"`, req);
  res.redirect('/plans/all?saved=1');
});

router.post('/plans/:id/delete', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM plans WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/plans/all');
  db.prepare("UPDATE plans SET deleted = 1, updated_at = datetime('now') WHERE id = ?").run(target.id);
  logActivity(req.user, `Deleted plan "${target.name}"`, req);
  res.redirect('/plans/all?deleted=1');
});

router.post('/plans/:id/restore', requireAdmin, (req, res) => {
  db.prepare("UPDATE plans SET deleted = 0, updated_at = datetime('now') WHERE id = ?").run(req.params.id);
  logActivity(req.user, 'Restored a plan', req);
  res.redirect('/plans/trash?restored=1');
});

router.post('/plans/:id/permanent-delete', requireAdmin, (req, res) => {
  db.prepare('DELETE FROM plans WHERE id = ?').run(req.params.id);
  logActivity(req.user, 'Permanently deleted a plan', req);
  res.redirect('/plans/trash?permanently=1');
});

router.get('/plans/trash', requireAdmin, (req, res) => {
  const plans = db.prepare('SELECT * FROM plans WHERE deleted = 1 ORDER BY updated_at DESC').all();
  pageView(req, res, 'pages/plans/trash', { title: 'Plans Trash', active: 'plans', plans, query: req.query });
});

/* ---------- PLAN CATEGORIES ---------- */

router.get('/plans/categories', requireAdmin, (req, res) => {
  const categories = db.prepare('SELECT pc.*, (SELECT COUNT(*) FROM plans p WHERE p.category_id = pc.id AND p.deleted = 0) AS plan_count FROM plan_categories pc ORDER BY pc.sort_order, pc.id').all();
  const editing = req.query.edit ? db.prepare('SELECT * FROM plan_categories WHERE id = ?').get(req.query.edit) : null;
  pageView(req, res, 'pages/plans/categories', { title: 'Plan Categories', active: 'plans', categories, editing, query: req.query });
});

router.post('/plans/categories/add', requireAdmin, (req, res) => {
  const { name, description, sort_order } = req.body;
  if (!name) return res.redirect('/plans/categories?error=Name is required');
  db.prepare('INSERT INTO plan_categories (name, description, sort_order) VALUES (?, ?, ?)').run(name.trim(), description || '', parseInt(sort_order) || 0);
  logActivity(req.user, `Created plan category "${name}"`, req);
  res.redirect('/plans/categories?saved=1');
});

router.post('/plans/categories/:id/edit', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM plan_categories WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/plans/categories');
  const { name, description, sort_order } = req.body;
  db.prepare('UPDATE plan_categories SET name = ?, description = ?, sort_order = ? WHERE id = ?')
    .run((name || target.name).trim(), description != null ? description : target.description, parseInt(sort_order) || target.sort_order, target.id);
  logActivity(req.user, `Edited plan category "${target.name}"`, req);
  res.redirect('/plans/categories?saved=1');
});

router.post('/plans/categories/:id/delete', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM plan_categories WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/plans/categories');
  db.prepare('UPDATE plans SET category_id = NULL WHERE category_id = ?').run(target.id);
  db.prepare('DELETE FROM plan_categories WHERE id = ?').run(target.id);
  logActivity(req.user, `Deleted plan category "${target.name}"`, req);
  res.redirect('/plans/categories?deleted=1');
});

/* ---------- SUBSCRIBERS ---------- */

router.get('/plans/subscribers', requireAdmin, (req, res) => {
  const q = (req.query.q || '').trim();
  const page = Math.max(1, parseInt(req.query.page, 10) || 1);
  const limit = 15;
  const offset = (page - 1) * limit;
  let where = 'WHERE 1=1';
  const params = [];
  if (q) { where += ' AND (s.username LIKE ? OR s.plan_name LIKE ?)'; params.push('%' + q + '%', '%' + q + '%'); }
  const total = db.prepare('SELECT COUNT(*) c FROM subscribers s ' + where).get(...params).c;
  const totalPages = Math.max(1, Math.ceil(total / limit));
  const subscribers = db.prepare('SELECT s.* FROM subscribers s ' + where + ' ORDER BY s.created_at DESC LIMIT ? OFFSET ?').all(...params, limit, offset);
  pageView(req, res, 'pages/plans/subscribers', { title: 'Subscribers', active: 'plans', subscribers, q, page, totalPages, total, query: req.query });
});

router.post('/plans/subscribers/:id/status', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM subscribers WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/plans/subscribers');
  db.prepare('UPDATE subscribers SET status = ? WHERE id = ?').run(req.body.status || 'active', target.id);
  logActivity(req.user, `Updated subscriber ${target.username} status to ${req.body.status}`, req);
  res.redirect('/plans/subscribers?saved=1');
});

router.post('/plans/subscribers/:id/delete', requireAdmin, (req, res) => {
  db.prepare('DELETE FROM subscribers WHERE id = ?').run(req.params.id);
  logActivity(req.user, 'Removed a subscriber', req);
  res.redirect('/plans/subscribers?deleted=1');
});

/* ---------- TRANSACTIONS ---------- */

router.get('/plans/transactions', requireAdmin, (req, res) => {
  const q = (req.query.q || '').trim();
  const page = Math.max(1, parseInt(req.query.page, 10) || 1);
  const limit = 20;
  const offset = (page - 1) * limit;
  let where = 'WHERE 1=1';
  const params = [];
  if (q) { where += ' AND (t.username LIKE ? OR t.plan_name LIKE ?)'; params.push('%' + q + '%', '%' + q + '%'); }
  const total = db.prepare('SELECT COUNT(*) c FROM transactions t ' + where).get(...params).c;
  const totalPages = Math.max(1, Math.ceil(total / limit));
  const transactions = db.prepare('SELECT t.* FROM transactions t ' + where + ' ORDER BY t.created_at DESC LIMIT ? OFFSET ?').all(...params, limit, offset);
  pageView(req, res, 'pages/plans/transactions', { title: 'Transactions', active: 'plans', transactions, q, page, totalPages, total, query: req.query });
});

/* ---------- COUPONS ---------- */

router.get('/plans/coupons', requireAdmin, (req, res) => {
  const coupons = db.prepare('SELECT * FROM coupons ORDER BY created_at DESC').all();
  const editing = req.query.edit ? db.prepare('SELECT * FROM coupons WHERE id = ?').get(req.query.edit) : null;
  pageView(req, res, 'pages/plans/coupons', { title: 'Coupons', active: 'plans', coupons, editing, query: req.query });
});

router.post('/plans/coupons/add', requireAdmin, (req, res) => {
  const { code, type, value, max_uses, min_amount, expiry_date, status } = req.body;
  if (!code) return res.redirect('/plans/coupons?error=Code is required');
  const exists = db.prepare('SELECT id FROM coupons WHERE code = ?').get(code.toUpperCase());
  if (exists) return res.redirect('/plans/coupons?error=Coupon code already exists');
  db.prepare('INSERT INTO coupons (code, type, value, max_uses, min_amount, expiry_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
    .run(code.toUpperCase().trim(), type || 'percent', parseFloat(value) || 0, parseInt(max_uses) || 0, parseFloat(min_amount) || 0, expiry_date || '', status === 'inactive' ? 'inactive' : 'active');
  logActivity(req.user, `Created coupon "${code}"`, req);
  res.redirect('/plans/coupons?saved=1');
});

router.post('/plans/coupons/:id/edit', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM coupons WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/plans/coupons');
  const { code, type, value, max_uses, min_amount, expiry_date, status } = req.body;
  db.prepare('UPDATE coupons SET code=?, type=?, value=?, max_uses=?, min_amount=?, expiry_date=?, status=? WHERE id=?')
    .run((code || target.code).toUpperCase().trim(), type || target.type, parseFloat(value) || target.value, parseInt(max_uses) != null ? parseInt(max_uses) : target.max_uses, parseFloat(min_amount) || target.min_amount, expiry_date != null ? expiry_date : target.expiry_date, status === 'inactive' ? 'inactive' : 'active', target.id);
  logActivity(req.user, `Edited coupon "${target.code}"`, req);
  res.redirect('/plans/coupons?saved=1');
});

router.post('/plans/coupons/:id/delete', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM coupons WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/plans/coupons');
  db.prepare('DELETE FROM coupons WHERE id = ?').run(target.id);
  logActivity(req.user, `Deleted coupon "${target.code}"`, req);
  res.redirect('/plans/coupons?deleted=1');
});

/* ---------- PLAN SETTINGS ---------- */

router.get('/plans/settings', requireAdmin, (req, res) => {
  const plans = db.prepare('SELECT * FROM plans WHERE deleted = 0 ORDER BY name').all();
  pageView(req, res, 'pages/plans/settings', { title: 'Plan Settings', active: 'plans', plans, query: req.query });
});

router.post('/plans/settings', requireAdmin, (req, res) => {
  const b = req.body;
  const upd = {};
  const keys = ['plan_currency', 'plan_currency_code', 'plan_tax_rate', 'plan_tax_name', 'plan_trial_days', 'plan_default_id', 'plan_stripe_pk', 'plan_stripe_sk', 'plan_razorpay_key', 'plan_razorpay_secret', 'plan_paypal_client', 'plan_paypal_secret'];
  for (const k of keys) { if (b[k] != null) upd[k] = String(b[k]); }
  setSettingsMany(upd);
  logActivity(req.user, 'Updated plan settings', req);
  res.redirect('/plans/settings?saved=1');
});

/* ---------- DOCS MANAGEMENT ---------- */

router.get('/docs', track('docs'), (req, res) => {
  const q = (req.query.q || '').trim();
  const cat = (req.query.category || '').trim();
  const page = Math.max(1, parseInt(req.query.page, 10) || 1);
  const limit = 10;
  const offset = (page - 1) * limit;

  let where = 'WHERE 1=1';
  const params = [];
  if (q) { where += ' AND (d.title LIKE ? OR d.description LIKE ? OR d.tags LIKE ?)'; params.push(`%${q}%`, `%${q}%`, `%${q}%`); }
  if (cat) { where += ' AND d.category = ?'; params.push(cat); }

  const total = db.prepare(`SELECT COUNT(*) c FROM docs d ${where}`).get(...params).c;
  const totalPages = Math.max(1, Math.ceil(total / limit));
  const docs = db.prepare(`SELECT d.*, u.username AS author FROM docs d LEFT JOIN users u ON u.id = d.author_id ${where} ORDER BY d.updated_at DESC LIMIT ? OFFSET ?`).all(...params, limit, offset);
  const categories = db.prepare('SELECT DISTINCT category FROM docs WHERE category != \'\' ORDER BY category').all().map(r => r.category);

  pageView(req, res, 'pages/user/docs', {
    title: 'Docs', active: 'docs', docs, q, cat, categories, page, totalPages, total,
    query: req.query,
    isAdmin: req.user && req.user.role === 'admin',
    extraScripts: '<script src="/js/docs.js"></script>'
  });
});

router.get('/docs/view/:id', track('docs'), (req, res) => {
  const doc = db.prepare('SELECT d.*, u.username AS author FROM docs d LEFT JOIN users u ON u.id = d.author_id WHERE d.id = ?').get(req.params.id);
  if (!doc) return res.redirect('/docs');
  pageView(req, res, 'pages/user/doc-view', { title: doc.title, active: 'docs', doc, query: req.query });
});

router.post('/docs/add', requireAdmin, (req, res) => {
  const { title, description, content, category, tags, status } = req.body;
  if (!title) return res.redirect('/docs?error=title');
  db.prepare('INSERT INTO docs (title, description, content, category, tags, status, author_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
    .run(title, description || '', content || '', (category || '').trim(), (tags || '').trim(), status === 'draft' ? 'draft' : 'published', req.user.id);
  logActivity(req.user, `Added doc "${title}"`, req);
  res.redirect('/docs?saved=1');
});

router.post('/docs/:id/edit', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM docs WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/docs');
  const { title, description, content, category, tags, status } = req.body;
  db.prepare("UPDATE docs SET title = ?, description = ?, content = ?, category = ?, tags = ?, status = ?, updated_at = datetime('now') WHERE id = ?")
    .run(title || target.title, description != null ? description : target.description, content != null ? content : target.content, (category != null ? category : target.category || '').trim(), (tags != null ? tags : target.tags || '').trim(), status === 'draft' ? 'draft' : 'published', target.id);
  logActivity(req.user, `Edited doc "${target.title}"`, req);
  res.redirect('/docs?edited=1');
});

router.post('/docs/:id/delete', requireAdmin, (req, res) => {
  const target = db.prepare('SELECT * FROM docs WHERE id = ?').get(req.params.id);
  if (!target) return res.redirect('/docs');
  db.prepare('DELETE FROM docs WHERE id = ?').run(target.id);
  logActivity(req.user, `Deleted doc "${target.title}"`, req);
  res.redirect('/docs?deleted=1');
});

router.post('/docs/bulk-delete', requireAdmin, (req, res) => {
  const ids = (req.body.ids || '').split(',').map(Number).filter(n => n > 0);
  if (ids.length) {
    const placeholders = ids.map(() => '?').join(',');
    db.prepare(`DELETE FROM docs WHERE id IN (${placeholders})`).run(...ids);
    logActivity(req.user, `Bulk deleted ${ids.length} doc(s)`, req);
  }
  res.redirect('/docs?deleted=1');
});

module.exports = router;
module.exports.CONTENT_SLUGS = CONTENT_SLUGS;
