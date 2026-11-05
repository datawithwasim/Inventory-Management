<?php
declare(strict_types=1);

// Emergency password reset for a user, for when the reset e-mail does not arrive.
// Usage (SSH / cPanel Terminal / cron):  php database/reset-password.php owner@example.com 'NewPassword123'
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/core/bootstrap.php';

[$self, $email, $password] = $argv + [null, null, null];
if (!$email || !$password || strlen($password) < 8) {
    fwrite(STDERR, "Usage: php database/reset-password.php <user-email> <new-password (8+ characters)>\n");
    exit(1);
}
$email = strtolower(trim($email));
if (!Core\DB::val('SELECT 1 FROM users WHERE email = ?', [$email])) { fwrite(STDERR, "No user with that email.\n"); exit(1); }
Core\DB::run('UPDATE users SET password_hash = ?, is_active = 1 WHERE email = ?', [password_hash($password, PASSWORD_DEFAULT), $email]);
Core\DB::run('DELETE FROM login_attempts WHERE email = ?', [$email]);
echo "Password updated for $email. Sign in and change it again if you shared it.\n";
