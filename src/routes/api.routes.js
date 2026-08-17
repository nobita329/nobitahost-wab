const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const db = require('../db');
const { getSettings, setSettingsMany } = require('../settings');
const { JWT_SECRET, COOKIE, logActivity } = require('../middleware/auth');
const { getSystemStats, recordSample, getHistory } = require('../system');
const { isValidEmail, isValidUsername } = require('../validate');

const router = express.Router();
const BANNED_PATHS = ['/api/auth/login', '/api/auth/register'];

function getUserFromReq(req) {
  const token = req.cookies[COOKIE];
  if (!token) return null;
  try {
    const payload = jwt.verify(token, JWT_SECRET);
    const user = db.prepare('SELECT * FROM users WHERE id = ?').get(payload.id);
    return user && user.status === 'active' ? user : null;
  } catch (e) {
    return null;
  }
}

function ok(res, data, code = 200) {
  res.status(code).json({ success: true, ...data });
}
function fail(res, message, code = 400, extra = {}) {
  res.status(code).json({ success: false, error: message, ...extra });
}

/* Auth */
router.post('/auth/login', (req, res) => {
  const { username, password } = req.body;
  const user = db.prepare('SELECT * FROM users WHERE username = ? OR email = ?').get(username, username);
  if (!user || !bcrypt.compareSync(password || '', user.password)) return fail(res, 'Invalid credentials', 401);
  if (user.status === 'suspended') return fail(res, 'Account suspended', 403);
  if (user.two_factor_enabled) return ok(res, { twoFactorRequired: true, userId: user.id });
  const token = jwt.sign({ id: user.id, username: user.username, role: user.role }, JWT_SECRET, { expiresIn: '7d' });
  logActivity(user, 'Logged in via API', req);
  res.cookie(COOKIE, token, { httpOnly: true, sameSite: 'lax', maxAge: 7 * 24 * 3600 * 1000 });
  ok(res, { user: publicUser(user), token });
});

router.post('/auth/register', (req, res) => {
  const s = getSettings();
  if (s.register_open !== 'on') return fail(res, 'Registration is closed', 403);
  const { username, email, password } = req.body;
  if (!username || !email || !password) return fail(res, 'All fields are required');
  if (!isValidUsername(username)) return fail(res, 'Username must be 2-32 chars (letters, numbers, _.-)');
  if (!isValidEmail(email)) return fail(res, 'Please enter a valid email address');
  if (password.length < 6) return fail(res, 'Password must be at least 6 characters');
  const exists = db.prepare('SELECT id FROM users WHERE username = ? OR email = ?').get(username, email);
  if (exists) return fail(res, 'Username or email already exists', 409);
  const info = db.prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)').run(username, email, bcrypt.hashSync(password, 10));
  ok(res, { message: 'Registered', id: info.lastInsertRowid }, 201);
});

router.get('/auth/me', (req, res) => {
  const user = getUserFromReq(req);
  if (!user) return fail(res, 'Not authenticated', 401);
  ok(res, { user: publicUser(user) });
});

router.get('/activity', (req, res) => {
  const user = getUserFromReq(req);
  if (!user) return fail(res, 'Not authenticated', 401);
  const rows = user.role === 'admin'
    ? db.prepare('SELECT * FROM activity ORDER BY created_at DESC LIMIT 100').all()
    : db.prepare('SELECT * FROM activity WHERE user_id = ? ORDER BY created_at DESC LIMIT 100').all(user.id);
  ok(res, { activity: rows });
});

router.get('/analytics', (req, res) => {
  const user = getUserFromReq(req);
  if (!user) return fail(res, 'Not authenticated', 401);
  const rows = db.prepare("SELECT page, date, SUM(hits) hits FROM analytics WHERE date >= date('now','-6 day') GROUP BY page, date ORDER BY date").all();
  ok(res, { analytics: rows });
});

router.get('/system', (req, res) => {
  const user = getUserFromReq(req);
  if (!user) return fail(res, 'Not authenticated', 401);
  const stats = getSystemStats();
  recordSample(stats);
  ok(res, { system: stats });
});

router.get('/system/history', (req, res) => {
  const user = getUserFromReq(req);
  if (!user) return fail(res, 'Not authenticated', 401);
  ok(res, { history: getHistory(req.query.range || '24h') });
});

router.get('/settings', (req, res) => {
  ok(res, { settings: publicSettings(getSettings()) });
});

