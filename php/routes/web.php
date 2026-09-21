<?php
/**
 * Web (HTML) routes — PHP port of src/routes/web.routes.js (+ the CasaOS desktop pages).
 *
 * Conventions
 *   $pv('pages/user/team', [...])   → render inside the CasaOS shell layout
 *   [Auth::class, 'requireAuth']    → middleware (requireAdmin / csrf / track)
 *   Response::ok([...])             → { success: true, ... }
 *   $req->str('x') / $req->int('x') → body ?? query
 */

return function (Router $r): void {

    /* ---------------------------------------------------------------- helpers */

    /** Render a page inside the shell layout (mirrors node's pageView()). */
    $pv = function (string $view, array $opts = []): void {
        View::render($view, array_merge([
            'title' => '',
            'active' => '',
            'bodyClass' => '',
        ], $opts));
    };

    /** Admin page-editor render (mirrors node's adminEditor()). */
    $adminEditor = function (string $view, array $opts = []) use ($pv): void {
        $pv($view, array_merge(['active' => 'admin-pages'], $opts));
    };

    $getUserPage = fn(string $slug): ?object => DB::get('SELECT * FROM content_pages WHERE slug = ?', [$slug]);

    $isAdmin = fn(): bool => ($u = Auth::user()) && ($u->role ?? '') === 'admin';

    $clip = fn($v, int $n): string => mb_substr(trim((string) ($v ?? '')), 0, $n);

    $getFavs = function (): array {
        $arr = json_decode((string) Settings::get('wallpaper_favs', '[]'), true);
        return is_array($arr) ? $arr : [];
    };
    $saveFavs = function (array $favs): void {
        Settings::set('wallpaper_favs', (string) json_encode(array_values($favs)));
    };

    $CONTENT_SLUGS = ['home', 'team', 'tutorials', 'command', 'analytics', 'projects', 'links', 'github', 'about'];

    $LINK_ICONS = ['🔗', '🌐', '🌍', '📧', '📬', '💬', '💭', '🎮', '🕹️', '🎯', '📱', '💻', '🖥️', '⌨️', '🎧', '🎵', '🎬', '📺', '📸', '🎥', '🎨', '🎭', '🏆', '🥇', '💎', '💰', '💳', '🛒', '🛍️', '🔒', '🔑', '⚙️', '🛠️', '🔧', '📦', '🚀', '☁️', '🗄️', '📊', '📈', '🧮', '📁', '📂', '📄', '📝', '📌', '📍', '🧭', '⭐', '❤️', '🔥', '⚡', '🎲', '🧩', '🤖', '👾', '🤝', '🌟', '🎉', '🎁'];

    /* ------------------------------------------------- CasaOS desktop (home) */

    $r->get('/', [Auth::track('home')], function (Request $req) use ($pv, $getUserPage) {
        $u = Auth::user();
        $uid = $u ? (int) $u->id : 0;

        $page = $getUserPage('home');
        $sys = System::stats();
        System::recordSample($sys);

        $stats = [
            'team' => DB::count('SELECT COUNT(*) FROM users WHERE owner_id = ?', [$uid]),
            'users' => DB::count('SELECT COUNT(*) FROM users'),
            'tutorials' => DB::count('SELECT COUNT(*) FROM tutorials'),
            'projects' => DB::count('SELECT COUNT(*) FROM projects'),
            'links' => DB::count('SELECT COUNT(*) FROM links'),
            'blog' => DB::count('SELECT COUNT(*) FROM blog_posts WHERE is_published = 1'),
            'docs' => DB::count('SELECT COUNT(*) FROM docs'),
            'plans' => DB::count('SELECT COUNT(*) FROM plans WHERE deleted = 0'),
            'activity' => DB::count('SELECT COUNT(*) FROM activity WHERE user_id = ?', [$uid]),
            'online' => $u ? 1 : 0,
        ];

        $social = ['youtube' => null, 'instagram' => null, 'github' => null, 'discord' => null];
        try {
            $social = Social::data(Settings::all());
        } catch (Throwable $e) { /* widgets degrade quietly */ }

        $apps = DB::all('SELECT * FROM casaos_apps WHERE user_id IS NULL OR user_id = ? ORDER BY sort_order, id', [$uid]);
        $activity = DB::all('SELECT * FROM activity WHERE user_id = ? OR ? = 1 ORDER BY id DESC LIMIT 6', [$uid, ($u && ($u->role ?? '') === 'admin') ? 1 : 0]);

        $pv('pages/casaos/home', [
            'title' => 'Home',
            'active' => 'home',
            'page' => $page,
            'sys' => $sys,
            'stats' => $stats,
            'social' => $social,
            'apps' => $apps,
            'devices' => System::storageDevices(),
            'activity' => $activity,
            'blogPosts' => Blog::latest(3),
            'query' => nh_obj($req->query),
            'extraScripts' => '<script src="/assets/js/home.js"></script>',
        ]);
    });

    /* ------------------------------------------------ CasaOS storage / system */

    $r->get('/storage', function (Request $req) use ($pv) {
        $sys = System::stats();
        $pv('pages/casaos/storage', [
            'title' => 'Storage',
            'active' => 'storage',
            'sys' => $sys,
            'devices' => System::storageDevices(),
        ]);
    });

    $r->get('/system', function (Request $req) use ($pv) {
        $sys = System::stats();
        System::recordSample($sys);
        $pv('pages/casaos/system', [
            'title' => 'System',
            'active' => 'system',
            'sys' => $sys,
            'history' => System::history($req->str('range', '24h')),
        ]);
    });

    $r->get('/system-data', function (Request $req) {
        $sys = System::stats();
        System::recordSample($sys);
        Response::ok(['system' => $sys, 'data' => $sys, 'devices' => System::storageDevices()]);
    });

    $r->get('/system-history', function (Request $req) {
        $range = $req->str('range', '24h');
        $history = System::history($range);
        Response::ok(['history' => $history, 'data' => $history, 'range' => $range]);
    });

    /* ------------------------------------------------------------ blog (public) */

    $r->get('/blog', [Auth::track('blog')], function (Request $req) use ($pv) {
        $sort = (string) ($req->query['sort'] ?? 'latest');
        $data = Blog::listPublished([
            'search' => trim((string) ($req->query['search'] ?? '')),
            'tag' => trim((string) ($req->query['tag'] ?? '')),
            'sort' => in_array($sort, ['latest', 'oldest', 'updated', 'popular'], true) ? $sort : 'latest',
            'page' => $req->query['page'] ?? 1,
        ]);
        $pv('pages/user/blog', [
            'title' => 'Blog',
            'active' => 'blog',
            'query' => nh_obj($req->query),
            'posts' => $data['posts'],
            'total' => $data['total'],
            'pages' => $data['pages'],
            'pageNum' => $data['page'],
            'tags' => $data['tags'],
        ]);
    });

    $r->get('/blog/{slug}', [Auth::track('blog')], function (Request $req, array $params) use ($pv) {
        $post = Blog::getPublished((string) $params['slug']);
        if (!$post) {
            Response::status(404);
            View::render('pages/errors/404', ['title' => '404', 'active' => '']);
            return;
        }
        $vKey = 'nh_vb_' . (int) $post->id;
        if (empty($req->cookies[$vKey])) {
            Blog::recordView($post);
            Response::cookie($vKey, '1', 30 * 24 * 3600);
        }
        $myFeedback = null;
        $sid = (string) ($req->cookies['nh_sid'] ?? '');
        if ($sid !== '') {
            $row = DB::get('SELECT is_helpful FROM blog_post_feedback WHERE post_id = ? AND session_id = ?', [(int) $post->id, $sid]);
            if ($row) $myFeedback = !empty($row->is_helpful);
        }
        $pv('pages/user/blog-post', [
            'title' => (string) ($post->seo_title ?: $post->title),
            'active' => 'blog',
            'post' => $post,
            'myFeedback' => $myFeedback,
            'metaDescription' => (string) ($post->seo_description ?: ($post->description ?: ($post->excerpt ?? ''))),
        ]);
    });

    /* --------------------------------------------------------- admin · blog */

    /** Normalised blog form fields (node's blogFields()). */
    $blogFields = function (array $b): array {
        $tags = [];
        foreach (explode(',', (string) ($b['tags'] ?? '')) as $t) {
            $t = trim($t);
            if ($t !== '') $tags[] = $t;
        }
        return [
            'title' => trim((string) ($b['title'] ?? '')),
            'slug' => trim((string) ($b['slug'] ?? '')),
            'description' => mb_substr(trim((string) ($b['description'] ?? '')), 0, 255),
            'cover_image_url' => trim((string) ($b['cover_image_url'] ?? '')),
            'seo_title' => mb_substr(trim((string) ($b['seo_title'] ?? '')), 0, 255),
            'seo_description' => mb_substr(trim((string) ($b['seo_description'] ?? '')), 0, 320),
            'seo_keywords' => mb_substr(trim((string) ($b['seo_keywords'] ?? '')), 0, 500),
            'content' => (string) ($b['content'] ?? ''),
            'tags' => implode(', ', $tags),
            'is_published' => !empty($b['is_published']) ? 1 : 0,
        ];
    };

    $r->get('/admin/blog', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $posts = [];
        foreach (DB::all('SELECT * FROM blog_posts ORDER BY created_at DESC, id DESC') as $p) {
            $p->tag_list = Blog::parseTags($p);
            $p->excerpt = Blog::excerpt($p, 120);
            $posts[] = $p;
        }
        $fb = DB::get('SELECT SUM(is_helpful = 1) helpful, SUM(is_helpful = 0) unhelpful FROM blog_post_feedback');
        $views = 0;
        $published = 0;
        foreach ($posts as $p) {
            $views += (int) ($p->views ?? 0);
            if (!empty($p->is_published)) $published++;
        }
        $pv('pages/admin/blog', [
            'title' => 'Blog Manager',
            'active' => 'admin-blog',
            'posts' => $posts,
            'query' => nh_obj($req->query),
            'stats' => [
                'total' => count($posts),
                'published' => $published,
                'views' => $views,
                'helpful' => (int) ($fb->helpful ?? 0),
            ],
        ]);
    });

    $r->get('/admin/blog/create', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $pv('pages/admin/blog-form', ['title' => 'New Post', 'active' => 'admin-blog', 'post' => null, 'query' => nh_obj($req->query)]);
    });

    $r->post('/admin/blog/create', [Auth::class, 'requireAdmin'], function (Request $req) use ($blogFields) {
        $f = $blogFields($req->body);
        if ($f['title'] === '' || $f['content'] === '') { Response::redirect('/admin/blog/create?error=fields'); return; }
        $slug = Blog::uniqueSlug($f['slug'] !== '' ? $f['slug'] : $f['title']);
        DB::run(
            'INSERT INTO blog_posts (title, slug, description, cover_image_url, seo_title, seo_description, seo_keywords, content, tags, is_published, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $f['title'], $slug, $f['description'], $f['cover_image_url'], $f['seo_title'], $f['seo_description'],
                $f['seo_keywords'], $f['content'], $f['tags'], $f['is_published'],
                $f['is_published'] ? gmdate('Y-m-d H:i:s') : null,
            ]
        );
        Auth::logActivity(Auth::user(), 'Created blog post "' . $f['title'] . '"', $req);
        Response::redirect('/admin/blog?created=1');
    });

    $r->get('/admin/blog/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) use ($pv) {
        $post = DB::get('SELECT * FROM blog_posts WHERE id = ?', [(int) $params['id']]);
        if (!$post) { Response::redirect('/admin/blog'); return; }
        $pv('pages/admin/blog-form', ['title' => 'Edit · ' . (string) $post->title, 'active' => 'admin-blog', 'post' => $post, 'query' => nh_obj($req->query)]);
    });

    $r->post('/admin/blog/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) use ($blogFields) {
        $target = DB::get('SELECT * FROM blog_posts WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/admin/blog'); return; }
        $f = $blogFields($req->body);
        if ($f['title'] === '' || $f['content'] === '') { Response::redirect('/admin/blog/' . (int) $target->id . '/edit?error=fields'); return; }
        $slug = Blog::uniqueSlug($f['slug'] !== '' ? $f['slug'] : $f['title'], (int) $target->id);
        $publishedAt = $f['is_published'] ? ((string) ($target->published_at ?: '') !== '' ? (string) $target->published_at : gmdate('Y-m-d H:i:s')) : null;
        DB::run(
            "UPDATE blog_posts SET title = ?, slug = ?, description = ?, cover_image_url = ?, seo_title = ?, seo_description = ?, seo_keywords = ?, content = ?, tags = ?, is_published = ?, published_at = ?, updated_at = datetime('now') WHERE id = ?",
            [
                $f['title'], $slug, $f['description'], $f['cover_image_url'], $f['seo_title'], $f['seo_description'],
                $f['seo_keywords'], $f['content'], $f['tags'], $f['is_published'], $publishedAt, (int) $target->id,
            ]
        );
        Auth::logActivity(Auth::user(), 'Edited blog post "' . $f['title'] . '"', $req);
        Response::redirect('/admin/blog?saved=1');
    });

    $r->post('/admin/blog/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM blog_posts WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/admin/blog'); return; }
        DB::run('DELETE FROM blog_post_feedback WHERE post_id = ?', [(int) $target->id]);
        DB::run('DELETE FROM blog_posts WHERE id = ?', [(int) $target->id]);
        Auth::logActivity(Auth::user(), 'Deleted blog post "' . (string) $target->title . '"', $req);
        Response::redirect('/admin/blog?deleted=1');
    });

    /* --------------------------------------------------- admin · links / github / projects */

    $r->post('/links/add', [Auth::class, 'requireAdmin'], function (Request $req) {
        $title = (string) $req->input('title', '');
        if ($title === '') { Response::redirect('/links?error=title'); return; }
        DB::run('INSERT INTO links (title, url, icon) VALUES (?, ?, ?)', [$title, (string) $req->input('url', ''), (string) ($req->input('icon') ?: '🔗')]);
        Auth::logActivity(Auth::user(), 'Added link "' . $title . '"', $req);
        Response::redirect('/links?saved=1');
    });

    $r->post('/links/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM links WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/links'); return; }
        DB::run('UPDATE links SET title = ?, url = ?, icon = ? WHERE id = ?', [
            (string) ($req->input('title') ?: $target->title),
            (string) $req->input('url', ''),
            (string) ($req->input('icon') ?: '🔗'),
            (int) $target->id,
        ]);
        Auth::logActivity(Auth::user(), 'Edited link "' . (string) $target->title . '"', $req);
        Response::redirect('/links?saved=1');
    });

    $r->post('/links/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM links WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/links'); return; }
        DB::run('DELETE FROM links WHERE id = ?', [(int) $target->id]);
        Auth::logActivity(Auth::user(), 'Deleted link "' . (string) $target->title . '"', $req);
        Response::redirect('/links?deleted=1');
    });

    $r->post('/github/add', [Auth::class, 'requireAdmin'], function (Request $req) {
        $username = (string) $req->input('username', '');
        if ($username === '') { Response::redirect('/github?error=username'); return; }
        DB::run('INSERT INTO github_links (username, url, note) VALUES (?, ?, ?)', [$username, (string) $req->input('url', ''), (string) $req->input('note', '')]);
        Auth::logActivity(Auth::user(), 'Added GitHub user "' . $username . '"', $req);
        Response::redirect('/github?saved=1');
    });

    $r->post('/github/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM github_links WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/github'); return; }
        DB::run('UPDATE github_links SET username = ?, url = ?, note = ? WHERE id = ?', [
            (string) ($req->input('username') ?: $target->username),
            (string) $req->input('url', ''),
            (string) $req->input('note', ''),
            (int) $target->id,
        ]);
        Auth::logActivity(Auth::user(), 'Edited GitHub user "' . (string) $target->username . '"', $req);
        Response::redirect('/github?saved=1');
    });

    $r->post('/github/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM github_links WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/github'); return; }
        DB::run('DELETE FROM github_links WHERE id = ?', [(int) $target->id]);
        Auth::logActivity(Auth::user(), 'Deleted GitHub user "' . (string) $target->username . '"', $req);
        Response::redirect('/github?deleted=1');
    });

    $r->post('/projects/add', [Auth::class, 'requireAdmin'], function (Request $req) {
        $name = (string) $req->input('name', '');
        if ($name === '') { Response::redirect('/projects?error=name'); return; }
        DB::run('INSERT INTO projects (name, description, url, button, thumbnail, html) VALUES (?, ?, ?, ?, ?, ?)', [
            $name,
            (string) $req->input('description', ''),
            (string) $req->input('url', ''),
            (string) ($req->input('button') ?: 'View Project'),
            (string) $req->input('thumbnail', ''),
            (string) $req->input('html', ''),
        ]);
        Auth::logActivity(Auth::user(), 'Added project "' . $name . '"', $req);
        Response::redirect('/projects?saved=1');
    });

    $r->post('/projects/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM projects WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/projects'); return; }
        DB::run('UPDATE projects SET name = ?, description = ?, url = ?, button = ?, thumbnail = ?, html = ? WHERE id = ?', [
            (string) ($req->input('name') ?: $target->name),
            (string) $req->input('description', ''),
            (string) $req->input('url', ''),
            (string) ($req->input('button') ?: 'View Project'),
            (string) $req->input('thumbnail', ''),
            (string) $req->input('html', ''),
            (int) $target->id,
        ]);
        Auth::logActivity(Auth::user(), 'Edited project "' . (string) $target->name . '"', $req);
        Response::redirect('/projects?saved=1');
    });

    $r->post('/projects/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM projects WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/projects'); return; }
        DB::run('DELETE FROM projects WHERE id = ?', [(int) $target->id]);
        Auth::logActivity(Auth::user(), 'Deleted project "' . (string) $target->name . '"', $req);
        Response::redirect('/projects?deleted=1');
    });


    /* ------------------------------------------------------------------ blog */

    $r->post('/blog/{slug}/feedback', function (Request $req, array $params) {
        $back = safe_back($req->input('back'));
        if ($back === '/') $back = '/blog/' . $params['slug'];
        $post = DB::get('SELECT id FROM blog_posts WHERE slug = ? AND is_published = 1', [$params['slug']]);
        if (!$post) { Response::redirect('/blog'); return; }

        $sid = (string) ($req->cookies['nh_sid'] ?? '');
        if ($sid === '' || strlen($sid) > 64) {
            $sid = bin2hex(random_bytes(16));
            Response::cookie('nh_sid', $sid, 365 * 24 * 3600);
        }
        $helpful = ((string) $req->input('helpful')) === 'no' ? 0 : 1;
        $changed = Blog::saveFeedback((int) $post->id, $helpful, $sid);
        $sep = str_contains($back, '?') ? '&' : '?';
        Response::redirect($back . $sep . 'fb=' . ($changed ? ($helpful ? 'yes' : 'no') : 'same'));
    });

    /* ------------------------------------------------------------------ team */

    $r->get('/team', [Auth::track('team')], function (Request $req) use ($pv, $getUserPage, $isAdmin) {
        $members = DB::all('SELECT u.*, r.name AS custom_role, r.color AS custom_color FROM users u LEFT JOIN roles r ON r.id = u.custom_role_id ORDER BY u.created_at DESC');
        $roles = DB::all('SELECT * FROM roles ORDER BY sort_order, id');
        $pv('pages/user/team', [
            'title' => 'Team',
            'active' => 'team',
            'page' => $getUserPage('team'),
            'members' => $members,
            'roles' => $roles,
            'isAdmin' => $isAdmin(),
            'query' => nh_obj($req->query),
        ]);
    });

    /* ------------------------------------------------------------- tutorials */

    $r->get('/tutorials', [Auth::track('tutorials')], function (Request $req) use ($pv, $getUserPage) {
        $tutorials = DB::all('SELECT t.*, u.username AS author FROM tutorials t LEFT JOIN users u ON u.id = t.author_id ORDER BY t.created_at DESC');
        $s = Settings::all();
        $yt = null;
        if (($s->youtube_enabled ?? '') === 'on' && !empty($s->youtube_channel)) {
            try {
                $yt = Youtube::getData($s);
            } catch (Throwable $e) {
                $yt = ['error' => $e->getMessage()];
            }
        }
        $pv('pages/user/tutorials', [
            'title' => 'Tutorials',
            'active' => 'tutorials',
            'page' => $getUserPage('tutorials'),
            'tutorials' => $tutorials,
            'yt' => $yt,
            'fmt' => Youtube::class,
        ]);
    });

    /* --------------------------------------------------------------- command */

    $r->get('/command', [Auth::track('command')], function (Request $req) use ($pv, $getUserPage) {
        $cmdData = str_replace('</', '<\/', (string) json_encode(Commands::all(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $pv('pages/user/command', [
            'title' => 'Command',
            'active' => 'command',
            'page' => $getUserPage('command'),
            'cmdData' => $cmdData,
            'catCounts' => Commands::CATEGORIES,
            'commandCount' => Commands::count(),
        ]);
    });

    /* ------------------------------------------------------------- analytics */

    $r->get('/analytics', [Auth::track('analytics')], function (Request $req) use ($pv, $getUserPage) {
        $last7 = DB::all("SELECT page, date, SUM(hits) AS hits FROM analytics WHERE date >= date('now', '-6 day') GROUP BY page, date ORDER BY date");
        $stats = [
            'totalUsers' => DB::count('SELECT COUNT(*) FROM users'),
            'activeUsers' => DB::count("SELECT COUNT(*) FROM users WHERE status = 'active'"),
            'suspended' => DB::count("SELECT COUNT(*) FROM users WHERE status = 'suspended'"),
            'admins' => DB::count("SELECT COUNT(*) FROM users WHERE role = 'admin'"),
            'todayHits' => (int) DB::first("SELECT COALESCE(SUM(hits),0) FROM analytics WHERE date = date('now')"),
            'todayLogins' => DB::count("SELECT COUNT(*) FROM activity WHERE date(created_at) = date('now') AND action LIKE '%ogged in%'"),
        ];
        $pv('pages/user/analytics', [
            'title' => 'Analytics',
            'active' => 'analytics',
            'page' => $getUserPage('analytics'),
            'last7' => $last7,
            'stats' => $stats,
            'system' => System::stats(),
        ]);
    });

    $r->get('/traffic-data', [Auth::class, 'requireAdmin'], function (Request $req) {
        try {
            Response::json([
                'ok' => true,
                'todayHits' => (int) DB::first("SELECT COALESCE(SUM(hits),0) FROM analytics WHERE date = date('now')"),
                'totalHits' => (int) DB::first('SELECT COALESCE(SUM(hits),0) FROM analytics'),
                'todayUniques' => (int) DB::first("SELECT COUNT(DISTINCT ip) FROM page_views WHERE date(created_at) = date('now')"),
                'totalUniques' => (int) DB::first('SELECT COUNT(DISTINCT ip) FROM page_views'),
                'todayViews' => DB::count("SELECT COUNT(*) FROM page_views WHERE date(created_at) = date('now')"),
                'totalViews' => DB::count('SELECT COUNT(*) FROM page_views'),
                'last7' => DB::all("SELECT date(created_at) AS date, COUNT(*) AS views, COUNT(DISTINCT ip) AS visitors FROM page_views WHERE date(created_at) >= date('now', '-6 day') GROUP BY date(created_at) ORDER BY date(created_at)"),
                'pages' => DB::all("SELECT page, COUNT(*) AS views, COUNT(DISTINCT ip) AS visitors FROM page_views WHERE date(created_at) >= date('now', '-6 day') GROUP BY page ORDER BY views DESC LIMIT 10"),
                'referrers' => DB::all("SELECT CASE WHEN referrer = '' THEN 'Direct' ELSE CASE WHEN referrer LIKE '%google%' THEN 'Google' WHEN referrer LIKE '%facebook%' OR referrer LIKE '%fb.com%' THEN 'Facebook' WHEN referrer LIKE '%twitter%' OR referrer LIKE '%x.com%' THEN 'Twitter' WHEN referrer LIKE '%instagram%' THEN 'Instagram' WHEN referrer LIKE '%youtube%' THEN 'YouTube' WHEN referrer LIKE '%reddit%' THEN 'Reddit' WHEN referrer LIKE '%github%' THEN 'GitHub' WHEN referrer LIKE '%t.me%' OR referrer LIKE '%telegram%' THEN 'Telegram' WHEN referrer LIKE '%discord%' THEN 'Discord' WHEN referrer LIKE '%linkedin%' THEN 'LinkedIn' WHEN referrer LIKE '%pinterest%' THEN 'Pinterest' WHEN referrer LIKE '%tiktok%' THEN 'TikTok' ELSE substr(referrer, 1, 40) END END AS source, COUNT(*) AS views, COUNT(DISTINCT ip) AS visitors FROM page_views WHERE date(created_at) >= date('now', '-6 day') GROUP BY source ORDER BY views DESC LIMIT 10"),
                'hourly' => DB::all("SELECT strftime('%H', created_at) AS hour, COUNT(*) AS views, COUNT(DISTINCT ip) AS visitors FROM page_views WHERE date(created_at) = date('now') GROUP BY hour ORDER BY hour"),
                'recentViews' => DB::all('SELECT id, page, ip, substr(user_agent, 1, 80) AS ua, substr(referrer, 1, 60) AS ref, created_at FROM page_views ORDER BY created_at DESC LIMIT 15'),
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    });

    /* -------------------------------------------------------------- projects */

    $r->get('/projects', [Auth::track('projects')], function (Request $req) use ($pv, $getUserPage, $isAdmin) {
        $pv('pages/user/projects', [
            'title' => 'Projects',
            'active' => 'projects',
            'page' => $getUserPage('projects'),
            'projects' => DB::all('SELECT * FROM projects ORDER BY sort_order, id'),
            'query' => nh_obj($req->query),
            'isAdmin' => $isAdmin(),
            'extraScripts' => '<script src="/assets/js/projects.js"></script>',
        ]);
    });

    /* ----------------------------------------------------------------- links */

    $r->get('/links', [Auth::track('links')], function (Request $req) use ($pv, $getUserPage, $isAdmin, $LINK_ICONS) {
        $pv('pages/user/links', [
            'title' => 'Links',
            'active' => 'links',
            'page' => $getUserPage('links'),
            'links' => DB::all('SELECT * FROM links ORDER BY sort_order, id'),
            'query' => nh_obj($req->query),
            'isAdmin' => $isAdmin(),
            'icons' => $LINK_ICONS,
            'extraScripts' => '<script src="/assets/js/links.js"></script>',
        ]);
    });

    /* ---------------------------------------------------------------- github */

    $r->get('/github', [Auth::track('github')], function (Request $req) use ($pv, $getUserPage, $isAdmin) {
        $github = DB::all('SELECT * FROM github_links ORDER BY sort_order, id');
        $s = Settings::all();
        $ghUsername = (string) ($s->github_username ?? '');
        $ghToken = (string) ($s->github_token ?? '');

        $users = $ghUsername !== '' ? [$ghUsername] : [];
        foreach ($github as $g) {
            if (!in_array((string) $g->username, $users, true)) $users[] = (string) $g->username;
        }

        $reposByUser = [];
        foreach ($users as $username) {
            $reposByUser[] = array_merge(['username' => $username], Github::getRepos($username, $ghToken));
        }

        $mainStats = $ghUsername !== '' ? Github::getUserStats($ghUsername, $ghToken) : ['stats' => null, 'error' => null];

        $pv('pages/user/github', [
            'title' => 'GitHub',
            'active' => 'github',
            'page' => $getUserPage('github'),
            'github' => $github,
            'reposByUser' => $reposByUser,
            'stats' => $mainStats['stats'] ?? null,
            'statsError' => $mainStats['error'] ?? null,
            'query' => nh_obj($req->query),
            'ghConfigured' => $ghUsername !== '',
            'isAdmin' => $isAdmin(),
            'extraScripts' => '<script src="/assets/js/github.js"></script>',
        ]);
    });

    $r->post('/github/api-settings', [Auth::class, 'requireAdmin'], function (Request $req) {
        $upd = [];
        if ($req->input('github_username') !== null) $upd['github_username'] = trim((string) $req->input('github_username'));
        if ($req->input('github_token')) $upd['github_token'] = trim((string) $req->input('github_token'));
        if ($upd) Settings::setMany($upd);
        Auth::logActivity(Auth::user(), 'Updated GitHub API settings', $req);
        Response::redirect('/github?apikey=1');
    });

    /* ----------------------------------------------------------------- about */

    $r->get('/about', [Auth::track('about')], function (Request $req) use ($pv, $getUserPage, $isAdmin) {
        $about = Obsidian::parseAbout(Obsidian::load('about'));
        $pv('pages/user/about', [
            'title' => !empty($about['enabled']) ? (($about['seo_title'] ?? '') ?: 'About') : 'About',
            'active' => 'about',
            'page' => $getUserPage('about'),
            'about' => $about,
            'query' => nh_obj($req->query),
            'isAdmin' => $isAdmin(),
        ]);
    });

    /* ----------------------------------------------------------------- terms */

    $r->get('/terms', [Auth::track('terms')], function (Request $req) use ($pv) {
        $terms = Obsidian::parseTerms(Obsidian::load('terms'));
        if (empty($terms['enabled']) || !$terms['sections']) {
            Response::status(404);
            View::render('pages/errors/404', ['title' => '404', 'active' => '']);
            return;
        }
        $pv('pages/user/terms', ['title' => $terms['title'], 'active' => '', 'terms' => $terms]);
    });

    /* ------------------------------------------- obsidian page editors (admin) */

    $r->get('/admin/pages', [Auth::class, 'requireAdmin'], function (Request $req) use ($adminEditor) {
        $adminEditor('pages/admin/pageedit-hub', ['title' => 'Page Editors']);
    });

    $r->get('/admin/pages/about', [Auth::class, 'requireAdmin'], function (Request $req) use ($adminEditor) {
        $adminEditor('pages/admin/pageedit-about', [
            'title' => 'About Editor',
            'd' => array_merge(Obsidian::defaultAbout(), Obsidian::load('about') ?? []),
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/admin/pages/about', [Auth::class, 'requireAdmin'], function (Request $req) use ($clip) {
        $b = $req->body;
        Obsidian::save('about', [
            'enabled' => !empty($b['enabled']),
            'seo_title' => $clip($b['seo_title'] ?? '', 120),
            'hero' => [
                'title' => $clip($b['hero_title'] ?? '', 150),
                'subtitle' => $clip($b['hero_subtitle'] ?? '', 300),
                'image_url' => $clip($b['hero_image_url'] ?? '', 500),
                'cta1_label' => $clip($b['cta1_label'] ?? '', 60),
                'cta1_url' => $clip($b['cta1_url'] ?? '', 400),
                'cta2_label' => $clip($b['cta2_label'] ?? '', 60),
                'cta2_url' => $clip($b['cta2_url'] ?? '', 400),
            ],
            'stats_enabled' => !empty($b['stats_enabled']),
            'stats_title' => '',
            'stats_text' => $clip($b['stats_text'] ?? '', 3000),
            'story_enabled' => !empty($b['story_enabled']),
            'story_title' => $clip($b['story_title'] ?? '', 150),
            'story_text' => $clip($b['story_text'] ?? '', 6000),
            'values_enabled' => !empty($b['values_enabled']),
            'values_title' => $clip($b['values_title'] ?? '', 150),
            'values_text' => $clip($b['values_text'] ?? '', 4000),
            'team_enabled' => !empty($b['team_enabled']),
            'team_title' => $clip($b['team_title'] ?? '', 150),
            'team_text' => $clip($b['team_text'] ?? '', 4000),
            'timeline_enabled' => !empty($b['timeline_enabled']),
            'timeline_title' => $clip($b['timeline_title'] ?? '', 150),
            'timeline_text' => $clip($b['timeline_text'] ?? '', 4000),
            'gallery_enabled' => !empty($b['gallery_enabled']),
            'gallery_title' => $clip($b['gallery_title'] ?? '', 150),
            'gallery_text' => $clip($b['gallery_text'] ?? '', 4000),
        ]);
        Auth::logActivity(Auth::user(), 'Updated About page (editor)', $req);
        Response::redirect('/admin/pages/about?saved=1');
    });

    $r->get('/admin/pages/terms', [Auth::class, 'requireAdmin'], function (Request $req) use ($adminEditor) {
        $adminEditor('pages/admin/pageedit-terms', [
            'title' => 'Terms Editor',
            'd' => array_merge(Obsidian::defaultTerms(), Obsidian::load('terms') ?? []),
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/admin/pages/terms', [Auth::class, 'requireAdmin'], function (Request $req) use ($clip) {
        $b = $req->body;
        Obsidian::save('terms', [
            'enabled' => array_key_exists('enabled', $b) ? !empty($b['enabled']) : true,
            'title' => $clip($b['title'] ?? '', 150) ?: 'Terms & Conditions',
            'summary' => $clip($b['summary'] ?? '', 400),
            'last_updated' => $clip($b['last_updated'] ?? '', 20),
            'sections_text' => $clip($b['sections_text'] ?? '', 60000),
        ]);
        Auth::logActivity(Auth::user(), 'Updated Terms page (editor)', $req);
        Response::redirect('/admin/pages/terms?saved=1');
    });

    $r->get('/admin/pages/footer', [Auth::class, 'requireAdmin'], function (Request $req) use ($adminEditor) {
        $adminEditor('pages/admin/pageedit-footer', [
            'title' => 'Footer Editor',
            'd' => array_merge(Obsidian::defaultFooter(), Obsidian::load('footer') ?? []),
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/admin/pages/footer', [Auth::class, 'requireAdmin'], function (Request $req) use ($clip) {
        $b = $req->body;
        Obsidian::save('footer', [
            'enabled' => !empty($b['enabled']),
            'copyright' => $clip($b['copyright'] ?? '', 200),
            'columns_text' => $clip($b['columns_text'] ?? '', 8000),
            'legal_text' => $clip($b['legal_text'] ?? '', 2000),
        ]);
        Auth::logActivity(Auth::user(), 'Updated Footer (editor)', $req);
        Response::redirect('/admin/pages/footer?saved=1');
    });

    $r->get('/admin/pages/navbar', [Auth::class, 'requireAdmin'], function (Request $req) use ($adminEditor) {
        $adminEditor('pages/admin/pageedit-navbar', [
            'title' => 'Navbar Editor',
            'd' => array_merge(Obsidian::defaultNavbar(), Obsidian::load('navbar') ?? []),
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/admin/pages/navbar', [Auth::class, 'requireAdmin'], function (Request $req) use ($clip) {
        $b = $req->body;
        Obsidian::save('navbar', [
            'enabled' => !empty($b['enabled']),
            'links_text' => $clip($b['links_text'] ?? '', 4000),
        ]);
        Auth::logActivity(Auth::user(), 'Updated Navbar links (editor)', $req);
        Response::redirect('/admin/pages/navbar?saved=1');
    });

    /* --------------------------------------------------- custom content pages */

    $r->get('/page/{slug}', function (Request $req, array $params) use ($pv, $CONTENT_SLUGS) {
        $slug = strtolower((string) $params['slug']);
        $page = DB::get('SELECT * FROM content_pages WHERE slug = ?', [$slug]);
        if (!$page) {
            Response::status(404);
            View::render('pages/errors/404', ['title' => '404', 'active' => '', 'bodyClass' => 'auth-page']);
            return;
        }
        if (in_array($slug, $CONTENT_SLUGS, true)) { Response::redirect('/' . $slug); return; }
        (Auth::track('page:' . $slug))($req);
        $pv('pages/user/custom-page', ['title' => (string) $page->title, 'active' => $slug, 'page' => $page]);
    });

    $r->post('/about/update', [Auth::class, 'requireAdmin'], function (Request $req) {
        DB::run("UPDATE content_pages SET content = ?, updated_at = datetime('now'), updated_by = ? WHERE slug = 'about'",
            [(string) $req->input('content', ''), (int) (Auth::user()->id ?? 0)]);
        Auth::logActivity(Auth::user(), 'Updated About page', $req);
        Response::redirect('/about?saved=1');
    });

    /* --------------------------------------------------------------- activity */

    $r->get('/activity', function (Request $req) use ($pv) {
        $u = Auth::user();
        if ($u && ($u->role ?? '') !== 'admin') {
            $rows = DB::all('SELECT * FROM activity WHERE user_id = ? ORDER BY created_at DESC LIMIT 100', [(int) $u->id]);
        } else {
            $rows = DB::all('SELECT * FROM activity ORDER BY created_at DESC LIMIT 100');
        }
        $pv('pages/user/activity', ['title' => 'Activity', 'active' => 'activity', 'logs' => $rows]);
    });

    /* ---------------------------------------------------------------- profile */

    $r->get('/profile', [Auth::class, 'requireAuth'], function (Request $req) use ($pv) {
        $u = Auth::user();
        $social = Comments::profileTree((int) $u->id, (int) $u->id);
        $cfTokens = [];
        foreach (DB::all('SELECT * FROM user_cf_tokens WHERE user_id = ? ORDER BY created_at DESC', [(int) $u->id]) as $t) {
            $row = clone $t;
            $row->token = Cloudflare::maskToken($t->token);
            $cfTokens[] = $row;
        }
        $pv('pages/user/profile', [
            'title' => 'My Profile',
            'active' => 'profile',
            'query' => nh_obj($req->query),
            'comments' => $social['tree'],
            'commentsTotal' => $social['total'],
            'cfTokens' => $cfTokens,
        ]);
    });

    $r->post('/profile', [Auth::class, 'requireAuth'], function (Request $req) {
        $u = Auth::user();
        $pic = '';
        $saved = Uploader::save($req->file('profile_pic'), 'images', 'avatars');
        if (!empty($saved['ok'])) $pic = (string) $saved['url'];
        if ($pic === '') $pic = (string) ($req->input('existing_pic') ?: ($u->profile_pic ?? ''));
        DB::run('UPDATE users SET bio = ?, profile_pic = ? WHERE id = ?', [(string) $req->input('bio', ''), $pic, (int) $u->id]);
        Auth::logActivity($u, 'Updated profile', $req);
        Response::redirect('/profile?saved=1');
    });

    $r->post('/profile/password', [Auth::class, 'requireAuth'], function (Request $req) {
        $u = Auth::user();
        if (!empty($u->is_demo)) { Response::redirect('/profile?error=demo'); return; }
        $current = (string) $req->input('current', '');
        $password = (string) $req->input('password', '');
        $confirm = (string) $req->input('confirm', '');
        $full = Auth::find((int) $u->id);   // Auth::user() never carries the hash
        if (!$full || !Auth::verify($current, (string) $full->password)) { Response::redirect('/profile?error=current'); return; }
        if ($password === '' || strlen($password) < 6) { Response::redirect('/profile?error=short'); return; }
        if ($password !== $confirm) { Response::redirect('/profile?error=match'); return; }
        DB::run('UPDATE users SET password = ? WHERE id = ?', [Auth::hash($password), (int) $u->id]);
        Auth::logActivity($u, 'Changed password', $req);
        Response::redirect('/profile?password=1');
    });

    $r->post('/profile/2fa/enable', [Auth::class, 'requireAuth'], function (Request $req) {
        $u = Auth::user();
        $code = (string) $req->input('code', '');
        $full = Auth::find((int) $u->id);
        $secret = (string) ($req->input('secret') ?: ($full->two_factor_secret ?? ''));
        if ($code === '') { Response::redirect('/profile?error=2fa-code'); return; }
        if (!Totp::verify($secret, preg_replace('/\s+/', '', $code) ?? $code)) { Response::redirect('/profile?error=2fa-invalid'); return; }
        DB::run('UPDATE users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?', [$secret, (int) $u->id]);
        Auth::logActivity($u, 'Enabled 2FA', $req);
        Response::redirect('/profile?2fa=on');
    });

    $r->post('/profile/2fa/disable', [Auth::class, 'requireAuth'], function (Request $req) {
        $u = Auth::user();
        DB::run('UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?', [(int) $u->id]);
        Auth::logActivity($u, 'Disabled 2FA', $req);
        Response::redirect('/profile?2fa=off');
    });

    $r->get('/profile/2fa/setup', [Auth::class, 'requireAuth'], function (Request $req) use ($pv) {
        $u = Auth::user();
        $full = Auth::find((int) $u->id);
        $secret = (string) (($full->two_factor_secret ?? '') ?: Totp::secret());
        $keyuri = Totp::provisioningUri($secret, (string) $u->email, (string) $u->username);
        $pv('pages/user/2fa-setup', [
            'title' => '2FA Setup',
            'active' => 'profile',
            'secret' => $secret,
            'keyuri' => $keyuri,
            'qr' => '',   // rendered client-side (assets/js/qr.js) — no server QR dependency
        ]);
    });

    /* -------------------------------------------- social · profile comments */

    $r->post('/profile/{id}/comment', [Auth::class, 'requireAuth'], function (Request $req, array $params) {
        $u = Auth::user();
        $back = safe_back($req->input('back'));
        $target = DB::get('SELECT id FROM users WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect($back); return; }
        $content = trim((string) $req->input('content', ''));
        $len = mb_strlen($content);
        if ($len < 3 || $len > 1000) {
            Response::redirect($back . (str_contains($back, '?') ? '&' : '?') . 'social_error=len');
            return;
        }
        $parent = null;
        if ($req->input('parent_id')) {
            $p = DB::get('SELECT id, parent_id FROM profile_comments WHERE id = ? AND profile_user_id = ?', [(int) $req->input('parent_id'), (int) $target->id]);
            if ($p) $parent = (int) ($p->parent_id ?: $p->id);
        }
        DB::run('INSERT INTO profile_comments (profile_user_id, author_id, parent_id, content) VALUES (?, ?, ?, ?)',
            [(int) $target->id, (int) $u->id, $parent, $content]);
        Auth::logActivity($u, 'Commented on profile #' . (int) $target->id, $req);
        Response::redirect($back);
    });

    $r->post('/profile/comment/{id}/edit', [Auth::class, 'requireAuth'], function (Request $req, array $params) {
        $u = Auth::user();
        $back = safe_back($req->input('back'));
        $c = DB::get('SELECT * FROM profile_comments WHERE id = ?', [(int) $params['id']]);
        if (!$c || (int) $c->author_id !== (int) $u->id) { Response::redirect($back); return; }
        $content = trim((string) $req->input('content', ''));
        $len = mb_strlen($content);
        if ($len >= 3 && $len <= 1000) {
            DB::run("UPDATE profile_comments SET content = ?, is_edited = 1, updated_at = datetime('now') WHERE id = ?", [$content, (int) $c->id]);
        }
        Response::redirect($back);
    });

    $r->post('/profile/comment/{id}/delete', [Auth::class, 'requireAuth'], function (Request $req, array $params) {
        $u = Auth::user();
        $back = safe_back($req->input('back'));
        $c = DB::get('SELECT * FROM profile_comments WHERE id = ?', [(int) $params['id']]);
        if (!$c || !Comments::canDelete($c, $u)) { Response::redirect($back); return; }
        $ids = array_map(fn($r) => (int) $r->id, DB::all('SELECT id FROM profile_comments WHERE id = ? OR parent_id = ?', [(int) $c->id, (int) $c->id]));
        if ($ids) {
            $q = implode(',', array_fill(0, count($ids), '?'));
            DB::run("DELETE FROM comment_reactions WHERE comment_id IN ($q)", $ids);
        }
        DB::run('DELETE FROM profile_comments WHERE id = ? OR parent_id = ?', [(int) $c->id, (int) $c->id]);
        Response::redirect($back);
    });

    $r->post('/profile/comment/{id}/react', [Auth::class, 'requireAuth'], function (Request $req, array $params) {
        $u = Auth::user();
        $back = safe_back($req->input('back'));
        $c = DB::get('SELECT id FROM profile_comments WHERE id = ?', [(int) $params['id']]);
        if (!$c) { Response::redirect($back); return; }
        $type = in_array((string) $req->input('type'), Comments::REACTION_TYPES, true) ? (string) $req->input('type') : 'like';
        $existing = DB::get('SELECT * FROM comment_reactions WHERE comment_id = ? AND user_id = ?', [(int) $c->id, (int) $u->id]);
        if (!$existing) {
            DB::run('INSERT INTO comment_reactions (comment_id, user_id, type) VALUES (?, ?, ?)', [(int) $c->id, (int) $u->id, $type]);
        } elseif ((string) $existing->type === $type) {
            DB::run('DELETE FROM comment_reactions WHERE id = ?', [(int) $existing->id]);
        } else {
            DB::run('UPDATE comment_reactions SET type = ? WHERE id = ?', [$type, (int) $existing->id]);
        }
        Response::redirect($back);
    });

    /* ------------------------------------------------------------------ team */

    $r->post('/team/create', [Auth::class, 'requireAdmin'], function (Request $req) {
        $u = Auth::user();
        $username = (string) $req->input('username', '');
        $email = (string) $req->input('email', '');
        $password = (string) $req->input('password', '');
        if ($username === '' || $email === '' || $password === '') { Response::redirect('/team?error=1'); return; }
        if (!Auth::validateUsername($username)) { Response::redirect('/team?error=invalid_username'); return; }
        if (!Auth::validateEmail($email)) { Response::redirect('/team?error=invalid_email'); return; }
        if (strlen($password) < 6) { Response::redirect('/team?error=short_password'); return; }
        if (DB::get('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email])) { Response::redirect('/team?error=exists'); return; }
        $roleId = $req->input('custom_role_id') ? (int) $req->input('custom_role_id') : null;
        Auth::createUser($username, $email, $password, 'user', ['owner_id' => (int) $u->id, 'custom_role_id' => $roleId]);
        Auth::logActivity($u, 'Team member created by ' . (string) $u->username, $req);
        Response::redirect('/team?created=1');
    });

    $r->post('/team/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM users WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/team'); return; }
        $roleId = $req->input('custom_role_id') ? (int) $req->input('custom_role_id') : null;
        $newUsername = (string) ($req->input('username') ?: $target->username);
        $newEmail = (string) ($req->input('email') ?: $target->email);
        if (nh_taken('users', 'username', $newUsername, (int) $target->id)
            || nh_taken('users', 'email', $newEmail, (int) $target->id)) {
            Response::redirect('/team?error=taken');
            return;
        }
        DB::run('UPDATE users SET username = ?, email = ?, custom_role_id = ? WHERE id = ?', [
            $newUsername,
            $newEmail,
            $roleId,
            (int) $target->id,
        ]);
        Auth::logActivity(Auth::user(), 'Edited team member ' . (string) $target->username, $req);
        Response::redirect('/team?edited=1');
    });

    $r->post('/team/{id}/suspend', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $u = Auth::user();
        $target = DB::get('SELECT * FROM users WHERE id = ?', [(int) $params['id']]);
        if (!$target || (int) $target->id === (int) $u->id) { Response::redirect('/team'); return; }
        $status = ((string) $target->status) === 'suspended' ? 'active' : 'suspended';
        DB::run('UPDATE users SET status = ? WHERE id = ?', [$status, (int) $target->id]);
        Auth::logActivity($u, ($status === 'suspended' ? 'Suspended ' : 'Activated ') . (string) $target->username, $req);
        Response::redirect('/team?suspend=1');
    });

    $r->post('/team/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $u = Auth::user();
        $target = DB::get('SELECT * FROM users WHERE id = ?', [(int) $params['id']]);
        if (!$target || (int) $target->id === (int) $u->id) { Response::redirect('/team'); return; }
        $admins = DB::count("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        if (((string) $target->role) === 'admin' && $admins <= 1) { Response::redirect('/team'); return; }
        DB::run('DELETE FROM users WHERE id = ?', [(int) $target->id]);
        Auth::logActivity($u, 'Deleted team member ' . (string) $target->username, $req);
        Response::redirect('/team?deleted=1');
    });

    $r->get('/team/member/{id}', function (Request $req, array $params) use ($pv) {
        $u = Auth::user();
        $target = DB::get('SELECT u.*, r.name AS custom_role, r.color AS custom_color FROM users u LEFT JOIN roles r ON r.id = u.custom_role_id WHERE u.id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/team'); return; }
        $social = Comments::profileTree((int) $target->id, $u ? (int) $u->id : null);
        $pv('pages/user/member-profile', [
            'title' => 'Profile · ' . (string) $target->username,
            'active' => 'team',
            'member' => $target,
            'isAdmin' => (bool) ($u && ($u->role ?? '') === 'admin'),
            'comments' => $social['tree'],
            'commentsTotal' => $social['total'],
        ]);
    });

    /* ------------------------------------------------------------- team roles */

    $r->post('/team/roles/create', [Auth::class, 'requireAdmin'], function (Request $req) {
        $cleanName = trim((string) $req->input('name', ''));
        if ($cleanName === '') { Response::redirect('/team?error=role'); return; }
        if (DB::get('SELECT id FROM roles WHERE name = ?', [$cleanName])) { Response::redirect('/team?error=roleexists'); return; }
        $maxSort = (int) DB::first('SELECT COALESCE(MAX(sort_order),0) FROM roles');
        DB::run('INSERT INTO roles (name, color, sort_order) VALUES (?, ?, ?)', [$cleanName, (string) ($req->input('color') ?: '#3b82f6'), $maxSort + 1]);
        Auth::logActivity(Auth::user(), 'Created role ' . $cleanName, $req);
        Response::redirect('/team?rolecreated=1');
    });

    $r->post('/team/roles/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $role = DB::get('SELECT * FROM roles WHERE id = ?', [(int) $params['id']]);
        if (!$role) { Response::redirect('/team'); return; }
        $roleName = trim((string) ($req->input('name') ?: $role->name));
        if (nh_taken('roles', 'name', $roleName, (int) $role->id)) {
            flash('error', 'That role name is already in use.');
            Response::redirect('/team?roleerror=taken');
            return;
        }
        DB::run('UPDATE roles SET name = ?, color = ? WHERE id = ?', [
            $roleName,
            (string) ($req->input('color') ?: $role->color),
            (int) $role->id,
        ]);
        Auth::logActivity(Auth::user(), 'Edited role ' . (string) $role->name, $req);
        Response::redirect('/team?roleedited=1');
    });

    $r->post('/team/roles/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $role = DB::get('SELECT * FROM roles WHERE id = ?', [(int) $params['id']]);
        if (!$role) { Response::redirect('/team'); return; }
        DB::run('UPDATE users SET custom_role_id = NULL WHERE custom_role_id = ?', [(int) $role->id]);
        DB::run('DELETE FROM roles WHERE id = ?', [(int) $role->id]);
        Auth::logActivity(Auth::user(), 'Deleted role ' . (string) $role->name, $req);
        Response::redirect('/team?roledeleted=1');
    });

    /* ----------------------------------------------------------------- admin */

    $r->get('/admin', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $sys = null;
        try { $sys = System::stats(); } catch (Throwable $e) { $sys = null; }
        $pv('pages/admin/dashboard', [
            'title' => 'Admin Dashboard',
            'active' => 'admin',
            'stats' => [
                'totalUsers' => DB::count('SELECT COUNT(*) FROM users'),
                'activeUsers' => DB::count("SELECT COUNT(*) FROM users WHERE status='active'"),
                'suspended' => DB::count("SELECT COUNT(*) FROM users WHERE status='suspended'"),
                'admins' => DB::count("SELECT COUNT(*) FROM users WHERE role='admin'"),
                'tutorials' => DB::count('SELECT COUNT(*) FROM tutorials'),
                'docs' => DB::count('SELECT COUNT(*) FROM docs'),
                'todayHits' => (int) DB::first("SELECT COALESCE(SUM(hits),0) FROM analytics WHERE date = date('now')"),
                'totalHits' => (int) DB::first('SELECT COALESCE(SUM(hits),0) FROM analytics'),
                'plans' => DB::count('SELECT COUNT(*) FROM plans'),
                'subscribers' => DB::count('SELECT COUNT(*) FROM subscribers'),
            ],
            'sys' => $sys,
            'recent' => DB::all('SELECT * FROM activity ORDER BY created_at DESC LIMIT 8'),
            'last7' => DB::all("SELECT page, date, SUM(hits) AS hits FROM analytics WHERE date >= date('now','-6 day') GROUP BY page, date ORDER BY date"),
        ]);
    });

    $r->get('/admin/create', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $pv('pages/admin/create', ['title' => 'Create User', 'active' => 'admin-create', 'error' => null, 'success' => null]);
    });

    $r->post('/admin/create', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $username = (string) $req->input('username', '');
        $email = (string) $req->input('email', '');
        $password = (string) $req->input('password', '');
        $fail = function (string $msg) use ($pv) {
            $pv('pages/admin/create', ['title' => 'Create User', 'active' => 'admin-create', 'error' => $msg, 'success' => null]);
        };
        if ($username === '' || $email === '' || $password === '') { $fail('All fields are required'); return; }
        if (!Auth::validateUsername($username)) { $fail('Username must be 2-32 chars (letters, numbers, _.-)'); return; }
        if (!Auth::validateEmail($email)) { $fail('Please enter a valid email address (e.g. user@example.com)'); return; }
        if (strlen($password) < 6) { $fail('Password must be at least 6 characters'); return; }
        if (DB::get('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email])) { $fail('Username or email already exists'); return; }

        $id = Auth::createUser($username, $email, $password, ((string) $req->input('role')) === 'admin' ? 'admin' : 'user');
        $created = DB::get('SELECT * FROM users WHERE id = ?', [$id]);
        Auth::logActivity($created, 'Account created by admin', $req);
        $pv('pages/admin/create', ['title' => 'Create User', 'active' => 'admin-create', 'error' => null, 'success' => 'User ' . $username . ' created successfully']);
    });


    /* ------------------------------------------------------- admin · users */

    $r->get('/admin/users', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $q = (string) ($req->query['q'] ?? '');
        $users = $q !== ''
            ? DB::all('SELECT u.*, o.username AS owner FROM users u LEFT JOIN users o ON o.id = u.owner_id WHERE u.username LIKE ? OR u.email LIKE ? ORDER BY u.created_at DESC', ['%' . $q . '%', '%' . $q . '%'])
            : DB::all('SELECT u.*, o.username AS owner FROM users u LEFT JOIN users o ON o.id = u.owner_id ORDER BY u.created_at DESC');
        $pv('pages/admin/users', ['title' => 'User Management', 'active' => 'admin-users', 'users' => $users, 'q' => $q]);
    });

    $r->post('/admin/users/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM users WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/admin/users'); return; }
        $newUsername = (string) ($req->input('username') ?: $target->username);
        $newEmail = (string) ($req->input('email') ?: $target->email);
        if (nh_taken('users', 'username', $newUsername, (int) $target->id)
            || nh_taken('users', 'email', $newEmail, (int) $target->id)) {
            Response::redirect('/admin/users?error=taken');
            return;
        }
        DB::run('UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?', [
            $newUsername,
            $newEmail,
            ((string) $req->input('role')) === 'admin' ? 'admin' : 'user',
            (int) $target->id,
        ]);
        Auth::logActivity(Auth::user(), 'Edited user ' . (string) $target->username, $req);
        Response::redirect('/admin/users?edited=1');
    });

    $r->post('/admin/users/{id}/suspend', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $u = Auth::user();
        $target = DB::get('SELECT * FROM users WHERE id = ?', [(int) $params['id']]);
        if (!$target || (int) $target->id === (int) $u->id) { Response::redirect('/admin/users'); return; }
        $status = ((string) $target->status) === 'suspended' ? 'active' : 'suspended';
        DB::run('UPDATE users SET status = ? WHERE id = ?', [$status, (int) $target->id]);
        Auth::logActivity($u, ($status === 'suspended' ? 'Suspended ' : 'Activated ') . (string) $target->username, $req);
        Response::redirect('/admin/users?suspend=1');
    });

    $r->post('/admin/users/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $u = Auth::user();
        $target = DB::get('SELECT * FROM users WHERE id = ?', [(int) $params['id']]);
        if (!$target || (int) $target->id === (int) $u->id) { Response::redirect('/admin/users'); return; }
        DB::run('DELETE FROM users WHERE id = ?', [(int) $target->id]);
        Auth::logActivity($u, 'Deleted user ' . (string) $target->username, $req);
        Response::redirect('/admin/users?deleted=1');
    });

    $r->get('/admin/activity', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $pv('pages/admin/activity', [
            'title' => 'Activity Log',
            'active' => 'admin-activity',
            'logs' => DB::all('SELECT * FROM activity ORDER BY created_at DESC LIMIT 200'),
        ]);
    });

    /* ------------------------------------------------------ admin · settings */

    $THEMES = ['dark', 'light', 'rainbow', 'neon', 'sunset', 'ocean', 'nature', 'candy', 'fire', 'galaxy', 'luxury', 'pastel'];
    $AUTO_SAVE_KEYS = ['panel_blur', 'transparency', 'theme', 'card_radius', 'accent_color', 'background_url', 'background_type', 'background_source', 'panel_name', 'logo_type', 'logo_emoji', 'logo_url', 'favicon_url'];

    $r->get('/admin/settings', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv, $getFavs) {
        $s = Settings::all(true);
        $pv('pages/admin/settings', [
            'title' => 'Settings Management',
            'active' => 'admin-settings',
            'settings' => $s,
            'sources' => Settings::WALLPAPER_SOURCES,
            'saved' => !empty($req->query['saved']),
            'overlay' => Settings::overlayAlpha($s),
            'glass' => Settings::normalizeBlur($s),
            'wpCats' => Wallpapers::CATEGORIES,
            'favs' => $getFavs(),
        ]);
    });

    $r->post('/admin/settings', [Auth::class, 'requireAdmin'], function (Request $req) use ($THEMES) {
        $b = $req->body;
        $on = fn(string $k): string => !empty($b[$k]) ? 'on' : 'off';
        $str = fn(string $k, string $d = ''): string => (string) (($b[$k] ?? '') !== '' ? $b[$k] : $d);

        $upd = [
            'panel_name' => (string) ($b['panel_name'] ?? ''),
            'panel_tagline' => (string) ($b['panel_tagline'] ?? ''),
            'logo_type' => $str('logo_type', 'emoji'),
            'logo_url' => $str('logo_url'),
            'logo_emoji' => $str('logo_emoji', '🔷'),
            'favicon_url' => $str('favicon_url'),
            'background_type' => $str('background_type', 'image'),
            'background_url' => $str('background_url'),
            'background_source' => $str('background_source', 'none'),
            'background_overlay' => $on('background_overlay'),
            'panel_blur' => (string) Settings::normalizeBlur((object) $b),
            'transparency' => $str('transparency', '100'),
            'theme' => in_array((string) ($b['theme'] ?? ''), $THEMES, true) ? (string) $b['theme'] : 'dark',
            'music_type' => $str('music_type', 'none'),
            'music_url' => $str('music_url'),
            'music_volume' => $str('music_volume', '40'),
            'transparent_bar' => $on('transparent_bar'),
            'blur_bar' => $on('blur_bar'),
            'card_radius' => $str('card_radius', '16'),
            'accent_color' => $str('accent_color', '#3b82f6'),
            'register_open' => $on('register_open'),
            'maintenance' => $on('maintenance'),
            'script_enabled' => $on('script_enabled'),
            'script_badge' => $str('script_badge'),
            'script_description' => $str('script_description'),
            'script_command' => $str('script_command'),
            'discord_enabled' => $on('discord_enabled'),
            'discord_server_id' => $str('discord_server_id'),
            'discord_channel' => $str('discord_channel'),
            'discord_theme' => $str('discord_theme', 'dark'),
            'youtube_enabled' => $on('youtube_enabled'),
            'youtube_channel' => $str('youtube_channel'),
            'youtube_api_key' => $str('youtube_api_key'),
            'instagram_handle' => $str('instagram_handle'),
            'smtp_host' => $str('smtp_host'),
            'smtp_port' => $str('smtp_port', '587'),
            'smtp_secure' => !empty($b['smtp_secure']) ? 'true' : 'false',
            'smtp_user' => $str('smtp_user'),
            'smtp_pass' => $str('smtp_pass'),
            'mail_from' => $str('mail_from'),
            'mail_enabled' => $on('mail_enabled'),
            'cookie_banner' => $on('cookie_banner'),
            'anti_adblock' => $on('anti_adblock'),
            'inject_body_code' => mb_substr((string) ($b['inject_body_code'] ?? ''), 0, 5000),
        ];

        $logo = Uploader::save($req->file('logo_file'), 'images', 'branding');
        if (!empty($logo['ok'])) $upd['logo_url'] = (string) $logo['url'];
        $fav = Uploader::save($req->file('favicon_file'), 'images', 'branding');
        if (!empty($fav['ok'])) $upd['favicon_url'] = (string) $fav['url'];
        $bgFile = $req->file('background_file');
        $bg = Uploader::save($bgFile, 'media', 'backgrounds');
        if (!empty($bg['ok'])) {
            $upd['background_url'] = (string) $bg['url'];
            $upd['background_type'] = preg_match('/video/i', (string) ($bgFile['type'] ?? '')) ? 'video' : 'image';
        }
        $music = Uploader::save($req->file('music_file'), 'media', 'music');
        if (!empty($music['ok'])) {
            $upd['music_url'] = (string) $music['url'];
            $upd['music_type'] = 'url';
        }

        Settings::setMany($upd);
        Auth::logActivity(Auth::user(), 'Updated panel settings', $req);
        Response::redirect('/admin/settings?saved=1');
    });

    $r->post('/admin/settings/api', [Auth::class, 'requireAdmin'], function (Request $req) use ($THEMES, $AUTO_SAVE_KEYS) {
        try {
            $b = is_array($req->body) ? $req->body : [];
            foreach (array_keys($b) as $k) {
                if (!in_array((string) $k, $AUTO_SAVE_KEYS, true)) continue;
                if ($k === 'theme') {
                    $t = (string) $b[$k];
                    Settings::set($k, in_array($t, $THEMES, true) ? $t : 'dark');
                } elseif ($k === 'panel_blur') {
                    Settings::set($k, (string) Settings::normalizeBlur((object) $b));
                } elseif ($k === 'transparency') {
                    $t = min(100, max(0, (int) ($b[$k] ?: 100)));
                    Settings::set($k, (string) $t);
                } else {
                    Settings::set((string) $k, (string) $b[$k]);
                }
            }
            Response::json(['ok' => true]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    });

    /* ----------------------------------------------------- admin · wallpapers */

    $r->get('/admin/wallpapers', [Auth::class, 'requireAdmin'], function (Request $req) use ($getFavs) {
        if ((string) ($req->query['fav'] ?? '') === '1') {
            Response::json(['ok' => true, 'favs' => $getFavs()]);
            return;
        }
        $page = max(1, (int) ($req->query['page'] ?? 1) ?: 1);
        try {
            $data = Wallpapers::fetch([
                'category' => (string) ($req->query['category'] ?? 'all'),
                'page' => $page,
                'q' => (string) ($req->query['q'] ?? ''),
            ]);
            $favs = $getFavs();
            $favIds = [];
            foreach ($favs as $f) $favIds[(string) (is_array($f) ? ($f['id'] ?? '') : ($f->id ?? ''))] = true;
            $items = [];
            foreach ((array) ($data['items'] ?? []) as $it) {
                $id = (string) (is_array($it) ? ($it['id'] ?? '') : ($it->id ?? ''));
                $items[] = is_array($it) ? array_merge($it, ['fav' => isset($favIds[$id])]) : (function ($o) use ($favIds, $id) { $o->fav = isset($favIds[$id]); return $o; })($it);
            }
            Response::json([
                'ok' => true,
                'items' => $items,
                'page' => $data['page'] ?? $page,
                'hasNext' => $data['hasNext'] ?? false,
                'totalPages' => $data['totalPages'] ?? null,
                'category' => (string) ($req->query['category'] ?? 'all'),
                'categories' => Wallpapers::CATEGORIES,
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage() ?: 'Wallpaper source unreachable'], 502);
        }
    });

    $r->post('/admin/wallpapers/fav', [Auth::class, 'requireAdmin'], function (Request $req) use ($getFavs, $saveFavs) {
        try {
            $favs = $getFavs();
            $b = is_array($req->body) ? $req->body : [];
            if (($b['action'] ?? '') === 'clear') {
                $saveFavs([]);
                Response::json(['ok' => true, 'favs' => []]);
                return;
            }
            $item = is_array($b['item'] ?? null) ? $b['item'] : [];
            $idx = -1;
            foreach ($favs as $i => $f) {
                $fid = (string) (is_array($f) ? ($f['id'] ?? '') : ($f->id ?? ''));
                if ($fid !== '' && $fid === (string) ($item['id'] ?? '')) { $idx = $i; break; }
            }
            if (($b['action'] ?? '') === 'remove' || $idx > -1) {
                if ($idx > -1) array_splice($favs, $idx, 1);
            } elseif (!empty($item['id']) && !empty($item['full'])) {
                $favs[] = [
                    'id' => $item['id'],
                    'title' => $item['title'] ?? 'Wallpaper',
                    'thumb' => $item['thumb'] ?? '',
                    'full' => $item['full'],
                    'detail' => $item['detail'] ?? '',
                    'category' => $item['category'] ?? '',
                    'fav' => true,
                ];
            }
            $saveFavs($favs);
            Response::json(['ok' => true, 'favs' => $favs]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    });

    $r->post('/admin/settings/reset', [Auth::class, 'requireAdmin'], function (Request $req) {
        try {
            Settings::setMany([
                'background_url' => '',
                'background_source' => 'none',
                'background_type' => 'image',
                'panel_blur' => '16',
                'transparency' => '100',
                'theme' => 'dark',
                'card_radius' => '16',
                'accent_color' => '#3b82f6',
            ]);
            Auth::logActivity(Auth::user(), 'Reset panel appearance to defaults', $req);
            Response::json(['ok' => true]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    });

    /* ------------------------------------------------------- admin · content */

    $r->get('/admin/settings/content', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv, $CONTENT_SLUGS) {
        $editing = !empty($req->query['edit']) ? DB::get('SELECT * FROM content_pages WHERE slug = ?', [(string) $req->query['edit']]) : null;
        $pv('pages/admin/content', [
            'title' => 'Content Management',
            'active' => 'admin-content',
            'pages' => DB::all('SELECT * FROM content_pages ORDER BY id'),
            'editing' => $editing,
            'contentSlugs' => $CONTENT_SLUGS,
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/admin/settings/content/create', [Auth::class, 'requireAdmin'], function (Request $req) {
        $u = Auth::user();
        $cleanTitle = trim((string) $req->input('title', ''));
        $slug = (string) $req->input('slug', '');
        if ($cleanTitle === '' || $slug === '') { Response::redirect('/admin/settings/content?error=create'); return; }
        $cleanSlug = strtolower($slug);
        $cleanSlug = (string) preg_replace('/[^a-z0-9-]/', '', $cleanSlug);
        $cleanSlug = (string) preg_replace('/-{2,}/', '-', $cleanSlug);
        $cleanSlug = trim($cleanSlug, '-');
        if ($cleanSlug === '' || strlen($cleanSlug) > 60) { Response::redirect('/admin/settings/content?error=create'); return; }
        if (DB::get('SELECT id FROM content_pages WHERE slug = ?', [$cleanSlug])) { Response::redirect('/admin/settings/content?error=exists'); return; }
        DB::run("INSERT INTO content_pages (slug, title, content, icon, in_nav, updated_at, updated_by) VALUES (?, ?, ?, ?, ?, datetime('now'), ?)", [
            $cleanSlug,
            $cleanTitle,
            (string) $req->input('content', ''),
            mb_substr(trim((string) $req->input('icon', '')), 0, 8) ?: '📄',
            $req->input('in_nav') ? 1 : 0,
            (int) ($u->id ?? 0),
        ]);
        Auth::logActivity($u, 'Created custom page "' . $cleanSlug . '"', $req);
        Response::redirect('/admin/settings/content?edit=' . rawurlencode($cleanSlug) . '&created=1');
    });

    $r->post('/admin/settings/content/{slug}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) use ($CONTENT_SLUGS) {
        $slug = strtolower((string) $params['slug']);
        if (in_array($slug, $CONTENT_SLUGS, true)) { Response::redirect('/admin/settings/content?error=system'); return; }
        DB::run('DELETE FROM content_pages WHERE slug = ?', [$slug]);
        Auth::logActivity(Auth::user(), 'Deleted custom page "' . $slug . '"', $req);
        Response::redirect('/admin/settings/content?deleted=1');
    });

    $r->post('/admin/settings/content/{slug}', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $u = Auth::user();
        $slug = (string) $params['slug'];
        $page = DB::get('SELECT * FROM content_pages WHERE slug = ?', [$slug]);
        if (!$page) { Response::redirect('/admin/settings/content'); return; }
        DB::run("UPDATE content_pages SET title = ?, content = ?, icon = ?, in_nav = ?, updated_at = datetime('now'), updated_by = ? WHERE slug = ?", [
            trim((string) $req->input('title', '')) ?: (string) $page->title,
            (string) $req->input('content', ''),
            mb_substr(trim((string) $req->input('icon', '')), 0, 8) ?: ((string) ($page->icon ?: '📄')),
            $req->input('in_nav') ? 1 : 0,
            (int) ($u->id ?? 0),
            $slug,
        ]);
        Auth::logActivity($u, 'Updated content page "' . $slug . '"', $req);
        Response::redirect('/admin/settings/content?edit=' . rawurlencode($slug) . '&saved=1');
    });

    /* ----------------------------------------------------- admin · tutorials */

    $r->get('/admin/settings/tutorials', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $pv('pages/admin/tutorials', [
            'title' => 'Tutorials Management',
            'active' => 'admin-tutorials',
            'tutorials' => DB::all('SELECT t.*, u.username AS author FROM tutorials t LEFT JOIN users u ON u.id = t.author_id ORDER BY t.created_at DESC'),
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/admin/settings/tutorials', [Auth::class, 'requireAdmin'], function (Request $req) {
        $u = Auth::user();
        $title = (string) $req->input('title', '');
        if ($title === '') { Response::redirect('/admin/settings/tutorials?error=1'); return; }
        DB::run('INSERT INTO tutorials (title, description, video_url, thumbnail, author_id) VALUES (?, ?, ?, ?, ?)', [
            $title,
            (string) $req->input('description', ''),
            (string) $req->input('video_url', ''),
            (string) $req->input('thumbnail', ''),
            (int) ($u->id ?? 0),
        ]);
        Auth::logActivity($u, 'Added tutorial "' . $title . '"', $req);
        Response::redirect('/admin/settings/tutorials?saved=1');
    });

    $r->post('/admin/settings/tutorials/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        DB::run('DELETE FROM tutorials WHERE id = ?', [(int) $params['id']]);
        Auth::logActivity(Auth::user(), 'Deleted a tutorial', $req);
        Response::redirect('/admin/settings/tutorials?deleted=1');
    });


    /* ------------------------------------------------------- admin · cloudflare */

    /** Masked copies of every user's stored Cloudflare tokens (admin views). */
    $cfUserTokens = function (): array {
        $out = [];
        foreach (DB::all('SELECT t.*, u.username FROM user_cf_tokens t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC') as $t) {
            $row = clone $t;
            $row->token = Cloudflare::maskToken((string) $t->token);
            $out[] = $row;
        }
        return $out;
    };

    $r->get('/admin/cloudflare', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv, $cfUserTokens) {
        $cfg = Cloudflare::getConfig();
        $data = ['conn' => null, 'connError' => null, 'zone' => null, 'analytics' => [], 'analyticsError' => null, 'zt' => null, 'ztError' => null];
        $zonesCount = 0;
        if ($cfg['apiToken'] !== '') {
            try { $data['conn'] = nh_obj(Cloudflare::verifyToken()); } catch (Throwable $e) { $data['connError'] = $e->getMessage(); }
            try { $zonesCount = count(Cloudflare::listZones()); } catch (Throwable $e) { /* optional */ }
            try { $data['zone'] = nh_obj(Cloudflare::zoneInfo()); } catch (Throwable $e) { /* zone optional */ }
        }
        if ($cfg['apiToken'] !== '' && $cfg['zoneId'] !== '') {
            try { $data['analytics'] = Cloudflare::webAnalytics(); } catch (Throwable $e) { $data['analyticsError'] = $e->getMessage(); }
        }
        $pv('pages/admin/cloudflare', [
            'title' => 'Cloudflare',
            'active' => 'admin-cloudflare',
            'cfg' => $cfg,
            'data' => $data,
            'userTokens' => $cfUserTokens(),
            'zonesCount' => $zonesCount,
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/admin/cloudflare/settings', [Auth::class, 'requireAdmin'], function (Request $req) use ($clip) {
        $b = $req->body;
        Cloudflare::saveConfig([
            'email' => $clip($b['email'] ?? '', 200),
            'authMode' => (($b['authMode'] ?? '') === 'global') ? 'global' : 'token',
            'apiToken' => $clip($b['apiToken'] ?? '', 200),
            'accountId' => $clip($b['accountId'] ?? '', 64),
            'zoneId' => $clip($b['zoneId'] ?? '', 64),
            'analytics_enabled' => !empty($b['analytics_enabled']),
            'analyticsToken' => $clip($b['analyticsToken'] ?? '', 100),
            'zerotrust_enabled' => !empty($b['zerotrust_enabled']),
            'ztTeam' => $clip($b['ztTeam'] ?? '', 200),
        ]);
        Auth::logActivity(Auth::user(), 'Updated Cloudflare settings', $req);
        Response::redirect('/admin/cloudflare?saved=1');
    });

    $r->post('/admin/cloudflare/test', [Auth::class, 'requireAdmin'], function (Request $req) {
        try {
            $result = Cloudflare::verifyToken();
            Response::json(['ok' => true, 'status' => $result['status'] ?? '']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    });

    $r->post('/admin/cloudflare/detect', [Auth::class, 'requireAdmin'], function (Request $req) {
        try {
            $cfg = Cloudflare::getConfig();
            if ($cfg['apiToken'] === '') { Response::json(['ok' => false, 'error' => 'Save an API token first']); return; }
            $zones = Cloudflare::listZones();
            if (!$zones) { Response::json(['ok' => false, 'error' => 'No zones visible to this token']); return; }
            $z = $zones[0];
            Cloudflare::saveConfig([
                'accountId' => (string) ($z['account_id'] ?? ''),
                'zoneId' => (string) ($z['id'] ?? ''),
                'email' => $cfg['email'],
                'authMode' => $cfg['authMode'],
                'apiToken' => '',   // keep the stored token (saveConfig ignores empty/masked values)
                'analyticsToken' => $cfg['analyticsToken'],
                'ztTeam' => $cfg['ztTeam'],
                'analytics_enabled' => true,
                'zerotrust_enabled' => $cfg['ztEnabled'],
            ]);
            Auth::logActivity(Auth::user(), 'Auto-detected Cloudflare zone ' . (string) ($z['name'] ?? ''), $req);
            Response::json([
                'ok' => true,
                'zone' => $z['name'] ?? '',
                'zone_status' => $z['status'] ?? '',
                'account_name' => $z['account_name'] ?? '',
                'zones' => array_map(fn($x) => (string) ($x['name'] ?? ''), $zones),
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    });

    $r->post('/admin/cloudflare/switch-zone', [Auth::class, 'requireAdmin'], function (Request $req) {
        $zid = mb_substr(trim((string) $req->input('zoneId', '')), 0, 64);
        $cfg = Cloudflare::getConfig();
        Cloudflare::saveConfig([
            'email' => $cfg['email'],
            'authMode' => $cfg['authMode'],
            'accountId' => $cfg['accountId'],
            'zoneId' => $zid,
            'analyticsToken' => $cfg['analyticsToken'],
            'ztTeam' => $cfg['ztTeam'],
            'analytics_enabled' => true,
            'zerotrust_enabled' => $cfg['ztEnabled'],
        ]);
        Response::redirect($req->referer !== '' ? safe_back(parse_url($req->referer, PHP_URL_PATH), '/admin/cloudflare/domains') : '/admin/cloudflare/domains');
    });

    /* Cloudflare · Zero Trust */

    $r->get('/admin/cloudflare/zerotrust', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $tab = ((string) ($req->query['tab'] ?? '')) === 'analytics' ? 'analytics' : 'management';
        $apps = []; $users = []; $devices = []; $summary = null; $error = null;
        try {
            if ($tab === 'management') {
                $apps = Cloudflare::ztApps();
                $users = Cloudflare::ztUsers();
                $devices = Cloudflare::ztDevices();
            } else {
                $summary = Cloudflare::zeroTrust();
            }
        } catch (Throwable $e) { $error = $e->getMessage(); }
        $pv('pages/admin/cf-zerotrust', [
            'title' => 'Zero Trust', 'active' => 'admin-cloudflare', 'tab' => $tab,
            'apps' => $apps, 'users' => $users, 'devices' => $devices,
            'summary' => $summary, 'error' => $error, 'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/admin/cloudflare/zerotrust/app/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        try {
            Cloudflare::ztAppDelete((string) $params['id']);
        } catch (Throwable $e) {
            Response::redirect('/admin/cloudflare/zerotrust?error=' . rawurlencode($e->getMessage()));
            return;
        }
        Auth::logActivity(Auth::user(), 'Deleted a Cloudflare Access app', $req);
        Response::redirect('/admin/cloudflare/zerotrust?deleted=1');
    });

    /* Cloudflare · Domains */

    $r->get('/admin/cloudflare/domains', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $tab = ((string) ($req->query['tab'] ?? '')) === 'analytics' ? 'analytics' : 'management';
        $zones = []; $stats = []; $error = null;
        try {
            if ($tab === 'management') $zones = Cloudflare::listZones();
            else $stats = Cloudflare::domainStats();
        } catch (Throwable $e) { $error = $e->getMessage(); }
        $pv('pages/admin/cf-domains', [
            'title' => 'Domains', 'active' => 'admin-cloudflare', 'tab' => $tab,
            'zones' => $zones, 'stats' => $stats, 'error' => $error, 'query' => nh_obj($req->query),
        ]);
    });

    /* Cloudflare · DNS */

    $cfZonesList = function (): array {
        try { return Cloudflare::listZones(); } catch (Throwable $e) { return []; }
    };

    $r->get('/admin/cloudflare/dns', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv, $cfZonesList) {
        $tab = ((string) ($req->query['tab'] ?? '')) === 'analytics' ? 'analytics' : 'management';
        $records = []; $analytics = []; $error = null;
        try {
            if ($tab === 'management') $records = Cloudflare::dnsList();
            else $analytics = Cloudflare::webAnalytics();
        } catch (Throwable $e) { $error = $e->getMessage(); }
        $pv('pages/admin/cf-dns', [
            'title' => 'DNS Records', 'active' => 'admin-cloudflare', 'tab' => $tab,
            'records' => $records, 'analytics' => $analytics, 'error' => $error, 'query' => nh_obj($req->query),
            'zonesList' => $cfZonesList(), 'currentZoneId' => Cloudflare::getConfig()['zoneId'],
        ]);
    });

    $r->post('/admin/cloudflare/dns', [Auth::class, 'requireAdmin'], function (Request $req) {
        try {
            Cloudflare::dnsCreate([
                'type' => (string) $req->input('type', 'A'),
                'name' => (string) $req->input('name', ''),
                'content' => (string) $req->input('content', ''),
                'ttl' => (string) $req->input('ttl', '1'),
                'proxied' => (string) $req->input('proxied', ''),
            ]);
            Auth::logActivity(Auth::user(), 'Created DNS ' . (string) $req->input('type', 'A') . ' record for ' . (string) $req->input('name', ''), $req);
        } catch (Throwable $e) {
            Response::redirect('/admin/cloudflare/dns?error=' . rawurlencode($e->getMessage()));
            return;
        }
        Response::redirect('/admin/cloudflare/dns?created=1');
    });

    $r->post('/admin/cloudflare/dns/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        try {
            Cloudflare::dnsUpdate((string) $params['id'], [
                'type' => (string) $req->input('type', 'A'),
                'name' => (string) $req->input('name', ''),
                'content' => (string) $req->input('content', ''),
                'ttl' => (string) $req->input('ttl', '1'),
                'proxied' => (string) $req->input('proxied', ''),
            ]);
            Auth::logActivity(Auth::user(), 'Updated DNS record ' . (string) $req->input('name', ''), $req);
        } catch (Throwable $e) {
            Response::redirect('/admin/cloudflare/dns?error=' . rawurlencode($e->getMessage()));
            return;
        }
        Response::redirect('/admin/cloudflare/dns?saved=1');
    });

    $r->post('/admin/cloudflare/dns/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        try {
            Cloudflare::dnsDelete((string) $params['id']);
            Auth::logActivity(Auth::user(), 'Deleted a DNS record', $req);
        } catch (Throwable $e) {
            Response::redirect('/admin/cloudflare/dns?error=' . rawurlencode($e->getMessage()));
            return;
        }
        Response::redirect('/admin/cloudflare/dns?deleted=1');
    });

    /* Cloudflare · DDoS */

    $r->get('/admin/cloudflare/ddos', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv, $cfZonesList) {
        $tab = ((string) ($req->query['tab'] ?? '')) === 'management' ? 'management' : 'analytics';
        $analytics = []; $settingsMap = []; $error = null;
        try {
            if ($tab === 'analytics') $analytics = Cloudflare::webAnalytics();
            else $settingsMap = Cloudflare::zoneSettings();
        } catch (Throwable $e) { $error = $e->getMessage(); }
        $pv('pages/admin/cf-ddos', [
            'title' => 'DDoS Protection', 'active' => 'admin-cloudflare', 'tab' => $tab,
            'analytics' => $analytics, 'settingsMap' => nh_obj($settingsMap), 'error' => $error, 'query' => nh_obj($req->query),
            'zonesList' => $cfZonesList(), 'currentZoneId' => Cloudflare::getConfig()['zoneId'],
        ]);
    });

    $r->post('/admin/cloudflare/ddos/level', [Auth::class, 'requireAdmin'], function (Request $req) {
        $level = (string) ($req->input('level') ?: 'medium');
        try {
            Cloudflare::zoneSetSetting('security_level', $level);
            Auth::logActivity(Auth::user(), 'Set Cloudflare security level to ' . $level, $req);
        } catch (Throwable $e) {
            Response::redirect('/admin/cloudflare/ddos?tab=management&error=' . rawurlencode($e->getMessage()));
            return;
        }
        Response::redirect('/admin/cloudflare/ddos?tab=management&saved=1');
    });

    /* Cloudflare · Security */

    $r->get('/admin/cloudflare/security', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv, $cfZonesList) {
        $tab = ((string) ($req->query['tab'] ?? '')) === 'analytics' ? 'analytics' : 'management';
        $rules = []; $arules = []; $events = []; $settingsMap = []; $error = null;
        try {
            if ($tab === 'management') {
                $rules = Cloudflare::fwRules();
                $arules = Cloudflare::accessRules();
            } else {
                $events = Cloudflare::securityEvents();
                $settingsMap = Cloudflare::zoneSettings();
            }
        } catch (Throwable $e) { $error = $e->getMessage(); }
        $pv('pages/admin/cf-security', [
            'title' => 'Security', 'active' => 'admin-cloudflare', 'tab' => $tab,
            'rules' => $rules, 'arules' => $arules, 'events' => $events, 'settingsMap' => nh_obj($settingsMap),
            'error' => $error, 'query' => nh_obj($req->query),
            'zonesList' => $cfZonesList(), 'currentZoneId' => Cloudflare::getConfig()['zoneId'],
        ]);
    });

    $r->post('/admin/cloudflare/security/access', [Auth::class, 'requireAdmin'], function (Request $req) {
        $mode = (string) $req->input('mode', 'block');
        try {
            Cloudflare::accessRuleCreate([
                'mode' => $mode,
                'value' => (string) $req->input('value', ''),
                'notes' => (string) $req->input('notes', ''),
            ]);
            Auth::logActivity(Auth::user(), 'Added Cloudflare IP access rule (' . $mode . ')', $req);
        } catch (Throwable $e) {
            Response::redirect('/admin/cloudflare/security?error=' . rawurlencode($e->getMessage()));
            return;
        }
        Response::redirect('/admin/cloudflare/security?created=1');
    });

    $r->post('/admin/cloudflare/security/access/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        try {
            Cloudflare::accessRuleDelete((string) $params['id']);
            Auth::logActivity(Auth::user(), 'Removed a Cloudflare IP access rule', $req);
        } catch (Throwable $e) {
            Response::redirect('/admin/cloudflare/security?error=' . rawurlencode($e->getMessage()));
            return;
        }
        Response::redirect('/admin/cloudflare/security?deleted=1');
    });

    $r->post('/admin/cloudflare/security/rule/{id}/toggle', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        try {
            Cloudflare::fwRuleToggle((string) $params['id'], ((string) $req->input('paused', '')) === '1');
        } catch (Throwable $e) {
            Response::redirect('/admin/cloudflare/security?error=' . rawurlencode($e->getMessage()));
            return;
        }
        Response::redirect('/admin/cloudflare/security');
    });

    $r->post('/admin/cloudflare/security/rule/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        try {
            Cloudflare::fwRuleDelete((string) $params['id']);
            Auth::logActivity(Auth::user(), 'Deleted a Cloudflare firewall rule', $req);
        } catch (Throwable $e) {
            Response::redirect('/admin/cloudflare/security?error=' . rawurlencode($e->getMessage()));
            return;
        }
        Response::redirect('/admin/cloudflare/security?deleted=1');
    });

    /* Cloudflare · zone settings page */

    $r->get('/admin/cloudflare/settings-page', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv, $cfUserTokens) {
        $cfg = Cloudflare::getConfig();
        $settingsMap = []; $error = null; $zone = null;
        try { $settingsMap = Cloudflare::zoneSettings(); } catch (Throwable $e) { $error = $e->getMessage(); }
        try { $zone = Cloudflare::zoneInfo(); } catch (Throwable $e) { /* optional */ }
        $pv('pages/admin/cf-settings', [
            'title' => 'CF Settings', 'active' => 'admin-cloudflare',
            'cfg' => $cfg, 'settingsMap' => nh_obj($settingsMap), 'error' => $error,
            'userTokens' => $cfUserTokens(),
            'maskedApiToken' => $cfg['apiToken'] !== '' ? Cloudflare::maskToken($cfg['apiToken']) : '',
            'data' => nh_obj(['zone' => $zone ? nh_obj($zone) : null]),
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/admin/cloudflare/settings-page/update', [Auth::class, 'requireAdmin'], function (Request $req) {
        $allowed = ['security_level', 'ssl', 'cache_level', 'always_online', 'browser_check', 'development_mode', 'automatic_https_rewrites', 'brotli', 'early_hints', 'http2', 'http3', '0rtt', 'ipv6', 'websockets', 'min_tls_version'];
        $updates = [];
        foreach (array_keys($req->body) as $k) {
            if (in_array((string) $k, $allowed, true)) $updates[] = [(string) $k, $req->body[$k]];
        }
        $errors = [];
        foreach ($updates as [$k, $v]) {
            try { Cloudflare::zoneSetSetting($k, $v); } catch (Throwable $e) { $errors[] = $k . ': ' . $e->getMessage(); }
        }
        if ($updates) {
            Auth::logActivity(Auth::user(), 'Updated Cloudflare zone settings (' . implode(', ', array_map(fn($u) => $u[0], $updates)) . ')', $req);
        }
        if ($errors) {
            Response::redirect('/admin/cloudflare/settings-page?error=' . rawurlencode((string) $errors[0]));
            return;
        }
        Response::redirect('/admin/cloudflare/settings-page?saved=1');
    });

    /* Cloudflare · accounts */

    $r->get('/admin/cloudflare/accounts', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $tab = ((string) ($req->query['tab'] ?? '')) === 'analytics' ? 'analytics' : 'management';
        $accs = []; $members = []; $stats = []; $error = null;
        try {
            $accs = Cloudflare::accounts();
            if ($tab === 'management') {
                $accId = (string) (Cloudflare::getConfig()['accountId'] ?: ($accs[0]['id'] ?? ''));
                if ($accId !== '') $members = Cloudflare::accountMembers($accId);
            } else {
                $stats = Cloudflare::domainStats(20);
            }
        } catch (Throwable $e) { $error = $e->getMessage(); }
        $pv('pages/admin/cf-accounts', [
            'title' => 'Accounts', 'active' => 'admin-cloudflare', 'tab' => $tab,
            'accs' => $accs, 'members' => $members, 'stats' => $stats, 'error' => $error,
            'currentAccountId' => Cloudflare::getConfig()['accountId'], 'query' => nh_obj($req->query),
        ]);
    });

    /* -------------------------------------------- user Cloudflare API tokens */

    $r->post('/profile/cf-token', [Auth::class, 'requireAuth'], function (Request $req) {
        $u = Auth::user();
        $token = mb_substr(trim((string) $req->input('token', '')), 0, 200);
        $label = mb_substr(trim((string) $req->input('label', '')), 0, 60);
        if ($token === '') { Response::redirect('/profile?cferror=1'); return; }
        if (DB::count('SELECT COUNT(*) FROM user_cf_tokens WHERE user_id = ?', [(int) $u->id]) >= 5) {
            Response::redirect('/profile?cflimit=1');
            return;
        }
        DB::run('INSERT INTO user_cf_tokens (user_id, label, token) VALUES (?, ?, ?)', [(int) $u->id, $label, $token]);
        Auth::logActivity($u, 'Added a Cloudflare API token', $req);
        Response::redirect('/profile?cfsaved=1#cloudflare');
    });

    $r->post('/profile/cf-token/{id}/delete', [Auth::class, 'requireAuth'], function (Request $req, array $params) {
        $u = Auth::user();
        $row = DB::get('SELECT * FROM user_cf_tokens WHERE id = ?', [(int) $params['id']]);
        if (!$row || ((int) $row->user_id !== (int) $u->id && ($u->role ?? '') !== 'admin')) {
            Response::redirect('/profile?cferror=1');
            return;
        }
        DB::run('DELETE FROM user_cf_tokens WHERE id = ?', [(int) $row->id]);
        Auth::logActivity($u, 'Removed a Cloudflare API token', $req);
        Response::redirect('/profile?cfdeleted=1#cloudflare');
    });


    /* ------------------------------------------------------------------ plans */

    $r->get('/plans', [[Auth::class, 'requireAdmin'], Auth::track('plans')], function (Request $req) use ($pv) {
        $pv('pages/plans/dashboard', [
            'title' => 'Plans Dashboard',
            'active' => 'plans',
            'stats' => [
                'totalPlans' => DB::count('SELECT COUNT(*) FROM plans WHERE deleted = 0'),
                'activePlans' => DB::count("SELECT COUNT(*) FROM plans WHERE deleted = 0 AND status = 'active'"),
                'totalSubscribers' => DB::count('SELECT COUNT(*) FROM subscribers'),
                'activeSubscribers' => DB::count("SELECT COUNT(*) FROM subscribers WHERE status = 'active'"),
                'expiredSubscriptions' => DB::count("SELECT COUNT(*) FROM subscribers WHERE status = 'expired'"),
                'totalCoupons' => DB::count('SELECT COUNT(*) FROM coupons'),
                'activeCoupons' => DB::count("SELECT COUNT(*) FROM coupons WHERE status = 'active'"),
                'monthlyRevenue' => (float) DB::first("SELECT COALESCE(SUM(total),0) FROM transactions WHERE status = 'completed' AND date(created_at) >= date('now','start of month')"),
                'totalRevenue' => (float) DB::first("SELECT COALESCE(SUM(total),0) FROM transactions WHERE status = 'completed'"),
            ],
            'recentTransactions' => DB::all('SELECT * FROM transactions ORDER BY created_at DESC LIMIT 5'),
        ]);
    });

    $r->get('/plans/all', [[Auth::class, 'requireAdmin'], Auth::track('plans')], function (Request $req) use ($pv) {
        $q = trim((string) ($req->query['q'] ?? ''));
        $catFilter = trim((string) ($req->query['category'] ?? ''));
        $page = max(1, (int) ($req->query['page'] ?? 1) ?: 1);
        $limit = 15;
        $offset = ($page - 1) * $limit;

        $where = 'WHERE p.deleted = 0';
        $params = [];
        if ($q !== '') { $where .= ' AND (p.name LIKE ? OR p.description LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
        if ($catFilter !== '') { $where .= ' AND pc.name = ?'; $params[] = $catFilter; }

        $total = (int) DB::first('SELECT COUNT(*) FROM plans p LEFT JOIN plan_categories pc ON pc.id = p.category_id ' . $where, $params);
        $totalPages = max(1, (int) ceil($total / $limit));
        $plans = DB::all(
            "SELECT p.*, pc.name AS category_name, (SELECT COUNT(*) FROM subscribers s WHERE s.plan_id = p.id AND s.status = 'active') AS sub_count
             FROM plans p LEFT JOIN plan_categories pc ON pc.id = p.category_id $where
             ORDER BY p.sort_order, p.created_at DESC LIMIT $limit OFFSET $offset",
            $params
        );
        $pv('pages/plans/all', [
            'title' => 'All Plans', 'active' => 'plans', 'plans' => $plans,
            'q' => $q, 'catFilter' => $catFilter, 'page' => $page, 'totalPages' => $totalPages,
            'total' => $total, 'query' => nh_obj($req->query),
        ]);
    });

    $r->get('/plans/add', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $pv('pages/plans/add', [
            'title' => 'Add Plan',
            'active' => 'plans',
            'categories' => DB::all('SELECT * FROM plan_categories ORDER BY sort_order, id'),
            'editing' => null,
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/plans/add', [Auth::class, 'requireAdmin'], function (Request $req) {
        $name = (string) $req->input('name', '');
        if ($name === '') { Response::redirect('/plans/add?error=Name is required'); return; }
        DB::run(
            'INSERT INTO plans (name, category_id, description, price, billing, features, storage_limit, user_limit, api_limit, trial_days, status, badge, badge_color, popular)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $name,
                $req->input('category_id') ? (int) $req->input('category_id') : null,
                (string) $req->input('description', ''),
                (float) $req->input('price', 0) ?: 0,
                (string) ($req->input('billing') ?: 'monthly'),
                (string) $req->input('features', ''),
                (string) $req->input('storage_limit', ''),
                (int) $req->input('user_limit', 0) ?: 0,
                (string) $req->input('api_limit', ''),
                (int) $req->input('trial_days', 0) ?: 0,
                ((string) $req->input('status')) === 'inactive' ? 'inactive' : 'active',
                (string) $req->input('badge', ''),
                (string) ($req->input('badge_color') ?: '#3b82f6'),
                $req->input('popular') ? 1 : 0,
            ]
        );
        Auth::logActivity(Auth::user(), 'Created plan "' . $name . '"', $req);
        Response::redirect('/plans/all?saved=1');
    });

    $r->get('/plans/edit/{id}', [Auth::class, 'requireAdmin'], function (Request $req, array $params) use ($pv) {
        $editing = DB::get('SELECT * FROM plans WHERE id = ?', [(int) $params['id']]);
        if (!$editing) { Response::redirect('/plans/all'); return; }
        $pv('pages/plans/add', [
            'title' => 'Edit Plan',
            'active' => 'plans',
            'categories' => DB::all('SELECT * FROM plan_categories ORDER BY sort_order, id'),
            'editing' => $editing,
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/plans/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM plans WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/plans/all'); return; }
        $has = fn(string $k): bool => array_key_exists($k, $req->body) && $req->body[$k] !== null;
        DB::run(
            "UPDATE plans SET name=?, category_id=?, description=?, price=?, billing=?, features=?, storage_limit=?, user_limit=?, api_limit=?, trial_days=?, status=?, badge=?, badge_color=?, popular=?, updated_at=datetime('now') WHERE id=?",
            [
                (string) ($req->input('name') ?: $target->name),
                $req->input('category_id') ? (int) $req->input('category_id') : null,
                $has('description') ? (string) $req->body['description'] : (string) $target->description,
                (float) $req->input('price', 0) ?: (float) $target->price,
                (string) ($req->input('billing') ?: $target->billing),
                $has('features') ? (string) $req->body['features'] : (string) $target->features,
                $has('storage_limit') ? (string) $req->body['storage_limit'] : (string) $target->storage_limit,
                (int) $req->input('user_limit', 0) ?: (int) $target->user_limit,
                $has('api_limit') ? (string) $req->body['api_limit'] : (string) $target->api_limit,
                (int) $req->input('trial_days', 0) ?: (int) $target->trial_days,
                ((string) $req->input('status')) === 'inactive' ? 'inactive' : 'active',
                $has('badge') ? (string) $req->body['badge'] : (string) $target->badge,
                (string) ($req->input('badge_color') ?: $target->badge_color),
                $req->input('popular') ? 1 : 0,
                (int) $target->id,
            ]
        );
        Auth::logActivity(Auth::user(), 'Edited plan "' . (string) $target->name . '"', $req);
        Response::redirect('/plans/all?saved=1');
    });

    $r->post('/plans/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM plans WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/plans/all'); return; }
        DB::run("UPDATE plans SET deleted = 1, updated_at = datetime('now') WHERE id = ?", [(int) $target->id]);
        Auth::logActivity(Auth::user(), 'Deleted plan "' . (string) $target->name . '"', $req);
        Response::redirect('/plans/all?deleted=1');
    });

    $r->post('/plans/{id}/restore', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        DB::run("UPDATE plans SET deleted = 0, updated_at = datetime('now') WHERE id = ?", [(int) $params['id']]);
        Auth::logActivity(Auth::user(), 'Restored a plan', $req);
        Response::redirect('/plans/trash?restored=1');
    });

    $r->post('/plans/{id}/permanent-delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        DB::run('DELETE FROM plans WHERE id = ?', [(int) $params['id']]);
        Auth::logActivity(Auth::user(), 'Permanently deleted a plan', $req);
        Response::redirect('/plans/trash?permanently=1');
    });

    $r->get('/plans/trash', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $pv('pages/plans/trash', [
            'title' => 'Plans Trash',
            'active' => 'plans',
            'plans' => DB::all('SELECT * FROM plans WHERE deleted = 1 ORDER BY updated_at DESC'),
            'query' => nh_obj($req->query),
        ]);
    });

    /* -------------------------------------------------------- plan categories */

    $r->get('/plans/categories', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $editing = !empty($req->query['edit']) ? DB::get('SELECT * FROM plan_categories WHERE id = ?', [(int) $req->query['edit']]) : null;
        $pv('pages/plans/categories', [
            'title' => 'Plan Categories',
            'active' => 'plans',
            'categories' => DB::all('SELECT pc.*, (SELECT COUNT(*) FROM plans p WHERE p.category_id = pc.id AND p.deleted = 0) AS plan_count FROM plan_categories pc ORDER BY pc.sort_order, pc.id'),
            'editing' => $editing,
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/plans/categories/add', [Auth::class, 'requireAdmin'], function (Request $req) {
        $name = trim((string) $req->input('name', ''));
        if ($name === '') { Response::redirect('/plans/categories?error=Name is required'); return; }
        DB::run('INSERT INTO plan_categories (name, description, sort_order) VALUES (?, ?, ?)', [
            $name,
            (string) $req->input('description', ''),
            (int) $req->input('sort_order', 0) ?: 0,
        ]);
        Auth::logActivity(Auth::user(), 'Created plan category "' . $name . '"', $req);
        Response::redirect('/plans/categories?saved=1');
    });

    $r->post('/plans/categories/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM plan_categories WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/plans/categories'); return; }
        DB::run('UPDATE plan_categories SET name = ?, description = ?, sort_order = ? WHERE id = ?', [
            trim((string) ($req->input('name') ?: $target->name)),
            array_key_exists('description', $req->body) && $req->body['description'] !== null ? (string) $req->body['description'] : (string) $target->description,
            (int) $req->input('sort_order', 0) ?: (int) $target->sort_order,
            (int) $target->id,
        ]);
        Auth::logActivity(Auth::user(), 'Edited plan category "' . (string) $target->name . '"', $req);
        Response::redirect('/plans/categories?saved=1');
    });

    $r->post('/plans/categories/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM plan_categories WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/plans/categories'); return; }
        DB::run('UPDATE plans SET category_id = NULL WHERE category_id = ?', [(int) $target->id]);
        DB::run('DELETE FROM plan_categories WHERE id = ?', [(int) $target->id]);
        Auth::logActivity(Auth::user(), 'Deleted plan category "' . (string) $target->name . '"', $req);
        Response::redirect('/plans/categories?deleted=1');
    });

    /* ------------------------------------------------------------ subscribers */

    $r->get('/plans/subscribers', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $q = trim((string) ($req->query['q'] ?? ''));
        $page = max(1, (int) ($req->query['page'] ?? 1) ?: 1);
        $limit = 15;
        $offset = ($page - 1) * $limit;
        $where = 'WHERE 1=1';
        $params = [];
        if ($q !== '') { $where .= ' AND (s.username LIKE ? OR s.plan_name LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
        $total = (int) DB::first('SELECT COUNT(*) FROM subscribers s ' . $where, $params);
        $totalPages = max(1, (int) ceil($total / $limit));
        $subscribers = DB::all("SELECT s.* FROM subscribers s $where ORDER BY s.created_at DESC LIMIT $limit OFFSET $offset", $params);
        $pv('pages/plans/subscribers', [
            'title' => 'Subscribers', 'active' => 'plans', 'subscribers' => $subscribers,
            'q' => $q, 'page' => $page, 'totalPages' => $totalPages, 'total' => $total, 'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/plans/subscribers/{id}/status', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM subscribers WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/plans/subscribers'); return; }
        $status = (string) ($req->input('status') ?: 'active');
        DB::run('UPDATE subscribers SET status = ? WHERE id = ?', [$status, (int) $target->id]);
        Auth::logActivity(Auth::user(), 'Updated subscriber ' . (string) $target->username . ' status to ' . $status, $req);
        Response::redirect('/plans/subscribers?saved=1');
    });

    $r->post('/plans/subscribers/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        DB::run('DELETE FROM subscribers WHERE id = ?', [(int) $params['id']]);
        Auth::logActivity(Auth::user(), 'Removed a subscriber', $req);
        Response::redirect('/plans/subscribers?deleted=1');
    });

    /* ----------------------------------------------------------- transactions */

    $r->get('/plans/transactions', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $q = trim((string) ($req->query['q'] ?? ''));
        $page = max(1, (int) ($req->query['page'] ?? 1) ?: 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $where = 'WHERE 1=1';
        $params = [];
        if ($q !== '') { $where .= ' AND (t.username LIKE ? OR t.plan_name LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
        $total = (int) DB::first('SELECT COUNT(*) FROM transactions t ' . $where, $params);
        $totalPages = max(1, (int) ceil($total / $limit));
        $transactions = DB::all("SELECT t.* FROM transactions t $where ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset", $params);
        $pv('pages/plans/transactions', [
            'title' => 'Transactions', 'active' => 'plans', 'transactions' => $transactions,
            'q' => $q, 'page' => $page, 'totalPages' => $totalPages, 'total' => $total, 'query' => nh_obj($req->query),
        ]);
    });

    /* ---------------------------------------------------------------- coupons */

    $r->get('/plans/coupons', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $editing = !empty($req->query['edit']) ? DB::get('SELECT * FROM coupons WHERE id = ?', [(int) $req->query['edit']]) : null;
        $pv('pages/plans/coupons', [
            'title' => 'Coupons',
            'active' => 'plans',
            'coupons' => DB::all('SELECT * FROM coupons ORDER BY created_at DESC'),
            'editing' => $editing,
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/plans/coupons/add', [Auth::class, 'requireAdmin'], function (Request $req) {
        $code = strtoupper(trim((string) $req->input('code', '')));
        if ($code === '') { Response::redirect('/plans/coupons?error=Code is required'); return; }
        if (DB::get('SELECT id FROM coupons WHERE code = ?', [$code])) { Response::redirect('/plans/coupons?error=Coupon code already exists'); return; }
        DB::run('INSERT INTO coupons (code, type, value, max_uses, min_amount, expiry_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)', [
            $code,
            (string) ($req->input('type') ?: 'percent'),
            (float) $req->input('value', 0) ?: 0,
            (int) $req->input('max_uses', 0) ?: 0,
            (float) $req->input('min_amount', 0) ?: 0,
            (string) $req->input('expiry_date', ''),
            ((string) $req->input('status')) === 'inactive' ? 'inactive' : 'active',
        ]);
        Auth::logActivity(Auth::user(), 'Created coupon "' . $code . '"', $req);
        Response::redirect('/plans/coupons?saved=1');
    });

    $r->post('/plans/coupons/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM coupons WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/plans/coupons'); return; }
        $maxUses = $req->input('max_uses');
        $newCode = strtoupper(trim((string) ($req->input('code') ?: $target->code)));
        if (nh_taken('coupons', 'code', $newCode, (int) $target->id)) {
            Response::redirect('/plans/coupons?error=Coupon code already exists');
            return;
        }
        DB::run('UPDATE coupons SET code=?, type=?, value=?, max_uses=?, min_amount=?, expiry_date=?, status=? WHERE id=?', [
            $newCode,
            (string) ($req->input('type') ?: $target->type),
            (float) $req->input('value', 0) ?: (float) $target->value,
            $maxUses !== null && $maxUses !== '' ? (int) $maxUses : (int) $target->max_uses,
            (float) $req->input('min_amount', 0) ?: (float) $target->min_amount,
            array_key_exists('expiry_date', $req->body) && $req->body['expiry_date'] !== null ? (string) $req->body['expiry_date'] : (string) $target->expiry_date,
            ((string) $req->input('status')) === 'inactive' ? 'inactive' : 'active',
            (int) $target->id,
        ]);
        Auth::logActivity(Auth::user(), 'Edited coupon "' . (string) $target->code . '"', $req);
        Response::redirect('/plans/coupons?saved=1');
    });

    $r->post('/plans/coupons/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM coupons WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/plans/coupons'); return; }
        DB::run('DELETE FROM coupons WHERE id = ?', [(int) $target->id]);
        Auth::logActivity(Auth::user(), 'Deleted coupon "' . (string) $target->code . '"', $req);
        Response::redirect('/plans/coupons?deleted=1');
    });

    /* ------------------------------------------------------------ plan settings */

    $r->get('/plans/settings', [Auth::class, 'requireAdmin'], function (Request $req) use ($pv) {
        $pv('pages/plans/settings', [
            'title' => 'Plan Settings',
            'active' => 'plans',
            'plans' => DB::all('SELECT * FROM plans WHERE deleted = 0 ORDER BY name'),
            'query' => nh_obj($req->query),
        ]);
    });

    $r->post('/plans/settings', [Auth::class, 'requireAdmin'], function (Request $req) {
        $keys = ['plan_currency', 'plan_currency_code', 'plan_tax_rate', 'plan_tax_name', 'plan_trial_days', 'plan_default_id', 'plan_stripe_pk', 'plan_stripe_sk', 'plan_razorpay_key', 'plan_razorpay_secret', 'plan_paypal_client', 'plan_paypal_secret'];
        $upd = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $req->body) && $req->body[$k] !== null) $upd[$k] = (string) $req->body[$k];
        }
        if ($upd) Settings::setMany($upd);
        Auth::logActivity(Auth::user(), 'Updated plan settings', $req);
        Response::redirect('/plans/settings?saved=1');
    });

    /* ------------------------------------------------------------------- docs */

    $r->get('/docs', [Auth::track('docs')], function (Request $req) use ($pv, $isAdmin) {
        $q = trim((string) ($req->query['q'] ?? ''));
        $cat = trim((string) ($req->query['category'] ?? ''));
        $page = max(1, (int) ($req->query['page'] ?? 1) ?: 1);
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $where = 'WHERE 1=1';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (d.title LIKE ? OR d.description LIKE ? OR d.tags LIKE ?)';
            $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%';
        }
        if ($cat !== '') { $where .= ' AND d.category = ?'; $params[] = $cat; }

        $total = (int) DB::first("SELECT COUNT(*) FROM docs d $where", $params);
        $totalPages = max(1, (int) ceil($total / $limit));
        $docs = DB::all(
            "SELECT d.*, u.username AS author FROM docs d LEFT JOIN users u ON u.id = d.author_id $where
             ORDER BY d.updated_at DESC LIMIT $limit OFFSET $offset",
            $params
        );
        $categories = array_map(fn($row) => (string) $row->category, DB::all("SELECT DISTINCT category FROM docs WHERE category != '' ORDER BY category"));

        $pv('pages/user/docs', [
            'title' => 'Docs', 'active' => 'docs', 'docs' => $docs, 'q' => $q, 'cat' => $cat,
            'categories' => $categories, 'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
            'query' => nh_obj($req->query), 'isAdmin' => $isAdmin(),
            'extraScripts' => '<script src="/assets/js/docs.js"></script>',
        ]);
    });

    $r->get('/docs/view/{id}', [Auth::track('docs')], function (Request $req, array $params) use ($pv) {
        $doc = DB::get('SELECT d.*, u.username AS author FROM docs d LEFT JOIN users u ON u.id = d.author_id WHERE d.id = ?', [(int) $params['id']]);
        if (!$doc) { Response::redirect('/docs'); return; }
        $pv('pages/user/doc-view', ['title' => (string) $doc->title, 'active' => 'docs', 'doc' => $doc, 'query' => nh_obj($req->query)]);
    });

    $r->post('/docs/add', [Auth::class, 'requireAdmin'], function (Request $req) {
        $title = (string) $req->input('title', '');
        if ($title === '') { Response::redirect('/docs?error=title'); return; }
        DB::run('INSERT INTO docs (title, description, content, category, tags, status, author_id) VALUES (?, ?, ?, ?, ?, ?, ?)', [
            $title,
            (string) $req->input('description', ''),
            (string) $req->input('content', ''),
            trim((string) $req->input('category', '')),
            trim((string) $req->input('tags', '')),
            ((string) $req->input('status')) === 'draft' ? 'draft' : 'published',
            (int) (Auth::user()->id ?? 0),
        ]);
        Auth::logActivity(Auth::user(), 'Added doc "' . $title . '"', $req);
        Response::redirect('/docs?saved=1');
    });

    $r->post('/docs/bulk-delete', [Auth::class, 'requireAdmin'], function (Request $req) {
        $ids = [];
        foreach (explode(',', (string) $req->input('ids', '')) as $n) {
            $n = (int) trim($n);
            if ($n > 0) $ids[] = $n;
        }
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            DB::run("DELETE FROM docs WHERE id IN ($placeholders)", $ids);
            Auth::logActivity(Auth::user(), 'Bulk deleted ' . count($ids) . ' doc(s)', $req);
        }
        Response::redirect('/docs?deleted=1');
    });

    $r->post('/docs/{id}/edit', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM docs WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/docs'); return; }
        $has = fn(string $k): bool => array_key_exists($k, $req->body) && $req->body[$k] !== null;
        DB::run("UPDATE docs SET title = ?, description = ?, content = ?, category = ?, tags = ?, status = ?, updated_at = datetime('now') WHERE id = ?", [
            (string) ($req->input('title') ?: $target->title),
            $has('description') ? (string) $req->body['description'] : (string) $target->description,
            $has('content') ? (string) $req->body['content'] : (string) $target->content,
            trim($has('category') ? (string) $req->body['category'] : (string) ($target->category ?? '')),
            trim($has('tags') ? (string) $req->body['tags'] : (string) ($target->tags ?? '')),
            ((string) $req->input('status')) === 'draft' ? 'draft' : 'published',
            (int) $target->id,
        ]);
        Auth::logActivity(Auth::user(), 'Edited doc "' . (string) $target->title . '"', $req);
        Response::redirect('/docs?edited=1');
    });

    $r->post('/docs/{id}/delete', [Auth::class, 'requireAdmin'], function (Request $req, array $params) {
        $target = DB::get('SELECT * FROM docs WHERE id = ?', [(int) $params['id']]);
        if (!$target) { Response::redirect('/docs'); return; }
        DB::run('DELETE FROM docs WHERE id = ?', [(int) $target->id]);
        Auth::logActivity(Auth::user(), 'Deleted doc "' . (string) $target->title . '"', $req);
        Response::redirect('/docs?deleted=1');
    });


    /* --------------------------------------------------- favicon + error pages */

    $r->get('/favicon.ico', function (Request $req) {
        $fav = (string) (Settings::get('favicon_url') ?: '/assets/img/favicon.svg');
        $rel = preg_replace('#^/assets/#', '', $fav) ?? $fav;
        $file = NH_ASSETS . '/' . ltrim((string) $rel, '/');
        if (!is_file($file)) $file = NH_ASSETS . '/img/favicon.svg';
        if (!is_file($file)) { Response::status(404); return; }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $type = match ($ext) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/x-icon',
        };
        Response::header('Content-Type', $type);
        Response::header('Cache-Control', 'public, max-age=86400');
        echo file_get_contents($file);
    });

    $r->get('/403', function (Request $req) {
        Response::status(403);
        View::render('pages/errors/403', ['title' => '403', 'active' => ''], 'layouts/auth');
    });

    $r->get('/500', function (Request $req) {
        Response::status(500);
        View::render('pages/errors/500', ['title' => '500', 'active' => ''], 'layouts/auth');
    });

    /* ---------------------------------------------- CasaOS app launcher (JSON) */

    $r->get('/apps', [Auth::class, 'requireAuth'], function (Request $req) use ($pv) {
        $u = Auth::user();
        $uid = $u ? (int) $u->id : 0;
        $pv('pages/casaos/home', [
            'title' => 'Apps',
            'active' => 'apps',
            'apps' => DB::all('SELECT * FROM casaos_apps WHERE user_id IS NULL OR user_id = ? ORDER BY sort_order, id', [$uid]),
            'sys' => System::stats(),
            'devices' => System::storageDevices(),
            'page' => null,
            'stats' => nh_obj([]),
            'activity' => [],
            'blogPosts' => [],
        ]);
    });

};
