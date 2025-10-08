<?php
require_once 'config/database.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_simple'])) {
    try {
        // Create essential tables for visual inventory with minimal dependencies
        $sqlStatements = [
            // Warehouse Zones
            "CREATE TABLE IF NOT EXISTS warehouse_zones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                location_id INT NOT NULL,
                zone_code VARCHAR(10) NOT NULL,
                zone_name VARCHAR(100) NOT NULL,
                zone_type ENUM('receiving', 'storage', 'picking', 'shipping', 'returns', 'quarantine') NOT NULL DEFAULT 'storage',
                status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_location_zone (location_id, zone_code),
                INDEX idx_location_zone (location_id, zone_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            
            // Storage Racks
            "CREATE TABLE IF NOT EXISTS storage_racks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                zone_id INT NOT NULL,
                rack_code VARCHAR(10) NOT NULL,
                rack_name VARCHAR(100) NOT NULL,
                rack_type ENUM('standard', 'pallet', 'cantilever', 'drive_in', 'flow', 'mobile') DEFAULT 'standard',
                total_levels INT DEFAULT 4,
                total_positions INT DEFAULT 10,
                status ENUM('active', 'inactive', 'maintenance', 'damaged') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_zone_rack (zone_id, rack_code),
                INDEX idx_zone_rack (zone_id, rack_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            
            // Inventory Bins
            "CREATE TABLE IF NOT EXISTS inventory_bins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                rack_id INT NOT NULL,
                bin_code VARCHAR(20) NOT NULL,
                bin_address VARCHAR(50) NOT NULL,
                level_number INT NOT NULL DEFAULT 1,
                position_number INT NOT NULL DEFAULT 1,
                bin_type ENUM('standard', 'bulk', 'small_parts', 'hazmat', 'fragile', 'temperature_controlled') DEFAULT 'standard',
                volume_liters DECIMAL(8,2) DEFAULT 100.0,
                weight_capacity_kg DECIMAL(8,2) DEFAULT 50.0,
                occupancy_status ENUM('empty', 'partial', 'full', 'reserved', 'blocked') DEFAULT 'empty',
                current_product_id INT DEFAULT NULL,
                current_quantity INT DEFAULT 0,
                utilization_percentage DECIMAL(5,2) DEFAULT 0.00,
                status ENUM('active', 'inactive', 'maintenance', 'damaged', 'blocked') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_rack_bin (rack_id, bin_code),
                UNIQUE KEY unique_bin_address (bin_address),
                INDEX idx_occupancy_status (occupancy_status),
                INDEX idx_rack_position (rack_id, level_number, position_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            
            // Product Bin Assignments
            "CREATE TABLE IF NOT EXISTS product_bin_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                bin_id INT NOT NULL,
                assignment_type ENUM('primary', 'overflow', 'picking', 'bulk', 'reserve') DEFAULT 'primary',
                quantity INT NOT NULL DEFAULT 0,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_movement_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('active', 'inactive', 'depleted') DEFAULT 'active',
                UNIQUE KEY unique_product_bin_type (product_id, bin_id, assignment_type),
                INDEX idx_product_assignments (product_id),
                INDEX idx_bin_assignments (bin_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        ];
        
        // Execute each statement
        foreach ($sqlStatements as $sql) {
            $pdo->exec($sql);
        }
        
        // Add foreign key constraints after tables are created
        $foreignKeys = [
            "ALTER TABLE warehouse_zones ADD CONSTRAINT fk_wz_location 
             FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE CASCADE",
            "ALTER TABLE storage_racks ADD CONSTRAINT fk_sr_zone 
             FOREIGN KEY (zone_id) REFERENCES warehouse_zones(id) ON DELETE CASCADE",
            "ALTER TABLE inventory_bins ADD CONSTRAINT fk_ib_rack 
             FOREIGN KEY (rack_id) REFERENCES storage_racks(id) ON DELETE CASCADE"
        ];
        
        foreach ($foreignKeys as $fkSql) {
            try {
                $pdo->exec($fkSql);
            } catch (PDOException $e) {
                // Ignore if foreign key already exists
                if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                    throw $e;
                }
            }
        }
        
        $success = "Essential visual inventory tables created successfully! You can now add sample data.";
        
    } catch (Exception $e) {
        $error = "Error creating tables: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    try {
        // Check if we have a location
        $stmt = $pdo->prepare("SELECT id FROM inventory_locations LIMIT 1");
        $stmt->execute();
        $location = $stmt->fetch();
        
        if (!$location) {
            // Create a sample location
            $stmt = $pdo->prepare("INSERT INTO inventory_locations (location_name, location_code, address, status) VALUES (?, ?, ?, 'active')");
            $stmt->execute(['Main Warehouse', 'WH001', '123 Industrial St, City, State 12345']);
            $locationId = $pdo->lastInsertId();
        } else {
            $locationId = $location['id'];
        }
        
        // Create sample zones
        $zones = [
            ['A', 'Zone A - Electronics', 'storage'],
            ['B', 'Zone B - Apparel', 'storage'], 
            ['C', 'Zone C - Home & Garden', 'storage'],
            ['R', 'Receiving Zone', 'receiving'],
            ['S', 'Shipping Zone', 'shipping']
        ];
        
        foreach ($zones as $zone) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO warehouse_zones (location_id, zone_code, zone_name, zone_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$locationId, $zone[0], $zone[1], $zone[2]]);
            $zoneId = $pdo->lastInsertId();
            
            if ($zoneId > 0) {
                // Create racks in each zone
                for ($rack = 1; $rack <= 4; $rack++) {
                    $rackCode = sprintf("R%02d", $rack);
                    $stmt = $pdo->prepare("INSERT INTO storage_racks (zone_id, rack_code, rack_name, total_levels, total_positions) VALUES (?, ?, ?, 4, 10)");
                    $stmt->execute([$zoneId, $rackCode, "Rack $rackCode"]);
                    $rackId = $pdo->lastInsertId();
                    
                    // Create bins for each rack (4 levels, 10 positions each)
                    for ($level = 1; $level <= 4; $level++) {
                        for ($position = 1; $position <= 10; $position++) {
                            $binCode = sprintf("L%02dP%02d", $level, $position);
                            $binAddress = "{$zone[0]}-{$rackCode}-L{$level}-P{$position}";
                            
                            // Random occupancy for demo
                            $occupancyStatuses = ['empty', 'empty', 'partial', 'full']; // More empty bins
                            $occupancyStatus = $occupancyStatuses[array_rand($occupancyStatuses)];
                            $utilization = match($occupancyStatus) {
                                'empty' => 0,
                                'partial' => rand(20, 70),
                                'full' => rand(85, 100),
                                default => 0
                            };
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO inventory_bins 
                                (rack_id, bin_code, bin_address, level_number, position_number, 
                                 occupancy_status, utilization_percentage, volume_liters, weight_capacity_kg)
                                VALUES (?, ?, ?, ?, ?, ?, ?, 100, 50)
                            ");
                            $stmt->execute([$rackId, $binCode, $binAddress, $level, $position, $occupancyStatus, $utilization]);
                        }
                    }
                }
            }
        }
        
        $success = "Sample warehouse data created successfully! You can now view the visual inventory system.";
        
    } catch (Exception $e) {
        $error = "Error creating sample data: " . $e->getMessage();
    }
}

// Check current status
$tablesStatus = [];
$essentialTables = ['warehouse_zones', 'storage_racks', 'inventory_bins', 'product_bin_assignments'];

foreach ($essentialTables as $table) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $tablesStatus[$table] = !!$stmt->fetch();
    } catch (PDOException $e) {
        $tablesStatus[$table] = false;
    }
}

