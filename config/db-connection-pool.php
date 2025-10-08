<?php
// Database Connection Pooling and Optimization Configuration
// This file implements connection pooling and optimization for better performance

class DatabaseConnectionPool {
    private static $instance = null;
    private $pool = [];
    private $maxConnections = 10;
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    
    // Database configuration
    private $config = [
        'host' => 'localhost',
        'dbname' => 'finalJulio',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true, // Enable persistent connections
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    ];
    
    private function __construct() {
        $this->host = $this->config['host'];
        $this->dbname = $this->config['dbname'];
        $this->username = $this->config['username'];
        $this->password = $this->config['password'];
        $this->charset = $this->config['charset'];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        // Try to reuse an existing connection
        foreach ($this->pool as $key => $connection) {
            if ($connection['available']) {
                $this->pool[$key]['available'] = false;
                $this->pool[$key]['last_used'] = time();
                return $connection['pdo'];
            }
        }
        
        // Create a new connection if pool is not full
        if (count($this->pool) < $this->maxConnections) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
                $pdo = new PDO($dsn, $this->username, $this->password, $this->config['options']);
                
                $connectionKey = uniqid();
                $this->pool[$connectionKey] = [
                    'pdo' => $pdo,
                    'available' => false,
                    'created_at' => time(),
                    'last_used' => time()
                ];
                
                return $pdo;
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed");
            }
        }
        
        // If pool is full, wait and try again or return the oldest connection
        $oldestKey = null;
        $oldestTime = time();
        
        foreach ($this->pool as $key => $connection) {
            if ($connection['last_used'] < $oldestTime) {
                $oldestTime = $connection['last_used'];
                $oldestKey = $key;
            }
        }
        
        if ($oldestKey !== null) {
            $this->pool[$oldestKey]['available'] = false;
            $this->pool[$oldestKey]['last_used'] = time();
            return $this->pool[$oldestKey]['pdo'];
        }
        
        throw new Exception("Unable to get database connection");
    }
    
    public function releaseConnection($pdo) {
        foreach ($this->pool as $key => $connection) {
            if ($connection['pdo'] === $pdo) {
                $this->pool[$key]['available'] = true;
                break;
            }
        }
    }
    
    public function closeAllConnections() {
        foreach ($this->pool as $key => $connection) {
            $this->pool[$key]['pdo'] = null;
        }
        $this->pool = [];
    }
    
    public function getPoolStatus() {
        $status = [
            'total_connections' => count($this->pool),
            'available_connections' => 0,
            'used_connections' => 0
        ];
        
        foreach ($this->pool as $connection) {
            if ($connection['available']) {
                $status['available_connections']++;
            } else {
                $status['used_connections']++;
            }
        }
        
        return $status;
    }
}

