<?php
require_once __DIR__ . '/../config/database.php';

echo "Checking financial_reports table structure...\n\n";

try {
    $stmt = $pdo->query("DESCRIBE financial_reports");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>