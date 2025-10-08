<?php
require_once '../config/database.php';
require_once '../includes/InventoryManager.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';
$setupNeeded = false;

// Initialize inventory manager
try {
    $inventoryManager = new InventoryManager($pdo);
} catch (Exception $e) {
    $error = "Error initializing inventory manager: " . $e->getMessage();
    $inventoryManager = null;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'move_product' && $inventoryManager) {
        try {
            $result = $inventoryManager->assignProductToBin(
                intval($_POST['product_id']),
                intval($_POST['bin_id']),
                intval($_POST['quantity']),
                $_SESSION['user_id'],
                $_POST['notes'] ?? ''
            );
            
            if ($result['success']) {
                $success = "Product moved successfully to bin " . $_POST['bin_address'];
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = "Error moving product: " . $e->getMessage();
        }
    }
}

// Get locations
try {
    $stmt = $pdo->prepare("SELECT id, location_name, location_code FROM inventory_locations WHERE status = 'active' ORDER BY location_name");
    $stmt->execute();
    $locations = $stmt->fetchAll();
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $setupNeeded = true;
        $locations = [];
    } else {
        throw $e;
    }
}

// Get products for movement
try {
    $stmt = $pdo->prepare("SELECT id, name, sku FROM products ORDER BY name LIMIT 100");
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visual Inventory Management - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* 3D Warehouse Visual Styles */
        .warehouse-zone {
            background: linear-gradient(145deg, #f1f5f9, #e2e8f0);
            border: 2px solid #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .rack-container {
            background: linear-gradient(145deg, #fef3c7, #fde68a);
            border: 1px solid #f59e0b;
            transition: all 0.3s ease;
        }
        
        .rack-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .bin {
            width: 40px;
            height: 30px;
            margin: 2px;
            border-radius: 4px;
            display: inline-block;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #d1d5db;
        }
        
        .bin:hover {
            transform: scale(1.1);
            z-index: 10;
        }
        
        .bin-empty { background: #f8fafc; border-color: #e2e8f0; }
        .bin-partial { background: #fef3c7; border-color: #f59e0b; }
        .bin-full { background: #dcfce7; border-color: #16a34a; }
        .bin-blocked { background: #fee2e2; border-color: #dc2626; }
        .bin-reserved { background: #ede9fe; border-color: #7c3aed; }
        
        .bin-level {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
            padding: 2px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 4px;
        }
        
        .level-label {
            font-size: 10px;
            font-weight: bold;
            margin-right: 8px;
            color: #374151;
            min-width: 20px;
        }
        
        .aisle-view {
            perspective: 1000px;
            padding: 20px;
        }
        
        .rack-3d {
            transform-style: preserve-3d;
            transition: transform 0.3s ease;
        }
        
        .rack-3d:hover {
            transform: rotateY(5deg) rotateX(5deg);
        }
        
        .location-header {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            text-align: center;
        }
        
        .zone-header {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px;
            border-radius: 8px 8px 0 0;
            text-align: center;
            margin-bottom: 12px;
        }
        
        .tooltip {
            position: absolute;
            z-index: 1000;
            background: #1f2937;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transform: translateX(-50%);
            bottom: 100%;
            left: 50%;
            margin-bottom: 8px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #1f2937 transparent transparent transparent;
        }
        
        .bin:hover .tooltip {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-50" x-data="inventoryVisual()">
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
                    <span class="text-lg text-gray-600">Visual Warehouse</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="simple-2d-warehouse.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-th-large mr-2"></i>2D View (Dynamic)
                    </a>
                    <a href="simple-2d-warehouse-static.php" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                        <i class="fas fa-table mr-2"></i>2D View (Static PHP)
                    </a>
                    <a href="simple-warehouse-view.html" class="bg-teal-600 text-white px-4 py-2 rounded-md hover:bg-teal-700">
                        <i class="fas fa-table-cells mr-2"></i>2D View (Static HTML)
                    </a>
                    <a href="inventory-threejs.php" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                        <i class="fas fa-cube mr-2"></i>3D View (Three.js)
                    </a>
                    <a href="inventory.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Inventory
                    </a>
                    <button @click="showMoveProductModal = true" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        <i class="fas fa-arrows-alt mr-2"></i>Move Product
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-full mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Visual Warehouse Management</h1>
                <p class="text-gray-600 mt-2">Interactive 3D view of warehouse zones, aisles, shelves, and bins</p>
            </div>
            
            <!-- Location Selector -->
            <div class="flex items-center space-x-4">
                <label class="text-sm font-medium text-gray-700">Warehouse:</label>
                <select x-model="selectedLocation" @change="loadWarehouseData()" 
                        class="border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Warehouse</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location['id'] ?>"><?= htmlspecialchars($location['location_name']) ?> (<?= htmlspecialchars($location['location_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
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
                <?php if ($setupNeeded): ?>
                    <div class="mt-2">
                        <a href="../setup-enhanced-inventory.php" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition-colors duration-200">
                            <i class="fas fa-database mr-2"></i>Setup Enhanced Inventory Schema
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Warehouse Legend -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-info-circle mr-2 text-blue-600"></i>Bin Status Legend
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="flex items-center space-x-2">
                    <div class="bin bin-empty"></div>
                    <span class="text-sm text-gray-700">Empty</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="bin bin-partial"></div>
                    <span class="text-sm text-gray-700">Partial</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="bin bin-full"></div>
                    <span class="text-sm text-gray-700">Full</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="bin bin-reserved"></div>
                    <span class="text-sm text-gray-700">Reserved</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="bin bin-blocked"></div>
                    <span class="text-sm text-gray-700">Blocked</span>
                </div>
            </div>
        </div>

        <!-- Warehouse Visualization -->
        <div x-show="selectedLocation && !loading" class="space-y-8">
            <!-- Warehouse Overview -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="location-header">
                    <h2 class="text-2xl font-bold" x-text="warehouseData.location_name"></h2>
                    <p class="text-blue-100 mt-2">Interactive Warehouse Layout</p>
                </div>
                
                <!-- Zones Grid -->
                <div class="p-6 space-y-8">
                    <template x-for="zone in warehouseData.zones" :key="zone.zone_id">
                        <div class="warehouse-zone rounded-lg p-6">
                            <div class="zone-header">
                                <h3 class="text-lg font-bold" x-text="`Zone ${zone.zone_code}: ${zone.zone_name}`"></h3>
                                <div class="text-sm text-green-100 mt-1">
                                    <span x-text="`${zone.racks.length} Racks`"></span>
                                    <span class="mx-2">•</span>
                                    <span x-text="`${zone.total_bins} Bins`"></span>
                                    <span class="mx-2">•</span>
                                    <span x-text="`${zone.utilization}% Utilized`"></span>
                                </div>
                            </div>
                            
                            <!-- Racks in Zone -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                <template x-for="rack in zone.racks" :key="rack.rack_id">
                                    <div class="rack-container rounded-lg p-4 rack-3d">
                                        <div class="text-center mb-3">
                                            <h4 class="font-semibold text-gray-800" x-text="`Rack ${rack.rack_code}`"></h4>
                                            <p class="text-xs text-gray-600" x-text="rack.rack_name"></p>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <span x-text="`${rack.levels} Levels`"></span>
                                                <span class="mx-1">•</span>
                                                <span x-text="`${rack.positions} Positions`"></span>
                                            </div>
                                        </div>
                                        
                                        <!-- Rack Levels (Shelves) -->
                                        <div class="space-y-1">
                                            <template x-for="level in rack.levels_array" :key="`${rack.rack_id}-${level}`">
                                                <div class="bin-level">
                                                    <div class="level-label" x-text="`L${level}`"></div>
                                                    <div class="flex flex-wrap">
                                                        <template x-for="position in rack.positions_array" :key="`${rack.rack_id}-${level}-${position}`">
                                                            <div 
                                                                class="bin"
                                                                :class="getBinClass(rack.rack_id, level, position)"
                                                                @click="selectBin(rack.rack_id, level, position, zone.zone_code, rack.rack_code)"
                                                                @mouseenter="showBinTooltip($event, rack.rack_id, level, position)"
                                                                @mouseleave="hideBinTooltip()">
                                                                
                                                                <div class="tooltip" x-show="tooltipVisible" x-html="tooltipContent"></div>
                                                                
                                                                <!-- Bin Content Indicator -->
                                                                <div class="absolute inset-0 flex items-center justify-center">
                                                                    <div class="w-2 h-2 rounded-full" 
                                                                         :class="getBinContentIndicator(rack.rack_id, level, position)"></div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div x-show="loading" class="text-center py-12">
            <i class="fas fa-spinner fa-spin text-4xl text-gray-400 mb-4"></i>
            <<p class="text-gray-600">Loading warehouse data...</p>
        </div>

        <!-- No Location Selected -->
        <div x-show="!selectedLocation && !loading" class="text-center py-12">
            <i class="fas fa-warehouse text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Select a Warehouse</h3>
            <p class="text-gray-500">Choose a warehouse location to view the visual inventory layout</p>
        </div>
    </div>

    <!-- Move Product Modal -->
    <div x-show="showMoveProductModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="move_product">
                    <input type="hidden" name="bin_id" x-model="selectedBin.bin_id">
                    <input type="hidden" name="bin_address" x-model="selectedBin.bin_address">
                    
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Move Product to Bin</h3>
                        <button type="button" @click="showMoveProductModal = false" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Target Bin</label>
                            <input type="text" :value="selectedBin.bin_address" readonly
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-50">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Product</label>
                            <select name="product_id" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                                <option value="">Select Product</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?> (<?= htmlspecialchars($product['sku']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                            <input type="number" name="quantity" min="1" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="2" 
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" @click="showMoveProductModal = false" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Move Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function inventoryVisual() {
            return {
                selectedLocation: '',
                warehouseData: { zones: [] },
                loading: false,
                showMoveProductModal: false,
                selectedBin: { bin_id: '', bin_address: '' },
                tooltipVisible: false,
                tooltipContent: '',
                binData: new Map(),

                async loadWarehouseData() {
                    if (!this.selectedLocation) {
                        this.warehouseData = { zones: [] };
                        return;
                    }

                    this.loading = true;
                    try {
                        const response = await fetch(`../enhanced-inventory-dashboard.php?action=ajax&type=warehouse_structure&location_id=${this.selectedLocation}`);
                        const data = await response.json();
                        
                        if (data.success) {
                            this.processWarehouseData(data.data);
                        }
                    } catch (error) {
                        console.error('Failed to load warehouse data:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                processWarehouseData(rawData) {
                    // Group data by zones and racks
                    const zones = new Map();
                    
                    rawData.forEach(item => {
                        if (!zones.has(item.zone_id)) {
                            zones.set(item.zone_id, {
                                zone_id: item.zone_id,
                                zone_code: item.zone_code,
                                zone_name: item.zone_name,
                                zone_type: item.zone_type,
                                racks: new Map(),
                                total_bins: 0,
                                utilized_bins: 0
                            });
                        }
                        
                        const zone = zones.get(item.zone_id);
                        
                        if (item.rack_id && !zone.racks.has(item.rack_id)) {
                            zone.racks.set(item.rack_id, {
                                rack_id: item.rack_id,
                                rack_code: item.rack_code,
                                rack_name: item.rack_name,
                                levels: 4, // Default levels
                                positions: 10, // Default positions
                                levels_array: [1, 2, 3, 4],
                                positions_array: Array.from({length: 10}, (_, i) => i + 1),
                                bins: new Map()
                            });
                        }
                        
                        if (item.bin_id) {
                            const rack = zone.racks.get(item.rack_id);
                            const binKey = `${item.rack_id}-${item.level_number || 1}-${item.position_number || 1}`;
                            
                            rack.bins.set(binKey, {
                                bin_id: item.bin_id,
                                bin_code: item.bin_code,
                                bin_address: item.bin_address,
                                occupancy_status: item.occupancy_status,
                                current_quantity: item.current_quantity,
                                utilization_percentage: item.utilization_percentage,
                                product_name: item.product_name,
                                product_sku: item.product_sku
                            });
                            
                            this.binData.set(binKey, rack.bins.get(binKey));
                            zone.total_bins++;
                            if (item.occupancy_status !== 'empty') zone.utilized_bins++;
                        }
                    });
                    
                    // Convert to arrays and calculate utilization
                    this.warehouseData.zones = Array.from(zones.values()).map(zone => {
                        zone.racks = Array.from(zone.racks.values());
                        zone.utilization = zone.total_bins > 0 ? Math.round((zone.utilized_bins / zone.total_bins) * 100) : 0;
                        return zone;
                    });
                },

                getBinClass(rackId, level, position) {
                    const binKey = `${rackId}-${level}-${position}`;
                    const bin = this.binData.get(binKey);
                    
                    if (!bin) return 'bin-empty';
                    
                    switch (bin.occupancy_status) {
                        case 'empty': return 'bin-empty';
                        case 'partial': return 'bin-partial';
                        case 'full': return 'bin-full';
                        case 'blocked': return 'bin-blocked';
                        case 'reserved': return 'bin-reserved';
                        default: return 'bin-empty';
                    }
                },

                getBinContentIndicator(rackId, level, position) {
                    const binKey = `${rackId}-${level}-${position}`;
                    const bin = this.binData.get(binKey);
                    
                    if (!bin || bin.occupancy_status === 'empty') return 'bg-gray-300';
                    if (bin.utilization_percentage > 80) return 'bg-red-500';
                    if (bin.utilization_percentage > 50) return 'bg-yellow-500';
                    return 'bg-green-500';
                },

                selectBin(rackId, level, position, zoneCode, rackCode) {
                    const binKey = `${rackId}-${level}-${position}`;
                    const bin = this.binData.get(binKey);
                    
                    if (bin) {
                        this.selectedBin = {
                            bin_id: bin.bin_id,
                            bin_address: bin.bin_address
                        };
                    } else {
                        // Generate address for empty bin
                        this.selectedBin = {
                            bin_id: '',
                            bin_address: `${zoneCode}-${rackCode}-L${level}-P${position}`
                        };
                    }
                    
                    this.showMoveProductModal = true;
                },

                showBinTooltip(event, rackId, level, position) {
                    const binKey = `${rackId}-${level}-${position}`;
                    const bin = this.binData.get(binKey);
                    
                    if (bin) {
                        this.tooltipContent = `
                            <strong>${bin.bin_address}</strong><br>
                            Status: ${bin.occupancy_status}<br>
                            ${bin.product_name ? `Product: ${bin.product_name}<br>` : ''}
                            ${bin.current_quantity ? `Quantity: ${bin.current_quantity}<br>` : ''}
                            Utilization: ${bin.utilization_percentage || 0}%
                        `;
                    } else {
                        this.tooltipContent = `
                            <strong>Empty Bin</strong><br>
                            Level ${level}, Position ${position}<br>
                            Available for assignment
                        `;
                    }
                    
                    this.tooltipVisible = true;
                },

                hideBinTooltip() {
                    this.tooltipVisible = false;
                }
            }
        }
    </script>
</body>
</html>