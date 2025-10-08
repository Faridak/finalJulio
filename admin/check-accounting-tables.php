<?php
require_once 'config/database.php';

$tables = [
    'chart_of_accounts',
    'general_ledger',
    'accounts_payable',
    'accounts_receivable'
];

echo "Checking accounting tables...\n\n";

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $result = $stmt->fetch();
        echo "$table: " . ($result ? "EXISTS" : "MISSING") . "\n";
    } catch (Exception $e) {
        echo "$table: ERROR - " . $e->getMessage() . "\n";
    }
}
?>