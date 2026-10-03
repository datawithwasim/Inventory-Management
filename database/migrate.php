<?php
declare(strict_types=1);

// Usage: php database/migrate.php
require dirname(__DIR__) . '/core/bootstrap.php';

$applied = Core\Migrator::run();
foreach ($applied as $n) echo "Applied $n\n";
echo $applied ? '' : "Nothing to migrate.\n";
