<?php
declare(strict_types=1);

/**
 * End-to-end smoke test for Phase 1. Run against a throw-away database:
 *   php -S 127.0.0.1:8099 -t public public/index.php &
 *   php tests/smoke.php http://127.0.0.1:8099
 */
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
require dirname(__DIR__) . '/core/bootstrap.php';

require __DIR__ . '/lib.php';

$suffix = bin2hex(random_bytes(3));

echo "Access control\n";
$anon = new Client($base);
check('dashboard redirects anonymous to login', str_ends_with((string)$anon->get('/dashboard')['location'], '/login'));
check('the old Super Admin panel is gone', in_array($anon->get('/admin')['status'], [404, 302], true) && !str_ends_with((string)$anon->get('/admin')['location'], '/admin/login'));
check('POST without CSRF token is rejected (419)', $anon->req('POST', '/login', ['email' => 'a@b.c', 'password' => 'x'])['status'] === 419);

echo "One company, set up once\n";
[, $ownerEmail] = fresh_company($base, "Acme $suffix", "owner-a-$suffix@test.local");
check('default roles provisioned', (int)Core\DB::val('SELECT COUNT(*) FROM roles') === 5);
check('the company has its starter masters', (int)Core\DB::val('SELECT COUNT(*) FROM units') === 6 && (int)Core\DB::val('SELECT COUNT(*) FROM warehouses') === 1);
check('setting up again changes nothing', Core\Seeder::run('Other', 'X', 'x@test.local', 'Password123') === [] && !Core\DB::val("SELECT 1 FROM users WHERE email = 'x@test.local'"));
check('no company table, plan or Super Admin is left in the database', !Core\DB::val("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('tenants', 'plans', 'super_admins')")
    && !Core\DB::val("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name = 'tenant_id'"));

echo "Company owner\n";
$a = new Client($base);
$a->post('/login', ['email' => "owner-a-$suffix@test.local", 'password' => 'wrong-password'], '/login');
check('a wrong password does not sign in', str_ends_with((string)$a->get('/dashboard')['location'], '/login'));
$r = $a->post('/login', ['email' => "owner-a-$suffix@test.local", 'password' => 'Password123'], '/login');
check('owner signs in', str_ends_with((string)$r['location'], '/dashboard'));
check('dashboard shows company name', str_contains($a->get('/dashboard')['body'], "Acme $suffix"));
$staffRole = (int)Core\DB::val("SELECT id FROM roles WHERE name = 'Staff'");
$r = $a->post('/users', ['name' => 'Staff One', 'email' => "staff-$suffix@test.local", 'password' => 'Password123', 'role_id' => $staffRole, 'warehouse_id' => '0'], '/users/create');
check('owner adds a staff user', str_contains($a->get('/users')['body'], "staff-$suffix@test.local"));
for ($i = 2; $i <= 4; $i++) $a->post('/users', ['name' => "Staff $i", 'email' => "staff$i-$suffix@test.local", 'password' => 'Password123', 'role_id' => $staffRole, 'warehouse_id' => '0'], '/users/create');
check('there is no user limit any more', (int)Core\DB::val('SELECT COUNT(*) FROM users') === 5);

echo "Roles\n";
$a->post('/roles', ['name' => 'Storekeeper', 'perms' => ['stock.view', 'stock.adjust', 'bogus.perm']], '/roles/create');
$roleId = (int)Core\DB::val("SELECT id FROM roles WHERE name = 'Storekeeper'");
check('custom role created', $roleId > 0);
check('only valid permissions saved', (int)Core\DB::val('SELECT COUNT(*) FROM role_permissions WHERE role_id = ?', [$roleId]) === 2);
$ownerRole = (int)Core\DB::val("SELECT id FROM roles WHERE is_owner = 1");
check('owner role cannot be deleted', (function () use ($a, $ownerRole) { $a->post("/roles/$ownerRole/delete", [], '/roles'); return (int)Core\DB::val('SELECT COUNT(*) FROM roles WHERE id = ?', [$ownerRole]) === 1; })());

echo "Staff permissions\n";
$s = new Client($base);
$s->post('/login', ['email' => "staff-$suffix@test.local", 'password' => 'Password123'], '/login');
check('staff can open dashboard', $s->get('/dashboard')['status'] === 200);
check('staff cannot create users (403)', $s->get('/users/create')['status'] === 403);
check('staff cannot edit roles (403)', $s->get("/roles/$roleId/edit")['status'] === 403);

echo "Updates & backup (owner only)\n";
check('the owner sees Settings → Updates & backup, up to date', str_contains($a->get('/settings/system')['body'], 'up to date'));
check('staff cannot open it or run updates (403)', $s->get('/settings/system')['status'] === 403 && $s->post('/settings/system/migrate', [], '/dashboard')['status'] === 403);

echo "Password reset & lockout\n";
$p = new Client($base);
@unlink(ROOT . '/storage/logs/mail.log');
$p->post('/forgot-password', ['email' => "staff-$suffix@test.local"], '/forgot-password');
$mail = (string)@file_get_contents(ROOT . '/storage/logs/mail.log');
preg_match('#/reset-password/([a-f0-9]+)#', $mail, $m);
check('reset email logged with token', !empty($m[1]));
$p->post("/reset-password/{$m[1]}", ['password' => 'NewPassword456', 'password_confirm' => 'NewPassword456'], "/reset-password/{$m[1]}");
$s2 = new Client($base);
check('new password works', str_ends_with((string)$s2->post('/login', ['email' => "staff-$suffix@test.local", 'password' => 'NewPassword456'], '/login')['location'], '/dashboard'));
$p->post("/reset-password/{$m[1]}", ['password' => 'Another789!', 'password_confirm' => 'Another789!'], '/forgot-password');
check('reset token is single-use', !Core\DB::val('SELECT 1 FROM users WHERE email = ? AND password_hash = ?', ["staff-$suffix@test.local", 'x']) && password_verify('NewPassword456', Core\DB::val('SELECT password_hash FROM users WHERE email = ?', ["staff-$suffix@test.local"])));
$l = new Client($base);
for ($i = 0; $i < 6; $i++) $l->post('/login', ['email' => "staff2-$suffix@test.local", 'password' => 'wrong'], '/login');
$r = $l->post('/login', ['email' => "staff2-$suffix@test.local", 'password' => 'Password123'], '/login');
check('account locks after repeated failures', !str_ends_with((string)$r['location'], '/dashboard'));

echo $fails ? "\n$fails check(s) FAILED\n" : "\nAll checks passed\n";
exit($fails ? 1 : 0);
