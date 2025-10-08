<?php
require_once 'config/database.php';

echo "Checking products table structure...\n\n";

try {
    // First check if the table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "Products table does not exist.\n";
        exit;
    }
    
    $stmt = $pdo->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Products table columns:\n";
    echo "=====================\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>