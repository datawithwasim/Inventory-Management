<?php
declare(strict_types=1);

namespace Core;

final class Migrator
{
    /** Applies pending SQL files in database/migrations. @return string[] names applied */
    public static function run(): array
    {
        DB::run('CREATE TABLE IF NOT EXISTS migrations (
            name VARCHAR(190) PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $done = array_column(DB::all('SELECT name FROM migrations'), 'name');
        $files = glob(ROOT . '/database/migrations/*.sql') ?: [];
        sort($files);

        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) continue;
            // MySQL DDL auto-commits, so no transaction; run statement by statement.
            $sql = str_replace("\r\n", "\n", (string)file_get_contents($file));
            foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
                DB::pdo()->exec($stmt);
            }
            DB::insert('migrations', ['name' => $name]);
            $applied[] = $name;
        }
        return $applied;
    }
}
