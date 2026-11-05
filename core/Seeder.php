<?php
declare(strict_types=1);

namespace Core;

/** Sets up the company the first time: roles, the owner, and the starter masters. Does nothing once the company has its roles. */
final class Seeder
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
        return array_values(array_merge($perms, ['sales.create']));
    }

    /** Warehouse Incharge / Operator: the people who work inside one warehouse. */
    private static function warehousePermissions(bool $incharge): array
    {
        $op = ['items.view', 'warehouses.view', 'stock.view', 'stock.transfer', 'stock.receive', 'labels.view'];
        return $incharge ? array_merge($op, ['masters.view', 'warehouses.create', 'warehouses.edit', 'stock.adjust', 'purchase.view', 'purchase.create', 'sales.view', 'reports.view', 'labels.create', 'labels.edit']) : $op;
    }

    /** @return string[] what was created */
    public static function run(string $company, string $ownerName, string $ownerEmail, string $ownerPassword): array
    {
        if (DB::val('SELECT 1 FROM roles LIMIT 1')) return [];
        return DB::transaction(function () use ($company, $ownerName, $ownerEmail, $ownerPassword) {
            $roleIds = [];
            $defs = [
                'Owner' => ['owner' => 1, 'perms' => []],
                'Admin' => ['owner' => 0, 'perms' => self::allPermissions()],
                'Staff' => ['owner' => 0, 'perms' => self::staffPermissions()],
                'Warehouse Incharge' => ['owner' => 0, 'perms' => self::warehousePermissions(true), 'bound' => 1],
                'Warehouse Operator' => ['owner' => 0, 'perms' => self::warehousePermissions(false), 'bound' => 1],
            ];
            foreach ($defs as $name => $def) {
                $rid = DB::insert('roles', ['name' => $name, 'is_owner' => $def['owner'], 'is_system' => 1, 'warehouse_bound' => $def['bound'] ?? 0]);
                $roleIds[$name] = $rid;
                foreach ($def['perms'] as $p) DB::insert('role_permissions', ['role_id' => $rid, 'permission' => $p]);
            }
            DB::insert('users', ['role_id' => $roleIds['Owner'], 'name' => $ownerName, 'email' => strtolower(trim($ownerEmail)), 'password_hash' => password_hash($ownerPassword, PASSWORD_DEFAULT)]);
            foreach ([['Piece', 'pcs', 0], ['Meter', 'm', 1], ['Yard', 'yd', 1], ['Set', 'set', 0], ['Box', 'box', 0], ['Kg', 'kg', 1]] as [$n, $s, $d]) {
                DB::insert('units', ['name' => $n, 'short_name' => $s, 'allow_decimal' => $d]);
            }
            DB::insert('warehouses', ['name' => 'Main Warehouse', 'code' => 'MAIN', 'is_default' => 1]);
            DB::insert('customers', ['name' => 'Walk-in customer', 'is_walkin' => 1]);
            Settings::set('company.name', $company);
            return ["Company: $company", "Owner: $ownerEmail"];
        });
    }
}
