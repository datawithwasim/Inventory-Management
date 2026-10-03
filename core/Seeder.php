<?php
declare(strict_types=1);

namespace Core;

final class Seeder
{
    /** Idempotent. @return string[] what was created */
    public static function run(string $adminName, string $adminEmail, string $adminPassword): array
    {
        $made = [];
        $plans = [
            ['Free',  'free',  3,  100,  1, 0,    14],
            ['Basic', 'basic', 10, 2000, 3, 999,  0],
            ['Pro',   'pro',   0,  0,    0, 2999, 0],
        ];
        foreach ($plans as [$name, $slug, $u, $i, $w, $price, $trial]) {
            if (!DB::val('SELECT 1 FROM plans WHERE slug = ?', [$slug])) {
                DB::insert('plans', ['name' => $name, 'slug' => $slug, 'max_users' => $u, 'max_items' => $i,
                    'max_warehouses' => $w, 'price' => $price, 'trial_days' => $trial]);
                $made[] = "Plan: $name";
            }
        }
        if (!DB::val('SELECT 1 FROM super_admins WHERE email = ?', [$adminEmail])) {
            DB::insert('super_admins', ['name' => $adminName, 'email' => $adminEmail,
                'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT)]);
            $made[] = "Super admin: $adminEmail";
        }
        return $made;
    }
}
