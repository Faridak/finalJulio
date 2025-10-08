<?php
// Redis Caching Configuration for Accounting System
// This file implements Redis caching for frequently accessed accounting data

require_once __DIR__ . '/../vendor/autoload.php';

use Predis\Client;

class AccountingRedisCache {
    private $redis;
    private $enabled = true;
    private $defaultTTL = 300; // 5 minutes default TTL
    
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
            // Redis not available, fall back to file-based caching
            $this->enabled = false;
            error_log("Redis connection failed: " . $e->getMessage());
        }
    }
    
    // Check if Redis caching is enabled
    public function isEnabled() {
        return $this->enabled;
    }
    
    // Get data from cache
    public function get($key) {
        if (!$this->enabled) {
            return $this->getFileCache($key);
        }
        
        try {
            $data = $this->redis->get($key);
            return $data ? json_decode($data, true) : null;
        } catch (Exception $e) {
            error_log("Redis GET failed: " . $e->getMessage());
            return $this->getFileCache($key);
        }
    }
    
    // Set data in cache
    public function set($key, $data, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->defaultTTL;
        }
        
        if (!$this->enabled) {
            return $this->setFileCache($key, $data, $ttl);
        }
        
        try {
            $jsonData = json_encode($data);
            $this->redis->setex($key, $ttl, $jsonData);
            return true;
        } catch (Exception $e) {
            error_log("Redis SET failed: " . $e->getMessage());
            return $this->setFileCache($key, $data, $ttl);
        }
    }
    
    // Delete data from cache
    public function delete($key) {
        if (!$this->enabled) {
            return $this->deleteFileCache($key);
        }
        
        try {
            $this->redis->del($key);
            return true;
        } catch (Exception $e) {
            error_log("Redis DEL failed: " . $e->getMessage());
            return $this->deleteFileCache($key);
        }
    }
    
    // Clear all accounting cache
    public function clearAccountingCache() {
        if (!$this->enabled) {
            return $this->clearFileAccountingCache();
        }
        
        try {
            // Get all keys matching accounting pattern
            $keys = $this->redis->keys('accounting:*');
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
            return true;
        } catch (Exception $e) {
            error_log("Redis clear cache failed: " . $e->getMessage());
            return $this->clearFileAccountingCache();
        }
    }
    
    // Get cache statistics
    public function getStats() {
        if (!$this->enabled) {
            return $this->getFileCacheStats();
        }
        
        try {
            $info = $this->redis->info();
            return [
                'enabled' => true,
                'connected' => true,
                'used_memory' => $info['Memory']['used_memory_human'] ?? 'N/A',
                'connected_clients' => $info['Clients']['connected_clients'] ?? 'N/A',
                'total_commands_processed' => $info['Stats']['total_commands_processed'] ?? 'N/A',
                'keyspace_hits' => $info['Stats']['keyspace_hits'] ?? 'N/A',
                'keyspace_misses' => $info['Stats']['keyspace_misses'] ?? 'N/A'
            ];
        } catch (Exception $e) {
            error_log("Redis stats failed: " . $e->getMessage());
            return [
                'enabled' => true,
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // File-based cache fallback methods
    private function getFileCache($key) {
        $cacheFile = sys_get_temp_dir() . "/accounting_cache_" . md5($key) . ".json";
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && isset($data['expires']) && $data['expires'] > time()) {
                return $data['value'];
            } else {
                // Expired, delete the file
                unlink($cacheFile);
            }
        }
        return null;
    }
    
    private function setFileCache($key, $data, $ttl) {
        $cacheFile = sys_get_temp_dir() . "/accounting_cache_" . md5($key) . ".json";
        $cacheData = [
            'value' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        return file_put_contents($cacheFile, json_encode($cacheData)) !== false;
    }
    
    private function deleteFileCache($key) {
        $cacheFile = sys_get_temp_dir() . "/accounting_cache_" . md5($key) . ".json";
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        return true;
    }
    
    private function clearFileAccountingCache() {
        $tempDir = sys_get_temp_dir();
        $files = glob($tempDir . "/accounting_cache_*.json");
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }
    
    private function getFileCacheStats() {
        $tempDir = sys_get_temp_dir();
        $files = glob($tempDir . "/accounting_cache_*.json");
        $totalSize = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
            }
        }
        
        return [
            'enabled' => false,
            'fallback' => 'file_cache',
            'cache_files' => count($files),
            'total_size' => $totalSize
        ];
    }
}

// Accounting cache manager with specific methods for accounting data
class AccountingCacheManager {
    private $cache;
    
    public function __construct() {
        $this->cache = new AccountingRedisCache();
    }
    
    // Cache chart of accounts
    public function cacheChartOfAccounts($accounts) {
        $key = 'accounting:chart_of_accounts';
        return $this->cache->set($key, $accounts, 600); // 10 minutes
    }
    
