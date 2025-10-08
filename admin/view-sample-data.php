<?php
require_once __DIR__ . '/../config/database.php';

echo "Viewing sample accounting data...\n\n";

try {
    // View accounts receivable
    echo "=== Accounts Receivable ===\n";
    $stmt = $pdo->query("SELECT * FROM accounts_receivable");
    $receivables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($receivables as $receivable) {
        echo "ID: " . $receivable['id'] . "\n";
        echo "Customer: " . $receivable['customer_name'] . "\n";
        echo "Invoice: " . $receivable['invoice_number'] . "\n";
        echo "Amount: $" . $receivable['amount'] . "\n";
        echo "Status: " . $receivable['status'] . "\n";
        echo "------------------------\n";
    }
    
    // View sales commissions
    echo "\n=== Sales Commissions ===\n";
    $stmt = $pdo->query("SELECT * FROM sales_commissions");
    $commissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($commissions as $commission) {
        echo "ID: " . $commission['id'] . "\n";
        echo "Salesperson ID: " . $commission['salesperson_id'] . "\n";
        echo "Period: " . $commission['period_start'] . " to " . $commission['period_end'] . "\n";
        echo "Total Sales: $" . $commission['total_sales'] . "\n";
        echo "Commission: $" . $commission['commission_amount'] . "\n";
        echo "Status: " . $commission['status'] . "\n";
        echo "------------------------\n";
    }
    
    // View marketing expenses
    echo "\n=== Marketing Expenses ===\n";
    $stmt = $pdo->query("SELECT * FROM marketing_expenses");
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($expenses as $expense) {
        echo "ID: " . $expense['id'] . "\n";
        echo "Expense Date: " . $expense['expense_date'] . "\n";
        echo "Expense Type: " . $expense['expense_type'] . "\n";
        echo "Description: " . $expense['description'] . "\n";
        echo "Amount: $" . $expense['amount'] . "\n";
        echo "------------------------\n";
    }
    
    echo "\nData viewing completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>