// Optimized database query functions with proper indexing
class DatabaseOptimizer {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Create indexes for accounting tables
    public function createAccountingIndexes() {
        $indexes = [
            // Chart of accounts indexes
            "CREATE INDEX IF NOT EXISTS idx_coa_code ON chart_of_accounts(account_code)",
            "CREATE INDEX IF NOT EXISTS idx_coa_type ON chart_of_accounts(account_type)",
            "CREATE INDEX IF NOT EXISTS idx_coa_active ON chart_of_accounts(is_active)",
            
            // General ledger indexes
            "CREATE INDEX IF NOT EXISTS idx_gl_account ON general_ledger(account_id)",
            "CREATE INDEX IF NOT EXISTS idx_gl_date ON general_ledger(transaction_date)",
            "CREATE INDEX IF NOT EXISTS idx_gl_reference ON general_ledger(reference_type, reference_id)",
            "CREATE INDEX IF NOT EXISTS idx_gl_amounts ON general_ledger(debit_amount, credit_amount)",
            
            // Accounts payable indexes
            "CREATE INDEX IF NOT EXISTS idx_ap_vendor ON accounts_payable(vendor_name)",
            "CREATE INDEX IF NOT EXISTS idx_ap_date ON accounts_payable(due_date)",
            "CREATE INDEX IF NOT EXISTS idx_ap_status ON accounts_payable(status)",
            "CREATE INDEX IF NOT EXISTS idx_ap_amount ON accounts_payable(amount)",
            
            // Accounts receivable indexes
            "CREATE INDEX IF NOT EXISTS idx_ar_customer ON accounts_receivable(customer_name)",
            "CREATE INDEX IF NOT EXISTS idx_ar_date ON accounts_receivable(due_date)",
            "CREATE INDEX IF NOT EXISTS idx_ar_status ON accounts_receivable(status)",
            "CREATE INDEX IF NOT EXISTS idx_ar_amount ON accounts_receivable(amount)",
            
            // Sales commissions indexes
            "CREATE INDEX IF NOT EXISTS idx_com_salesperson ON sales_commissions(salesperson_id, salesperson_name)",
            "CREATE INDEX IF NOT EXISTS idx_com_period ON sales_commissions(period_start, period_end)",
            "CREATE INDEX IF NOT EXISTS idx_com_sales ON sales_commissions(total_sales)",
            "CREATE INDEX IF NOT EXISTS idx_com_status ON sales_commissions(status)",
            "CREATE INDEX IF NOT EXISTS idx_com_tier ON sales_commissions(tier_level)",
            
            // Commission tiers indexes
            "CREATE INDEX IF NOT EXISTS idx_tiers_threshold ON commission_tiers(min_sales_threshold)",
            "CREATE INDEX IF NOT EXISTS idx_tiers_rate ON commission_tiers(commission_rate)",
            
            // Marketing campaigns indexes
            "CREATE INDEX IF NOT EXISTS idx_mc_name ON marketing_campaigns(campaign_name)",
            "CREATE INDEX IF NOT EXISTS idx_mc_type ON marketing_campaigns(campaign_type)",
            "CREATE INDEX IF NOT EXISTS idx_mc_dates ON marketing_campaigns(start_date, end_date)",
            "CREATE INDEX IF NOT EXISTS idx_mc_status ON marketing_campaigns(status)",
            
            // Marketing expenses indexes
            "CREATE INDEX IF NOT EXISTS idx_me_campaign ON marketing_expenses(campaign_id)",
            "CREATE INDEX IF NOT EXISTS idx_me_date ON marketing_expenses(expense_date)",
            "CREATE INDEX IF NOT EXISTS idx_me_type ON marketing_expenses(expense_type)",
            "CREATE INDEX IF NOT EXISTS idx_me_amount ON marketing_expenses(amount)",
            
            // Operations costs indexes
            "CREATE INDEX IF NOT EXISTS idx_oc_center ON operations_costs(cost_center)",
            "CREATE INDEX IF NOT EXISTS idx_oc_type ON operations_costs(cost_type)",
            "CREATE INDEX IF NOT EXISTS idx_oc_date ON operations_costs(expense_date)",
            "CREATE INDEX IF NOT EXISTS idx_oc_amount ON operations_costs(amount)",
            
            // Product costing indexes
            "CREATE INDEX IF NOT EXISTS idx_pc_product ON product_costing(product_id)",
            "CREATE INDEX IF NOT EXISTS idx_pc_method ON product_costing(cost_method)",
            "CREATE INDEX IF NOT EXISTS idx_pc_updated ON product_costing(last_updated)",
            
            // Payroll indexes
            "CREATE INDEX IF NOT EXISTS idx_payroll_employee ON payroll(employee_id, employee_name)",
            "CREATE INDEX IF NOT EXISTS idx_payroll_period ON payroll(payroll_period_start, payroll_period_end)",
            "CREATE INDEX IF NOT EXISTS idx_payroll_status ON payroll(status)",
            "CREATE INDEX IF NOT EXISTS idx_payroll_amount ON payroll(net_pay)",
            
            // Financial reports indexes
            "CREATE INDEX IF NOT EXISTS idx_fr_name ON financial_reports(report_name)",
            "CREATE INDEX IF NOT EXISTS idx_fr_type ON financial_reports(report_type)",
            "CREATE INDEX IF NOT EXISTS idx_fr_period ON financial_reports(period_start, period_end)",
            "CREATE INDEX IF NOT EXISTS idx_fr_generated ON financial_reports(generated_at)"
        ];
        
        foreach ($indexes as $indexSql) {
            try {
                $this->pdo->exec($indexSql);
            } catch (PDOException $e) {
                error_log("Failed to create index: " . $e->getMessage());
            }
        }
    }
    
