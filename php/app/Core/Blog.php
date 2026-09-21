<?php
/** Blog module — PHP port of src/blog.js. */
final class Blog
{
    public const PER_PAGE = 9;

    public static function parseTags($post): array
    {
        $raw = is_array($post) ? ($post['tags'] ?? '') : ($post->tags ?? '');
        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }

    public static function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        return $s === '' ? 'post' : $s;
    }

    public static function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug = self::slugify($base);
        $n = 1;
        while (true) {
            $row = $excludeId
                ? DB::get('SELECT id FROM blog_posts WHERE slug = ? AND id != ?', [$slug, $excludeId])
                : DB::get('SELECT id FROM blog_posts WHERE slug = ?', [$slug]);
            if (!$row) return $slug;
            $slug = self::slugify($base) . '-' . (++$n);
        }
    }

    public static function excerpt($post, int $len = 160): string
    {
        $desc = trim((string) (is_object($post) ? ($post->description ?? '') : ($post['description'] ?? '')));
        $content = (string) (is_object($post) ? ($post->content ?? '') : ($post['content'] ?? ''));
        $text = $desc !== '' ? $desc : trim((string) preg_replace(
            ['/```[\s\S]*?```/', '/[#>*_`~|\[\]()-]/', '/\s+/'],
            [' ', ' ', ' '],
            $content
        ));
        return mb_strlen($text) > $len ? rtrim(mb_substr($text, 0, $len)) . '…' : $text;
    }

    /** @return array{posts:array,total:int,page:int,pages:int,tags:array} */
    public static function listPublished(array $opts = []): array
    {
        $search = strtolower(trim((string) ($opts['search'] ?? '')));
        $tag = strtolower(trim((string) ($opts['tag'] ?? '')));
        $sort = (string) ($opts['sort'] ?? 'latest');
        $rows = DB::all('SELECT * FROM blog_posts WHERE is_published = 1 AND published_at IS NOT NULL ORDER BY published_at DESC, id DESC');

        if ($search !== '') {
            $rows = array_values(array_filter($rows, function ($p) use ($search) {
                if (str_contains(strtolower((string) $p->title), $search)) return true;
                if (str_contains(strtolower((string) $p->description), $search)) return true;
                foreach (self::parseTags($p) as $t) {
                    if (str_contains(strtolower($t), $search)) return true;
                }
                return false;
            }));
        }
        if ($tag !== '') {
            $rows = array_values(array_filter($rows, function ($p) use ($tag) {
                foreach (self::parseTags($p) as $t) {
                    if (strtolower($t) === $tag) return true;
                }
                return false;
            }));
        }

        usort($rows, function ($a, $b) use ($sort) {
            return match ($sort) {
                'oldest' => strcmp((string) $a->published_at, (string) $b->published_at),
                'updated' => strcmp((string) $b->updated_at, (string) $a->updated_at),
                'popular' => ((int) $b->views) <=> ((int) $a->views),
                default => strcmp((string) $b->published_at, (string) $a->published_at),
            };
        });

        $total = count($rows);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) ($opts['page'] ?? 1) ?: 1), $pages);
        $slice = array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        $posts = [];
        foreach ($slice as $p) {
            $p->tag_list = self::parseTags($p);
            $p->excerpt = self::excerpt($p);
            $posts[] = $p;
        }

        $summary = [];
        foreach ($rows as $p) {
            foreach (self::parseTags($p) as $t) $summary[$t] = ($summary[$t] ?? 0) + 1;
        }
        arsort($summary);
        $tags = [];
        foreach ($summary as $label => $count) $tags[] = ['label' => (string) $label, 'count' => $count];

        return ['posts' => $posts, 'total' => $total, 'page' => $page, 'pages' => $pages, 'tags' => $tags];
    }

    public static function getPublished(string $slug): ?object
    {
        $post = DB::get('SELECT * FROM blog_posts WHERE slug = ? AND is_published = 1 AND published_at IS NOT NULL', [$slug]);
        if (!$post) return null;
        $rendered = Markdown::render((string) $post->content);
        $prev = DB::get('SELECT slug, title FROM blog_posts WHERE is_published = 1 AND published_at > ? ORDER BY published_at ASC LIMIT 1', [$post->published_at]);
        $next = DB::get('SELECT slug, title FROM blog_posts WHERE is_published = 1 AND published_at < ? ORDER BY published_at DESC LIMIT 1', [$post->published_at]);
        $fb = DB::get('SELECT SUM(is_helpful = 1) helpful, SUM(is_helpful = 0) unhelpful FROM blog_post_feedback WHERE post_id = ?', [(int) $post->id]);

        $post->tag_list = self::parseTags($post);
        $post->excerpt = self::excerpt($post);
        $post->html = $rendered['html'];
        $post->toc = $rendered['toc'];
        $post->prev = $prev;
        $post->next = $next;
        $post->feedback = ['helpful' => (int) ($fb->helpful ?? 0), 'unhelpful' => (int) ($fb->unhelpful ?? 0)];
        return $post;
    }

    public static function latest(int $limit = 3): array
    {
        $rows = DB::all('SELECT id, title, slug, description, cover_image_url, tags, views, published_at FROM blog_posts WHERE is_published = 1 AND published_at IS NOT NULL ORDER BY published_at DESC LIMIT ' . max(1, $limit));
        foreach ($rows as $p) {
            $p->tag_list = self::parseTags($p);
            $p->excerpt = self::excerpt($p, 110);
        }
        return $rows;
    }

    public static function recordView(object $post): void
    {
        DB::run('UPDATE blog_posts SET views = views + 1 WHERE id = ?', [(int) $post->id]);
    }

    public static function saveFeedback(int $postId, int $helpful, string $sessionId): bool
    {
        $existing = DB::get('SELECT id, is_helpful FROM blog_post_feedback WHERE post_id = ? AND session_id = ?', [$postId, $sessionId]);
        if ($existing) {
            if ((int) $existing->is_helpful === $helpful) return false;
            DB::run('UPDATE blog_post_feedback SET is_helpful = ? WHERE id = ?', [$helpful, (int) $existing->id]);
            return true;
        }
        DB::insert('blog_post_feedback', ['post_id' => $postId, 'is_helpful' => $helpful, 'session_id' => $sessionId]);
        return true;
    }

    public static function fmtDate($ts): string
    {
        if (!$ts) return '';
        $s = (string) $ts;
        $t = strtotime(str_replace(' ', 'T', $s) . (strlen($s) === 16 ? ':00Z' : 'Z'));
        if (!$t) return mb_substr($s, 0, 10);
        return date('M j, Y', $t);
    }
}
