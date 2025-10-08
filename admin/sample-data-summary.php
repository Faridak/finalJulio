<?php
// Summary of sample data created for testing the system
$host = 'localhost';
$dbname = 'finalJulio';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sample Data Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Sample Data Summary</h1>
        <p class="lead">This page summarizes the sample data created for testing the purchase order allocation system.</p>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h2>Database Statistics</h2>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get counts
                        $counts = [];
                        $tables = ['purchase_orders', 'warehouse_bins', 'warehouse_inventory', 'po_inventory_allocations'];
                        foreach ($tables as $table) {
                            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                            $result = $stmt->fetch();
                            $counts[$table] = $result['count'];
                        }
                        ?>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Purchase Orders
                                <span class="badge bg-primary rounded-pill"><?php echo $counts['purchase_orders']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Warehouse Bins
                                <span class="badge bg-primary rounded-pill"><?php echo $counts['warehouse_bins']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Warehouse Inventory Items
                                <span class="badge bg-primary rounded-pill"><?php echo $counts['warehouse_inventory']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                PO Inventory Allocations
                                <span class="badge bg-primary rounded-pill"><?php echo $counts['po_inventory_allocations']; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h2>Sample Purchase Orders</h2>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->query("
                            SELECT po.id, po.po_number, po.status, COUNT(poi.id) as item_count
                            FROM purchase_orders po
                            LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
                            GROUP BY po.id, po.po_number, po.status
                            ORDER BY po.id
                        ");
                        $purchaseOrders = $stmt->fetchAll();
                        ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>PO Number</th>
                                        <th>Status</th>
                                        <th>Items</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($purchaseOrders as $po): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $po['status'] == 'received' ? 'success' : 
                                                    ($po['status'] == 'confirmed' ? 'primary' : 'secondary'); ?>">
                                                <?php echo ucfirst($po['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $po['item_count']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h2>Allocation Details</h2>
            </div>
            <div class="card-body">
                <?php
                $stmt = $pdo->query("
                    SELECT 
                        po.po_number,
                        poi.product_name,
                        pia.quantity_allocated,
                        wb.bin_code,
                        pia.allocated_at
                    FROM po_inventory_allocations pia
                    JOIN purchase_orders po ON pia.purchase_order_id = po.id
                    JOIN purchase_order_items poi ON pia.purchase_order_item_id = poi.id
                    JOIN warehouse_bins wb ON pia.warehouse_bin_id = wb.id
                    ORDER BY po.id, pia.id
                ");
                $allocations = $stmt->fetchAll();
                ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Bin</th>
                                <th>Allocated At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allocations as $allocation): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($allocation['po_number']); ?></td>
                                <td><?php echo htmlspecialchars($allocation['product_name']); ?></td>
                                <td><?php echo $allocation['quantity_allocated']; ?></td>
                                <td><?php echo htmlspecialchars($allocation['bin_code']); ?></td>
                                <td><?php echo $allocation['allocated_at']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h2>How to Test the System</h2>
            </div>
            <div class="card-body">
                <ol>
                    <li><strong>View 3D Visualization:</strong> Open <a href="simple-warehouse.html" target="_blank">simple-warehouse.html</a> to see the warehouse with racks, shelves, and bins</li>
                    <li><strong>View Allocations:</strong> Visit <a href="inventory-allocation.php" target="_blank">inventory-allocation.php</a> to see the allocation interface</li>
                    <li><strong>Test PO Allocation:</strong> Visit <a href="test-po-allocation.php" target="_blank">test-po-allocation.php</a> to see the sample POs and their allocation status</li>
                    <li><strong>Check Database:</strong> Use phpMyAdmin or MySQL command line to examine the tables:
                        <ul>
                            <li><code>purchase_orders</code> - Contains the purchase orders</li>
                            <li><code>purchase_order_items</code> - Contains the items in each PO</li>
                            <li><code>warehouse_racks</code>, <code>warehouse_shelves</code>, <code>warehouse_bins</code> - Warehouse structure</li>
                            <li><code>warehouse_inventory</code> - Items stored in bins</li>
                            <li><code>po_inventory_allocations</code> - Linking table between PO items and warehouse bins</li>
                        </ul>
                    </li>
                </ol>
                
                <h3>Sample Data Created:</h3>
                <ul>
                    <li><strong>3 Purchase Orders</strong> with various statuses:
                        <ul>
                            <li>PO-2025-001: Confirmed with 3 items, already allocated</li>
                            <li>PO-2025-002: Received with 2 items, allocated via API test</li>
                            <li>PO-2025-003: Confirmed with 3 items, not yet allocated</li>
                        </ul>
                    </li>
                    <li><strong>8 Purchase Order Items</strong> across the orders</li>
                    <li><strong>6 Inventory Allocations</strong> linking PO items to warehouse bins</li>
                    <li><strong>150 Warehouse Bins</strong> organized in racks and shelves</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>