<?php
/** Session auth, guards, activity log + analytics tracking. */
final class Auth
{
    private static ?object $user = null;
    private static bool $loaded = false;

    public static function user(): ?object
    {
        if (self::$loaded) return self::$user;
        self::$loaded = true;
        $id = $_SESSION['uid'] ?? null;
        if (!$id) return self::$user = null;
        $u = DB::get('SELECT * FROM users WHERE id = ?', [(int) $id]);
        if (!$u || ($u->status ?? '') !== 'active') {
            unset($_SESSION['uid']);
            return self::$user = null;
        }
        unset($u->password, $u->two_factor_secret);
        return self::$user = $u;
    }

    /** Full row (with password hash + 2fa secret) for internal checks. */
    public static function find(int $id): ?object
    {
        return DB::get('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function byUsername(string $username): ?object
    {
        return DB::get('SELECT * FROM users WHERE username = ? OR email = ?', [$username, $username]);
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function login(object $user, bool $remember = false): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user->id;
        $_SESSION['login_at'] = time();
        self::$user = null;
        self::$loaded = false;
        DB::run('UPDATE users SET last_login = datetime(\'now\'), last_ip = ? WHERE id = ?', [Request::capture()->ip, (int) $user->id]);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        @session_destroy();
        self::$user = null;
        self::$loaded = false;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u && ($u->role ?? '') === 'admin';
    }

    /** User row without secrets (for JSON responses). */
    public static function publicUser(int $id): ?object
    {
        $u = DB::get('SELECT id, username, email, role, status, owner_id, profile_pic, bio, two_factor_enabled, last_login, created_at FROM users WHERE id = ?', [$id]);
        return $u;
    }

    /* ---------------- 2FA pending flow ---------------- */

    public static function setPending2fa(int $userId, string $next = '/'): void
    {
        $_SESSION['pending_2fa'] = $userId;
        $_SESSION['pending_2fa_next'] = $next;
    }

    public static function pending2fa(): ?int
    {
        return isset($_SESSION['pending_2fa']) ? (int) $_SESSION['pending_2fa'] : null;
    }

    public static function clearPending2fa(): void
    {
        unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_next']);
    }

    /* ---------------- guards (router middleware) ---------------- */

    public static function requireAuth(Request $req, array $params)
    {
        if (self::user()) return true;
        if ($req->json()) { Response::fail('Authentication required', 401); return false; }
        Response::redirect('/login?next=' . rawurlencode($req->path));
        return false;
    }

    public static function requireAdmin(Request $req, array $params)
    {
        $u = self::user();
        if (!$u) {
            if ($req->json()) { Response::fail('Authentication required', 401); return false; }
            Response::redirect('/login?next=' . rawurlencode($req->path));
            return false;
        }
        if (($u->role ?? '') !== 'admin') {
            if ($req->json()) { Response::fail('Admin access required', 403); return false; }
            Response::status(403);
            View::render('pages/errors/403', ['title' => '403', 'active' => ''], 'layouts/auth');
            return false;
        }
        return true;
    }

    public static function requireOwner(Request $req, array $params)
    {
        return self::requireAdmin($req, $params);
    }

    /** CSRF guard for state-changing requests. */
    public static function csrf(Request $req, array $params)
    {
        if (in_array($req->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !csrf_ok($req)) {
            if ($req->json()) { Response::fail('Invalid or expired CSRF token', 419); return false; }
            flash('error', 'Session expired — please try again.');
            Response::redirect(safe_back($req->referer, '/'));
            return false;
        }
        return true;
    }

    /* ---------------- logging / analytics ---------------- */

    public static function logActivity(?object $user, string $action, ?Request $req = null): void
    {
        if (!$user) return;
        $req = $req ?: Request::capture();
        try {
            DB::run(
                'INSERT INTO activity (user_id, username, action, ip, user_agent) VALUES (?, ?, ?, ?, ?)',
                [(int) $user->id, (string) $user->username, $action, substr($req->ip, 0, 64), substr($req->ua, 0, 200)]
            );
        } catch (Throwable $e) {
            /* noop */
        }
    }

    /** Route middleware: counts a page view + analytics hit. */
    public static function track(string $page): callable
    {
        return function (Request $req) use ($page) {
            try {
                DB::run(
                    "INSERT INTO analytics (page, date, hits) VALUES (?, date('now'), 1)
                     ON CONFLICT(page, date) DO UPDATE SET hits = hits + 1",
                    [$page]
                );
                if (!$req->isBot()) {
                    DB::run(
                        "INSERT INTO page_views (page, ip, user_agent, referrer, user_id, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))",
                        [$page, substr($req->ip, 0, 64), substr($req->ua, 0, 500), substr($req->referer, 0, 500), self::user()->id ?? null]
                    );
                }
            } catch (Throwable $e) {
                error_log('[track] ' . $page . ': ' . $e->getMessage());
            }
            return true;
        };
    }

    /* ---------------- registration / creation ---------------- */

    public static function createUser(string $username, string $email, string $password, string $role = 'user', array $extra = []): int
    {
        return DB::insert('users', array_merge([
            'username' => $username,
            'email' => $email,
            'password' => self::hash($password),
            'role' => $role,
            'status' => 'active',
        ], $extra));
    }

    /** Same rules as src/validate.js */
    public static function validateEmail(string $email): bool
    {
        return strlen($email) <= 254 && (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', $email);
    }

    /** Same rules as src/validate.js */
    public static function validateUsername(string $username): bool
    {
        $len = strlen($username);
        return $len >= 2 && $len <= 32 && (bool) preg_match('/^[a-zA-Z0-9_.-]+$/', $username);
    }
}
