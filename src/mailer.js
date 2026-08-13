const nodemailer = require('nodemailer');
const { getSettings } = require('./settings');

function getTransport() {
  const s = getSettings();
  const host = s.smtp_host || process.env.SMTP_HOST || '';
  if (!host) return null;
  const secure = (s.smtp_secure || process.env.SMTP_SECURE || 'false') === 'true';
  return nodemailer.createTransport({
    host,
    port: Number(s.smtp_port || process.env.SMTP_PORT || 587),
    secure,
    auth: s.smtp_user
      ? { user: s.smtp_user || process.env.SMTP_USER, pass: s.smtp_pass || process.env.SMTP_PASS }
      : undefined
  });
}

function isMailEnabled() {
  const s = getSettings();
  return s.mail_enabled === 'on' && (s.smtp_host || process.env.SMTP_HOST);
}

async function sendMail(to, subject, html) {
  const s = getSettings();
  if (!isMailEnabled()) {
    console.log(`[Mail] Email would be sent to ${to} (SMTP not configured): ${subject}`);
    return { skipped: true };
  }
  const transport = getTransport();
  if (!transport) return { skipped: true };
  const from = s.mail_from || process.env.MAIL_FROM;
  await transport.sendMail({ from, to, subject, html });
  return { ok: true };
}

module.exports = { sendMail, isMailEnabled };
