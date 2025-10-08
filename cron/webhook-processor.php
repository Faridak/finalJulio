<?php
/**
 * Webhook Processor Cron Job
 * Processes pending webhook events and retries failed ones
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/WebhookManager.php';
require_once __DIR__ . '/../includes/WebhookMonitoring.php';

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

echo "Starting webhook processor...\n";

try {
    $webhookManager = new WebhookManager($pdo);
    $webhookMonitoring = new WebhookMonitoring($pdo);
    
    // Process pending events
    echo "Processing pending webhook events...\n";
    $result = $webhookManager->processPendingEvents();
    
    if ($result['success']) {
        echo "Processed {$result['processed']} webhook events.\n";
        
        if (!empty($result['results'])) {
            $successCount = 0;
            $failureCount = 0;
            
            foreach ($result['results'] as $eventId => $eventResult) {
                if ($eventResult['success']) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            }
            
            echo "Successful: {$successCount}, Failed: {$failureCount}\n";
        }
    } else {
        echo "Failed to process webhook events: {$result['error']}\n";
    }
    
    // Check for issues
    echo "Checking for webhook issues...\n";
    $monitorResult = $webhookMonitoring->checkForIssues();
    
    if ($monitorResult['success']) {
        echo "Checked for issues. Found {$monitorResult['issues_found']} issues.\n";
    } else {
        echo "Failed to check for issues: {$monitorResult['error']}\n";
    }
    
    // Clean up old events (older than 30 days)
    echo "Cleaning up old webhook events...\n";
    $cleanupResult = $webhookMonitoring->cleanupOldEvents(30);
    
    if ($cleanupResult['success']) {
        echo "Cleaned up {$cleanupResult['deleted_rows']} old webhook events.\n";
    } else {
        echo "Failed to clean up old events: {$cleanupResult['error']}\n";
    }
    
    echo "Webhook processor completed successfully.\n";
    
} catch (Exception $e) {
    echo "Webhook processor failed: " . $e->getMessage() . "\n";
    error_log("Webhook processor error: " . $e->getMessage());
}
?>