<?php
declare(strict_types=1);

namespace App\Models;

use Core\Auth;
use Core\DB;

/**
 * The currencies a company buys in. Rupees are the books' currency (rate 1); the others carry the number of rupees one unit is worth,
 * which the company keeps up to date (Rate lists → Exchange rates). Purchase orders to a foreign supplier remember the rate of the day.
 */
final class Currencies
{
    public const BASE = 'INR';
    /** code => [name, symbol, rupees per unit when a company first looks]. */
    public const DEFAULTS = ['INR' => ['Indian rupee', '₹', 1], 'USD' => ['US dollar', '$', 83], 'EUR' => ['Euro', '€', 90], 'GBP' => ['Pound sterling', '£', 105],
        'AED' => ['UAE dirham', 'AED', 22.6], 'CNY' => ['Chinese yuan', 'CN¥', 11.5], 'JPY' => ['Japanese yen', 'JP¥', 0.56]];
    public const ORIGINS = ['domestic' => 'Domestic', 'imported' => 'Imported', 'market' => 'Market (local)'];

    private static function tid(): int
    {
        return (int)Auth::tenantId();
    }

    /** Adds the usual currencies the first time a company needs them. */
    private static function ensure(): void
    {
        $t = self::tid();
        if ((int)DB::val('SELECT COUNT(*) FROM currencies WHERE tenant_id = ?', [$t]) > 0) return;
        foreach (self::DEFAULTS as $code => [$name, $symbol, $rate]) {
            DB::run('INSERT IGNORE INTO currencies (tenant_id, code, name, symbol, rate_to_inr, is_active) VALUES (?,?,?,?,?,?)', [$t, $code, $name, $symbol, $rate, $code === self::BASE ? 1 : 1]);
        }
    }

    /** @return array<int, array> every currency of the company, rupees first. */
    public static function all(bool $onlyActive = false): array
    {
        self::ensure();
        return DB::all('SELECT code, name, symbol, rate_to_inr, is_active, updated_at FROM currencies WHERE tenant_id = ?' . ($onlyActive ? ' AND is_active = 1' : '') . " ORDER BY code = 'INR' DESC, code", [self::tid()]);
    }

    public static function get(string $code): ?array
    {
        self::ensure();
        return DB::one('SELECT code, name, symbol, rate_to_inr, is_active FROM currencies WHERE tenant_id = ? AND code = ?', [self::tid(), strtoupper($code)]);
    }

    /** Rupees for one unit of $code (1 for rupees, and for a currency the company does not know). */
    public static function rate(string $code): float
    {
        if ($code === '' || $code === self::BASE) return 1.0;
        $c = self::get($code);
        return $c && (float)$c['rate_to_inr'] > 0 ? (float)$c['rate_to_inr'] : 1.0;
    }

    public static function toInr(float $amount, string $code, ?float $rate = null): float
    {
        return round($amount * ($rate ?? self::rate($code)), 2);
    }

    /** An amount in its currency: rupees the way the company writes money, other currencies with their symbol. */
    public static function format(float $amount, string $code): string
    {
        if ($code === '' || $code === self::BASE) return money($amount);
        $c = self::get($code);
        return ($c['symbol'] ?? $code) . ' ' . number_format($amount, 2);
    }

    /** code => label for the pick lists. */
    public static function options(): array
    {
        return array_map(fn($c) => ['id' => $c['code'], 'name' => $c['code'] . ' — ' . $c['name']], self::all(true));
    }

    /** The currency (and today's exchange rate) a purchase order to this supplier is made in. */
    public static function forSupplier(array $supplier): array
    {
        $code = ($supplier['origin'] ?? 'domestic') === 'imported' ? strtoupper((string)($supplier['currency'] ?? self::BASE)) : self::BASE;
        return [$code, self::rate($code)];
    }
}
