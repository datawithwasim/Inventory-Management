<?php
declare(strict_types=1);

namespace App\Models;

use Core\DB;
use Core\Model;

final class User extends Model
{
    protected static string $table = 'users';

    public static function withRoles(): array
    {
        return DB::all(
            'SELECT u.*, r.name AS role_name, r.is_owner FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.tenant_id = ? ORDER BY u.name', [static::tenantId()]);
    }

    public static function withRole(int $id): ?array
    {
        return DB::one(
            'SELECT u.*, r.is_owner FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.tenant_id = ? AND u.id = ?', [static::tenantId(), $id]);
    }

    public static function emailTaken(string $email, ?int $exceptId = null): bool
    {
        return (bool)DB::val('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $exceptId ?? 0]);
    }
}
