<?php
declare(strict_types=1);

namespace Core;

/**
 * Warehouse-wise users. A user tied to a warehouse (operator, incharge) sees and works on that warehouse only;
 * a user with no warehouse (head office, and always the Owner) sees and works on all of them.
 */
final class Scope
{
    /** The warehouse the signed-in user is tied to, or null when not restricted. */
    public static function warehouse(): ?int
    {
        $u = Auth::user();
        if (!$u || $u['is_owner'] || empty($u['warehouse_id'])) return null;
        return (int)$u['warehouse_id'];
    }

    public static function restricted(): bool
    {
        return self::warehouse() !== null;
    }

    /** May the user work on this warehouse? */
    public static function allows(int $warehouseId): bool
    {
        $w = self::warehouse();
        return $w === null || $w === $warehouseId;
    }

    public static function name(): ?string
    {
        $w = self::warehouse();
        return $w === null ? null : (string)DB::val('SELECT name FROM warehouses WHERE id = ?', [$w]);
    }

    /** " AND <column> = <own warehouse>" for restricted users, nothing for everyone else. (Integer only, safe to inline.) */
    public static function sql(string $column): string
    {
        $w = self::warehouse();
        return $w === null ? '' : " AND $column = $w";
    }

    /** " AND (<a> = own OR <b> = own)" — documents that involve the user's warehouse on either side. */
    public static function sqlEither(string $a, string $b): string
    {
        $w = self::warehouse();
        return $w === null ? '' : " AND ($a = $w OR $b = $w)";
    }
}
