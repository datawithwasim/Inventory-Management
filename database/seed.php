<?php
declare(strict_types=1);

// Usage: php database/seed.php   (idempotent: safe to run again)
require dirname(__DIR__) . '/core/bootstrap.php';

use Core\DB;

$plans = [
    ['Free',  'free',  3,  100,  1, 0,    14],
    ['Basic', 'basic', 10, 2000, 3, 999,  0],
    ['Pro',   'pro',   0,  0,    0, 2999, 0],
];
foreach ($plans as [$name, $slug, $u, $i, $w, $price, $trial]) {
    if (!DB::val('SELECT 1 FROM plans WHERE slug = ?', [$slug])) {
        DB::insert('plans', ['name' => $name, 'slug' => $slug, 'max_users' => $u, 'max_items' => $i,
            'max_warehouses' => $w, 'price' => $price, 'trial_days' => $trial]);
        echo "Plan created: $name\n";
    }
}

$sa = config('superadmin');
if (!DB::val('SELECT 1 FROM super_admins WHERE email = ?', [$sa['email']])) {
    DB::insert('super_admins', ['name' => $sa['name'], 'email' => $sa['email'],
        'password_hash' => password_hash($sa['password'], PASSWORD_DEFAULT)]);
    echo "Super admin created: {$sa['email']} (change the password after first login)\n";
}
