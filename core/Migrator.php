<?php
declare(strict_types=1);

namespace Core;

final class Migrator
{
    private static function ensureTable(): void
    {
        DB::run('CREATE TABLE IF NOT EXISTS migrations (
            name VARCHAR(190) PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /** @return string[] migration file names not applied yet */
    public static function pending(): array
    {
        self::ensureTable();
        $done = array_column(DB::all('SELECT name FROM migrations'), 'name');
        $files = array_merge(glob(ROOT . '/database/migrations/*.sql') ?: [], glob(ROOT . '/database/migrations/*.php') ?: []);
        usort($files, fn($a, $b) => strcmp(basename($a), basename($b)));
        return array_values(array_filter(array_map('basename', $files), fn($n) => !in_array($n, $done, true)));
    }

    /** Applies pending migrations in database/migrations: SQL files, or PHP files returning a function that gets the PDO connection. @return string[] names applied */
    public static function run(): array
    {
        $applied = [];
        foreach (self::pending() as $name) {
            $file = ROOT . '/database/migrations/' . $name;
            if (str_ends_with($name, '.php')) {
                $fn = require $file;
                $fn(DB::pdo());
                DB::insert('migrations', ['name' => $name]);
                $applied[] = $name;
                continue;
            }
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
