<?php
declare(strict_types=1);

namespace Core;

/** Per-company settings (tenant_settings key/value) with defaults and a per-request cache. */
final class Settings
{
    /** @var array<int, array<string,string>> */
    private static array $cache = [];

    public const DEFAULTS = [
        // company profile
        'company.legal_name' => '', 'company.address' => '', 'company.phone' => '', 'company.email' => '', 'company.website' => '',
        'company.tax_no' => '', 'company.bank' => '', 'company.logo' => '',
        // preferences
        'currency.symbol' => '', 'currency.position' => 'before', 'currency.decimals' => '2', 'number.grouping' => 'intl', 'date.format' => 'Y-m-d',
        // workflow
        'po_approval' => '0', 'negative_stock' => '0', 'shade_rule' => 'warn', 'max_discount' => '0', 'require_rack' => '0',
    ];

    private static function tid(?int $tenantId): int
    {
        return $tenantId ?? (int)Auth::tenantId();
    }

    private static function load(int $tenantId): array
    {
        if (!isset(self::$cache[$tenantId])) {
            $rows = $tenantId ? DB::all('SELECT skey, svalue FROM tenant_settings WHERE tenant_id = ?', [$tenantId]) : [];
            self::$cache[$tenantId] = array_column($rows, 'svalue', 'skey');
        }
        return self::$cache[$tenantId];
    }

    public static function get(string $key, ?string $default = null, ?int $tenantId = null): string
    {
        $all = self::load(self::tid($tenantId));
        if (array_key_exists($key, $all)) return (string)$all[$key];
        return $default ?? (string)(self::DEFAULTS[$key] ?? '');
    }

    public static function set(string $key, string $value, ?int $tenantId = null): void
    {
        $t = self::tid($tenantId);
        DB::run('INSERT INTO tenant_settings (tenant_id, skey, svalue) VALUES (?,?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$t, $key, $value]);
        self::$cache[$t][$key] = $value;
    }

    public static function delete(string $key, ?int $tenantId = null): void
    {
        $t = self::tid($tenantId);
        DB::run('DELETE FROM tenant_settings WHERE tenant_id = ? AND skey = ?', [$t, $key]);
        unset(self::$cache[$t][$key]);
    }

    public static function bool(string $key, ?int $tenantId = null): bool
    {
        return self::get($key, null, $tenantId) === '1';
    }

    public static function float(string $key, ?int $tenantId = null): float
    {
        return (float)self::get($key, null, $tenantId);
    }

    /** JSON settings (print templates, label sets). Missing keys fall back to $defaults. */
    public static function json(string $key, array $defaults = [], ?int $tenantId = null): array
    {
        $raw = self::get($key, '', $tenantId);
        $saved = $raw !== '' ? json_decode($raw, true) : null;
        return is_array($saved) ? array_replace($defaults, $saved) : $defaults;
    }

    public static function setJson(string $key, array $value, ?int $tenantId = null): void
    {
        self::set($key, (string)json_encode($value, JSON_UNESCAPED_UNICODE), $tenantId);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
