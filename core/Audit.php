<?php
declare(strict_types=1);

namespace Core;

final class Audit
{
    /** The actor defaults to whoever is logged in; pass it explicitly when the session is not set yet (e.g. during login). */
    public static function log(string $action, ?string $entity = null, ?int $entityId = null, ?string $details = null, ?string $actorType = null, ?int $actorId = null): void
    {
        if ($actorType === null) {
            $actorId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $actorType = $actorId ? 'user' : 'system';
        }
        DB::insert('audit_logs', [
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
