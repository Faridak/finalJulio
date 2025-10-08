<?php
/**
 * Business Automation Management Admin Interface
 */

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/BusinessAutomation.php';

// Check if user is admin
Security::requireAdmin();

$businessAutomation = new BusinessAutomation($pdo);

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'run_commission_progression':
            $result = $businessAutomation->autoProgressCommissionTiers();
            
            if ($result['success']) {
                $message = "Commission tier progression completed. {$result['progressed_count']} salespeople progressed.";
                $messageType = 'success';
                $commissionAlerts = $result['alerts'];
            } else {
                $message = 'Commission tier progression failed: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'run_inventory_alerts':
            $result = $businessAutomation->checkInventoryAndAlert();
            
            if ($result['success']) {
                $message = "Inventory alert check completed. {$result['alert_count']} alerts sent.";
                $messageType = 'success';
                $inventoryAlerts = $result['alerts'];
            } else {
                $message = 'Inventory alert check failed: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'run_marketing_roi':
            $result = $businessAutomation->calculateMarketingROI();
            
            if ($result['success']) {
                $message = "Marketing ROI calculation completed. {$result['updated_count']} campaigns updated.";
                $messageType = 'success';
            } else {
                $message = 'Marketing ROI calculation failed: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'run_financial_closing':
            $result = $businessAutomation->autoCloseFinancialPeriod();
            
            if ($result['success']) {
                if (isset($result['message'])) {
                    $message = $result['message'];
                    $messageType = 'info';
                } else {
                    $message = "Financial period {$result['period']} closed. Net income: $" . number_format($result['net_income'], 2);
                    $messageType = 'success';
                }
            } else {
                $message = 'Financial period closing failed: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'run_all_automations':
            $results = $businessAutomation->runAllAutomations();
            $message = 'All automations completed. Results: ' . json_encode($results);
            $messageType = 'success';
            break;
            
        case 'cleanup_old_records':
            $result = $businessAutomation->cleanupOldRecords(90);
            
            if ($result['success']) {
                $message = "Cleanup completed. Deleted {$result['deleted_notifications']} notifications and {$result['deleted_logs']} logs.";
                $messageType = 'success';
            } else {
                $message = 'Cleanup failed: ' . $result['error'];
                $messageType = 'error';
            }
            break;
    }
}

// Get automation statistics
$automationStats = $businessAutomation->getAutomationStats();

// Get scheduled tasks
$stmt = $pdo->query("SELECT * FROM scheduled_tasks ORDER BY next_run ASC");
$scheduledTasks = $stmt->fetchAll();

// Get recent automation logs
$stmt = $pdo->query("SELECT * FROM automation_logs ORDER BY created_at DESC LIMIT 20");
$automationLogs = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Automation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Business Automation</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <form method="POST" class="me-2">
                            <input type="hidden" name="action" value="run_all_automations">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-lightning"></i> Run All Automations
                            </button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="action" value="cleanup_old_records">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-trash"></i> Cleanup Old Records
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Automation Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Commission Tiers</h5>
                                <p class="card-text">
                                    <?php 
                                    $totalSalespeople = 0;
                                    foreach ($automationStats['commission_tiers'] as $tier) {
                                        $totalSalespeople += $tier['salesperson_count'];
                                    }
                                    echo $totalSalespeople; ?> salespeople
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Low Stock Items</h5>
                                <p class="card-text text-warning">
                                    <?php echo $automationStats['inventory']['low_stock_items'] ?? 0; ?> items
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Closed Periods</h5>
                                <p class="card-text">
                                    <?php echo $automationStats['financial']['closed_periods'] ?? 0; ?> periods
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Avg Marketing ROI</h5>
                                <p class="card-text">
                                    <?php echo round($automationStats['marketing']['avg_roi'] ?? 0, 2); ?>%
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Manual Automation Controls -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Manual Automation Controls</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <form method="POST">
                                    <input type="hidden" name="action" value="run_commission_progression">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-arrow-up-circle"></i> Progress Commissions
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-3">
                                <form method="POST">
                                    <input type="hidden" name="action" value="run_inventory_alerts">
                                    <button type="submit" class="btn btn-warning w-100">
                                        <i class="bi bi-exclamation-triangle"></i> Check Inventory
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-3">
                                <form method="POST">
                                    <input type="hidden" name="action" value="run_marketing_roi">
                                    <button type="submit" class="btn btn-info w-100">
                                        <i class="bi bi-graph-up"></i> Calculate Marketing ROI
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-3">
                                <form method="POST">
                                    <input type="hidden" name="action" value="run_financial_closing">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-calendar-check"></i> Close Financial Period
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scheduled Tasks -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Scheduled Tasks</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Description</th>
                                        <th>Cron Expression</th>
                                        <th>Last Run</th>
                                        <th>Next Run</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scheduledTasks as $task): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($task['task_name']); ?></td>
                                        <td><?php echo htmlspecialchars($task['task_description']); ?></td>
                                        <td><?php echo htmlspecialchars($task['cron_expression']); ?></td>
                                        <td><?php echo $task['last_run'] ? date('Y-m-d H:i:s', strtotime($task['last_run'])) : 'Never'; ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($task['next_run'])); ?></td>
                                        <td>
                                            <?php if ($task['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Automation Logs -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Automation Logs</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Process</th>
                                        <th>Status</th>
                                        <th>Details</th>
                                        <th>Execution Time</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($automationLogs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['process_name']); ?></td>
                                        <td>
                                            <?php
                                            switch ($log['status']) {
                                                case 'success':
                                                    echo '<span class="badge bg-success">Success</span>';
                                                    break;
                                                case 'failed':
                                                    echo '<span class="badge bg-danger">Failed</span>';
                                                    break;
                                                case 'warning':
                                                    echo '<span class="badge bg-warning">Warning</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr(json_encode(json_decode($log['details']), JSON_PRETTY_PRINT), 0, 100)) . (strlen(json_encode(json_decode($log['details']))) > 100 ? '...' : ''); ?></td>
                                        <td><?php echo $log['execution_time'] ? $log['execution_time'] . 's' : 'N/A'; ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>