$allTablesExist = !in_array(false, $tablesStatus);

// Get data counts if tables exist
$dataCounts = [];
if ($allTablesExist) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM warehouse_zones");
        $stmt->execute();
        $dataCounts['zones'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM storage_racks");
        $stmt->execute();
        $dataCounts['racks'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_bins");
        $stmt->execute();
        $dataCounts['bins'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $dataCounts = ['zones' => 0, 'racks' => 0, 'bins' => 0];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Visual Inventory Setup - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Simple Visual Inventory Setup</h1>
            <p class="text-gray-600">Quick setup for essential visual warehouse features</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <!-- Tables Status -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-database text-blue-600 mr-2"></i>Database Tables
                </h2>
                <div class="space-y-3">
                    <?php foreach ($tablesStatus as $table => $exists): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-700"><?= $table ?></span>
                            <?php if ($exists): ?>
                                <span class="text-green-600"><i class="fas fa-check-circle"></i> Exists</span>
                            <?php else: ?>
                                <span class="text-red-600"><i class="fas fa-times-circle"></i> Missing</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!$allTablesExist): ?>
                    <form method="POST" class="mt-6">
                        <button type="submit" name="setup_simple" 
                                class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 transition-colors"
                                onclick="return confirm('Create essential visual inventory tables?')">
                            <i class="fas fa-plus mr-2"></i>Create Essential Tables
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Data Status -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-chart-bar text-green-600 mr-2"></i>Sample Data
                </h2>
                <?php if ($allTablesExist): ?>
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-700">Warehouse Zones</span>
                            <span class="text-blue-600 font-semibold"><?= $dataCounts['zones'] ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-700">Storage Racks</span>
                            <span class="text-green-600 font-semibold"><?= $dataCounts['racks'] ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-700">Inventory Bins</span>
                            <span class="text-purple-600 font-semibold"><?= $dataCounts['bins'] ?></span>
                        </div>
                    </div>
                    
                    <?php if ($dataCounts['zones'] == 0): ?>
                        <form method="POST">
                            <button type="submit" name="add_sample_data" 
                                    class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 transition-colors"
                                    onclick="return confirm('Add sample warehouse data for testing?')">
                                <i class="fas fa-warehouse mr-2"></i>Add Sample Data
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center">
                            <i class="fas fa-check-circle text-green-600 text-2xl mb-2"></i>
                            <p class="text-green-700 font-medium">Sample data ready!</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-8">
                        <i class="fas fa-table text-gray-300 text-3xl mb-2"></i><br>
                        Create tables first to add sample data
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Next Steps -->
        <?php if ($allTablesExist && $dataCounts['zones'] > 0): ?>
            <div class="bg-white rounded-lg shadow-lg p-8 text-center">
                <i class="fas fa-rocket text-blue-600 text-4xl mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Setup Complete!</h2>
                <p class="text-gray-600 mb-6">Your visual inventory system is ready to use.</p>
                
                <div class="space-x-4">
                    <a href="admin/inventory-visual.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-warehouse mr-2"></i>Open Visual Warehouse
                    </a>
                    <a href="test-visual-inventory-web.php" class="inline-block bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-check mr-2"></i>Test System
                    </a>
                    <a href="admin/inventory-locations.php" class="inline-block bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-map-marker-alt mr-2"></i>Manage Locations
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Alternative Options -->
        <div class="mt-8 text-center">
            <div class="space-x-4">
                <a href="setup-enhanced-inventory.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-cogs mr-1"></i>Full Enhanced Setup
                </a>
                <a href="visual-inventory-demo.php" class="text-green-600 hover:text-green-800">
                    <i class="fas fa-eye mr-1"></i>View Demo
                </a>
                <a href="admin/dashboard.php" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-arrow-left mr-1"></i>Back to Admin
                </a>
            </div>
        </div>
    </div>
</body>
</html>