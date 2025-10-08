<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Get date range from query params
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Platform Overview Statistics
$overviewQuery = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'customer') as total_customers,
        (SELECT COUNT(*) FROM users WHERE role = 'merchant') as total_merchants,
        (SELECT COUNT(*) FROM products) as total_products,
        (SELECT COUNT(*) FROM orders WHERE created_at BETWEEN ? AND ?) as period_orders,
        (SELECT COALESCE(SUM(total), 0) FROM orders WHERE created_at BETWEEN ? AND ?) as period_revenue,
        (SELECT COALESCE(AVG(total), 0) FROM orders WHERE created_at BETWEEN ? AND ?) as avg_order_value,
        (SELECT COUNT(DISTINCT user_id) FROM orders WHERE created_at BETWEEN ? AND ?) as active_customers
";
$stmt = $pdo->prepare($overviewQuery);
$stmt->execute([
    $startDate . ' 00:00:00', $endDate . ' 23:59:59',
    $startDate . ' 00:00:00', $endDate . ' 23:59:59',
    $startDate . ' 00:00:00', $endDate . ' 23:59:59',
    $startDate . ' 00:00:00', $endDate . ' 23:59:59'
]);
$overview = $stmt->fetch();

// Daily revenue and orders for chart
$dailyStatsQuery = "
    SELECT 
        DATE(created_at) as stat_date,
        COUNT(*) as order_count,
        COALESCE(SUM(total), 0) as daily_revenue,
        COUNT(DISTINCT user_id) as unique_customers
    FROM orders 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY stat_date ASC
";
$stmt = $pdo->prepare($dailyStatsQuery);
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$dailyStats = $stmt->fetchAll();

// Top performing merchants
$topMerchantsQuery = "
    SELECT 
        u.email,
        u.id,
        COUNT(DISTINCT p.id) as product_count,
        COUNT(DISTINCT o.id) as order_count,
        COALESCE(SUM(o.total), 0) as merchant_revenue
    FROM users u
    LEFT JOIN products p ON u.id = p.merchant_id
    LEFT JOIN orders o ON o.created_at BETWEEN ? AND ?
    WHERE u.role = 'merchant'
    GROUP BY u.id
    ORDER BY merchant_revenue DESC
    LIMIT 10
";
$stmt = $pdo->prepare($topMerchantsQuery);
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topMerchants = $stmt->fetchAll();

// Product category performance
$categoryStatsQuery = "
    SELECT 
        COALESCE(p.category, 'Uncategorized') as category,
        COUNT(DISTINCT p.id) as product_count,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.total), 0) as category_revenue
    FROM products p
    LEFT JOIN orders o ON o.created_at BETWEEN ? AND ?
    GROUP BY p.category
    ORDER BY category_revenue DESC
    LIMIT 10
";
$stmt = $pdo->prepare($categoryStatsQuery);
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$categoryStats = $stmt->fetchAll();

// User growth statistics
$userGrowthQuery = "
    SELECT 
        DATE(created_at) as signup_date,
        role,
        COUNT(*) as signup_count
    FROM users 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at), role
    ORDER BY signup_date ASC
";
$stmt = $pdo->prepare($userGrowthQuery);
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$userGrowth = $stmt->fetchAll();

// Order status distribution
$orderStatusQuery = "
    SELECT 
        status,
        COUNT(*) as status_count,
        COALESCE(SUM(total), 0) as status_revenue
    FROM orders 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY status
