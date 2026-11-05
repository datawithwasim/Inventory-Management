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

    /* ---------- users ---------- */

    public static function user(): ?array
    {
        if (self::$userLoaded) return self::$user;
        self::$userLoaded = true;
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) return null;
        $u = DB::one('SELECT u.*, r.name AS role_name, r.is_owner FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$id]);
        if (!$u || !$u['is_active']) {
            unset($_SESSION['user_id']);
            return null;
        }
        return self::$user = $u;
    }

    public static function check(): bool
    {
        return self::user() !== null;
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

    /** @return string|null error message, null on success */
    public static function attempt(string $email, string $password): ?string
    {
        if (self::throttled($email)) {
            return 'Too many failed attempts. Please try again in ' . self::WINDOW_MIN . ' minutes.';
        }
        $u = DB::one('SELECT * FROM users WHERE email = ?', [$email]);

        if (!$u || !password_verify($password, $u['password_hash'])) {
            self::recordFailure($email);
            return 'Invalid email or password.';
        }
        if (!$u['is_active']) return 'Your user account is disabled.';

        DB::run('DELETE FROM login_attempts WHERE email = ?', [$email]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        DB::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
        self::reset();
        Audit::log('login', 'user', (int)$u['id'], null, 'user', (int)$u['id']);
        return null;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
        self::reset();
    }

    /* ---------- internals ---------- */

    private static function reset(): void
    {
        self::$user = null;
        self::$userLoaded = false;
        self::$perms = null;
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
