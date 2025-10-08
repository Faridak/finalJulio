<?php
// Queue System for Background Processing
// This file implements a Redis-based queue system for handling heavy calculations

require_once __DIR__ . '/../vendor/autoload.php';

use Predis\Client;

class AccountingQueueSystem {
    private $redis;
    private $enabled = true;
    
    public function __construct() {
        try {
            // Try to connect to Redis
            $this->redis = new Client([
                'scheme' => 'tcp',
                'host'   => '127.0.0.1',
                'port'   => 6379,
                'timeout' => 2.5,
            ]);
            
            // Test connection
            $this->redis->ping();
        } catch (Exception $e) {
            // Redis not available
            $this->enabled = false;
            error_log("Redis connection failed for queue system: " . $e->getMessage());
        }
    }
    
    // Check if queue system is enabled
    public function isEnabled() {
        return $this->enabled;
    }
    
    // Add a job to the queue
    public function enqueue($queueName, $jobData, $priority = 'normal') {
        if (!$this->enabled) {
            return $this->processJobImmediately($jobData);
        }
        
        try {
            $job = [
                'id' => uniqid(),
                'data' => $jobData,
                'created_at' => time(),
                'attempts' => 0,
                'priority' => $priority
            ];
            
            $jsonData = json_encode($job);
            
            // Add to appropriate queue based on priority
            switch ($priority) {
                case 'high':
                    $this->redis->lpush("queue:{$queueName}:high", $jsonData);
                    break;
                case 'low':
                    $this->redis->rpush("queue:{$queueName}:low", $jsonData);
                    break;
                case 'normal':
                default:
                    $this->redis->lpush("queue:{$queueName}", $jsonData);
                    break;
            }
            
            return $job['id'];
        } catch (Exception $e) {
            error_log("Queue enqueue failed: " . $e->getMessage());
            return false;
        }
    }
    
    // Get a job from the queue
    public function dequeue($queueName) {
        if (!$this->enabled) {
            return null;
        }
        
        try {
            // Try high priority queue first
            $job = $this->redis->rpop("queue:{$queueName}:high");
            if (!$job) {
                // Try normal priority queue
                $job = $this->redis->rpop("queue:{$queueName}");
            }
            if (!$job) {
                // Try low priority queue
                $job = $this->redis->rpop("queue:{$queueName}:low");
            }
            
            return $job ? json_decode($job, true) : null;
        } catch (Exception $e) {
            error_log("Queue dequeue failed: " . $e->getMessage());
            return null;
        }
    }
    
    // Mark job as completed
    public function completeJob($queueName, $jobId) {
        if (!$this->enabled) {
            return true;
        }
        
        try {
            // Move job to completed queue
            $this->redis->lpush("queue:{$queueName}:completed", $jobId);
            return true;
        } catch (Exception $e) {
            error_log("Queue complete job failed: " . $e->getMessage());
            return false;
        }
    }
    
    // Mark job as failed
    public function failJob($queueName, $jobId, $error = '') {
        if (!$this->enabled) {
            return true;
        }
        
        try {
            // Move job to failed queue
            $failedJob = [
                'job_id' => $jobId,
                'error' => $error,
                'failed_at' => time()
            ];
            $this->redis->lpush("queue:{$queueName}:failed", json_encode($failedJob));
            return true;
        } catch (Exception $e) {
            error_log("Queue fail job failed: " . $e->getMessage());
            return false;
        }
    }
    
    // Get queue statistics
    public function getQueueStats($queueName) {
        if (!$this->enabled) {
            return ['enabled' => false];
        }
        
        try {
            $stats = [
                'enabled' => true,
                'high_priority' => $this->redis->llen("queue:{$queueName}:high"),
                'normal_priority' => $this->redis->llen("queue:{$queueName}"),
                'low_priority' => $this->redis->llen("queue:{$queueName}:low"),
                'completed' => $this->redis->llen("queue:{$queueName}:completed"),
                'failed' => $this->redis->llen("queue:{$queueName}:failed")
            ];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Queue stats failed: " . $e->getMessage());
            return ['enabled' => true, 'error' => $e->getMessage()];
        }
    }
    
