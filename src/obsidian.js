const db = require('./db');
const markdown = require('./markdown');

const KEYS = {
  about: 'obsidian_about',
  terms: 'obsidian_terms',
  footer: 'obsidian_footer',
  navbar: 'obsidian_navbar'
};

function load(name) {
  const row = db.prepare('SELECT value FROM settings WHERE key = ?').get(KEYS[name]);
  if (!row || !row.value) return null;
  try {
    const v = JSON.parse(row.value);
    return v && typeof v === 'object' ? v : null;
  } catch (e) {
    return null;
  }
}

function save(name, data) {
  db.prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
    .run(KEYS[name], JSON.stringify(data));
}

function splitLines(text) {
  return String(text || '').split('\n').map((l) => l.trim()).filter(Boolean);
}

function parsePipe(line, n) {
  const parts = line.split('|').map((p) => p.trim());
  while (parts.length < n) parts.push('');
  return parts;
}

/* ---------- About ---------- */

function defaultAbout() {
  return {
    enabled: false,
    seo_title: 'About',
    hero: { title: `About ${db.prepare("SELECT value FROM settings WHERE key = 'panel_name'").get()?.value || 'Us'}`, subtitle: '', image_url: '', cta1_label: 'Contact us', cta1_url: '/links', cta2_label: 'View plans', cta2_url: '/plans' },
    stats_enabled: true,
    stats_text: '99.9% | Uptime\n24/7 | Support\n10k+ | Customers\n1ms | Latency',
    story_enabled: true,
    story_title: 'Our story',
    story_text: 'Write your story here...',
    values_enabled: true,
    values_title: 'What we stand for',
    values_text: 'Speed | Fast setup and fast support.\nQuality | Reliable infrastructure.\nHonesty | Clear pricing and policies.\nCare | We treat customers like humans.',
    team_enabled: true,
    team_title: 'Meet the team',
    team_text: 'PJ | Owner |\nSupport | Customer Success |\nOps | Infrastructure |',
    timeline_enabled: true,
    timeline_title: 'Milestones',
    timeline_text: `${new Date().getFullYear()} | Launched | First customers onboarded.`,
    gallery_enabled: true,
    gallery_title: 'Behind the scenes',
    gallery_text: ''
  };
}

function parseAbout(data) {
  const d = { ...defaultAbout(), ...data };
  return {
    enabled: !!d.enabled,
    seo_title: String(d.seo_title || 'About'),
    hero: {
      title: String(d.hero?.title || ''), subtitle: String(d.hero?.subtitle || ''), image_url: String(d.hero?.image_url || ''),
      cta1_label: String(d.hero?.cta1_label || ''), cta1_url: String(d.hero?.cta1_url || ''),
      cta2_label: String(d.hero?.cta2_label || ''), cta2_url: String(d.hero?.cta2_url || '')
    },
    stats_enabled: !!d.stats_enabled,
    stats: splitLines(d.stats_text).map((l) => { const [value, label] = parsePipe(l, 2); return { value, label }; }),
    story_enabled: !!d.story_enabled,
    story_title: String(d.story_title || ''),
    story_paragraphs: splitLines(d.story_text),
    values_enabled: !!d.values_enabled,
    values_title: String(d.values_title || ''),
    values_items: splitLines(d.values_text).map((l) => { const [title, text] = parsePipe(l, 2); return { title, text }; }),
    team_enabled: !!d.team_enabled,
    team_title: String(d.team_title || ''),
    team_members: splitLines(d.team_text).map((l) => { const [name, role, image] = parsePipe(l, 3); return { name, role, image }; }),
    timeline_enabled: !!d.timeline_enabled,
    timeline_title: String(d.timeline_title || ''),
    timeline_items: splitLines(d.timeline_text).map((l) => { const [year, title, text] = parsePipe(l, 3); return { year, title, text }; }),
    gallery_enabled: !!d.gallery_enabled,
    gallery_title: String(d.gallery_title || ''),
    gallery_images: splitLines(d.gallery_text).map((l) => { const [url, alt] = parsePipe(l, 2); return { url, alt }; })
  };
}

/* ---------- Terms ---------- */

function defaultTerms() {
  return {
    enabled: true,
    title: 'Terms & Conditions',
    summary: '',
    last_updated: new Date().toISOString().slice(0, 10),
    sections_text: '## introduction | Introduction\nReplace this with your own terms content.\n\n## fair-use | Fair Use\n- Do not abuse the services.\n- No illegal activity.'
  };
}

function parseTerms(data) {
  const d = { ...defaultTerms(), ...data };
  const sections = [];
  let cur = null;
  for (const line of splitLines(d.sections_text)) {
    const h = line.match(/^##\s+([a-z0-9-_]+)\s*\|\s*(.+)$/i);
    if (h) {
      if (cur) sections.push(cur);
      cur = { id: h[1].toLowerCase(), title: h[2].trim(), body: [] };
    } else if (cur) {
      cur.body.push(line);
    }
  }
  if (cur) sections.push(cur);
  return {
    enabled: d.enabled === undefined ? true : !!d.enabled,
    title: String(d.title || 'Terms & Conditions'),
    summary: String(d.summary || ''),
    last_updated: String(d.last_updated || ''),
    sections: sections.map((s) => ({ ...s, html: markdown.render(s.body.join('\n')).html }))
  };
}

/* ---------- Footer ---------- */

function defaultFooter() {
  return {
    enabled: false,
    copyright: '',
    columns_text: '# Product\nPlans | /plans | internal | always\nBlog | /blog | internal | always\n# Company\nAbout | /about | internal | always\nTeam | /team | internal | always',
    legal_text: 'Terms & Conditions | /terms | internal | always'
  };
}

function parseFooterLinks(text) {
  return splitLines(text).map((l) => {
    const [label, url, type, visibility] = parsePipe(l, 4);
    return { label, url, type: type === 'external' ? 'external' : 'internal', visibility: ['guest', 'auth'].includes(visibility) ? visibility : 'always' };
  });
}

function parseFooterColumns(text) {
  const columns = [];
  let cur = null;
  for (const line of splitLines(text)) {
    if (/^#\s+/.test(line)) {
      if (cur) columns.push(cur);
      cur = { title: line.replace(/^#\s+/, '').trim(), links: [] };
    } else if (cur) {
      const [label, url, type, visibility] = parsePipe(line, 4);
      cur.links.push({ label, url, type: type === 'external' ? 'external' : 'internal', visibility: ['guest', 'auth'].includes(visibility) ? visibility : 'always' });
    }
  }
  if (cur) columns.push(cur);
  return columns;
}

function parseFooter(data) {
  const d = { ...defaultFooter(), ...data };
  return {
    enabled: !!d.enabled,
    copyright: String(d.copyright || ''),
    columns: parseFooterColumns(d.columns_text),
    legal: parseFooterLinks(d.legal_text)
  };
}

/* ---------- Navbar ---------- */

function defaultNavbar() {
  return {
    enabled: false,
    links_text: 'Status | /status | internal | always\nDiscord | https://discord.gg/yourinvite | external | always'
  };
}

function parseNavbar(data) {
  const d = { ...defaultNavbar(), ...data };
  return {
    enabled: !!d.enabled,
    links: parseFooterLinks(d.links_text)
  };
}

function visibleFor(links, user) {
  return links.filter((l) => l.visibility === 'always' || (l.visibility === 'auth' && user) || (l.visibility === 'guest' && !user));
}

module.exports = { load, save, parseAbout, parseTerms, parseFooter, parseNavbar, defaultAbout, defaultTerms, defaultFooter, defaultNavbar, visibleFor };
