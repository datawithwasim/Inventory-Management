<?php
declare(strict_types=1);

namespace Core;

/** Streams a full SQL dump (structure + data) using plain PDO — works on shared hosting without mysqldump. */
final class Backup
{
    public static function stream(callable $write): int
    {
        $pdo = DB::pdo();
        $write("-- Inventory backup " . gmdate('Y-m-d H:i:s') . " UTC\n-- Restore: cPanel > phpMyAdmin > Import (into an empty database)\n\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(\PDO::FETCH_NUM);
        $rowsTotal = 0;
        foreach ($tables as [$table]) {
            $q = '`' . str_replace('`', '``', $table) . '`';
            $create = $pdo->query("SHOW CREATE TABLE $q")->fetch(\PDO::FETCH_NUM)[1];
            $write("DROP TABLE IF EXISTS $q;\n$create;\n\n");
            $stmt = $pdo->query("SELECT * FROM $q");
            $buf = [];
            $cols = null;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $cols ??= '(' . implode(',', array_map(fn($c) => '`' . $c . '`', array_keys($row))) . ')';
                $buf[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row)) . ')';
                $rowsTotal++;
                if (count($buf) >= 200) {
                    $write("INSERT INTO $q $cols VALUES\n" . implode(",\n", $buf) . ";\n");
                    $buf = [];
                }
            }
            if ($buf) $write("INSERT INTO $q $cols VALUES\n" . implode(",\n", $buf) . ";\n");
            $write("\n");
        }
        $write("SET FOREIGN_KEY_CHECKS=1;\n");
        return $rowsTotal;
    }
}