    // Optimize queries with prepared statements and proper parameter binding
    public function getAccountBalanceOptimized($accountId) {
        $stmt = $this->pdo->prepare("
            SELECT balance 
            FROM chart_of_accounts 
            WHERE id = :account_id 
            AND is_active = 1
        ");
        $stmt->bindParam(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    public function getGeneralLedgerOptimized($accountId = null, $startDate = null, $endDate = null) {
        $sql = "SELECT gl.*, ca.account_code, ca.account_name 
                FROM general_ledger gl 
                JOIN chart_of_accounts ca ON gl.account_id = ca.id";
        
        $params = [];
        $conditions = [];
        
        if ($accountId) {
            $conditions[] = "gl.account_id = :account_id";
            $params[':account_id'] = $accountId;
        }
        
        if ($startDate) {
            $conditions[] = "gl.transaction_date >= :start_date";
            $params[':start_date'] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "gl.transaction_date <= :end_date";
            $params[':end_date'] = $endDate;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY gl.transaction_date DESC, gl.id DESC LIMIT 1000"; // Limit for performance
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getFinancialReportOptimized($reportType, $startDate, $endDate) {
        switch ($reportType) {
            case 'income_statement':
                return $this->generateIncomeStatementOptimized($startDate, $endDate);
            case 'balance_sheet':
                return $this->generateBalanceSheetOptimized($endDate);
            case 'cash_flow':
                return $this->generateCashFlowStatementOptimized($startDate, $endDate);
            default:
                throw new Exception("Invalid report type");
        }
    }
    
    private function generateIncomeStatementOptimized($startDate, $endDate) {
        // Revenue accounts (4000-4999)
        $stmt = $this->pdo->prepare("
            SELECT SUM(gl.credit_amount - gl.debit_amount) as total 
            FROM general_ledger gl 
            JOIN chart_of_accounts ca ON gl.account_id = ca.id 
            WHERE ca.account_code LIKE '4%' 
            AND gl.transaction_date BETWEEN :start_date AND :end_date
        ");
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        $revenue = $stmt->fetchColumn() ?? 0;
        
        // Expense accounts (5000-5999)
        $stmt = $this->pdo->prepare("
            SELECT SUM(gl.debit_amount - gl.credit_amount) as total 
            FROM general_ledger gl 
            JOIN chart_of_accounts ca ON gl.account_id = ca.id 
            WHERE ca.account_code LIKE '5%' 
            AND gl.transaction_date BETWEEN :start_date AND :end_date
        ");
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        $expenses = $stmt->fetchColumn() ?? 0;
        
        return [
            'period' => "$startDate to $endDate",
            'revenue' => floatval($revenue),
            'expenses' => floatval($expenses),
            'net_income' => floatval($revenue - $expenses)
        ];
    }
    
    private function generateBalanceSheetOptimized($endDate) {
        // Assets (1000-1999)
        $stmt = $this->pdo->prepare("
            SELECT SUM(ca.balance) as total 
            FROM chart_of_accounts ca 
            WHERE ca.account_code LIKE '1%' 
            AND ca.is_active = 1
        ");
        $stmt->execute();
        $assets = $stmt->fetchColumn() ?? 0;
        
        // Liabilities (2000-2999)
        $stmt = $this->pdo->prepare("
            SELECT SUM(ca.balance) as total 
            FROM chart_of_accounts ca 
            WHERE ca.account_code LIKE '2%' 
            AND ca.is_active = 1
        ");
        $stmt->execute();
        $liabilities = $stmt->fetchColumn() ?? 0;
        
        // Equity (3000-3999)
        $stmt = $this->pdo->prepare("
            SELECT SUM(ca.balance) as total 
            FROM chart_of_accounts ca 
            WHERE ca.account_code LIKE '3%' 
            AND ca.is_active = 1
        ");
        $stmt->execute();
        $equity = $stmt->fetchColumn() ?? 0;
        
        return [
            'as_of_date' => $endDate,
            'assets' => floatval($assets),
            'liabilities' => floatval($liabilities),
            'equity' => floatval($equity),
            'balanced' => (abs(($assets - $liabilities - $equity)) < 0.01)
        ];
    }
    
    private function generateCashFlowStatementOptimized($startDate, $endDate) {
        // Operating activities - Cash receipts
        $stmt = $this->pdo->prepare("
            SELECT SUM(gl.debit_amount) as total 
            FROM general_ledger gl 
            JOIN chart_of_accounts ca ON gl.account_id = ca.id 
            WHERE ca.account_code = '1000' 
            AND gl.transaction_date BETWEEN :start_date AND :end_date 
            AND gl.debit_amount > 0
        ");
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        $cashReceipts = $stmt->fetchColumn() ?? 0;
        
        // Operating activities - Cash payments
        $stmt = $this->pdo->prepare("
            SELECT SUM(gl.credit_amount) as total 
            FROM general_ledger gl 
            JOIN chart_of_accounts ca ON gl.account_id = ca.id 
            WHERE ca.account_code = '1000' 
            AND gl.transaction_date BETWEEN :start_date AND :end_date 
            AND gl.credit_amount > 0
        ");
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        $cashPayments = $stmt->fetchColumn() ?? 0;
        
        $netCashFlow = $cashReceipts - $cashPayments;
        
        return [
            'period' => "$startDate to $endDate",
            'cash_receipts' => floatval($cashReceipts),
            'cash_payments' => floatval($cashPayments),
            'net_cash_flow' => floatval($netCashFlow)
        ];
    }
}

// Function to get optimized database connection
function getOptimizedDBConnection() {
    $pool = DatabaseConnectionPool::getInstance();
    return $pool->getConnection();
}

// Function to release database connection back to pool
function releaseDBConnection($pdo) {
    $pool = DatabaseConnectionPool::getInstance();
    $pool->releaseConnection($pdo);
}

// Function to create all accounting indexes
function createAccountingIndexes($pdo) {
    $optimizer = new DatabaseOptimizer($pdo);
    $optimizer->createAccountingIndexes();
}

?>