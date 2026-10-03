<?php
declare(strict_types=1);

namespace Core;

/** Per-company running document numbers (ADJ-0001, TRF-0001 ...). Call inside a transaction. */
final class Numbering
{
    public static function next(int $tenantId, string $prefix): string
    {
        DB::run('INSERT IGNORE INTO counters (tenant_id, prefix, next_no) VALUES (?, ?, 1)', [$tenantId, $prefix]);
        $n = (int)DB::val('SELECT next_no FROM counters WHERE tenant_id = ? AND prefix = ? FOR UPDATE', [$tenantId, $prefix]);
        DB::run('UPDATE counters SET next_no = next_no + 1 WHERE tenant_id = ? AND prefix = ?', [$tenantId, $prefix]);
        return sprintf('%s-%04d', $prefix, $n);
    }
}
