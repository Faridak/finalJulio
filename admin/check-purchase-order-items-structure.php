<?php
require_once 'config/database.php';

echo "Checking purchase_order_items table structure...\n\n";

try {
    // First check if the table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'purchase_order_items'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "Purchase order items table does not exist.\n";
        exit;
    }
    
    $stmt = $pdo->query("DESCRIBE purchase_order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Purchase order items table columns:\n";
    echo "=====================\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>