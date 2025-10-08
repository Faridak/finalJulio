<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/AnalyticsSystem.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$analytics = new AnalyticsSystem($pdo);
$period = $_GET['period'] ?? '30_days';

// Get platform analytics
$platformData = $analytics->getPlatformAnalytics($period);

// Handle real-time data refresh
if (isset($_GET['ajax']) && $_GET['ajax'] === 'refresh') {
    header('Content-Type: application/json');
    echo json_encode($platformData);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Analytics - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Platform Analytics</h1>
                    <p class="text-gray-600">Comprehensive marketplace insights and business intelligence</p>
                </div>
                <div class="flex space-x-4">
                    <!-- Real-time Refresh -->
                    <button onclick="refreshData()" id="refreshBtn"
                            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                    
                    <!-- Period Selector -->
                    <select id="periodSelector" onchange="changePeriod()" 
                            class="border border-gray-300 rounded px-3 py-2">
                        <option value="7_days" <?= $period === '7_days' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="30_days" <?= $period === '30_days' ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="90_days" <?= $period === '90_days' ? 'selected' : '' ?>>Last 90 Days</option>
                        <option value="1_year" <?= $period === '1_year' ? 'selected' : '' ?>>Last Year</option>
                    </select>
                    
                    <!-- Advanced Reports -->
                    <button onclick="openAdvancedReports()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-chart-bar mr-2"></i>Advanced Reports
                    </button>
                </div>
            </div>
        </div>

        <!-- Real-time Status Indicator -->
        <div id="statusIndicator" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 hidden">
            <i class="fas fa-check-circle mr-2"></i>Data refreshed successfully
        </div>

        <!-- Platform Overview Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Revenue -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Total Revenue</h3>
                        <p class="text-2xl font-bold text-green-600">
                            $<?= number_format($platformData['platform_overview']['total_revenue'] ?? 0, 2) ?>
                        </p>
                        <p class="text-sm text-gray-600 mt-1">Platform-wide</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-dollar-sign text-green-600"></i>
                    </div>
                </div>
            </div>

            <!-- Total Users -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Total Users</h3>
                        <p class="text-2xl font-bold text-blue-600">
                            <?= number_format($platformData['platform_overview']['total_users'] ?? 0) ?>
                        </p>
                        <p class="text-sm text-gray-600 mt-1">
                            <?= number_format($platformData['platform_overview']['total_customers'] ?? 0) ?> customers, 
                            <?= number_format($platformData['platform_overview']['total_merchants'] ?? 0) ?> merchants
                        </p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                </div>
            </div>

            <!-- Total Orders -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Total Orders</h3>
                        <p class="text-2xl font-bold text-purple-600">
                            <?= number_format($platformData['platform_overview']['total_orders'] ?? 0) ?>
                        </p>
                        <p class="text-sm text-gray-600 mt-1">This period</p>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-shopping-cart text-purple-600"></i>
                    </div>
                </div>
            </div>

            <!-- Commission Collected -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Commission Collected</h3>
                        <p class="text-2xl font-bold text-orange-600">
                            $<?= number_format($platformData['platform_overview']['total_commission_collected'] ?? 0, 2) ?>
                        </p>
                        <p class="text-sm text-gray-600 mt-1">Platform earnings</p>
                    </div>
                    <div class="p-3 bg-orange-100 rounded-full">
                        <i class="fas fa-percentage text-orange-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Growth and Financial Summary -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- User Growth Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">User Growth Trends</h3>
                <div class="h-80">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </div>

            <!-- Financial Summary -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Financial Summary</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <div>
                            <h4 class="font-medium text-gray-900">Total Processed</h4>
                            <p class="text-sm text-gray-600">Payment volume</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-gray-900">
                                $<?= number_format($platformData['financial_summary']['total_processed'] ?? 0, 2) ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                <?= number_format($platformData['financial_summary']['transaction_count'] ?? 0) ?> transactions
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-between items-center p-4 bg-green-50 rounded-lg">
                        <div>
                            <h4 class="font-medium text-gray-900">Platform Fees</h4>
                            <p class="text-sm text-gray-600">Payment processing fees</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-green-600">
                                $<?= number_format($platformData['financial_summary']['platform_fees_collected'] ?? 0, 2) ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-between items-center p-4 bg-blue-50 rounded-lg">
                        <div>
                            <h4 class="font-medium text-gray-900">Commission Revenue</h4>
                            <p class="text-sm text-gray-600">Marketplace commissions</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-blue-600">
                                $<?= number_format($platformData['financial_summary']['commission_collected'] ?? 0, 2) ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-between items-center p-4 bg-purple-50 rounded-lg border-2 border-purple-200">
                        <div>
                            <h4 class="font-medium text-gray-900">Average Transaction</h4>
                            <p class="text-sm text-gray-600">Per transaction value</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-purple-600">
                                $<?= number_format($platformData['financial_summary']['avg_transaction_size'] ?? 0, 2) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Performance and Top Merchants -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Category Performance -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Category Performance</h3>
                <div class="h-80">
                    <canvas id="categoryPerformanceChart"></canvas>
                </div>
            </div>

            <!-- Top Merchants -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Performing Merchants</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Merchant</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Orders</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rating</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach (array_slice($platformData['merchant_analytics'] ?? [], 0, 8) as $merchant): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($merchant['name'] ?: $merchant['email']) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= $merchant['total_products'] ?> products
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        $<?= number_format($merchant['total_revenue'] ?? 0, 2) ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        <?= number_format($merchant['total_orders'] ?? 0) ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        <div class="flex items-center">
                                            <span><?= number_format($merchant['average_rating'] ?? 0, 1) ?></span>
                                            <div class="ml-1 flex">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star text-xs <?= $i <= ($merchant['average_rating'] ?? 0) ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
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
        </div>

        <!-- Security and System Health -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Security Metrics -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Security Overview</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center p-4 bg-red-50 rounded-lg">
                        <div class="text-2xl font-bold text-red-600">
                            <?= number_format($platformData['security_metrics']['failed_logins'] ?? 0) ?>
                        </div>
                        <div class="text-sm text-gray-600">Failed Logins</div>
                    </div>
                    <div class="text-center p-4 bg-orange-50 rounded-lg">
                        <div class="text-2xl font-bold text-orange-600">
                            <?= number_format($platformData['security_metrics']['blocked_ips'] ?? 0) ?>
                        </div>
                        <div class="text-sm text-gray-600">Blocked IPs</div>
                    </div>
                    <div class="text-center p-4 bg-yellow-50 rounded-lg">
                        <div class="text-2xl font-bold text-yellow-600">
                            <?= number_format($platformData['security_metrics']['csrf_violations'] ?? 0) ?>
                        </div>
                        <div class="text-sm text-gray-600">CSRF Violations</div>
                    </div>
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600">
                            <?= number_format($platformData['security_metrics']['api_errors'] ?? 0) ?>
                        </div>
                        <div class="text-sm text-gray-600">API Errors</div>
                    </div>
                </div>
            </div>

            <!-- System Health -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">System Health</h3>
                <div class="space-y-4">
                    <!-- Uptime -->
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">System Uptime</span>
                        <div class="flex items-center">
                            <span class="font-semibold text-green-600">
                                <?= number_format($platformData['system_health']['uptime_percentage'] ?? 99.9, 1) ?>%
                            </span>
                            <div class="ml-2 w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" 
                                     style="width: <?= $platformData['system_health']['uptime_percentage'] ?? 99.9 ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Response Time -->
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Avg Response Time</span>
                        <span class="font-semibold <?= ($platformData['system_health']['avg_response_time'] ?? 250) < 500 ? 'text-green-600' : 'text-yellow-600' ?>">
                            <?= number_format($platformData['system_health']['avg_response_time'] ?? 250) ?>ms
                        </span>
                    </div>

                    <!-- Error Rate -->
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Error Rate</span>
                        <span class="font-semibold <?= ($platformData['system_health']['error_rate'] ?? 0.1) < 1 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= number_format($platformData['system_health']['error_rate'] ?? 0.1, 2) ?>%
                        </span>
                    </div>

                    <!-- Active Sessions -->
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Active Sessions</span>
                        <span class="font-semibold text-blue-600">
                            <?= number_format($platformData['system_health']['active_sessions'] ?? 0) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Reports Modal -->
    <div id="advancedReportsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-4xl w-full p-6 max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Advanced Analytics Reports</h3>
                    <button onclick="closeAdvancedReports()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Cohort Analysis -->
                    <div class="border rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-2">Cohort Analysis</h4>
                        <p class="text-sm text-gray-600 mb-4">Analyze user retention over time</p>
                        <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            Generate Report
                        </button>
                    </div>

                    <!-- A/B Testing -->
                    <div class="border rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-2">A/B Testing Results</h4>
                        <p class="text-sm text-gray-600 mb-4">View experiment outcomes</p>
                        <button class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                            View Tests
                        </button>
                    </div>

                    <!-- Revenue Attribution -->
                    <div class="border rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-2">Revenue Attribution</h4>
                        <p class="text-sm text-gray-600 mb-4">Track revenue sources</p>
                        <button class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                            View Attribution
                        </button>
                    </div>

                    <!-- Custom Reports -->
                    <div class="border rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-2">Custom Reports</h4>
                        <p class="text-sm text-gray-600 mb-4">Build custom analytics</p>
                        <button class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700">
                            Report Builder
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let platformData = <?= json_encode($platformData) ?>;

        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowthData = platformData.user_growth || [];
        
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: userGrowthData.map(item => new Date(item.date).toLocaleDateString()),
                datasets: [
                    {
                        label: 'New Users',
                        data: userGrowthData.map(item => item.new_users),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'New Customers',
                        data: userGrowthData.map(item => item.new_customers),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'New Merchants',
                        data: userGrowthData.map(item => item.new_merchants),
                        borderColor: 'rgb(168, 85, 247)',
                        backgroundColor: 'rgba(168, 85, 247, 0.1)',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Number of Users' }
                    }
                }
            }
        });

        // Category Performance Chart
        const categoryCtx = document.getElementById('categoryPerformanceChart').getContext('2d');
        const categoryData = platformData.category_performance || [];
        
        new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: categoryData.map(item => item.category),
                datasets: [{
                    label: 'Revenue',
                    data: categoryData.map(item => item.revenue),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Revenue ($)' }
                    }
                }
            }
        });

        // Utility functions
        function changePeriod() {
            const period = document.getElementById('periodSelector').value;
            window.location.href = `?period=${period}`;
        }

        function refreshData() {
            const btn = document.getElementById('refreshBtn');
            const icon = btn.querySelector('i');
            
            btn.disabled = true;
            icon.classList.add('fa-spin');
            
            fetch(`?ajax=refresh&period=${document.getElementById('periodSelector').value}`)
                .then(response => response.json())
                .then(data => {
                    platformData = data;
                    showStatusMessage('Data refreshed successfully', 'success');
                    // Update charts and metrics here
                })
                .catch(error => {
                    showStatusMessage('Failed to refresh data', 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    icon.classList.remove('fa-spin');
                });
        }

        function showStatusMessage(message, type) {
            const indicator = document.getElementById('statusIndicator');
            indicator.className = `px-4 py-3 rounded mb-6 ${type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'}`;
            indicator.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>${message}`;
            indicator.classList.remove('hidden');
            
            setTimeout(() => {
                indicator.classList.add('hidden');
            }, 5000);
        }

        function openAdvancedReports() {
            document.getElementById('advancedReportsModal').classList.remove('hidden');
        }

        function closeAdvancedReports() {
            document.getElementById('advancedReportsModal').classList.add('hidden');
        }

        // Auto-refresh every 5 minutes
        setInterval(refreshData, 300000);
    </script>
</body>
</html>