<?php
// Create Comprehensive Chart of Accounts
// This script adds all the necessary accounts for the full accounting system

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Additional chart of accounts for the comprehensive system
    $comprehensiveAccounts = [
        // Assets
        ['1000', 'Cash', 'asset', 'Cash on hand and in bank accounts'],
        ['1100', 'Accounts Receivable', 'asset', 'Money owed by customers'],
        ['1200', 'Inventory', 'asset', 'Value of products in stock'],
        ['1300', 'Prepaid Expenses', 'asset', 'Expenses paid in advance'],
        ['1400', 'Equipment', 'asset', 'Business equipment and machinery'],
        ['1500', 'Accumulated Depreciation', 'asset', 'Cumulative depreciation of assets'],
        
        // Liabilities
        ['2000', 'Accounts Payable', 'liability', 'Money owed to suppliers'],
        ['2100', 'Sales Tax Payable', 'liability', 'Sales tax collected from customers'],
        ['2200', 'Income Tax Payable', 'liability', 'Income taxes owed to government'],
        ['2300', 'Loan Payable', 'liability', 'Long-term debt obligations'],
        ['2400', 'Accrued Commissions Payable', 'liability', 'Commissions owed to sales staff'],
        ['2500', 'Accrued Payroll Payable', 'liability', 'Payroll amounts owed to employees'],
        ['2600', 'Accrued Expenses', 'liability', 'Expenses incurred but not yet paid'],
        
        // Equity
        ['3000', 'Owner Equity', 'equity', 'Owner investment in business'],
        ['3100', 'Retained Earnings', 'equity', 'Accumulated profits'],
        ['3200', 'Common Stock', 'equity', 'Shares issued to investors'],
        
        // Revenue
        ['4000', 'Product Sales', 'revenue', 'Revenue from product sales'],
        ['4100', 'Service Revenue', 'revenue', 'Revenue from services'],
        ['4200', 'Shipping Revenue', 'revenue', 'Revenue from shipping charges'],
        ['4300', 'Commission Income', 'revenue', 'Income from commissions earned'],
        ['4400', 'Other Income', 'revenue', 'Other miscellaneous income'],
        
        // Expenses
        ['5000', 'Cost of Goods Sold', 'expense', 'Direct costs of products sold'],
        ['5100', 'Salaries and Wages', 'expense', 'Employee compensation'],
        ['5200', 'Rent Expense', 'expense', 'Cost of renting business premises'],
        ['5300', 'Utilities Expense', 'expense', 'Electricity, water, gas, etc.'],
        ['5400', 'Marketing Expense', 'expense', 'Advertising and promotional costs'],
        ['5500', 'Shipping Expense', 'expense', 'Cost of shipping to customers'],
        ['5600', 'Bank Fees', 'expense', 'Bank service charges'],
        ['5700', 'Insurance Expense', 'expense', 'Business insurance premiums'],
        ['5800', 'Depreciation Expense', 'expense', 'Depreciation of assets'],
        ['5900', 'Miscellaneous Expense', 'expense', 'Other business expenses'],
        ['5910', 'Commission Expense', 'expense', 'Commissions paid to sales staff'],
        ['5920', 'Social Media Ads', 'expense', 'Social media advertising costs'],
        ['5930', 'Processing Fees', 'expense', 'Payment processing fees'],
        ['5940', 'Promotional Costs', 'expense', 'Promotional and marketing materials'],
        ['5950', 'Shipping and Packaging', 'expense', 'Direct shipping and packaging costs'],
        ['5960', 'Payment Processing Fees', 'expense', 'Fees for payment processing services']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO chart_of_accounts (account_code, account_name, account_type, description) VALUES (?, ?, ?, ?)");
    
    foreach ($comprehensiveAccounts as $account) {
        $stmt->execute($account);
    }
    
    echo "Comprehensive chart of accounts created successfully\n";
    
    // Insert additional commission tiers for more granularity
    $additionalTiers = [
        ['bronze', 0, 0.0500],        // 5% for sales up to threshold
        ['silver', 10000, 0.0750],    // 7.5% for sales above $10,000
        ['gold', 25000, 0.1000],      // 10% for sales above $25,000
        ['platinum', 50000, 0.1250],  // 12.5% for sales above $50,000
        ['diamond', 100000, 0.1500]   // 15% for sales above $100,000
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO commission_tiers (tier_name, min_sales_threshold, commission_rate) VALUES (?, ?, ?)");
    
    foreach ($additionalTiers as $tier) {
        $stmt->execute($tier);
    }
    
    echo "Commission tiers updated successfully\n";
    
    echo "Comprehensive accounting setup completed successfully!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>