";
$stmt = $pdo->prepare($orderStatusQuery);
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$orderStatus = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Analytics - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
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
                <h1 class="text-3xl font-bold text-gray-900">Platform Analytics</h1>
                <p class="text-gray-600 mt-2">Comprehensive insights into VentDepot performance</p>
            </div>
            
            <!-- Date Range Selector -->
            <form method="GET" class="flex items-center space-x-4">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700">From</label>
                    <input type="date" name="start_date" id="start_date" value="<?= $startDate ?>"
                           class="border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700">To</label>
                    <input type="date" name="end_date" id="end_date" value="<?= $endDate ?>"
                           class="border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="pt-6">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        Update
                    </button>
                </div>
            </form>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Customers</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($overview['total_customers']) ?></p>
                        <p class="text-xs text-gray-500"><?= number_format($overview['active_customers']) ?> active this period</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-store text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Merchants</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($overview['total_merchants']) ?></p>
                        <p class="text-xs text-gray-500"><?= number_format($overview['total_products']) ?> products listed</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-shopping-cart text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Orders (Period)</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($overview['period_orders']) ?></p>
                        <p class="text-xs text-gray-500">Avg: $<?= number_format($overview['avg_order_value'], 2) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <i class="fas fa-dollar-sign text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Revenue (Period)</p>
                        <p class="text-2xl font-bold text-gray-900">$<?= number_format($overview['period_revenue'], 2) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Revenue Trend Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Revenue & Orders Trend</h2>
                <canvas id="revenueTrendChart" width="400" height="200"></canvas>
            </div>

            <!-- Order Status Distribution -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Order Status Distribution</h2>
                <canvas id="orderStatusChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Category Performance and User Growth -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Category Performance Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Category Performance</h2>
                <canvas id="categoryChart" width="400" height="200"></canvas>
            </div>

            <!-- User Growth Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">User Registration Trend</h2>
                <canvas id="userGrowthChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Top Merchants Table -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Top Performing Merchants</h2>
            <?php if (empty($topMerchants)): ?>
                <p class="text-gray-500 text-center py-8">No merchant data available for this period</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Merchant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Products</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($topMerchants as $index => $merchant): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                            <span class="text-yellow-600 font-semibold"><?= $index + 1 ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($merchant['email']) ?></div>
                                        <div class="text-sm text-gray-500">ID: <?= $merchant['id'] ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= number_format($merchant['product_count']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= number_format($merchant['order_count']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        $<?= number_format($merchant['merchant_revenue'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="merchants.php?search=<?= urlencode($merchant['email']) ?>" 
                                           class="text-blue-600 hover:text-blue-900">View Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Export Options -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Export Analytics</h2>
            <div class="flex flex-wrap gap-4">
                <button onclick="exportData('csv')" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    <i class="fas fa-file-csv mr-2"></i>Export CSV
                </button>
                <button onclick="exportData('pdf')" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                    <i class="fas fa-file-pdf mr-2"></i>Export PDF
                </button>
                <button onclick="exportData('excel')" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    <i class="fas fa-file-excel mr-2"></i>Export Excel
                </button>
                <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-print mr-2"></i>Print Report
                </button>
            </div>
        </div>
    </div>

    <script>
        // Revenue Trend Chart
        const revenueTrendCtx = document.getElementById('revenueTrendChart').getContext('2d');
        const dailyStats = <?= json_encode($dailyStats) ?>;
        
        new Chart(revenueTrendCtx, {
            type: 'line',
            data: {
                labels: dailyStats.map(item => new Date(item.stat_date).toLocaleDateString()),
                datasets: [{
                    label: 'Daily Revenue',
                    data: dailyStats.map(item => parseFloat(item.daily_revenue)),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y'
                }, {
                    label: 'Order Count',
                    data: dailyStats.map(item => parseInt(item.order_count)),
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Order Status Chart
        const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
        const orderStatus = <?= json_encode($orderStatus) ?>;
        
        new Chart(orderStatusCtx, {
            type: 'doughnut',
            data: {
                labels: orderStatus.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                datasets: [{
                    data: orderStatus.map(item => parseInt(item.status_count)),
                    backgroundColor: [
                        'rgb(251, 191, 36)', // pending - yellow
                        'rgb(59, 130, 246)', // shipped - blue
                        'rgb(34, 197, 94)',  // delivered - green
                        'rgb(239, 68, 68)'   // cancelled - red
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Category Performance Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryStats = <?= json_encode($categoryStats) ?>;
        
        new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: categoryStats.map(item => item.category),
                datasets: [{
                    label: 'Revenue',
                    data: categoryStats.map(item => parseFloat(item.category_revenue)),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        }
                    }
                }
            }
        });

        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowth = <?= json_encode($userGrowth) ?>;
        
        // Process user growth data
        const dates = [...new Set(userGrowth.map(item => item.signup_date))];
        const customerData = dates.map(date => {
            const item = userGrowth.find(u => u.signup_date === date && u.role === 'customer');
            return item ? parseInt(item.signup_count) : 0;
        });
        const merchantData = dates.map(date => {
            const item = userGrowth.find(u => u.signup_date === date && u.role === 'merchant');
            return item ? parseInt(item.signup_count) : 0;
        });
        
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: dates.map(date => new Date(date).toLocaleDateString()),
                datasets: [{
                    label: 'Customer Signups',
                    data: customerData,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Merchant Signups',
                    data: merchantData,
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        function exportData(format) {
            alert(`Export to ${format.toUpperCase()} functionality would be implemented here.\n\nThis would generate a comprehensive ${format} report with all analytics data for the selected date range.`);
        }
    </script>
</body>
</html>
