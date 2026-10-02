<?php
declare(strict_types=1);

namespace App\Models;

use Core\DB;
use Core\Model;

final class Role extends Model
{
    protected static string $table = 'roles';

    public static function permissions(int $roleId): array
    {
        return array_column(DB::all('SELECT permission FROM role_permissions WHERE role_id = ?', [$roleId]), 'permission');
    }

    public static function savePermissions(int $roleId, array $perms): void
    {
        $valid = array_intersect($perms, Tenants::allPermissions());
        DB::transaction(function () use ($roleId, $valid) {
            DB::run('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
            foreach (array_unique($valid) as $p) DB::insert('role_permissions', ['role_id' => $roleId, 'permission' => $p]);
        });
    }

    public static function withCounts(): array
    {
        return DB::all(
            'SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count
             FROM roles r WHERE r.tenant_id = ? ORDER BY r.is_owner DESC, r.name', [static::tenantId()]);
    }
}
