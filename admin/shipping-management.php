<?php
require_once '../config/database.php';
require_once '../classes/ShippingCalculator.php';
require_once '../classes/GeographicalManager.php';

// Require admin login
requireRole('admin');

$shippingCalc = new ShippingCalculator($pdo);
$geoManager = new GeographicalManager($pdo);

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_provider') {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $trackingUrl = trim($_POST['tracking_url'] ?? '');
        $maxWeight = floatval($_POST['max_weight'] ?? 0);
        $maxDimensions = trim($_POST['max_dimensions'] ?? '');
        $supportsIntl = isset($_POST['supports_international']);
        
        if ($name && $code) {
            $stmt = $pdo->prepare("
                INSERT INTO shipping_providers (name, code, tracking_url_template, max_weight_kg, max_dimensions_cm, supports_international)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$name, $code, $trackingUrl, $maxWeight, $maxDimensions, $supportsIntl])) {
                $success = 'Shipping provider added successfully!';
            } else {
                $error = 'Failed to add shipping provider.';
            }
        } else {
            $error = 'Please fill in all required fields.';
        }
    } elseif ($action === 'add_service') {
        $providerId = intval($_POST['provider_id'] ?? 0);
        $name = trim($_POST['service_name'] ?? '');
        $code = trim($_POST['service_code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $minDays = intval($_POST['min_days'] ?? 1);
        $maxDays = intval($_POST['max_days'] ?? 5);
        
        if ($providerId && $name && $code) {
            $stmt = $pdo->prepare("
                INSERT INTO shipping_services (provider_id, name, code, description, estimated_days_min, estimated_days_max)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$providerId, $name, $code, $description, $minDays, $maxDays])) {
                $success = 'Shipping service added successfully!';
            } else {
                $error = 'Failed to add shipping service.';
            }
        } else {
            $error = 'Please fill in all required fields.';
        }
    } elseif ($action === 'add_rate_rule') {
        $providerId = intval($_POST['provider_id'] ?? 0);
        $serviceId = intval($_POST['service_id'] ?? 0);
        $zoneId = intval($_POST['zone_id'] ?? 0);
        $weightMin = floatval($_POST['weight_min'] ?? 0);
        $weightMax = floatval($_POST['weight_max'] ?? 999);
        $baseCost = floatval($_POST['base_cost'] ?? 0);
        $costPerKg = floatval($_POST['cost_per_kg'] ?? 0);
        $freeThreshold = floatval($_POST['free_threshold'] ?? 0) ?: null;
        
        if ($providerId && $serviceId && $zoneId && $baseCost > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO shipping_rate_rules (provider_id, service_id, zone_id, weight_min_kg, weight_max_kg, base_cost, cost_per_kg, free_shipping_threshold)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$providerId, $serviceId, $zoneId, $weightMin, $weightMax, $baseCost, $costPerKg, $freeThreshold])) {
                $success = 'Shipping rate rule added successfully!';
            } else {
                $error = 'Failed to add shipping rate rule.';
            }
        } else {
            $error = 'Please fill in all required fields.';
        }
    } elseif ($action === 'update_currency_rates') {
        // Sample currency rate update (in real app, this would fetch from an API)
        $rates = [
            ['from' => 'USD', 'to' => 'CAD', 'rate' => 1.35],
            ['from' => 'USD', 'to' => 'EUR', 'rate' => 0.85],
            ['from' => 'USD', 'to' => 'GBP', 'rate' => 0.73],
            ['from' => 'USD', 'to' => 'JPY', 'rate' => 110.00],
        ];
        
        if ($geoManager->updateCurrencyRates($rates)) {
            $success = 'Currency rates updated successfully!';
        } else {
            $error = 'Failed to update currency rates.';
        }
    }
}

