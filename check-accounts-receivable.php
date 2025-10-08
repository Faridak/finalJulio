<?php
require_once 'config/database.php';

try {
    $stmt = $pdo->query('DESCRIBE accounts_receivable');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Accounts Receivable Table Structure:\n";
    echo "====================================\n";
    foreach ($columns as $column) {
        echo "Field: " . $column['Field'] . "\n";
        echo "Type: " . $column['Type'] . "\n";
        echo "Null: " . $column['Null'] . "\n";
        echo "Key: " . $column['Key'] . "\n";
        echo "Default: " . $column['Default'] . "\n";
        echo "Extra: " . $column['Extra'] . "\n";
        echo "------------------------\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>