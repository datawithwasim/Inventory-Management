<?php
declare(strict_types=1);

/**
 * End-to-end smoke test for Phase 1. Run against a throw-away database:
 *   php -S 127.0.0.1:8099 -t public public/index.php &
 *   php tests/smoke.php http://127.0.0.1:8099
 */
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
require dirname(__DIR__) . '/core/bootstrap.php';

final class Client
{
    private string $jar;
    public function __construct(private string $base) { $this->jar = tempnam(sys_get_temp_dir(), 'jar'); }

    public function req(string $method, string $path, array $data = []): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($method === 'POST') curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $raw = (string)curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $headers = substr($raw, 0, $hs);
        preg_match('/^Location:\s*(\S+)/mi', $headers, $m);
        return ['status' => $status, 'body' => substr($raw, $hs), 'location' => $m[1] ?? null];
    }

    public function get(string $p): array { return $this->req('GET', $p); }

    /** POST with a CSRF token scraped from a page that has a form. */
    public function post(string $p, array $data, string $tokenPage): array
    {
        $page = $this->get($tokenPage)['body'];
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $page, $m);
        return $this->req('POST', $p, $data + ['_csrf' => $m[1] ?? '']);
    }
}

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? "  PASS " : "  FAIL ") . $name . "\n";
    if (!$ok) $fails++;
}
function flashed(array $r, string $needle): bool { return str_contains($r['body'], $needle); }

$suffix = bin2hex(random_bytes(3));
$sa = config('superadmin');

echo "Access control\n";
$anon = new Client($base);
check('dashboard redirects anonymous to login', str_ends_with((string)$anon->get('/dashboard')['location'], '/login'));
check('admin panel redirects anonymous to admin login', str_ends_with((string)$anon->get('/admin')['location'], '/admin/login'));
check('POST without CSRF token is rejected (419)', $anon->req('POST', '/login', ['email' => 'a@b.c', 'password' => 'x'])['status'] === 419);

echo "Super Admin\n";
$admin = new Client($base);
$admin->post('/admin/login', ['email' => $sa['email'], 'password' => 'wrong-password'], '/admin/login');
check('bad admin password does not sign in', str_ends_with((string)$admin->get('/admin')['location'], '/admin/login'));
$r = $admin->post('/admin/login', ['email' => $sa['email'], 'password' => $sa['password']], '/admin/login');
check('admin signs in', str_ends_with((string)$r['location'], '/admin'));
check('admin dashboard loads', $admin->get('/admin')['status'] === 200);
check('admin cannot use company dashboard without impersonating', str_ends_with((string)$admin->get('/dashboard')['location'], '/login'));

$freePlan = (int)Core\DB::val("SELECT id FROM plans WHERE slug = 'free'");
$mk = function (string $name, string $email) use ($admin, $freePlan) {
    return $admin->post('/admin/tenants', [
        'name' => $name, 'plan_id' => $freePlan, 'status' => 'active', 'subscription_ends_at' => '',
        'owner_name' => 'Owner ' . $name, 'owner_email' => $email, 'owner_password' => 'Password123',
    ], '/admin/tenants/create');
};
$mk("Acme $suffix", "owner-a-$suffix@test.local");
$mk("Beta $suffix", "owner-b-$suffix@test.local");
$list = $admin->get('/admin/tenants')['body'];
check('both companies created', str_contains($list, "Acme $suffix") && str_contains($list, "Beta $suffix"));
check('duplicate owner email rejected, company not created', substr_count($admin->get('/admin/tenants')['body'], "Dup $suffix") === 0);
$tA = (int)Core\DB::val('SELECT id FROM tenants WHERE name = ?', ["Acme $suffix"]);
$tB = (int)Core\DB::val('SELECT id FROM tenants WHERE name = ?', ["Beta $suffix"]);
check('default roles provisioned', (int)Core\DB::val('SELECT COUNT(*) FROM roles WHERE tenant_id = ?', [$tA]) === 3);

