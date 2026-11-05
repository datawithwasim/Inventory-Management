<?php
declare(strict_types=1);

namespace Core;

/** Base for simple tables: list, find, count, create, update and delete by id. */
abstract class Model
{
    protected static string $table;

    public static function all(string $order = 'id DESC'): array
    {
        return DB::all('SELECT * FROM `' . static::$table . '` ORDER BY ' . $order);
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM `' . static::$table . '` WHERE id = ?', [$id]);
    }

    public static function count(): int
    {
        return (int)DB::val('SELECT COUNT(*) FROM `' . static::$table . '`');
    }

    public static function create(array $data): int
    {
        return DB::insert(static::$table, $data);
    }

    public static function update(int $id, array $data): void
    {
        $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        DB::run('UPDATE `' . static::$table . "` SET $set WHERE id = ?", [...array_values($data), $id]);
    }

    public static function delete(int $id): void
    {
        DB::run('DELETE FROM `' . static::$table . '` WHERE id = ?', [$id]);
    }
}
