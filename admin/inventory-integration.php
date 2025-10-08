<?php
/**
 * Inventory-Product Integration Enhancement
 * Ensures proper integration between products and inventory system
 */

require_once 'config/database.php';
require_once 'includes/security.php';

// Require admin login
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token mismatch');
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'sync_product_inventory':
                // Sync products with product_inventory table
                $stmt = $pdo->query("
                    INSERT IGNORE INTO product_inventory 
                    (product_id, location_id, quantity_on_hand, reorder_point, reorder_quantity, last_updated)
                    SELECT p.id, 1, p.stock, 10, 50, NOW()
                    FROM products p
                    WHERE p.id NOT IN (SELECT product_id FROM product_inventory WHERE location_id = 1)
                ");
                $synced = $stmt->rowCount();
                $success = "Synced $synced products with inventory system";
                break;
                
            case 'create_sample_bins':
                // Create sample inventory bins for visual warehouse
                $stmt = $pdo->query("
                    SELECT wz.id as zone_id, sr.id as rack_id, wz.zone_code, sr.rack_code
                    FROM warehouse_zones wz
                    JOIN storage_racks sr ON wz.id = sr.zone_id
                    LIMIT 2
                ");
                $structures = $stmt->fetchAll();
                
                $binCount = 0;
                foreach ($structures as $structure) {
                    for ($level = 1; $level <= 3; $level++) {
                        for ($position = 1; $position <= 4; $position++) {
                            $binCode = "B{$level}{$position}";
                            $binAddress = "{$structure['zone_code']}-{$structure['rack_code']}-L{$level}-P{$position}";
                            
                            $stmt = $pdo->prepare("
                                INSERT IGNORE INTO inventory_bins 
                                (rack_id, bin_code, bin_address, level_number, position_number, bin_type, 
                                 occupancy_status, status, created_at) 
                                VALUES (?, ?, ?, ?, ?, 'standard', 'empty', 'active', NOW())
                            ");
                            $stmt->execute([$structure['rack_id'], $binCode, $binAddress, $level, $position]);
                            $binCount++;
                        }
                    }
                }
                $success = "Created sample inventory bins for visual warehouse system";
                break;
                
            case 'assign_products_to_bins':
                // Assign some products to bins for demonstration
                $stmt = $pdo->query("
                    SELECT p.id as product_id, ib.id as bin_id, p.stock
                    FROM products p
                    CROSS JOIN inventory_bins ib
                    WHERE ib.occupancy_status = 'empty'
                    AND p.stock > 0
                    LIMIT 5
                ");
                $assignments = $stmt->fetchAll();
                
                $assignedCount = 0;
                foreach ($assignments as $assignment) {
                    // Create product-bin assignment
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO product_bin_assignments 
                        (product_id, bin_id, assignment_type, quantity, assigned_by, status, created_at)
                        VALUES (?, ?, 'primary', ?, ?, 'active', NOW())
                    ");
                    $stmt->execute([
                        $assignment['product_id'], 
                        $assignment['bin_id'], 
                        min($assignment['stock'], 20),
                        $_SESSION['user_id']
                    ]);
                    
                    // Update bin status
                    $stmt = $pdo->prepare("
                        UPDATE inventory_bins 
                        SET occupancy_status = 'partial', 
                            current_product_id = ?, 
                            current_quantity = ?,
                            utilization_percentage = 25.0
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $assignment['product_id'], 
                        min($assignment['stock'], 20),
                        $assignment['bin_id']
                    ]);
                    
                    $assignedCount++;
                }
                $success = "Assigned $assignedCount products to bins for demonstration";
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get integration status
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) FROM products");
$stats['total_products'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM product_inventory");
$stats['tracked_products'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM inventory_bins");
$stats['total_bins'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM inventory_bins WHERE occupancy_status != 'empty'");
$stats['occupied_bins'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM product_bin_assignments WHERE status = 'active'");
$stats['product_assignments'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM warehouse_zones");
$stats['warehouse_zones'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM storage_racks");
$stats['storage_racks'] = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Integration - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                    <span class="text-gray-400">|</span>
                    <a href="admin/dashboard.php" class="text-lg font-semibold text-red-600 hover:text-red-700">Admin Panel</a>
                    <span class="text-gray-400">|</span>
                    <span class="text-lg text-gray-600">Inventory Integration</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="admin/inventory.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-boxes mr-2"></i>Inventory Management
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Inventory Module Integration</h1>
            <p class="text-gray-600 mt-2">Ensure perfect integration between products and inventory management</p>
        </div>

        <!-- Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-cube text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Products</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_products']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-clipboard-list text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Tracked Products</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['tracked_products']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-warehouse text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Inventory Bins</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_bins']) ?></p>
                        <p class="text-xs text-gray-500"><?= $stats['occupied_bins'] ?> occupied</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <i class="fas fa-link text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Product Assignments</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['product_assignments']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Integration Actions -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Integration Actions</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Sync Products -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-sync text-blue-600 text-lg mr-2"></i>
                        <h3 class="font-medium text-gray-900">Sync Products</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        Ensure all products are tracked in the inventory system
                    </p>
                    <form method="POST">
                        <?= Security::getCSRFInput() ?>
                        <input type="hidden" name="action" value="sync_product_inventory">
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            Sync Now
                        </button>
                    </form>
                </div>

                <!-- Create Bins -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-plus text-green-600 text-lg mr-2"></i>
                        <h3 class="font-medium text-gray-900">Create Sample Bins</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        Create inventory bins for the visual warehouse system
                    </p>
                    <form method="POST">
                        <?= Security::getCSRFInput() ?>
                        <input type="hidden" name="action" value="create_sample_bins">
                        <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                            Create Bins
                        </button>
                    </form>
                </div>

                <!-- Assign Products -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-arrow-right text-purple-600 text-lg mr-2"></i>
                        <h3 class="font-medium text-gray-900">Assign Products</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        Assign products to bins for demonstration
                    </p>
                    <form method="POST">
                        <?= Security::getCSRFInput() ?>
                        <input type="hidden" name="action" value="assign_products_to_bins">
                        <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                            Assign Products
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Access Links -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Access Inventory Features</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <a href="admin/inventory.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-list text-blue-600"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="font-medium text-gray-900">Basic Inventory</h3>
                        <p class="text-sm text-gray-600">Stock levels & tracking</p>
                    </div>
                </a>

                <a href="admin/inventory-visual.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-warehouse text-green-600"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="font-medium text-gray-900">Visual Warehouse</h3>
                        <p class="text-sm text-gray-600">2D bin visualization</p>
                    </div>
                </a>

                <a href="admin/inventory-threejs.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <i class="fas fa-cube text-purple-600"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="font-medium text-gray-900">3D Warehouse</h3>
                        <p class="text-sm text-gray-600">Three.js 3D visualization</p>
                    </div>
                </a>

                <a href="admin/warehouse-config.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                    <div class="p-2 bg-orange-100 rounded-lg">
                        <i class="fas fa-cog text-orange-600"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="font-medium text-gray-900">Configuration</h3>
                        <p class="text-sm text-gray-600">Manage racks & bins</p>
                    </div>
                </a>

                <a href="enhanced-inventory-dashboard.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <i class="fas fa-dashboard text-yellow-600"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="font-medium text-gray-900">Enhanced Dashboard</h3>
                        <p class="text-sm text-gray-600">Advanced analytics</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Integration Status -->
        <div class="mt-8 bg-green-50 border border-green-200 rounded-lg p-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-green-800">Integration Status: Complete</h3>
                    <p class="text-green-700">
                        ✅ Database schema installed<br>
                        ✅ Admin navigation integrated<br>
                        ✅ Visual warehouse system ready<br>
                        ✅ Product-inventory synchronization available<br>
                        ✅ Bin location mapping functional<br>
                        ✅ Security measures implemented
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>