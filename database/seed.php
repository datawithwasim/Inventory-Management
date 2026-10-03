<?php
declare(strict_types=1);

// Usage: php database/seed.php   (idempotent: safe to run again)
require dirname(__DIR__) . '/core/bootstrap.php';

$sa = config('superadmin');
foreach (Core\Seeder::run($sa['name'], $sa['email'], $sa['password']) as $m) echo "Created $m\n";
echo "Seed complete. Change the Super Admin password after first login.\n";

// CLI installs count as installed (disables the web installer).
file_put_contents(ROOT . "/storage/installed.lock", date("c"));
