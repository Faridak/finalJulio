<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Get admin statistics
$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'customer') as total_customers,
        (SELECT COUNT(*) FROM users WHERE role = 'merchant') as total_merchants,
        (SELECT COUNT(*) FROM products) as total_products,
        (SELECT COUNT(*) FROM orders) as total_orders,
        (SELECT COALESCE(SUM(total), 0) FROM orders) as total_revenue,
        (SELECT COUNT(*) FROM orders WHERE status = 'pending') as pending_orders,
        (SELECT COUNT(*) FROM users WHERE role = 'merchant' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_merchants
";
$stmt = $pdo->prepare($statsQuery);
$stmt->execute();
$stats = $stmt->fetch();

// Get recent activities with more details
$recentActivitiesQuery = "
    (SELECT 'order' as type, o.id, o.user_id, o.total as amount, o.created_at, o.status,
            u.email as user_email, COALESCE(up.first_name, '') as first_name, COALESCE(up.last_name, '') as last_name,
            CONCAT('New order #', o.id, ' - $', FORMAT(o.total, 2)) as description
     FROM orders o
     LEFT JOIN users u ON o.user_id = u.id
     LEFT JOIN user_profiles up ON u.id = up.user_id
     ORDER BY o.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'customer' as type, u.id, u.id as user_id, 0 as amount, u.created_at, u.role as status,
            u.email as user_email, COALESCE(up.first_name, '') as first_name, COALESCE(up.last_name, '') as last_name,
            CONCAT('New customer registration: ', u.email) as description
     FROM users u
     LEFT JOIN user_profiles up ON u.id = up.user_id
     WHERE u.role = 'customer' AND u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY u.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'merchant' as type, ma.id, ma.user_id, ma.estimated_monthly_sales as amount, ma.created_at, ma.status,
            ma.contact_email as user_email, ma.contact_name as first_name, '' as last_name,
            CONCAT('Merchant application: ', ma.business_name) as description
     FROM merchant_applications ma
     WHERE ma.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY ma.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'product' as type, p.id, p.merchant_id as user_id, p.price as amount, p.created_at, 'added' as status,
            u.email as user_email, COALESCE(up.first_name, '') as first_name, COALESCE(up.last_name, '') as last_name,
            CONCAT('New product: ', p.name) as description
     FROM products p
     LEFT JOIN users u ON p.merchant_id = u.id
     LEFT JOIN user_profiles up ON u.id = up.user_id
     WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY p.created_at DESC LIMIT 5)
    ORDER BY created_at DESC
    LIMIT 15
";
$stmt = $pdo->prepare($recentActivitiesQuery);
$stmt->execute();
$recentActivities = $stmt->fetchAll();

// Get daily revenue for chart (last 30 days)
$revenueQuery = "
    SELECT
        DATE(created_at) as order_date,
        COUNT(*) as order_count,
        COALESCE(SUM(total), 0) as daily_revenue
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY order_date ASC
";
$stmt = $pdo->prepare($revenueQuery);
$stmt->execute();
$revenueData = $stmt->fetchAll();

// If no data, create sample data for the last 7 days to show the chart
if (empty($revenueData)) {
    $revenueData = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $revenueData[] = [
            'order_date' => $date,
            'order_count' => 0,
            'daily_revenue' => 0
        ];
    }
}

// Get top merchants by revenue with enhanced details
$topMerchantsQuery = "
    SELECT
        u.email,
        u.id,
        COALESCE(up.first_name, '') as first_name,
        COALESCE(up.last_name, '') as last_name,
        COUNT(DISTINCT p.id) as product_count,
        COUNT(DISTINCT oi.order_id) as order_count,
        COALESCE(SUM(oi.price * oi.quantity), 0) as merchant_revenue,
        COUNT(DISTINCT CASE WHEN p.stock > 0 THEN p.id END) as active_products,
        MAX(o.created_at) as last_sale_date,
        u.created_at as joined_date
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN products p ON u.id = p.merchant_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE u.role = 'merchant'
    GROUP BY u.id
    ORDER BY merchant_revenue DESC
    LIMIT 5
