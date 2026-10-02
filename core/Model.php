<?php
declare(strict_types=1);

namespace Core;

/**
 * Base for tenant-owned tables. Every query is forced to the current tenant,
 * so one company can never read or write another company's rows.
 */
abstract class Model
{
    protected static string $table;

    protected static function tenantId(): int
    {
        $id = Auth::tenantId();
        if (!$id) throw new \RuntimeException('No tenant in context');
        return $id;
    }

    public static function all(string $order = 'id DESC'): array
    {
        return DB::all('SELECT * FROM `' . static::$table . '` WHERE tenant_id = ? ORDER BY ' . $order, [static::tenantId()]);
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM `' . static::$table . '` WHERE tenant_id = ? AND id = ?', [static::tenantId(), $id]);
    }

    public static function count(): int
    {
        return (int)DB::val('SELECT COUNT(*) FROM `' . static::$table . '` WHERE tenant_id = ?', [static::tenantId()]);
    }

    public static function create(array $data): int
    {
        $data['tenant_id'] = static::tenantId();
        return DB::insert(static::$table, $data);
    }

    public static function update(int $id, array $data): void
    {
        $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        DB::run('UPDATE `' . static::$table . "` SET $set WHERE tenant_id = ? AND id = ?",
            [...array_values($data), static::tenantId(), $id]);
    }

    public static function delete(int $id): void
    {
        DB::run('DELETE FROM `' . static::$table . '` WHERE tenant_id = ? AND id = ?', [static::tenantId(), $id]);
    }
}
