const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

function isValidEmail(email) {
  return typeof email === 'string' && email.length <= 254 && EMAIL_RE.test(email);
}

function isValidUsername(username) {
  return typeof username === 'string' && username.length >= 2 && username.length <= 32 && /^[a-zA-Z0-9_.-]+$/.test(username);
}

module.exports = { isValidEmail, isValidUsername };
