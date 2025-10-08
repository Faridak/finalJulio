<?php
require_once __DIR__ . '/../config/database.php';

echo "Adding minimal sample accounting data (v2)...\n\n";

try {
    // Add one accounts payable record
    $stmt = $pdo->prepare("INSERT INTO accounts_payable (vendor_name, invoice_number, invoice_date, due_date, amount, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Test Vendor', 'VEND-2025-101', '2025-09-20', '2025-10-20', 750.00, 'Test vendor invoice']);
    echo "✓ Added accounts payable record\n";
    
    // Add one general ledger entry
    // First, get an account ID
    $stmt = $pdo->query("SELECT id FROM chart_of_accounts LIMIT 1");
    $account = $stmt->fetch();
    
    if ($account) {
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$account['id'], '2025-09-20', 'Test transaction', 100.00, 0.00, 'manual']);
        echo "✓ Added general ledger entry\n";
    }
    
    // Add one financial report
    $stmt = $pdo->prepare("INSERT INTO financial_reports (report_name, report_type, period_start, period_end, report_data, generated_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        'Test Report',
        'income_statement',
        '2025-09-01',
        '2025-09-30',
        json_encode(['test' => 'data']),
        1
    ]);
    echo "✓ Added financial report\n";
    
    // Add one marketing expense
    $stmt = $pdo->prepare("INSERT INTO marketing_expenses (expense_date, expense_type, description, amount) VALUES (?, ?, ?, ?)");
    $stmt->execute(['2025-09-20', 'ads', 'Test marketing expense', 500.00]);
    echo "✓ Added marketing expense\n";
    
    echo "\nMinimal sample data added successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>