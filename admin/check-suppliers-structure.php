<?php
require_once 'config/database.php';

echo "Checking suppliers table structure...\n\n";

try {
    // First check if the table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'suppliers'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "Suppliers table does not exist.\n";
        exit;
    }
    
    $stmt = $pdo->query("DESCRIBE suppliers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Suppliers table columns:\n";
    echo "=====================\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>