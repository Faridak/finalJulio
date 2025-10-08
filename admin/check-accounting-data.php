<?php
require_once __DIR__ . '/../config/database.php';

echo "Checking accounting data...\n\n";

try {
    // Check accounts receivable
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM accounts_receivable");
    $result = $stmt->fetch();
    echo "Accounts Receivable: " . $result['count'] . "\n";
    
    // Check accounts payable
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM accounts_payable");
    $result = $stmt->fetch();
    echo "Accounts Payable: " . $result['count'] . "\n";
    
    // Check general ledger
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM general_ledger");
    $result = $stmt->fetch();
    echo "General Ledger Entries: " . $result['count'] . "\n";
    
    // Check financial reports
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM financial_reports");
    $result = $stmt->fetch();
    echo "Financial Reports: " . $result['count'] . "\n";
    
    // Check sales commissions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sales_commissions");
    $result = $stmt->fetch();
    echo "Sales Commissions: " . $result['count'] . "\n";
    
    // Check marketing expenses
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM marketing_expenses");
    $result = $stmt->fetch();
    echo "Marketing Expenses: " . $result['count'] . "\n";
    
    echo "\nData check completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>