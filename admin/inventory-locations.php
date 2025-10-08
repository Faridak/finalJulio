<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_location') {
        try {
            // First, insert the location
            $stmt = $pdo->prepare("
                INSERT INTO inventory_locations 
                (location_name, location_code, address, city, state, zip_code, country, 
                 contact_name, contact_phone, contact_email, manager_name, capacity_limit, 
                 temperature_controlled, security_level, notes, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            
            $stmt->execute([
                $_POST['location_name'],
                $_POST['location_code'],
                $_POST['address'],
                $_POST['city'],
                $_POST['state'],
                $_POST['zip_code'],
                $_POST['country'],
                $_POST['contact_name'],
                $_POST['contact_phone'],
                $_POST['contact_email'],
                $_POST['manager_name'],
                !empty($_POST['capacity_limit']) ? intval($_POST['capacity_limit']) : null,
                isset($_POST['temperature_controlled']) ? 1 : 0,
                $_POST['security_level'],
                $_POST['notes']
            ]);
            
            $locationId = $pdo->lastInsertId();
            
            // Get configuration parameters
            $defaultRacks = intval($_POST['default_racks'] ?? 10);
            $defaultLevels = intval($_POST['default_levels'] ?? 5);
            $defaultPositions = intval($_POST['default_positions'] ?? 10);
            
            // Auto-generate default warehouse structure
            generateDefaultWarehouseStructure($pdo, $locationId, $_POST['location_code'], $defaultRacks, $defaultLevels, $defaultPositions);
            
            $totalBins = $defaultRacks * $defaultLevels * $defaultPositions;
            $success = "Location added successfully with default structure: {$defaultRacks} racks × {$defaultLevels} shelves × {$defaultPositions} bins ({$totalBins} total bins)";
            
        } catch (PDOException $e) {
            $error = "Error adding location: " . $e->getMessage();
        }
    } elseif ($action === 'update_location') {
        try {
            $locationId = intval($_POST['location_id']);
            
            $stmt = $pdo->prepare("
                UPDATE inventory_locations SET 
                location_name = ?, location_code = ?, address = ?, city = ?, state = ?, 
                zip_code = ?, country = ?, contact_name = ?, contact_phone = ?, contact_email = ?,
                manager_name = ?, capacity_limit = ?, temperature_controlled = ?, 
                security_level = ?, notes = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $_POST['location_name'],
                $_POST['location_code'],
                $_POST['address'],
                $_POST['city'],
                $_POST['state'],
                $_POST['zip_code'],
                $_POST['country'],
                $_POST['contact_name'],
                $_POST['contact_phone'],
                $_POST['contact_email'],
                $_POST['manager_name'],
                !empty($_POST['capacity_limit']) ? intval($_POST['capacity_limit']) : null,
                isset($_POST['temperature_controlled']) ? 1 : 0,
                $_POST['security_level'],
                $_POST['notes'],
                $locationId
            ]);
            
            $success = "Location updated successfully.";
            
        } catch (PDOException $e) {
            $error = "Error updating location: " . $e->getMessage();
        }
    } elseif ($action === 'delete_location') {
        try {
            $locationId = intval($_POST['location_id']);
            
            // Check if location has zones
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM warehouse_zones WHERE location_id = ?");
            $stmt->execute([$locationId]);
            $zoneCount = $stmt->fetchColumn();
            
            if ($zoneCount > 0) {
                $error = "Cannot delete location with existing zones. Please remove zones first.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM inventory_locations WHERE id = ?");
                $stmt->execute([$locationId]);
                $success = "Location deleted successfully.";
            }
            
        } catch (PDOException $e) {
            $error = "Error deleting location: " . $e->getMessage();
        }
    } elseif ($action === 'toggle_status') {
        try {
            $locationId = intval($_POST['location_id']);
            $newStatus = $_POST['new_status'];
            
            $stmt = $pdo->prepare("UPDATE inventory_locations SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $locationId]);
            
            $success = "Location status updated successfully.";
            
        } catch (PDOException $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    }
}

// Get locations with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$whereConditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(location_name LIKE ? OR location_code LIKE ? OR city LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($statusFilter)) {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $whereConditions);

try {
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM inventory_locations WHERE $whereClause";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalLocations = $stmt->fetchColumn();
    
    // Get locations with zone count
    $locationsQuery = "
        SELECT il.*, 
               COUNT(wz.id) as zone_count,
               (SELECT COUNT(*) FROM storage_racks sr 
                JOIN warehouse_zones wz2 ON sr.zone_id = wz2.id 
                WHERE wz2.location_id = il.id) as rack_count,
               (SELECT COUNT(*) FROM inventory_bins ib 
                JOIN storage_racks sr2 ON ib.rack_id = sr2.id 
                JOIN warehouse_zones wz3 ON sr2.zone_id = wz3.id 
                WHERE wz3.location_id = il.id) as bin_count
        FROM inventory_locations il
        LEFT JOIN warehouse_zones wz ON il.id = wz.location_id
        WHERE $whereClause
        GROUP BY il.id
        ORDER BY il.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($locationsQuery);
    $stmt->execute($params);
    $locations = $stmt->fetchAll();
    
    // Get statistics
    $statsQueries = [
        'total_locations' => "SELECT COUNT(*) FROM inventory_locations",
        'active_locations' => "SELECT COUNT(*) FROM inventory_locations WHERE status = 'active'",
        'total_zones' => "SELECT COUNT(*) FROM warehouse_zones",
        'total_bins' => "SELECT COUNT(*) FROM inventory_bins ib JOIN storage_racks sr ON ib.rack_id = sr.id JOIN warehouse_zones wz ON sr.zone_id = wz.id"
    ];
    
    $stats = [];
    foreach ($statsQueries as $key => $query) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $stats[$key] = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $stats[$key] = 0;
        }
    }
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $error = "Inventory locations table is missing. Please import the inventory module schema first.";
        $locations = [];
        $stats = ['total_locations' => 0, 'active_locations' => 0, 'total_zones' => 0, 'total_bins' => 0];
    } else {
        throw $e;
    }
}

