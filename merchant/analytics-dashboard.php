<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/AnalyticsSystem.php';

// Check if user is merchant
if (!isLoggedIn() || $_SESSION['role'] !== 'merchant') {
    header('Location: login.php');
    exit;
}

$analytics = new AnalyticsSystem($pdo);
$period = $_GET['period'] ?? '30_days';
$merchantId = $_SESSION['user_id'];

// Get comprehensive analytics data
$analyticsData = $analytics->getMerchantAnalytics($merchantId, $period);

// Handle export requests
if (isset($_GET['export'])) {
    requireCSRF();
    $format = $_GET['format'] ?? 'csv';
    $exportData = $analytics->exportAnalytics($analyticsData, $format);
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="analytics_' . date('Y-m-d') . '.' . $format . '"');
    echo $exportData;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Merchant Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Analytics Dashboard</h1>
                    <p class="text-gray-600">Comprehensive business insights and performance metrics</p>
                </div>
                <div class="flex space-x-4">
                    <!-- Period Selector -->
                    <select id="periodSelector" onchange="changePeriod()" 
                            class="border border-gray-300 rounded px-3 py-2">
                        <option value="7_days" <?= $period === '7_days' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="30_days" <?= $period === '30_days' ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="90_days" <?= $period === '90_days' ? 'selected' : '' ?>>Last 90 Days</option>
                        <option value="1_year" <?= $period === '1_year' ? 'selected' : '' ?>>Last Year</option>
                    </select>
                    
                    <!-- Export Button -->
                    <div class="relative">
                        <button onclick="toggleExportMenu()" 
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div id="exportMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                            <a href="?export=1&format=csv&period=<?= $period ?>&<?= generateCSRFInput() ?>" 
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-file-csv mr-2"></i>CSV Export
                            </a>
                            <a href="?export=1&format=pdf&period=<?= $period ?>&<?= generateCSRFInput() ?>" 
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-file-pdf mr-2"></i>PDF Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Revenue -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Total Revenue</h3>
                        <p class="text-2xl font-bold text-green-600">
                            $<?= number_format($analyticsData['sales_overview']['gross_revenue'] ?? 0, 2) ?>
                        </p>
                        <?php if (isset($analyticsData['sales_overview']['growth_metrics']['gross_revenue_growth'])): ?>
                            <p class="text-sm <?= $analyticsData['sales_overview']['growth_metrics']['gross_revenue_growth'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <i class="fas fa-<?= $analyticsData['sales_overview']['growth_metrics']['gross_revenue_growth'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= abs($analyticsData['sales_overview']['growth_metrics']['gross_revenue_growth']) ?>% vs previous period
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-dollar-sign text-green-600"></i>
                    </div>
                </div>
            </div>

            <!-- Total Orders -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Total Orders</h3>
                        <p class="text-2xl font-bold text-blue-600">
                            <?= number_format($analyticsData['sales_overview']['total_orders'] ?? 0) ?>
                        </p>
                        <?php if (isset($analyticsData['sales_overview']['growth_metrics']['total_orders_growth'])): ?>
                            <p class="text-sm <?= $analyticsData['sales_overview']['growth_metrics']['total_orders_growth'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <i class="fas fa-<?= $analyticsData['sales_overview']['growth_metrics']['total_orders_growth'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= abs($analyticsData['sales_overview']['growth_metrics']['total_orders_growth']) ?>% vs previous period
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-shopping-cart text-blue-600"></i>
                    </div>
                </div>
            </div>

            <!-- Average Order Value -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Avg Order Value</h3>
                        <p class="text-2xl font-bold text-purple-600">
                            $<?= number_format($analyticsData['sales_overview']['average_order_value'] ?? 0, 2) ?>
                        </p>
                        <?php if (isset($analyticsData['sales_overview']['growth_metrics']['average_order_value_growth'])): ?>
                            <p class="text-sm <?= $analyticsData['sales_overview']['growth_metrics']['average_order_value_growth'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <i class="fas fa-<?= $analyticsData['sales_overview']['growth_metrics']['average_order_value_growth'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= abs($analyticsData['sales_overview']['growth_metrics']['average_order_value_growth']) ?>% vs previous period
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-chart-line text-purple-600"></i>
                    </div>
                </div>
            </div>

            <!-- Unique Customers -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Unique Customers</h3>
                        <p class="text-2xl font-bold text-orange-600">
                            <?= number_format($analyticsData['sales_overview']['unique_customers'] ?? 0) ?>
                        </p>
                        <?php if (isset($analyticsData['sales_overview']['growth_metrics']['unique_customers_growth'])): ?>
                            <p class="text-sm <?= $analyticsData['sales_overview']['growth_metrics']['unique_customers_growth'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <i class="fas fa-<?= $analyticsData['sales_overview']['growth_metrics']['unique_customers_growth'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= abs($analyticsData['sales_overview']['growth_metrics']['unique_customers_growth']) ?>% vs previous period
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="p-3 bg-orange-100 rounded-full">
                        <i class="fas fa-users text-orange-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Trends Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue Trends</h3>
            <div class="h-80">
                <canvas id="revenueTrendsChart"></canvas>
            </div>
        </div>

        <!-- Product Performance and Order Analytics -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Top Performing Products -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Performing Products</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Units Sold</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rating</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach (array_slice($analyticsData['product_performance'] ?? [], 0, 5) as $product): ?>
                                <tr>
                                    <td class="px-4 py-2">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($product['name']) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= htmlspecialchars($product['category']) ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        <?= number_format($product['units_sold'] ?? 0) ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        $<?= number_format($product['revenue'] ?? 0, 2) ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        <div class="flex items-center">
                                            <span><?= number_format($product['average_rating'] ?? 0, 1) ?></span>
                                            <div class="ml-1 flex">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star text-sm <?= $i <= ($product['average_rating'] ?? 0) ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Order Status Breakdown -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Order Status Breakdown</h3>
                <div class="h-64">
                    <canvas id="orderStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Customer Insights and Reviews -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Customer Insights -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Customer Insights</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="text-gray-600">Total Customers</span>
                        <span class="font-semibold"><?= number_format($analyticsData['customer_insights']['total_customers'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="text-gray-600">Returning Customers</span>
                        <span class="font-semibold"><?= number_format($analyticsData['customer_insights']['returning_customers'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="text-gray-600">Avg Customer LTV</span>
                        <span class="font-semibold">$<?= number_format($analyticsData['customer_insights']['avg_customer_ltv'] ?? 0, 2) ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="text-gray-600">Avg Order Frequency</span>
                        <span class="font-semibold"><?= number_format($analyticsData['customer_insights']['avg_order_frequency'] ?? 0, 1) ?> orders</span>
                    </div>
                </div>

                <!-- Top Customers -->
                <div class="mt-6">
                    <h4 class="font-medium text-gray-900 mb-3">Top Customers</h4>
                    <div class="space-y-2">
                        <?php foreach (array_slice($analyticsData['customer_insights']['top_customers'] ?? [], 0, 5) as $customer): ?>
                            <div class="flex justify-between items-center p-2 hover:bg-gray-50 rounded">
                                <div>
                                    <div class="text-sm font-medium"><?= htmlspecialchars($customer['name'] ?: $customer['email']) ?></div>
                                    <div class="text-xs text-gray-500"><?= $customer['order_count'] ?> orders</div>
                                </div>
                                <div class="text-sm font-semibold text-green-600">
                                    $<?= number_format($customer['total_spent'], 2) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Review Analytics -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Review Analytics</h3>
                <div class="space-y-4">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-gray-900">
                            <?= number_format($analyticsData['review_analytics']['average_rating'] ?? 0, 1) ?>
                        </div>
                        <div class="flex justify-center mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= ($analyticsData['review_analytics']['average_rating'] ?? 0) ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="text-sm text-gray-600">
                            Based on <?= number_format($analyticsData['review_analytics']['total_reviews'] ?? 0) ?> reviews
                        </div>
                    </div>

                    <!-- Rating Distribution -->
                    <div class="space-y-2">
                        <?php 
                        $totalReviews = $analyticsData['review_analytics']['total_reviews'] ?? 1;
                        for ($rating = 5; $rating >= 1; $rating--): 
                            $count = $analyticsData['review_analytics'][$rating === 5 ? 'five_star_count' : 
                                    ($rating === 4 ? 'four_star_count' : 
                                    ($rating === 3 ? 'three_star_count' : 
                                    ($rating === 2 ? 'two_star_count' : 'one_star_count')))] ?? 0;
                            $percentage = $totalReviews > 0 ? ($count / $totalReviews) * 100 : 0;
                        ?>
                            <div class="flex items-center">
                                <span class="text-sm w-6"><?= $rating ?></span>
                                <i class="fas fa-star text-yellow-400 text-sm mx-1"></i>
                                <div class="flex-1 bg-gray-200 rounded-full h-2 mx-2">
                                    <div class="bg-yellow-400 h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                                </div>
                                <span class="text-sm w-12 text-right"><?= number_format($count) ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Commission and Conversion Analytics -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Commission Analytics -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Commission & Earnings</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="text-gray-600">Gross Sales</span>
                        <span class="font-semibold">$<?= number_format($analyticsData['commission_analytics']['total_gross_sales'] ?? 0, 2) ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                        <span class="text-gray-600">Commission Paid</span>
                        <span class="font-semibold text-red-600">-$<?= number_format($analyticsData['commission_analytics']['total_commission_paid'] ?? 0, 2) ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                        <span class="text-gray-600">Net Earnings</span>
                        <span class="font-semibold text-green-600">$<?= number_format($analyticsData['commission_analytics']['total_net_earnings'] ?? 0, 2) ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="text-gray-600">Avg Commission Rate</span>
                        <span class="font-semibold"><?= number_format(($analyticsData['commission_analytics']['avg_commission_rate'] ?? 0) * 100, 2) ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Conversion Metrics -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Conversion Metrics</h3>
                <div class="space-y-4">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-600">
                            <?= number_format($analyticsData['conversion_metrics']['conversion_rate'] ?? 0, 2) ?>%
                        </div>
                        <div class="text-sm text-gray-600">Overall Conversion Rate</div>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Views</span>
                            <span class="font-semibold"><?= number_format($analyticsData['conversion_metrics']['total_views'] ?? 0) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Conversions</span>
                            <span class="font-semibold"><?= number_format($analyticsData['conversion_metrics']['conversions'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Revenue Trends Chart
        const revenueTrendsCtx = document.getElementById('revenueTrendsChart').getContext('2d');
        const revenueTrendsData = <?= json_encode($analyticsData['revenue_trends'] ?? []) ?>;
        
        new Chart(revenueTrendsCtx, {
            type: 'line',
            data: {
                labels: revenueTrendsData.map(item => new Date(item.date).toLocaleDateString()),
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenueTrendsData.map(item => item.revenue),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'Orders',
                        data: revenueTrendsData.map(item => item.orders),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Revenue ($)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Orders' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });

        // Order Status Chart
        const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
        const orderStatusData = <?= json_encode($analyticsData['order_analytics']['status_breakdown'] ?? []) ?>;
        
        new Chart(orderStatusCtx, {
            type: 'doughnut',
            data: {
                labels: orderStatusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                datasets: [{
                    data: orderStatusData.map(item => item.count),
                    backgroundColor: [
                        '#10B981', // completed - green
                        '#F59E0B', // pending - yellow
                        '#EF4444', // cancelled - red
                        '#6B7280', // other - gray
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Utility functions
        function changePeriod() {
            const period = document.getElementById('periodSelector').value;
            window.location.href = `?period=${period}`;
        }

        function toggleExportMenu() {
            const menu = document.getElementById('exportMenu');
            menu.classList.toggle('hidden');
        }

        // Close export menu when clicking outside
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('exportMenu');
            const button = e.target.closest('button');
            if (!button || button.onclick !== toggleExportMenu) {
                menu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>