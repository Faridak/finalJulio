<?php
require_once 'config/database.php';

// Insert sample inventory locations
$locationsSql = "
INSERT IGNORE INTO inventory_locations (id, location_code, location_name, location_type, address_line1, city, state, postal_code, country_code, manager_name, phone, total_capacity, current_utilization, status) VALUES
(1, 'WH001', 'Main Warehouse', 'warehouse', '1000 Warehouse Drive', 'Dallas', 'TX', '75201', 'USA', 'Robert Johnson', '+1-555-0201', 10000, 65.5, 'active'),
(2, 'WH002', 'West Coast Distribution', 'warehouse', '2000 Pacific Blvd', 'Los Angeles', 'CA', '90001', 'USA', 'Lisa Chen', '+1-555-0202', 8000, 72.3, 'active'),
(3, 'WH003', 'East Coast Hub', 'warehouse', '3000 Atlantic Ave', 'Atlanta', 'GA', '30301', 'USA', 'Mark Williams', '+1-555-0203', 6000, 58.7, 'active'),
(4, 'STORE001', 'Flagship Store', 'store', '100 Main Street', 'New York', 'NY', '10001', 'USA', 'Jennifer Davis', '+1-555-0204', 500, 80.0, 'active'),
(5, 'DROP001', 'Dropship Virtual', 'dropship', 'Virtual Location', 'Virtual', 'Virtual', '00000', 'USA', 'System', 'N/A', 999999, 0.0, 'active')
";

try {
    $pdo->exec($locationsSql);
    echo "Sample inventory locations inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting inventory locations: " . $e->getMessage() . "\n";
}

// Insert sample suppliers
$suppliersSql = "
INSERT IGNORE INTO suppliers (id, supplier_code, company_name, contact_person, email, phone, website, address_line1, city, state, postal_code, country_code, payment_terms, lead_time_days, minimum_order_amount, quality_rating, delivery_rating, status, preferred_supplier) VALUES
(1, 'TECH001', 'TechSource Electronics', 'John Smith', 'john@techsource.com', '+1-555-0101', 'https://techsource.com', '123 Tech Street', 'San Francisco', 'CA', '94105', 'USA', 'net_30', 5, 500.00, 4.8, 4.9, 'active', TRUE),
(2, 'FASH002', 'Fashion Forward Wholesale', 'Sarah Johnson', 'sarah@fashionforward.com', '+1-555-0102', 'https://fashionforward.com', '456 Fashion Ave', 'New York', 'NY', '10001', 'USA', 'net_15', 7, 200.00, 4.6, 4.7, 'active', FALSE),
(3, 'HOME003', 'Home Essentials Supply', 'Mike Davis', 'mike@homeessentials.com', '+1-555-0103', 'https://homeessentials.com', '789 Home Blvd', 'Chicago', 'IL', '60601', 'USA', 'net_30', 10, 300.00, 4.5, 4.4, 'active', FALSE),
(4, 'SPORT004', 'Athletic Gear Direct', 'Emily Wilson', 'emily@athleticgear.com', '+1-555-0104', 'https://athleticgear.com', '321 Sports Way', 'Denver', 'CO', '80201', 'USA', 'net_45', 14, 1000.00, 4.9, 4.8, 'active', TRUE),
(5, 'BEAUTY005', 'Beauty Products Inc', 'David Brown', 'david@beautyproducts.com', '+1-555-0105', 'https://beautyproducts.com', '654 Beauty Lane', 'Los Angeles', 'CA', '90210', 'USA', 'net_30', 8, 150.00, 4.7, 4.6, 'active', FALSE)
";

try {
    $pdo->exec($suppliersSql);
    echo "Sample suppliers inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting suppliers: " . $e->getMessage() . "\n";
}

// Insert sample supplier products
$supplierProductsSql = "
INSERT IGNORE INTO supplier_products (id, supplier_id, supplier_sku, product_name, description, category, brand, cost_price, suggested_retail_price, bulk_price, bulk_quantity, available_quantity, minimum_order_quantity, lead_time_days, status) VALUES
(1, 1, 'TS-BT-001', 'Premium Bluetooth Headphones', 'High-quality wireless headphones with noise cancellation', 'Electronics', 'TechSource', 45.00, 89.99, 40.00, 50, 200, 5, 3, 'active'),
(2, 1, 'TS-SW-002', 'Smart Fitness Watch', 'Advanced fitness tracker with heart rate monitoring', 'Electronics', 'TechSource', 95.00, 199.99, 85.00, 25, 150, 2, 5, 'active'),
(3, 1, 'TS-CH-003', 'Wireless Phone Charger', 'Fast wireless charging pad for smartphones', 'Electronics', 'TechSource', 15.00, 34.99, 12.00, 100, 500, 10, 2, 'active'),
(4, 2, 'FF-TS-001', 'Organic Cotton T-Shirt', 'Comfortable organic cotton t-shirt', 'Clothing', 'EcoWear', 8.00, 24.99, 6.50, 100, 1000, 20, 7, 'active'),
(5, 2, 'FF-DJ-002', 'Classic Denim Jacket', 'Timeless denim jacket in multiple sizes', 'Clothing', 'ClassicWear', 25.00, 69.99, 22.00, 50, 300, 10, 10, 'active'),
(6, 3, 'HE-MUG-001', 'Ceramic Coffee Mug Set', 'Set of 4 handcrafted ceramic mugs', 'Home', 'HomeStyle', 12.00, 39.99, 10.00, 20, 200, 5, 8, 'active'),
(7, 3, 'HE-CAN-002', 'Scented Candle Collection', 'Set of 3 premium scented candles', 'Home', 'AromaLife', 18.00, 45.99, 15.00, 30, 150, 6, 12, 'active'),
(8, 4, 'AG-YM-001', 'Premium Yoga Mat', 'Non-slip yoga mat with extra cushioning', 'Sports', 'FlexFit', 20.00, 49.99, 17.00, 25, 100, 5, 14, 'active'),
(9, 4, 'AG-WB-002', 'Stainless Steel Water Bottle', 'Insulated water bottle for sports', 'Sports', 'HydroMax', 12.00, 29.99, 10.00, 50, 300, 10, 10, 'active'),
(10, 5, 'BP-SK-001', 'Skincare Gift Set', 'Complete skincare routine set', 'Beauty', 'GlowSkin', 35.00, 89.99, 30.00, 20, 80, 3, 6, 'active')
";

