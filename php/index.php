<?php
/**
 * NobitaHost · PHP edition — front controller.
 *
 * Works with:
 *   • php -S 0.0.0.0:8080 router.php      (dev / preview)
 *   • Apache + .htaccess                  (shared hosting)
 *   • nginx  try_files $uri /index.php?$query_string
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$req = Request::capture();
Response::status(200);

View::init(NH_VIEWS);

$navPages = [];
try {
    $navPages = DB::all('SELECT slug, title, icon FROM content_pages WHERE in_nav = 1 ORDER BY id');
} catch (Throwable $e) { /* noop */ }

$footerData = null;
$navData = [];
try {
    $footerData = Obsidian::parseFooter(Obsidian::load('footer'));
    $navData = Obsidian::navbarLinks($NH_USER);
} catch (Throwable $e) { /* obsidian blocks are optional */ }

View::$globals = [
    'settings' => $NH_SETTINGS,
    'user' => $NH_USER,
    'bg' => Settings::resolveBackground($NH_SETTINGS),
    'navPages' => $navPages,
    'footerData' => $footerData,
    'navData' => $navData,
    'normalizeBlur' => Settings::normalizeBlur($NH_SETTINGS),
    'overlayAlpha' => Settings::overlayAlpha($NH_SETTINGS),
    'active' => '',
    'title' => '',
];

/* ---------------- maintenance mode ---------------- */
if (($NH_SETTINGS->maintenance ?? 'off') === 'on' && !str_starts_with($req->path, '/login') && !str_starts_with($req->path, '/api/')) {
    if (!($NH_USER && ($NH_USER->role ?? '') === 'admin')) {
        Response::status(503);
        View::render('pages/errors/maintenance', ['title' => 'Maintenance', 'active' => ''], 'layouts/auth');
        return;
    }
}

/* ---------------- routes ---------------- */
$router = new Router();

$register = function (string $file) use ($router): void {
    $path = NH_ROOT . '/routes/' . $file;
    if (is_file($path)) (require $path)($router);
};

try {
    $register('api.php');
    $register('auth.php');
    $register('web.php');

    if (!$router->dispatch($req)) {
        Response::status(404);
        if ($req->json()) {
            Response::json(['success' => false, 'error' => 'Not Found'], 404);
        } else {
            View::render('pages/errors/404', ['title' => '404', 'active' => ''], 'layouts/app');
        }
    }
} catch (Throwable $ex) {
    error_log('[NobitaHost] ' . $ex->getMessage() . "\n" . $ex->getTraceAsString());
    Response::status($ex instanceof HttpException ? $ex->status : 500);
    if ($req->json()) {
        Response::json(['success' => false, 'error' => $ex->getMessage()], Response::$status);
        return;
    }
    if (Env::get('APP_DEBUG') === 'true' && PHP_SAPI === 'cli-server') {
        Response::text($ex->getMessage() . "\n\n" . $ex->getFile() . ':' . $ex->getLine() . "\n" . $ex->getTraceAsString(), 500);
        return;
    }
    View::render('pages/errors/500', ['title' => 'Server Error', 'active' => '', 'error' => $ex->getMessage()], 'layouts/app');
}

/** Simple exception carrying an HTTP status. */
class HttpException extends RuntimeException
{
    public function __construct(public int $status, string $message = '')
    {
        parent::__construct($message ?: 'HTTP ' . $status);
    }
}
