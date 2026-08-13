const UA = { 'user-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125 Safari/537.36' };

const cache = new Map();
const TTL = 10 * 60 * 1000;

function cacheGet(key) {
  const hit = cache.get(key);
  if (!hit) return null;
  if (Date.now() - hit.at > TTL) { cache.delete(key); return null; }
  return hit.data;
}

function cacheSet(key, data) {
  cache.set(key, { at: Date.now(), data });
  if (cache.size > 50) {
    const oldest = cache.keys().next().value;
    if (oldest) cache.delete(oldest);
  }
}

function parseNumber(str) {
  if (str == null) return null;
  const s = String(str).trim().replace(/,/g, '');
  const m = s.match(/^([\d.]+)\s*([KMB])?$/i);
  if (!m) return null;
  const n = parseFloat(m[1]);
  if (isNaN(n)) return null;
  const mult = { k: 1e3, m: 1e6, b: 1e9 }[m[2] ? m[2].toLowerCase() : ''];
  return Math.round(n * (mult || 1));
}

function fmt(n) {
  if (n == null || isNaN(n)) return '—';
  return n >= 1e9 ? (n / 1e9).toFixed(1).replace(/\.0$/, '') + 'B'
    : n >= 1e6 ? (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M'
    : n >= 1e3 ? (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'K'
    : String(n);
}

function decodeXml(s) {
  if (!s) return '';
  return s
    .replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&apos;/g, "'")
    .replace(/&#(\d+);/g, (m, d) => String.fromCharCode(Number(d)));
}

function extractHandle(channel) {
  if (!channel) return null;
  const h = String(channel).trim();
  if (h.startsWith('http')) {
    const m = h.match(/youtube\.com\/@([\w.-]+)/) || h.match(/youtube\.com\/channel\/(UC[\w-]{22})/);
    return m ? m[1] : null;
  }
  return h.startsWith('@') ? h.slice(1) : h;
}

async function fetchText(url) {
  const r = await fetch(url, { headers: UA, redirect: 'follow' });
  if (!r.ok) throw new Error('YouTube request failed (' + r.status + ')');
  return r.text();
}

async function fetchJson(url) {
  const r = await fetch(url, { headers: UA, redirect: 'follow' });
  if (!r.ok) throw new Error('YouTube API request failed (' + r.status + ')');
  return r.json();
}

function parseRss(xml) {
  const out = [];
  const entryRe = /<entry>([\s\S]*?)<\/entry>/g;
  let m;
  while ((m = entryRe.exec(xml))) {
    const e = m[1];
    const idm = e.match(/<yt:videoId>([\w-]{6,})<\/yt:videoId>/) || e.match(/yt:video:([\w-]{6,})/);
    const title = decodeXml((e.match(/<title>([\s\S]*?)<\/title>/) || [])[1]);
    const pub = (e.match(/<published>([\s\S]*?)<\/published>/) || [])[1];
    const thumb = (e.match(/<media:thumbnail url="([^"]*)"/) || [])[1];
    const vid = (idm && idm[1]) || (thumb && thumb.match(/\/vi\/([\w-]+)\//))?.[1];
    if (!vid) continue;
    out.push({
      id: vid,
      title: title || 'Untitled',
      published: pub || '',
      thumb: thumb || 'https://i.ytimg.com/vi/' + vid + '/hqdefault.jpg',
      views: null,
      likes: null
    });
  }
  return out;
}

async function resolveFromApi(handle, apiKey) {
  const data = await fetchJson('https://www.googleapis.com/youtube/v3/channels?part=snippet,statistics,contentDetails&forHandle=' + encodeURIComponent(handle) + '&key=' + encodeURIComponent(apiKey));
  const it = data.items && data.items[0];
  if (!it) throw new Error('Channel not found');
  const st = it.statistics || {};
  return {
    id: it.id,
    title: it.snippet && it.snippet.title,
    thumb: it.snippet && it.snippet.thumbnails && (it.snippet.thumbnails.high || it.snippet.thumbnails.medium || it.snippet.thumbnails.default || {}).url,
    handle,
    subscribers: Number(st.subscriberCount) || 0,
    views: Number(st.viewCount) || 0,
    likes: Number(st.likeCount) || null,
    videoCount: Number(st.videoCount) || 0,
    uploadsPlaylist: it.contentDetails && it.contentDetails.relatedPlaylists && it.contentDetails.relatedPlaylists.uploads
  };
}

async function resolveFromScrape(handle) {
  const html = await fetchText('https://www.youtube.com/@' + handle);
  let id = (html.match(/"browseId":"(UC[\w-]{22})"/) || [])[1] || (html.match(/youtube\.com\/channel\/(UC[\w-]{22})/) || [])[1];
  if (!id) throw new Error('Could not resolve channel id');
  const subsMatch = html.match(/([\d.,]+[KMB]?)\s*(?:subscribers|Subscribers)/);
  let subs = parseNumber(subsMatch && subsMatch[1]);
  if (subs == null) subs = 0;
  let views = null;
  const viewsRe = /([\d.,]+[KMB]?)\s*(?:views|Views)/g;
  const viewsNums = [];
  let vm;
  while ((vm = viewsRe.exec(html))) viewsNums.push(parseNumber(vm[1]));
  if (viewsNums.length) views = viewsNums[viewsNums.length - 1];
  const avm = html.match(/"avatar":\{"thumbnails":\[\{"url":"(https:\\?\\?\/\\?\/?yt3[^"]+)"/);
  let avatar = avm ? avm[1].replace(/\\u002F/g, '/').replace(/\\\//g, '/') : '';
  if (!avatar) {
    const y3 = html.match(/https:\/\/yt3\.googleusercontent\.com\/[\w-]+=s\d+/);
    avatar = y3 ? y3[0] : '';
  }
  return { id, handle, subscribers: subs, views, thumb: avatar };
}

async function apiVideos(uploadsPlaylist, apiKey) {
  const out = [];
  let token = '';
  do {
    const url = 'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=' + uploadsPlaylist + '&maxResults=50' + (token ? '&pageToken=' + token : '') + '&key=' + apiKey;
    const data = await fetchJson(url);
    const items = data.items || [];
    const ids = items.map((i) => i.snippet && i.snippet.resourceId && i.snippet.resourceId.videoId).filter(Boolean);
    if (ids.length) {
      const stats = await fetchJson('https://www.googleapis.com/youtube/v3/videos?part=snippet,statistics&id=' + ids.join(',') + '&key=' + apiKey);
      const statMap = {};
      for (const v of (stats.items || [])) {
        const st = v.statistics || {};
        statMap[v.id] = { views: Number(st.viewCount) || 0, likes: Number(st.likeCount) || 0 };
      }
      for (const i of items) {
        const vid = i.snippet && i.snippet.resourceId && i.snippet.resourceId.videoId;
        if (!vid) continue;
        const sn = i.snippet;
        out.push({
          id: vid,
          title: sn.title,
          published: sn.publishedAt || '',
          thumb: (sn.thumbnails && (sn.thumbnails.high || sn.thumbnails.medium || sn.thumbnails.default || {}).url) || ('https://i.ytimg.com/vi/' + vid + '/hqdefault.jpg'),
          views: (statMap[vid] && statMap[vid].views) || 0,
          likes: (statMap[vid] && statMap[vid].likes) || 0
        });
      }
    }
    token = data.nextPageToken || '';
  } while (token && out.length < 300);
  return out;
}

async function getYouTubeData(settings) {
  const channelInput = settings.youtube_channel;
  const apiKey = settings.youtube_api_key;
  if (!channelInput) return null;

  const cacheKey = (apiKey || 'nokey') + '::' + channelInput;
  const hit = cacheGet(cacheKey);
  if (hit) return hit;

  const handle = extractHandle(channelInput);
  let channel, videos;

  if (apiKey) {
    channel = await resolveFromApi(handle, apiKey);
    videos = channel.uploadsPlaylist ? await apiVideos(channel.uploadsPlaylist, apiKey) : [];
  } else {
    channel = await resolveFromScrape(handle);
    let xml = '';
    try {
      xml = await fetchText('https://www.youtube.com/feeds/videos.xml?channel_id=' + channel.id);
    } catch (e) {
      xml = '';
    }
    videos = xml ? parseRss(xml) : [];
    channel.title = (xml.match(/<title>([\s\S]*?)<\/title>/) || [])[1] ? decodeXml((xml.match(/<title>([\s\S]*?)<\/title>/) || [])[1]) : ('@' + handle);
    if (!channel.thumb) channel.thumb = '';
  }

  const result = { channel, videos };
  cacheSet(cacheKey, result);
  return result;
}

module.exports = { getYouTubeData, parseNumber, fmt, decodeXml };
