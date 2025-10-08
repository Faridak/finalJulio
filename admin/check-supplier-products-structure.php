<?php
require_once 'config/database.php';

echo "Checking supplier_products table structure...\n\n";

try {
    // First check if the table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'supplier_products'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "Supplier products table does not exist.\n";
        exit;
    }
    
    $stmt = $pdo->query("DESCRIBE supplier_products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Supplier products table columns:\n";
    echo "=====================\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>