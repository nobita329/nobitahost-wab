const express = require('express');
const bcrypt = require('bcryptjs');
const crypto = require('crypto');
const { authenticator } = require('otplib');
const db = require('../db');
const { getSettings, resolveBackground } = require('../settings');
const { signToken, logActivity, COOKIE } = require('../middleware/auth');
const { sendMail } = require('../mailer');
const { isValidEmail, isValidUsername } = require('../validate');

const router = express.Router();

function getNext(req) {
  const n = req.query.next || '/';
  return typeof n === 'string' && n.startsWith('/') ? n : '/';
}

function renderAuth(res, view, opts = {}) {
  const s = getSettings();
  res.render(`pages/auth/${view}`, {
    title: '',
    bodyClass: 'auth-page',
    bg: resolveBackground(s),
    ...opts
  });
}

router.get('/login', (req, res) => {
  if (req.user) return res.redirect('/');
  renderAuth(res, 'login', { error: null, next: getNext(req), reset: !!req.query.reset });
});

router.get('/login/demo', (req, res) => {
  if (req.user) return res.redirect('/');
  const demo = db.prepare("SELECT * FROM users WHERE is_demo = 1 AND status = 'active'").get();
  if (!demo) return renderAuth(res, 'login', { error: 'Demo account is not available.', next: '/', reset: false });
  db.prepare("UPDATE users SET last_login = datetime('now'), last_ip = ? WHERE id = ?").run(
    (req.headers['x-forwarded-for'] || req.ip || '').split(',')[0].trim(),
    demo.id
  );
  logActivity(demo, 'Logged in (demo auto-login)', req);
  res.cookie(COOKIE, signToken(demo), { httpOnly: true, sameSite: 'lax', maxAge: 7 * 24 * 3600 * 1000 });
  res.redirect(getNext(req));
});

router.post('/login', (req, res) => {
  const { username, password, next } = req.body;
  const redirect = getNext({ query: { next: next || req.query.next || '/' } });

  if (!username || !password) {
    return renderAuth(res, 'login', { error: 'Please enter username/email and password', next: redirect });
  }

  if (username.includes('@') && !isValidEmail(username)) {
    return renderAuth(res, 'login', { error: 'Please enter a valid email address', next: redirect });
  }

  const user = db
    .prepare('SELECT * FROM users WHERE username = ? OR email = ?')
    .get(username, username);

  if (!user || !bcrypt.compareSync(password || '', user.password)) {
    return renderAuth(res, 'login', { error: 'Invalid username or password', next: redirect });
  }
  if (user.is_demo) {
    return renderAuth(res, 'login', { error: 'The demo account can only be accessed from the panel — use the Try Demo button.', next: redirect, reset: false });
  }
  if (user.status === 'suspended') {
    return renderAuth(res, 'login', { error: 'Your account has been suspended. Contact the administrator.', next: redirect });
  }

  if (user.two_factor_enabled) {
    res.cookie('nh_pending_2fa', String(user.id), { httpOnly: true, sameSite: 'lax', maxAge: 10 * 60 * 1000 });
    return res.redirect('/verify-2fa?next=' + encodeURIComponent(redirect));
  }

  db.prepare("UPDATE users SET last_login = datetime('now'), last_ip = ? WHERE id = ?").run(
    (req.headers['x-forwarded-for'] || req.ip || '').split(',')[0].trim(),
    user.id
  );
  logActivity(user, 'Logged in', req);
  res.cookie(COOKIE, signToken(user), { httpOnly: true, sameSite: 'lax', maxAge: 7 * 24 * 3600 * 1000 });
  res.redirect(redirect);
});

router.get('/register', (req, res) => {
  const s = getSettings();
  if (req.user) return res.redirect('/');
  if (s.register_open !== 'on') {
    return renderAuth(res, 'register', { error: 'Registration is currently closed by the administrator.', success: null });
  }
  renderAuth(res, 'register', { error: null, success: null });
});

router.post('/register', (req, res) => {
  const s = getSettings();
  if (s.register_open !== 'on') {
    return renderAuth(res, 'register', { error: 'Registration is currently closed by the administrator.', success: null });
  }
  const { username, email, password, confirm } = req.body;
  if (!username || !email || !password) return renderAuth(res, 'register', { error: 'All fields are required', success: null });
  if (!isValidUsername(username)) return renderAuth(res, 'register', { error: 'Username must be 2-32 chars (letters, numbers, _.-)', success: null });
  if (!isValidEmail(email)) return renderAuth(res, 'register', { error: 'Please enter a valid email address (e.g. user@example.com)', success: null });
  if (password.length < 6) return renderAuth(res, 'register', { error: 'Password must be at least 6 characters', success: null });
  if (password !== confirm) return renderAuth(res, 'register', { error: 'Passwords do not match', success: null });

  const exists = db.prepare('SELECT id FROM users WHERE username = ? OR email = ?').get(username, email);
  if (exists) return renderAuth(res, 'register', { error: 'Username or email already exists', success: null });

  const hash = bcrypt.hashSync(password, 10);
  const info = db.prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)').run(username, email, hash, 'user');
  const user = db.prepare('SELECT * FROM users WHERE id = ?').get(info.lastInsertRowid);
  logActivity(user, 'Registered account', req);

  sendMail(email, `Welcome to ${s.panel_name}`, `<p>Hi ${username},</p><p>Your ${s.panel_name} account has been created successfully.</p><p>You can login at ${process.env.PANEL_URL || ''}/login</p>`);

  renderAuth(res, 'register', { error: null, success: `Account created for ${username}. You can now login.` });
});

