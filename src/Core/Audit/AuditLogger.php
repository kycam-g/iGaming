<?php

declare(strict_types=1);

namespace App\Core\Audit;

use App\Core\Database\Database;
use PDO;

final class AuditLogger
{
    public function record(
        string $actorType,
        ?string $actorId,
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $ip = null,
        array $metadata = [],
        ?PDO $pdo = null,
    ): void {
        $pdo ??= Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (actor_type,actor_id,action,entity_type,entity_id,ip,metadata) '
            . 'VALUES (:actor_type,:actor_id,:action,:entity_type,:entity_id,:ip,:metadata)'
        );
        $stmt->execute([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip' => $ip,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
