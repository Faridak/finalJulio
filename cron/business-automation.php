<?php
/**
 * Business Automation Cron Job
 * Runs automated business processes including commission tier progression,
 * inventory alerts, financial period closing, and marketing ROI calculations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/BusinessAutomation.php';

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

echo "Starting business automation processes...\n";

try {
    $businessAutomation = new BusinessAutomation($pdo);
    
    // Start timing
    $startTime = microtime(true);
    
    // Log the start of automation processes
    echo "Running all automation processes...\n";
    
    // Run all automations
    $results = $businessAutomation->runAllAutomations();
    
    // Calculate execution time
    $executionTime = microtime(true) - $startTime;
    
    // Log results
    echo "Automation processes completed in " . round($executionTime, 4) . " seconds.\n";
    
    // Output results
    foreach ($results as $process => $result) {
        if ($result['success']) {
            echo "✓ {$process}: Success\n";
            
            // Output specific details based on process
            switch ($process) {
                case 'commission_tiers':
                    echo "  - {$result['progressed_count']} salespeople progressed\n";
                    if (!empty($result['alerts'])) {
                        foreach ($result['alerts'] as $alert) {
                            echo "  - {$alert['salesperson']} progressed to {$alert['new_tier']}\n";
                        }
                    }
                    break;
                    
                case 'inventory_alerts':
                    echo "  - {$result['alert_count']} alerts sent\n";
                    if (!empty($result['alerts'])) {
                        foreach ($result['alerts'] as $alert) {
                            echo "  - Low stock alert: {$alert['product']} (SKU: {$alert['sku']})\n";
                        }
                    }
                    break;
                    
                case 'marketing_roi':
                    echo "  - {$result['updated_count']} campaigns updated\n";
                    break;
                    
                case 'financial_closing':
                    if (isset($result['message'])) {
                        echo "  - {$result['message']}\n";
                    } else {
                        echo "  - Period {$result['period']} closed with net income: $" . number_format($result['net_income'], 2) . "\n";
                    }
                    break;
            }
        } else {
            echo "✗ {$process}: Failed - {$result['error']}\n";
        }
    }
    
    // Clean up old records weekly
    if (date('w') == 0) { // Sunday
        echo "Cleaning up old records...\n";
        $cleanupResult = $businessAutomation->cleanupOldRecords(90);
        
        if ($cleanupResult['success']) {
            echo "✓ Cleanup completed: {$cleanupResult['deleted_notifications']} notifications and {$cleanupResult['deleted_logs']} logs deleted\n";
        } else {
            echo "✗ Cleanup failed: {$cleanupResult['error']}\n";
        }
    }
    
    // Get automation stats
    $stats = $businessAutomation->getAutomationStats();
    
    if ($stats) {
        echo "\n=== AUTOMATION STATISTICS ===\n";
        echo "Total salespeople: " . array_sum(array_column($stats['commission_tiers'], 'salesperson_count')) . "\n";
        echo "Low stock items: " . ($stats['inventory']['low_stock_items'] ?? 0) . "\n";
        echo "Closed financial periods: " . ($stats['financial']['closed_periods'] ?? 0) . "\n";
        echo "Average marketing ROI: " . round($stats['marketing']['avg_roi'] ?? 0, 2) . "%\n";
    }
    
    echo "Business automation completed successfully.\n";
    
} catch (Exception $e) {
    echo "Business automation failed: " . $e->getMessage() . "\n";
    error_log("Business automation error: " . $e->getMessage());
}
?>