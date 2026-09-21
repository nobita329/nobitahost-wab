<?php
/** Markdown → HTML (+ TOC). Faithful PHP port of src/markdown.js. */
final class Markdown
{
    public static function esc($s): string
    {
        return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        return $s === '' ? 'section' : $s;
    }

    public static function inline(string $s): string
    {
        $s = self::esc($s);
        $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', function ($m) {
            $safe = preg_match('#^(https?://|/)#i', $m[2]) ? $m[2] : '';
            return $safe !== ''
                ? '<img src="' . self::esc($safe) . '" alt="' . self::esc($m[1]) . '" loading="lazy" decoding="async">'
                : self::esc($m[1]);
        }, $s) ?? $s;

        $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', function ($m) {
            if (!preg_match('#^(https?://|/|#|mailto:)#i', $m[2])) return $m[1];
            $ext = (bool) preg_match('#^https?://#i', $m[2]);
            return '<a href="' . self::esc($m[2]) . '"' . ($ext ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . $m[1] . '</a>';
        }, $s) ?? $s;

        $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s) ?? $s;
        $s = preg_replace('/\*\*\*([^*]+)\*\*\*/', '<b><i>$1</i></b>', $s) ?? $s;
        $s = preg_replace('/\*\*([^*]+)\*\*/', '<b>$1</b>', $s) ?? $s;
        $s = preg_replace('/(^|[\s(])\*([^*\n]+)\*/', '$1<i>$2</i>', $s) ?? $s;
        $s = preg_replace('/(^|[\s(])_([^_\n]+)_/', '$1<i>$2</i>', $s) ?? $s;
        $s = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $s) ?? $s;
        $s = preg_replace('/ {2}\n/', '<br>', $s) ?? $s;
        return $s;
    }

    /** @return array{html:string, toc:array<int,array{id:string,text:string,level:int}>} */
    public static function render(?string $md): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", (string) $md));
        $out = [];
        $toc = [];
        $used = [];
        $i = 0;
        $para = [];
        $list = null;   // ['tag' => 'ul'|'ol']
        $quote = null;

        $flushPara = function () use (&$out, &$para) {
            if ($para) { $out[] = '<p>' . self::inline(implode(' ', $para)) . '</p>'; $para = []; }
        };
        $flushList = function () use (&$out, &$list) {
            if ($list) { $out[] = '</' . $list['tag'] . '>'; $list = null; }
        };
        $flushQuote = function () use (&$out, &$quote) {
            if ($quote) { $out[] = '<blockquote>' . self::inline(implode(' ', $quote)) . '</blockquote>'; $quote = null; }
        };
        $flushAll = function () use (&$flushPara, &$flushList, &$flushQuote) {
            $flushPara(); $flushList(); $flushQuote();
        };

        $n = count($lines);
        while ($i < $n) {
            $line = $lines[$i];

            if (preg_match('/^```(\w*)\s*$/', $line, $fence)) {
                $flushAll();
                $buf = [];
                $i++;
                while ($i < $n && !preg_match('/^```\s*$/', $lines[$i])) $buf[] = $lines[$i++];
                $i++;
                $lang = $fence[1] !== '' ? ' class="lang-' . self::esc($fence[1]) . '"' : '';
                $out[] = '<pre tabindex="0"><code' . $lang . '>' . self::esc(implode("\n", $buf)) . '</code></pre>';
                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.*)$/', $line, $h)) {
                $flushAll();
                $level = strlen($h[1]);
                $text = trim(preg_replace('/#+\s*$/', '', $h[2]) ?? '');
                $base = self::slugify($text);
                $used[$base] = ($used[$base] ?? 0) + 1;
                $id = $used[$base] > 1 ? $base . '-' . $used[$base] : $base;
                $toc[] = ['id' => $id, 'text' => $text, 'level' => $level];
                $out[] = "<h{$level} id=\"{$id}\">" . self::inline($text) . "</h{$level}>";
                $i++;
                continue;
            }

            if (preg_match('/^(-{3,}|\*{3,}|_{3,})\s*$/', $line)) { $flushAll(); $out[] = '<hr>'; $i++; continue; }

            $ul = preg_match('/^\s*[-*+]\s+(.*)$/', $line, $m1) ? $m1 : null;
            $ol = preg_match('/^\s*\d+[.)]\s+(.*)$/', $line, $m2) ? $m2 : null;
            if ($ul || $ol) {
                $flushPara(); $flushQuote();
                $tag = $ul ? 'ul' : 'ol';
                if (!$list || $list['tag'] !== $tag) { $flushList(); $out[] = "<{$tag}>"; $list = ['tag' => $tag]; }
                $out[] = '<li>' . self::inline(($ul ?: $ol)[1]) . '</li>';
                $i++;
                continue;
            }
            $flushList();

            if (preg_match('/^>\s?(.*)$/', $line, $bq)) {
                $flushPara();
                $quote = $quote ?: [];
                $quote[] = $bq[1];
                $i++;
                continue;
            }
            $flushQuote();

            $cells = function (string $l): array {
                $l = preg_replace('/^\s*\|/', '', $l) ?? $l;
                $l = preg_replace('/\|\s*$/', '', $l) ?? $l;
                return array_map('trim', explode('|', $l));
            };

            if (str_contains($line, '|') && $i + 1 < $n && preg_match('/^\s*\|?[\s:|-]+\|[\s:|-]*$/', $lines[$i + 1])) {
                $flushAll();
                $head = $cells($line);
                $i += 2;
                $rows = [];
                while ($i < $n && str_contains($lines[$i], '|')) $rows[] = $cells($lines[$i++]);
                $t = '<div class="md-table-wrap"><table><thead><tr>';
                foreach ($head as $c) $t .= '<th>' . self::inline($c) . '</th>';
                $t .= '</tr></thead><tbody>';
                foreach ($rows as $r) {
                    $t .= '<tr>';
                    foreach ($r as $c) $t .= '<td>' . self::inline($c) . '</td>';
                    $t .= '</tr>';
                }
                $t .= '</tbody></table></div>';
                $out[] = $t;
                continue;
            }

            if (preg_match('/^\s*$/', $line)) { $flushAll(); $i++; continue; }

            $para[] = trim($line);
            $i++;
        }
        $flushAll();

        return ['html' => implode("\n", $out), 'toc' => $toc];
    }

    public static function html(?string $md): string
    {
        return self::render($md)['html'];
    }

    /** Some content pages already store raw HTML — pass it through when it looks like markup. */
    public static function smart(?string $text): string
    {
        $text = (string) $text;
        if (preg_match('/^\s*<(div|p|h[1-6]|section|ul|ol|table|article|span|iframe|img)\b/i', $text)) {
            return $text;
        }
        return self::html($text);
    }
}
