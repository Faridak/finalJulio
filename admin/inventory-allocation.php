<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$poId = intval($_GET['po_id'] ?? 0);

if (!$poId) {
    die("Invalid purchase order ID");
}

try {
    // Get PO details
    $stmt = $pdo->prepare("
        SELECT po.*, s.company_name, il.location_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        JOIN inventory_locations il ON po.location_id = il.id
        WHERE po.id = ?
    ");
    $stmt->execute([$poId]);
    $po = $stmt->fetch();
    
    if (!$po) {
        die("Purchase order not found");
    }
    
    // Get PO items
    $stmt = $pdo->prepare("
        SELECT poi.*, sp.product_name, sp.supplier_sku as sku, p.name as product_name_db
        FROM purchase_order_items poi
        LEFT JOIN supplier_products sp ON poi.supplier_product_id = sp.id
        LEFT JOIN products p ON sp.product_id = p.id
        WHERE poi.purchase_order_id = ?
    ");
    $stmt->execute([$poId]);
    $items = $stmt->fetchAll();
    
    // Get empty bins
    $stmt = $pdo->prepare("
        SELECT wb.id, wb.bin_code, wb.status,
               ws.shelf_level,
               wr.rack_code, wr.name as rack_name
        FROM warehouse_bins wb
        JOIN warehouse_shelves ws ON wb.shelf_id = ws.id
        JOIN warehouse_racks wr ON ws.rack_id = wr.id
        WHERE wb.status IN ('empty', 'partial')
        ORDER BY wr.rack_code, ws.shelf_level, wb.bin_position
    ");
    $stmt->execute();
    $bins = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Allocation - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bin-card {
            transition: all 0.2s ease;
        }
        .bin-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .bin-empty { background-color: #f3f4f6; }
        .bin-partial { background-color: #fef3c7; }
        .bin-full { background-color: #d1fae5; }
        .bin-blocked { background-color: #fee2e2; }
    </style>
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
                    <a href="purchase-orders.php" class="text-lg text-blue-600 hover:text-blue-700">Purchase Orders</a>
                    <span class="text-gray-400">|</span>
                    <span class="text-lg text-gray-600">Inventory Allocation</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8" x-data="inventoryAllocation(<?= $poId ?>)">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Inventory Allocation</h1>
                <p class="text-gray-600 mt-2">Allocate received items from Purchase Order <?= htmlspecialchars($po['po_number']) ?></p>
            </div>
            <button @click="allocateAllItems()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition duration-200">
                <i class="fas fa-check-circle mr-2"></i>Complete Allocation
            </button>
        </div>

        <!-- PO Information -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Purchase Order</h3>
                    <p class="text-gray-600">PO Number: <span class="font-medium"><?= htmlspecialchars($po['po_number']) ?></span></p>
                    <p class="text-gray-600">Status: <span class="font-medium"><?= ucfirst(str_replace('_', ' ', $po['status'])) ?></span></p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Supplier</h3>
                    <p class="text-gray-600">Company: <span class="font-medium"><?= htmlspecialchars($po['company_name']) ?></span></p>
                    <p class="text-gray-600">Location: <span class="font-medium"><?= htmlspecialchars($po['location_name']) ?></span></p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Order Details</h3>
                    <p class="text-gray-600">Order Date: <span class="font-medium"><?= date('M j, Y', strtotime($po['order_date'])) ?></span></p>
                    <?php if ($po['expected_delivery_date']): ?>
                        <p class="text-gray-600">Expected: <span class="font-medium"><?= date('M j, Y', strtotime($po['expected_delivery_date'])) ?></span></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Items and Bins -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- PO Items -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Purchase Order Items</h2>
                <div class="space-y-4">
                    <?php foreach ($items as $item): ?>
                        <div class="border border-gray-200 rounded-lg p-4" 
                             x-bind:class="{'bg-blue-50': selectedItemId === <?= $item['id'] ?>}"
                             @click="selectItem(<?= $item['id'] ?>, <?= $item['quantity_ordered'] ?>)">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-medium text-gray-900">
                                        <?= htmlspecialchars($item['product_name'] ?? $item['product_name_db'] ?? 'Unknown Product') ?>
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        SKU: <?= htmlspecialchars($item['sku'] ?? $item['supplier_sku'] ?? 'N/A') ?>
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Ordered: <span class="font-medium"><?= $item['quantity_ordered'] ?></span> |
                                        Received: <span class="font-medium"><?= $item['quantity_received'] ?></span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= $item['quantity_ordered'] ?> units
                                    </span>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center">
                                <input type="number" 
                                       x-model="itemQuantities[<?= $item['id'] ?>]" 
                                       min="0" 
                                       max="<?= $item['quantity_ordered'] ?>"
                                       class="w-20 border border-gray-300 rounded-md px-3 py-1 text-sm"
                                       placeholder="Qty">
                                <span class="ml-2 text-sm text-gray-600">to allocate</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Warehouse Bins -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Warehouse Bins</h2>
                <div class="mb-4">
                    <input type="text" 
                           x-model="binSearch" 
                           placeholder="Search bins..." 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[500px] overflow-y-auto">
                    <?php foreach ($bins as $bin): ?>
                        <div class="bin-card border rounded-lg p-3 cursor-pointer <?= 'bin-' . $bin['status'] ?>"
                             x-bind:class="{
                                 'ring-2 ring-blue-500': selectedBinId === <?= $bin['id'] ?>,
                                 'opacity-50': binAllocations[<?= $bin['id'] ?>] >= 100
                             }"
                             @click="selectBin(<?= $bin['id'] ?>)"
                             x-show="binSearch === '' || '<?= strtolower($bin['bin_code']) ?>'.includes(binSearch.toLowerCase())">
                            <div class="text-center">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($bin['bin_code']) ?></div>
                                <div class="text-xs text-gray-600 mt-1">
                                    <?= htmlspecialchars($bin['rack_code']) ?> - Level <?= $bin['shelf_level'] ?>
                                </div>
                                <div class="text-xs mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        <?php
                                        switch($bin['status']) {
                                            case 'empty': echo 'bg-gray-100 text-gray-800'; break;
                                            case 'partial': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'full': echo 'bg-green-100 text-green-800'; break;
                                            case 'blocked': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= ucfirst($bin['status']) ?>
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1" x-show="binAllocations[<?= $bin['id'] ?>] > 0">
                                    <span x-text="binAllocations[<?= $bin['id'] ?>]"></span>% allocated
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Allocation Summary -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8" x-show="selectedItemId && selectedBinId">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Allocate Items</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-medium text-gray-900 mb-2">Selected Item</h3>
                    <template x-if="selectedItem">
                        <div>
                            <p class="text-sm text-gray-600" x-text="selectedItem.name"></p>
                            <p class="text-sm text-gray-600">Available: <span x-text="selectedItem.available"></span></p>
                        </div>
                    </template>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-medium text-gray-900 mb-2">Selected Bin</h3>
                    <template x-if="selectedBin">
                        <div>
                            <p class="text-sm text-gray-600" x-text="selectedBin.code"></p>
                            <p class="text-sm text-gray-600">Status: <span x-text="selectedBin.status"></span></p>
                        </div>
                    </template>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-medium text-gray-900 mb-2">Allocation</h3>
                    <div class="flex items-center">
                        <input type="number" 
                               x-model="allocationQuantity" 
                               min="1"
                               :max="selectedItem?.available"
                               class="w-20 border border-gray-300 rounded-md px-3 py-1 text-sm">
                        <span class="ml-2 text-sm text-gray-600">units</span>
                        <button @click="allocateItems()" 
                                class="ml-3 bg-blue-600 text-white px-3 py-1 rounded-md text-sm hover:bg-blue-700">
                            Allocate
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function inventoryAllocation(poId) {
            return {
                poId: poId,
                selectedItemId: null,
                selectedBinId: null,
                selectedItem: null,
                selectedBin: null,
                allocationQuantity: 1,
                binSearch: '',
                itemQuantities: {},
                binAllocations: {},
                allocations: [], // Track all allocations
                
                init() {
                    // Initialize item quantities
                    <?php foreach ($items as $item): ?>
                        this.itemQuantities[<?= $item['id'] ?>] = <?= $item['quantity_ordered'] ?>;
                    <?php endforeach; ?>
                    
                    // Initialize bin allocations
                    <?php foreach ($bins as $bin): ?>
                        this.binAllocations[<?= $bin['id'] ?>] = 0;
                    <?php endforeach; ?>
                },
                
                selectItem(itemId, availableQuantity) {
                    this.selectedItemId = itemId;
                    this.selectedItem = {
                        id: itemId,
                        name: '<?= htmlspecialchars($item['product_name'] ?? $item['product_name_db'] ?? 'Unknown Product') ?>',
                        available: availableQuantity
                    };
                    this.allocationQuantity = Math.min(availableQuantity, 10); // Default to 10 or available
                },
                
                selectBin(binId) {
                    this.selectedBinId = binId;
                    // Find the bin details
                    const bins = <?php echo json_encode($bins); ?>;
                    const bin = bins.find(b => b.id == binId);
                    if (bin) {
                        this.selectedBin = {
                            id: binId,
                            code: bin.bin_code,
                            status: bin.status
                        };
                    }
                },
                
                allocateItems() {
                    if (!this.selectedItemId || !this.selectedBinId || this.allocationQuantity <= 0) {
                        alert('Please select an item, bin, and quantity');
                        return;
                    }
                    
                    // Add to allocations array
                    this.allocations.push({
                        item_id: this.selectedItemId,
                        bin_id: this.selectedBinId,
                        quantity: parseInt(this.allocationQuantity)
                    });
                    
                    // Update UI
                    this.itemQuantities[this.selectedItemId] -= this.allocationQuantity;
                    this.binAllocations[this.selectedBinId] = (this.binAllocations[this.selectedBinId] || 0) + 10;
                    
                    // Reset selection
                    this.selectedItemId = null;
                    this.selectedBinId = null;
                    this.allocationQuantity = 1;
                    
                    alert(`Allocated ${this.allocationQuantity} units to bin ${this.selectedBin.code}`);
                },
                
                allocateAllItems() {
                    if (this.allocations.length === 0) {
                        alert('Please allocate some items first');
                        return;
                    }
                    
                    if (confirm('Are you sure you want to complete the allocation for this purchase order?')) {
                        // Send allocations to server
                        fetch('api/po-inventory-allocation.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'allocate_items_to_bins',
                                po_id: this.poId,
                                allocations: this.allocations
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Items allocated successfully!');
                                window.location.href = 'purchase-orders.php';
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            alert('Error allocating items: ' + error.message);
                        });
                    }
                }
            }
        }
    </script>
</body>
</html>