    public function getChartOfAccounts() {
        $key = 'accounting:chart_of_accounts';
        return $this->cache->get($key);
    }
    
    // Cache account balance
    public function cacheAccountBalance($accountId, $balance) {
        $key = 'accounting:account_balance:' . $accountId;
        return $this->cache->set($key, $balance, 300); // 5 minutes
    }
    
    public function getAccountBalance($accountId) {
        $key = 'accounting:account_balance:' . $accountId;
        return $this->cache->get($key);
    }
    
    // Cache general ledger entries
    public function cacheGeneralLedger($accountId, $entries, $startDate = null, $endDate = null) {
        $key = 'accounting:general_ledger:' . $accountId;
        if ($startDate) $key .= ':from_' . $startDate;
        if ($endDate) $key .= ':to_' . $endDate;
        return $this->cache->set($key, $entries, 180); // 3 minutes
    }
    
    public function getGeneralLedger($accountId, $startDate = null, $endDate = null) {
        $key = 'accounting:general_ledger:' . $accountId;
        if ($startDate) $key .= ':from_' . $startDate;
        if ($endDate) $key .= ':to_' . $endDate;
        return $this->cache->get($key);
    }
    
    // Cache financial report
    public function cacheFinancialReport($reportType, $startDate, $endDate, $reportData) {
        $key = 'accounting:financial_report:' . $reportType . ':' . $startDate . ':' . $endDate;
        return $this->cache->set($key, $reportData, 600); // 10 minutes
    }
    
    public function getFinancialReport($reportType, $startDate, $endDate) {
        $key = 'accounting:financial_report:' . $reportType . ':' . $startDate . ':' . $endDate;
        return $this->cache->get($key);
    }
    
    // Cache sales commissions
    public function cacheSalesCommissions($commissions) {
        $key = 'accounting:sales_commissions';
        return $this->cache->set($key, $commissions, 180); // 3 minutes
    }
    
    public function getSalesCommissions() {
        $key = 'accounting:sales_commissions';
        return $this->cache->get($key);
    }
    
    // Cache commission tiers
    public function cacheCommissionTiers($tiers) {
        $key = 'accounting:commission_tiers';
        return $this->cache->set($key, $tiers, 600); // 10 minutes
    }
    
    public function getCommissionTiers() {
        $key = 'accounting:commission_tiers';
        return $this->cache->get($key);
    }
    
    // Cache marketing campaigns
    public function cacheMarketingCampaigns($campaigns) {
        $key = 'accounting:marketing_campaigns';
        return $this->cache->set($key, $campaigns, 180); // 3 minutes
    }
    
    public function getMarketingCampaigns() {
        $key = 'accounting:marketing_campaigns';
        return $this->cache->get($key);
    }
    
    // Cache marketing expenses
    public function cacheMarketingExpenses($expenses) {
        $key = 'accounting:marketing_expenses';
        return $this->cache->set($key, $expenses, 180); // 3 minutes
    }
    
    public function getMarketingExpenses() {
        $key = 'accounting:marketing_expenses';
        return $this->cache->get($key);
    }
    
    // Cache operations costs
    public function cacheOperationsCosts($costs) {
        $key = 'accounting:operations_costs';
        return $this->cache->set($key, $costs, 180); // 3 minutes
    }
    
    public function getOperationsCosts() {
        $key = 'accounting:operations_costs';
        return $this->cache->get($key);
    }
    
    // Cache payroll records
    public function cachePayrollRecords($payroll) {
        $key = 'accounting:payroll_records';
        return $this->cache->set($key, $payroll, 180); // 3 minutes
    }
    
    public function getPayrollRecords() {
        $key = 'accounting:payroll_records';
        return $this->cache->get($key);
    }
    
    // Cache financial ratios
    public function cacheFinancialRatios($ratios, $endDate) {
        $key = 'accounting:financial_ratios:' . $endDate;
        return $this->cache->set($key, $ratios, 300); // 5 minutes
    }
    
    public function getFinancialRatios($endDate) {
        $key = 'accounting:financial_ratios:' . $endDate;
        return $this->cache->get($key);
    }
    
    // Clear all accounting cache
    public function clearAllCache() {
        return $this->cache->clearAccountingCache();
    }
    
    // Get cache statistics
    public function getCacheStats() {
        return $this->cache->getStats();
    }
    
    // Check if caching is enabled
    public function isCacheEnabled() {
        return $this->cache->isEnabled();
    }
}

// Global function to get cache manager instance
function getAccountingCacheManager() {
    static $cacheManager = null;
    if ($cacheManager === null) {
        $cacheManager = new AccountingCacheManager();
    }
    return $cacheManager;
}

?>