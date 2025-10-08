<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$merchantId = intval($_GET['id'] ?? 0);

if (!$merchantId) {
    header('Location: merchants.php');
    exit;
}

// Get merchant details
$merchantQuery = "
    SELECT u.*, up.first_name, up.last_name
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE u.id = ? AND u.role = 'merchant'
";
$stmt = $pdo->prepare($merchantQuery);
$stmt->execute([$merchantId]);
$merchant = $stmt->fetch();

if (!$merchant) {
    header('Location: merchants.php');
    exit;
}

// Get date range from query parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Get sales analytics for this merchant
$salesQuery = "
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenue,
        COALESCE(AVG(oi.price * oi.quantity), 0) as avg_order_value,
        COUNT(DISTINCT o.user_id) as unique_customers,
        COUNT(DISTINCT oi.product_id) as products_sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE p.merchant_id = ? 
    AND o.created_at BETWEEN ? AND ?
    AND o.status NOT IN ('cancelled', 'refunded')
";
$stmt = $pdo->prepare($salesQuery);
$stmt->execute([$merchantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$salesData = $stmt->fetch();

// Get daily sales for chart
$dailySalesQuery = "
    SELECT 
        DATE(o.created_at) as sale_date,
        COUNT(DISTINCT o.id) as orders_count,
        COALESCE(SUM(oi.price * oi.quantity), 0) as daily_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE p.merchant_id = ?
    AND o.created_at BETWEEN ? AND ?
    AND o.status NOT IN ('cancelled', 'refunded')
    GROUP BY DATE(o.created_at)
    ORDER BY sale_date
";
$stmt = $pdo->prepare($dailySalesQuery);
$stmt->execute([$merchantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$dailySales = $stmt->fetchAll();

// Get top products for this merchant
$topProductsQuery = "
    SELECT 
        p.name,
        p.id,
        p.price,
        COUNT(oi.id) as times_ordered,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.price * oi.quantity) as product_revenue
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    JOIN orders o ON oi.order_id = o.id
    WHERE p.merchant_id = ?
    AND o.created_at BETWEEN ? AND ?
    AND o.status NOT IN ('cancelled', 'refunded')
    GROUP BY p.id
    ORDER BY product_revenue DESC
    LIMIT 10
";
$stmt = $pdo->prepare($topProductsQuery);
$stmt->execute([$merchantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topProducts = $stmt->fetchAll();

// Get customer analytics
$customerQuery = "
    SELECT 
        u.email,
        COALESCE(up.first_name, '') as first_name,
        COALESCE(up.last_name, '') as last_name,
        COUNT(DISTINCT o.id) as order_count,
        SUM(oi.price * oi.quantity) as customer_revenue
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    JOIN orders o ON u.id = o.user_id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.merchant_id = ?
    AND o.created_at BETWEEN ? AND ?
    AND o.status NOT IN ('cancelled', 'refunded')
    GROUP BY u.id
    ORDER BY customer_revenue DESC
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
    <title>Sales Analytics - <?= htmlspecialchars($merchant['first_name'] . ' ' . $merchant['last_name']) ?> - VentDepot Admin</title>
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
                    <span class="text-gray-400">|</span>
                    <span class="text-lg text-gray-600">Sales Analytics</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    Sales Analytics - <?= htmlspecialchars(trim($merchant['first_name'] . ' ' . $merchant['last_name']) ?: $merchant['email']) ?>
                </h1>
                <p class="text-gray-600 mt-2">Comprehensive sales tracking and performance metrics</p>
            </div>
            <div class="flex space-x-3">
                <a href="merchant-store.php?id=<?= $merchantId ?>" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    <i class="fas fa-store mr-2"></i>View Store
                </a>
                <a href="user-details.php?id=<?= $merchantId ?>" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    <i class="fas fa-user mr-2"></i>View Details
                </a>
                <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="flex items-end space-x-4">
                <input type="hidden" name="id" value="<?= $merchantId ?>">
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

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
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
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-box text-indigo-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Products Sold</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($salesData['products_sold'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Daily Sales Trend</h2>
            <div class="relative h-64">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <!-- Top Products and Customers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Top Products -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Top Products by Revenue</h2>
                <div class="space-y-4">
                    <?php if (empty($topProducts)): ?>
                        <p class="text-gray-500 text-center py-4">No product sales data available for the selected period.</p>
                    <?php else: ?>
                        <?php foreach ($topProducts as $index => $product): ?>
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-blue-600 font-semibold text-sm"><?= $index + 1 ?></span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></div>
                                        <div class="text-sm text-gray-500"><?= number_format($product['total_quantity']) ?> units sold</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-gray-900">$<?= number_format($product['product_revenue'], 2) ?></div>
                                    <div class="text-sm text-gray-500"><?= number_format($product['times_ordered']) ?> orders</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Customers -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Top Customers by Revenue</h2>
                <div class="space-y-4">
                    <?php if (empty($topCustomers)): ?>
                        <p class="text-gray-500 text-center py-4">No customer data available for the selected period.</p>
                    <?php else: ?>
                        <?php foreach ($topCustomers as $index => $customer): ?>
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-green-600 font-semibold text-sm"><?= $index + 1 ?></span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            <?= htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name']) ?: $customer['email']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($customer['email']) ?></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-gray-900">$<?= number_format($customer['customer_revenue'], 2) ?></div>
                                    <div class="text-sm text-gray-500"><?= number_format($customer['order_count']) ?> orders</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Performance Insights -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Performance Insights</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-4 border border-gray-200 rounded-lg">
                    <i class="fas fa-trophy text-yellow-600 text-2xl mb-2"></i>
                    <div class="font-semibold text-gray-900">Best Selling Product</div>
                    <div class="text-sm text-gray-600">
                        <?= !empty($topProducts) ? htmlspecialchars($topProducts[0]['name']) : 'No sales data' ?>
                    </div>
                </div>
                
                <div class="text-center p-4 border border-gray-200 rounded-lg">
                    <i class="fas fa-star text-blue-600 text-2xl mb-2"></i>
                    <div class="font-semibold text-gray-900">Top Customer</div>
                    <div class="text-sm text-gray-600">
                        <?= !empty($topCustomers) ? htmlspecialchars(trim($topCustomers[0]['first_name'] . ' ' . $topCustomers[0]['last_name']) ?: $topCustomers[0]['email']) : 'No customer data' ?>
                    </div>
                </div>
                
                <div class="text-center p-4 border border-gray-200 rounded-lg">
                    <i class="fas fa-calendar text-green-600 text-2xl mb-2"></i>
                    <div class="font-semibold text-gray-900">Reporting Period</div>
                    <div class="text-sm text-gray-600">
                        <?= date('M j', strtotime($startDate)) ?> - <?= date('M j, Y', strtotime($endDate)) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sales Chart
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('salesChart');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            const salesData = <?= json_encode($dailySales) ?>;
            
            if (!salesData || salesData.length === 0) {
                const chartContainer = canvas.parentElement;
                chartContainer.innerHTML = '<div class="flex items-center justify-center h-64 text-gray-500"><div class="text-center"><i class="fas fa-chart-line text-4xl mb-4"></i><p>No sales data available</p><p class="text-sm">Data will appear when orders are placed</p></div></div>';
                return;
            }
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: salesData.map(item => {
                        const date = new Date(item.sale_date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Daily Revenue',
                        data: salesData.map(item => parseFloat(item.daily_revenue) || 0),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.1,
                        fill: true,
                        pointBackgroundColor: 'rgb(59, 130, 246)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(2);
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Revenue: $' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        });

        function exportReport() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            const merchantId = <?= $merchantId ?>;
            window.location.href = `export-merchant-report.php?id=${merchantId}&start_date=${startDate}&end_date=${endDate}`;
        }
    </script>
</body>
</html>
