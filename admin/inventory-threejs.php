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
    <title>Three.js 3D Warehouse - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Note: Tailwind CDN is for development only. For production, install Tailwind CLI -->
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <!-- Three.js with proper ES modules setup -->
    <script async src="https://unpkg.com/es-module-shims@1.6.3/dist/es-module-shims.js"></script>
    <script type="importmap">
    {
      "imports": {
        "three": "https://unpkg.com/three@0.158.0/build/three.module.js",
        "three/addons/": "https://unpkg.com/three@0.158.0/examples/jsm/"
      }
    }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        #three-container {
            position: relative;
            width: 100%;
            height: 600px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        
        .control-panel {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            min-width: 200px;
        }
        
        .info-panel {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-width: 300px;
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1002;
        }
        
        .view-mode-btn {
            @apply px-3 py-1 text-sm rounded-md transition-colors;
        }
        
        .view-mode-btn.active {
            @apply bg-blue-600 text-white;
        }
        
        .view-mode-btn:not(.active) {
            @apply bg-gray-200 text-gray-700 hover:bg-gray-300;
        }
    </style>
</head>
<body class="bg-gray-50" x-data="threejsWarehouse()" x-init="init()">
    <!-- Fallback initialization script -->
    <script>
        // Ensure threejsWarehouse is available before Alpine.js initializes
        if (typeof threejsWarehouse === 'undefined') {
            console.warn('threejsWarehouse not yet available, creating fallback');
            window.threejsWarehouse = function() {
                return {
                    selectedLocation: '',
                    warehouseData: { zones: [] },
                    warehouseStats: { totalBins: 0, occupiedBins: 0, utilization: 0, totalZones: 0, totalRacks: 10 },
                    loading: false,
                    showMoveProductModal: false,
                    selectedBin: { bin_id: '', bin_address: '' },
                    selectedBinInfo: null,
                    showInventoryPanel: false,
                    inventoryItems: [],
                    showRackCardPanel: false,
                    selectedRackInfo: null,
                    rackInventoryItems: { shelves: [], summary: { total_items: 0, total_value: 0 } },
                    viewMode: 'overview',
                    showEmpty: true,
                    showOccupied: true,
                    showLabels: true,
                    isInitialized: false,
                    
                    init() {
                        console.log('Fallback threejsWarehouse initialized');
                        this.isInitialized = true;
                        // Try to upgrade to the actual implementation
                        this.tryUpgrade();
                    },
                    
                    tryUpgrade() {
                        // Check if the actual implementation is available
                        if (window.threejsWarehouseActual && typeof window.threejsWarehouseActual === 'function') {
                            try {
                                console.log('Upgrading to actual threejsWarehouse implementation');
                                const actualImpl = window.threejsWarehouseActual();
                                // Merge the actual implementation with current state
                                Object.assign(this, actualImpl);
                                // Re-initialize with Three.js if available
                                if (typeof THREE !== 'undefined' && typeof THREE.OrbitControls !== 'undefined') {
                                    this.init();
                                }
                            } catch (error) {
                                console.warn('Failed to upgrade to actual implementation:', error);
                                // Create 2D fallback
                                this.create2DFallback();
                            }
                        } else {
                            // Try again later
                            setTimeout(() => this.tryUpgrade(), 1000);
                        }
                    },
                    
                    create2DFallback() {
                        const container = document.getElementById('three-container');
                        if (!container) return;
                        
                        container.innerHTML = `
                            <div class="flex items-center justify-center h-full bg-gradient-to-br from-blue-50 to-indigo-100">
                                <div class="text-center p-8">
                                    <div class="mb-6">
                                        <i class="fas fa-warehouse text-6xl text-blue-500 mb-4"></i>
                                    </div>
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">3D Warehouse Visualization</h3>
                                    <p class="text-gray-600 mb-4">Loading warehouse data...</p>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div class="bg-white p-3 rounded-lg shadow">
                                            <div class="font-semibold text-blue-600">📦 Total Bins</div>
                                            <div class="text-2xl font-bold">${this.warehouseStats.totalBins || 500}</div>
                                        </div>
                                        <div class="bg-white p-3 rounded-lg shadow">
                                            <div class="font-semibold text-green-600">🏗️ Total Racks</div>
                                            <div class="text-2xl font-bold">${this.warehouseStats.totalRacks || 10}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    },
                    
                    async loadWarehouseData() {
                        console.log('Fallback loadWarehouseData called');
                        if (!this.isInitialized) {
                            console.log('Not yet initialized, waiting...');
                            return;
                        }
                        // Create simple 2D visualization
                        this.create2DFallback();
                    },
                    
                    setViewMode(mode) {
                        this.viewMode = mode;
                    },
                    
                    updateVisibility() {
                        console.log('Fallback updateVisibility called');
                    },
                    
                    resetView() {
                        console.log('Fallback resetView called');
                    },
                    
                    selectBinForMovement() {
                        this.showMoveProductModal = true;
                    },
                    
                    closeRackCard() {
                        this.showRackCardPanel = false;
                    },
                    
                    closeInventoryPanel() {
                        this.showInventoryPanel = false;
                    },
                    
                    generateLocationReport() {
                        window.open('inventory-report.php?type=location', '_blank');
                    },
                    generateRackReport() {
                        window.open('inventory-report.php?type=rack', '_blank');
                    },
                    generateShelfReport() {
                        window.open('inventory-report.php?type=shelf', '_blank');
                    },
                    generateBinReport() {
                        window.open('inventory-report.php?type=bin', '_blank');
                    },
                    
                    // Additional methods that Alpine.js needs
                    selectBin(binData) {
                        this.selectedBinInfo = binData;
                    },
                    
                    showRackCard(rackData) {
                        this.selectedRackInfo = rackData;
                        this.showRackCardPanel = true;
                    },
                    
                    openInventoryPanel(binData) {
                        this.selectedBinInfo = binData;
                        this.inventoryItems = [];
                        this.showInventoryPanel = true;
                    }
                };
            };
        }
    </script>
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
                    <span class="text-lg text-gray-600">3D Warehouse</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="warehouse-config.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        <i class="fas fa-cog mr-2"></i>Configure Warehouse
                    </a>
                    <a href="inventory-visual.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        <i class="fas fa-th mr-2"></i>2D View
                    </a>
                    <a href="inventory.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Inventory
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-full mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Three.js 3D Warehouse Management</h1>
                <p class="text-gray-600 mt-2">Immersive 3D visualization of warehouse zones, racks, and bins</p>
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

        <!-- Three.js Container -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <div id="three-container">
                <!-- Loading Overlay -->
                <div class="loading-overlay" x-show="loading">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600">Loading 3D warehouse...</p>
                    </div>
                </div>
                
                <!-- Control Panel -->
                <div class="control-panel">
                    <h4 class="font-semibold text-gray-900 mb-3">View Controls</h4>
                    
                    <!-- Warehouse Structure Info -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Structure</label>
                        <div class="space-y-1 text-sm text-gray-600">
                            <div>Racks: <span x-text="warehouseStats.totalRacks || 10" class="font-medium"></span></div>
                            <div>Shelves: <span class="font-medium">5</span> per rack</div>
                            <div>Bins: <span class="font-medium">10</span> per shelf</div>
                            <div class="text-xs text-gray-500 mt-2">
                                Total capacity: <span x-text="(warehouseStats.totalRacks || 10) * 5 * 10" class="font-medium"></span> bins
                            </div>
                        </div>
                    </div>
                    
                    <!-- View Mode -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">View Mode</label>
                        <div class="flex space-x-1">
                            <button @click="setViewMode('overview')" 
                                    :class="['view-mode-btn', viewMode === 'overview' ? 'active' : '']">
                                Overview
                            </button>
                            <button @click="setViewMode('zone')" 
                                    :class="['view-mode-btn', viewMode === 'zone' ? 'active' : '']">
                                Zone
                            </button>
                            <button @click="setViewMode('rack')" 
                                    :class="['view-mode-btn', viewMode === 'rack' ? 'active' : '']">
                                Rack
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Show</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" x-model="showEmpty" @change="updateVisibility()" class="mr-2">
                                <span class="text-sm">Empty Bins</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" x-model="showOccupied" @change="updateVisibility()" class="mr-2">
                                <span class="text-sm">Occupied Bins</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" x-model="showLabels" @change="updateVisibility()" class="mr-2">
                                <span class="text-sm">Labels</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Reset View -->
                    <button @click="resetView()" class="w-full bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 text-sm">
                        <i class="fas fa-home mr-2"></i>Reset View
                    </button>
                </div>
                
                <!-- Info Panel -->
                <div class="info-panel" x-show="selectedBinInfo">
                    <h4 class="font-semibold text-gray-900 mb-2">Bin Information</h4>
                    <div class="space-y-1 text-sm">
                        <div><strong>Address:</strong> <span x-text="selectedBinInfo?.bin_address"></span></div>
                        <div><strong>Status:</strong> <span x-text="selectedBinInfo?.occupancy_status"></span></div>
                        <div><strong>Utilization:</strong> <span x-text="selectedBinInfo?.utilization_percentage + '%'"></span></div>
                        <div x-show="selectedBinInfo?.product_name">
                            <strong>Product:</strong> <span x-text="selectedBinInfo?.product_name"></span>
                        </div>
                        <div x-show="selectedBinInfo?.current_quantity">
                            <strong>Quantity:</strong> <span x-text="selectedBinInfo?.current_quantity"></span>
                        </div>
                    </div>
                    <button @click="selectBinForMovement()" 
                            class="w-full mt-3 bg-green-600 text-white px-3 py-2 rounded-md hover:bg-green-700 text-sm">
                        <i class="fas fa-arrows-alt mr-2"></i>Move Product Here
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-cubes text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Bins</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="warehouseStats.totalBins || 0"></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Occupied</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="warehouseStats.occupiedBins || 0"></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-percentage text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Utilization</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="warehouseStats.utilization + '%' || '0%'"></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <i class="fas fa-layer-group text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Zones</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="warehouseStats.totalZones || 0"></p>
                    </div>
                </div>
                
                <!-- Inventory Reports Section -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📊 Inventory Reports</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <button @click="generateLocationReport()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm">
                            <i class="fas fa-map-marker-alt mr-2"></i>By Location
                        </button>
                        <button @click="generateRackReport()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 text-sm">
                            <i class="fas fa-th-large mr-2"></i>By Rack
                        </button>
                        <button @click="generateShelfReport()" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 text-sm">
                            <i class="fas fa-layer-group mr-2"></i>By Shelf
                        </button>
                        <button @click="generateBinReport()" class="bg-orange-600 text-white px-4 py-2 rounded-md hover:bg-orange-700 text-sm">
                            <i class="fas fa-cube mr-2"></i>By Bin
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rack Card Panel -->
        <div x-show="showRackCardPanel" 
             x-transition:enter="transition-opacity duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100"
             class="fixed inset-y-0 left-0 w-96 bg-white shadow-2xl z-50 border-r border-gray-200">
            <div class="h-full flex flex-col">
                <!-- Panel Header -->
                <div class="bg-blue-600 text-white p-4 flex justify-between items-center">
                    <div>
                        <h3 class="font-semibold text-lg">🏗️ Rack Details</h3>
                        <p class="text-blue-100 text-sm" x-text="selectedRackInfo?.rack_code || 'Unknown Rack'"></p>
                    </div>
                    <button @click="closeRackCard()" class="text-white hover:text-blue-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <!-- Rack Summary -->
                <div class="p-4 border-b bg-gray-50">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Levels:</span>
                            <span class="font-medium ml-2" x-text="selectedRackInfo?.levels || 'N/A'"></span>
                        </div>
                        <div>
                            <span class="text-gray-600">Positions:</span>
                            <span class="font-medium ml-2" x-text="selectedRackInfo?.positions || 'N/A'"></span>
                        </div>
                        <div>
                            <span class="text-gray-600">Total Bins:</span>
                            <span class="font-medium ml-2" x-text="selectedRackInfo?.total_bins || 'N/A'"></span>
                        </div>
                        <div>
                            <span class="text-gray-600">Occupancy:</span>
                            <span class="font-medium ml-2 text-green-600">75%</span>
                        </div>
                    </div>
                </div>
                
                <!-- Loading State -->
                <div x-show="loading" class="flex-1 flex items-center justify-center">
                    <div class="text-center">
                        <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-gray-600">Loading rack inventory...</p>
                    </div>
                </div>
                
                <!-- Rack Inventory by Shelves -->
                <div x-show="!loading" class="flex-1 overflow-y-auto p-4">
                    <template x-if="rackInventoryItems.shelves && rackInventoryItems.shelves.length > 0">
                        <div class="space-y-6">
                            <template x-for="shelf in rackInventoryItems.shelves" :key="shelf.level">
                                <div class="bg-gray-50 rounded-lg p-4 border">
                                    <h4 class="font-medium text-gray-900 mb-3 flex items-center">
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm mr-2">L<span x-text="shelf.level"></span></span>
                                        <span x-text="shelf.level_name || ('Level ' + shelf.level)"></span>
                                    </h4>
                                    
                                    <div class="space-y-3">
                                        <template x-for="item in shelf.items" :key="item.sku">
                                            <div class="bg-white rounded p-3 border flex items-start space-x-3">
                                                <img :src="item.image_url" :alt="item.name" 
                                                     class="w-12 h-12 object-cover rounded bg-gray-200" 
                                                     @error="$event.target.src='https://via.placeholder.com/48x48?text=Item'">
                                                <div class="flex-1 min-w-0">
                                                    <h5 class="font-medium text-sm text-gray-900 truncate" x-text="item.name"></h5>
                                                    <p class="text-xs text-gray-600 font-mono" x-text="item.sku"></p>
                                                    <p class="text-xs text-gray-500" x-text="item.bin_address"></p>
                                                    <div class="mt-1 flex items-center justify-between">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" 
                                                              :class="item.quantity > 20 ? 'bg-green-100 text-green-800' : 
                                                                      item.quantity > 10 ? 'bg-yellow-100 text-yellow-800' : 
                                                                      'bg-red-100 text-red-800'">
                                                            <span x-text="item.quantity"></span> units
                                                        </span>
                                                        <span class="text-xs font-medium text-gray-900" x-text="'$' + (item.price || 0).toFixed(2)"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    
                    <template x-if="!rackInventoryItems.shelves || rackInventoryItems.shelves.length === 0">
                        <div class="text-center text-gray-500 mt-8">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-8l-1 1m0 0l-1 1m1-1v4M6 5l1 1v4m0 0l1 1m-1-1H4"></path>
                            </svg>
                            <p class="font-medium">No items in this rack</p>
                            <p class="text-sm">This rack is currently empty</p>
                        </div>
                    </template>
                </div>
                
                <!-- Panel Footer -->
                <div class="bg-gray-50 px-4 py-3 border-t">
                    <div class="text-sm text-gray-600 space-y-1">
                        <div class="flex justify-between">
                            <span>Total Items:</span>
                            <span class="font-medium" x-text="rackInventoryItems.summary?.total_items || 0"></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Total Value:</span>
                            <span class="font-medium text-green-600" x-text="'$' + (rackInventoryItems.summary?.total_value || 0).toLocaleString()"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Panel -->
        <div x-show="showInventoryPanel" 
             x-transition:enter="transition-opacity duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100"
             class="fixed inset-y-0 right-0 w-96 bg-white shadow-2xl z-50 border-l border-gray-200">
            <div class="h-full flex flex-col">
                <!-- Panel Header -->
                <div class="bg-blue-600 text-white p-4 flex justify-between items-center">
                    <div>
                        <h3 class="font-semibold text-lg">Bin Inventory</h3>
                        <p class="text-blue-100 text-sm" x-text="selectedBinInfo?.binId || 'Unknown Bin'"></p>
                    </div>
                    <button @click="closeInventoryPanel()" class="text-white hover:text-blue-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                                
                <!-- Loading State -->
                <div x-show="loading" class="flex-1 flex items-center justify-center">
                    <div class="text-center">
                        <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-gray-600">Loading inventory...</p>
                    </div>
                </div>
                                
                <!-- Inventory Items -->
                <div x-show="!loading" class="flex-1 overflow-y-auto p-4">
                    <template x-if="inventoryItems.length === 0">
                        <div class="text-center text-gray-500 mt-8">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-8l-1 1m0 0l-1 1m1-1v4M6 5l1 1v4m0 0l1 1m-1-1H4"></path>
                            </svg>
                            <p class="font-medium">This bin is empty</p>
                            <p class="text-sm">No items stored in this location</p>
                        </div>
                    </template>
                                    
                    <!-- Item List -->
                    <div class="space-y-3">
                        <template x-for="item in inventoryItems" :key="item.id">
                            <div class="bg-gray-50 rounded-lg p-4 border hover:shadow-md transition-shadow">
                                <div class="flex items-start space-x-3">
                                    <!-- Item Image -->
                                    <img :src="item.image_url" :alt="item.name" 
                                         class="w-16 h-16 object-cover rounded-lg bg-gray-200" 
                                         @error="$event.target.src='https://via.placeholder.com/64x64?text=No+Image'">
                                    
                                    <!-- Item Details -->
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-medium text-gray-900 truncate" x-text="item.name"></h4>
                                        <p class="text-sm text-gray-600 font-mono" x-text="'SKU: ' + item.sku"></p>
                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                                  :class="item.quantity > 10 ? 'bg-green-100 text-green-800' : 
                                                          item.quantity > 5 ? 'bg-yellow-100 text-yellow-800' : 
                                                          'bg-red-100 text-red-800'">
                                                <span x-text="item.quantity"></span> in stock
                                            </span>
                                            <span class="text-sm font-medium text-gray-900" x-text="'$' + (item.price || 0).toFixed(2)"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                                
                <!-- Panel Footer -->
                <div class="bg-gray-50 px-4 py-3 border-t">
                    <div class="flex justify-between items-center text-sm text-gray-600">
                        <span x-text="inventoryItems.length + ' item(s) total'"></span>
                        <span x-text="'Total Value: $' + inventoryItems.reduce((sum, item) => sum + (item.price * item.quantity), 0).toFixed(2)"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Location Selected -->
        <div x-show="!selectedLocation && !loading" class="text-center py-12">
            <i class="fas fa-warehouse text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Select a Warehouse</h3>
            <p class="text-gray-500">Choose a warehouse location to explore in 3D</p>
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">Selected Bin</label>
                            <input type="text" :value="selectedBin.bin_address" readonly 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-50">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Product</label>
                            <select name="product_id" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="">Select Product</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?> (<?= htmlspecialchars($product['sku'] ?? 'No SKU') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                            <input type="number" name="quantity" min="1" required 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                            <textarea name="notes" rows="3" 
                                      class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="showMoveProductModal = false" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
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

    <!-- Include the Three.js JavaScript first -->
    <script src="js/warehouse-threejs.js"></script>
    
    <!-- Three.js ES Module initialization -->
    <script type="module">
        import * as THREE from 'three';
        import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
        
        // Make THREE available globally with a custom wrapper
        window.THREE = {
            ...THREE,
            OrbitControls: OrbitControls
        };
        
        // Dispatch a custom event when Three.js is loaded
        window.dispatchEvent(new CustomEvent('threejs-loaded'));
        console.log('Three.js ES modules loaded with OrbitControls');
    </script>
    
    <script>
        // Add debugging and initialization checks
        let threeJSLoaded = false;
        
        // Wait for Three.js ES modules to load
        window.addEventListener('threejs-loaded', function() {
            threeJSLoaded = true;
            console.log('Three.js ES modules loaded successfully');
            initializeAfterThreeJS();
        });
        
        // Fallback timeout in case ES modules fail
        setTimeout(() => {
            if (!threeJSLoaded) {
                console.log('Three.js ES modules timeout, trying fallback');
                loadThreeJSFallback();
            }
        }, 5000);
        
        function initializeAfterThreeJS() {
            console.log('DOM Content Loaded');
            console.log('THREE available:', typeof THREE !== 'undefined');
            console.log('OrbitControls available:', typeof THREE !== 'undefined' && typeof THREE.OrbitControls !== 'undefined');
            console.log('threejsWarehouse available:', typeof threejsWarehouse !== 'undefined');
            
            // Check if container exists
            const container = document.getElementById('three-container');
            console.log('Three container found:', !!container);
            
            if (typeof THREE === 'undefined') {
                console.error('THREE.js not loaded!');
                loadThreeJSFallback();
                return;
            }
            
            if (typeof THREE.OrbitControls === 'undefined') {
                console.error('OrbitControls not loaded!');
                loadThreeJSFallback();
                return;
            }
            
            // Check if threejsWarehouse function is available
            if (typeof threejsWarehouse === 'undefined') {
                console.error('threejsWarehouse function not available!');
                // The fallback function should already be created in the HTML
                return;
            }
            
            // Initialize the main application
            setupThreeJSWarehouse();
        }
        
        function loadThreeJSFallback() {
            console.log('Loading Three.js fallback...');
            
            // Load fallback Three.js (older version that works reliably)
            const threeScript = document.createElement('script');
            threeScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js';
            threeScript.onload = function() {
                // Load OrbitControls after Three.js
                const controlsScript = document.createElement('script');
                controlsScript.src = 'https://threejs.org/examples/js/controls/OrbitControls.js';
                controlsScript.onload = function() {
                    console.log('Fallback Three.js and OrbitControls loaded');
                    setupThreeJSWarehouse();
                };
                controlsScript.onerror = function() {
                    console.error('Failed to load OrbitControls fallback');
                    createBasicFallback();
                };
                document.head.appendChild(controlsScript);
            };
            threeScript.onerror = function() {
                console.error('Failed to load Three.js fallback');
                createBasicFallback();
            };
            document.head.appendChild(threeScript);
        }
        
        function setupThreeJSWarehouse() {
            // Extend the threejsWarehouse function with reporting capabilities
            const originalWarehouse = window.threejsWarehouse;
            if (typeof originalWarehouse !== 'function') {
                console.error('threejsWarehouse function not found!');
                createBasicFallback();
                return;
            }
            
            window.threejsWarehouse = function() {
                const warehouse = originalWarehouse();
                
                // Add reporting methods
                warehouse.generateLocationReport = function() {
                    this.openReportWindow('location', 'Location Inventory Report');
                };
                
                warehouse.generateRackReport = function() {
                    this.openReportWindow('rack', 'Rack Inventory Report');
                };
                
                warehouse.generateShelfReport = function() {
                    this.openReportWindow('shelf', 'Shelf Inventory Report');
                };
                
                warehouse.generateBinReport = function() {
                    this.openReportWindow('bin', 'Bin Inventory Report');
                };
                
                warehouse.openReportWindow = function(type, title) {
                    const reportUrl = `inventory-report.php?type=${type}&location=${this.selectedLocation || ''}`;
                    const reportWindow = window.open(
                        reportUrl, 
                        'inventory_report',
                        'width=1200,height=800,scrollbars=yes,resizable=yes'
                    );
                    
                    if (reportWindow) {
                        reportWindow.focus();
                    } else {
                        alert('Please allow popups to view the inventory report.');
                    }
                };
                
                return warehouse;
            };
        }
        
        function createBasicFallback() {
            console.log('Creating basic 2D fallback visualization...');
            
            const container = document.getElementById('three-container');
            if (!container) {
                console.error('Container not found!');
                return;
            }
            
            // Create basic 2D fallback
            container.innerHTML = `
                <div class="flex items-center justify-center h-full bg-gradient-to-br from-blue-50 to-indigo-100">
                    <div class="text-center p-8">
                        <div class="mb-6">
                            <i class="fas fa-warehouse text-6xl text-blue-500 mb-4"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">3D Warehouse Visualization</h3>
                        <p class="text-gray-600 mb-4">3D rendering is not available in your browser.</p>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="bg-white p-3 rounded-lg shadow">
                                <div class="font-semibold text-blue-600">📦 Total Bins</div>
                                <div class="text-2xl font-bold">500</div>
                            </div>
                            <div class="bg-white p-3 rounded-lg shadow">
                                <div class="font-semibold text-green-600">🏗️ Total Racks</div>
                                <div class="text-2xl font-bold">10</div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="inventory-visual.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                <i class="fas fa-th mr-2"></i>Switch to 2D View
                            </a>
                        </div>
                    </div>
                </div>
            `;
            
            // Create a basic warehouse object for Alpine.js
            window.threejsWarehouse = function() {
                return {
                    selectedLocation: '',
                    warehouseData: { zones: [] },
                    loading: false,
                    showMoveProductModal: false,
                    selectedBin: { bin_id: '', bin_address: '' },
                    
                    init() {
                        console.log('Initialized 2D fallback warehouse view');
                    },
                    
                    async loadWarehouseData() {
                        console.log('Loading warehouse data (2D fallback)');
                        this.loading = false;
                    },
                    
                    generateLocationReport() { 
                        window.open('inventory-report.php?type=location', '_blank');
                    },
                    generateRackReport() { 
                        window.open('inventory-report.php?type=rack', '_blank');
                    },
                    generateShelfReport() { 
                        window.open('inventory-report.php?type=shelf', '_blank');
                    },
                    generateBinReport() { 
                        window.open('inventory-report.php?type=bin', '_blank');
                    }
                };
            };
        }
        
        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                // Wait a bit for ES modules to potentially load
                setTimeout(() => {
                    if (!threeJSLoaded) {
                        initializeAfterThreeJS();
                    }
                }, 1000);
            });
        } else {
            // DOM already loaded
            setTimeout(() => {
                if (!threeJSLoaded) {
                    initializeAfterThreeJS();
                }
            }, 1000);
        }
    </script>
</body>
</html>