";
$stmt = $pdo->prepare($topMerchantsQuery);
$stmt->execute();
$topMerchants = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Admin Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                    <span class="text-gray-400">|</span>
                    <span class="text-lg font-semibold text-red-600">Admin Panel</span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 text-gray-600 hover:text-blue-600">
                            <i class="fas fa-user-shield"></i>
                            <span><?= htmlspecialchars($_SESSION['user_email']) ?></span>
                            <i class="fas fa-chevron-down text-sm"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" 
                             class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                            <a href="../index.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">View Store</a>
                            <a href="../logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Welcome Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
            <p class="text-gray-600 mt-2">Monitor and manage your VentDepot platform</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Customers</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_customers']) ?></p>
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
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_merchants']) ?></p>
                        <?php if ($stats['new_merchants'] > 0): ?>
                            <p class="text-xs text-green-600">+<?= $stats['new_merchants'] ?> this week</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-shopping-cart text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Orders</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_orders']) ?></p>
                        <?php if ($stats['pending_orders'] > 0): ?>
                            <p class="text-xs text-yellow-600"><?= $stats['pending_orders'] ?> pending</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <i class="fas fa-dollar-sign text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-900">$<?= number_format($stats['total_revenue'], 2) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Quick Actions</h2>
                <div class="space-y-4">
                    <a href="users.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-users text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Manage Users</h3>
                            <p class="text-sm text-gray-600">View and manage all users</p>
                        </div>
                    </a>

                    <a href="merchants.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-store text-green-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Verify Merchants</h3>
                            <p class="text-sm text-gray-600">Approve merchant applications</p>
                        </div>
                    </a>

                    <a href="orders.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <i class="fas fa-shopping-bag text-yellow-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Monitor Orders</h3>
                            <p class="text-sm text-gray-600">Track all platform orders</p>
                        </div>
                    </a>

                    <a href="analytics.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i class="fas fa-chart-bar text-purple-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Platform Analytics</h3>
                            <p class="text-sm text-gray-600">View detailed platform metrics</p>
                        </div>
                    </a>

                    <a href="global-shipping-admin.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-indigo-100 rounded-lg">
                            <i class="fas fa-shipping-fast text-indigo-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Shipping Management</h3>
                            <p class="text-sm text-gray-600">Configure shipping providers & rates</p>
                        </div>
                    </a>

                    <a href="shipping-info-management.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-teal-100 rounded-lg">
                            <i class="fas fa-map-marked-alt text-teal-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Geographic Settings</h3>
                            <p class="text-sm text-gray-600">Manage zones & shipping info</p>
                        </div>
                    </a>

                    <a href="suppliers.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-orange-100 rounded-lg">
                            <i class="fas fa-truck text-orange-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Supplier Management</h3>
                            <p class="text-sm text-gray-600">Manage suppliers & products</p>
                        </div>
                    </a>

                    <a href="inventory.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <i class="fas fa-boxes text-yellow-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Inventory Management</h3>
                            <p class="text-sm text-gray-600">Track stock levels & movements</p>
                        </div>
                    </a>

                    <a href="seo-management.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-search text-green-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">SEO Management</h3>
                            <p class="text-sm text-gray-600">Manage product SEO & social media tags</p>
                        </div>
                    </a>

                    <a href="pricing-management.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-red-100 rounded-lg">
                            <i class="fas fa-tags text-red-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Price Management</h3>
                            <p class="text-sm text-gray-600">Manage pricing, discounts & promotions</p>
                        </div>
                    </a>
                    
                    <!-- Accounting Modules -->
                    <a href="accounting-dashboard.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-calculator text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Accounting Dashboard</h3>
                            <p class="text-sm text-gray-600">Manage financial records & transactions</p>
                        </div>
                    </a>
                    
                    <a href="accounts-payable.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <i class="fas fa-money-bill-wave text-yellow-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Accounts Payable</h3>
                            <p class="text-sm text-gray-600">Manage vendor invoices & payments</p>
                        </div>
                    </a>
                    
                    <a href="accounts-receivable.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-hand-holding-usd text-green-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Accounts Receivable</h3>
                            <p class="text-sm text-gray-600">Manage customer invoices & receipts</p>
                        </div>
                    </a>
                    
                    <a href="financial-reports.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i class="fas fa-chart-line text-purple-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Financial Reports</h3>
                            <p class="text-sm text-gray-600">Generate financial statements</p>
                        </div>
                    </a>
                    
                    <!-- C-Level Financial Reporting -->
                    <a href="c-level-dashboard.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-indigo-100 rounded-lg">
                            <i class="fas fa-chart-bar text-indigo-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">C-Level Dashboard</h3>
                            <p class="text-sm text-gray-600">Executive financial dashboard</p>
                        </div>
                    </a>
                    
                    <a href="cash-flow-forecasting.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-chart-line text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Cash Flow Forecasting</h3>
                            <p class="text-sm text-gray-600">90-day liquidity planning</p>
                        </div>
                    </a>
                    
                    <a href="budget-vs-actual.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-balance-scale text-green-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Budget vs Actual</h3>
                            <p class="text-sm text-gray-600">Variance analysis reporting</p>
                        </div>
                    </a>
                    
                    <a href="unit-economics.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <i class="fas fa-chart-pie text-yellow-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Unit Economics</h3>
                            <p class="text-sm text-gray-600">CAC, LTV, Payback Period</p>
                        </div>
                    </a>
                    
                    <a href="growth-metrics.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i class="fas fa-arrow-up text-purple-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Growth Metrics</h3>
                            <p class="text-sm text-gray-600">ARR, MRR, Churn Rate, NPS</p>
                        </div>
                    </a>
                    
                    <a href="risk-management.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-red-100 rounded-lg">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Risk Management</h3>
                            <p class="text-sm text-gray-600">Financial risks & compliance</p>
                        </div>
                    </a>

                    <!-- CMS Management -->
                    <a href="cms-dashboard.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i class="fas fa-desktop text-purple-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">CMS Management</h3>
                            <p class="text-sm text-gray-600">Manage frontend content and banners</p>
                        </div>
                    </a>

                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Revenue Overview (Last 30 Days)</h2>
                <div class="relative h-64">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Activities and Top Merchants -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Activities -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-900">Recent Activities</h2>
                    <div class="flex space-x-2">
                        <button onclick="filterActivities('all')" class="activity-filter-btn active px-3 py-1 text-xs rounded-full bg-blue-100 text-blue-800">All</button>
                        <button onclick="filterActivities('order')" class="activity-filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-600">Orders</button>
                        <button onclick="filterActivities('customer')" class="activity-filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-600">Customers</button>
                        <button onclick="filterActivities('merchant')" class="activity-filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-600">Merchants</button>
                    </div>
                </div>

                <?php if (empty($recentActivities)): ?>
                    <p class="text-gray-500 text-center py-8">No recent activities</p>
                <?php else: ?>
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php foreach (array_slice($recentActivities, 0, 12) as $activity): ?>
                            <div class="activity-item activity-<?= $activity['type'] ?> flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors"
                                 onclick="viewActivityDetails('<?= $activity['type'] ?>', <?= $activity['id'] ?>)">
                                <div class="flex items-center">
                                    <div class="p-2 rounded-lg <?php
                                        switch($activity['type']) {
                                            case 'order': echo 'bg-blue-100'; break;
                                            case 'customer': echo 'bg-green-100'; break;
                                            case 'merchant': echo 'bg-orange-100'; break;
                                            case 'product': echo 'bg-purple-100'; break;
                                            default: echo 'bg-gray-100';
                                        }
                                    ?>">
                                        <i class="fas <?php
                                            switch($activity['type']) {
                                                case 'order': echo 'fa-shopping-cart text-blue-600'; break;
                                                case 'customer': echo 'fa-user text-green-600'; break;
                                                case 'merchant': echo 'fa-store text-orange-600'; break;
                                                case 'product': echo 'fa-box text-purple-600'; break;
                                                default: echo 'fa-circle text-gray-600';
                                            }
                                        ?>"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($activity['description']) ?>
                                        </p>
                                        <div class="flex items-center space-x-2 text-xs text-gray-500">
                                            <span><?= date('M j, Y H:i', strtotime($activity['created_at'])) ?></span>
                                            <?php if ($activity['user_email']): ?>
                                                <span>•</span>
                                                <span><?= htmlspecialchars($activity['user_email']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <?php if ($activity['amount'] > 0): ?>
                                        <span class="text-sm font-medium text-gray-900">$<?= number_format($activity['amount'], 2) ?></span>
                                    <?php endif; ?>
                                    <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <button onclick="showAllActivities()" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-external-link-alt mr-1"></i>View All Activities
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Merchants -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-900">Top Merchants (Last 30 Days)</h2>
                    <a href="merchants.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        <i class="fas fa-external-link-alt mr-1"></i>View All
                    </a>
                </div>

                <?php if (empty($topMerchants)): ?>
                    <p class="text-gray-500 text-center py-8">No merchant data available</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($topMerchants as $index => $merchant): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                            <span class="text-green-600 font-semibold text-sm"><?= $index + 1 ?></span>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">
                                                <?php
                                                $displayName = trim($merchant['first_name'] . ' ' . $merchant['last_name']);
                                                echo htmlspecialchars($displayName ?: $merchant['email']);
                                                ?>
                                            </p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($merchant['email']) ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-900">$<?= number_format($merchant['merchant_revenue'], 2) ?></p>
                                        <p class="text-xs text-gray-500">30-day revenue</p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between text-sm text-gray-600 mb-3">
                                    <div class="flex space-x-4">
                                        <span><i class="fas fa-box text-blue-500 mr-1"></i><?= $merchant['product_count'] ?> products</span>
                                        <span><i class="fas fa-check-circle text-green-500 mr-1"></i><?= $merchant['active_products'] ?> active</span>
                                        <span><i class="fas fa-shopping-cart text-purple-500 mr-1"></i><?= $merchant['order_count'] ?> orders</span>
                                    </div>
                                    <?php if ($merchant['last_sale_date']): ?>
                                        <span class="text-xs text-gray-500">
                                            Last sale: <?= date('M j', strtotime($merchant['last_sale_date'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex space-x-2">
                                    <a href="merchant-store.php?id=<?= $merchant['id'] ?>"
                                       class="flex-1 bg-blue-600 text-white text-center py-2 px-3 rounded-md text-sm hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-store mr-1"></i>View Store
                                    </a>
                                    <a href="user-details.php?id=<?= $merchant['id'] ?>"
                                       class="flex-1 bg-green-600 text-white text-center py-2 px-3 rounded-md text-sm hover:bg-green-700 transition-colors">
                                        <i class="fas fa-user mr-1"></i>View Details
                                    </a>
                                    <a href="merchant-sales.php?id=<?= $merchant['id'] ?>"
                                       class="flex-1 bg-purple-600 text-white text-center py-2 px-3 rounded-md text-sm hover:bg-purple-700 transition-colors">
                                        <i class="fas fa-chart-line mr-1"></i>Track Sales
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-6 text-center">
                        <a href="reports.php?type=merchants" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-chart-bar mr-1"></i>View Merchant Analytics
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            // Revenue Chart
            const canvas = document.getElementById('revenueChart');
            if (!canvas) {
                console.error('Revenue chart canvas not found');
                return;
            }

            const ctx = canvas.getContext('2d');
            const revenueData = <?= json_encode($revenueData) ?>;

            console.log('Revenue data:', revenueData); // Debug log

            if (!revenueData || revenueData.length === 0) {
                // Show message if no data
                const chartContainer = canvas.parentElement;
                chartContainer.innerHTML = '<div class="flex items-center justify-center h-48 text-gray-500"><div class="text-center"><i class="fas fa-chart-line text-4xl mb-4"></i><p>No revenue data available</p><p class="text-sm">Data will appear when orders are placed</p></div></div>';
                return;
            }

            try {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: revenueData.map(item => {
                            const date = new Date(item.order_date);
                            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                        }),
                        datasets: [{
                            label: 'Daily Revenue',
                            data: revenueData.map(item => parseFloat(item.daily_revenue) || 0),
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
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
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
                    });
                } catch (error) {
                    console.error('Error creating chart:', error);
                    const chartContainer = canvas.parentElement;
                    chartContainer.innerHTML = '<div class="flex items-center justify-center h-48 text-red-500"><div class="text-center"><i class="fas fa-exclamation-triangle text-4xl mb-4"></i><p>Error loading chart</p><p class="text-sm">Please refresh the page</p></div></div>';
                }
        });

        // Activity filtering and interaction functions
        function filterActivities(type) {
            const items = document.querySelectorAll('.activity-item');
            const buttons = document.querySelectorAll('.activity-filter-btn');

            // Update button states
            buttons.forEach(btn => {
                btn.classList.remove('active', 'bg-blue-100', 'text-blue-800');
                btn.classList.add('bg-gray-100', 'text-gray-600');
            });

            event.target.classList.remove('bg-gray-100', 'text-gray-600');
            event.target.classList.add('active', 'bg-blue-100', 'text-blue-800');

            // Filter items
            items.forEach(item => {
                if (type === 'all' || item.classList.contains(`activity-${type}`)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function viewActivityDetails(type, id) {
            let url = '';
            switch(type) {
                case 'order':
                    url = `order-details.php?id=${id}`;
                    break;
                case 'customer':
                    url = `user-details.php?id=${id}`;
                    break;
                case 'merchant':
                    url = `merchant-details.php?id=${id}`;
                    break;
                case 'product':
                    url = `product-details.php?id=${id}`;
                    break;
                default:
                    return;
            }

            // Open in modal or new window
            window.open(url, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
        }

        function showAllActivities() {
            window.location.href = 'activity-log.php';
        }
    </script>

    <!-- Admin Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h4 class="font-semibold mb-4">Content Management</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="contact-management.php" class="hover:text-white">Contact Information</a></li>
                        <li><a href="shipping-info-management.php" class="hover:text-white">Shipping Information</a></li>
                        <li><a href="returns-faq-management.php" class="hover:text-white">Returns & FAQ</a></li>
                        <li><a href="global-shipping-admin.php" class="hover:text-white">Global Shipping</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">User Management</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="users.php" class="hover:text-white">Manage Users</a></li>
                        <li><a href="merchants.php" class="hover:text-white">Merchant Applications</a></li>
                        <li><a href="orders.php" class="hover:text-white">Order Management</a></li>
                        <li><a href="products.php" class="hover:text-white">Product Management</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">System</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="settings.php" class="hover:text-white">Site Settings</a></li>
                        <li><a href="reports.php" class="hover:text-white">Reports & Analytics</a></li>
                        <li><a href="logs.php" class="hover:text-white">System Logs</a></li>
                        <li><a href="backup.php" class="hover:text-white">Backup & Maintenance</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Shipping & Geography</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="global-shipping-admin.php" class="hover:text-white">Shipping Management</a></li>
                        <li><a href="shipping-info-management.php" class="hover:text-white">Geographic Settings</a></li>
                        <li><a href="shipping-management.php" class="hover:text-white">Shipping Calculator</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Supply Chain</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="suppliers.php" class="hover:text-white">Supplier Management</a></li>
                        <li><a href="inventory.php" class="hover:text-white">Inventory Management</a></li>
                        <li><a href="purchase-orders.php" class="hover:text-white">Purchase Orders</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Financial Reporting</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="c-level-dashboard.php" class="hover:text-white">C-Level Dashboard</a></li>
                        <li><a href="cash-flow-forecasting.php" class="hover:text-white">Cash Flow Forecasting</a></li>
                        <li><a href="budget-vs-actual.php" class="hover:text-white">Budget vs Actual</a></li>
                        <li><a href="unit-economics.php" class="hover:text-white">Unit Economics</a></li>
                        <li><a href="growth-metrics.php" class="hover:text-white">Growth Metrics</a></li>
                        <li><a href="risk-management.php" class="hover:text-white">Risk Management</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="../index.php" class="hover:text-white">View Store</a></li>
                        <li><a href="../contact.php" class="hover:text-white">Customer Contact</a></li>
                        <li><a href="../merchant/register.php" class="hover:text-white">Merchant Registration</a></li>
                        <li><a href="../seller-guide.php" class="hover:text-white">Seller Guide</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 VentDepot Admin Panel. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
