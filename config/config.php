<?php
$env = [];
$file = dirname(__DIR__) . '/.env';
if (is_file($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim(trim($v), "\"'");
    }
}
$get = fn(string $k, $d = null) => $env[$k] ?? getenv($k) ?: $d;

return [
    'name'  => $get('APP_NAME', 'Inventory'),
    'debug' => filter_var($get('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'db' => [
        'host' => $get('DB_HOST', '127.0.0.1'),
        'port' => $get('DB_PORT', '3306'),
        'name' => $get('DB_NAME', 'inventory'),
        'user' => $get('DB_USER', 'root'),
        'pass' => $get('DB_PASS', ''),
    ],
    'mail' => [
        'driver' => $get('MAIL_DRIVER', 'log'),
        'from'   => $get('MAIL_FROM', 'noreply@localhost'),
    ],
    'superadmin' => [
        'name'     => $get('SUPERADMIN_NAME', 'Super Admin'),
        'email'    => $get('SUPERADMIN_EMAIL', 'admin@example.com'),
        'password' => $get('SUPERADMIN_PASSWORD', 'ChangeMe123!'),
    ],
    'permissions' => require __DIR__ . '/permissions.php',
];
