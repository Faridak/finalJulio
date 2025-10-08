<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_shipping_info') {
        $domesticInfo = trim($_POST['domestic_info'] ?? '');
        $internationalInfo = trim($_POST['international_info'] ?? '');
        $processingTime = trim($_POST['processing_time'] ?? '');
        $shippingRates = trim($_POST['shipping_rates'] ?? '');
        $restrictions = trim($_POST['restrictions'] ?? '');
        $trackingInfo = trim($_POST['tracking_info'] ?? '');
        
        // Update shipping information
        $stmt = $pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value) VALUES 
            ('shipping_domestic_info', ?),
            ('shipping_international_info', ?),
            ('shipping_processing_time', ?),
            ('shipping_rates_info', ?),
            ('shipping_restrictions', ?),
            ('shipping_tracking_info', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        if ($stmt->execute([$domesticInfo, $internationalInfo, $processingTime, $shippingRates, $restrictions, $trackingInfo])) {
            $success = 'Shipping information updated successfully!';
        } else {
            $error = 'Failed to update shipping information.';
        }
    }
}

// Get current shipping information
$shippingInfo = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'shipping_%'");
while ($row = $stmt->fetch()) {
    $shippingInfo[$row['setting_key']] = $row['setting_value'];
}

// Get shipping statistics
$stats = [
    'total_shipments' => $pdo->query("SELECT COUNT(*) FROM shipments")->fetchColumn(),
    'pending_shipments' => $pdo->query("SELECT COUNT(*) FROM shipments WHERE status IN ('created', 'picked_up', 'in_transit')")->fetchColumn(),
    'delivered_shipments' => $pdo->query("SELECT COUNT(*) FROM shipments WHERE status = 'delivered'")->fetchColumn(),
    'active_providers' => $pdo->query("SELECT COUNT(*) FROM shipping_providers WHERE is_active = TRUE")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Information Management - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
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
                <h1 class="text-3xl font-bold text-gray-900">Shipping Information Management</h1>
                <p class="text-gray-600 mt-2">Manage shipping policies and information displayed to customers</p>
            </div>
            <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Shipping Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-box text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Shipments</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_shipments']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-truck text-yellow-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">In Transit</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['pending_shipments']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Delivered</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['delivered_shipments']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shipping-fast text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Providers</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['active_providers'] ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shipping Information Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Shipping Information Content</h2>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="update_shipping_info">
                
                <div>
                    <label for="processing_time" class="block text-sm font-medium text-gray-700 mb-2">Processing Time</label>
                    <textarea name="processing_time" id="processing_time" rows="3"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                              placeholder="Orders are typically processed within 1-2 business days..."><?= htmlspecialchars($shippingInfo['shipping_processing_time'] ?? '') ?></textarea>
                </div>
                
                <div>
                    <label for="domestic_info" class="block text-sm font-medium text-gray-700 mb-2">Domestic Shipping Information</label>
                    <textarea name="domestic_info" id="domestic_info" rows="5"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                              placeholder="Information about domestic shipping options, rates, and delivery times..."><?= htmlspecialchars($shippingInfo['shipping_domestic_info'] ?? '') ?></textarea>
                </div>
                
                <div>
                    <label for="international_info" class="block text-sm font-medium text-gray-700 mb-2">International Shipping Information</label>
                    <textarea name="international_info" id="international_info" rows="5"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                              placeholder="Information about international shipping options, customs, and delivery times..."><?= htmlspecialchars($shippingInfo['shipping_international_info'] ?? '') ?></textarea>
                </div>
                
                <div>
                    <label for="shipping_rates" class="block text-sm font-medium text-gray-700 mb-2">Shipping Rates Information</label>
                    <textarea name="shipping_rates" id="shipping_rates" rows="4"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                              placeholder="Information about how shipping rates are calculated..."><?= htmlspecialchars($shippingInfo['shipping_rates_info'] ?? '') ?></textarea>
                </div>
                
                <div>
                    <label for="restrictions" class="block text-sm font-medium text-gray-700 mb-2">Shipping Restrictions</label>
                    <textarea name="restrictions" id="restrictions" rows="4"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                              placeholder="Information about shipping restrictions, prohibited items, etc..."><?= htmlspecialchars($shippingInfo['shipping_restrictions'] ?? '') ?></textarea>
                </div>
                
                <div>
                    <label for="tracking_info" class="block text-sm font-medium text-gray-700 mb-2">Tracking Information</label>
                    <textarea name="tracking_info" id="tracking_info" rows="4"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                              placeholder="Information about package tracking and delivery confirmation..."><?= htmlspecialchars($shippingInfo['shipping_tracking_info'] ?? '') ?></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Update Shipping Information
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-cogs text-blue-600 mr-2"></i>Shipping Settings
                </h3>
                <p class="text-gray-600 mb-4">Manage shipping providers, rates, and zones</p>
                <a href="global-shipping-admin.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    Manage Shipping
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-map-marked-alt text-green-600 mr-2"></i>Shipping Zones
                </h3>
                <p class="text-gray-600 mb-4">Configure shipping zones and geographical settings</p>
                <a href="global-shipping-admin.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    Manage Zones
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-chart-line text-purple-600 mr-2"></i>Shipping Reports
                </h3>
                <p class="text-gray-600 mb-4">View shipping analytics and performance metrics</p>
                <a href="reports.php" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                    View Reports
                </a>
            </div>
        </div>

        <!-- Preview Section -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Shipping Information Preview</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>The information you enter here will be displayed on the shipping information page that customers can access from the footer.</p>
                        <p class="mt-1">Make sure to include clear information about:</p>
                        <ul class="list-disc list-inside mt-1 space-y-1">
                            <li>Processing times and cutoff times</li>
                            <li>Available shipping methods and costs</li>
                            <li>International shipping policies</li>
                            <li>Tracking and delivery information</li>
                            <li>Any restrictions or special requirements</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
