<?php
/**
 * C-Level Financial Reporting API
 * Provides endpoints for executive financial reporting including cash flow forecasting, 
 * budget vs actual, unit economics, and growth metrics
 */

require_once '../../../config/database.php';
require_once '../../../config/db-connection-pool.php';
require_once '../../../config/redis-cache.php';
require_once '../../../config/queue-system.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isLoggedIn() || getUserRole() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Use connection pooling for better performance
    $pdo = getOptimizedDBConnection();
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_cash_flow_forecast':
        getCashFlowForecast($pdo);
        break;
        
    case 'get_budget_variance':
        getBudgetVariance($pdo);
        break;
        
    case 'get_unit_economics':
        getUnitEconomics($pdo);
        break;
        
    case 'get_growth_metrics':
        getGrowthMetrics($pdo);
        break;
        
    case 'get_operational_metrics':
        getOperationalMetrics($pdo);
        break;
        
    case 'get_financial_risk_indicators':
        getFinancialRiskIndicators($pdo);
        break;
        
    case 'get_executive_dashboard_data':
        getExecutiveDashboardData($pdo);
        break;
        
    case 'generate_executive_report':
        generateExecutiveReport($pdo);
        break;
        
    case 'save_dashboard_config':
        saveDashboardConfig($pdo);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Get cash flow forecast data
 */
function getCashFlowForecast($pdo) {
    $period = $_GET['period'] ?? '90';
    $type = $_GET['type'] ?? 'all'; // actual, predicted, all
    
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $forecastData = $cacheManager->get("cash_flow_forecast_{$period}_{$type}");
        
        if ($forecastData === null) {
            $sql = "SELECT * FROM cash_flow_forecasts WHERE forecast_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
            $params = [$period];
            
            if ($type !== 'all') {
                $sql .= " AND forecast_type = ?";
                $params[] = $type;
            }
            
            $sql .= " ORDER BY forecast_date ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $forecastData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache for 15 minutes
            $cacheManager->set("cash_flow_forecast_{$period}_{$type}", $forecastData, 900);
        }
        
        echo json_encode(['success' => true, 'data' => $forecastData]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve cash flow forecast: ' . $e->getMessage()]);
    }
}

/**
 * Get budget vs actual data
 */