    // Process job immediately if queue is not available
    private function processJobImmediately($jobData) {
        // This would typically call the actual processing function directly
        // For now, we'll just log it
        error_log("Processing job immediately: " . json_encode($jobData));
        return true;
    }
    
    // Retry failed jobs
    public function retryFailedJobs($queueName, $limit = 10) {
        if (!$this->enabled) {
            return 0;
        }
        
        try {
            $retried = 0;
            for ($i = 0; $i < $limit; $i++) {
                $failedJob = $this->redis->rpop("queue:{$queueName}:failed");
                if (!$failedJob) {
                    break;
                }
                
                $jobData = json_decode($failedJob, true);
                if ($jobData && isset($jobData['job_id'])) {
                    // Re-add to normal queue
                    $this->redis->lpush("queue:{$queueName}", json_encode([
                        'id' => $jobData['job_id'],
                        'data' => $jobData['data'] ?? [],
                        'created_at' => time(),
                        'attempts' => ($jobData['attempts'] ?? 0) + 1,
                        'priority' => 'normal'
                    ]));
                    $retried++;
                }
            }
            
            return $retried;
        } catch (Exception $e) {
            error_log("Retry failed jobs failed: " . $e->getMessage());
            return 0;
        }
    }
}

// Specific queue processors for accounting tasks
class AccountingQueueProcessor {
    private $queueSystem;
    private $pdo;
    
    public function __construct($pdo) {
        $this->queueSystem = new AccountingQueueSystem();
        $this->pdo = $pdo;
    }
    