router.get('/forgot', (req, res) => {
  if (req.user) return res.redirect('/');
  renderAuth(res, 'forgot', { error: null, success: null });
});

router.post('/forgot', async (req, res) => {
  const { email } = req.body;
  const user = db.prepare('SELECT * FROM users WHERE email = ?').get(email);
  if (!user) {
    return renderAuth(res, 'forgot', { error: 'No account found with that email', success: null });
  }
  if (user.is_demo) {
    return renderAuth(res, 'forgot', { error: 'The demo account password cannot be changed.', success: null });
  }
  const token = crypto.randomBytes(32).toString('hex');
  db.prepare("INSERT INTO reset_tokens (user_id, token, expires_at) VALUES (?, ?, datetime('now', '+1 hour'))").run(user.id, token);
  const s = getSettings();
  const base = process.env.PANEL_URL || `${req.protocol}://${req.get('host')}`;
  const link = `${base}/reset/${token}`;
  const mailResult = await sendMail(user.email, `Reset your ${s.panel_name} password`, `<p>Hi ${user.username},</p><p>Click the link below to reset your password (valid 1 hour):</p><p><a href="${link}">${link}</a></p>`);
  renderAuth(res, 'forgot', {
    error: null,
    success: mailResult.skipped ? `Reset link (SMTP not configured): ${link}` : 'If the account exists, a password reset link has been emailed.'
  });
});

router.get('/reset/:token', (req, res) => {
  renderAuth(res, 'reset', { error: null, success: null, token: req.params.token });
});

router.post('/reset/:token', (req, res) => {
  const { password, confirm } = req.body;
  const row = db.prepare("SELECT * FROM reset_tokens WHERE token = ? AND used = 0 AND expires_at > datetime('now')").get(req.params.token);
  if (!row) return renderAuth(res, 'reset', { error: 'This reset link is invalid or has expired.', success: null, token: req.params.token });
  if (!password || password.length < 6) return renderAuth(res, 'reset', { error: 'Password must be at least 6 characters', success: null, token: req.params.token });
  if (password !== confirm) return renderAuth(res, 'reset', { error: 'Passwords do not match', success: null, token: req.params.token });

  const user = db.prepare('SELECT * FROM users WHERE id = ?').get(row.user_id);
  if (user.is_demo) {
    return renderAuth(res, 'reset', { error: 'The demo account password cannot be changed.', success: null, token: req.params.token });
  }
  db.prepare('UPDATE users SET password = ? WHERE id = ?').run(bcrypt.hashSync(password, 10), user.id);
  db.prepare('UPDATE reset_tokens SET used = 1 WHERE id = ?').run(row.id);
  logActivity(user, 'Reset password', req);
  res.redirect('/login?reset=1');
});

router.get('/verify-2fa', (req, res) => {
  if (req.user) return res.redirect('/');
  const pendingId = req.cookies.nh_pending_2fa;
  if (!pendingId) return res.redirect('/login');
  renderAuth(res, 'verify-2fa', { error: null, next: getNext(req) });
});

router.post('/verify-2fa', (req, res) => {
  const pendingId = req.cookies.nh_pending_2fa;
  const user = pendingId && db.prepare('SELECT * FROM users WHERE id = ?').get(Number(pendingId));
  if (!user) return res.redirect('/login');

  const ok = authenticator.verify({ token: String(req.body.code || '').replace(/\s/g, ''), secret: user.two_factor_secret });
  if (!ok) return renderAuth(res, 'verify-2fa', { error: 'Invalid code. Please try again.', next: getNext(req) });

  db.prepare("UPDATE users SET last_login = datetime('now'), last_ip = ? WHERE id = ?").run(
    (req.headers['x-forwarded-for'] || req.ip || '').split(',')[0].trim(),
    user.id
  );
  logActivity(user, 'Logged in (2FA verified)', req);
  res.clearCookie('nh_pending_2fa');
  res.cookie(COOKIE, signToken(user), { httpOnly: true, sameSite: 'lax', maxAge: 7 * 24 * 3600 * 1000 });
  res.redirect(getNext(req));
});

router.get('/logout', (req, res) => {
  if (req.user) logActivity(req.user, 'Logged out', req);
  res.clearCookie(COOKIE);
  res.clearCookie('nh_pending_2fa');
  res.redirect('/login');
});

module.exports = router;
