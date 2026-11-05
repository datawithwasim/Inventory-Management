<?php
declare(strict_types=1);

namespace Core;

/** Running document numbers. Prefix, digits and yearly restart are set in Settings → Numbering. */
final class Numbering
{
    /** Internal code => label shown in Settings. */
    public const DOCS = [
        'SO' => 'Sales order', 'DLV' => 'Delivery', 'INV' => 'Sales invoice', 'SR' => 'Sales return',
        'REQ' => 'Purchase requisition', 'PO' => 'Purchase order', 'GRN' => 'Goods receipt', 'PB' => 'Purchase bill', 'PRT' => 'Purchase return',
        'CL' => 'Catalogue launch', 'LIS' => 'Fabric dispatched for a launch', 'LRT' => 'Fabric returned from a launch', 'LRC' => 'Catalogues received',
        'ADJ' => 'Stock adjustment', 'TRF' => 'Stock transfer', 'STK' => 'Stock-take', 'SKU' => 'Auto-generated item SKU',
    ];

    /** @return array{prefix:string, pad:int, year:bool} */
    public static function config(string $code): array
    {
        $saved = Settings::json('numbering.' . $code, []);
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
    public static function next(string $code): string
    {
        $cfg = self::config($code);
        $key = $code . ($cfg['year'] ? date('y') : '');           // a yearly series restarts with the year
        DB::run('INSERT IGNORE INTO counters (prefix, next_no) VALUES (?, 1)', [$key]);
        $n = (int)DB::val('SELECT next_no FROM counters WHERE prefix = ? FOR UPDATE', [$key]);
        DB::run('UPDATE counters SET next_no = next_no + 1 WHERE prefix = ?', [$key]);
        return self::format($cfg, $n);
    }
}
