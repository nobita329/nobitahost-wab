<?php
/**
 * JSON API — mirrors the Node build's /api/* surface.
 */

return function (Router $r): void {

    /* ---------------- meta ---------------- */

    $r->get('/api/status', function (Request $req) {
        Response::ok([
            'name' => Settings::get('panel_name', 'NobitaHost'),
            'engine' => 'php',
            'php' => PHP_VERSION,
            'version' => '2.0.0',
            'time' => date('c'),
            'maintenance' => Settings::get('maintenance') === 'on',
        ]);
    });

    /* ---------------- system ---------------- */

    $r->get('/api/system', function (Request $req) {
        $stats = System::stats();
        System::recordSample($stats);
        // `system` keeps the Node client (js/system.js) happy, `data` feeds the CasaOS topbar widgets.
        Response::ok(['system' => $stats, 'data' => $stats, 'devices' => System::storageDevices()]);
    });

    $r->get('/api/system/history', function (Request $req) {
        $range = $req->str('range', '24h');
        $history = System::history($range);
        Response::ok(['history' => $history, 'data' => $history, 'range' => $range]);
    });

    $r->get('/api/storage', function (Request $req) {
        Response::ok(['data' => System::storageDevices()]);
    });

    /* ---------------- auth ---------------- */

    $r->post('/api/auth/login', function (Request $req) {
        $username = $req->str('username');
        $password = (string) $req->input('password', '');
        $user = $username !== '' ? Auth::byUsername($username) : null;
        if (!$user || !Auth::verify($password, (string) $user->password)) {
            return Response::fail('Invalid username or password', 401);
        }
        if (($user->status ?? '') !== 'active') {
            return Response::fail('This account is suspended', 403);
        }
        if ((int) $user->two_factor_enabled === 1) {
            $code = $req->str('code');
            if ($code === '' || !Totp::verify((string) $user->two_factor_secret, $code)) {
                return Response::fail('Two-factor code required or invalid', 401);
            }
        }
        Auth::login($user);
        Auth::logActivity(Auth::find((int) $user->id), 'API login', $req);
        Response::ok(['user' => Auth::publicUser((int) $user->id)]);
    });

    $r->post('/api/auth/register', function (Request $req) {
        if (Settings::get('register_open') !== 'on') return Response::fail('Registration is closed', 403);
        $username = $req->str('username');
        $email = $req->str('email');
        $password = (string) $req->input('password', '');
        if (!Auth::validateUsername($username)) return Response::fail('Username must be 3-24 chars (letters, numbers, . _ -)', 400);
        if (!Auth::validateEmail($email)) return Response::fail('Invalid email address', 400);
        if (strlen($password) < 6) return Response::fail('Password must be at least 6 characters', 400);
        if (DB::get('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email])) {
            return Response::fail('Username or email already taken', 409);
        }
        $id = Auth::createUser($username, $email, $password);
        $u = Auth::find($id);
        Auth::logActivity($u, 'Registered (API)', $req);
        // node parity: the API only creates the account — it does not log the caller in
        Response::json(['success' => true, 'message' => 'Registered', 'id' => $id], 201);
    });

    $r->get('/api/auth/me', function (Request $req) {
        $u = Auth::user();
        if (!$u) return Response::fail('Not authenticated', 401);
        Response::ok(['user' => $u]);
    });

    /* ---------------- activity / analytics ---------------- */

    $r->get('/api/activity', function (Request $req) {
        $u = Auth::user();
        if (!$u) return Response::fail('Not authenticated', 401);
        $limit = min(200, $req->int('limit', 50));
        Response::ok(['data' => DB::all('SELECT * FROM activity WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit, [(int) $u->id])]);
    });

    $r->get('/api/analytics', function (Request $req) {
        if (!Auth::isAdmin()) return Response::fail('Admin access required', 403);
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $days[$d] = (int) DB::first("SELECT COALESCE(SUM(hits),0) FROM analytics WHERE date = ?", [$d]);
        }
        Response::ok([
            'days' => $days,
            'totalViews' => (int) DB::first('SELECT COALESCE(SUM(hits),0) FROM analytics'),
            'users' => (int) DB::first('SELECT COUNT(*) FROM users'),
            'activeUsers' => (int) DB::first("SELECT COUNT(*) FROM users WHERE status = 'active'"),
        ]);
    });

    /* ---------------- settings ---------------- */

    $r->get('/api/settings', function (Request $req) {
        $s = (array) Settings::all();
        foreach (['smtp_pass', 'cf_api_token', 'github_token', 'youtube_api_key', 'obsidian_about', 'obsidian_terms', 'obsidian_footer', 'obsidian_navbar'] as $k) {
            unset($s[$k]);
        }
        Response::ok(['data' => $s]);
    });

    $r->put('/api/settings', function (Request $req) {
        if (!Auth::isAdmin()) return Response::fail('Admin access required', 403);
        $allowed = array_keys(Settings::DEFAULTS);
        $updated = 0;
        foreach ($allowed as $key) {
            if (array_key_exists($key, $req->body)) {
                Settings::set($key, (string) $req->body[$key]);
                $updated++;
            }
        }
        Auth::logActivity(Auth::user(), "Updated $updated settings via API", $req);
        Response::ok(['updated' => $updated, 'data' => Settings::all(true)]);
    });

    /* ---------------- admin: users ---------------- */

    $r->get('/api/admin/users', function (Request $req) {
        if (!Auth::isAdmin()) return Response::fail('Admin access required', 403);
        Response::ok(['data' => DB::all('SELECT id, username, email, role, status, owner_id, profile_pic, last_login, created_at FROM users ORDER BY id')]);
    });

    $r->post('/api/admin/users', function (Request $req) {
        if (!Auth::isAdmin()) return Response::fail('Admin access required', 403);
        $username = $req->str('username');
        $email = $req->str('email');
        $password = (string) $req->input('password', '');
        $role = $req->str('role', 'user');
        if (!Auth::validateUsername($username) || !Auth::validateEmail($email) || strlen($password) < 6) {
            return Response::fail('Invalid username, email or password', 400);
        }
        if (DB::get('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email])) {
            return Response::fail('Username or email already taken', 409);
        }
        $id = Auth::createUser($username, $email, $password, in_array($role, ['admin', 'user'], true) ? $role : 'user');
        Auth::logActivity(Auth::user(), "Created user $username via API", $req);
        Response::ok(['id' => $id]);
    });

    $r->put('/api/admin/users/{id}', function (Request $req, array $p) {
        if (!Auth::isAdmin()) return Response::fail('Admin access required', 403);
        $id = (int) $p['id'];
        $target = DB::get('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$target) return Response::fail('User not found', 404);
        $data = [];
        foreach (['username', 'email', 'role', 'status', 'bio'] as $f) {
            if (isset($req->body[$f])) $data[$f] = (string) $req->body[$f];
        }
        if (!empty($req->body['password'])) $data['password'] = Auth::hash((string) $req->body['password']);
        if ($data) DB::update('users', $data, 'id = ?', [$id]);
        Auth::logActivity(Auth::user(), "Updated user #{$id} via API", $req);
        Response::ok(['updated' => count($data)]);
    });

    $r->put('/api/admin/users/{id}/suspend', function (Request $req, array $p) {
        if (!Auth::isAdmin()) return Response::fail('Admin access required', 403);
        $id = (int) $p['id'];
        $suspended = filter_var($req->body['suspended'] ?? true, FILTER_VALIDATE_BOOL);
        DB::run('UPDATE users SET status = ? WHERE id = ?', [$suspended ? 'suspended' : 'active', $id]);
        Auth::logActivity(Auth::user(), ($suspended ? 'Suspended' : 'Reactivated') . " user #{$id} via API", $req);
        Response::ok(['suspended' => $suspended]);
    });

    $r->delete('/api/admin/users/{id}', function (Request $req, array $p) {
        if (!Auth::isAdmin()) return Response::fail('Admin access required', 403);
        $id = (int) $p['id'];
        if ($id === (int) (Auth::user()->id ?? 0)) return Response::fail('You cannot delete your own account', 400);
        DB::delete('users', 'id = ?', [$id]);
        Auth::logActivity(Auth::user(), "Deleted user #{$id} via API", $req);
        Response::ok(['deleted' => $id]);
    });

    /* ---------------- casaos apps ---------------- */

    $r->get('/api/apps', function (Request $req) {
        $scope = (string) ($req->query['scope'] ?? '');
        $uid = $scope === 'me' && Auth::user() ? (int) Auth::user()->id : null;
        $apps = DB::all('SELECT * FROM casaos_apps WHERE user_id IS NULL OR user_id = ? ORDER BY sort_order, id', [$uid]);
        Response::ok(['data' => $apps]);
    });

    $r->post('/api/apps', function (Request $req) {
        if (!Auth::isAdmin()) return Response::fail('Admin access required', 403);
        $title = $req->str('title');
        if ($title === '') return Response::fail('Title is required', 400);
        $id = DB::insert('casaos_apps', [
            'user_id' => null,
            'title' => $title,
            'icon' => $req->str('icon', '📦'),
            'color' => $req->str('color', '#3388ff'),
            'url' => $req->str('url', '#'),
            'category' => $req->str('category', 'apps'),
            'description' => $req->str('description'),
            'sort_order' => $req->int('sort_order', 0),
            'is_system' => 0,
        ]);
        Auth::logActivity(Auth::user(), "Added app tile '$title'", $req);
        Response::ok(['id' => $id]);
    });

    $r->put('/api/apps/{id}', function (Request $req, array $p) {
        if (!Auth::isAdmin()) return Response::fail('Admin access required', 403);
        $id = (int) $p['id'];
        $row = DB::get('SELECT * FROM casaos_apps WHERE id = ?', [$id]);
        if (!$row) return Response::fail('App not found', 404);
        $title = $req->str('title');
        if ($title === '') return Response::fail('Title is required', 400);
        DB::update('casaos_apps', [
            'title' => mb_substr($title, 0, 80),
            'icon' => mb_substr((string) $req->input('icon', $row->icon), 0, 16),
            'color' => mb_substr((string) $req->input('color', $row->color), 0, 32),
            'url' => mb_substr((string) $req->input('url', $row->url), 0, 300),
            'category' => mb_substr((string) $req->input('category', $row->category), 0, 40),
            'description' => mb_substr((string) $req->input('description', $row->description), 0, 300),
            'sort_order' => (int) $req->input('sort_order', $row->sort_order),
        ], 'id = ?', [$id]);
        Response::ok(['id' => $id, 'app' => DB::get('SELECT * FROM casaos_apps WHERE id = ?', [$id])]);
    });

    $r->delete('/api/apps/{id}', function (Request $req, array $p) {
        if (!Auth::isAdmin()) return Response::fail('Admin access required', 403);
        DB::delete('casaos_apps', 'id = ? AND is_system = 0', [(int) $p['id']]);
        Response::ok(['deleted' => (int) $p['id']]);
    });
};
