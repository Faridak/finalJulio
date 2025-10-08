<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Get date range from query parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Sales Analytics
$salesStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total) as total_revenue,
        AVG(total) as avg_order_value,
        COUNT(DISTINCT user_id) as unique_customers
    FROM orders 
    WHERE created_at BETWEEN ? AND ? 
    AND status NOT IN ('cancelled', 'refunded')
");
$salesStats->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$salesData = $salesStats->fetch();

// Product Analytics
$productStats = $pdo->prepare("
    SELECT 
        p.name,
        p.category,
        COUNT(oi.id) as times_ordered,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.price * oi.quantity) as total_revenue
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ?
    AND o.status NOT IN ('cancelled', 'refunded')
    GROUP BY p.id
    ORDER BY total_revenue DESC
    LIMIT 10
");
$productStats->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topProducts = $productStats->fetchAll();

// Merchant Analytics
$merchantStats = $pdo->prepare("
    SELECT 
        u.email as merchant_email,
        COUNT(DISTINCT o.id) as total_orders,
        SUM(o.total) as total_revenue,
        COUNT(DISTINCT p.id) as total_products
    FROM users u
    JOIN products p ON u.id = p.merchant_id
    JOIN order_items oi ON p.id = oi.product_id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ?
    AND o.status NOT IN ('cancelled', 'refunded')
    AND u.role = 'merchant'
    GROUP BY u.id
    ORDER BY total_revenue DESC
    LIMIT 10
");
$merchantStats->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topMerchants = $merchantStats->fetchAll();

// Daily Sales Chart Data
$dailySales = $pdo->prepare("
    SELECT 
        DATE(created_at) as sale_date,
        COUNT(*) as orders_count,
        SUM(total) as daily_revenue
    FROM orders 
    WHERE created_at BETWEEN ? AND ?
    AND status NOT IN ('cancelled', 'refunded')
    GROUP BY DATE(created_at)
    ORDER BY sale_date
");
$dailySales->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$dailySalesData = $dailySales->fetchAll();

// Shipping Analytics
$shippingStats = $pdo->query("
    SELECT 
        COUNT(*) as total_shipments,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_shipments,
        COUNT(CASE WHEN status IN ('created', 'picked_up', 'in_transit') THEN 1 END) as pending_shipments,
        AVG(shipping_cost) as avg_shipping_cost
    FROM shipments
")->fetch();

// User Analytics
$userStats = $pdo->query("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN role = 'customer' THEN 1 END) as customers,
        COUNT(CASE WHEN role = 'merchant' THEN 1 END) as merchants,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30_days
    FROM users
")->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                    <span class="text-gray-400">|</span>
                    <a href="dashboard.php" class="text-lg font-semibold text-red-600 hover:text-red-700">Admin Panel</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Reports & Analytics</h1>
                <p class="text-gray-600 mt-2">Comprehensive business intelligence and performance metrics</p>
            </div>
            <div class="flex space-x-2">
                <a href="c-level-dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-chart-bar mr-2"></i>C-Level Dashboard
                </a>
                <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="flex items-end space-x-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>"
                           class="border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>"
                           class="border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    <i class="fas fa-filter mr-2"></i>Apply Filter
                </button>
                <button type="button" onclick="exportReport()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    <i class="fas fa-download mr-2"></i>Export
                </button>
            </form>
        </div>

        <!-- C-Level Financial Reporting Quick Links -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">C-Level Financial Reporting</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <a href="cash-flow-forecasting.php" class="bg-blue-100 hover:bg-blue-200 text-blue-800 p-4 rounded-lg text-center transition duration-200">
                    <i class="fas fa-chart-line text-2xl mb-2"></i>
                    <div class="font-medium text-sm">Cash Flow</div>
                </a>
                <a href="budget-vs-actual.php" class="bg-green-100 hover:bg-green-200 text-green-800 p-4 rounded-lg text-center transition duration-200">
                    <i class="fas fa-balance-scale text-2xl mb-2"></i>
                    <div class="font-medium text-sm">Budget vs Actual</div>
                </a>
                <a href="unit-economics.php" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 p-4 rounded-lg text-center transition duration-200">
                    <i class="fas fa-chart-pie text-2xl mb-2"></i>
                    <div class="font-medium text-sm">Unit Economics</div>
                </a>
                <a href="growth-metrics.php" class="bg-purple-100 hover:bg-purple-200 text-purple-800 p-4 rounded-lg text-center transition duration-200">
                    <i class="fas fa-arrow-up text-2xl mb-2"></i>
                    <div class="font-medium text-sm">Growth Metrics</div>
                </a>
                <a href="risk-management.php" class="bg-red-100 hover:bg-red-200 text-red-800 p-4 rounded-lg text-center transition duration-200">
                    <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                    <div class="font-medium text-sm">Risk Management</div>
                </a>
                <a href="c-level-dashboard.php" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-800 p-4 rounded-lg text-center transition duration-200">
                    <i class="fas fa-chart-bar text-2xl mb-2"></i>
                    <div class="font-medium text-sm">Executive Dashboard</div>
                </a>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shopping-cart text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Orders</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($salesData['total_orders'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-dollar-sign text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Revenue</p>
                        <p class="text-2xl font-semibold text-gray-900">$<?= number_format($salesData['total_revenue'] ?? 0, 2) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-chart-line text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Avg Order Value</p>
                        <p class="text-2xl font-semibold text-gray-900">$<?= number_format($salesData['avg_order_value'] ?? 0, 2) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-users text-orange-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Unique Customers</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($salesData['unique_customers'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Daily Sales Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Daily Sales Trend</h2>
                <canvas id="dailySalesChart" width="400" height="200"></canvas>
            </div>

            <!-- User Analytics -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">User Statistics</h2>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Users</span>
                        <span class="font-semibold"><?= number_format($userStats['total_users']) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Customers</span>
                        <span class="font-semibold text-blue-600"><?= number_format($userStats['customers']) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Merchants</span>
                        <span class="font-semibold text-green-600"><?= number_format($userStats['merchants']) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">New Users (30 days)</span>
                        <span class="font-semibold text-purple-600"><?= number_format($userStats['new_users_30_days']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shipping Analytics -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Shipping Analytics</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?= number_format($shippingStats['total_shipments'] ?? 0) ?></div>
                    <div class="text-sm text-gray-600">Total Shipments</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600"><?= number_format($shippingStats['delivered_shipments'] ?? 0) ?></div>
                    <div class="text-sm text-gray-600">Delivered</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600"><?= number_format($shippingStats['pending_shipments'] ?? 0) ?></div>
                    <div class="text-sm text-gray-600">In Transit</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">$<?= number_format($shippingStats['avg_shipping_cost'] ?? 0, 2) ?></div>
                    <div class="text-sm text-gray-600">Avg Shipping Cost</div>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Top Products by Revenue</h2>
                <div class="space-y-4">
                    <?php if (empty($topProducts)): ?>
                        <p class="text-gray-500 text-center py-4">No product data available for the selected period.</p>
                    <?php else: ?>
                        <?php foreach ($topProducts as $index => $product): ?>
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-blue-600 font-semibold text-sm"><?= $index + 1 ?></span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($product['category']) ?></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-gray-900">$<?= number_format($product['total_revenue'], 2) ?></div>
                                    <div class="text-sm text-gray-500"><?= number_format($product['total_quantity']) ?> sold</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Merchants -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Top Merchants by Revenue</h2>
                <div class="space-y-4">
                    <?php if (empty($topMerchants)): ?>
                        <p class="text-gray-500 text-center py-4">No merchant data available for the selected period.</p>
                    <?php else: ?>
                        <?php foreach ($topMerchants as $index => $merchant): ?>
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-green-600 font-semibold text-sm"><?= $index + 1 ?></span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($merchant['merchant_email']) ?></div>
                                        <div class="text-sm text-gray-500"><?= number_format($merchant['total_products']) ?> products</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-gray-900">$<?= number_format($merchant['total_revenue'], 2) ?></div>
                                    <div class="text-sm text-gray-500"><?= number_format($merchant['total_orders']) ?> orders</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Export Options</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button onclick="exportCSV('sales')" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    <i class="fas fa-file-csv mr-2"></i>Export Sales Data
                </button>
                <button onclick="exportCSV('products')" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    <i class="fas fa-file-csv mr-2"></i>Export Product Data
                </button>
                <button onclick="exportCSV('merchants')" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                    <i class="fas fa-file-csv mr-2"></i>Export Merchant Data
                </button>
            </div>
        </div>
    </div>

    <script>
        // Daily Sales Chart
        const ctx = document.getElementById('dailySalesChart').getContext('2d');
        const dailySalesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($d) { return '"' . date('M j', strtotime($d['sale_date'])) . '"'; }, $dailySalesData)); ?>],
                datasets: [{
                    label: 'Daily Revenue',
                    data: [<?php echo implode(',', array_column($dailySalesData, 'daily_revenue')); ?>],
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        function exportReport() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            window.location.href = `export-report.php?start_date=${startDate}&end_date=${endDate}`;
        }

        function exportCSV(type) {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            window.location.href = `export-csv.php?type=${type}&start_date=${startDate}&end_date=${endDate}`;
        }
    </script>
</body>
</html>
