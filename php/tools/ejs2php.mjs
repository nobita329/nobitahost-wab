#!/usr/bin/env node
/**
 * ejs2php — converts the Node build's EJS templates into PHP templates.
 *
 *   node php/tools/ejs2php.mjs views/pages/user/home.ejs php/views/pages/user/home.php
 *   node php/tools/ejs2php.mjs --all            convert every page
 *   node php/tools/ejs2php.mjs --dry <file>     print to stdout
 *
 * It is a mechanical port helper: tags, includes, member access, loops,
 * JS string/array methods and `typeof x !== 'undefined'` guards are rewritten
 * to PHP. Anything it cannot prove is marked with `/* EJS2PHP:? *\/` so the
 * result can be reviewed by hand.
 */
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '../..');
const VIEWS = path.join(ROOT, 'views');
const OUT_VIEWS = path.join(ROOT, 'php/views');

const PHP_TYPES = new Set(['string', 'int', 'integer', 'float', 'double', 'bool', 'boolean', 'array', 'object', 'mixed', 'iterable', 'void', 'callable', 'self', 'parent']);
const JS_GLOBALS = new Set(['Math', 'JSON', 'Date', 'String', 'Number', 'Boolean', 'Object', 'Array', 'parseInt', 'parseFloat', 'encodeURIComponent', 'decodeURIComponent', 'isNaN', 'console', 'window', 'document', 'navigator', 'location', 'undefined', 'NaN', 'Infinity']);

const S_OPEN = '\uE000';
const S_CLOSE = '\uE001';

const PHP_WORDS = new Set([
  'if', 'else', 'elseif', 'endif', 'foreach', 'endforeach', 'for', 'endfor', 'while', 'do',
  'switch', 'case', 'default', 'break', 'continue', 'return', 'echo', 'print', 'function', 'fn',
  'as', 'true', 'false', 'null', 'and', 'or', 'not', 'xor', 'isset', 'empty', 'unset', 'array',
  'new', 'class', 'extends', 'implements', 'static', 'public', 'private', 'protected', 'const',
  'use', 'instanceof', 'try', 'catch', 'finally', 'throw', 'match', 'require', 'include',
  'require_once', 'include_once', 'exit', 'die', 'list', 'clone', 'yield', 'global',
]);

const PHP_FUNCS = new Set([
  'e', 'v', 'nh_count', 'nh_slice', 'nh_replace', 'nh_join', 'nh_arr', 'nh_locale_date', 'nh_or',
  'nh_pct', 'count', 'trim', 'ltrim', 'rtrim', 'max', 'min', 'round', 'floor', 'ceil', 'abs',
  'intval', 'floatval', 'strval', 'implode', 'explode', 'substr', 'mb_substr', 'mb_strlen',
  'strlen', 'strtolower', 'strtoupper', 'ucfirst', 'ucwords', 'str_replace', 'preg_replace',
  'strpos', 'str_contains', 'str_starts_with', 'str_ends_with', 'json_encode', 'json_decode',
  'rawurlencode', 'urlencode', 'htmlspecialchars', 'number_format', 'date', 'time', 'strtotime',
  'array_map', 'array_filter', 'array_values', 'array_slice', 'array_reverse', 'array_keys',
  'array_merge', 'in_array', 'is_array', 'is_string', 'is_numeric', 'isset', 'empty', 'partial',
  'section', 'section_start', 'section_end', 'icon', 'csrf_field', 'csrf_token', 'setting',
  'setting_on', 'asset', 'url', 'is_admin', 'current_user', 'flash', 'timeago', 'fmt_date',
  'fmt_bytes', 'excerpt', 'str_slug', 'initials', 'avatar_of', 'safe_back', 'json_attr',
  'Markdown', 'Settings', 'System', 'DB', 'Nav', 'Auth', 'Totp', 'View', 'Response', 'Request',
  'md', 'nl2br', 'strip_tags', 'basename', 'dirname', 'file_get_contents', 'range', 'sort',
  'usort', 'shuffle', 'array_sum', 'array_unique', 'implode', 'sprintf', 'vsprintf', 'print_r',
  'NH_ROOT', 'PHP_EOL', 'E_ALL', 'stdClass',
]);

/* ------------------------------------------------------------------ tokens */

