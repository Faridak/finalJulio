<?php
require_once __DIR__ . '/../config/database.php';

echo "Checking table structures...\n\n";

try {
    // Check sales_commissions table structure
    echo "=== Sales Commissions Table Structure ===\n";
    $stmt = $pdo->query("DESCRIBE sales_commissions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n=== Marketing Expenses Table Structure ===\n";
    $stmt = $pdo->query("DESCRIBE marketing_expenses");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\nTable structure check completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>