-- Migration 015: Physical warehouse structure for 2D/3D mapping + smart slotting
-- Hierarchy: inventory_locations (warehouse) -> warehouse_zones -> storage_racks
--            (aisle) -> inventory_bins (level x position). IKEA-style bin addressing.
-- Column names align with includes/InventoryManager.php; capacity + dimension
-- fields added so put-away can be dimension-aware. Idempotent.

CREATE TABLE IF NOT EXISTS warehouse_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    zone_code VARCHAR(20) NOT NULL,
    zone_name VARCHAR(100) NOT NULL,
    zone_type ENUM('receiving','storage','picking','bulk','shipping','returns','cold') DEFAULT 'storage',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_zone (location_id, zone_code),
    INDEX idx_zone_location (location_id)
);

CREATE TABLE IF NOT EXISTS storage_racks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    rack_code VARCHAR(20) NOT NULL,          -- aisle/rack code, e.g. A01
    rack_name VARCHAR(100) NULL,
    rack_type ENUM('pallet','shelving','bin','floor','mezzanine') DEFAULT 'shelving',
    aisle VARCHAR(20) NULL,
    levels INT DEFAULT 4,                     -- vertical levels (rows/shelves)
    positions_per_level INT DEFAULT 10,       -- bins across each level
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rack (zone_id, rack_code),
    INDEX idx_rack_zone (zone_id)
);

CREATE TABLE IF NOT EXISTS inventory_bins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rack_id INT NOT NULL,
    bin_code VARCHAR(30) NOT NULL,
    bin_address VARCHAR(60) NOT NULL,         -- WH001-STG-A01-L2-P03
    level_number INT NOT NULL DEFAULT 1,      -- row / shelf height
    position_number INT NOT NULL DEFAULT 1,   -- slot along the level
    -- physical capacity of the bin
    length_cm DECIMAL(8,2) DEFAULT 40,
    width_cm  DECIMAL(8,2) DEFAULT 40,
    height_cm DECIMAL(8,2) DEFAULT 40,
    max_volume_cm3 DECIMAL(14,2) DEFAULT 64000,
    volume_liters DECIMAL(10,2) DEFAULT 64,   -- used by legacy InventoryManager
    weight_capacity_kg DECIMAL(10,2) DEFAULT 50,
    -- live occupancy
    current_product_id INT NULL,
    current_quantity INT NOT NULL DEFAULT 0,
    current_volume_cm3 DECIMAL(14,2) NOT NULL DEFAULT 0,
    current_weight_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
    occupancy_status ENUM('empty','partial','full','blocked') DEFAULT 'empty',
    utilization_percentage DECIMAL(5,2) DEFAULT 0.00,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bin (rack_id, bin_code),
    INDEX idx_bin_rack (rack_id),
    INDEX idx_bin_occupancy (occupancy_status),
    INDEX idx_bin_product (current_product_id)
);

CREATE TABLE IF NOT EXISTS product_bin_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    bin_id INT NOT NULL,
    assignment_type ENUM('primary','overflow','picking') DEFAULT 'primary',
    quantity INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    assigned_by INT NULL,
    last_movement_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_assignment (product_id, bin_id, assignment_type),
    INDEX idx_pba_bin (bin_id),
    INDEX idx_pba_product (product_id)
);
