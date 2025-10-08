<?php
// View current chart of accounts
require_once __DIR__ . '/../config/database.php';

try {
    // Get all chart of accounts
    $stmt = $pdo->query("SELECT * FROM chart_of_accounts ORDER BY account_code");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current Chart of Accounts:\n";
    echo "========================\n";
    foreach ($accounts as $account) {
        echo sprintf("%-6s %-30s %-12s %s\n", 
            $account['account_code'], 
            $account['account_name'], 
            $account['account_type'], 
            $account['description']
        );
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>