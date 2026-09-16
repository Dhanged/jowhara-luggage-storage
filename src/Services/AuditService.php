<?php
namespace App\Services;

class AuditService
{
    public function log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
    {
        $userId = $_SESSION["user_id"] ?? null;
        $ip = $_SERVER["REMOTE_ADDR"] ?? null;
        \db_execute(
            "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
            "ississ",
            [$userId, $action, $entityType, $entityId, $details, $ip]
        );
    }
}
