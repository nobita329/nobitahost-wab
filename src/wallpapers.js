'use strict';

const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

const CATEGORIES = [
  { slug: 'all', label: 'All Categories', path: '/' },
  { slug: 'most-popular-4k-wallpapers', label: 'Most Popular' },
  { slug: 'best-4k-wallpapers', label: 'Best 4K' },
  { slug: 'cool-wallpapers', label: 'Cool' },
  { slug: 'aesthetic-wallpapers', label: 'Aesthetic' },
  { slug: 'cute-kawaii-wallpapers', label: 'Cute Kawaii' },
  { slug: 'black-dark', label: 'Black & Dark' },
  { slug: 'pitch-black-wallpapers', label: 'Pitch Black' },
  { slug: 'space', label: 'Space' },
  { slug: 'nature', label: 'Nature' },
  { slug: 'abstract', label: 'Abstract' },
  { slug: 'anime', label: 'Anime' },
  { slug: 'cars', label: 'Cars' },
  { slug: 'games', label: 'Games' },
  { slug: 'technology', label: 'Technology' },
  { slug: 'cr7-wallpapers', label: 'CR7' },
  { slug: 'aot-wallpapers', label: 'Attack on Titan' },
  { slug: 'hollow-knight-wallpapers', label: 'Hollow Knight' },
  { slug: 'random-wallpapers', label: 'Random' },
  { slug: '5k-wallpapers', label: '5K' },
  { slug: '8k-wallpapers', label: '8K' },
  { slug: 'ultrawide-monitor-hd-wallpapers', label: 'Ultrawide HD' },
  { slug: 'ultrawide-monitor-3440-wallpapers', label: 'Ultrawide 3440' },
  { slug: 'dual-monitor-hd-wallpapers', label: 'Dual Monitor' },
  { slug: 'windows-11-stock-wallpapers', label: 'Windows 11 Stock' },
  { slug: 'macos-27-golden-wallpapers', label: 'macOS Golden' },
  { slug: 'aluminium-os-stock-wallpapers', label: 'Aluminium OS' },
  { slug: 'iphone-17-pro-wallpapers', label: 'iPhone 17 Pro' },
  { slug: 'uncompressed-png-wallpapers', label: 'Uncompressed PNG' }
];

const TTL = 600 * 1000;
const cache = new Map();

function cacheKey(cat, page, q) {
  return `${cat || 'all'}|${page || 1}|${(q || '').trim().toLowerCase()}`;
}

function buildUrl(cat, page, q) {
  const p = Math.max(1, parseInt(page, 10) || 1);
  const query = (q || '').trim();
  let url;
  if (query) {
    url = `https://4kwallpapers.com/search/?text=${encodeURIComponent(query)}`;
    if (p > 1) url += `&page=${p}`;
  } else if (!cat || cat === 'all' || cat === 'none') {
    url = 'https://4kwallpapers.com/';
    if (p > 1) url += `?page=${p}`;
  } else {
    url = `https://4kwallpapers.com/${encodeURIComponent(cat)}/`;
    if (p > 1) url += `?page=${p}`;
  }
  return url;
}

function parsePage(html, page) {
  const items = [];
  const blocks = html.split('<p itemprop="associatedMedia"');
  for (const b of blocks) {
    const contentM = b.match(/contentUrl" href="([^"]+)"/);
    const thumbM = b.match(/<img itemprop="thumbnail" src="([^"]+)"/);
    const linkM = b.match(/href="(https:\/\/4kwallpapers\.com\/[a-z0-9-]+\/[a-z0-9-]+-(\d+)\.html)"/);
    const captM = b.match(/class="title(?:2| tags)">([^<]*)/);
    const altM = b.match(/alt="([^"]+)"/);
    if (!contentM || !thumbM) continue;
    const thumb = thumbM[1];
    const extM = thumb.match(/\.(jpg|png|webp)$/i);
    const ext = extM ? extM[1] : 'jpg';
    const id = linkM ? linkM[2] : ((contentM[1].match(/(\d+)\.\w+$/) || [])[1] || '');
    let full = contentM[1];
    if (!/\/wallpapers\//.test(full) && linkM) {
      const slug = linkM[1].split('/').pop().replace(/-\d+\.html$/, '');
      full = `https://4kwallpapers.com/images/wallpapers/${slug}--${id}.${ext}`;
    }
    let title = captM && captM[1] ? captM[1].trim().split(',')[0].trim() : '';
    if (!title && altM) title = altM[1].split(',')[0].trim();
    if (!title) title = 'Wallpaper';
    const cat = linkM ? (linkM[1].split('/')[3] || '') : '';
    items.push({
      id,
      title,
      thumb,
      full,
      detail: linkM ? linkM[1] : '',
      category: cat
    });
  }
  const nums = [];
  const re = /page="(\d+)"/g;
  let m;
  while ((m = re.exec(html)) !== null) nums.push(parseInt(m[1], 10));
  const maxPage = nums.length ? Math.max.apply(null, nums) : 0;
  const hasNextCtrl = /ctrl-right/.test(html);
  let hasNext = maxPage ? maxPage > (page || 1) : hasNextCtrl;
  if (maxPage === (page || 1) && hasNextCtrl) hasNext = true;
  return {
    items,
    page: Math.max(1, parseInt(page, 10) || 1),
    totalPages: maxPage > 1 ? maxPage : null,
    hasNext
  };
}

async function fetchWallpapers({ category, page, q }) {
  const p = Math.max(1, parseInt(page, 10) || 1);
  const query = (q || '').trim();
  const key = cacheKey(category, p, query);
  const hit = cache.get(key);
  if (hit && Date.now() - hit.t < TTL) return hit.data;

  const url = buildUrl(category, p, query);
  const res = await fetch(url, {
    headers: { 'User-Agent': UA, Accept: 'text/html' },
    redirect: 'follow'
  });
  if (!res.ok) {
    const err = new Error(`4kwallpapers.com responded ${res.status}`);
    err.status = res.status;
    throw err;
  }
  const html = await res.text();
  const data = parsePage(html, p);
  cache.set(key, { t: Date.now(), data });
  return data;
}

function getCategoryLabel(slug) {
  const c = CATEGORIES.find((x) => x.slug === slug);
  return c ? c.label : slug;
}

module.exports = {
  CATEGORIES,
  fetchWallpapers,
  getCategoryLabel,
  buildUrl
};
