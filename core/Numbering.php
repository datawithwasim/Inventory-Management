<?php
declare(strict_types=1);

namespace Core;

/** Per-company running document numbers. Prefix, digits and yearly restart are set in Settings → Numbering. */
final class Numbering
{
    /** Internal code => label shown in Settings. */
    public const DOCS = [
        'SO' => 'Sales order', 'DLV' => 'Delivery', 'INV' => 'Sales invoice', 'SR' => 'Sales return',
        'REQ' => 'Purchase requisition', 'PO' => 'Purchase order', 'GRN' => 'Goods receipt', 'PB' => 'Purchase bill', 'PRT' => 'Purchase return',
        'ADJ' => 'Stock adjustment', 'TRF' => 'Stock transfer', 'STK' => 'Stock-take', 'SKU' => 'Auto-generated item SKU',
    ];

    /** @return array{prefix:string, pad:int, year:bool} */
    public static function config(int $tenantId, string $code): array
    {
        $saved = Settings::json('numbering.' . $code, [], $tenantId);
        return [
            'prefix' => (string)($saved['prefix'] ?? $code),
            'pad' => max(3, min(8, (int)($saved['pad'] ?? 4))),
            'year' => !empty($saved['year']),
        ];
    }

    public static function format(array $cfg, int $n, ?string $year = null): string
    {
        return $cfg['prefix'] . '-' . ($cfg['year'] ? ($year ?? date('Y')) . '-' : '') . str_pad((string)$n, $cfg['pad'], '0', STR_PAD_LEFT);
    }

    /** Call inside a transaction. */
    public static function next(int $tenantId, string $code): string
    {
        $cfg = self::config($tenantId, $code);
        $key = $code . ($cfg['year'] ? date('y') : '');           // a yearly series restarts with the year
        DB::run('INSERT IGNORE INTO counters (tenant_id, prefix, next_no) VALUES (?, ?, 1)', [$tenantId, $key]);
        $n = (int)DB::val('SELECT next_no FROM counters WHERE tenant_id = ? AND prefix = ? FOR UPDATE', [$tenantId, $key]);
        DB::run('UPDATE counters SET next_no = next_no + 1 WHERE tenant_id = ? AND prefix = ?', [$tenantId, $key]);
        return self::format($cfg, $n);
    }
}
