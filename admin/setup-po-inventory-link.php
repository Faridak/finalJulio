<?php
// Simplified database configuration for VentDepot
$host = 'localhost';
$dbname = 'finalJulio';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "Database connection successful\n";
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

try {
    // Create table to link purchase orders with warehouse inventory allocations
    $sql = "CREATE TABLE IF NOT EXISTS po_inventory_allocations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_order_id INT NOT NULL,
        purchase_order_item_id INT NOT NULL,
        warehouse_bin_id INT NOT NULL,
        quantity_allocated INT NOT NULL,
        allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        allocated_by INT DEFAULT NULL,
        
        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items(id) ON DELETE CASCADE,
        FOREIGN KEY (warehouse_bin_id) REFERENCES warehouse_bins(id) ON DELETE CASCADE,
        FOREIGN KEY (allocated_by) REFERENCES users(id) ON DELETE SET NULL,
        
        UNIQUE KEY unique_allocation (purchase_order_item_id, warehouse_bin_id)
    )";
    
    $pdo->exec($sql);
    echo "Created po_inventory_allocations table\n";
    
    // Add indexes for performance
    $indexes = [
        "CREATE INDEX idx_po_inventory_po ON po_inventory_allocations(purchase_order_id)",
        "CREATE INDEX idx_po_inventory_item ON po_inventory_allocations(purchase_order_item_id)",
        "CREATE INDEX idx_po_inventory_bin ON po_inventory_allocations(warehouse_bin_id)",
        "CREATE INDEX idx_po_inventory_date ON po_inventory_allocations(allocated_at)"
    ];
    
    foreach ($indexes as $index) {
        $pdo->exec($index);
    }
    echo "Created indexes for po_inventory_allocations table\n";
    
    // Add a column to track if a PO item has been fully allocated
    try {
        $pdo->exec("ALTER TABLE purchase_order_items ADD COLUMN fully_allocated BOOLEAN DEFAULT FALSE");
        echo "Added fully_allocated column to purchase_order_items table\n";
    } catch (Exception $e) {
        // Column might already exist
        echo "fully_allocated column already exists or error: " . $e->getMessage() . "\n";
    }
    
    echo "Purchase order to inventory linking setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error setting up PO inventory linking: " . $e->getMessage() . "\n";
}
?>