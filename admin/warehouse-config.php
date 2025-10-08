<?php
require_once '../config/database.php';
require_once '../includes/security.php';

// Require admin login
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Handle warehouse configuration actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token mismatch');
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_rack':
                $locationId = intval($_POST['location_id']);
                $zoneId = intval($_POST['zone_id']);
                $rackCode = trim($_POST['rack_code']);
                $rackName = trim($_POST['rack_name']);
                $levels = intval($_POST['levels']) ?: 5;
                $positions = intval($_POST['positions']) ?: 10;
                
                // Insert new rack
                $stmt = $pdo->prepare("
                    INSERT INTO storage_racks (zone_id, rack_code, rack_name, rack_type, levels, positions, status, created_at)
                    VALUES (?, ?, ?, 'standard', ?, ?, 'active', NOW())
                ");
                $stmt->execute([$zoneId, $rackCode, $rackName, $levels, $positions]);
                $rackId = $pdo->lastInsertId();
                
                // Create bins for this rack
                for ($level = 1; $level <= $levels; $level++) {
                    for ($position = 1; $position <= $positions; $position++) {
                        $binCode = "B{$level}{$position}";
                        $binAddress = "{$rackCode}-L{$level}-P" . str_pad($position, 2, '0', STR_PAD_LEFT);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO inventory_bins 
                            (rack_id, bin_code, bin_address, level_number, position_number, 
                             bin_type, occupancy_status, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, 'standard', 'empty', 'active', NOW())
                        ");
                        $stmt->execute([$rackId, $binCode, $binAddress, $level, $position]);
                    }
                }
                
                $success = "Added rack {$rackCode} with {$levels} shelves and {$positions} bins per shelf (" . ($levels * $positions) . " total bins)";
                break;
                
            case 'delete_rack':
                $rackId = intval($_POST['rack_id']);
                
                // Check if rack has any products assigned
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM product_bin_assignments pba
                    JOIN inventory_bins ib ON pba.bin_id = ib.id
                    WHERE ib.rack_id = ? AND pba.status = 'active'
                ");
                $stmt->execute([$rackId]);
                $assignedProducts = $stmt->fetchColumn();
                
                if ($assignedProducts > 0) {
                    $error = "Cannot delete rack - it has {$assignedProducts} product assignments. Move products first.";
                } else {
                    // Delete bins first, then rack
                    $stmt = $pdo->prepare("DELETE FROM inventory_bins WHERE rack_id = ?");
                    $stmt->execute([$rackId]);
                    
                    $stmt = $pdo->prepare("DELETE FROM storage_racks WHERE id = ?");
                    $stmt->execute([$rackId]);
                    
                    $success = "Rack deleted successfully along with all its bins";
                }
                break;
                
            case 'add_shelf':
                $rackId = intval($_POST['rack_id']);
                $positions = intval($_POST['positions']) ?: 10;
                
                // Get current max level for this rack
                $stmt = $pdo->prepare("SELECT MAX(level_number) FROM inventory_bins WHERE rack_id = ?");
                $stmt->execute([$rackId]);
                $maxLevel = $stmt->fetchColumn() ?: 0;
                $newLevel = $maxLevel + 1;
                
                // Get rack code for bin addressing
                $stmt = $pdo->prepare("SELECT rack_code FROM storage_racks WHERE id = ?");
                $stmt->execute([$rackId]);
                $rackCode = $stmt->fetchColumn();
                
                // Create bins for new shelf
                for ($position = 1; $position <= $positions; $position++) {
                    $binCode = "B{$newLevel}{$position}";
                    $binAddress = "{$rackCode}-L{$newLevel}-P" . str_pad($position, 2, '0', STR_PAD_LEFT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO inventory_bins 
                        (rack_id, bin_code, bin_address, level_number, position_number, 
                         bin_type, occupancy_status, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'standard', 'empty', 'active', NOW())
                    ");
                    $stmt->execute([$rackId, $binCode, $binAddress, $newLevel, $position]);
                }
                
                // Update rack levels count
                $stmt = $pdo->prepare("UPDATE storage_racks SET levels = ? WHERE id = ?");
                $stmt->execute([$newLevel, $rackId]);
                
                $success = "Added shelf level {$newLevel} with {$positions} bins to rack {$rackCode}";
                break;
                
            case 'delete_shelf':
                $rackId = intval($_POST['rack_id']);
                $levelNumber = intval($_POST['level_number']);
                
                // Check if shelf has any products assigned
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM product_bin_assignments pba
                    JOIN inventory_bins ib ON pba.bin_id = ib.id
                    WHERE ib.rack_id = ? AND ib.level_number = ? AND pba.status = 'active'
                ");
                $stmt->execute([$rackId, $levelNumber]);
                $assignedProducts = $stmt->fetchColumn();
                
                if ($assignedProducts > 0) {
                    $error = "Cannot delete shelf - it has {$assignedProducts} product assignments. Move products first.";
                } else {
                    // Delete bins for this shelf level
                    $stmt = $pdo->prepare("DELETE FROM inventory_bins WHERE rack_id = ? AND level_number = ?");
                    $stmt->execute([$rackId, $levelNumber]);
                    $deletedBins = $stmt->rowCount();
                    
                    // Update rack levels count
                    $stmt = $pdo->prepare("SELECT MAX(level_number) FROM inventory_bins WHERE rack_id = ?");
                    $stmt->execute([$rackId]);
                    $maxLevel = $stmt->fetchColumn() ?: 0;
                    
                    $stmt = $pdo->prepare("UPDATE storage_racks SET levels = ? WHERE id = ?");
                    $stmt->execute([$maxLevel, $rackId]);
                    
                    $success = "Deleted shelf level {$levelNumber} and {$deletedBins} bins";
                }
                break;
                
            case 'add_bins':
                $rackId = intval($_POST['rack_id']);
                $levelNumber = intval($_POST['level_number']);
                $quantity = intval($_POST['quantity']) ?: 1;
                
                // Get current max position for this rack level
                $stmt = $pdo->prepare("SELECT MAX(position_number) FROM inventory_bins WHERE rack_id = ? AND level_number = ?");
                $stmt->execute([$rackId, $levelNumber]);
                $maxPosition = $stmt->fetchColumn() ?: 0;
                
                // Get rack code for bin addressing
                $stmt = $pdo->prepare("SELECT rack_code FROM storage_racks WHERE id = ?");
                $stmt->execute([$rackId]);
                $rackCode = $stmt->fetchColumn();
                
                // Add new bins
                for ($i = 1; $i <= $quantity; $i++) {
                    $newPosition = $maxPosition + $i;
                    $binCode = "B{$levelNumber}{$newPosition}";
                    $binAddress = "{$rackCode}-L{$levelNumber}-P" . str_pad($newPosition, 2, '0', STR_PAD_LEFT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO inventory_bins 
                        (rack_id, bin_code, bin_address, level_number, position_number, 
                         bin_type, occupancy_status, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'standard', 'empty', 'active', NOW())
                    ");
                    $stmt->execute([$rackId, $binCode, $binAddress, $levelNumber, $newPosition]);
                }
                
                // Update rack positions count
                $stmt = $pdo->prepare("SELECT MAX(position_number) FROM inventory_bins WHERE rack_id = ?");
                $stmt->execute([$rackId]);
                $maxPositions = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("UPDATE storage_racks SET positions = ? WHERE id = ?");
                $stmt->execute([$maxPositions, $rackId]);
                
                $success = "Added {$quantity} bins to shelf level {$levelNumber}";
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get warehouse data for display
$warehouses = [];
$racks = [];
$bins = [];

try {
    // Get locations
    $stmt = $pdo->query("SELECT id, location_name, location_code FROM inventory_locations WHERE status = 'active' ORDER BY location_name");
    $warehouses = $stmt->fetchAll();
    
    // Get zones with rack counts
    $stmt = $pdo->query("
        SELECT wz.*, il.location_name, COUNT(sr.id) as rack_count
        FROM warehouse_zones wz 
        LEFT JOIN inventory_locations il ON wz.location_id = il.id
        LEFT JOIN storage_racks sr ON wz.id = sr.zone_id AND sr.status = 'active'
        WHERE wz.status = 'active'
        GROUP BY wz.id
        ORDER BY il.location_name, wz.zone_code
    ");
    $zones = $stmt->fetchAll();
    
    // Get racks with bin counts
    $stmt = $pdo->query("
        SELECT sr.*, wz.zone_code, il.location_name,
               COALESCE(sr.levels, 5) as levels,
               COALESCE(sr.positions, 10) as positions,
               COUNT(ib.id) as bin_count,
               SUM(CASE WHEN ib.occupancy_status != 'empty' THEN 1 ELSE 0 END) as occupied_bins
        FROM storage_racks sr
        LEFT JOIN warehouse_zones wz ON sr.zone_id = wz.id
        LEFT JOIN inventory_locations il ON wz.location_id = il.id
        LEFT JOIN inventory_bins ib ON sr.id = ib.rack_id AND ib.status = 'active'
        WHERE sr.status = 'active'
        GROUP BY sr.id
        ORDER BY il.location_name, wz.zone_code, sr.rack_code
    ");
    $racks = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Configuration - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50" x-data="warehouseConfig()">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                    <span class="text-gray-400">|</span>
                    <a href="dashboard.php" class="text-lg font-semibold text-red-600 hover:text-red-700">Admin Panel</a>
                    <span class="text-gray-400">|</span>
                    <span class="text-lg text-gray-600">Warehouse Configuration</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="inventory-threejs.php" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                        <i class="fas fa-cube mr-2"></i>View 3D Warehouse
                    </a>
                    <a href="inventory.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Inventory
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Warehouse Configuration</h1>
            <p class="text-gray-600 mt-2">Manage racks, shelves, and bins in your warehouse structure</p>
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

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <button @click="showAddRackModal = true" 
                    class="bg-blue-600 text-white p-6 rounded-lg hover:bg-blue-700 transition-colors">
                <div class="text-center">
                    <i class="fas fa-plus text-3xl mb-2"></i>
                    <h3 class="text-lg font-semibold">Add New Rack</h3>
                    <p class="text-sm opacity-90">Create a new rack with shelves and bins</p>
                </div>
            </button>

            <button @click="showBulkAddModal = true" 
                    class="bg-green-600 text-white p-6 rounded-lg hover:bg-green-700 transition-colors">
                <div class="text-center">
                    <i class="fas fa-layer-group text-3xl mb-2"></i>
                    <h3 class="text-lg font-semibold">Bulk Operations</h3>
                    <p class="text-sm opacity-90">Add multiple racks or shelves at once</p>
                </div>
            </button>

            <a href="inventory-threejs.php" 
               class="bg-purple-600 text-white p-6 rounded-lg hover:bg-purple-700 transition-colors">
                <div class="text-center">
                    <i class="fas fa-cube text-3xl mb-2"></i>
                    <h3 class="text-lg font-semibold">View in 3D</h3>
                    <p class="text-sm opacity-90">Visualize your warehouse configuration</p>
                </div>
            </a>
        </div>

        <!-- Current Configuration -->
        <div class="bg-white rounded-lg shadow-md mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Current Configuration</h2>
            </div>
            
            <div class="p-6 space-y-6">
                <?php foreach ($zones as $zone): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                    Zone <?= htmlspecialchars($zone['zone_code']) ?>: <?= htmlspecialchars($zone['zone_name']) ?>
                                </h3>
                                <p class="text-sm text-gray-600">
                                    <?= htmlspecialchars($zone['location_name']) ?> - <?= $zone['rack_count'] ?> racks
                                </p>
                            </div>
                            <button @click="selectedZone = <?= $zone['id'] ?>; showAddRackModal = true" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>Add Rack
                            </button>
                        </div>
                        
                        <!-- Racks in this zone -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php 
                            $zoneRacks = array_filter($racks, function($rack) use ($zone) {
                                return $rack['zone_id'] == $zone['id'];
                            });
                            ?>
                            <?php foreach ($zoneRacks as $rack): ?>
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-medium text-gray-900"><?= htmlspecialchars($rack['rack_code']) ?></h4>
                                        <div class="flex space-x-1">
                                            <button @click="editRack(<?= htmlspecialchars(json_encode($rack)) ?>)" 
                                                    class="text-blue-600 hover:text-blue-800" title="Edit Rack">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button @click="deleteRack(<?= $rack['id'] ?>, '<?= htmlspecialchars($rack['rack_code']) ?>')" 
                                                    class="text-red-600 hover:text-red-800" title="Delete Rack">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($rack['rack_name']) ?></p>
                                    <div class="text-xs text-gray-500">
                                        <div>Levels: <?= isset($rack['levels']) ? $rack['levels'] : 'N/A' ?></div>
                                        <div>Positions: <?= isset($rack['positions']) ? $rack['positions'] : 'N/A' ?></div>
                                        <div>Bins: <?= $rack['bin_count'] ?> (<?= $rack['occupied_bins'] ?> occupied)</div>
                                        <?php if ($rack['bin_count'] > 0): ?>
                                            <div class="mt-1">
                                                <div class="bg-gray-200 rounded-full h-2">
                                                    <div class="bg-green-600 h-2 rounded-full" 
                                                         style="width: <?= round(($rack['occupied_bins'] / $rack['bin_count']) * 100) ?>%"></div>
                                                </div>
                                                <span class="text-xs"><?= round(($rack['occupied_bins'] / $rack['bin_count']) * 100) ?>% utilized</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Add Rack Modal -->
    <div x-show="showAddRackModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 z-50" x-cloak>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="add_rack">
                    <?= Security::getCSRFInput() ?>
                    
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Add New Rack</h3>
                        <button type="button" @click="showAddRackModal = false" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Zone</label>
                            <select name="zone_id" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="">Select Zone</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['location_name']) ?> - Zone <?= htmlspecialchars($zone['zone_code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Rack Code</label>
                            <input type="text" name="rack_code" required placeholder="e.g., R01, R02..." 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Rack Name</label>
                            <input type="text" name="rack_name" required placeholder="e.g., Main Storage Rack 1" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Shelves (Levels)</label>
                                <input type="number" name="levels" min="1" max="10" value="5" required 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Bins per Shelf</label>
                                <input type="number" name="positions" min="1" max="20" value="10" required 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="showAddRackModal = false" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Rack
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function warehouseConfig() {
            return {
                showAddRackModal: false,
                showBulkAddModal: false,
                selectedZone: null,
                selectedRack: null,

                editRack(rack) {
                    // Implement edit functionality
                    console.log('Edit rack:', rack);
                },

                deleteRack(rackId, rackCode) {
                    if (confirm(`Are you sure you want to delete rack ${rackCode}? This will also delete all its bins.`)) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete_rack">
                            <input type="hidden" name="rack_id" value="${rackId}">
                            <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            };
        }
    </script>
</body>
</html>