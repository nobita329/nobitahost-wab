<?php
/**
 * SQLite (PDO) wrapper + schema/migrations.
 * Same database layout as the Node build, so a `data/nobitahost.db` file can be reused 1:1.
 */
final class DB
{
    private static ?PDO $pdo = null;
    public static string $path = '';

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;
        self::$path = Env::get('DB_PATH') ?: (NH_DATA . '/nobitahost.db');
        $dir = dirname(self::$path);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $pdo = new PDO('sqlite:' . self::$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        return self::$pdo = $pdo;
    }

    /* ---------------- query helpers ---------------- */

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /**
     * Rows come back as NhObj so templates can read a missing column as null
     * (EJS/JS returned `undefined`); plain stdClass would raise a warning.
     */
    private static function wrap($row): object
    {
        if ($row instanceof NhObj) return $row;
        $o = new NhObj();
        foreach ((array) $row as $k => $v) $o->{(string) $k} = $v;
        return $o;
    }

    public static function all(string $sql, array $params = []): array
    {
        return array_map([self::class, 'wrap'], self::run($sql, $params)->fetchAll());
    }

    public static function get(string $sql, array $params = []): ?object
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : self::wrap($row);
    }

    public static function first(string $sql, array $params = [])
    {
        $row = self::run($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? null : $row[0];
    }

    public static function count(string $sql, array $params = []): int
    {
        return (int) (self::first($sql, $params) ?? 0);
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_fill(0, count($cols), '?'))
        );
        self::run($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $cols = array_keys($data);
        $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
        return self::run("UPDATE $table SET $set WHERE $where", array_merge(array_values($data), $whereParams))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run("DELETE FROM $table WHERE $where", $params)->rowCount();
    }

    public static function columns(string $table): array
    {
        $out = [];
        foreach (self::all("PRAGMA table_info($table)") as $c) $out[] = $c->name;
        return $out;
    }

    /* ---------------- schema ---------------- */

    public static function migrate(): void
    {
        $pdo = self::pdo();
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  email TEXT UNIQUE NOT NULL,
  password TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'user',
  status TEXT NOT NULL DEFAULT 'active',
  owner_id INTEGER DEFAULT NULL,
  profile_pic TEXT DEFAULT '',
  bio TEXT DEFAULT '',
  two_factor_enabled INTEGER NOT NULL DEFAULT 0,
  two_factor_secret TEXT DEFAULT NULL,
  last_login TEXT,
  last_ip TEXT DEFAULT '',
  custom_role_id INTEGER DEFAULT NULL,
  is_demo INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT);
