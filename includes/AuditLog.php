<?php
/**
 * Append-only admin audit trail.
 *
 * Usage:
 *   AuditLog::record($pdo, 'seller.payout', 'merchant', $mid,
 *       "Paid seller #$mid \$$net", ['net'=>$net, 'commission'=>$commission]);
 *
 * The actor is taken from the session automatically. Failures never break the
 * caller's flow — an audit write should not abort a payout.
 */
class AuditLog
{
    public static function record(PDO $pdo, $action, $entityType = null, $entityId = null, $summary = null, array $meta = [])
    {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO audit_log (user_id, user_email, action, entity_type, entity_id, summary, meta, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $_SESSION['user_email'] ?? null,
                $action,
                $entityType,
                $entityId !== null ? (string) $entityId : null,
                $summary !== null ? mb_substr($summary, 0, 255) : null,
                $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Best-effort: log to the security channel but do not rethrow.
            if (class_exists('Security')) {
                Security::logSecurityEvent('audit_write_failed', ['action' => $action, 'error' => $e->getMessage()], 'error');
            }
        }
    }
}
