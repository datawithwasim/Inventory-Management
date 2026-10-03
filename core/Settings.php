<?php
declare(strict_types=1);

namespace Core;

/** Per-company key/value settings (tenant_settings). */
final class Settings
{
    public static function get(string $key, string $default = ''): string
    {
        $v = DB::val('SELECT svalue FROM tenant_settings WHERE tenant_id = ? AND skey = ?', [Auth::tenantId(), $key]);
        return $v === null ? $default : (string)$v;
    }

    public static function set(string $key, string $value): void
    {
        DB::run('INSERT INTO tenant_settings (tenant_id, skey, svalue) VALUES (?,?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
            [Auth::tenantId(), $key, $value]);
    }

    public static function bool(string $key): bool
    {
        return self::get($key, '0') === '1';
    }
}