// Function to generate default warehouse structure
function generateDefaultWarehouseStructure($pdo, $locationId, $locationCode, $racks = 10, $levels = 5, $positions = 10) {
    try {
        // Create a default zone
        $stmt = $pdo->prepare("
            INSERT INTO warehouse_zones 
            (location_id, zone_code, zone_name, zone_type, description, status) 
            VALUES (?, 'A', 'Main Zone', 'storage', 'Auto-generated main storage zone', 'active')
        ");
        $stmt->execute([$locationId]);
        $zoneId = $pdo->lastInsertId();
        
        // Create racks
        for ($r = 1; $r <= $racks; $r++) {
            $rackCode = 'R' . str_pad($r, 2, '0', STR_PAD_LEFT);
            $rackName = "Rack {$r}";
            
            $stmt = $pdo->prepare("
                INSERT INTO storage_racks 
                (zone_id, rack_code, rack_name, rack_type, levels, positions, status) 
                VALUES (?, ?, ?, 'standard', ?, ?, 'active')
            ");
            $stmt->execute([$zoneId, $rackCode, $rackName, $levels, $positions]);
            $rackId = $pdo->lastInsertId();
            
            // Create bins for this rack
            for ($level = 1; $level <= $levels; $level++) {
                for ($pos = 1; $pos <= $positions; $pos++) {
                    $binCode = "B{$level}{$pos}";
                    $binAddress = "{$rackCode}-L{$level}-P" . str_pad($pos, 2, '0', STR_PAD_LEFT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO inventory_bins 
                        (rack_id, bin_code, bin_address, level_number, position_number, 
                         bin_type, occupancy_status, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'standard', 'empty', 'active', NOW())
                    ");
                    $stmt->execute([$rackId, $binCode, $binAddress, $level, $pos]);
                }
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error generating warehouse structure: " . $e->getMessage());
        return false;
    }
}

$totalPages = ceil($totalLocations / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Locations - VentDepot Admin</title>
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
                    <span class="text-gray-400">|</span>
                    <a href="inventory.php" class="text-lg text-blue-600 hover:text-blue-700">Inventory</a>
                    <span class="text-gray-400">|</span>
                    <span class="text-lg text-gray-600">Locations</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="inventory.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Inventory
                    </a>
                    <a href="inventory-visual.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-warehouse mr-2"></i>Visual Warehouse
                    </a>
                    <a href="inventory-receiving.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        <i class="fas fa-truck mr-2"></i>Receive Inventory
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Inventory Locations</h1>
                <p class="text-gray-600 mt-2">Manage warehouse and storage facility locations</p>
            </div>
            <button onclick="openAddLocationModal()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition duration-200">
                <i class="fas fa-plus mr-2"></i>Add Location
            </button>
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

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-warehouse text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Locations</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_locations']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Locations</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['active_locations']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <i class="fas fa-layer-group text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Zones</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_zones']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-cube text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Bins</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_bins']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Locations Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($locations)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-warehouse text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-lg font-medium">No Locations Found</p>
                                    <p class="text-sm">Add your first warehouse or storage location</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($locations as $location): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-start space-x-4">
                                            <div class="flex-shrink-0">
                                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-warehouse text-blue-600"></i>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-medium text-gray-900"><?= htmlspecialchars($location['location_name']) ?></div>
                                                <div class="text-sm text-gray-500">Code: <?= htmlspecialchars($location['location_code']) ?></div>
                                                <div class="text-sm text-gray-500">
                                                    <?= $location['zone_count'] ?> zones, <?= $location['rack_count'] ?> racks, <?= $location['bin_count'] ?> bins
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($location['address'] ?? '') ?></div>
                                        <div class="text-sm text-gray-500">
                                            <?= htmlspecialchars($location['city'] ?? '') ?><?= !empty($location['city']) && !empty($location['state']) ? ', ' : '' ?><?= htmlspecialchars($location['state'] ?? '') ?><?= !empty($location['zip_code']) ? ' ' . htmlspecialchars($location['zip_code']) : '' ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if (!empty($location['contact_name'])): ?>
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($location['contact_name']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($location['manager_name'])): ?>
                                            <div class="text-sm text-gray-500">
                                                <i class="fas fa-user-tie mr-1"></i><?= htmlspecialchars($location['manager_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (empty($location['contact_name']) && empty($location['manager_name'])): ?>
                                            <div class="text-sm text-gray-400">No contact info</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            $status = $location['status'] ?? 'unknown';
                                            switch($status) {
                                                case 'active': echo 'bg-green-100 text-green-800'; break;
                                                case 'inactive': echo 'bg-red-100 text-red-800'; break;
                                                case 'maintenance': echo 'bg-yellow-100 text-yellow-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="inventory-visual.php?location_id=<?= $location['id'] ?>" 
                                               class="text-blue-600 hover:text-blue-900" title="View Visual Layout">
                                                <i class="fas fa-warehouse"></i>
                                            </a>
                                            <button onclick="editLocation(<?= htmlspecialchars(json_encode($location)) ?>)" 
                                                    class="text-green-600 hover:text-green-900" title="Edit Location">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteLocation(<?= $location['id'] ?>, '<?= htmlspecialchars($location['location_name']) ?>')" 
                                                    class="text-red-600 hover:text-red-900" title="Delete Location">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function openAddLocationModal() {
            document.getElementById('addLocationModal').classList.remove('hidden');
            calculateTotalBins(); // Calculate initial total
        }
        
        function calculateTotalBins() {
            const racks = parseInt(document.querySelector('input[name="default_racks"]').value) || 0;
            const levels = parseInt(document.querySelector('input[name="default_levels"]').value) || 0;
            const positions = parseInt(document.querySelector('input[name="default_positions"]').value) || 0;
            const total = racks * levels * positions;
            
            document.getElementById('totalBinsCalc').textContent = `Total: ${total.toLocaleString()} bins`;
        }
        
        // Add event listeners for real-time calculation
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input[name="default_racks"], input[name="default_levels"], input[name="default_positions"]');
            inputs.forEach(input => {
                input.addEventListener('input', calculateTotalBins);
            });
        });

        function editLocation(location) {
            // This would populate edit modal - placeholder for now
            alert('Edit functionality will be implemented');
        }

        function deleteLocation(locationId, locationName) {
            if (confirm(`Are you sure you want to delete "${locationName}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_location">
                    <input type="hidden" name="location_id" value="${locationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

    <!-- Simple Add Modal (Basic Version) -->
    <div id="addLocationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Add New Location</h3>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="add_location">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Location Name *</label>
                            <input type="text" name="location_name" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Location Code *</label>
                            <input type="text" name="location_code" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                            <input type="text" name="address" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">City</label>
                            <input type="text" name="city" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">State</label>
                            <input type="text" name="state" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ZIP Code</label>
                            <input type="text" name="zip_code" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                            <input type="text" name="country" value="United States" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Name</label>
                            <input type="text" name="contact_name" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Phone</label>
                            <input type="text" name="contact_phone" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
                            <input type="email" name="contact_email" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Manager Name</label>
                            <input type="text" name="manager_name" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Security Level</label>
                            <select name="security_level" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="standard">Standard</option>
                                <option value="high">High</option>
                                <option value="maximum">Maximum</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="temperature_controlled" class="mr-2">
                                <span class="text-sm text-gray-700">Temperature Controlled</span>
                            </label>
                        </div>
                        
                        <!-- Warehouse Structure Configuration -->
                        <div class="col-span-2 border-t pt-4">
                            <h4 class="text-md font-semibold text-gray-900 mb-3">🏗️ Default Warehouse Structure</h4>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Default Racks</label>
                                    <input type="number" name="default_racks" value="10" min="1" max="50" 
                                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Shelves per Rack</label>
                                    <input type="number" name="default_levels" value="5" min="1" max="10" 
                                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Bins per Shelf</label>
                                    <input type="number" name="default_positions" value="10" min="1" max="20" 
                                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                            </div>
                            <div class="mt-2 text-sm text-gray-600">
                                <i class="fas fa-info-circle mr-1"></i>
                                This will automatically create the warehouse structure when the location is added.
                                <span id="totalBinsCalc" class="font-medium text-blue-600">Total: 500 bins</span>
                            </div>
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" onclick="document.getElementById('addLocationModal').classList.add('hidden')" 
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">Cancel</button>
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">Add Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>