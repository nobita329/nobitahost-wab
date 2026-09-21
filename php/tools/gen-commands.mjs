import fs from 'node:fs';
const conv = fs.readFileSync('php/tools/ejs2php.mjs','utf8').split('/* -------------------------------------------------------------------- cli */')[0]
  + '\nexport { jsToPhp };\n';
fs.writeFileSync('/tmp/conv-lib.mjs', conv);
const { jsToPhp } = await import('/tmp/conv-lib.mjs');

const src = fs.readFileSync('src/commands.js','utf8').split('\n');
const seg = (a,b) => src.slice(a-1,b).join('\n');

/* ---- protect JS single/double-quoted strings so the converter never sees their text ---- */
const O = '\uF000', C = '\uF001';
function protect(js) {
  const table = [];
  let out = '', i = 0;
  while (i < js.length) {
    const ch = js[i];
    if (ch === "'" || ch === '"') {
      let j = i + 1, body = '';
      while (j < js.length) {
        if (js[j] === '\\') { body += js[j] + (js[j+1] ?? ''); j += 2; continue; }
        if (js[j] === ch) break;
        body += js[j]; j++;
      }
      table.push(body);
      out += O + (table.length - 1) + C;
      i = j + 1;
      continue;
    }
    out += ch; i++;
  }
  return { code: out, table };
}
function jsDecode(body) {
  const map = { n: '\n', t: '\t', r: '\r', b: '\b', f: '\f', v: '\v', '0': '\0', '\\': '\\', "'": "'", '"': '"', '`': '`' };
  let out = '', i = 0;
  while (i < body.length) {
    if (body[i] === '\\' && i + 1 < body.length) {
      const n = body[i + 1];
      if (n === 'u') { out += String.fromCharCode(parseInt(body.substr(i + 2, 4), 16)); i += 6; continue; }
      if (n === 'x') { out += String.fromCharCode(parseInt(body.substr(i + 2, 2), 16)); i += 4; continue; }
      out += (n in map) ? map[n] : n;
      i += 2; continue;
    }
    out += body[i]; i++;
  }
  return out;
}
function phpStr(jsBody) {
  const s = jsDecode(jsBody);
  return "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}
function restore(code, table) {
  return code.replace(new RegExp(O + '(\\d+)' + C, 'g'), (_, n) => phpStr(table[Number(n)]));
}

const notes = [];

/* Printable ASCII in ICU primary collation order, case-collapsed (derived from Node's
   localeCompare once) so the PHP list sorts exactly like the JS list. */
const PRIMARY = ' _-,;:!?.\'"()[]{}@*/\\&#%`^+<=>|~$0123456789abcdefghijklmnopqrstuvwxyz';
const phpSingle = (s) => "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";

function conv2(js, fixups = []) {
  const { code, table } = protect(js);
  let out = code.replace(/\bV\(/g, '$V(');
  for (const [re, rep] of fixups) out = out.replace(re, rep);
  out = jsToPhp(out, notes);
  out = restore(out, table);
  out = out.replace(/gmdate\("c", strtotime\(\(string\) time\(\)\)\)/g, "gmdate('Y-m-d\\TH:i:s.v\\Z')");
  out = out.replace(/' \+ /g, "' . ").replace(/ \+ '/g, " . '");
  // tidy: comments keep their text (converter adds $ to bare words), trailing spaces go
  return out
    .split('\n')
    .map((l) => (/^\s*\/\//.test(l) ? l.replace(/\$(?=[A-Za-z_])/g, '') : l))
    .map((l) => l.replace(/\s+$/, ''))
    .join('\n')
    .trim();
}

const curated = conv2(seg(52, 546));
const varsBlk = conv2(seg(552, 566), [
  [/services\.filter\(\(s, i\) => services\.indexOf\(s\) === i\)/, 'array_values(array_unique(services));'],
]);
const tpls = conv2(seg(568, 715));

const ind = (block, pad) => block.split('\n').map(l => (l.trim() ? pad + l.trim() : '')).join('\n');

const php = `<?php
/**
 * Command library - PHP port of src/commands.js
 * Builds the same curated + templated list of ${'{'}cmd, desc, cat${'}'} entries.
 */
final class Commands
{
    public const CATEGORIES = [
        'useful'     => 'Useful',
        'linux'      => 'Linux',
        'vps'        => 'VPS',
        'docker'     => 'Docker',
        'git'        => 'Git',
        'networking' => 'Networking',
        'system'     => 'System',
        'auto'       => 'Auto',
        'review'     => 'Review',
        'suggestion' => 'Suggestion',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;

        $list = [];
        $seen = [];
        $add = function (string $cmd, string $desc, string $cat) use (&$list, &$seen): void {
            $cmd = trim((string) preg_replace('/\\s+/', ' ', $cmd));
            if ($cmd === '' || in_array($cmd, $seen, true)) return;
            $seen[] = $cmd;
            $list[] = ['cmd' => $cmd, 'desc' => $desc !== '' ? $desc : '', 'cat' => $cat];
        };
        $addCurated = function (string $cmd, string $desc, string $cat) use ($add): void {
            $add($cmd, $desc, $cat);
            $add('sudo ' . $cmd, 'Run "' . $cmd . '" with root privileges', $cat);
            $add($cmd . ' --help', 'Show usage and options for "' . $cmd . '"', $cat);
        };

        $curated = [
${ind(curated, '            ')}
        ];
        foreach ($curated as $c) {
            $addCurated((string) ($c['c'] ?? ''), (string) ($c['d'] ?? ''), (string) ($c['t'] ?? 'useful'));
        }

        $V = fn(string $k, array $arr): array => array_map(fn($v) => ['k' => $k, 'v' => $v], array_values($arr));

${ind(varsBlk, '        ')}

        $templates = [
${ind(tpls, '            ')}
        ];
        self::expand($list, $templates);

        // decorate-sort-undecorate with an ICU-ish collation key (mirrors JS localeCompare)
        $keys = [];
        foreach ($list as $i => $c) {
            $keys[$i] = self::collKey((string) $c['cat']) . "\\x02" . self::collKey((string) $c['cmd']);
        }
        asort($keys, SORT_STRING);
        $sorted = [];
        foreach ($keys as $i => $k) $sorted[] = $list[$i];
        return self::$cache = $sorted;
    }

    public static function count(): int
    {
        return count(self::all());
    }

    /** Printable ASCII in ICU primary collation order, case-collapsed (generated - see php/tools/gen-commands.mjs). */
    private const COLL_PRIMARY = ${phpSingle(PRIMARY)};

    private static ?array $collMaps = null;

    /** @return array{0: array<string,string>, 1: array<string,string>} [primary weights, case weights] */
    private static function collMaps(): array
    {
        if (self::$collMaps === null) {
            $primary = [];
            $case = [];
            $order = self::COLL_PRIMARY;
            $len = strlen($order);
            for ($i = 0; $i < $len; $i++) {
                $c = $order[$i];
                $primary[$c] = chr(33 + $i);
                $case[$c] = '0';
                $upper = strtoupper($c);
                if ($upper !== $c) {
                    $primary[$upper] = chr(33 + $i);
                    $case[$upper] = '1';
                }
            }
            self::$collMaps = [$primary, $case];
        }
        return self::$collMaps;
    }

    /** Sort key mirroring JS localeCompare: ICU-ish primary weights, lowercase before uppercase. */
    private static function collKey(string $str): string
    {
        [$primary, $case] = self::collMaps();
        return strtr($str, $primary) . "\\x01" . strtr($str, $case);
    }

    /** @return array<string,int> command count per category */
    public static function categoryCounts(): array
    {
        $counts = [];
        foreach (self::CATEGORIES as $key => $label) $counts[$key] = 0;
        foreach (self::all() as $c) $counts[$c['cat']] = ($counts[$c['cat']] ?? 0) + 1;
        return $counts;
    }

    private static function expand(array &$list, array $templates): void
    {
        foreach ($templates as $t) {
            $combos = [[]];
            foreach (($t['vars'] ?? []) as $vals) {
                $next = [];
                foreach ($combos as $combo) {
                    foreach ($vals as $v) $next[] = array_merge($combo, [$v['k'] => $v['v']]);
                }
                $combos = $next;
            }
            foreach ($combos as $c) {
                $cmd = (string) $t['tpl'];
                $desc = (string) ($t['desc'] ?? '');
                foreach (array_keys($c) as $k) {
                    $cmd = str_replace('{' . $k . '}', (string) $c[$k], $cmd);
                    $desc = str_replace('{' . $k . '}', (string) $c[$k], $desc);
                }
                if ($cmd !== '') $list[] = ['cmd' => $cmd, 'desc' => $desc, 'cat' => (string) $t['cat']];
            }
        }
    }
}
`;
fs.writeFileSync('php/app/Core/Commands.php', php);
console.log('written', php.length, 'bytes');
console.log('notes:', [...new Set(notes)].join(' | ') || 'none');
console.log('leaked placeholders:', (php.match(/[\uE000\uE001\uF000\uF001]/g) || []).length);
