<?php
/**
 * Business Metrics Monitoring Dashboard
 */

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/BusinessMetricsMonitor.php';

// Check if user is admin
Security::requireAdmin();

$metricsMonitor = new BusinessMetricsMonitor($pdo);

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_metrics':
            $result = $metricsMonitor->checkMetricsAndAlert();
            
            if ($result['success']) {
                if ($result['alerts_sent'] > 0) {
                    $message = "Checked metrics and sent {$result['alerts_sent']} alerts";
                    $messageType = 'warning';
                } else {
                    $message = 'Checked metrics - no issues found';
                    $messageType = 'success';
                }
            } else {
                $message = 'Failed to check metrics: ' . $result['error'];
                $messageType = 'error';
            }
            break;
    }
}

// Get dashboard metrics
$dashboardMetrics = $metricsMonitor->getDashboardMetrics();

// Get sales report for the last 7 days
$salesReport = $metricsMonitor->getSalesReport('7d');

// Get top selling products
$topProducts = $metricsMonitor->getTopSellingProducts(10, '30d');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Metrics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Business Metrics Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <form method="POST">
                            <input type="hidden" name="action" value="check_metrics">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-search"></i> Check Metrics Now
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

                <!-- Key Metrics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Orders (24h)</h5>
                                <p class="card-text display-6"><?php echo $dashboardMetrics['sales']['orders_last_24h'] ?? 0; ?></p>
                                <small class="text-muted">Last hour: <?php echo $dashboardMetrics['sales']['orders_last_hour'] ?? 0; ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Revenue (24h)</h5>
                                <p class="card-text display-6">$<?php echo number_format($dashboardMetrics['sales']['revenue_last_24h'] ?? 0, 2); ?></p>
                                <small class="text-muted">7 days: $<?php echo number_format($dashboardMetrics['sales']['revenue_last_7d'] ?? 0, 2); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Low Stock Items</h5>
                                <p class="card-text display-6 text-warning"><?php echo $dashboardMetrics['inventory']['low_stock_items'] ?? 0; ?></p>
                                <small class="text-muted">Out of stock: <?php echo $dashboardMetrics['inventory']['out_of_stock_items'] ?? 0; ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Active Users (24h)</h5>
                                <p class="card-text display-6"><?php echo $dashboardMetrics['users']['active_users_24h'] ?? 0; ?></p>
                                <small class="text-muted">Total: <?php echo $dashboardMetrics['users']['total_users'] ?? 0; ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales Chart -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Sales Trend (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" height="100"></canvas>
                    </div>
                </div>

                <!-- Top Products and Inventory Value -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Top Selling Products (30 Days)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>SKU</th>
                                                <th>Units Sold</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topProducts as $product): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                                <td><?php echo $product['units_sold']; ?></td>
                                                <td>$<?php echo number_format($product['revenue'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Inventory Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Products:</span>
                                    <strong><?php echo $dashboardMetrics['inventory']['total_products'] ?? 0; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Low Stock Items:</span>
                                    <strong class="text-warning"><?php echo $dashboardMetrics['inventory']['low_stock_items'] ?? 0; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Out of Stock:</span>
                                    <strong class="text-danger"><?php echo $dashboardMetrics['inventory']['out_of_stock_items'] ?? 0; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total Value:</span>
                                    <strong>$<?php echo number_format($dashboardMetrics['inventory']['total_inventory_value'] ?? 0, 2); ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Financial Summary (24h)</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Successful Payments:</span>
                                    <strong class="text-success"><?php echo $dashboardMetrics['financial']['successful_payments_24h'] ?? 0; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Failed Payments:</span>
                                    <strong class="text-danger"><?php echo $dashboardMetrics['financial']['failed_payments_24h'] ?? 0; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total Revenue:</span>
                                    <strong>$<?php echo number_format($dashboardMetrics['financial']['total_revenue_24h'] ?? 0, 2); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sales chart
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('salesChart').getContext('2d');
            
            // Prepare data from PHP
            var dates = [];
            var orders = [];
            var revenue = [];
            
            <?php foreach ($salesReport['daily_data'] as $day): ?>
            dates.push('<?php echo $day['date']; ?>');
            orders.push(<?php echo $day['orders']; ?>);
            revenue.push(<?php echo $day['revenue']; ?>);
            <?php endforeach; ?>
            
            var salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Orders',
                        data: orders,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        yAxisID: 'y'
                    }, {
                        label: 'Revenue ($)',
                        data: revenue,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Orders'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Revenue ($)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>