    // Process financial report generation jobs
    public function processFinancialReportJob($jobData) {
        try {
            $reportType = $jobData['report_type'] ?? '';
            $startDate = $jobData['start_date'] ?? '';
            $endDate = $jobData['end_date'] ?? '';
            $userId = $jobData['user_id'] ?? null;
            
            if (!$reportType || !$startDate || !$endDate) {
                throw new Exception("Missing required parameters for financial report");
            }
            
            // Generate the report
            $reportData = $this->generateFinancialReport($reportType, $startDate, $endDate);
            
            // Save to database
            $stmt = $this->pdo->prepare("INSERT INTO financial_reports (report_name, report_type, period_start, period_end, report_data, generated_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                ucfirst(str_replace('_', ' ', $reportType)) . " Report",
                $reportType,
                $startDate,
                $endDate,
                json_encode($reportData),
                $userId
            ]);
            
            // Clear cache for this report
            require_once __DIR__ . '/redis-cache.php';
            $cacheManager = getAccountingCacheManager();
            $cacheManager->clearAllCache();
            
            return ['success' => true, 'report_id' => $this->pdo->lastInsertId()];
        } catch (Exception $e) {
            error_log("Financial report job failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Process commission calculation jobs
    public function processCommissionCalculationJob($jobData) {
        try {
            $salespersonId = $jobData['salesperson_id'] ?? 0;
            $periodStart = $jobData['period_start'] ?? '';
            $periodEnd = $jobData['period_end'] ?? '';
            
            if (!$salespersonId || !$periodStart || !$periodEnd) {
                throw new Exception("Missing required parameters for commission calculation");
            }
            
            // Calculate total sales for the period
            $stmt = $this->pdo->prepare("
                SELECT SUM(total_amount) as total_sales 
                FROM orders 
                WHERE salesperson_id = ? 
                AND created_at BETWEEN ? AND ?
            ");
            $stmt->execute([$salespersonId, $periodStart, $periodEnd]);
            $totalSales = $stmt->fetchColumn() ?? 0;
            
            // Get applicable commission tier
            $stmt = $this->pdo->prepare("
                SELECT tier_name, commission_rate 
                FROM commission_tiers 
                WHERE ? >= min_sales_threshold 
                ORDER BY min_sales_threshold DESC 
                LIMIT 1
            ");
            $stmt->execute([$totalSales]);
            $tier = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tier) {
                // Use default bronze tier
                $tier = ['tier_name' => 'bronze', 'commission_rate' => 0.05];
            }
            
            // Calculate commission amount
            $commissionAmount = $totalSales * $tier['commission_rate'];
            
            // Insert or update commission record
            $stmt = $this->pdo->prepare("
                INSERT INTO sales_commissions 
                (salesperson_id, period_start, period_end, total_sales, commission_rate, commission_amount, tier_level) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                total_sales = VALUES(total_sales), 
                commission_rate = VALUES(commission_rate), 
                commission_amount = VALUES(commission_amount), 
                tier_level = VALUES(tier_level)
            ");
            $stmt->execute([
                $salespersonId, 
                $periodStart, 
                $periodEnd, 
                $totalSales, 
                $tier['commission_rate'], 
                $commissionAmount, 
                $tier['tier_name']
            ]);
            
            // Clear cache for commissions
            require_once __DIR__ . '/redis-cache.php';
            $cacheManager = getAccountingCacheManager();
            $cacheManager->clearAllCache();
            
            return ['success' => true, 'commission_id' => $this->pdo->lastInsertId()];
        } catch (Exception $e) {
            error_log("Commission calculation job failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Process marketing ROI calculation jobs
    public function processMarketingROIJob($jobData) {
        try {
            $campaignId = $jobData['campaign_id'] ?? 0;
            
            if (!$campaignId) {
                throw new Exception("Missing campaign ID for ROI calculation");
            }
            
            // Calculate revenue generated from this campaign
            // This would typically involve complex attribution logic
            $stmt = $this->pdo->prepare("
                SELECT SUM(o.total_amount) as revenue_generated
                FROM orders o
                JOIN revenue_attribution ra ON o.id = ra.order_id
                WHERE ra.attribution_source = 'paid' 
                AND ra.source_detail LIKE ?
            ");
            $stmt->execute(["%campaign_{$campaignId}%"]);
            $revenueGenerated = $stmt->fetchColumn() ?? 0;
            
            // Update campaign with calculated ROI
            $stmt = $this->pdo->prepare("
                UPDATE marketing_campaigns 
                SET revenue_generated = ?, 
                roi = CASE 
                    WHEN budget > 0 THEN (? - budget) / budget 
                    ELSE 0 
                END 
                WHERE id = ?
            ");
            $stmt->execute([$revenueGenerated, $revenueGenerated, $campaignId]);
            
            // Clear cache for marketing data
            require_once __DIR__ . '/redis-cache.php';
            $cacheManager = getAccountingCacheManager();
            $cacheManager->clearAllCache();
            
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Marketing ROI job failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Helper function to generate financial reports
    private function generateFinancialReport($reportType, $startDate, $endDate) {
        switch ($reportType) {
            case 'income_statement':
                return $this->generateIncomeStatement($startDate, $endDate);
            case 'balance_sheet':
                return $this->generateBalanceSheet($endDate);
            case 'cash_flow':
                return $this->generateCashFlowStatement($startDate, $endDate);
            default:
                throw new Exception("Invalid report type");
        }
    }
    
    private function generateIncomeStatement($startDate, $endDate) {
        // Revenue accounts (4000-4999)
        $stmt = $this->pdo->prepare("
            SELECT SUM(gl.credit_amount - gl.debit_amount) as total 
            FROM general_ledger gl 
            JOIN chart_of_accounts ca ON gl.account_id = ca.id 
            WHERE ca.account_code LIKE '4%' 
            AND gl.transaction_date BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $revenue = $stmt->fetchColumn() ?? 0;
        
        // Expense accounts (5000-5999)
        $stmt = $this->pdo->prepare("
            SELECT SUM(gl.debit_amount - gl.credit_amount) as total 
            FROM general_ledger gl 
            JOIN chart_of_accounts ca ON gl.account_id = ca.id 
            WHERE ca.account_code LIKE '5%' 
            AND gl.transaction_date BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $expenses = $stmt->fetchColumn() ?? 0;
        
        return [
            'period' => "$startDate to $endDate",
            'revenue' => floatval($revenue),
            'expenses' => floatval($expenses),
            'net_income' => floatval($revenue - $expenses)
        ];
    }
    
    private function generateBalanceSheet($endDate) {
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
    
    private function generateCashFlowStatement($startDate, $endDate) {
        // Operating activities - Cash receipts
        $stmt = $this->pdo->prepare("
            SELECT SUM(gl.debit_amount) as total 
            FROM general_ledger gl 
            JOIN chart_of_accounts ca ON gl.account_id = ca.id 
            WHERE ca.account_code = '1000' 
            AND gl.transaction_date BETWEEN ? AND ? 
            AND gl.debit_amount > 0
        ");
        $stmt->execute([$startDate, $endDate]);
        $cashReceipts = $stmt->fetchColumn() ?? 0;
        
        // Operating activities - Cash payments
        $stmt = $this->pdo->prepare("
            SELECT SUM(gl.credit_amount) as total 
            FROM general_ledger gl 
            JOIN chart_of_accounts ca ON gl.account_id = ca.id 
            WHERE ca.account_code = '1000' 
            AND gl.transaction_date BETWEEN ? AND ? 
            AND gl.credit_amount > 0
        ");
        $stmt->execute([$startDate, $endDate]);
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

// Queue worker script
class AccountingQueueWorker {
    private $processor;
    private $running = true;
    
    public function __construct($pdo) {
        $this->processor = new AccountingQueueProcessor($pdo);
    }
    
    // Start processing jobs from the queue
    public function start($queueName = 'accounting') {
        echo "Starting accounting queue worker for queue: {$queueName}\n";
        
        while ($this->running) {
            try {
                $queueSystem = new AccountingQueueSystem();
                $job = $queueSystem->dequeue($queueName);
                
                if ($job) {
                    echo "Processing job: {$job['id']}\n";
                    $result = $this->processJob($job);
                    
                    if ($result['success']) {
                        $queueSystem->completeJob($queueName, $job['id']);
                        echo "Job {$job['id']} completed successfully\n";
                    } else {
                        $queueSystem->failJob($queueName, $job['id'], $result['error'] ?? 'Unknown error');
                        echo "Job {$job['id']} failed: " . ($result['error'] ?? 'Unknown error') . "\n";
                    }
                } else {
                    // No jobs available, sleep for a bit
                    sleep(5);
                }
            } catch (Exception $e) {
                error_log("Queue worker error: " . $e->getMessage());
                sleep(10); // Sleep longer on error
            }
        }
    }
    
    // Process a specific job
    private function processJob($job) {
        $jobType = $job['data']['type'] ?? '';
        
        switch ($jobType) {
            case 'financial_report':
                return $this->processor->processFinancialReportJob($job['data']);
            case 'commission_calculation':
                return $this->processor->processCommissionCalculationJob($job['data']);
            case 'marketing_roi':
                return $this->processor->processMarketingROIJob($job['data']);
            default:
                return ['success' => false, 'error' => 'Unknown job type: ' . $jobType];
        }
    }
    
    // Stop the worker
    public function stop() {
        $this->running = false;
        echo "Queue worker stopped\n";
    }
}

// Global function to get queue system instance
function getAccountingQueueSystem() {
    static $queueSystem = null;
    if ($queueSystem === null) {
        $queueSystem = new AccountingQueueSystem();
    }
    return $queueSystem;
}

// Function to enqueue financial report generation
function enqueueFinancialReport($reportType, $startDate, $endDate, $userId = null) {
    $queueSystem = getAccountingQueueSystem();
    $jobData = [
        'type' => 'financial_report',
        'report_type' => $reportType,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'user_id' => $userId
    ];
    
    return $queueSystem->enqueue('accounting', $jobData, 'normal');
}

// Function to enqueue commission calculation
function enqueueCommissionCalculation($salespersonId, $periodStart, $periodEnd) {
    $queueSystem = getAccountingQueueSystem();
    $jobData = [
        'type' => 'commission_calculation',
        'salesperson_id' => $salespersonId,
        'period_start' => $periodStart,
        'period_end' => $periodEnd
    ];
    
    return $queueSystem->enqueue('accounting', $jobData, 'high');
}

// Function to enqueue marketing ROI calculation
function enqueueMarketingROICalculation($campaignId) {
    $queueSystem = getAccountingQueueSystem();
    $jobData = [
        'type' => 'marketing_roi',
        'campaign_id' => $campaignId
    ];
    
    return $queueSystem->enqueue('accounting', $jobData, 'normal');
}

?>