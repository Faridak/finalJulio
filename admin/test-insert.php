<?php
require_once __DIR__ . '/../config/database.php';

echo "Testing database insert...\n\n";

try {
    // Try to insert a simple test record
    $stmt = $pdo->prepare("INSERT INTO accounts_receivable (customer_name, invoice_number, invoice_date, due_date, amount, description) VALUES (?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute(['Test Customer', 'TEST-001', '2025-09-20', '2025-10-20', 500.00, 'Test invoice']);
    
    if ($result) {
        echo "✓ Successfully inserted test record\n";
        $id = $pdo->lastInsertId();
        echo "Inserted record ID: " . $id . "\n";
    } else {
        echo "✗ Failed to insert test record\n";
    }
    
    // Check how many records we have now
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM accounts_receivable");
    $result = $stmt->fetch();
    echo "Total accounts receivable records: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>