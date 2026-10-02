<?php
declare(strict_types=1);

// PHP built-in server: let it serve real static files.
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($f) && $f !== __FILE__) return false;
}

require dirname(__DIR__) . '/core/bootstrap.php';

try {
    $router = new Core\Router();
    require ROOT . '/config/routes.php';
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
