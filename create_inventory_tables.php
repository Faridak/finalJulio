<?php
require_once 'config/database.php';

// Create inventory_locations table
$inventoryLocationsSql = "
CREATE TABLE IF NOT EXISTS inventory_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_code VARCHAR(20) UNIQUE NOT NULL,
    location_name VARCHAR(100) NOT NULL,
    location_type ENUM('warehouse', 'store', 'dropship', 'virtual') DEFAULT 'warehouse',
    
    -- Address Information
    address_line1 VARCHAR(255) DEFAULT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    postal_code VARCHAR(20) DEFAULT NULL,
    country_code VARCHAR(3) DEFAULT 'USA',
    
    -- Contact Information
    manager_name VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    
    -- Capacity Information
    total_capacity INT DEFAULT NULL COMMENT 'Total storage capacity in units',
    current_utilization DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage of capacity used',
    
    -- Status
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($inventoryLocationsSql);
    echo "inventory_locations table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating inventory_locations table: " . $e->getMessage() . "\n";
}

// Create product_inventory table
$productInventorySql = "
CREATE TABLE IF NOT EXISTS product_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT NOT NULL,
    
    -- Stock Levels
    quantity_on_hand INT NOT NULL DEFAULT 0,
    quantity_reserved INT NOT NULL DEFAULT 0 COMMENT 'Reserved for pending orders',
    quantity_available INT GENERATED ALWAYS AS (quantity_on_hand - quantity_reserved) STORED,
    
    -- Reorder Information
    reorder_point INT DEFAULT 10 COMMENT 'Minimum stock before reordering',
    reorder_quantity INT DEFAULT 50 COMMENT 'Quantity to order when restocking',
    max_stock_level INT DEFAULT 100 COMMENT 'Maximum stock to maintain',
    
    -- Cost Information
    average_cost DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Weighted average cost',
    last_cost DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Cost of last purchase',
    
    -- Tracking
    last_counted_at TIMESTAMP NULL COMMENT 'Last physical count date',
    last_movement_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_location (product_id, location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($productInventorySql);
    echo "product_inventory table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating product_inventory table: " . $e->getMessage() . "\n";
}

// Create suppliers table
$suppliersSql = "
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_code VARCHAR(20) UNIQUE NOT NULL COMMENT 'Unique supplier identifier',
    company_name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    
    -- Address Information
    address_line1 VARCHAR(255) DEFAULT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    postal_code VARCHAR(20) DEFAULT NULL,
    country_code VARCHAR(3) DEFAULT 'USA',
    
    -- Business Information
    tax_id VARCHAR(50) DEFAULT NULL COMMENT 'Tax ID or VAT number',
    business_license VARCHAR(100) DEFAULT NULL,
    payment_terms ENUM('net_15', 'net_30', 'net_45', 'net_60', 'cod', 'prepaid') DEFAULT 'net_30',
    currency_code VARCHAR(3) DEFAULT 'USD',
    
    -- Performance Metrics
    lead_time_days INT DEFAULT 7 COMMENT 'Average lead time in days',
    minimum_order_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_percentage DECIMAL(5,2) DEFAULT 0.00,
    quality_rating DECIMAL(3,2) DEFAULT 5.00 COMMENT 'Rating out of 5.00',
    delivery_rating DECIMAL(3,2) DEFAULT 5.00 COMMENT 'Rating out of 5.00',
    
    -- Status and Preferences
    status ENUM('active', 'inactive', 'pending', 'suspended') DEFAULT 'active',
    preferred_supplier BOOLEAN DEFAULT FALSE,
    notes TEXT DEFAULT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($suppliersSql);
    echo "suppliers table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating suppliers table: " . $e->getMessage() . "\n";
}

