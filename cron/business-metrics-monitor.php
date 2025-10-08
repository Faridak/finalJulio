<?php
/**
 * Business Metrics Monitor Cron Job
 * Checks business metrics and sends alerts when thresholds are breached
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/BusinessMetricsMonitor.php';

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

echo "Starting business metrics monitor...\n";

try {
    $metricsMonitor = new BusinessMetricsMonitor($pdo);
    
    // Check metrics and send alerts
    echo "Checking business metrics...\n";
    $result = $metricsMonitor->checkMetricsAndAlert();
    
    if ($result['success']) {
        echo "Checked metrics. Sent {$result['alerts_sent']} alerts.\n";
        
        if (!empty($result['alerts'])) {
            foreach ($result['alerts'] as $alert) {
                // Handle case where checkCommissionThresholds returns an array of alerts
                if (isset($alert[0]) && is_array($alert[0])) {
                    foreach ($alert as $subAlert) {
                        echo "Alert: {$subAlert['type']} - {$subAlert['message']}\n";
                    }
                } else {
                    echo "Alert: {$alert['type']} - {$alert['message']}\n";
                }
            }
        }
    } else {
        echo "Failed to check metrics: {$result['error']}\n";
    }
    
    echo "Business metrics monitor completed successfully.\n";
    
} catch (Exception $e) {
    echo "Business metrics monitor failed: " . $e->getMessage() . "\n";
    error_log("Business metrics monitor error: " . $e->getMessage());
}
?>