<?php
// Create sample purchase orders and allocate them to inventory locations
$host = 'localhost';
$dbname = 'finalJulio';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    echo "Database connection successful\n";
    
    // First, let's check if we have suppliers and inventory locations
    $stmt = $pdo->query("SELECT id FROM suppliers LIMIT 1");
    $supplier = $stmt->fetch();
    
    if (!$supplier) {
        echo "No suppliers found. Creating sample supplier...\n";
        $stmt = $pdo->prepare("INSERT IGNORE INTO suppliers (supplier_code, company_name, contact_person, email, phone, website, address_line1, city, state, postal_code, country_code, payment_terms, lead_time_days, minimum_order_amount, quality_rating, delivery_rating, status, preferred_supplier) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['SUPP001', 'Sample Supplier', 'John Doe', 'john@sample.com', '+1-555-0123', 'https://sample.com', '123 Sample St', 'Sample City', 'SC', '12345', 'USA', 'net_30', 5, 100.00, 4.5, 4.5, 'active', TRUE]);
        $supplierId = $pdo->lastInsertId();
    } else {
        $supplierId = $supplier['id'];
    }
    
    $stmt = $pdo->query("SELECT id FROM inventory_locations LIMIT 1");
    $location = $stmt->fetch();
    
    if (!$location) {
        echo "No inventory locations found. Creating sample location...\n";
        $stmt = $pdo->prepare("INSERT IGNORE INTO inventory_locations (location_code, location_name, location_type, address_line1, city, state, postal_code, country_code, manager_name, phone, total_capacity, current_utilization, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['WH001', 'Main Warehouse', 'warehouse', '1000 Warehouse Drive', 'Dallas', 'TX', '75201', 'USA', 'Robert Johnson', '+1-555-0201', 10000, 65.5, 'active']);
        $locationId = $pdo->lastInsertId();
    } else {
        $locationId = $location['id'];
    }
    
    // Create sample purchase orders
    echo "Creating sample purchase orders...\n";
    
    $samplePOs = [
        [
            'po_number' => 'PO-2025-001',
            'supplier_id' => $supplierId,
            'location_id' => $locationId,
            'order_date' => date('Y-m-d'),
            'expected_delivery_date' => date('Y-m-d', strtotime('+7 days')),
            'notes' => 'First sample purchase order',
            'items' => [
                ['product_name' => 'Bluetooth Headphones', 'supplier_sku' => 'BT-001', 'quantity_ordered' => 50, 'unit_cost' => 45.00],
                ['product_name' => 'Smart Fitness Watch', 'supplier_sku' => 'SW-002', 'quantity_ordered' => 30, 'unit_cost' => 95.00],
                ['product_name' => 'Wireless Phone Charger', 'supplier_sku' => 'CH-003', 'quantity_ordered' => 100, 'unit_cost' => 15.00]
            ]
        ],
        [
            'po_number' => 'PO-2025-002',
            'supplier_id' => $supplierId,
            'location_id' => $locationId,
            'order_date' => date('Y-m-d', strtotime('-2 days')),
            'expected_delivery_date' => date('Y-m-d', strtotime('+5 days')),
            'notes' => 'Second sample purchase order',
            'items' => [
                ['product_name' => 'Organic Cotton T-Shirt', 'supplier_sku' => 'TS-001', 'quantity_ordered' => 200, 'unit_cost' => 8.00],
                ['product_name' => 'Classic Denim Jacket', 'supplier_sku' => 'DJ-002', 'quantity_ordered' => 50, 'unit_cost' => 25.00]
            ]
        ],
        [
            'po_number' => 'PO-2025-003',
            'supplier_id' => $supplierId,
            'location_id' => $locationId,
            'order_date' => date('Y-m-d', strtotime('-1 day')),
            'expected_delivery_date' => date('Y-m-d', strtotime('+3 days')),
            'notes' => 'Third sample purchase order',
            'items' => [
                ['product_name' => 'Ceramic Coffee Mug Set', 'supplier_sku' => 'MUG-001', 'quantity_ordered' => 100, 'unit_cost' => 12.00],
                ['product_name' => 'Premium Yoga Mat', 'supplier_sku' => 'YM-001', 'quantity_ordered' => 75, 'unit_cost' => 20.00],
                ['product_name' => 'Stainless Steel Water Bottle', 'supplier_sku' => 'WB-002', 'quantity_ordered' => 150, 'unit_cost' => 12.00]
            ]
        ]
    ];
    
    $createdPOs = [];
    
    foreach ($samplePOs as $poData) {
        // Calculate totals
        $subtotal = 0;
        foreach ($poData['items'] as $item) {
            $subtotal += $item['quantity_ordered'] * $item['unit_cost'];
        }
        
        $taxAmount = $subtotal * 0.08; // 8% tax
        $shippingCost = 25.00;
        $totalAmount = $subtotal + $taxAmount + $shippingCost;
        
        // Insert purchase order
        $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_number, supplier_id, location_id, order_date, expected_delivery_date, subtotal, tax_amount, shipping_cost, total_amount, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $poData['po_number'],
            $poData['supplier_id'],
            $poData['location_id'],
            $poData['order_date'],
            $poData['expected_delivery_date'],
            $subtotal,
            $taxAmount,
            $shippingCost,
            $totalAmount,
            $poData['notes'],
            'confirmed' // Start with confirmed status so we can allocate
        ]);
        
        $poId = $pdo->lastInsertId();
        $createdPOs[] = $poId;
        
        echo "Created PO: {$poData['po_number']} (ID: $poId)\n";
        
        // Insert purchase order items
        foreach ($poData['items'] as $item) {
            $stmt = $pdo->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_name, supplier_sku, quantity_ordered, unit_cost, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $poId,
                $item['product_name'],
                $item['supplier_sku'],
                $item['quantity_ordered'],
                $item['unit_cost'],
                'pending'
            ]);
            
            $itemId = $pdo->lastInsertId();
            echo "  - Added item: {$item['product_name']} (ID: $itemId)\n";
        }
    }
    
    echo "Sample purchase orders created successfully!\n";
    
    // Now let's allocate some of these POs to inventory locations
    echo "Allocating purchase orders to inventory locations...\n";
    
    // Get some available bins for allocation
    $stmt = $pdo->query("SELECT id FROM warehouse_bins WHERE status = 'empty' OR status = 'partial' LIMIT 20");
    $bins = $stmt->fetchAll();
    
    if (count($bins) < 10) {
        echo "Not enough bins available for allocation. Creating more bins...\n";
        // We'll need to create more bins if needed
        $stmt = $pdo->query("SELECT id FROM warehouse_shelves LIMIT 1");
        $shelf = $stmt->fetch();
        
        if ($shelf) {
            $shelfId = $shelf['id'];
            for ($i = 1; $i <= 20; $i++) {
                $binCode = "AUTO-B" . str_pad($i, 3, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT IGNORE INTO warehouse_bins (shelf_id, bin_position, bin_code, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$shelfId, $i + 100, $binCode, 'empty']);
            }
            
            // Refresh bin list
            $stmt = $pdo->query("SELECT id FROM warehouse_bins WHERE status = 'empty' OR status = 'partial' LIMIT 20");
            $bins = $stmt->fetchAll();
        }
    }
    
    // Allocate items from the first PO
    $firstPOId = $createdPOs[0];
    $stmt = $pdo->prepare("SELECT id, product_name, quantity_ordered FROM purchase_order_items WHERE purchase_order_id = ?");
    $stmt->execute([$firstPOId]);
    $poItems = $stmt->fetchAll();
    
    $binIndex = 0;
    foreach ($poItems as $item) {
        if ($binIndex >= count($bins)) {
            break;
        }
        
        $binId = $bins[$binIndex]['id'];
        $quantity = min($item['quantity_ordered'], 50); // Allocate up to 50 items per bin
        
        // Insert allocation record
        $stmt = $pdo->prepare("INSERT INTO po_inventory_allocations (purchase_order_id, purchase_order_item_id, warehouse_bin_id, quantity_allocated) VALUES (?, ?, ?, ?)");
        $stmt->execute([$firstPOId, $item['id'], $binId, $quantity]);
        
        // Update bin status
        $stmt = $pdo->prepare("UPDATE warehouse_bins SET status = 'partial' WHERE id = ?");
        $stmt->execute([$binId]);
        
        // Add inventory items to the bin
        $stmt = $pdo->prepare("INSERT INTO warehouse_inventory (bin_id, item_name, sku, quantity, date_arrived) VALUES (?, ?, ?, ?, ?)");
        $sku = "SKU-" . strtoupper(substr(md5(uniqid()), 0, 6));
        $stmt->execute([$binId, $item['product_name'], $sku, $quantity, date('Y-m-d')]);
        
        echo "Allocated {$quantity} of {$item['product_name']} to bin ID: {$binId}\n";
        
        $binIndex++;
    }
    
    // Update PO status to 'received'
    $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'received', actual_delivery_date = ? WHERE id = ?");
    $stmt->execute([date('Y-m-d'), $firstPOId]);
    
    // Update PO items status
    $stmt = $pdo->prepare("UPDATE purchase_order_items SET status = 'received', quantity_received = quantity_ordered WHERE purchase_order_id = ?");
    $stmt->execute([$firstPOId]);
    
    echo "Successfully allocated first purchase order (ID: $firstPOId) to inventory locations!\n";
    
    // Let's also mark the second PO as 'received' but not allocate it yet
    $secondPOId = $createdPOs[1];
    $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'received', actual_delivery_date = ? WHERE id = ?");
    $stmt->execute([date('Y-m-d'), $secondPOId]);
    
    echo "Marked second purchase order (ID: $secondPOId) as received (not yet allocated).\n";
    
    echo "\nSample data creation completed successfully!\n";
    echo "You can now test the inventory allocation system with these sample purchase orders.\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>