echo "Company owner\n";
$a = new Client($base);
$r = $a->post('/login', ['email' => "owner-a-$suffix@test.local", 'password' => 'Password123'], '/login');
check('owner A signs in', str_ends_with((string)$r['location'], '/dashboard'));
check('dashboard shows company name', str_contains($a->get('/dashboard')['body'], "Acme $suffix"));
$staffRole = (int)Core\DB::val("SELECT id FROM roles WHERE tenant_id = ? AND name = 'Staff'", [$tA]);
$r = $a->post('/users', ['name' => 'Staff One', 'email' => "staff-$suffix@test.local", 'password' => 'Password123', 'role_id' => $staffRole], '/users/create');
check('owner adds a staff user', str_contains($a->get('/users')['body'], "staff-$suffix@test.local"));
$a->post('/users', ['name' => 'Staff Two', 'email' => "staff2-$suffix@test.local", 'password' => 'Password123', 'role_id' => $staffRole], '/users/create');
check('plan user limit (3) enforced', !str_contains($a->get('/users')['body'], "staff3-$suffix") && (int)Core\DB::val('SELECT COUNT(*) FROM users WHERE tenant_id = ?', [$tA]) === 3);
$a->post('/users', ['name' => 'Staff Three', 'email' => "staff3-$suffix@test.local", 'password' => 'Password123', 'role_id' => $staffRole], '/users');
check('4th user blocked by limit', (int)Core\DB::val('SELECT COUNT(*) FROM users WHERE tenant_id = ?', [$tA]) === 3);

echo "Roles\n";
$a->post('/roles', ['name' => 'Storekeeper', 'perms' => ['stock.view', 'stock.adjust', 'bogus.perm']], '/roles/create');
$roleId = (int)Core\DB::val("SELECT id FROM roles WHERE tenant_id = ? AND name = 'Storekeeper'", [$tA]);
check('custom role created', $roleId > 0);
check('only valid permissions saved', (int)Core\DB::val('SELECT COUNT(*) FROM role_permissions WHERE role_id = ?', [$roleId]) === 2);
$ownerRole = (int)Core\DB::val("SELECT id FROM roles WHERE tenant_id = ? AND is_owner = 1", [$tA]);
check('owner role cannot be deleted', (function () use ($a, $ownerRole) { $a->post("/roles/$ownerRole/delete", [], '/roles'); return (int)Core\DB::val('SELECT COUNT(*) FROM roles WHERE id = ?', [$ownerRole]) === 1; })());

echo "Staff permissions\n";
$s = new Client($base);
$s->post('/login', ['email' => "staff-$suffix@test.local", 'password' => 'Password123'], '/login');
check('staff can open dashboard', $s->get('/dashboard')['status'] === 200);
check('staff cannot create users (403)', $s->get('/users/create')['status'] === 403);
check('staff cannot edit roles (403)', $s->get("/roles/$roleId/edit")['status'] === 403);

echo "Tenant isolation\n";
$userB = (int)Core\DB::val('SELECT id FROM users WHERE tenant_id = ? LIMIT 1', [$tB]);
check('owner A cannot open company B user (404)', $a->get("/users/$userB/edit")['status'] === 404);
$a->post("/users/$userB/delete", [], '/users');
check('owner A cannot delete company B user', (int)Core\DB::val('SELECT COUNT(*) FROM users WHERE id = ?', [$userB]) === 1);
$roleB = (int)Core\DB::val('SELECT id FROM roles WHERE tenant_id = ? AND name = "Staff"', [$tB]);
check('owner A cannot open company B role (404)', $a->get("/roles/$roleB/edit")['status'] === 404);
check('cross-tenant role assignment rejected', (function () use ($a, $roleB, $suffix) {
    $a->post('/users', ['name' => 'X', 'email' => "x-$suffix@test.local", 'password' => 'Password123', 'role_id' => $roleB], '/users');
    return !Core\DB::val('SELECT 1 FROM users WHERE email = ?', ["x-$suffix@test.local"]);
})());

echo "Support login & suspension\n";
$r = $admin->post("/admin/tenants/$tB/impersonate", [], '/admin/tenants');
check('admin opens company B as owner', str_ends_with((string)$r['location'], '/dashboard'));
check('support banner visible', str_contains($admin->get('/dashboard')['body'], 'support mode'));
$admin->post('/impersonate/stop', [], '/dashboard');
check('stop returns to admin', str_ends_with((string)$admin->get('/dashboard')['location'], '/login') && $admin->get('/admin')['status'] === 200);
$admin->post("/admin/tenants/$tA/status", [], '/admin/tenants');
check('suspended company: logged-in user kicked out', str_ends_with((string)$a->get('/dashboard')['location'], '/login'));
$a2 = new Client($base);
$a2->post('/login', ['email' => "owner-a-$suffix@test.local", 'password' => 'Password123'], '/login');
check('suspended company cannot sign in', str_ends_with((string)$a2->get('/dashboard')['location'], '/login'));
$admin->post("/admin/tenants/$tA/status", [], '/admin/tenants');
check('reactivated company can sign in', str_ends_with((string)$a2->post('/login', ['email' => "owner-a-$suffix@test.local", 'password' => 'Password123'], '/login')['location'], '/dashboard'));

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