CREATE TABLE IF NOT EXISTS content_pages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT UNIQUE NOT NULL,
  title TEXT NOT NULL,
  content TEXT DEFAULT '',
  in_nav INTEGER NOT NULL DEFAULT 0,
  icon TEXT NOT NULL DEFAULT '📄',
  updated_at TEXT,
  updated_by INTEGER
);
CREATE TABLE IF NOT EXISTS roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT UNIQUE NOT NULL,
  color TEXT NOT NULL DEFAULT '#3b82f6',
  sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS tutorials (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  description TEXT DEFAULT '',
  video_url TEXT DEFAULT '',
  thumbnail TEXT DEFAULT '',
  author_id INTEGER,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS links (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  url TEXT DEFAULT '',
  icon TEXT DEFAULT '🔗',
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS projects (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  description TEXT DEFAULT '',
  url TEXT DEFAULT '',
  button TEXT DEFAULT 'View Project',
  thumbnail TEXT DEFAULT '',
  html TEXT DEFAULT '',
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS github_links (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL,
  url TEXT DEFAULT '',
  note TEXT DEFAULT '',
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS activity (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  username TEXT,
  action TEXT,
  ip TEXT,
  user_agent TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS analytics (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  page TEXT NOT NULL,
  date TEXT NOT NULL DEFAULT (date('now')),
  hits INTEGER NOT NULL DEFAULT 0,
  UNIQUE(page, date)
);
CREATE TABLE IF NOT EXISTS reset_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  token TEXT NOT NULL,
  used INTEGER NOT NULL DEFAULT 0,
  expires_at TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  message TEXT NOT NULL,
  read INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS docs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  description TEXT DEFAULT '',
  content TEXT DEFAULT '',
  category TEXT DEFAULT '',
  tags TEXT DEFAULT '',
  status TEXT DEFAULT 'published',
  author_id INTEGER,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS plan_categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  description TEXT DEFAULT '',
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS plans (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  category_id INTEGER DEFAULT NULL,
  description TEXT DEFAULT '',
  price REAL NOT NULL DEFAULT 0,
  billing TEXT NOT NULL DEFAULT 'monthly',
  features TEXT DEFAULT '',
  storage_limit TEXT DEFAULT '',
  user_limit INTEGER NOT NULL DEFAULT 0,
  api_limit TEXT DEFAULT '',
  trial_days INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'active',
  popular INTEGER NOT NULL DEFAULT 0,
  badge TEXT DEFAULT '',
  badge_color TEXT DEFAULT '#3b82f6',
  sort_order INTEGER NOT NULL DEFAULT 0,
  deleted INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS subscribers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER DEFAULT NULL,
  username TEXT DEFAULT '',
  plan_id INTEGER DEFAULT NULL,
  plan_name TEXT DEFAULT '',
  start_date TEXT NOT NULL DEFAULT (datetime('now')),
  expiry_date TEXT DEFAULT '',
  status TEXT NOT NULL DEFAULT 'active',
  payment_method TEXT DEFAULT '',
  amount REAL NOT NULL DEFAULT 0,
  coupon_id INTEGER DEFAULT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS coupons (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL,
  type TEXT NOT NULL DEFAULT 'percent',
  value REAL NOT NULL DEFAULT 0,
  max_uses INTEGER NOT NULL DEFAULT 0,
  used_count INTEGER NOT NULL DEFAULT 0,
  min_amount REAL NOT NULL DEFAULT 0,
  expiry_date TEXT DEFAULT '',
  status TEXT NOT NULL DEFAULT 'active',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS transactions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER DEFAULT NULL,
  username TEXT DEFAULT '',
  plan_id INTEGER DEFAULT NULL,
  plan_name TEXT DEFAULT '',
  coupon_id INTEGER DEFAULT NULL,
  coupon_code TEXT DEFAULT '',
  amount REAL NOT NULL DEFAULT 0,
  discount REAL NOT NULL DEFAULT 0,
  tax REAL NOT NULL DEFAULT 0,
  total REAL NOT NULL DEFAULT 0,
  payment_method TEXT DEFAULT '',
  status TEXT NOT NULL DEFAULT 'completed',
  note TEXT DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS system_stats (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ts INTEGER NOT NULL,
  cpu REAL NOT NULL,
  mem REAL NOT NULL,
  disk REAL NOT NULL,
  rx REAL NOT NULL,
  tx REAL NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_system_stats_ts ON system_stats(ts);
CREATE TABLE IF NOT EXISTS page_views (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  page TEXT NOT NULL,
  ip TEXT DEFAULT '',
  user_agent TEXT DEFAULT '',
  referrer TEXT DEFAULT '',
  country TEXT DEFAULT '',
  user_id INTEGER DEFAULT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_page_views_page ON page_views(page);
CREATE INDEX IF NOT EXISTS idx_page_views_created ON page_views(created_at);
CREATE INDEX IF NOT EXISTS idx_page_views_ip ON page_views(ip);
CREATE TABLE IF NOT EXISTS user_cf_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  label TEXT DEFAULT '',
  token TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_user_cf_tokens_user ON user_cf_tokens(user_id);
CREATE TABLE IF NOT EXISTS profile_comments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  profile_user_id INTEGER NOT NULL,
  author_id INTEGER NOT NULL,
  parent_id INTEGER DEFAULT NULL,
  content TEXT NOT NULL,
  is_edited INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_profile_comments_profile ON profile_comments(profile_user_id);
CREATE INDEX IF NOT EXISTS idx_profile_comments_parent ON profile_comments(parent_id);
CREATE TABLE IF NOT EXISTS comment_reactions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  comment_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  type TEXT NOT NULL DEFAULT 'like',
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE(comment_id, user_id)
);
CREATE TABLE IF NOT EXISTS blog_posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  slug TEXT UNIQUE NOT NULL,
  description TEXT DEFAULT '',
  cover_image_url TEXT DEFAULT '',
  seo_title TEXT DEFAULT '',
  seo_description TEXT DEFAULT '',
  seo_keywords TEXT DEFAULT '',
  content TEXT NOT NULL DEFAULT '',
  tags TEXT DEFAULT '',
  views INTEGER NOT NULL DEFAULT 0,
  is_published INTEGER NOT NULL DEFAULT 0,
  published_at TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_blog_posts_published ON blog_posts(is_published, published_at);
CREATE TABLE IF NOT EXISTS blog_post_feedback (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  post_id INTEGER NOT NULL,
  is_helpful INTEGER NOT NULL,
  session_id TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE(post_id, session_id)
);
CREATE TABLE IF NOT EXISTS casaos_apps (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER DEFAULT NULL,
  title TEXT NOT NULL,
  icon TEXT DEFAULT '📦',
  color TEXT DEFAULT '#3388ff',
  url TEXT DEFAULT '',
  category TEXT DEFAULT 'apps',
  description TEXT DEFAULT '',
  sort_order INTEGER NOT NULL DEFAULT 0,
  is_system INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL);

        // keep parity with the node schema if the DB was created by an older build
        $pageCols = self::columns('content_pages');
        if (!in_array('in_nav', $pageCols, true)) $pdo->exec("ALTER TABLE content_pages ADD COLUMN in_nav INTEGER NOT NULL DEFAULT 0");
        if (!in_array('icon', $pageCols, true)) $pdo->exec("ALTER TABLE content_pages ADD COLUMN icon TEXT NOT NULL DEFAULT '📄'");
        $userCols = self::columns('users');
        if (!in_array('custom_role_id', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN custom_role_id INTEGER DEFAULT NULL");
        if (!in_array('is_demo', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN is_demo INTEGER NOT NULL DEFAULT 0");
    }
}
