<?php
/**
 * NobitaHost · PHP edition — bootstrap
 * Loads env, core classes, session, settings and helpers.
 */
declare(strict_types=1);

define('NH_ROOT', dirname(__DIR__));            // /php
define('NH_APP', NH_ROOT . '/app');
define('NH_VIEWS', NH_ROOT . '/views');
define('NH_ASSETS', NH_ROOT . '/assets');
define('NH_DATA', NH_ROOT . '/data');
define('NH_UPLOADS', NH_ROOT . '/storage/uploads');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set(getenv('TZ') ?: 'UTC');

foreach ([NH_DATA, NH_UPLOADS, NH_ROOT . '/storage/cache'] as $dir) {
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
}

spl_autoload_register(function (string $class): void {
    $file = NH_APP . '/Core/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) require_once $file;
});

require_once NH_APP . '/Core/helpers.php';

Env::load(NH_ROOT . '/.env');
Env::load(dirname(NH_ROOT) . '/.env');   // repo-root .env (shared with the node app)

DB::migrate();

session_name('nh_php');
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();
}

Request::boot();

$NH_SETTINGS = Settings::all();
$NH_USER     = Auth::user();