// Get data for display
$providers = $pdo->query("SELECT * FROM shipping_providers ORDER BY name")->fetchAll();
$services = $pdo->query("
    SELECT ss.*, sp.name as provider_name 
    FROM shipping_services ss 
    JOIN shipping_providers sp ON ss.provider_id = sp.id 
    ORDER BY sp.name, ss.name
")->fetchAll();
$zones = $geoManager->getShippingZones();
$countries = $geoManager->getCountries();

// Get rate rules
$rateRules = $pdo->query("
    SELECT srr.*, sp.name as provider_name, ss.name as service_name, sz.name as zone_name
    FROM shipping_rate_rules srr
    JOIN shipping_providers sp ON srr.provider_id = sp.id
    JOIN shipping_services ss ON srr.service_id = ss.id
    JOIN shipping_zones sz ON srr.zone_id = sz.id
    ORDER BY sp.name, ss.name, sz.name
")->fetchAll();

// Get recent shipments
$recentShipments = $pdo->query("
    SELECT s.*, sp.name as provider_name, ss.name as service_name
    FROM shipments s
    JOIN shipping_providers sp ON s.provider_id = sp.id
    JOIN shipping_services ss ON s.service_id = ss.id
    ORDER BY s.created_at DESC
    LIMIT 10
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Management - VentDepot Admin</title>
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
                <h1 class="text-3xl font-bold text-gray-900">Shipping & Geographical Management</h1>
                <p class="text-gray-600 mt-2">Manage shipping providers, rates, and geographical data</p>
            </div>
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

        <!-- Tabs -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden" x-data="{ activeTab: 'providers' }">
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6">
                    <button @click="activeTab = 'providers'" 
                            :class="activeTab === 'providers' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-truck mr-2"></i>Providers
                    </button>
                    <button @click="activeTab = 'services'" 
                            :class="activeTab === 'services' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-shipping-fast mr-2"></i>Services
                    </button>
                    <button @click="activeTab = 'rates'" 
                            :class="activeTab === 'rates' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-dollar-sign mr-2"></i>Rate Rules
                    </button>
                    <button @click="activeTab = 'zones'" 
                            :class="activeTab === 'zones' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-globe mr-2"></i>Zones
                    </button>
                    <button @click="activeTab = 'tracking'" 
                            :class="activeTab === 'tracking' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-search mr-2"></i>Tracking
                    </button>
                    <button @click="activeTab = 'currency'" 
                            :class="activeTab === 'currency' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-exchange-alt mr-2"></i>Currency
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Providers Tab -->
                <div x-show="activeTab === 'providers'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Shipping Providers</h3>
                        <button onclick="document.getElementById('addProviderModal').classList.remove('hidden')"
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Provider
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provider</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Max Weight</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">International</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($providers as $provider): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($provider['name']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($provider['code']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= $provider['max_weight_kg'] ?>kg
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($provider['supports_international']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Yes
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    No
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($provider['is_active']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Services Tab -->
                <div x-show="activeTab === 'services'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Shipping Services</h3>
                        <button onclick="document.getElementById('addServiceModal').classList.remove('hidden')"
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Service
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provider</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Delivery Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($service['provider_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($service['name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($service['description']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($service['code']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= $service['estimated_days_min'] ?>-<?= $service['estimated_days_max'] ?> days
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($service['is_active']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Rate Rules Tab -->
                <div x-show="activeTab === 'rates'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Shipping Rate Rules</h3>
                        <button onclick="document.getElementById('addRateModal').classList.remove('hidden')"
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Rate Rule
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provider/Service</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zone</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Weight Range</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Base Cost</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Per KG</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Free Shipping</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($rateRules as $rule): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($rule['provider_name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($rule['service_name']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($rule['zone_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= $rule['weight_min_kg'] ?>kg - <?= $rule['weight_max_kg'] ?>kg
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            $<?= number_format($rule['base_cost'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            $<?= number_format($rule['cost_per_kg'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= $rule['free_shipping_threshold'] ? '$' . number_format($rule['free_shipping_threshold'], 2) : 'N/A' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Zones Tab -->
                <div x-show="activeTab === 'zones'" class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900">Shipping Zones</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($zones as $zone): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 mb-2"><?= htmlspecialchars($zone['name']) ?></h4>
                                <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars($zone['description']) ?></p>
                                <div class="text-xs text-gray-500">
                                    <strong>Countries:</strong> <?= htmlspecialchars($zone['countries']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tracking Tab -->
                <div x-show="activeTab === 'tracking'" class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Shipments</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provider</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recentShipments as $shipment): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($shipment['tracking_number']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            #<?= str_pad($shipment['order_id'], 6, '0', STR_PAD_LEFT) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($shipment['provider_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php
                                                switch($shipment['status']) {
                                                    case 'created': echo 'bg-gray-100 text-gray-800'; break;
                                                    case 'picked_up': echo 'bg-blue-100 text-blue-800'; break;
                                                    case 'in_transit': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                                    case 'exception': echo 'bg-red-100 text-red-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?= ucfirst(str_replace('_', ' ', $shipment['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= date('M j, Y', strtotime($shipment['created_at'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Currency Tab -->
                <div x-show="activeTab === 'currency'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Currency Exchange Rates</h3>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="update_currency_rates">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                <i class="fas fa-sync mr-2"></i>Update Rates
                            </button>
                        </form>
                    </div>
                    
                    <?php $currencyRates = $geoManager->getCurrencyRates(); ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($currencyRates as $rate): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-medium text-gray-900"><?= $rate['from_currency'] ?> → <?= $rate['to_currency'] ?></p>
                                        <p class="text-2xl font-bold text-blue-600"><?= number_format($rate['rate'], 4) ?></p>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Updated: <?= date('M j, Y', strtotime($rate['updated_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Provider Modal -->
    <div id="addProviderModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Shipping Provider</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_provider">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Provider Name</label>
                            <input type="text" name="name" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Provider Code</label>
                            <input type="text" name="code" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tracking URL Template</label>
                            <input type="url" name="tracking_url"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="https://example.com/track/{tracking_number}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Max Weight (kg)</label>
                            <input type="number" name="max_weight" step="0.1"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Max Dimensions (cm)</label>
                            <input type="text" name="max_dimensions"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="100x100x100">
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="supports_international" id="supports_international"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="supports_international" class="ml-2 text-sm text-gray-700">
                                Supports International Shipping
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="document.getElementById('addProviderModal').classList.add('hidden')"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            Add Provider
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Service Modal -->
    <div id="addServiceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Shipping Service</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_service">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Provider</label>
                            <select name="provider_id" required
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Provider</option>
                                <?php foreach ($providers as $provider): ?>
                                    <option value="<?= $provider['id'] ?>"><?= htmlspecialchars($provider['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Service Name</label>
                            <input type="text" name="service_name" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Service Code</label>
                            <input type="text" name="service_code" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="3"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Min Days</label>
                                <input type="number" name="min_days" min="0" required
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Max Days</label>
                                <input type="number" name="max_days" min="1" required
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="document.getElementById('addServiceModal').classList.add('hidden')"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            Add Service
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Rate Rule Modal -->
    <div id="addRateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Rate Rule</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_rate_rule">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Provider</label>
                            <select name="provider_id" required
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Provider</option>
                                <?php foreach ($providers as $provider): ?>
                                    <option value="<?= $provider['id'] ?>"><?= htmlspecialchars($provider['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Service</label>
                            <select name="service_id" required
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Service</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= $service['id'] ?>"><?= htmlspecialchars($service['provider_name'] . ' - ' . $service['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Zone</label>
                            <select name="zone_id" required
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Zone</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Min Weight (kg)</label>
                                <input type="number" name="weight_min" step="0.001" min="0" required
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Max Weight (kg)</label>
                                <input type="number" name="weight_max" step="0.001" min="0" required
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Base Cost ($)</label>
                                <input type="number" name="base_cost" step="0.01" min="0" required
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cost per KG ($)</label>
                                <input type="number" name="cost_per_kg" step="0.01" min="0"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Free Shipping Threshold ($)</label>
                            <input type="number" name="free_threshold" step="0.01" min="0"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="document.getElementById('addRateModal').classList.add('hidden')"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            Add Rate Rule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-populate services based on selected provider
        document.addEventListener('DOMContentLoaded', function() {
            const providerSelects = document.querySelectorAll('select[name="provider_id"]');

            providerSelects.forEach(select => {
                select.addEventListener('change', function() {
                    const providerId = this.value;
                    const serviceSelect = this.closest('form').querySelector('select[name="service_id"]');

                    if (serviceSelect && providerId) {
                        // Filter services by provider
                        const services = <?= json_encode($services) ?>;
                        serviceSelect.innerHTML = '<option value="">Select Service</option>';

                        services.forEach(service => {
                            if (service.provider_id == providerId) {
                                const option = document.createElement('option');
                                option.value = service.id;
                                option.textContent = service.name;
                                serviceSelect.appendChild(option);
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
