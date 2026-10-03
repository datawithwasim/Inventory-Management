<?php
// PHP built-in server: let it serve real static files.
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($f) && $f !== __FILE__) return false;
}
require dirname(__DIR__) . '/index.php';
