<?php
// Setup script for accounting module
// This script creates the necessary tables for the accounting system

// Database connection (assuming this follows the same pattern as other setup files)
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create chart of accounts table
    $sql = "CREATE TABLE IF NOT EXISTS chart_of_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_code VARCHAR(20) UNIQUE NOT NULL,
        account_name VARCHAR(100) NOT NULL,
        account_type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
        description TEXT,
        balance DECIMAL(15,2) DEFAULT 0.00,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "Chart of accounts table created successfully\n";
    
    // Create general ledger table
    $sql = "CREATE TABLE IF NOT EXISTS general_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        transaction_date DATE NOT NULL,
        description TEXT NOT NULL,
        debit_amount DECIMAL(15,2) DEFAULT 0.00,
        credit_amount DECIMAL(15,2) DEFAULT 0.00,
        reference_type ENUM('order', 'invoice', 'payment', 'manual') NOT NULL,
        reference_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($sql);
    echo "General ledger table created successfully\n";
    
    // Create accounts payable table
    $sql = "CREATE TABLE IF NOT EXISTS accounts_payable (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vendor_name VARCHAR(100) NOT NULL,
        invoice_number VARCHAR(50) NOT NULL,
        invoice_date DATE NOT NULL,
        due_date DATE NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        paid_amount DECIMAL(15,2) DEFAULT 0.00,
        status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
        description TEXT,
        gl_transaction_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (gl_transaction_id) REFERENCES general_ledger(id) ON DELETE SET NULL
    )";
    
    $pdo->exec($sql);
    echo "Accounts payable table created successfully\n";
    
    // Create accounts receivable table
    $sql = "CREATE TABLE IF NOT EXISTS accounts_receivable (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(100) NOT NULL,
        invoice_number VARCHAR(50) NOT NULL,
        invoice_date DATE NOT NULL,
        due_date DATE NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        received_amount DECIMAL(15,2) DEFAULT 0.00,
        status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
        description TEXT,
        gl_transaction_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (gl_transaction_id) REFERENCES general_ledger(id) ON DELETE SET NULL
    )";
    
    $pdo->exec($sql);
    echo "Accounts receivable table created successfully\n";
    
    // Create financial reports table
    $sql = "CREATE TABLE IF NOT EXISTS financial_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_name VARCHAR(100) NOT NULL,
        report_type ENUM('balance_sheet', 'income_statement', 'cash_flow') NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        report_data LONGTEXT,
        generated_by INT,
        generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
    )";
    
    $pdo->exec($sql);
    echo "Financial reports table created successfully\n";
    
    // Insert default chart of accounts
    $defaultAccounts = [
        // Assets
        ['1000', 'Cash', 'asset', 'Cash on hand and in bank accounts'],
        ['1100', 'Accounts Receivable', 'asset', 'Money owed by customers'],
        ['1200', 'Inventory', 'asset', 'Value of products in stock'],
        ['1300', 'Prepaid Expenses', 'asset', 'Expenses paid in advance'],
        
        // Liabilities
        ['2000', 'Accounts Payable', 'liability', 'Money owed to suppliers'],
        ['2100', 'Sales Tax Payable', 'liability', 'Sales tax collected from customers'],
        ['2200', 'Income Tax Payable', 'liability', 'Income taxes owed to government'],
        ['2300', 'Loan Payable', 'liability', 'Long-term debt obligations'],
        
        // Equity
        ['3000', 'Owner Equity', 'equity', 'Owner investment in business'],
        ['3100', 'Retained Earnings', 'equity', 'Accumulated profits'],
        
        // Revenue
        ['4000', 'Product Sales', 'revenue', 'Revenue from product sales'],
        ['4100', 'Service Revenue', 'revenue', 'Revenue from services'],
        ['4200', 'Shipping Revenue', 'revenue', 'Revenue from shipping charges'],
        
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
        ['5900', 'Miscellaneous Expense', 'expense', 'Other business expenses']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO chart_of_accounts (account_code, account_name, account_type, description) VALUES (?, ?, ?, ?)");
    
    foreach ($defaultAccounts as $account) {
        $stmt->execute($account);
    }
    
    echo "Default chart of accounts inserted successfully\n";
    
    echo "Accounting module setup completed successfully!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

?>