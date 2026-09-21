<?php
/**
 * Dev-server router (php -S ... router.php).
 * Serves real files from the app root, everything else goes to index.php.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

// never expose internals
foreach (['/app/', '/data/', '/cli/', '/views/', '/routes/', '/storage/cache'] as $blocked) {
    if (str_starts_with($path, $blocked)) {
        http_response_code(404);
        echo 'Not Found';
        return true;
    }
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    // let the built-in server stream it with the right mime type
    return false;
}

require __DIR__ . '/index.php';
