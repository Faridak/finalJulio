<?php
/**
 * Add Sample Accounting Data for Testing
 * This script adds sample data to test the accounting functionality
 */

require_once __DIR__ . '/../config/database.php';

// Require admin login
requireRole('admin');

echo "Adding sample accounting data...\n";

try {
    // Get some customer and merchant users for our samples
    $stmt = $pdo->query("SELECT id, email FROM users WHERE role IN ('customer', 'merchant') LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "No users found. Please create some users first.\n";
        exit(1);
    }
    
    // Add sample accounts receivable (money owed to us)
    $stmt = $pdo->prepare("
        INSERT INTO accounts_receivable (customer_name, invoice_number, invoice_date, due_date, amount, description) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $receivables = [
        ['Tech Solutions Inc.', 'INV-2025-101', '2025-09-01', '2025-09-30', 1250.00, 'Software licensing fees'],
        ['Global Marketing Co.', 'INV-2025-102', '2025-09-05', '2025-10-05', 3400.00, 'Marketing campaign services'],
        ['Digital Media Group', 'INV-2025-103', '2025-09-10', '2025-10-10', 2100.00, 'Website development'],
        ['Innovative Startups Ltd.', 'INV-2025-104', '2025-09-12', '2025-10-12', 4500.00, 'Consulting services'],
        ['Creative Design Studio', 'INV-2025-105', '2025-09-15', '2025-10-15', 1800.00, 'Brand design package']
    ];
    
    $addedReceivables = 0;
    foreach ($receivables as $receivable) {
        try {
            $stmt->execute($receivable);
            $addedReceivables++;
        } catch (Exception $e) {
            // Ignore duplicate entries
        }
    }
    
    echo "✓ Added " . $addedReceivables . " sample accounts receivable\n";
    
    // Add sample accounts payable (money we owe)
    $stmt = $pdo->prepare("
        INSERT INTO accounts_payable (vendor_name, invoice_number, invoice_date, due_date, amount, description) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $payables = [
        ['ServerHub Hosting', 'VEND-2025-101', '2025-09-01', '2025-09-30', 850.00, 'Cloud hosting services'],
        ['Office Supply Co.', 'VEND-2025-102', '2025-09-03', '2025-10-03', 420.00, 'Office supplies and equipment'],
        ['Marketing Agency Pro', 'VEND-2025-103', '2025-09-07', '2025-10-07', 2100.00, 'Digital marketing services'],
        ['Software Licenses Inc.', 'VEND-2025-104', '2025-09-10', '2025-10-10', 1200.00, 'Annual software licenses'],
        ['Utility Providers LLC', 'VEND-2025-105', '2025-09-15', '2025-10-15', 650.00, 'Electricity and internet services']
    ];
    
    $addedPayables = 0;
    foreach ($payables as $payable) {
        try {
            $stmt->execute($payable);
            $addedPayables++;
        } catch (Exception $e) {
            // Ignore duplicate entries
        }
    }
    
    echo "✓ Added " . $addedPayables . " sample accounts payable\n";
    
    // Add sample general ledger entries
    $stmt = $pdo->prepare("
        INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    // Get some account IDs for our entries
    $accountStmt = $pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code LIMIT 10");
    $accounts = $accountStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($accounts)) {
        echo "No chart of accounts found. Please run the accounting setup first.\n";
        exit(1);
    }
    
    // Create a mapping of account codes to IDs for easier reference
    $accountMap = [];
    foreach ($accounts as $account) {
        $accountMap[$account['account_code']] = $account['id'];
    }
    
    // Sample general ledger entries
    $ledgerEntries = [
        // Cash receipts
        [$accountMap['1000'] ?? $accounts[0]['id'], '2025-09-01', 'Payment received from Tech Solutions Inc.', 1250.00, 0.00, 'receipt'],
        [$accountMap['4000'] ?? $accounts[7]['id'], '2025-09-01', 'Revenue from Tech Solutions Inc.', 0.00, 1250.00, 'receipt'],
        
        // Expense payments
        [$accountMap['5100'] ?? $accounts[9]['id'], '2025-09-03', 'Office supplies purchase', 420.00, 0.00, 'payment'],
        [$accountMap['1000'] ?? $accounts[0]['id'], '2025-09-03', 'Payment for office supplies', 0.00, 420.00, 'payment'],
        
        // More revenue
        [$accountMap['1000'] ?? $accounts[0]['id'], '2025-09-05', 'Payment received from Global Marketing Co.', 3400.00, 0.00, 'receipt'],
        [$accountMap['4000'] ?? $accounts[7]['id'], '2025-09-05', 'Revenue from Global Marketing Co.', 0.00, 3400.00, 'receipt'],
        
        // More expenses
        [$accountMap['5400'] ?? $accounts[11]['id'], '2025-09-07', 'Digital marketing services', 2100.00, 0.00, 'payment'],
        [$accountMap['1000'] ?? $accounts[0]['id'], '2025-09-07', 'Payment for marketing services', 0.00, 2100.00, 'payment'],
    ];
    
    $addedLedgerEntries = 0;
    foreach ($ledgerEntries as $entry) {
        try {
            $stmt->execute($entry);
            $addedLedgerEntries++;
        } catch (Exception $e) {
            // Ignore duplicate entries
        }
    }
    
    echo "✓ Added " . $addedLedgerEntries . " sample general ledger entries\n";
    
    // Update chart of accounts balances based on our entries
    foreach ($accounts as $account) {
        $accountId = $account['id'];
        
        // Calculate balance: credits - debits
        $balanceStmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(credit_amount), 0) - COALESCE(SUM(debit_amount), 0) as balance
            FROM general_ledger 
            WHERE account_id = ?
        ");
        $balanceStmt->execute([$accountId]);
        $balance = $balanceStmt->fetch(PDO::FETCH_ASSOC)['balance'] ?? 0;
        
        // Update the account balance
        $updateStmt = $pdo->prepare("UPDATE chart_of_accounts SET balance = ? WHERE id = ?");
        $updateStmt->execute([$balance, $accountId]);
    }
    
    echo "✓ Updated chart of accounts balances\n";
    
    // Add sample financial reports
    $stmt = $pdo->prepare("
        INSERT INTO financial_reports (report_name, report_type, period_start, period_end, report_data, generated_by) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $reports = [
        [
            'Income Statement - September 2025',
            'income_statement',
            '2025-09-01',
            '2025-09-30',
            json_encode([
                'period' => '2025-09-01 to 2025-09-30',
                'revenue' => 4650.00,
                'expenses' => 2520.00,
                'net_income' => 2130.00
            ]),
            $_SESSION['user_id'] ?? 1
        ],
        [
            'Balance Sheet - September 2025',
            'balance_sheet',
            '2025-09-01',
            '2025-09-30',
            json_encode([
                'as_of_date' => '2025-09-30',
                'assets' => 15000.00,
                'liabilities' => 3500.00,
                'equity' => 11500.00,
                'balanced' => true
            ]),
            $_SESSION['user_id'] ?? 1
        ]
    ];
    
    $addedReports = 0;
    foreach ($reports as $report) {
        try {
            $stmt->execute($report);
            $addedReports++;
        } catch (Exception $e) {
            // Ignore duplicate entries
        }
    }
    
    echo "✓ Added " . $addedReports . " sample financial reports\n";
    
    // Add sample sales commissions
    $stmt = $pdo->prepare("
        INSERT INTO sales_commissions (salesperson_id, period_start, period_end, sales_amount, commission_rate, commission_amount, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Get some merchant users for sales commissions
    $merchantStmt = $pdo->query("SELECT id FROM users WHERE role = 'merchant' LIMIT 5");
    $merchants = $merchantStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($merchants)) {
        $commissions = [
            [$merchants[0], '2025-09-01', '2025-09-30', 25000.00, 5.00, 1250.00, 'pending'],
            [$merchants[1], '2025-09-01', '2025-09-30', 18000.00, 5.00, 900.00, 'pending'],
            [$merchants[2], '2025-09-01', '2025-09-30', 32000.00, 5.00, 1600.00, 'pending'],
            [$merchants[0], '2025-10-01', '2025-10-31', 22000.00, 5.00, 1100.00, 'pending'],
            [$merchants[1], '2025-10-01', '2025-10-31', 15000.00, 5.00, 750.00, 'pending']
        ];
        
        $addedCommissions = 0;
        foreach ($commissions as $commission) {
            try {
                $stmt->execute($commission);
                $addedCommissions++;
            } catch (Exception $e) {
                // Ignore duplicate entries
            }
        }
        
        echo "✓ Added " . $addedCommissions . " sample sales commissions\n";
    } else {
        echo "No merchant users found for sales commissions\n";
    }
    
    // Add sample marketing expenses
    $stmt = $pdo->prepare("
        INSERT INTO marketing_expenses (campaign_name, start_date, end_date, budget, spent, status, roi) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $marketingExpenses = [
        ['Fall Product Launch', '2025-10-01', '2025-10-31', 5000.00, 3200.00, 'active', 2.5],
        ['Social Media Campaign', '2025-10-05', '2025-11-05', 3000.00, 1800.00, 'active', 3.2],
        ['Email Marketing', '2025-10-10', '2025-11-10', 1500.00, 950.00, 'active', 4.1],
        ['SEO Optimization', '2025-10-01', '2025-10-31', 2000.00, 1200.00, 'planned', 5.0]
    ];
    
    $addedMarketingExpenses = 0;
    foreach ($marketingExpenses as $expense) {
        try {
            $stmt->execute($expense);
            $addedMarketingExpenses++;
        } catch (Exception $e) {
            // Ignore duplicate entries
        }
    }
    
    echo "✓ Added " . $addedMarketingExpenses . " sample marketing expenses\n";
    
    echo "\nSample accounting data added successfully!\n";
    echo "You can now test the accounting functionality in the admin panel.\n";
    
} catch (Exception $e) {
    echo "Error adding sample data: " . $e->getMessage() . "\n";
    exit(1);
}
?>