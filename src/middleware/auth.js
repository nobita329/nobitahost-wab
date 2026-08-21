const jwt = require('jsonwebtoken');
const db = require('../db');
const { getSettings, resolveBackground } = require('../settings');

const JWT_SECRET = process.env.JWT_SECRET || 'nobitahost-dev-secret';
const COOKIE = 'nh_token';

function signToken(user) {
  return jwt.sign(
    { id: user.id, username: user.username, role: user.role },
    JWT_SECRET,
    { expiresIn: '7d' }
  );
}

function cookieOptions(req, extra = {}) {
  const proto = req.protocol || 'http';
  const isSecure = proto === 'https' || (req.headers['x-forwarded-proto'] || '').includes('https');
  return {
    httpOnly: true,
    sameSite: isSecure ? 'none' : 'lax',
    secure: isSecure,
    maxAge: extra.maxAge || 7 * 24 * 3600 * 1000,
    path: '/'
  };
}

function logActivity(user, action, req) {
  if (!user) return;
  try {
    db.prepare(
      'INSERT INTO activity (user_id, username, action, ip, user_agent) VALUES (?, ?, ?, ?, ?)'
    ).run(
      user.id,
      user.username,
      action,
      req ? (req.headers['x-forwarded-for'] || req.ip || '').split(',')[0].trim() : '',
      req ? (req.headers['user-agent'] || '').slice(0, 200) : ''
    );
  } catch (e) {
    /* noop */
  }
}

function loadUser(req, res, next) {
  const settings = getSettings();
  res.locals.settings = settings;
  res.locals.normalizeBlur = (s) => { const b = parseInt((s && s.panel_blur) || 0, 10) || 0; return Math.min(40, Math.max(0, b)); };
  res.locals.overlayAlpha = (s) => { const t = Math.min(100, Math.max(0, parseInt((s && s.transparency) || 100, 10) || 100)); return ((100 - t) / 100 * 0.55).toFixed(3); };
  const token = req.cookies[COOKIE];
  if (token) {
    try {
      const payload = jwt.verify(token, JWT_SECRET);
      const user = db.prepare('SELECT * FROM users WHERE id = ?').get(payload.id);
      if (user && user.status === 'active') req.user = user;
    } catch (e) {
      res.clearCookie(COOKIE, { path: '/' });
    }
  }
  res.locals.user = req.user || null;
  try {
    res.locals.navPages = db.prepare("SELECT slug, title, icon FROM content_pages WHERE in_nav = 1 ORDER BY id").all();
  } catch (e) {
    res.locals.navPages = [];
  }
  try {
    const obsidian = require('../obsidian');
    const navData = obsidian.parseNavbar(obsidian.load('navbar'));
    res.locals.customNavLinks = navData.enabled ? obsidian.visibleFor(navData.links, req.user) : [];
    res.locals.footerData = obsidian.parseFooter(obsidian.load('footer'));
  } catch (e) {
    res.locals.customNavLinks = [];
    res.locals.footerData = null;
  }
  next();
}

function requireAuth(req, res, next) {
  if (!req.user) return res.redirect('/login?next=' + encodeURIComponent(req.originalUrl));
  next();
}

function requireAdmin(req, res, next) {
  if (!req.user) return res.redirect('/login?next=' + encodeURIComponent(req.originalUrl));
  if (req.user.role !== 'admin') {
    const settings = getSettings();
    return res.status(403).render('pages/errors/403', {
      title: '403', bodyClass: 'auth-page',
      bg: resolveBackground(settings)
    });
  }
  next();
}

module.exports = {
  COOKIE,
  JWT_SECRET,
  signToken,
  cookieOptions,
  logActivity,
  loadUser,
  requireAuth,
  requireAdmin
};
