<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $map = [
        'Core\\'            => ROOT . '/core/',
        'App\\Controllers\\' => ROOT . '/app/Controllers/',
        'App\\Models\\'      => ROOT . '/app/Models/',
        'Admin\\Controllers\\' => ROOT . '/admin/Controllers/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require $file;
            return;
        }
    }
});

$GLOBALS['config'] = require ROOT . '/config/config.php';
require __DIR__ . '/helpers.php';

date_default_timezone_set('UTC');

if (PHP_SAPI !== 'cli') {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_name('inv_session');
    session_start();
}
