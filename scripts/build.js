#!/usr/bin/env node
require('dotenv').config();
const bcrypt = require('bcryptjs');
const path = require('path');
const fs = require('fs');
const db = require('../src/db');
const { DEFAULT_SETTINGS, setSetting } = require('../src/settings');

console.log('┌───────────────────────────────────────────┐');
console.log('│         NobitaHost · Build/Setup          │');
console.log('└───────────────────────────────────────────┘');

for (const dir of [path.join(__dirname, '..', 'data'), path.join(__dirname, '..', 'public', 'uploads')]) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

let seededSettings = 0;
for (const [k, v] of Object.entries(DEFAULT_SETTINGS)) {
  const exists = db.prepare('SELECT 1 FROM settings WHERE key = ?').get(k);
  if (!exists) {
    setSetting(k, v);
    seededSettings++;
  }
}
console.log(`✔ Settings seeded (${seededSettings} defaults)`);

const DEFAULT_CONTENT = {
  home: `<div class="hero-card">
  <h1>Welcome to <span class="grad-text">NobitaHost</span> 👋</h1>
  <p>Your all-in-one control panel. Manage your team, tutorials, projects, analytics and more — all from one beautiful dashboard.</p>
</div>
<p>Get started by exploring the menu. Admins can edit this content from <b>Settings → Content Management</b>.</p>`,
  team: `<h2>Team Management</h2>
<p>Create and manage your sub-users below. You can add team members, edit their details, suspend or remove them anytime.</p>
<p>Use the <b>+ New Team Member</b> button to invite someone to your team.</p>`,
  tutorials: `<h2>Tutorials</h2>
<p>Browse our step-by-step tutorials to get the most out of your panel.</p>`,
  command: `<h2>Command Center</h2>
<p>Run the panel like a pro. Here are some handy commands:</p>
<div class="cmd-list">
  <div class="cmd"><code>npm run build</code><button class="btn-copy" data-copy="npm run build">Copy</button></div>
  <div class="cmd"><code>npm run createuser</code><button class="btn-copy" data-copy="npm run createuser">Copy</button></div>
  <div class="cmd"><code>pm2 start ecosystem.config.js</code><button class="btn-copy" data-copy="pm2 start ecosystem.config.js">Copy</button></div>
  <div class="cmd"><code>pm2 logs nobitahost-web</code><button class="btn-copy" data-copy="pm2 logs nobitahost-web">Copy</button></div>
</div>`,
  analytics: `<h2>Analytics</h2>
<p>Live page views, login activity and user statistics are shown below.</p>`,
  projects: `<h2>Projects</h2>
<p>Showcase what you build. Describe your active, ongoing and completed projects here.</p>`,
  links: `<h2>Links</h2>
<p>Important links, resources and shortcuts for your community.</p>`,
  github: `<h2>GitHub</h2>
<p>Meet our developers and contributors. Connect with them on GitHub.</p>`,
  about: `<h2>About NobitaHost</h2>
<p>NobitaHost is a modern, self-hosted Node.js panel built for teams and communities. Fast, secure and fully customisable.</p>`
};

let seededPages = 0;
for (const [slug, content] of Object.entries(DEFAULT_CONTENT)) {
  const exists = db.prepare('SELECT 1 FROM content_pages WHERE slug = ?').get(slug);
  if (!exists) {
    db.prepare('INSERT INTO content_pages (slug, title, content) VALUES (?, ?, ?)')
      .run(slug, slug.charAt(0).toUpperCase() + slug.slice(1), content);
    seededPages++;
  }
}
console.log(`✔ Content pages seeded (${seededPages} pages)`);

const admin = db.prepare("SELECT 1 FROM users WHERE role = 'admin'").get();
if (!admin) {
  const username = process.env.ADMIN_USER || 'admin';
  const password = process.env.ADMIN_PASS || 'admin123';
  db.prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)')
    .run(username, 'admin@nobitahost.local', bcrypt.hashSync(password, 10), 'admin');
  console.log(`✔ Default admin created -> username: ${username}  password: ${password}`);
} else {
  console.log('✔ Admin account already exists (skipped)');
}

console.log('\n✅ NobitaHost is ready.');
console.log('   Start web panel : npm start   (port 3001)');
console.log('   Start API       : npm run api (port 3002)');
console.log('   Or with PM2     : pm2 start ecosystem.config.js');
