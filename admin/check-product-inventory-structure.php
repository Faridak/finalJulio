<?php
require_once 'config/database.php';

echo "Checking product_inventory table structure...\n\n";

try {
    // First check if the table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'product_inventory'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "Product inventory table does not exist.\n";
        exit;
    }
    
    $stmt = $pdo->query("DESCRIBE product_inventory");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Product inventory table columns:\n";
    echo "=====================\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>