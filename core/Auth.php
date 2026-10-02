<?php
declare(strict_types=1);

namespace Core;

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MIN = 15;

    private static ?array $user = null;
    private static bool $userLoaded = false;
    private static ?array $perms = null;
    private static ?array $admin = null;
    private static bool $adminLoaded = false;

    /* ---------- tenant users ---------- */

    public static function user(): ?array
    {
        if (self::$userLoaded) return self::$user;
        self::$userLoaded = true;
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) return null;

        $u = DB::one(
            'SELECT u.*, r.name AS role_name, r.is_owner, t.name AS tenant_name, t.status AS tenant_status,
                    t.trial_ends_at, t.subscription_ends_at, p.name AS plan_name,
                    p.max_users, p.max_items, p.max_warehouses
             FROM users u
             JOIN roles r ON r.id = u.role_id
             JOIN tenants t ON t.id = u.tenant_id
             JOIN plans p ON p.id = t.plan_id
             WHERE u.id = ?', [$id]);

        if (!$u || !$u['is_active'] || self::tenantProblem($u) !== null) {
            unset($_SESSION['user_id'], $_SESSION['impersonator_admin_id']);
            return null;
        }
        return self::$user = $u;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function tenantId(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['tenant_id'] : null;
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        if (!$u) return false;
        if ($u['is_owner']) return true;
        if (self::$perms === null) {
            self::$perms = array_column(
                DB::all('SELECT permission FROM role_permissions WHERE role_id = ?', [$u['role_id']]),
                'permission');
        }
        return in_array($permission, self::$perms, true);
    }

    /** Returns a human message when the company may not use the app, else null. */
    public static function tenantProblem(array $t): ?string
    {
        if ($t['tenant_status'] === 'suspended') return 'This company account is suspended. Please contact support.';
        $today = date('Y-m-d');
        if ($t['tenant_status'] === 'trial' && $t['trial_ends_at'] && $t['trial_ends_at'] < $today) {
            return 'Your free trial has ended. Please contact support to activate your plan.';
        }
        if ($t['tenant_status'] === 'active' && $t['subscription_ends_at'] && $t['subscription_ends_at'] < $today) {
            return 'Your subscription has expired. Please renew to continue.';
        }
        return null;
    }

    /** @return string|null error message, null on success */
    public static function attempt(string $email, string $password): ?string
    {
        if (self::throttled($email)) {
            return 'Too many failed attempts. Please try again in ' . self::WINDOW_MIN . ' minutes.';
        }
        $u = DB::one(
            'SELECT u.*, t.status AS tenant_status, t.trial_ends_at, t.subscription_ends_at
             FROM users u JOIN tenants t ON t.id = u.tenant_id WHERE u.email = ?', [$email]);

        if (!$u || !password_verify($password, $u['password_hash'])) {
            self::recordFailure($email);
            return 'Invalid email or password.';
        }
        if (!$u['is_active']) return 'Your user account is disabled.';
        if ($msg = self::tenantProblem($u)) return $msg;

        DB::run('DELETE FROM login_attempts WHERE email = ?', [$email]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        DB::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
        self::reset();
        Audit::log('login', 'user', (int)$u['id'], null, (int)$u['tenant_id'], 'user', (int)$u['id']);
        return null;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['impersonator_admin_id']);
        session_regenerate_id(true);
        self::reset();
    }

    /* ---------- super admin ---------- */

    public static function admin(): ?array
    {
        if (self::$adminLoaded) return self::$admin;
        self::$adminLoaded = true;
        $id = $_SESSION['admin_id'] ?? null;
        if (!$id) return null;
        $a = DB::one('SELECT * FROM super_admins WHERE id = ? AND is_active = 1', [$id]);
        if (!$a) unset($_SESSION['admin_id']);
        return self::$admin = $a;
    }

    public static function attemptAdmin(string $email, string $password): ?string
    {
        $key = 'admin:' . $email;
        if (self::throttled($key)) {
            return 'Too many failed attempts. Please try again in ' . self::WINDOW_MIN . ' minutes.';
        }
        $a = DB::one('SELECT * FROM super_admins WHERE email = ?', [$email]);
        if (!$a || !$a['is_active'] || !password_verify($password, $a['password_hash'])) {
            self::recordFailure($key);
            return 'Invalid email or password.';
        }
        DB::run('DELETE FROM login_attempts WHERE email = ?', [$key]);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$a['id'];
        DB::run('UPDATE super_admins SET last_login_at = NOW() WHERE id = ?', [$a['id']]);
        self::reset();
        Audit::log('admin_login', 'super_admin', (int)$a['id'], null, null, 'admin', (int)$a['id']);
        return null;
    }

    public static function logoutAdmin(): void
    {
        unset($_SESSION['admin_id'], $_SESSION['user_id'], $_SESSION['impersonator_admin_id']);
        session_regenerate_id(true);
        self::reset();
    }

    /** Support login: become the company owner, remembering the admin. */
    public static function impersonate(int $tenantId): bool
    {
        $admin = self::admin();
        if (!$admin) return false;
        $owner = DB::one(
            'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.tenant_id = ? AND r.is_owner = 1 AND u.is_active = 1 ORDER BY u.id LIMIT 1', [$tenantId]);
        if (!$owner) return false;
        $_SESSION['user_id'] = (int)$owner['id'];
        $_SESSION['impersonator_admin_id'] = (int)$admin['id'];
        self::reset();
        Audit::log('impersonate_start', 'tenant', $tenantId, null, $tenantId, 'admin', (int)$admin['id']);
        return true;
    }

    public static function stopImpersonating(): void
    {
        unset($_SESSION['user_id'], $_SESSION['impersonator_admin_id']);
        self::reset();
    }

    public static function impersonating(): bool
    {
        return !empty($_SESSION['impersonator_admin_id']) && self::user() !== null;
    }

    /* ---------- internals ---------- */

    private static function reset(): void
    {
        self::$user = null;
        self::$userLoaded = false;
        self::$perms = null;
        self::$admin = null;
        self::$adminLoaded = false;
    }

    private static function throttled(string $key): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_MIN * 60);
        $n = (int)DB::val(
            'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip = ? AND attempted_at > ?',
            [$key, client_ip(), $since]);
        return $n >= self::MAX_ATTEMPTS;
    }

    private static function recordFailure(string $key): void
    {
        DB::insert('login_attempts', ['email' => $key, 'ip' => client_ip(), 'attempted_at' => date('Y-m-d H:i:s')]);
    }
}
