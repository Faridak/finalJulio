<?php
// Verify enhanced accounting module
require_once __DIR__ . '/../config/database.php';

try {
    // Check if new tables exist
    $newTables = [
        'sales_commissions',
        'commission_tiers',
        'marketing_campaigns',
        'marketing_expenses',
        'operations_costs',
        'product_costing',
        'payroll'
    ];
    
    echo "Verifying new tables:\n";
    echo "===================\n";
    foreach ($newTables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $result = $stmt->fetch();
        
        if ($result) {
            // Get row count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM `$table`");
            $countStmt->execute();
            $countResult = $countStmt->fetch();
            echo "Table '$table' exists with " . $countResult['count'] . " records\n";
        } else {
            echo "Table '$table' does not exist\n";
        }
    }
    
    // Check additional chart of accounts
    echo "\nVerifying additional chart of accounts:\n";
    echo "=====================================\n";
    $additionalAccountCodes = ['1400', '2400', '2500', '2600', '3200', '4300', '4400', '5910', '5920', '5930', '5940', '5950', '5960'];
    
    foreach ($additionalAccountCodes as $code) {
        $stmt = $pdo->prepare("SELECT * FROM chart_of_accounts WHERE account_code = ?");
        $stmt->execute([$code]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($account) {
            echo sprintf("%-6s %-30s %-12s\n", 
                $account['account_code'], 
                $account['account_name'], 
                $account['account_type']
            );
        } else {
            echo "Account with code '$code' not found\n";
        }
    }
    
    // Check commission tiers
    echo "\nVerifying commission tiers:\n";
    echo "=========================\n";
    $stmt = $pdo->query("SELECT * FROM commission_tiers ORDER BY min_sales_threshold");
    $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tiers as $tier) {
        echo sprintf("%-10s %-20s %-10s\n", 
            $tier['tier_name'], 
            "Threshold: $" . number_format($tier['min_sales_threshold'], 2), 
            "Rate: " . ($tier['commission_rate'] * 100) . "%"
        );
    }
    
    echo "\nVerification completed!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>