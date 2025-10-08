<?php
/**
 * Escrow Auto-Release Cron Job
 * Handles automatic escrow releases and maintenance tasks
 * Run this script every hour: 0 * * * * php /path/to/escrow-cron.php
 */

require_once 'config/database.php';
require_once 'includes/EscrowSystem.php';
require_once 'includes/NotificationSystem.php';

// Log function
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] [{$level}] {$message}\n";
}

try {
    logMessage("Starting escrow maintenance tasks...");
    
    $escrowSystem = new EscrowSystem($pdo);
    $notificationSystem = new NotificationSystem($pdo);
    
    // 1. Process auto-releases for expired escrows
    logMessage("Processing auto-releases...");
    $autoReleaseResult = $escrowSystem->processAutoReleases();
    
    if ($autoReleaseResult['success']) {
        logMessage("Auto-release processed: {$autoReleaseResult['released']} escrows released out of {$autoReleaseResult['processed']} eligible");
    } else {
        logMessage("Auto-release failed: " . $autoReleaseResult['error'], 'ERROR');
    }
    
    // 2. Send release reminders (24 hours before auto-release)
    logMessage("Sending release reminders...");
    $stmt = $pdo->prepare("
        SELECT et.*, o.id as order_number, u.email as buyer_email,
               CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as buyer_name
        FROM escrow_transactions et
        JOIN orders o ON et.order_id = o.id
        JOIN users u ON et.buyer_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE et.status IN ('active', 'shipped')
        AND et.release_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        AND et.reminder_sent = FALSE
    ");
    $stmt->execute();
    $pendingReleases = $stmt->fetchAll();
    
    $remindersSent = 0;
    foreach ($pendingReleases as $escrow) {
        try {
            // Send reminder notification
            $notificationSystem->notifyEscrowReleaseReminder(
                $escrow['buyer_id'],
                $escrow['order_number'],
                $escrow['release_date']
            );
            
            // Mark reminder as sent
            $stmt = $pdo->prepare("
                UPDATE escrow_transactions SET reminder_sent = TRUE WHERE id = ?
            ");
            $stmt->execute([$escrow['id']]);
            
            $remindersSent++;
            
        } catch (Exception $e) {
            logMessage("Failed to send reminder for escrow {$escrow['id']}: " . $e->getMessage(), 'ERROR');
        }
    }
    
    logMessage("Release reminders sent: {$remindersSent}");
    
    // 3. Escalate old disputes
    logMessage("Escalating old disputes...");
    $stmt = $pdo->prepare("
        UPDATE escrow_disputes 
        SET status = 'escalated', priority = 'high'
        WHERE status = 'open' 
        AND created_at < DATE_SUB(NOW(), INTERVAL 72 HOUR)
        AND priority != 'urgent'
    ");
    $stmt->execute();
    $escalatedDisputes = $stmt->rowCount();
    
    logMessage("Escalated disputes: {$escalatedDisputes}");
    
    // 4. Generate daily escrow statistics
    logMessage("Updating escrow statistics...");
    $stmt = $pdo->prepare("
        INSERT INTO escrow_daily_stats (
            date, total_escrows, active_escrows, released_escrows, 
            disputed_escrows, total_amount, auto_releases
        ) VALUES (
            CURDATE(),
            (SELECT COUNT(*) FROM escrow_transactions WHERE DATE(created_at) = CURDATE()),
            (SELECT COUNT(*) FROM escrow_transactions WHERE status IN ('active', 'shipped')),
            (SELECT COUNT(*) FROM escrow_transactions WHERE DATE(released_at) = CURDATE()),
            (SELECT COUNT(*) FROM escrow_transactions WHERE status = 'disputed'),
            (SELECT COALESCE(SUM(amount), 0) FROM escrow_transactions WHERE status IN ('active', 'shipped', 'disputed')),
            ?
        ) ON DUPLICATE KEY UPDATE
            active_escrows = VALUES(active_escrows),
            released_escrows = VALUES(released_escrows),
            disputed_escrows = VALUES(disputed_escrows),
            total_amount = VALUES(total_amount),
            auto_releases = auto_releases + VALUES(auto_releases)
    ");
    $stmt->execute([$autoReleaseResult['released'] ?? 0]);
    
    // 5. Clean up old logs (keep last 90 days)
    logMessage("Cleaning up old logs...");
    $stmt = $pdo->prepare("
        DELETE FROM escrow_logs 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute();
    $deletedLogs = $stmt->rowCount();
    
    logMessage("Deleted old logs: {$deletedLogs}");
    
    // 6. Update merchant reputation scores based on escrow performance
    logMessage("Updating merchant reputation scores...");
    $stmt = $pdo->prepare("
        UPDATE user_profiles up
        JOIN (
            SELECT 
                et.seller_id,
                COUNT(*) as total_escrows,
                COUNT(CASE WHEN et.status = 'released' AND et.release_reason = 'buyer_confirmed' THEN 1 END) as buyer_confirmed,
                COUNT(CASE WHEN et.status = 'released' AND et.release_reason = 'auto_released' THEN 1 END) as auto_released,
                COUNT(CASE WHEN et.status = 'disputed' THEN 1 END) as disputed,
                AVG(CASE WHEN et.shipped_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, et.created_at, et.shipped_at) END) as avg_shipping_hours
            FROM escrow_transactions et
            WHERE et.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY et.seller_id
        ) escrow_stats ON up.user_id = escrow_stats.seller_id
        SET up.escrow_performance_score = GREATEST(0, LEAST(100,
            (escrow_stats.buyer_confirmed * 100 / escrow_stats.total_escrows) +
            (escrow_stats.auto_released * 50 / escrow_stats.total_escrows) -
            (escrow_stats.disputed * 25 / escrow_stats.total_escrows) +
            (CASE WHEN escrow_stats.avg_shipping_hours <= 24 THEN 10 
                  WHEN escrow_stats.avg_shipping_hours <= 48 THEN 5 
                  ELSE 0 END)
        ))
    ");
    $stmt->execute();
    $updatedMerchants = $stmt->rowCount();
    
    logMessage("Updated merchant scores: {$updatedMerchants}");
    
    logMessage("Escrow maintenance tasks completed successfully");
    
} catch (Exception $e) {
    logMessage("Fatal error in escrow maintenance: " . $e->getMessage(), 'FATAL');
    
    // Send alert to admin
    try {
        $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $adminStmt->execute();
        $admin = $adminStmt->fetch();
        
        if ($admin) {
            $notificationSystem->createNotification(
                $admin['id'],
                'system_alert',
                'Escrow Maintenance Error',
                "Escrow cron job failed: " . $e->getMessage(),
                ['error' => $e->getMessage(), 'timestamp' => date('Y-m-d H:i:s')]
            );
        }
    } catch (Exception $notifyError) {
        logMessage("Failed to notify admin: " . $notifyError->getMessage(), 'ERROR');
    }
    
    exit(1);
}

// Create the daily stats table if it doesn't exist
function createDailyStatsTable($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS escrow_daily_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL UNIQUE,
            total_escrows INT DEFAULT 0,
            active_escrows INT DEFAULT 0,
            released_escrows INT DEFAULT 0,
            disputed_escrows INT DEFAULT 0,
            total_amount DECIMAL(12,2) DEFAULT 0,
            auto_releases INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_escrow_daily_stats_date (date)
        )
    ";
    $pdo->exec($sql);
}

// Add escrow performance score column to user_profiles if it doesn't exist
function addEscrowPerformanceColumn($pdo) {
    try {
        $pdo->exec("
            ALTER TABLE user_profiles 
            ADD COLUMN IF NOT EXISTS escrow_performance_score DECIMAL(5,2) DEFAULT 0 AFTER reputation_score
        ");
        
        $pdo->exec("
            ALTER TABLE escrow_transactions 
            ADD COLUMN IF NOT EXISTS reminder_sent BOOLEAN DEFAULT FALSE AFTER auto_release_processed
        ");
    } catch (Exception $e) {
        // Columns might already exist
    }
}

// Initialize required tables and columns
createDailyStatsTable($pdo);
addEscrowPerformanceColumn($pdo);
?>