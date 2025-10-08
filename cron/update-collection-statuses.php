<?php
/**
 * Cron job to update collection statuses for overdue accounts
 * This script should be run daily to update the collection status of accounts receivable
 */

require_once '../config/database.php';
require_once '../includes/CreditCheck.php';

try {
    $creditCheck = new CreditCheck($pdo);
    
    // Get all accounts receivable that are overdue and not yet in collections
    $stmt = $pdo->prepare("
        SELECT id, customer_name, invoice_number, amount, received_amount, due_date
        FROM accounts_receivable 
        WHERE due_date < CURDATE() 
        AND status != 'paid' 
        AND collection_status IN ('not_due', 'current')
    ");
    $stmt->execute();
    $overdueAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updatedCount = 0;
    
    foreach ($overdueAccounts as $account) {
        $result = $creditCheck->updateCollectionStatusForOverdue($account['id']);
        
        if ($result['success']) {
            $updatedCount++;
            echo "Updated collection status for invoice " . $account['invoice_number'] . 
                 " (" . $result['days_overdue'] . " days overdue, status: " . $result['collection_status'] . ")\n";
        } else {
            echo "Failed to update collection status for invoice " . $account['invoice_number'] . 
                 ": " . $result['message'] . "\n";
        }
    }
    
    echo "Collection status update completed. Updated $updatedCount accounts.\n";
    
} catch (Exception $e) {
    error_log("Collection status update cron job failed: " . $e->getMessage());
    echo "Collection status update cron job failed: " . $e->getMessage() . "\n";
}
?>