try {
    $pdo->exec($supplierProductsSql);
    echo "Sample supplier products inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting supplier products: " . $e->getMessage() . "\n";
}

// Insert sample product inventory
$productInventorySql = "
INSERT IGNORE INTO product_inventory (product_id, location_id, quantity_on_hand, quantity_reserved, reorder_point, reorder_quantity, max_stock_level, average_cost, last_cost) VALUES
(1, 1, 50, 5, 10, 100, 200, 45.00, 45.00),
(1, 2, 30, 2, 5, 50, 100, 45.00, 45.00),
(2, 1, 25, 3, 5, 50, 100, 95.00, 95.00),
(3, 1, 100, 10, 20, 200, 500, 15.00, 15.00),
(4, 2, 200, 20, 50, 500, 1000, 8.00, 8.00),
(5, 2, 50, 5, 10, 100, 200, 25.00, 25.00),
(6, 3, 40, 4, 10, 100, 200, 12.00, 12.00),
(7, 3, 30, 2, 5, 100, 200, 18.00, 18.00),
(8, 1, 25, 1, 5, 50, 100, 20.00, 20.00),
(9, 1, 60, 5, 10, 200, 500, 12.00, 12.00),
(10, 3, 20, 2, 5, 50, 100, 35.00, 35.00),
(11, 1, 15, 1, 5, 50, 100, 29.99, 29.99),
(12, 2, 35, 3, 10, 100, 200, 95.00, 95.00)
";

try {
    $pdo->exec($productInventorySql);
    echo "Sample product inventory inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting product inventory: " . $e->getMessage() . "\n";
}

// Insert sample purchase orders
$purchaseOrdersSql = "
INSERT IGNORE INTO purchase_orders (id, po_number, supplier_id, location_id, order_date, expected_delivery_date, subtotal, tax_amount, shipping_cost, discount_amount, total_amount, status, created_by) VALUES
(1, 'PO-2024-001', 1, 1, '2024-01-15', '2024-01-20', 2250.00, 180.00, 50.00, 0.00, 2480.00, 'received', 1),
(2, 'PO-2024-002', 2, 2, '2024-01-16', '2024-01-23', 1800.00, 144.00, 75.00, 0.00, 2019.00, 'received', 1),
(3, 'PO-2024-003', 3, 3, '2024-01-17', '2024-01-27', 900.00, 72.00, 30.00, 0.00, 1002.00, 'received', 1)
";

try {
    $pdo->exec($purchaseOrdersSql);
    echo "Sample purchase orders inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting purchase orders: " . $e->getMessage() . "\n";
}

// Insert sample purchase order items
$purchaseOrderItemsSql = "
INSERT IGNORE INTO purchase_order_items (purchase_order_id, supplier_product_id, product_id, product_name, supplier_sku, quantity_ordered, quantity_received, unit_cost) VALUES
(1, 1, 1, 'Premium Bluetooth Headphones', 'TS-BT-001', 25, 25, 45.00),
(1, 2, 2, 'Smart Fitness Watch', 'TS-SW-002', 15, 15, 95.00),
(1, 3, 3, 'Wireless Phone Charger', 'TS-CH-003', 50, 50, 15.00),
(2, 4, 4, 'Organic Cotton T-Shirt', 'FF-TS-001', 100, 100, 8.00),
(2, 5, 5, 'Classic Denim Jacket', 'FF-DJ-002', 30, 30, 25.00),
(3, 6, 6, 'Ceramic Coffee Mug Set', 'HE-MUG-001', 20, 20, 12.00),
(3, 7, 7, 'Scented Candle Collection', 'HE-CAN-002', 15, 15, 18.00)
";

try {
    $pdo->exec($purchaseOrderItemsSql);
    echo "Sample purchase order items inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting purchase order items: " . $e->getMessage() . "\n";
}

// Insert sample inventory movements
$inventoryMovementsSql = "
INSERT IGNORE INTO inventory_movements (product_id, location_id, movement_type, quantity, unit_cost, total_cost, reference_type, reference_id, created_by) VALUES
(1, 1, 'in', 25, 45.00, 1125.00, 'purchase_order', 1, 1),
(2, 1, 'in', 15, 95.00, 1425.00, 'purchase_order', 1, 1),
(3, 1, 'in', 50, 15.00, 750.00, 'purchase_order', 1, 1),
(4, 2, 'in', 100, 8.00, 800.00, 'purchase_order', 2, 1),
(5, 2, 'in', 30, 25.00, 750.00, 'purchase_order', 2, 1),
(6, 3, 'in', 20, 12.00, 240.00, 'purchase_order', 3, 1),
(7, 3, 'in', 15, 18.00, 270.00, 'purchase_order', 3, 1),
(1, 1, 'out', -5, 45.00, -225.00, 'sale_order', 101, 1),
(4, 2, 'out', -20, 8.00, -160.00, 'sale_order', 102, 1)
";

try {
    $pdo->exec($inventoryMovementsSql);
    echo "Sample inventory movements inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting inventory movements: " . $e->getMessage() . "\n";
}

echo "\nAll sample inventory data inserted successfully!\n";
?>