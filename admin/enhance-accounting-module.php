<?php
// Enhancement script for accounting module
// This script adds the necessary tables and accounts for commission tracking, marketing expenses, and other requirements

// Database connection
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create sales commission tracking table
    $sql = "CREATE TABLE IF NOT EXISTS sales_commissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        salesperson_id INT NOT NULL,
        salesperson_name VARCHAR(100) NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        total_sales DECIMAL(15,2) DEFAULT 0.00,
        commission_rate DECIMAL(5,4) DEFAULT 0.0500, -- 5% default
        commission_amount DECIMAL(15,2) DEFAULT 0.00,
        tier_level ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
        status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
        gl_transaction_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (gl_transaction_id) REFERENCES general_ledger(id) ON DELETE SET NULL
    )";
    
    $pdo->exec($sql);
    echo "Sales commissions table created successfully\n";
    
    // Create commission tiers table
    $sql = "CREATE TABLE IF NOT EXISTS commission_tiers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tier_name VARCHAR(50) NOT NULL,
        min_sales_threshold DECIMAL(15,2) NOT NULL,
        commission_rate DECIMAL(5,4) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "Commission tiers table created successfully\n";
    
    // Create marketing campaigns table
    $sql = "CREATE TABLE IF NOT EXISTS marketing_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_name VARCHAR(100) NOT NULL,
        campaign_type ENUM('social_media', 'email', 'ppc', 'referral', 'other') NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        budget DECIMAL(15,2) DEFAULT 0.00,
        spent_amount DECIMAL(15,2) DEFAULT 0.00,
        revenue_generated DECIMAL(15,2) DEFAULT 0.00,
        roi DECIMAL(10,4) DEFAULT 0.0000,
        status ENUM('planned', 'active', 'completed', 'cancelled') DEFAULT 'planned',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "Marketing campaigns table created successfully\n";
    
    // Create marketing expenses table
    $sql = "CREATE TABLE IF NOT EXISTS marketing_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT,
        expense_date DATE NOT NULL,
        expense_type ENUM('ads', 'processing_fees', 'promotional_costs', 'other') NOT NULL,
        description TEXT,
        amount DECIMAL(15,2) NOT NULL,
        gl_transaction_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE SET NULL,
        FOREIGN KEY (gl_transaction_id) REFERENCES general_ledger(id) ON DELETE SET NULL
    )";
    
    $pdo->exec($sql);
    echo "Marketing expenses table created successfully\n";
    
    // Create operations costs table
    $sql = "CREATE TABLE IF NOT EXISTS operations_costs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cost_center ENUM('warehouse', 'customer_service', 'admin', 'other') NOT NULL,
        cost_type ENUM('direct', 'indirect') NOT NULL,
        expense_date DATE NOT NULL,
        description TEXT,
        amount DECIMAL(15,2) NOT NULL,
        gl_transaction_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (gl_transaction_id) REFERENCES general_ledger(id) ON DELETE SET NULL
    )";
    
    $pdo->exec($sql);
    echo "Operations costs table created successfully\n";
    
    // Create product costing table
    $sql = "CREATE TABLE IF NOT EXISTS product_costing (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        cost_method ENUM('fifo', 'lifo', 'weighted_average') DEFAULT 'weighted_average',
        unit_cost DECIMAL(15,4) DEFAULT 0.0000,
        total_units INT DEFAULT 0,
        total_cost DECIMAL(15,2) DEFAULT 0.00,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "Product costing table created successfully\n";
    
    // Create payroll table
    $sql = "CREATE TABLE IF NOT EXISTS payroll (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        employee_name VARCHAR(100) NOT NULL,
        payroll_period_start DATE NOT NULL,
        payroll_period_end DATE NOT NULL,
        salary_amount DECIMAL(15,2) DEFAULT 0.00,
        commission_amount DECIMAL(15,2) DEFAULT 0.00,
        bonus_amount DECIMAL(15,2) DEFAULT 0.00,
        overtime_amount DECIMAL(15,2) DEFAULT 0.00,
        benefits_amount DECIMAL(15,2) DEFAULT 0.00,
        tax_deductions DECIMAL(15,2) DEFAULT 0.00,
        other_deductions DECIMAL(15,2) DEFAULT 0.00,
        net_pay DECIMAL(15,2) DEFAULT 0.00,
        status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
        gl_transaction_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (gl_transaction_id) REFERENCES general_ledger(id) ON DELETE SET NULL
    )";
    
    $pdo->exec($sql);
    echo "Payroll table created successfully\n";
    
    // Add additional chart of accounts for the new requirements
    $additionalAccounts = [
        // Additional Assets
        ['1400', 'Equipment', 'asset', 'Business equipment and machinery'],
        
        // Additional Liabilities
        ['2400', 'Accrued Commissions Payable', 'liability', 'Commissions owed to sales staff'],
        ['2500', 'Accrued Payroll Payable', 'liability', 'Payroll amounts owed to employees'],
        ['2600', 'Accrued Expenses', 'liability', 'Expenses incurred but not yet paid'],
        
        // Additional Equity
        ['3200', 'Common Stock', 'equity', 'Shares issued to investors'],
        
        // Additional Revenue
        ['4300', 'Commission Income', 'revenue', 'Income from commissions earned'],
        ['4400', 'Other Income', 'revenue', 'Other miscellaneous income'],
        
        // Additional Expenses
        ['5910', 'Commission Expense', 'expense', 'Commissions paid to sales staff'],
        ['5920', 'Social Media Ads', 'expense', 'Social media advertising costs'],
        ['5930', 'Processing Fees', 'expense', 'Payment processing fees'],
        ['5940', 'Promotional Costs', 'expense', 'Promotional and marketing materials'],
        ['5950', 'Shipping and Packaging', 'expense', 'Direct shipping and packaging costs'],
        ['5960', 'Payment Processing Fees', 'expense', 'Fees for payment processing services']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO chart_of_accounts (account_code, account_name, account_type, description) VALUES (?, ?, ?, ?)");
    
    foreach ($additionalAccounts as $account) {
        $stmt->execute($account);
    }
    
    echo "Additional chart of accounts inserted successfully\n";
    
    // Insert default commission tiers
    $defaultTiers = [
        ['bronze', 0, 0.0500],      // 5% for sales up to threshold
        ['silver', 10000, 0.0750],  // 7.5% for sales above $10,000
        ['gold', 25000, 0.1000],    // 10% for sales above $25,000
        ['platinum', 50000, 0.1250] // 12.5% for sales above $50,000
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO commission_tiers (tier_name, min_sales_threshold, commission_rate) VALUES (?, ?, ?)");
    
    foreach ($defaultTiers as $tier) {
        $stmt->execute($tier);
    }
    
    echo "Default commission tiers inserted successfully\n";
    
    echo "Accounting module enhancement completed successfully!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

?>