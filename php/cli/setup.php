<?php
/**
 * Setup / seed CLI:
 *   php cli/setup.php                 create DB, seed settings, content, roles, admin, demo, CasaOS apps
 *   php cli/setup.php --import-node   reuse the Node build's data/nobitahost.db first
 *   php cli/setup.php --admin=user:pass
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$argvList = $argv ?? [];
$importNode = in_array('--import-node', $argvList, true);
$adminArg = null;
foreach ($argvList as $a) {
    if (str_starts_with($a, '--admin=')) $adminArg = substr($a, 8);
}

echo "┌───────────────────────────────────────────┐\n";
echo "│     NobitaHost · PHP Setup (CasaOS UI)    │\n";
echo "└───────────────────────────────────────────┘\n";

/* ---------------- optional: import the Node database ---------------- */
if ($importNode) {
    $nodeDb = dirname(NH_ROOT) . '/data/nobitahost.db';
    if (is_file($nodeDb)) {
        if (!is_dir(NH_DATA)) @mkdir(NH_DATA, 0775, true);
        copy($nodeDb, DB::$path ?: (NH_DATA . '/nobitahost.db'));
        echo "✔ Imported Node database from $nodeDb\n";
        DB::migrate();
    } else {
        echo "ℹ No Node database found at $nodeDb — starting fresh\n";
    }
}

/* ---------------- settings ---------------- */
$seeded = 0;
foreach (Settings::DEFAULTS as $k => $v) {
    $exists = DB::get('SELECT 1 FROM settings WHERE key = ?', [$k]);
    if (!$exists) { Settings::set($k, (string) $v); $seeded++; }
}
echo "✔ Settings seeded ($seeded new / " . count(Settings::DEFAULTS) . " defaults)\n";

/* ---------------- content pages ---------------- */
$defaultContent = [
    'home' => "<div class=\"cs-panel\"><div class=\"cs-panel-body\"><h2>Welcome to NobitaHost 👋</h2><p>Your all-in-one panel, now running on <b>PHP</b> with a <b>CasaOS</b> desktop. Launch any app from the grid below.</p></div></div>",
    'team' => "<h2>Team Management</h2>\n<p>Create and manage your sub-users below. You can add team members, edit their details, suspend or remove them anytime.</p>",
    'tutorials' => "<h2>Tutorials</h2>\n<p>Browse our step-by-step tutorials to get the most out of your panel.</p>",
    'command' => "<h2>Command Center</h2>\n<p>Run the panel like a pro. Here are some handy commands:</p>",
    'analytics' => "<h2>Analytics</h2>\n<p>Live page views, login activity and user statistics are shown below.</p>",
    'projects' => "<h2>Projects</h2>\n<p>Showcase what you build — active, ongoing and completed projects.</p>",
    'links' => "<h2>Links</h2>\n<p>Important links, resources and shortcuts for your community.</p>",
    'github' => "<h2>GitHub</h2>\n<p>Meet our developers and contributors.</p>",
    'about' => "<h2>About NobitaHost</h2>\n<p>A modern, self-hosted panel built for teams and communities. Fast, secure and fully customisable.</p>",
];
$pages = 0;
foreach ($defaultContent as $slug => $content) {
    if (!DB::get('SELECT id FROM content_pages WHERE slug = ?', [$slug])) {
        DB::insert('content_pages', ['slug' => $slug, 'title' => ucfirst($slug), 'content' => $content, 'updated_at' => date('Y-m-d H:i:s')]);
        $pages++;
    }
}
echo "✔ Content pages seeded ($pages new)\n";

/* ---------------- roles ---------------- */
if (!DB::first('SELECT COUNT(*) FROM roles')) {
    foreach ([['Owner', '#ef4444', 1], ['Admin', '#f59e0b', 2], ['Developer', '#3b82f6', 3], ['Moderator', '#10b981', 4], ['Member', '#8b5cf6', 5]] as $r) {
        DB::insert('roles', ['name' => $r[0], 'color' => $r[1], 'sort_order' => $r[2]]);
    }
    echo "✔ Default roles created\n";
}

/* ---------------- users ---------------- */
[$adminUser, $adminPass] = $adminArg ? array_pad(explode(':', $adminArg, 2), 2, 'admin123') : [Env::get('ADMIN_USER', 'admin'), Env::get('ADMIN_PASS', 'admin123')];
if (!DB::first("SELECT COUNT(*) FROM users WHERE role = 'admin'")) {
    Auth::createUser($adminUser, $adminUser . '@nobitahost.local', $adminPass, 'admin');
    echo "✔ Default admin created -> username: $adminUser  password: $adminPass\n";
} else {
    echo "✔ Admin account already exists (skipped)\n";
}
if (!DB::get('SELECT id FROM users WHERE username = ?', ['demo'])) {
    Auth::createUser('demo', 'demo@nobitahost.in', 'demo123', 'user', ['is_demo' => 1]);
    echo "✔ Demo user created -> demo / demo123\n";
} else {
    DB::run("UPDATE users SET is_demo = 1 WHERE username = 'demo'");
}

/* ---------------- CasaOS app tiles ---------------- */
$tiles = [
    ['Files', '🗂️', '#3388ff', '/storage', 'system', 'Browse panel storage', 1],
    ['System', '📊', '#5e5ce6', '/system', 'system', 'CPU, RAM, disk & network', 2],
    ['Docs', '📘', '#0a84ff', '/docs', 'apps', 'Panel documentation', 3],
    ['Blog', '✍️', '#bf5af2', '/blog', 'apps', 'Latest articles', 4],
    ['Terminal', '⌨️', '#30d158', '/command', 'apps', 'Command center', 5],
];
$added = 0;
foreach ($tiles as $t) {
    if (!DB::get('SELECT id FROM casaos_apps WHERE title = ?', [$t[0]])) {
        DB::insert('casaos_apps', [
            'user_id' => null, 'title' => $t[0], 'icon' => $t[1], 'color' => $t[2],
            'url' => $t[3], 'category' => $t[4], 'description' => $t[5],
            'sort_order' => $t[6], 'is_system' => 1,
        ]);
        $added++;
    }
}
echo "✔ CasaOS app tiles seeded ($added new)\n";

echo "\n✅ NobitaHost PHP is ready.\n";
echo "   Dev server : php -S 0.0.0.0:8080 router.php   (from the php/ folder)\n";
echo "   Apache     : point the document root at php/ (.htaccess included)\n";