// Create supplier_products table
$supplierProductsSql = "
CREATE TABLE IF NOT EXISTS supplier_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    product_id INT DEFAULT NULL COMMENT 'Link to existing product if available',
    
    -- Product Information
    supplier_sku VARCHAR(100) NOT NULL COMMENT 'Supplier product code',
    product_name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    brand VARCHAR(100) DEFAULT NULL,
    
    -- Pricing Information
    cost_price DECIMAL(10,2) NOT NULL COMMENT 'Cost from supplier',
    suggested_retail_price DECIMAL(10,2) DEFAULT NULL,
    bulk_price DECIMAL(10,2) DEFAULT NULL COMMENT 'Price for bulk orders',
    bulk_quantity INT DEFAULT NULL COMMENT 'Minimum quantity for bulk price',
    
    -- Availability
    available_quantity INT DEFAULT 0,
    minimum_order_quantity INT DEFAULT 1,
    lead_time_days INT DEFAULT NULL COMMENT 'Override supplier default lead time',
    
    -- Product Specifications
    weight_kg DECIMAL(8,3) DEFAULT NULL,
    dimensions_cm VARCHAR(50) DEFAULT NULL COMMENT 'LxWxH format',
    color VARCHAR(50) DEFAULT NULL,
    size VARCHAR(50) DEFAULT NULL,
    material VARCHAR(100) DEFAULT NULL,
    
    -- Status
    status ENUM('active', 'inactive', 'discontinued', 'out_of_stock') DEFAULT 'active',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    UNIQUE KEY unique_supplier_sku (supplier_id, supplier_sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($supplierProductsSql);
    echo "supplier_products table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating supplier_products table: " . $e->getMessage() . "\n";
}

// Create purchase_orders table
$purchaseOrdersSql = "
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id INT NOT NULL,
    location_id INT NOT NULL COMMENT 'Delivery location',
    
    -- Order Information
    order_date DATE NOT NULL,
    expected_delivery_date DATE DEFAULT NULL,
    actual_delivery_date DATE DEFAULT NULL,
    
    -- Financial Information
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    shipping_cost DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    
    -- Status
    status ENUM('draft', 'sent', 'confirmed', 'partial_received', 'received', 'cancelled') DEFAULT 'draft',
    
    -- Additional Information
    notes TEXT DEFAULT NULL,
    terms_conditions TEXT DEFAULT NULL,
    
    -- User Tracking
    created_by INT DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    approved_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($purchaseOrdersSql);
    echo "purchase_orders table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating purchase_orders table: " . $e->getMessage() . "\n";
}

// Create purchase_order_items table
$purchaseOrderItemsSql = "
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    supplier_product_id INT DEFAULT NULL,
    product_id INT DEFAULT NULL,
    
    -- Product Information
    product_name VARCHAR(255) NOT NULL,
    supplier_sku VARCHAR(100) DEFAULT NULL,
    
    -- Quantity and Pricing
    quantity_ordered INT NOT NULL,
    quantity_received INT DEFAULT 0,
    unit_cost DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(12,2) GENERATED ALWAYS AS (quantity_ordered * unit_cost) STORED,
    
    -- Status
    status ENUM('pending', 'partial_received', 'received', 'cancelled') DEFAULT 'pending',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_product_id) REFERENCES supplier_products(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($purchaseOrderItemsSql);
    echo "purchase_order_items table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating purchase_order_items table: " . $e->getMessage() . "\n";
}

// Create inventory_movements table
$inventoryMovementsSql = "
CREATE TABLE IF NOT EXISTS inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT NOT NULL,
    
    -- Movement Details
    movement_type ENUM('in', 'out', 'transfer', 'adjustment', 'return') NOT NULL,
    quantity INT NOT NULL COMMENT 'Positive for in, negative for out',
    unit_cost DECIMAL(10,2) DEFAULT NULL,
    total_cost DECIMAL(10,2) DEFAULT NULL,
    
    -- Reference Information
    reference_type ENUM('purchase_order', 'sale_order', 'transfer', 'adjustment', 'return', 'damage', 'theft') DEFAULT NULL,
    reference_id INT DEFAULT NULL COMMENT 'ID of related order/transfer',
    supplier_id INT DEFAULT NULL,
    
    -- Additional Information
    reason VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    batch_number VARCHAR(50) DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,
    
    -- User Tracking
    created_by INT DEFAULT NULL COMMENT 'User who created this movement',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($inventoryMovementsSql);
    echo "inventory_movements table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating inventory_movements table: " . $e->getMessage() . "\n";
}

echo "\nAll inventory tables created successfully!\n";
?>