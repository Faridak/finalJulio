<?php
require_once 'config/database.php';

echo "Checking product_variants table structure...\n\n";

try {
    // First check if the table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'product_variants'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "Product variants table does not exist.\n";
        exit;
    }
    
    $stmt = $pdo->query("DESCRIBE product_variants");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Product variants table columns:\n";
    echo "=====================\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>