<?php
declare(strict_types=1);

namespace App\Models;

use Core\DB;

/** Platform-level company management (used by the Super Admin panel). */
final class Tenants
{
    public static function allPermissions(): array
    {
        $out = [];
        foreach (config('permissions') as $module => $def) {
            foreach ($def['actions'] as $a) $out[] = "$module.$a";
        }
        return $out;
    }

    private static function staffPermissions(): array
    {
        $perms = array_filter(self::allPermissions(), fn($p) => str_ends_with($p, '.view'));
        return array_values(array_merge($perms, ['sales.create', 'pos.use']));
    }

    public static function uniqueSlug(string $name): string
    {
        $base = slugify($name);
        $slug = $base;
        for ($i = 2; DB::val('SELECT 1 FROM tenants WHERE slug = ?', [$slug]); $i++) $slug = "$base-$i";
        return $slug;
    }

    /** Creates company + default roles + owner user. Returns the tenant id. */
    public static function provision(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $plan = DB::one('SELECT * FROM plans WHERE id = ?', [$d['plan_id']]);
            $status = $d['status'];
            $trialEnds = null;
            if ($status === 'trial') {
                $days = (int)$plan['trial_days'] ?: 14;
                $trialEnds = date('Y-m-d', strtotime("+$days days"));
            }
            $tenantId = DB::insert('tenants', [
                'name' => $d['name'], 'slug' => self::uniqueSlug($d['name']), 'plan_id' => $plan['id'],
                'status' => $status, 'trial_ends_at' => $trialEnds,
                'subscription_ends_at' => $d['subscription_ends_at'] ?: null,
            ]);

            $roleIds = [];
            $defs = [
                'Owner' => ['owner' => 1, 'perms' => []],
                'Admin' => ['owner' => 0, 'perms' => self::allPermissions()],
                'Staff' => ['owner' => 0, 'perms' => self::staffPermissions()],
            ];
            foreach ($defs as $name => $def) {
                $rid = DB::insert('roles', ['tenant_id' => $tenantId, 'name' => $name,
                    'is_owner' => $def['owner'], 'is_system' => 1]);
                $roleIds[$name] = $rid;
                foreach ($def['perms'] as $p) DB::insert('role_permissions', ['role_id' => $rid, 'permission' => $p]);
            }

            DB::insert('users', [
                'tenant_id' => $tenantId, 'role_id' => $roleIds['Owner'],
                'name' => $d['owner_name'], 'email' => $d['owner_email'],
                'password_hash' => password_hash($d['owner_password'], PASSWORD_DEFAULT),
            ]);
            return $tenantId;
        });
    }
}
