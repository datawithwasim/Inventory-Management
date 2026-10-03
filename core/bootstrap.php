<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $map = [
        'Core\\'            => ROOT . '/core/',
        'App\\Controllers\\' => ROOT . '/app/Controllers/',
        'App\\Models\\'      => ROOT . '/app/Models/',
        'App\\Admin\\'       => ROOT . '/app/Admin/',
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
    // Hardening: headers that stop framing/sniffing, strict cookie-only sessions, and a 4-hour idle timeout.
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (!empty($_SERVER['HTTPS'])) header('Strict-Transport-Security: max-age=15552000');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_name('inv_session');
    session_start();
    if (!empty($_SESSION['_last']) && time() - (int)$_SESSION['_last'] > 14400) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['_last'] = time();
}