router.put('/settings', (req, res) => {
  const user = getUserFromReq(req);
  if (!user || user.role !== 'admin') return fail(res, 'Admin only', 403);
  const ALLOWED = ['panel_name', 'panel_tagline', 'logo_url', 'logo_emoji', 'favicon_url', 'background_url', 'background_source', 'music_url', 'transparent_bar', 'blur_bar', 'script_enabled', 'script_badge', 'script_description', 'script_command', 'discord_enabled', 'discord_server_id', 'discord_channel', 'discord_theme', 'youtube_enabled', 'youtube_channel', 'youtube_api_key'];
  const upd = {};
  for (const k of ALLOWED) if (k in req.body) upd[k] = req.body[k];
  setSettingsMany(upd);
  ok(res, { settings: publicSettings(getSettings()) });
});

router.get('/admin/users', (req, res) => {
  const user = getUserFromReq(req);
  if (!user || user.role !== 'admin') return fail(res, 'Admin only', 403);
  const users = db.prepare('SELECT id, username, email, role, status, owner_id, last_login, created_at FROM users ORDER BY created_at DESC').all();
  ok(res, { users });
});

router.post('/admin/users', (req, res) => {
  const user = getUserFromReq(req);
  if (!user || user.role !== 'admin') return fail(res, 'Admin only', 403);
  const { username, email, password, role } = req.body;
  if (!username || !email || !password) return fail(res, 'All fields are required');
  if (!isValidUsername(username)) return fail(res, 'Username must be 2-32 chars (letters, numbers, _.-)');
  if (!isValidEmail(email)) return fail(res, 'Please enter a valid email address');
  if (password.length < 6) return fail(res, 'Password must be at least 6 characters');
  const exists = db.prepare('SELECT id FROM users WHERE username = ? OR email = ?').get(username, email);
  if (exists) return fail(res, 'User exists', 409);
  const info = db.prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)')
    .run(username, email, bcrypt.hashSync(password, 10), role === 'admin' ? 'admin' : 'user');
  ok(res, { id: info.lastInsertRowid }, 201);
});

router.put('/admin/users/:id', (req, res) => {
  const user = getUserFromReq(req);
  if (!user || user.role !== 'admin') return fail(res, 'Admin only', 403);
  const target = db.prepare('SELECT * FROM users WHERE id = ?').get(req.params.id);
  if (!target) return fail(res, 'Not found', 404);
  const { username, email, role } = req.body;
  db.prepare('UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?')
    .run(username || target.username, email || target.email, role === 'admin' ? 'admin' : 'user', target.id);
  ok(res, { message: 'Updated' });
});

router.put('/admin/users/:id/suspend', (req, res) => {
  const user = getUserFromReq(req);
  if (!user || user.role !== 'admin') return fail(res, 'Admin only', 403);
  const target = db.prepare('SELECT * FROM users WHERE id = ?').get(req.params.id);
  if (!target) return fail(res, 'Not found', 404);
  const suspended = req.body.suspended === true;
  db.prepare('UPDATE users SET status = ? WHERE id = ?').run(suspended ? 'suspended' : 'active', target.id);
  ok(res, { status: suspended ? 'suspended' : 'active' });
});

router.delete('/admin/users/:id', (req, res) => {
  const user = getUserFromReq(req);
  if (!user || user.role !== 'admin') return fail(res, 'Admin only', 403);
  if (Number(req.params.id) === user.id) return fail(res, 'Cannot delete yourself', 400);
  db.prepare('DELETE FROM users WHERE id = ?').run(req.params.id);
  ok(res, { message: 'Deleted' });
});

function publicUser(u) {
  return {
    id: u.id, username: u.username, email: u.email, role: u.role, status: u.status,
    bio: u.bio, profile_pic: u.profile_pic, two_factor_enabled: !!u.two_factor_enabled,
    last_login: u.last_login, created_at: u.created_at
  };
}

function publicSettings(s) {
  const out = {};
  for (const k of ['panel_name', 'panel_tagline', 'logo_url', 'logo_emoji', 'favicon_url', 'background_url', 'background_source', 'music_url', 'music_type', 'transparent_bar', 'blur_bar', 'script_enabled', 'script_badge', 'script_description', 'script_command', 'discord_enabled', 'discord_server_id', 'discord_channel', 'discord_theme', 'youtube_enabled', 'youtube_channel', 'youtube_api_key']) {
    out[k] = s[k];
  }
  return out;
}

module.exports = router;
