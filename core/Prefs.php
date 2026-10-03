<?php
declare(strict_types=1);

namespace Core;

/** Per-user preferences (dashboard layout, list columns …). Small JSON values keyed by name. */
final class Prefs
{
    private static ?array $cache = null;

    private static function uid(): int
    {
        return (int)(Auth::user()['id'] ?? 0);
    }

    private static function all(): array
    {
        if (self::$cache === null) {
            $u = self::uid();
            self::$cache = $u ? array_column(DB::all('SELECT pkey, pvalue FROM user_prefs WHERE user_id = ?', [$u]), 'pvalue', 'pkey') : [];
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        if (!isset($all[$key])) return $default;
        $v = json_decode($all[$key], true);
        return $v === null ? $default : $v;
    }

    public static function set(string $key, mixed $value): void
    {
        $u = self::uid();
        if (!$u) return;
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        DB::run('INSERT INTO user_prefs (user_id, pkey, pvalue) VALUES (?,?,?) ON DUPLICATE KEY UPDATE pvalue = VALUES(pvalue)', [$u, $key, $json]);
        self::$cache = null;
    }

    public static function forget(string $key): void
    {
        DB::run('DELETE FROM user_prefs WHERE user_id = ? AND pkey = ?', [self::uid(), $key]);
        self::$cache = null;
    }
}