function tokenize(src) {
  const parts = [];
  const re = /<%(-|=|#)?([\s\S]*?)%>/g;
  let last = 0;
  let m;
  while ((m = re.exec(src)) !== null) {
    if (m.index > last) parts.push({ type: 'text', value: src.slice(last, m.index) });
    const kind = m[1] === '=' ? 'echo' : m[1] === '-' ? 'raw' : m[1] === '#' ? 'comment' : 'code';
    parts.push({ type: kind, value: m[2] });
    last = re.lastIndex;
  }
  if (last < src.length) parts.push({ type: 'text', value: src.slice(last) });
  return parts;
}

function protectStrings(code) {
  const strings = [];
  const push = (m) => { strings.push(m); return S_OPEN + (strings.length - 1) + S_CLOSE; };
  // 1. regex literals (not division): preceded by ( , = : [ ! & | ? { ; or start
  let out = code.replace(/(^|[({\[,.=:;!&|?+\-]\s*)(\/(?:\\.|[^\/\\\n])+\/[gimsuy]*)/g,
    (_, pre, rx) => pre + push("'" + rx + "'"));
  // 2. x.match(/re/)[1]  →  nh_match_group(x, '/re/', 1)
  out = out.replace(new RegExp(`([\\w$.\\->\\[\\]'${S_OPEN}-${S_CLOSE}]+)\\.match\\((${STR})\\)\\[(\\d+)\\]`, 'g'),
    (_, target, rx, idx) => `nh_match_group(${target}, ${rx}, ${idx})`);
  // 3. /re/.test(x)  →  (bool) preg_match('/re/', x)
  out = out.replace(new RegExp(`(${STR})\\.test\\(([^)]*)\\)`, 'g'),
    (_, rx, target) => `(bool) preg_match(${rx}, (string) ${target})`);
  // 4. x.test(...) leftover
  // 5. normal strings
  out = out.replace(/'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"|`(?:\\.|[^`\\])*`/g, push);
  return { code: out, strings };
}

function restoreStrings(code, strings) {
  return code.replace(new RegExp(S_OPEN + '(\\d+)' + S_CLOSE, 'g'), (_, i) => strings[Number(i)]);
}

const STR = `${S_OPEN}\\d+${S_CLOSE}`;
const EXPR = `(?:\\((?:[^()]|\\([^()]*\\))*\\)|\\[(?:[^\\[\\]]|\\[[^\\[\\]]*\\])*\\]|\\{[^{}]*\\}|[\\w$.\\->'${S_OPEN}-${S_CLOSE}])+`;

/* ------------------------------------------------------- JS → PHP rewrite */

function looksLikeObjectLiteral(body) {
  const b = body.trim();
  if (!b || b.includes(';') || b.includes('=>') || /\bfunction\b/.test(b)) return false;
  // split on top-level commas
  const segs = [];
  let depth = 0;
  let cur = '';
  for (const ch of b) {
    if ('([{'.includes(ch)) depth++;
    if (')]}'.includes(ch)) depth--;
    if (ch === ',' && depth === 0) { segs.push(cur); cur = ''; } else cur += ch;
  }
  segs.push(cur);
  return segs.every((s) => /^\s*[\w$]+\s*:/.test(s));
}

function jsToPhp(rawCode, notes) {
  let s = rawCode;

  /* ---- phase 0: constructs that need to see the real string literals ---- */
  s = s.replace(/typeof\s+([\w$.]+)\s*===?\s*'undefined'/g, (_, n) => `!isset(${n})`);
  s = s.replace(/typeof\s+([\w$.]+)\s*!==?\s*'undefined'/g, (_, n) => `isset(${n})`);
  s = s.replace(/typeof\s+([\w$.]+)\s*!==?\s*"undefined"/g, (_, n) => `isset(${n})`);
  s = s.replace(/typeof\s+([\w$.]+)/g, (_, n) => `gettype(${n})`);
  s = s.replace(/\bnew Date\(\)\.getFullYear\(\)/g, "date('Y')");
  s = s.replace(/\bnew Date\(\)/g, "time()");

  /* ---- phase 1: protect string literals ---- */
  const { code: protectedCode, strings } = protectStrings(s);
  s = protectedCode;

  /* ---- phase 2: operators that must settle before expression matching ---- */
  s = s.replace(/\|\|/g, '?:');
  s = s.replace(new RegExp(`(${STR})\\s*\\+`, 'g'), '$1 .');
  s = s.replace(new RegExp(`\\+\\s*(${STR})`, 'g'), '. $1');

  /* ---- phase 5b: chained map(...).join(...) ---- */
  s = s.replace(new RegExp(`(${EXPR})\\.map\\(\\s*function\\s*\\(\\s*([\\w$]+)\\s*\\)\\s*\\{\\s*return\\s+([\\s\\S]+?);?\\s*\\}\\s*\\)\\.join\\(([^)]*)\\)`, 'g'),
    (_, arr, v, expr, glue) => `nh_join(array_map(fn($${v}) => ${expr.trim()}, (array) ${arr}), ${glue})`);
  s = s.replace(new RegExp(`(${EXPR})\\.map\\(\\s*function\\s*\\(\\s*([\\w$]+)\\s*\\)\\s*\\{\\s*return\\s+([\\s\\S]+?);?\\s*\\}\\s*\\)\\.join\\(\\)`, 'g'),
    (_, arr, v, expr) => `nh_join(array_map(fn($${v}) => ${expr.trim()}, (array) ${arr}))`);

  /* ---- phase 3: loops / functional helpers ---- */
  s = s.replace(new RegExp(`(${EXPR})\\.forEach\\(\\s*function\\s*\\(\\s*([\\w$]+)\\s*,\\s*([\\w$]+)\\s*\\)\\s*\\{`, 'g'),
    (_, arr, v, i) => `foreach (array_values(${arr}) as $${i} => $${v}) {`);
  s = s.replace(new RegExp(`(${EXPR})\\.forEach\\(\\s*function\\s*\\(\\s*([\\w$]+)\\s*\\)\\s*\\{`, 'g'),
    (_, arr, v) => `foreach (${arr} as $${v}) {`);
  s = s.replace(new RegExp(`(${EXPR})\\.forEach\\(\\s*\\(?\\s*([\\w$]+)\\s*\\)?\\s*=>\\s*\\{`, 'g'),
    (_, arr, v) => `foreach (${arr} as $${v}) {`);
  s = s.replace(new RegExp(`(${EXPR})\\.map\\(\\s*function\\s*\\(\\s*([\\w$]+)\\s*(?:,\\s*([\\w$]+)\\s*)?\\)\\s*\\{\\s*return\\s+([\\s\\S]+?);?\\s*\\}\\s*\\)`, 'g'),
    (_, arr, v, i, expr) => (i
      ? `array_map(fn($${v}, $${i}) => ${expr.trim()}, array_values(${arr}), array_keys(${arr}))`
      : `array_map(fn($${v}) => ${expr.trim()}, (array) ${arr})`));
  s = s.replace(new RegExp(`(${EXPR})\\.filter\\(\\s*function\\s*\\(\\s*([\\w$]+)\\s*\\)\\s*\\{\\s*return\\s+([\\s\\S]+?);?\\s*\\}\\s*\\)`, 'g'),
    (_, arr, v, expr) => `array_values(array_filter((array) ${arr}, fn($${v}) => ${expr.trim()}))`);
  s = s.replace(new RegExp(`(${EXPR})\\.sort\\(\\s*function\\s*\\(\\s*([\\w$]+)\\s*,\\s*([\\w$]+)\\s*\\)\\s*\\{\\s*return\\s+([\\s\\S]+?);?\\s*\\}\\s*\\)`, 'g'),
    (_, arr, a, b, expr) => `(function () use (&${arr}) { usort(${arr}, fn($${a}, $${b}) => ${expr.trim()}); return ${arr}; })()`);
  s = s.replace(new RegExp(`(${EXPR})\\.reduce\\(`, 'g'), (_, arr) => { notes.push('reduce() needs a manual PHP rewrite'); return `${arr} /* EJS2PHP:? reduce */ (`; });

  /* ---- phase 3b: comma separated declarations ---- */
  s = s.replace(/\b(?:var|let|const)\s+([^;=]+=[^;]*?)(?=\s*(?:;|$))/g, (m, body) => {
    if (!/,/.test(body)) return m;
    // split on top-level commas
    const parts = [];
    let depth = 0, cur = '';
    for (const ch of body) {
      if ('([{'.includes(ch)) depth++;
      if (')]}'.includes(ch)) depth--;
      if (ch === ',' && depth === 0) { parts.push(cur); cur = ''; } else cur += ch;
    }
    parts.push(cur);
    if (parts.some((x) => !x.includes('='))) return m;
    return parts.map((x) => 'var ' + x.trim()).join('; ');
  });

  /* ---- phase 4: declarations ---- */
  s = s.replace(/\b(?:var|let|const)\s+([\w$]+)\s*=/g, (_, n) => `$${n} =`);
  s = s.replace(/\b(?:var|let|const)\s+([\w$]+)/g, (_, n) => `$${n}`);

  /* ---- phase 5: JS globals ---- */
  s = s.replace(/\bJSON\.stringify\(/g, 'json_encode(');
  s = s.replace(/\bJSON\.parse\(([^)]*)\)/g, 'json_decode($1, true)');
  s = s.replace(/\bMath\.(max|min|round|floor|ceil|abs|pow)\(/g, '$1(');
  s = s.replace(/\bparseInt\(([^,)]+)(?:,\s*\d+)?\)/g, '(int) ($1)');
  s = s.replace(/\bparseFloat\(([^)]+)\)/g, '(float) ($1)');
  s = s.replace(/\bNumber\(([^)]+)\)/g, '(float) ($1)');
  s = s.replace(/\bString\(([^)]*)\)/g, '(string) ($1)');
  s = s.replace(/\bBoolean\(([^)]+)\)/g, '(bool) ($1)');
  s = s.replace(/\bArray\.isArray\(([^)]+)\)/g, 'is_array($1)');
  s = s.replace(/\bencodeURIComponent\(/g, 'rawurlencode(');
  s = s.replace(/\bdecodeURIComponent\(/g, 'rawurldecode(');
  s = s.replace(/\bObject\.keys\(([^)]+)\)/g, 'array_keys((array) $1)');
  s = s.replace(/\bObject\.values\(([^)]+)\)/g, 'array_values((array) $1)');
  s = s.replace(/\bObject\.entries\(([^)]+)\)/g, '((array) $1)');
  s = s.replace(/\bisNaN\(([^)]+)\)/g, '!is_numeric($1)');
  s = s.replace(/\bundefined\b/g, 'null');

  /* ---- phase 6: string / array methods ---- */
  s = s.replace(new RegExp(`(${EXPR})\\.length\\b`, 'g'), 'nh_count($1)');
  s = s.replace(new RegExp(`(${EXPR})\\.trim\\(\\)`, 'g'), 'trim((string) $1)');
  s = s.replace(new RegExp(`(${EXPR})\\.slice\\(([^()]*(?:\\([^()]*\\))?[^()]*)\\)`, 'g'), 'nh_slice($1, $2)');
  s = s.replace(new RegExp(`(${EXPR})\\.substring\\(([^)]*)\\)`, 'g'), 'nh_slice($1, $2)');
  s = s.replace(new RegExp(`(${EXPR})\\.substr\\(([^)]*)\\)`, 'g'), 'nh_slice($1, $2)');
  s = s.replace(new RegExp(`(${EXPR})\\.replace\\(([^()]*(?:\\([^()]*\\))?[^()]*)\\)`, 'g'), 'nh_replace($1, $2)');
  s = s.replace(new RegExp(`(${EXPR})\\.join\\(([^)]*)\\)`, 'g'), 'nh_join($1, $2)');
  s = s.replace(new RegExp(`(${EXPR})\\.join\\(\\)`, 'g'), 'nh_join($1)');
  s = s.replace(new RegExp(`(${EXPR})\\.toLowerCase\\(\\)`, 'g'), 'strtolower((string) $1)');
  s = s.replace(new RegExp(`(${EXPR})\\.toUpperCase\\(\\)`, 'g'), 'strtoupper((string) $1)');
  s = s.replace(new RegExp(`(${EXPR})\\.toFixed\\(([^)]*)\\)`, 'g'), 'number_format((float) $1, $2)');
  s = s.replace(new RegExp(`(${EXPR})\\.split\\(([^)]*)\\)`, 'g'), 'explode($2, (string) $1)');
  s = s.replace(new RegExp(`(${EXPR})\\.charAt\\(([^)]*)\\)`, 'g'), 'mb_substr((string) $1, $2, 1)');
  s = s.replace(new RegExp(`(${EXPR})\\.indexOf\\(([^)]*)\\)`, 'g'), '(int) strpos((string) $1, $2)');
  s = s.replace(new RegExp(`(${EXPR})\\.includes\\(([^)]*)\\)`, 'g'), '(is_array($1) ? in_array($2, (array) $1, true) : str_contains((string) $1, (string) $2))');
  s = s.replace(new RegExp(`(${EXPR})\\.reverse\\(\\)`, 'g'), 'array_reverse((array) $1)');
  s = s.replace(new RegExp(`(${EXPR})\\.concat\\(([^)]*)\\)`, 'g'), 'array_merge((array) $1, (array) $2)');
  s = s.replace(new RegExp(`(${EXPR})\\.toLocaleDateString\\([^)]*\\)`, 'g'), 'nh_locale_date($1)');
  s = s.replace(new RegExp(`(${EXPR})\\.toISOString\\(\\)`, 'g'), 'gmdate("c", strtotime((string) $1))');
  s = s.replace(new RegExp(`(${EXPR})\\.getTime\\(\\)`, 'g'), '(strtotime((string) $1) * 1000)');
  s = s.replace(new RegExp(`(${EXPR})\\.push\\(([^)]*)\\)`, 'g'), '$1[] = $2');
  s = s.replace(/\bnew Date\(([^)]*)\)/g, 'nh_locale_date($1)');

  /* ---- phase 7: object literals → php arrays ---- */
  s = s.replace(/\{([^{}]*)\}/g, (m, body) => (looksLikeObjectLiteral(body)
    ? '[' + body.replace(/([\w$]+)\s*:/g, (_, k) => `'${k}' =>`) + ']'
    : m));

  /* ---- phase 8: member access chains → $a->b ---- */
  s = s.replace(new RegExp(`(?<![\\w$>'"${S_CLOSE}])([a-zA-Z_$][\\w$]*)((?:\\.[a-zA-Z_$][\\w$]*)+)`, 'g'), (m, head, chain) => {
    if (PHP_WORDS.has(head) || PHP_FUNCS.has(head) || JS_GLOBALS.has(head)) return m;
    const props = chain.split('.').slice(1);
    return `$${head}->${props.join('->')}`;
  });

  /* ---- phase 9: bare identifiers → $vars ---- */
  s = s.replace(new RegExp(`(?<![\\w$.>'"${S_CLOSE}\\\\-])([a-zA-Z_][\\w$]*)(?![\\w$])(?!\\s*\\()(?!\\s*=>)`, 'g'),
    (m, name) => {
      if (PHP_WORDS.has(name) || PHP_FUNCS.has(name) || PHP_TYPES.has(name) || JS_GLOBALS.has(name)) return m;
      return `$${name}`;
    });

  /* ---- phase 10: closing braces of converted loops ---- */
  if (/^\s*\}\s*\)\s*;?\s*$/.test(s)) s = '}';
  s = s.replace(/\}\s*\)\s*;/g, '};');

  /* ---- phase 11: leftovers ---- */
  const leftovers = {
    '.map(': 'map()', '.filter(': 'filter()', '.join(': 'join()', '.slice(': 'slice()',
    '.replace(': 'replace()', '.length': 'length', '.forEach(': 'forEach()', '.split(': 'split()',
    '.indexOf(': 'indexOf()', '.includes(': 'includes()', '.push(': 'push()', '.trim()': 'trim()',
    '.sort(': 'sort()', '.reduce(': 'reduce()', 'typeof ': 'typeof', 'JSON.': 'JSON', 'Math.': 'Math',
    'new Date': 'new Date', '?.': 'optional chaining',
  };
  for (const [needle, label] of Object.entries(leftovers)) {
    if (s.includes(needle)) notes.push('unconverted ' + label);
  }

  if (/function\s*\(/.test(s)) {
    s = s.replace(/function\s*\(([^)]*)\)\s*\{/g, (_, args) => {
      const a = args.split(',').map((x) => x.trim()).filter(Boolean).map((x) => (x.startsWith('$') ? x : `$${x}`));
      return `function (${a.join(', ')}) {`;
    });
    notes.push('anonymous function rewritten — check `use (...)` captures');
  }
  if (/(?<!\w)=>/.test(s) && !/\bfn\s*\(/.test(s)) notes.push('arrow function left as-is');

  s = restoreStrings(s, strings);
  // string concatenation: 'a' + x  →  'a' . x
  s = s.replace(/('(?:[^'\\]|\\.)*')\s*\+(?!=)/g, '$1 .');
  s = s.replace(/(?<![<>=!+\-])\+\s*('(?:[^'\\]|\\.)*')/g, '. $1');
  return s;
}

/* ------------------------------------------------------------- conversion */

const LAYOUT_PARTIALS = new Set(['partials/head', 'partials/auth-head', 'partials/cf-head', 'partials/footer', 'partials/sidebar', 'partials/topbar']);

function convert(srcFile, relFromViews) {
  const src = fs.readFileSync(srcFile, 'utf8');
  const parts = tokenize(src);
  const notes = [];
  let out = '';
  const dir = path.dirname(relFromViews);

  for (const part of parts) {
    if (part.type === 'text') { out += part.value; continue; }
    if (part.type === 'comment') { continue; }

    const code = part.value;

    // includes
    const inc = code.match(/^\s*include\(\s*'([^']+)'\s*(?:,\s*([\s\S]*?))?\s*\)\s*$/);
    if (part.type === 'raw' && inc) {
      const resolved = path.normalize(path.join(dir, inc[1])).replace(/\\/g, '/');
      if (LAYOUT_PARTIALS.has(resolved)) {
        out += `<?php /* EJS2PHP: layout include '${resolved}' handled by the PHP layout */ ?>\n`;
        continue;
      }
      const data = inc[2] ? jsToPhp(inc[2], notes) : '';
      out += data
        ? `<?php partial('${resolved}', ${wrapData(data)}); ?>`
        : `<?php partial('${resolved}'); ?>`;
      continue;
    }

    const php = jsToPhp(code, notes);

    if (part.type === 'echo') {
      out += `<?= e(${php.trim()}) ?>`;
    } else if (part.type === 'raw') {
      out += `<?= ${php.trim()} ?>`;
    } else {
      const body = php.trim();
      out += body === '' ? '' : `<?php ${body} ?>`;
    }
  }

  const header = `<?php\n/**\n * Ported from views/${relFromViews} (EJS → PHP) by php/tools/ejs2php.mjs.\n * Layout + shell come from views/layouts/app.php (CasaOS UI).\n */\n?>\n`;
  return header + out + (notes.length ? `\n<!-- EJS2PHP notes: ${[...new Set(notes)].join('; ')} -->\n` : '');
}

function wrapData(expr) {
  const t = expr.trim();
  if (t.startsWith('[') || t.startsWith('array(')) return t;
  return t;
}

/* -------------------------------------------------------------------- cli */

const args = process.argv.slice(2);
const dry = args.includes('--dry');
const all = args.includes('--all');

function relToViews(p) {
  return path.relative(VIEWS, path.resolve(p)).replace(/\\/g, '/');
}

const SKIP = [
  'pages/auth/', 'pages/errors/', 'pages/casaos/',
  'partials/head.ejs', 'partials/auth-head.ejs', 'partials/cf-head.ejs',
  'partials/footer.ejs', 'partials/sidebar.ejs', 'partials/topbar.ejs',
];

let files = [];
if (all) {
  const walk = (d) => fs.readdirSync(d, { withFileTypes: true }).forEach((e) => {
    const full = path.join(d, e.name);
    if (e.isDirectory()) walk(full);
    else if (e.name.endsWith('.ejs')) files.push(full);
  });
  walk(VIEWS);
  files = files.filter((f) => !SKIP.some((sk) => relToViews(f).startsWith(sk) || relToViews(f) === sk));
} else {
  files = args.filter((a) => !a.startsWith('--')).map((a) => path.resolve(a));
}

for (const file of files) {
  const rel = relToViews(file);
  const php = convert(file, rel);
  const target = path.join(OUT_VIEWS, rel.replace(/\.ejs$/, '.php'));
  if (dry) {
    process.stdout.write(php);
  } else {
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.writeFileSync(target, php);
    console.log(`✔ ${rel} → ${path.relative(ROOT, target)}`);
  }
}
