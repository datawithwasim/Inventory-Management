<?php
declare(strict_types=1);

// Usage: php database/seed.php   (sets up the company and its owner from .env: COMPANY_NAME, OWNER_NAME, OWNER_EMAIL, OWNER_PASSWORD; does nothing once set up)
require dirname(__DIR__) . '/core/bootstrap.php';

$o = config('owner');
foreach (Core\Seeder::run($o['company'], $o['name'], $o['email'], $o['password']) as $m) echo "Created $m\n";
echo "Seed complete. Change the owner password after the first sign-in.\n";

// CLI installs count as installed (disables the web installer).
file_put_contents(ROOT . "/storage/installed.lock", date("c"));
