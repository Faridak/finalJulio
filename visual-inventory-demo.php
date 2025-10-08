<?php
require_once 'config/database.php';

// Check if the enhanced inventory tables exist
$setupRequired = false;
$tableChecks = [];

$requiredTables = [
    'warehouse_zones' => 'Warehouse zones (areas within warehouse)',
    'storage_racks' => 'Storage racks within zones', 
    'inventory_bins' => 'Individual bin locations',
    'product_bin_assignments' => 'Product-to-bin mappings'
];

foreach ($requiredTables as $table => $description) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->fetch();
        $tableChecks[$table] = ['exists' => !!$exists, 'description' => $description];
        if (!$exists) $setupRequired = true;
    } catch (PDOException $e) {
        $tableChecks[$table] = ['exists' => false, 'description' => $description];
        $setupRequired = true;
    }
}

// Check for sample data
$sampleData = [];
if (!$setupRequired) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM warehouse_zones");
        $stmt->execute();
        $sampleData['zones'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM storage_racks");
        $stmt->execute();
        $sampleData['racks'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_bins");
        $stmt->execute();
        $sampleData['bins'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_locations");
        $stmt->execute();
        $sampleData['locations'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $sampleData = ['zones' => 0, 'racks' => 0, 'bins' => 0, 'locations' => 0];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visual Inventory System Demo - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">
                <i class="fas fa-warehouse text-blue-600 mr-3"></i>
                Visual Inventory Management System
            </h1>
            <p class="text-lg text-gray-600">
                Interactive 3D warehouse visualization with zones, aisles, shelves, and bins
            </p>
        </div>

        <!-- System Status -->
        <div class="bg-white rounded-lg shadow-lg mb-8 p-8">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">
                <i class="fas fa-cogs text-green-600 mr-2"></i>System Status
            </h2>
            
            <?php if (!$setupRequired): ?>
                <!-- System Ready -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                        <h3 class="text-xl font-semibold text-green-800">System Ready!</h3>
                    </div>
                    <p class="text-green-700 mb-4">
                        Your visual inventory management system is fully set up and ready to use.
                    </p>
                    
                    <!-- Sample Data Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <div class="bg-white rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-blue-600"><?= $sampleData['locations'] ?></div>
                            <div class="text-sm text-gray-600">Warehouses</div>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-green-600"><?= $sampleData['zones'] ?></div>
                            <div class="text-sm text-gray-600">Zones</div>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-yellow-600"><?= $sampleData['racks'] ?></div>
                            <div class="text-sm text-gray-600">Racks</div>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-purple-600"><?= $sampleData['bins'] ?></div>
                            <div class="text-sm text-gray-600">Bins</div>
                        </div>
                    </div>
                    
                    <!-- Access Buttons -->
                    <div class="flex flex-wrap gap-4">
                        <a href="admin/inventory-visual.php" 
                           class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors inline-flex items-center">
                            <i class="fas fa-warehouse mr-2"></i>Open Visual Warehouse
                        </a>
                        <a href="admin/inventory.php" 
                           class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors inline-flex items-center">
                            <i class="fas fa-list mr-2"></i>Regular Inventory View
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Setup Required -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl mr-3"></i>
                        <h3 class="text-xl font-semibold text-yellow-800">Setup Required</h3>
                    </div>
                    <p class="text-yellow-700 mb-4">
                        The enhanced inventory database schema needs to be installed before you can use the visual system.
                    </p>
                    
                    <!-- Database Tables Status -->
                    <div class="bg-white rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-gray-800 mb-3">Database Tables Status:</h4>
                        <div class="space-y-2">
                            <?php foreach ($tableChecks as $table => $info): ?>
                                <div class="flex items-center">
                                    <?php if ($info['exists']): ?>
                                        <i class="fas fa-check text-green-600 mr-2"></i>
                                        <span class="text-green-700"><?= $table ?></span>
                                    <?php else: ?>
                                        <i class="fas fa-times text-red-600 mr-2"></i>
                                        <span class="text-red-700"><?= $table ?></span>
                                    <?php endif; ?>
                                    <span class="text-gray-600 ml-2">- <?= $info['description'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <a href="setup-enhanced-inventory.php" 
                       class="bg-yellow-600 text-white px-6 py-3 rounded-lg hover:bg-yellow-700 transition-colors inline-flex items-center">
                        <i class="fas fa-database mr-2"></i>Run Setup Now
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Features Overview -->
        <div class="grid md:grid-cols-2 gap-8 mb-8">
            <!-- Visual Features -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-eye text-blue-600 mr-2"></i>Visual Features
                </h3>
                <ul class="space-y-3 text-gray-700">
                    <li class="flex items-center">
                        <i class="fas fa-cube text-blue-500 mr-3"></i>
                        3D-style warehouse layout visualization
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-layer-group text-blue-500 mr-3"></i>
                        Hierarchical structure: Zone → Rack → Level → Position
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-palette text-blue-500 mr-3"></i>
                        Color-coded bin status indicators
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-mouse-pointer text-blue-500 mr-3"></i>
                        Interactive hover tooltips with details
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-arrows-alt text-blue-500 mr-3"></i>
                        Click-to-select bins for product assignment
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-chart-pie text-blue-500 mr-3"></i>
                        Real-time utilization percentage display
                    </li>
                </ul>
            </div>

            <!-- Management Features -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-cogs text-green-600 mr-2"></i>Management Features
                </h3>
                <ul class="space-y-3 text-gray-700">
                    <li class="flex items-center">
                        <i class="fas fa-map-marker-alt text-green-500 mr-3"></i>
                        Precise bin location tracking (Aisle-Rack-Level-Position)
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-boxes text-green-500 mr-3"></i>
                        Product-to-bin assignment management
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-sync-alt text-green-500 mr-3"></i>
                        Automated cycle counting schedules
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-bell text-green-500 mr-3"></i>
                        Smart reorder point suggestions
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-barcode text-green-500 mr-3"></i>
                        Barcode/QR code generation for bins
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-chart-line text-green-500 mr-3"></i>
                        Advanced analytics and reporting
                    </li>
                </ul>
            </div>
        </div>

        <!-- Quick Tutorial -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h3 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-graduation-cap text-purple-600 mr-2"></i>How to Use the Visual Inventory System
            </h3>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="bg-blue-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-building text-blue-600 text-xl"></i>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-2">1. Select Warehouse</h4>
                    <p class="text-gray-600 text-sm">Choose a warehouse location from the dropdown to load its visual layout</p>
                </div>
                <div class="text-center">
                    <div class="bg-green-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-search text-green-600 text-xl"></i>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-2">2. Explore Layout</h4>
                    <p class="text-gray-600 text-sm">Hover over bins to see details, click zones/racks to drill down into specific areas</p>
                </div>
                <div class="text-center">
                    <div class="bg-purple-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-hand-pointer text-purple-600 text-xl"></i>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-2">3. Manage Products</h4>
                    <p class="text-gray-600 text-sm">Click bins to assign products, move inventory, or update bin information</p>
                </div>
            </div>
        </div>

        <!-- Bin Status Legend -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>Bin Status Color Guide
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-6 bg-gray-100 border border-gray-300 rounded"></div>
                    <span class="text-sm font-medium text-gray-700">Empty</span>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-6 bg-yellow-100 border border-yellow-400 rounded"></div>
                    <span class="text-sm font-medium text-gray-700">Partial</span>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-6 bg-green-100 border border-green-400 rounded"></div>
                    <span class="text-sm font-medium text-gray-700">Full</span>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-6 bg-purple-100 border border-purple-400 rounded"></div>
                    <span class="text-sm font-medium text-gray-700">Reserved</span>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-6 bg-red-100 border border-red-400 rounded"></div>
                    <span class="text-sm font-medium text-gray-700">Blocked</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>