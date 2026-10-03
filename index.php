<?php
// Front controller. Works both when the web root is this folder (cPanel public_html)
// and when the web root is public/ (public/index.php includes this file).
// Keep this file's syntax old-PHP-safe so the version message below can be shown.
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    echo 'This application needs PHP 8.1 or newer. You are running PHP ' . PHP_VERSION
        . '. In cPanel open "MultiPHP Manager" and select PHP 8.1 or higher for this domain.';
    exit;
}

require __DIR__ . '/core/bootstrap.php';

try {
    $router = new Core\Router();
    require ROOT . '/config/routes.php';

    $path = '/' . trim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $base = base_path();
    if ($base !== '' && strpos($path, $base) === 0) $path = '/' . trim(substr($path, strlen($base)), '/');
    if (!App\Controllers\InstallController::installed() && $path !== '/install') {
        redirect('install');
    }

    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    if (config('debug')) {
        echo '<pre>' . e((string)$e) . '</pre>';
    } else {
        Core\View::render('errors/500', [], null);
    }
}
