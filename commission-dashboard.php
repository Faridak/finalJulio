<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/CommissionManagementSystem.php';

// Check if user is logged in and is a merchant
if (!isLoggedIn() || $_SESSION['role'] !== 'merchant') {
    header('Location: login.php');
    exit;
}

$commissionSystem = new CommissionManagementSystem($pdo);
$merchantId = $_SESSION['user_id'];

// Get report period
$period = $_GET['period'] ?? '30_days';
$validPeriods = ['7_days', '30_days', '90_days', '1_year'];
if (!in_array($period, $validPeriods)) {
    $period = '30_days';
}

// Get commission report
$report = $commissionSystem->getMerchantCommissionReport($merchantId, $period);
$analytics = $commissionSystem->getCommissionAnalytics($period);

// Get commission scenarios for volume projection
$projectedVolume = floatval($_GET['projection'] ?? ($report['summary']['total_gross'] * 2));
$scenarios = $commissionSystem->calculateCommissionScenarios($merchantId, $projectedVolume);

// Format period name
$periodNames = [
    '7_days' => 'Last 7 Days',
    '30_days' => 'Last 30 Days', 
    '90_days' => 'Last 90 Days',
    '1_year' => 'Last Year'
];
$periodName = $periodNames[$period];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Dashboard - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Commission Dashboard</h1>
                    <p class="text-gray-600 mt-1">Track your earnings and commission structure</p>
                </div>
                
                <div class="mt-4 md:mt-0 flex items-center space-x-4">
                    <!-- Period Selector -->
                    <select onchange="window.location.href='commission-dashboard.php?period=' + this.value" 
                            class="border border-gray-300 rounded px-3 py-2">
                        <?php foreach ($periodNames as $key => $name): ?>
                            <option value="<?= $key ?>" <?= $period === $key ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <!-- Current Tier Badge -->
                    <?php if ($report['current_tier']): ?>
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full 
                                <?= match($report['current_tier']['tier_name']) {
                                    'Platinum' => 'bg-purple-100 text-purple-800',
                                    'Gold' => 'bg-yellow-100 text-yellow-800',
                                    'Silver' => 'bg-gray-100 text-gray-800',
                                    'Bronze' => 'bg-orange-100 text-orange-800',
                                    default => 'bg-blue-100 text-blue-800'
                                } ?>">
                                <i class="fas fa-crown mr-1"></i>
                                <?= htmlspecialchars($report['current_tier']['tier_name']) ?> Tier
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-dollar-sign text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            $<?= number_format($report['summary']['total_net'], 2) ?>
                        </h3>
                        <p class="text-gray-600 text-sm">Net Earnings</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-chart-line text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            $<?= number_format($report['summary']['total_gross'], 2) ?>
                        </h3>
                        <p class="text-gray-600 text-sm">Gross Sales</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-percentage text-purple-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <?= number_format($report['summary']['avg_commission_rate'] * 100, 2) ?>%
                        </h3>
                        <p class="text-gray-600 text-sm">Avg Commission Rate</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-orange-100 rounded-full">
                        <i class="fas fa-shopping-cart text-orange-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <?= number_format($report['summary']['total_orders']) ?>
                        </h3>
                        <p class="text-gray-600 text-sm">Total Orders</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Fee Breakdown -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Fee Breakdown (<?= $periodName ?>)</h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Gross Sales</span>
                        <span class="font-medium">$<?= number_format($report['summary']['total_gross'], 2) ?></span>
                    </div>
                    
                    <hr class="border-gray-200">
                    
                    <div class="flex justify-between items-center text-red-600">
                        <span>Commission (<?= number_format($report['summary']['avg_commission_rate'] * 100, 2) ?>%)</span>
                        <span>-$<?= number_format($report['summary']['total_commission'], 2) ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center text-red-600">
                        <span>Platform Fees</span>
                        <span>-$<?= number_format($report['summary']['total_platform_fees'], 2) ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center text-red-600">
                        <span>Payment Processing</span>
                        <span>-$<?= number_format($report['summary']['total_processing_fees'], 2) ?></span>
                    </div>
                    
                    <hr class="border-gray-200">
                    
                    <div class="flex justify-between items-center font-semibold text-lg">
                        <span class="text-gray-900">Net Earnings</span>
                        <span class="text-green-600">$<?= number_format($report['summary']['total_net'], 2) ?></span>
                    </div>
                </div>
                
                <!-- Savings/Discounts -->
                <?php if ($report['summary']['avg_volume_discount'] > 0 || $report['summary']['avg_performance_adjustment'] < 0): ?>
                    <div class="mt-6 p-4 bg-green-50 rounded-lg">
                        <h3 class="font-medium text-green-900 mb-2">Your Savings</h3>
                        <div class="text-sm text-green-800 space-y-1">
                            <?php if ($report['summary']['avg_volume_discount'] > 0): ?>
                                <div>Volume Discount: <?= number_format($report['summary']['avg_volume_discount'] * 100, 2) ?>%</div>
                            <?php endif; ?>
                            <?php if ($report['summary']['avg_performance_adjustment'] < 0): ?>
                                <div>Performance Bonus: <?= number_format(abs($report['summary']['avg_performance_adjustment']) * 100, 2) ?>%</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tier Progress -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Tier Progress</h2>
                
                <?php if ($report['current_tier']): ?>
                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">Current Tier</span>
                            <span class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($report['current_tier']['tier_name']) ?>
                            </span>
                        </div>
                        <div class="text-sm text-gray-600">
                            Commission Rate: <?= number_format($report['current_tier']['commission_rate'] * 100, 2) ?>%
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($report['next_tier_requirements']): ?>
                    <div class="space-y-4">
                        <h3 class="font-medium text-gray-900">Next Tier: <?= htmlspecialchars($report['next_tier_requirements']['tier_name']) ?></h3>
                        
                        <?php
                        $currentVolume = $report['summary']['total_gross'] ?? 0;
                        $currentOrders = $report['summary']['total_orders'] ?? 0;
                        $requiredVolume = $report['next_tier_requirements']['min_volume'];
                        $requiredOrders = $report['next_tier_requirements']['min_orders'];
                        $volumeProgress = min(100, ($currentVolume / $requiredVolume) * 100);
                        $ordersProgress = min(100, ($currentOrders / $requiredOrders) * 100);
                        ?>
                        
                        <!-- Volume Progress -->
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm text-gray-600">Sales Volume</span>
                                <span class="text-sm text-gray-900">
                                    $<?= number_format($currentVolume) ?> / $<?= number_format($requiredVolume) ?>
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $volumeProgress ?>%"></div>
                            </div>
                        </div>
                        
                        <!-- Orders Progress -->
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm text-gray-600">Order Count</span>
                                <span class="text-sm text-gray-900">
                                    <?= number_format($currentOrders) ?> / <?= number_format($requiredOrders) ?>
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: <?= $ordersProgress ?>%"></div>
                            </div>
                        </div>
                        
                        <?php if ($report['potential_savings']): ?>
                            <div class="p-3 bg-blue-50 rounded-lg">
                                <div class="text-sm text-blue-800">
                                    <div class="font-medium">Potential Monthly Savings:</div>
                                    <div>$<?= number_format($report['potential_savings']['monthly_savings'], 2) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-gray-500">
                        <i class="fas fa-crown text-3xl mb-2"></i>
                        <p>You're at the highest tier!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Commission Scenarios -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-900">Commission Calculator</h2>
                <div class="flex items-center space-x-2">
                    <label class="text-sm text-gray-600">Projected Volume:</label>
                    <input type="number" id="projectedVolume" value="<?= $projectedVolume ?>" 
                           class="border border-gray-300 rounded px-3 py-1 w-32"
                           onchange="updateProjection()">
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission Rate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform Fee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Fees</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Eligible</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($scenarios as $scenario): ?>
                            <tr class="<?= $scenario['tier'] === $report['current_tier']['tier_name'] ? 'bg-blue-50' : '' ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <?php if ($scenario['tier'] === $report['current_tier']['tier_name']): ?>
                                            <i class="fas fa-star text-blue-600 mr-2"></i>
                                        <?php endif; ?>
                                        <span class="font-medium text-gray-900"><?= $scenario['tier'] ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= number_format($scenario['final_rate'] * 100, 2) ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    $<?= number_format($scenario['commission'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    $<?= number_format($scenario['platform_fee'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    $<?= number_format($scenario['total_fees'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                    $<?= number_format($scenario['merchant_net'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($scenario['requirements_met']): ?>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-1"></i>Yes
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                            <i class="fas fa-times mr-1"></i>No
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Commissions -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold text-gray-900">Recent Commissions</h2>
            </div>
            
            <?php if (!empty($report['commissions'])): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission Rate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach (array_slice($report['commissions'], 0, 20) as $commission): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        #<?= $commission['order_id'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= date('M j, Y', strtotime($commission['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        $<?= number_format($commission['gross_amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= number_format($commission['commission_rate'] * 100, 2) ?>%
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        $<?= number_format($commission['commission_amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                        $<?= number_format($commission['net_amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full 
                                            <?= $commission['status'] === 'paid_out' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= ucfirst(str_replace('_', ' ', $commission['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-8 text-center">
                    <i class="fas fa-chart-line text-gray-300 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Commission Data</h3>
                    <p class="text-gray-600">Start selling to see your commission breakdown here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateProjection() {
            const volume = document.getElementById('projectedVolume').value;
            if (volume && volume > 0) {
                window.location.href = `commission-dashboard.php?period=<?= $period ?>&projection=${volume}`;
            }
        }
    </script>
</body>
</html>