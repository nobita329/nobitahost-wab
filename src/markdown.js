function esc(s) {
  return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function slugify(s) {
  return String(s).toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'section';
}

function inline(s) {
  s = esc(s);
  return s
    .replace(/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g, (_, alt, src) => {
      const safe = /^(https?:\/\/|\/)/i.test(src) ? src : '';
      return safe ? `<img src="${esc(safe)}" alt="${esc(alt)}" loading="lazy" decoding="async">` : esc(alt);
    })
    .replace(/\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g, (_, text, href) => {
      if (!/^(https?:\/\/|\/|#|mailto:)/i.test(href)) return text;
      const ext = /^https?:\/\//i.test(href);
      return `<a href="${esc(href)}"${ext ? ' target="_blank" rel="noopener noreferrer"' : ''}>${text}</a>`;
    })
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*\*([^*]+)\*\*\*/g, '<b><i>$1</i></b>')
    .replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>')
    .replace(/(^|[\s(])\*([^*\n]+)\*/g, '$1<i>$2</i>')
    .replace(/(^|[\s(])_([^_\n]+)_/g, '$1<i>$2</i>')
    .replace(/~~([^~]+)~~/g, '<del>$1</del>')
    .replace(/ {2}\n/g, '<br>');
}

function render(md) {
  const lines = String(md == null ? '' : md).replace(/\r\n?/g, '\n').split('\n');
  const out = [];
  const toc = [];
  const used = {};
  let i = 0;
  let para = [];
  let list = null;
  let quote = null;

  const flushPara = () => { if (para.length) { out.push(`<p>${inline(para.join(' '))}</p>`); para = []; } };
  const flushList = () => { if (list) { out.push(`</${list.tag}>`); list = null; } };
  const flushQuote = () => { if (quote) { out.push(`<blockquote>${inline(quote.join(' '))}</blockquote>`); quote = null; } };
  const flushAll = () => { flushPara(); flushList(); flushQuote(); };

  while (i < lines.length) {
    const line = lines[i];

    const fence = line.match(/^```(\w*)\s*$/);
    if (fence) {
      flushAll();
      const buf = [];
      i++;
      while (i < lines.length && !/^```\s*$/.test(lines[i])) buf.push(lines[i++]);
      i++;
      out.push(`<pre tabindex="0"><code${fence[1] ? ` class="lang-${esc(fence[1])}"` : ''}>${esc(buf.join('\n'))}</code></pre>`);
      continue;
    }

    const h = line.match(/^(#{1,4})\s+(.*)$/);
    if (h) {
      flushAll();
      const level = h[1].length;
      const text = h[2].replace(/#+\s*$/, '').trim();
      const base = slugify(text);
      used[base] = (used[base] || 0) + 1;
      const id = used[base] > 1 ? `${base}-${used[base]}` : base;
      toc.push({ id, text, level });
      out.push(`<h${level} id="${id}">${inline(text)}</h${level}>`);
      i++;
      continue;
    }

    if (/^(-{3,}|\*{3,}|_{3,})\s*$/.test(line)) { flushAll(); out.push('<hr>'); i++; continue; }

    const ul = line.match(/^\s*[-*+]\s+(.*)$/);
    const ol = line.match(/^\s*\d+[.)]\s+(.*)$/);
    if (ul || ol) {
      flushPara(); flushQuote();
      const tag = ul ? 'ul' : 'ol';
      if (!list || list.tag !== tag) { flushList(); out.push(`<${tag}>`); list = { tag }; }
      out.push(`<li>${inline((ul || ol)[1])}</li>`);
      i++;
      continue;
    }
    flushList();

    const bq = line.match(/^>\s?(.*)$/);
    if (bq) { flushPara(); quote = quote || []; quote.push(bq[1]); i++; continue; }
    flushQuote();

    if (line.includes('|') && i + 1 < lines.length && /^\s*\|?[\s:|-]+\|[\s:|-]*$/.test(lines[i + 1])) {
      flushAll();
      const cells = (l) => l.replace(/^\s*\|/, '').replace(/\|\s*$/, '').split('|').map((c) => c.trim());
      const head = cells(line);
      i += 2;
      const rows = [];
      while (i < lines.length && lines[i].includes('|')) rows.push(cells(lines[i++]));
      let t = '<div class="md-table-wrap"><table><thead><tr>';
      head.forEach((c) => { t += `<th>${inline(c)}</th>`; });
      t += '</tr></thead><tbody>';
      rows.forEach((r) => {
        t += '<tr>';
        r.forEach((c) => { t += `<td>${inline(c)}</td>`; });
        t += '</tr>';
      });
      t += '</tbody></table></div>';
      out.push(t);
      continue;
    }

    if (/^\s*$/.test(line)) { flushAll(); i++; continue; }

    para.push(line.trim());
    i++;
  }
  flushAll();

  return { html: out.join('\n'), toc };
}

module.exports = { render, esc, slugify };
