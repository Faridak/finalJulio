<?php
require_once 'config/database.php';

$success = '';
$error = '';
$setupComplete = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_schema'])) {
    try {
        // Read and execute the enhanced inventory schema
        $schemaFile = __DIR__ . '/enhanced_inventory_schema.sql';
        
        if (!file_exists($schemaFile)) {
            throw new Exception("Enhanced inventory schema file not found: $schemaFile");
        }
        
        $sql = file_get_contents($schemaFile);
        
        // Execute the entire SQL as one block to maintain integrity
        $pdo->exec($sql);
        
        $success = "Enhanced inventory schema has been successfully installed!";
        $setupComplete = true;
        
    } catch (Exception $e) {
        $error = "Error setting up enhanced inventory schema: " . $e->getMessage();
        
        // Provide specific guidance for common errors
        if (strpos($e->getMessage(), "doesn't exist") !== false) {
            $error .= "<br><br><strong>Troubleshooting:</strong><br>";
            $error .= "• Ensure all basic inventory tables exist<br>";
            $error .= "• Try running the basic database setup first<br>";
            $error .= "• Check that XAMPP MySQL is running<br>";
        }
    }
}

// Check if schema is already installed
$tablesExist = true;
$requiredTables = [
    'warehouse_zones',
    'storage_racks', 
    'inventory_bins',
    'product_bin_assignments',
    'cycle_count_plans',
    'reorder_rules',
    'reorder_suggestions',
    'vendor_contacts',
    'vendor_communication_log'
];

$existingTables = [];
foreach ($requiredTables as $table) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            $existingTables[] = $table;
        } else {
            $tablesExist = false;
        }
    } catch (PDOException $e) {
        $tablesExist = false;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Inventory Setup - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Enhanced Inventory Management Setup</h1>
            <p class="text-gray-600">Set up visual warehouse management with zones, racks, and bins</p>
        </div>

        <!-- Success/Error Messages -->
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

        <!-- Setup Card -->
        <div class="bg-white rounded-lg shadow-lg p-8">
            <?php if ($tablesExist || $setupComplete): ?>
                <!-- Already Installed -->
                <div class="text-center">
                    <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Enhanced Inventory System Ready!</h2>
                    <p class="text-gray-600 mb-6">All required database tables are installed and ready to use.</p>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
                        <?php foreach ($existingTables as $table): ?>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                <i class="fas fa-table text-green-600 mr-2"></i>
                                <span class="text-sm font-medium text-green-800"><?= $table ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="space-y-4">
                        <a href="admin/inventory-visual.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-warehouse mr-2"></i>Open Visual Warehouse Manager
                        </a>
                        <br>
                        <a href="enhanced-inventory-dashboard.php" class="inline-block bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-dashboard mr-2"></i>Open Enhanced Inventory Dashboard
                        </a>
                        <br>
                        <a href="admin/inventory.php" class="inline-block bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Regular Inventory
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Setup Required -->
                <div class="text-center">
                    <i class="fas fa-database text-6xl text-blue-500 mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Enhanced Inventory Setup Required</h2>
                    <p class="text-gray-600 mb-6">Install the enhanced inventory management system with visual warehouse capabilities.</p>
                    
                    <!-- Features List -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-6 text-left">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-star text-yellow-500 mr-2"></i>Enhanced Features
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <div class="flex items-center text-sm text-gray-700">
                                    <i class="fas fa-warehouse text-blue-500 mr-2 w-4"></i>
                                    Visual warehouse layout with zones
                                </div>
                                <div class="flex items-center text-sm text-gray-700">
                                    <i class="fas fa-layer-group text-blue-500 mr-2 w-4"></i>
                                    Hierarchical storage: Racks → Shelves → Bins
                                </div>
                                <div class="flex items-center text-sm text-gray-700">
                                    <i class="fas fa-map-marker-alt text-blue-500 mr-2 w-4"></i>
                                    Precise bin location tracking
                                </div>
                                <div class="flex items-center text-sm text-gray-700">
                                    <i class="fas fa-sync-alt text-blue-500 mr-2 w-4"></i>
                                    Automated cycle counting
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center text-sm text-gray-700">
                                    <i class="fas fa-chart-line text-green-500 mr-2 w-4"></i>
                                    Advanced inventory analytics
                                </div>
                                <div class="flex items-center text-sm text-gray-700">
                                    <i class="fas fa-bell text-green-500 mr-2 w-4"></i>
                                    Smart reorder suggestions
                                </div>
                                <div class="flex items-center text-sm text-gray-700">
                                    <i class="fas fa-phone text-green-500 mr-2 w-4"></i>
                                    Vendor communication tracking
                                </div>
                                <div class="flex items-center text-sm text-gray-700">
                                    <i class="fas fa-cube text-green-500 mr-2 w-4"></i>
                                    3D-style visual bin management
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Database Tables -->
                    <div class="bg-blue-50 rounded-lg p-6 mb-6 text-left">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-table text-blue-500 mr-2"></i>Database Tables to be Created
                        </h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <?php foreach ($requiredTables as $table): ?>
                                <div class="bg-white border border-blue-200 rounded p-2 text-sm font-medium text-gray-700">
                                    <i class="fas fa-plus text-blue-500 mr-1"></i><?= $table ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Setup Button -->
                    <form method="POST" class="mt-6">
                        <button type="submit" name="setup_schema" 
                                class="bg-blue-600 text-white px-8 py-4 rounded-lg hover:bg-blue-700 transition-colors text-lg font-semibold"
                                onclick="return confirm('This will create new database tables. Continue?')">
                            <i class="fas fa-rocket mr-2"></i>Install Enhanced Inventory System
                        </button>
                    </form>
                    
                    <p class="text-xs text-gray-500 mt-4">
                        This will create additional database tables for enhanced inventory management.
                        Your existing inventory data will not be affected.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Additional Info -->
        <div class="mt-8 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i>System Requirements
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-800 mb-2">Prerequisites</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>✓ Basic inventory system installed</li>
                        <li>✓ MySQL/MariaDB database</li>
                        <li>✓ PHP 7.4+ with PDO extension</li>
                        <li>✓ Admin user privileges</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-800 mb-2">What Gets Installed</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Warehouse zone management</li>
                        <li>• Storage rack configuration</li>
                        <li>• Bin location tracking</li>
                        <li>• Automated reorder system</li>
                        <li>• Vendor communication logs</li>
                        <li>• Cycle counting plans</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>