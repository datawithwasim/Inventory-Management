<?php
declare(strict_types=1);

namespace Core;

final class Audit
{
    /**
     * Actor/tenant default to whoever is logged in; pass them explicitly when
     * the session is not set yet (e.g. during login).
     */
    public static function log(
        string $action, ?string $entity = null, ?int $entityId = null, ?string $details = null,
        ?int $tenantId = null, ?string $actorType = null, ?int $actorId = null
    ): void {
        if ($actorType === null) {
            if (!empty($_SESSION['impersonator_admin_id'])) {
                $actorType = 'admin';
                $actorId = (int)$_SESSION['impersonator_admin_id'];
            } elseif (!empty($_SESSION['user_id'])) {
                $actorType = 'user';
                $actorId = (int)$_SESSION['user_id'];
            } elseif (!empty($_SESSION['admin_id'])) {
                $actorType = 'admin';
                $actorId = (int)$_SESSION['admin_id'];
            } else {
                $actorType = 'system';
            }
        }
        $tenantId ??= Auth::tenantId();
        DB::insert('audit_logs', [
            'tenant_id'  => $tenantId,
            'actor_type' => $actorType,
            'actor_id'   => $actorId,
            'action'     => $action,
            'entity'     => $entity,
            'entity_id'  => $entityId,
            'details'    => $details,
            'ip'         => client_ip(),
        ]);
    }
}
