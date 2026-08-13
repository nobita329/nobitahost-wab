#!/usr/bin/env node
require('dotenv').config();
const bcrypt = require('bcryptjs');
const readline = require('readline');
const db = require('../src/db');

const rl = readline.createInterface({ input: process.stdin, output: process.stdout });

function ask(q) {
  return new Promise((resolve) => rl.question(q, resolve));
}

(async () => {
  const args = process.argv.slice(2);
  const getArg = (key) => {
    const i = args.indexOf('--' + key);
    return i >= 0 ? args[i + 1] : undefined;
  };

  let username = getArg('username');
  let email = getArg('email');
  let password = getArg('password');
  let role = getArg('role') || 'user';

  if (!username) username = await ask('Username: ');
  if (!email) email = await ask('Email: ');
  if (!password) password = await ask('Password: ');
  if (!role) {
    const r = await ask('Role (user/admin) [user]: ');
    role = r || 'user';
  }

  username = (username || '').trim();
  email = (email || '').trim();
  password = (password || '').trim();
  role = role.toLowerCase() === 'admin' ? 'admin' : 'user';

  if (!username || !email || !password) {
    console.error('✘ Username, email and password are required.');
    process.exit(1);
  }
  if (password.length < 6) {
    console.error('✘ Password must be at least 6 characters.');
    process.exit(1);
  }

  const exists = db.prepare('SELECT id FROM users WHERE username = ? OR email = ?').get(username, email);
  if (exists) {
    console.error('✘ Username or email already exists.');
    process.exit(1);
  }

  const info = db.prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)')
    .run(username, email, bcrypt.hashSync(password, 10), role);

  console.log(`✔ User created (#${info.lastInsertRowid})`);
  console.log(`   username : ${username}`);
  console.log(`   email    : ${email}`);
  console.log(`   role     : ${role}`);
  rl.close();
})();
