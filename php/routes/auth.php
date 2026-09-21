<?php
/**
 * Authentication routes: login, demo login, register, logout,
 * forgot / reset password, two-factor verification.
 */

return function (Router $r): void {

    /* ---------------- login ---------------- */

    $r->get('/login', function (Request $req) {
        if (Auth::user()) Response::redirect('/');
        View::render('pages/auth/login', [
            'title' => 'Login',
            'active' => '',
            'next' => $req->str('next', '/'),
            'error' => $req->str('error'),
        ], 'layouts/auth');
    });

    $r->post('/login', [Auth::class, 'csrf'], function (Request $req) {
        $username = $req->str('username');
        $password = (string) $req->input('password', '');
        $next = safe_back($req->str('next', '/'), '/');

        $row = Auth::byUsername($username);
        if (!$row || !Auth::verify($password, (string) $row->password)) {
            if ($req->json()) return Response::fail('Invalid username or password', 401);
            flash('error', 'Invalid username or password.');
            return Response::redirect('/login?next=' . rawurlencode($next));
        }
        if (($row->status ?? '') !== 'active') {
            if ($req->json()) return Response::fail('This account is suspended', 403);
            flash('error', 'This account is suspended. Contact an administrator.');
            return Response::redirect('/login?next=' . rawurlencode($next));
        }
        if ((int) $row->two_factor_enabled === 1) {
            Auth::setPending2fa((int) $row->id, $next);
            if ($req->json()) return Response::ok(['two_factor' => true]);
            return Response::redirect('/verify-2fa');
        }

        Auth::login($row);
        Auth::logActivity(Auth::find((int) $row->id), 'Logged in', $req);
        if ($req->json()) return Response::ok(['user' => Auth::publicUser((int) $row->id)]);
        Response::redirect($next);
    });

    /* ---------------- one-click demo login ---------------- */

    $r->get('/login/demo', function (Request $req) {
        $demo = DB::get('SELECT * FROM users WHERE is_demo = 1 OR username = ? LIMIT 1', ['demo']);
        if (!$demo) {
            flash('error', 'Demo account is not available.');
            return Response::redirect('/login');
        }
        Auth::login($demo);
        Auth::logActivity(Auth::find((int) $demo->id), 'Demo login', $req);
        flash('success', 'Signed in as demo user.');
        Response::redirect('/');
    });

    /* ---------------- register ---------------- */

    $r->get('/register', function (Request $req) {
        if (Auth::user()) Response::redirect('/');
        if (Settings::get('register_open') !== 'on') {
            flash('error', 'Registration is currently closed.');
            Response::redirect('/login');
        }
        View::render('pages/auth/register', ['title' => 'Create account', 'active' => ''], 'layouts/auth');
    });

    $r->post('/register', [Auth::class, 'csrf'], function (Request $req) {
        if (Settings::get('register_open') !== 'on') {
            flash('error', 'Registration is currently closed.');
            return Response::redirect('/login');
        }
        $username = $req->str('username');
        $email = $req->str('email');
        $password = (string) $req->input('password', '');
        $confirm = (string) $req->input('confirm_password', $password);

        $fail = function (string $msg) use ($req) {
            if ($req->json()) return Response::fail($msg, 400);
            flash('error', $msg);
            return Response::redirect('/register');
        };

        if (!Auth::validateUsername($username)) return $fail('Username must be 3–24 characters (letters, numbers, dot, dash, underscore).');
        if (!Auth::validateEmail($email)) return $fail('Please enter a valid email address.');
        if (strlen($password) < 6) return $fail('Password must be at least 6 characters.');
        if ($password !== $confirm) return $fail('Passwords do not match.');
        if (DB::get('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email])) {
            return $fail('That username or email is already taken.');
        }

        $id = Auth::createUser($username, $email, $password);
        $user = Auth::find($id);
        Auth::logActivity($user, 'Registered account', $req);

        if (Settings::get('mail_enabled') === 'on') {
            Mailer::send(
                $email,
                'Welcome to ' . Settings::get('panel_name'),
                Mailer::template('Welcome aboard 👋', 'Your account is ready. Here are your details:', [
                    'Username' => $username,
                    'Email' => $email,
                    'Panel' => Env::get('PANEL_URL', ''),
                ], 'Open panel', Env::get('PANEL_URL', '/'))
            );
        }

        if ($req->json()) return Response::ok(['user' => Auth::publicUser($id)]);
        // node parity: stay on the register page with a success notice (no auto-login)
        flash('success', 'Account created for ' . $username . '. You can now login.');
        Response::redirect('/register');
    });

    /* ---------------- logout ---------------- */

    $r->any('/logout', function (Request $req) {
        $u = Auth::user();
        if ($u) Auth::logActivity(Auth::find((int) $u->id), 'Logged out', $req);
        Auth::logout();
        if ($req->json()) return Response::ok(['loggedOut' => true]);
        Response::redirect('/login');
    });

    /* ---------------- forgot / reset ---------------- */

    $r->get('/forgot', function (Request $req) {
        if (Auth::user()) Response::redirect('/');
        View::render('pages/auth/forgot', ['title' => 'Forgot password', 'active' => ''], 'layouts/auth');
    });

    $r->post('/forgot', [Auth::class, 'csrf'], function (Request $req) {
        $email = $req->str('email');
        $row = DB::get('SELECT * FROM users WHERE email = ?', [$email]);
        $base = rtrim(Env::get('PANEL_URL', ''), '/');
        if ($row) {
            $token = bin2hex(random_bytes(24));
            DB::insert('reset_tokens', [
                'user_id' => (int) $row->id,
                'token' => $token,
                'used' => 0,
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            ]);
            $link = ($base ?: '') . '/reset/' . $token;
            $result = Mailer::send(
                (string) $row->email,
                'Reset your password',
                Mailer::template('Password reset', 'We received a request to reset your password. The link expires in 1 hour.', [
                    'Account' => $row->username,
                ], 'Reset password', $link)
            );
            Auth::logActivity(Auth::find((int) $row->id), 'Requested password reset (' . ($result['transport'] ?? 'mail') . ')', $req);
        }
        flash('success', 'If that email exists, a reset link has been sent.');
        Response::redirect('/login');
    });

    $r->get('/reset/{token}', function (Request $req, array $p) {
        $row = DB::get("SELECT * FROM reset_tokens WHERE token = ? AND used = 0 AND expires_at > datetime('now')", [$p['token']]);
        if (!$row) {
            flash('error', 'That reset link is invalid or has expired.');
            return Response::redirect('/forgot');
        }
        View::render('pages/auth/reset', ['title' => 'Reset password', 'active' => '', 'token' => $p['token']], 'layouts/auth');
    });

    $r->post('/reset/{token}', [Auth::class, 'csrf'], function (Request $req, array $p) {
        $row = DB::get("SELECT * FROM reset_tokens WHERE token = ? AND used = 0 AND expires_at > datetime('now')", [$p['token']]);
        if (!$row) {
            flash('error', 'That reset link is invalid or has expired.');
            return Response::redirect('/forgot');
        }
        $password = (string) $req->input('password', '');
        $confirm = (string) $req->input('confirm_password', $password);
        if (strlen($password) < 6) {
            flash('error', 'Password must be at least 6 characters.');
            return Response::redirect('/reset/' . rawurlencode((string) $p['token']));
        }
        if ($password !== $confirm) {
            flash('error', 'Passwords do not match.');
            return Response::redirect('/reset/' . rawurlencode((string) $p['token']));
        }
        DB::run('UPDATE users SET password = ? WHERE id = ?', [Auth::hash($password), (int) $row->user_id]);
        DB::run('UPDATE reset_tokens SET used = 1 WHERE id = ?', [(int) $row->id]);
        Auth::logActivity(Auth::find((int) $row->user_id), 'Reset password', $req);
        flash('success', 'Password updated — you can log in now.');
        Response::redirect('/login');
    });

    /* ---------------- two-factor verification ---------------- */

    $r->get('/verify-2fa', function (Request $req) {
        $pending = Auth::pending2fa();
        if (!$pending) Response::redirect('/login');
        View::render('pages/auth/verify-2fa', ['title' => 'Two-factor check', 'active' => ''], 'layouts/auth');
    });

    $r->post('/verify-2fa', [Auth::class, 'csrf'], function (Request $req) {
        $pending = Auth::pending2fa();
        if (!$pending) return Response::redirect('/login');
        $row = Auth::find($pending);
        $code = $req->str('code');
        if (!$row || !Totp::verify((string) $row->two_factor_secret, $code)) {
            flash('error', 'That code is not valid. Try again.');
            return Response::redirect('/verify-2fa');
        }
        $next = safe_back($_SESSION['pending_2fa_next'] ?? '/', '/');
        Auth::clearPending2fa();
        Auth::login($row);
        Auth::logActivity(Auth::find($pending), 'Logged in (2FA)', $req);
        Response::redirect($next);
    });
};
