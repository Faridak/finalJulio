-- VentDepot Supplier and Inventory Management Module
-- Add these tables to your existing finalJulio database

USE finalJulio;

-- =====================================================
-- SUPPLIER MANAGEMENT TABLES
-- =====================================================

-- Suppliers Table
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (country_code) REFERENCES countries(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Supplier Categories (what types of products they supply)
CREATE TABLE IF NOT EXISTS supplier_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    specialization TEXT DEFAULT NULL COMMENT 'Specific expertise in this category',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_supplier_category (supplier_id, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Supplier Products (products available from suppliers)
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

-- =====================================================
-- INVENTORY MANAGEMENT TABLES
-- =====================================================

-- Inventory Locations (warehouses, stores, etc.)
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (country_code) REFERENCES countries(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Product Inventory (current stock levels)
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

-- Inventory Movements (stock in/out tracking)
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

-- =====================================================
-- PURCHASE ORDER MANAGEMENT
-- =====================================================

-- Purchase Orders
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

-- Purchase Order Items
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

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Supplier indexes
CREATE INDEX idx_suppliers_status ON suppliers(status);
CREATE INDEX idx_suppliers_country ON suppliers(country_code);
CREATE INDEX idx_suppliers_rating ON suppliers(quality_rating, delivery_rating);

-- Supplier products indexes
CREATE INDEX idx_supplier_products_supplier ON supplier_products(supplier_id);
CREATE INDEX idx_supplier_products_product ON supplier_products(product_id);
CREATE INDEX idx_supplier_products_status ON supplier_products(status);
CREATE INDEX idx_supplier_products_category ON supplier_products(category);

-- Inventory indexes
CREATE INDEX idx_inventory_product ON product_inventory(product_id);
CREATE INDEX idx_inventory_location ON product_inventory(location_id);
CREATE INDEX idx_inventory_reorder ON product_inventory(reorder_point);

-- Movement indexes
CREATE INDEX idx_movements_product ON inventory_movements(product_id);
CREATE INDEX idx_movements_location ON inventory_movements(location_id);
CREATE INDEX idx_movements_type ON inventory_movements(movement_type);
CREATE INDEX idx_movements_date ON inventory_movements(created_at);
CREATE INDEX idx_movements_reference ON inventory_movements(reference_type, reference_id);

-- Purchase order indexes
CREATE INDEX idx_po_supplier ON purchase_orders(supplier_id);
CREATE INDEX idx_po_status ON purchase_orders(status);
CREATE INDEX idx_po_date ON purchase_orders(order_date);
CREATE INDEX idx_po_items_po ON purchase_order_items(purchase_order_id);

-- =====================================================
-- SAMPLE DATA
-- =====================================================

-- Insert Sample Suppliers
INSERT IGNORE INTO suppliers (supplier_code, company_name, contact_person, email, phone, website, address_line1, city, state, postal_code, country_code, payment_terms, lead_time_days, minimum_order_amount, quality_rating, delivery_rating, status, preferred_supplier) VALUES
('TECH001', 'TechSource Electronics', 'John Smith', 'john@techsource.com', '+1-555-0101', 'https://techsource.com', '123 Tech Street', 'San Francisco', 'CA', '94105', 'USA', 'net_30', 5, 500.00, 4.8, 4.9, 'active', TRUE),
('FASH002', 'Fashion Forward Wholesale', 'Sarah Johnson', 'sarah@fashionforward.com', '+1-555-0102', 'https://fashionforward.com', '456 Fashion Ave', 'New York', 'NY', '10001', 'USA', 'net_15', 7, 200.00, 4.6, 4.7, 'active', FALSE),
('HOME003', 'Home Essentials Supply', 'Mike Davis', 'mike@homeessentials.com', '+1-555-0103', 'https://homeessentials.com', '789 Home Blvd', 'Chicago', 'IL', '60601', 'USA', 'net_30', 10, 300.00, 4.5, 4.4, 'active', FALSE),
('SPORT004', 'Athletic Gear Direct', 'Emily Wilson', 'emily@athleticgear.com', '+1-555-0104', 'https://athleticgear.com', '321 Sports Way', 'Denver', 'CO', '80201', 'USA', 'net_45', 14, 1000.00, 4.9, 4.8, 'active', TRUE),
('BEAUTY005', 'Beauty Products Inc', 'David Brown', 'david@beautyproducts.com', '+1-555-0105', 'https://beautyproducts.com', '654 Beauty Lane', 'Los Angeles', 'CA', '90210', 'USA', 'net_30', 8, 150.00, 4.7, 4.6, 'active', FALSE);

-- Insert Supplier Categories
INSERT IGNORE INTO supplier_categories (supplier_id, category, specialization) VALUES
(1, 'Electronics', 'Consumer electronics, audio equipment, smart devices'),
(1, 'Accessories', 'Phone accessories, cables, chargers'),
(2, 'Clothing', 'Fashion apparel, trendy clothing lines'),
(2, 'Accessories', 'Fashion accessories, jewelry, bags'),
(3, 'Home', 'Home decor, kitchen essentials, furniture'),
(3, 'Garden', 'Outdoor furniture, garden tools'),
(4, 'Sports', 'Athletic equipment, fitness gear, outdoor sports'),
(4, 'Health', 'Fitness supplements, health products'),
(5, 'Beauty', 'Skincare, cosmetics, personal care'),
(5, 'Health', 'Wellness products, aromatherapy');

-- Insert Inventory Locations
INSERT IGNORE INTO inventory_locations (location_code, location_name, location_type, address_line1, city, state, postal_code, country_code, manager_name, phone, total_capacity, current_utilization, status) VALUES
('WH001', 'Main Warehouse', 'warehouse', '1000 Warehouse Drive', 'Dallas', 'TX', '75201', 'USA', 'Robert Johnson', '+1-555-0201', 10000, 65.5, 'active'),
('WH002', 'West Coast Distribution', 'warehouse', '2000 Pacific Blvd', 'Los Angeles', 'CA', '90001', 'USA', 'Lisa Chen', '+1-555-0202', 8000, 72.3, 'active'),
('WH003', 'East Coast Hub', 'warehouse', '3000 Atlantic Ave', 'Atlanta', 'GA', '30301', 'USA', 'Mark Williams', '+1-555-0203', 6000, 58.7, 'active'),
('STORE001', 'Flagship Store', 'store', '100 Main Street', 'New York', 'NY', '10001', 'USA', 'Jennifer Davis', '+1-555-0204', 500, 80.0, 'active'),
('DROP001', 'Dropship Virtual', 'dropship', 'Virtual Location', 'Virtual', 'Virtual', '00000', 'USA', 'System', 'N/A', 999999, 0.0, 'active');

-- Insert Sample Supplier Products
INSERT IGNORE INTO supplier_products (supplier_id, supplier_sku, product_name, description, category, brand, cost_price, suggested_retail_price, bulk_price, bulk_quantity, available_quantity, minimum_order_quantity, lead_time_days, status) VALUES
(1, 'TS-BT-001', 'Premium Bluetooth Headphones', 'High-quality wireless headphones with noise cancellation', 'Electronics', 'TechSource', 45.00, 89.99, 40.00, 50, 200, 5, 3, 'active'),
(1, 'TS-SW-002', 'Smart Fitness Watch', 'Advanced fitness tracker with heart rate monitoring', 'Electronics', 'TechSource', 95.00, 199.99, 85.00, 25, 150, 2, 5, 'active'),
(1, 'TS-CH-003', 'Wireless Phone Charger', 'Fast wireless charging pad for smartphones', 'Electronics', 'TechSource', 15.00, 34.99, 12.00, 100, 500, 10, 2, 'active'),
(2, 'FF-TS-001', 'Organic Cotton T-Shirt', 'Comfortable organic cotton t-shirt', 'Clothing', 'EcoWear', 8.00, 24.99, 6.50, 100, 1000, 20, 7, 'active'),
(2, 'FF-DJ-002', 'Classic Denim Jacket', 'Timeless denim jacket in multiple sizes', 'Clothing', 'ClassicWear', 25.00, 69.99, 22.00, 50, 300, 10, 10, 'active'),
(3, 'HE-MUG-001', 'Ceramic Coffee Mug Set', 'Set of 4 handcrafted ceramic mugs', 'Home', 'HomeStyle', 12.00, 39.99, 10.00, 20, 200, 5, 8, 'active'),
(3, 'HE-CAN-002', 'Scented Candle Collection', 'Set of 3 premium scented candles', 'Home', 'AromaLife', 18.00, 45.99, 15.00, 30, 150, 6, 12, 'active'),
(4, 'AG-YM-001', 'Premium Yoga Mat', 'Non-slip yoga mat with extra cushioning', 'Sports', 'FlexFit', 20.00, 49.99, 17.00, 25, 100, 5, 14, 'active'),
(4, 'AG-WB-002', 'Stainless Steel Water Bottle', 'Insulated water bottle for sports', 'Sports', 'HydroMax', 12.00, 29.99, 10.00, 50, 300, 10, 10, 'active'),
(5, 'BP-SK-001', 'Skincare Gift Set', 'Complete skincare routine set', 'Beauty', 'GlowSkin', 35.00, 89.99, 30.00, 20, 80, 3, 6, 'active');

-- Show summary of inserted data
SELECT 'Supplier and Inventory Module Setup Complete!' as Status;
SELECT
    'Suppliers' as Table_Name, COUNT(*) as Records FROM suppliers
UNION ALL SELECT
    'Supplier Categories' as Table_Name, COUNT(*) as Records FROM supplier_categories
UNION ALL SELECT
    'Supplier Products' as Table_Name, COUNT(*) as Records FROM supplier_products
UNION ALL SELECT
    'Inventory Locations' as Table_Name, COUNT(*) as Records FROM inventory_locations;