function getBudgetVariance($pdo) {
    $period = $_GET['period'] ?? '30';
    
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $budgetData = $cacheManager->get("budget_variance_{$period}");
        
        if ($budgetData === null) {
            $stmt = $pdo->prepare("
                SELECT * FROM budget_vs_actual 
                WHERE report_period >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY report_period DESC, budget_category
            ");
            $stmt->execute([$period]);
            $budgetData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache for 30 minutes
            $cacheManager->set("budget_variance_{$period}", $budgetData, 1800);
        }
        
        echo json_encode(['success' => true, 'data' => $budgetData]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve budget variance data: ' . $e->getMessage()]);
    }
}

/**
 * Get unit economics data
 */
function getUnitEconomics($pdo) {
    $period = $_GET['period'] ?? '30';
    
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $unitEconomicsData = $cacheManager->get("unit_economics_{$period}");
        
        if ($unitEconomicsData === null) {
            $stmt = $pdo->prepare("
                SELECT * FROM unit_economics 
                WHERE calculation_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY calculation_date DESC
                LIMIT 10
            ");
            $stmt->execute([$period]);
            $unitEconomicsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache for 1 hour
            $cacheManager->set("unit_economics_{$period}", $unitEconomicsData, 3600);
        }
        
        echo json_encode(['success' => true, 'data' => $unitEconomicsData]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve unit economics data: ' . $e->getMessage()]);
    }
}

/**
 * Get growth metrics data
 */
function getGrowthMetrics($pdo) {
    $period = $_GET['period'] ?? '90';
    
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $growthData = $cacheManager->get("growth_metrics_{$period}");
        
        if ($growthData === null) {
            $stmt = $pdo->prepare("
                SELECT * FROM growth_metrics 
                WHERE metric_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY metric_date DESC
                LIMIT 20
            ");
            $stmt->execute([$period]);
            $growthData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache for 1 hour
            $cacheManager->set("growth_metrics_{$period}", $growthData, 3600);
        }
        
        echo json_encode(['success' => true, 'data' => $growthData]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve growth metrics data: ' . $e->getMessage()]);
    }
}

/**
 * Get operational metrics data
 */
function getOperationalMetrics($pdo) {
    $period = $_GET['period'] ?? '90';
    
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $operationalData = $cacheManager->get("operational_metrics_{$period}");
        
        if ($operationalData === null) {
            $stmt = $pdo->prepare("
                SELECT * FROM operational_metrics 
                WHERE metric_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY metric_date DESC
                LIMIT 20
            ");
            $stmt->execute([$period]);
            $operationalData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache for 1 hour
            $cacheManager->set("operational_metrics_{$period}", $operationalData, 3600);
        }
        
        echo json_encode(['success' => true, 'data' => $operationalData]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve operational metrics data: ' . $e->getMessage()]);
    }
}

/**
 * Get financial risk indicators data
 */
function getFinancialRiskIndicators($pdo) {
    $period = $_GET['period'] ?? '90';
    
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $riskData = $cacheManager->get("financial_risk_indicators_{$period}");
        
        if ($riskData === null) {
            $stmt = $pdo->prepare("
                SELECT * FROM financial_risk_indicators 
                WHERE indicator_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY indicator_date DESC
                LIMIT 20
            ");
            $stmt->execute([$period]);
            $riskData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache for 1 hour
            $cacheManager->set("financial_risk_indicators_{$period}", $riskData, 3600);
        }
        
        echo json_encode(['success' => true, 'data' => $riskData]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve financial risk indicators data: ' . $e->getMessage()]);
    }
}

/**
 * Get all executive dashboard data
 */
function getExecutiveDashboardData($pdo) {
    try {
        // Get latest data from all sources
        $data = [
            'cash_flow_forecast' => [],
            'budget_variance' => [],
            'unit_economics' => [],
            'growth_metrics' => [],
            'operational_metrics' => [],
            'financial_risk_indicators' => []
        ];
        
        // Get cash flow forecast (last 30 days, predicted)
        $stmt = $pdo->query("
            SELECT * FROM cash_flow_forecasts 
            WHERE forecast_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND forecast_type = 'predicted'
            ORDER BY forecast_date ASC
            LIMIT 15
        ");
        $data['cash_flow_forecast'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get budget variance (last 30 days)
        $stmt = $pdo->query("
            SELECT * FROM budget_vs_actual 
            WHERE report_period >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY report_period DESC, budget_category
            LIMIT 20
        ");
        $data['budget_variance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get latest unit economics
        $stmt = $pdo->query("
            SELECT * FROM unit_economics 
            ORDER BY calculation_date DESC
            LIMIT 1
        ");
        $data['unit_economics'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get latest growth metrics
        $stmt = $pdo->query("
            SELECT * FROM growth_metrics 
            ORDER BY metric_date DESC
            LIMIT 1
        ");
        $data['growth_metrics'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get latest operational metrics
        $stmt = $pdo->query("
            SELECT * FROM operational_metrics 
            ORDER BY metric_date DESC
            LIMIT 1
        ");
        $data['operational_metrics'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get latest financial risk indicators
        $stmt = $pdo->query("
            SELECT * FROM financial_risk_indicators 
            ORDER BY indicator_date DESC
            LIMIT 1
        ");
        $data['financial_risk_indicators'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $data]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve executive dashboard data: ' . $e->getMessage()]);
    }
}

/**
 * Generate executive report
 */
function generateExecutiveReport($pdo) {
    $reportType = $_GET['report_type'] ?? '';
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    
    if (!$reportType) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Report type is required']);
        return;
    }
    
    try {
        $reportData = null;
        
        switch ($reportType) {
            case 'cash_flow':
                $stmt = $pdo->prepare("
                    SELECT * FROM cash_flow_forecasts 
                    WHERE forecast_date BETWEEN ? AND ?
                    ORDER BY forecast_date ASC
                ");
                $stmt->execute([$startDate, $endDate]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'budget_variance':
                $stmt = $pdo->prepare("
                    SELECT * FROM budget_vs_actual 
                    WHERE report_period BETWEEN ? AND ?
                    ORDER BY report_period DESC, budget_category
                ");
                $stmt->execute([$startDate, $endDate]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'unit_economics':
                $stmt = $pdo->prepare("
                    SELECT * FROM unit_economics 
                    WHERE calculation_date BETWEEN ? AND ?
                    ORDER BY calculation_date DESC
                ");
                $stmt->execute([$startDate, $endDate]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'growth_metrics':
                $stmt = $pdo->prepare("
                    SELECT * FROM growth_metrics 
                    WHERE metric_date BETWEEN ? AND ?
                    ORDER BY metric_date DESC
                ");
                $stmt->execute([$startDate, $endDate]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid report type']);
                return;
        }
        
        if ($reportData === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid report type']);
            return;
        }
        
        // Save report to database
        $stmt = $pdo->prepare("
            INSERT INTO executive_reports (report_name, report_type, period_start, period_end, report_data, generated_by) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            ucfirst(str_replace('_', ' ', $reportType)) . " Report",
            $reportType,
            $startDate,
            $endDate,
            json_encode($reportData),
            $_SESSION['user_id'] ?? null
        ]);
        $reportId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'report_id' => $reportId, 
            'data' => $reportData,
            'message' => 'Report generated successfully'
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to generate executive report: ' . $e->getMessage()]);
    }
}

/**
 * Save dashboard configuration
 */
function saveDashboardConfig($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $dashboardName = $data['dashboard_name'] ?? 'CFO Dashboard';
    $configData = $data['config_data'] ?? [];
    $isDefault = $data['is_default'] ?? false;
    
    try {
        // If setting as default, unset previous default
        if ($isDefault) {
            $stmt = $pdo->prepare("UPDATE executive_dashboard_config SET is_default = FALSE WHERE is_default = TRUE");
            $stmt->execute();
        }
        
        // Insert or update configuration
        $stmt = $pdo->prepare("
            INSERT INTO executive_dashboard_config (dashboard_name, user_id, config_data, is_default) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                config_data = VALUES(config_data),
                is_default = VALUES(is_default),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $dashboardName,
            $_SESSION['user_id'],
            json_encode($configData),
            $isDefault
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Dashboard configuration saved successfully']);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save dashboard configuration: ' . $e->getMessage()]);
    }
}
?>