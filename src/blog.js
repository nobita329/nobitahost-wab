const db = require('./db');
const markdown = require('./markdown');

const PER_PAGE = 9;

function parseTags(post) {
  return String(post.tags || '').split(',').map((t) => t.trim()).filter(Boolean);
}

function slugify(s) {
  return String(s).toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'post';
}

function uniqueSlug(base, excludeId) {
  let slug = slugify(base);
  let n = 1;
  while (true) {
    const row = excludeId
      ? db.prepare('SELECT id FROM blog_posts WHERE slug = ? AND id != ?').get(slug, excludeId)
      : db.prepare('SELECT id FROM blog_posts WHERE slug = ?').get(slug);
    if (!row) return slug;
    slug = `${slugify(base)}-${++n}`;
  }
}

function listPublished({ search = '', tag = '', sort = 'latest', page = 1 }) {
  let rows = db.prepare(`SELECT * FROM blog_posts WHERE is_published = 1 AND published_at IS NOT NULL ORDER BY published_at DESC, id DESC`).all();
  if (search) {
    const q = search.toLowerCase();
    rows = rows.filter((p) => p.title.toLowerCase().includes(q) || String(p.description || '').toLowerCase().includes(q) || parseTags(p).some((t) => t.toLowerCase().includes(q)));
  }
  if (tag) rows = rows.filter((p) => parseTags(p).some((t) => t.toLowerCase() === tag.toLowerCase()));
  if (sort === 'oldest') rows.sort((a, b) => String(a.published_at).localeCompare(String(b.published_at)));
  else if (sort === 'updated') rows.sort((a, b) => String(b.updated_at).localeCompare(String(a.updated_at)));
  else if (sort === 'popular') rows.sort((a, b) => b.views - a.views);
  const total = rows.length;
  const pages = Math.max(1, Math.ceil(total / PER_PAGE));
  page = Math.min(Math.max(1, parseInt(page, 10) || 1), pages);
  const posts = rows.slice((page - 1) * PER_PAGE, page * PER_PAGE).map((p) => ({ ...p, tag_list: parseTags(p), excerpt: excerpt(p) }));
  const tagSummary = {};
  for (const p of rows) for (const t of parseTags(p)) tagSummary[t] = (tagSummary[t] || 0) + 1;
  return {
    posts,
    total,
    page,
    pages,
    tags: Object.entries(tagSummary).map(([label, count]) => ({ label, count })).sort((a, b) => b.count - a.count)
  };
}

function excerpt(post, len = 160) {
  const text = String(post.description || '').trim() ||
    String(post.content || '').replace(/```[\s\S]*?```/g, ' ').replace(/[#>*_`~|\[\]()-]/g, ' ').replace(/\s+/g, ' ').trim();
  return text.length > len ? `${text.slice(0, len).trim()}…` : text;
}

function getPublished(slug) {
  const post = db.prepare('SELECT * FROM blog_posts WHERE slug = ? AND is_published = 1 AND published_at IS NOT NULL').get(slug);
  if (!post) return null;
  const rendered = markdown.render(post.content);
  const prev = db.prepare('SELECT slug, title FROM blog_posts WHERE is_published = 1 AND published_at > ? ORDER BY published_at ASC LIMIT 1').get(post.published_at);
  const next = db.prepare('SELECT slug, title FROM blog_posts WHERE is_published = 1 AND published_at < ? ORDER BY published_at DESC LIMIT 1').get(post.published_at);
  const fb = db.prepare('SELECT SUM(is_helpful = 1) helpful, SUM(is_helpful = 0) unhelpful FROM blog_post_feedback WHERE post_id = ?').get(post.id);
  return {
    ...post,
    tag_list: parseTags(post),
    excerpt: excerpt(post),
    html: rendered.html,
    toc: rendered.toc,
    prev: prev || null,
    next: next || null,
    feedback: { helpful: fb.helpful || 0, unhelpful: fb.unhelpful || 0 }
  };
}

function latest(limit = 3) {
  return db.prepare('SELECT id, title, slug, description, cover_image_url, tags, views, published_at FROM blog_posts WHERE is_published = 1 AND published_at IS NOT NULL ORDER BY published_at DESC LIMIT ?')
    .all(limit)
    .map((p) => ({ ...p, tag_list: parseTags(p), excerpt: excerpt(p, 110) }));
}

function recordView(post) {
  db.prepare('UPDATE blog_posts SET views = views + 1 WHERE id = ?').run(post.id);
}

function saveFeedback(postId, helpful, sessionId) {
  const existing = db.prepare('SELECT id, is_helpful FROM blog_post_feedback WHERE post_id = ? AND session_id = ?').get(postId, sessionId);
  if (existing) {
    if (existing.is_helpful === helpful) return false;
    db.prepare('UPDATE blog_post_feedback SET is_helpful = ? WHERE id = ?').run(helpful, existing.id);
    return true;
  }
  db.prepare('INSERT INTO blog_post_feedback (post_id, is_helpful, session_id) VALUES (?, ?, ?)').run(postId, helpful, sessionId);
  return true;
}

function fmtDate(ts) {
  if (!ts) return '';
  const d = new Date(String(ts).replace(' ', 'T') + (String(ts).length === 16 ? ':00Z' : 'Z'));
  if (isNaN(d.getTime())) return String(ts).slice(0, 10);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

module.exports = { listPublished, getPublished, latest, recordView, saveFeedback, uniqueSlug, parseTags, excerpt, fmtDate, PER_PAGE };
