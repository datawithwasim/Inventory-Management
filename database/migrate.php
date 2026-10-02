<?php
declare(strict_types=1);

// Usage: php database/migrate.php
require dirname(__DIR__) . '/core/bootstrap.php';

use Core\DB;

DB::run('CREATE TABLE IF NOT EXISTS migrations (
    name VARCHAR(190) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$done = array_column(DB::all('SELECT name FROM migrations'), 'name');
$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) continue;
    echo "Applying $name ... ";
    // MySQL DDL auto-commits, so no transaction here; each file runs statement by statement.
    foreach (array_filter(array_map('trim', explode(";\n", file_get_contents($file)))) as $stmt) {
        DB::pdo()->exec($stmt);
    }
    DB::insert('migrations', ['name' => $name]);
    echo "done\n";
    $ran++;
}
echo $ran ? "Applied $ran migration(s).\n" : "Nothing to migrate.\n";
