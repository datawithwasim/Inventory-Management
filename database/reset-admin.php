<?php
declare(strict_types=1);

// Emergency Super Admin password reset, for when the reset e-mail does not arrive.
// Usage (SSH / cPanel Terminal / cron):  php database/reset-admin.php admin@example.com 'NewPassword123'
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/core/bootstrap.php';

[$self, $email, $password] = $argv + [null, null, null];
if (!$email || !$password || strlen($password) < 8) {
    fwrite(STDERR, "Usage: php database/reset-admin.php <super-admin-email> <new-password (8+ characters)>\n");
    exit(1);
}
$email = strtolower(trim($email));
$n = Core\DB::run('UPDATE super_admins SET password_hash = ?, is_active = 1 WHERE email = ?', [password_hash($password, PASSWORD_DEFAULT), $email]);
if (!Core\DB::val('SELECT 1 FROM super_admins WHERE email = ?', [$email])) { fwrite(STDERR, "No Super Admin with that email.\n"); exit(1); }
Core\DB::run('DELETE FROM login_attempts WHERE email = ?', ['admin:' . $email]);
echo "Password updated for $email. Sign in at /admin/login and change it again if you shared it.\n";
