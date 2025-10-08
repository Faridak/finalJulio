<?php
require_once '../config/database.php';

// Require merchant login
requireRole('merchant');

$merchantId = $_SESSION['user_id'];

// Get date range from query params
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Sales Overview
$salesQuery = "
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(o.total), 0) as total_revenue,
        COALESCE(AVG(o.total), 0) as avg_order_value,
        COUNT(DISTINCT o.user_id) as unique_customers
    FROM orders o
    JOIN products p ON p.merchant_id = ?
    WHERE o.created_at BETWEEN ? AND ?
";
$stmt = $pdo->prepare($salesQuery);
$stmt->execute([$merchantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$salesOverview = $stmt->fetch();

// Daily sales data for chart
$dailySalesQuery = "
    SELECT 
        DATE(o.created_at) as sale_date,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.total), 0) as daily_revenue
    FROM orders o
    JOIN products p ON p.merchant_id = ?
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY DATE(o.created_at)
    ORDER BY sale_date ASC
";
$stmt = $pdo->prepare($dailySalesQuery);
$stmt->execute([$merchantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$dailySales = $stmt->fetchAll();

// Top selling products
$topProductsQuery = "
    SELECT 
        p.name,
        p.price,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.total), 0) as product_revenue
    FROM products p
    LEFT JOIN orders o ON o.created_at BETWEEN ? AND ?
    WHERE p.merchant_id = ?
    GROUP BY p.id
    ORDER BY order_count DESC
    LIMIT 10
";
$stmt = $pdo->prepare($topProductsQuery);
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59', $merchantId]);
$topProducts = $stmt->fetchAll();

// Category performance
$categoryQuery = "
    SELECT 
        COALESCE(p.category, 'Uncategorized') as category,
        COUNT(DISTINCT p.id) as product_count,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.total), 0) as category_revenue
    FROM products p
    LEFT JOIN orders o ON o.created_at BETWEEN ? AND ?
    WHERE p.merchant_id = ?
    GROUP BY p.category
    ORDER BY category_revenue DESC
";
$stmt = $pdo->prepare($categoryQuery);
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59', $merchantId]);
$categoryPerformance = $stmt->fetchAll();

// Customer insights
$customerQuery = "
    SELECT 
        u.email,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.total), 0) as customer_value,
        MAX(o.created_at) as last_order
    FROM users u
    JOIN orders o ON u.id = o.user_id
    JOIN products p ON p.merchant_id = ?
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY customer_value DESC
    LIMIT 10
";
$stmt = $pdo->prepare($customerQuery);
$stmt->execute([$merchantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topCustomers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - VentDepot Merchant</title>
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
                    <a href="dashboard.php" class="text-lg font-semibold text-gray-700 hover:text-blue-600">Merchant Dashboard</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Sales Analytics</h1>
                <p class="text-gray-600 mt-2">Detailed insights into your store performance</p>
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
                        <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Orders</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($salesOverview['total_orders']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-900">$<?= number_format($salesOverview['total_revenue'], 2) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Avg Order Value</p>
                        <p class="text-2xl font-bold text-gray-900">$<?= number_format($salesOverview['avg_order_value'], 2) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <i class="fas fa-users text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Unique Customers</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($salesOverview['unique_customers']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Sales Trend Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Sales Trend</h2>
                <canvas id="salesTrendChart" width="400" height="200"></canvas>
            </div>

            <!-- Category Performance Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Revenue by Category</h2>
                <canvas id="categoryChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Data Tables Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Top Products -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Top Selling Products</h2>
                <?php if (empty($topProducts)): ?>
                    <p class="text-gray-500 text-center py-8">No product data available for this period</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach (array_slice($topProducts, 0, 5) as $index => $product): ?>
                            <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-blue-600 font-semibold"><?= $index + 1 ?></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></p>
                                        <p class="text-sm text-gray-600">$<?= number_format($product['price'], 2) ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-gray-900"><?= $product['order_count'] ?> orders</p>
                                    <p class="text-sm text-gray-600">$<?= number_format($product['product_revenue'], 2) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Customers -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Top Customers</h2>
                <?php if (empty($topCustomers)): ?>
                    <p class="text-gray-500 text-center py-8">No customer data available for this period</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach (array_slice($topCustomers, 0, 5) as $index => $customer): ?>
                            <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-green-600 font-semibold"><?= $index + 1 ?></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($customer['email']) ?></p>
                                        <p class="text-sm text-gray-600">Last order: <?= date('M j, Y', strtotime($customer['last_order'])) ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-gray-900">$<?= number_format($customer['customer_value'], 2) ?></p>
                                    <p class="text-sm text-gray-600"><?= $customer['order_count'] ?> orders</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Export Options -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Export Data</h2>
            <div class="flex flex-wrap gap-4">
                <button onclick="exportData('csv')" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    <i class="fas fa-file-csv mr-2"></i>Export CSV
                </button>
                <button onclick="exportData('pdf')" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                    <i class="fas fa-file-pdf mr-2"></i>Export PDF
                </button>
                <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-print mr-2"></i>Print Report
                </button>
            </div>
        </div>
    </div>

    <script>
        // Sales Trend Chart
        const salesCtx = document.getElementById('salesTrendChart').getContext('2d');
        const salesData = <?= json_encode($dailySales) ?>;
        
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesData.map(item => new Date(item.sale_date).toLocaleDateString()),
                datasets: [{
                    label: 'Daily Revenue',
                    data: salesData.map(item => parseFloat(item.daily_revenue)),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Order Count',
                    data: salesData.map(item => parseInt(item.order_count)),
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

        // Category Performance Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryData = <?= json_encode($categoryPerformance) ?>;
        
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(item => item.category),
                datasets: [{
                    data: categoryData.map(item => parseFloat(item.category_revenue)),
                    backgroundColor: [
                        'rgb(59, 130, 246)',
                        'rgb(34, 197, 94)',
                        'rgb(251, 191, 36)',
                        'rgb(239, 68, 68)',
                        'rgb(168, 85, 247)',
                        'rgb(236, 72, 153)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': $' + context.parsed.toFixed(2);
                            }
                        }
                    }
                }
            }
        });

        function exportData(format) {
            alert(`Export to ${format.toUpperCase()} functionality would be implemented here.\n\nThis would generate a ${format} file with the current analytics data.`);
        }
    </script>
</body>
</html>
