<?php
declare(strict_types=1);

namespace Core;

/** Company settings (key/value) with defaults and a per-request cache. */
final class Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public const DEFAULTS = [
        // company profile
        'company.legal_name' => '', 'company.address' => '', 'company.phone' => '', 'company.email' => '', 'company.website' => '',
        'company.tax_no' => '', 'company.bank' => '', 'company.logo' => '',
        // preferences
        'currency.symbol' => '', 'currency.position' => 'before', 'currency.decimals' => '2', 'number.grouping' => 'intl', 'date.format' => 'Y-m-d',
        // workflow
        'po_approval' => '0', 'negative_stock' => '0', 'shade_rule' => 'warn', 'max_discount' => '0', 'require_rack' => '0',
    ];

    private static function load(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = array_column(DB::all('SELECT skey, svalue FROM settings'), 'svalue', 'skey');
            } catch (\PDOException) {
                self::$cache = [];   // an older database waiting for its update (Settings → Updates & backup): run on the defaults until then
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): string
    {
        $all = self::load();
        if (array_key_exists($key, $all)) return (string)$all[$key];
        return $default ?? (string)(self::DEFAULTS[$key] ?? '');
    }

    public static function set(string $key, string $value): void
    {
        DB::run('INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$key, $value]);
        if (self::$cache !== null) self::$cache[$key] = $value;
    }

    public static function delete(string $key): void
    {
        DB::run('DELETE FROM settings WHERE skey = ?', [$key]);
        unset(self::$cache[$key]);
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    public static function float(string $key): float
    {
        return (float)self::get($key);
    }

    /** JSON settings (print templates, label sets). Missing keys fall back to $defaults. */
    public static function json(string $key, array $defaults = []): array
    {
        $raw = self::get($key, '');
        $saved = $raw !== '' ? json_decode($raw, true) : null;
        return is_array($saved) ? array_replace($defaults, $saved) : $defaults;
    }

    public static function setJson(string $key, array $value): void
    {
        self::set($key, (string)json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
