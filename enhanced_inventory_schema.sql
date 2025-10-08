-- Enhanced Inventory Management System for VentDepot
-- Adds bin location mapping, inventory cycles, automated reorders, and vendor management

USE finalJulio;

-- Disable foreign key checks temporarily to avoid order dependencies
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- ENHANCED INVENTORY LOCATION MANAGEMENT
-- =====================================================

-- Warehouse Zones (Areas within a warehouse)
CREATE TABLE IF NOT EXISTS warehouse_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    zone_code VARCHAR(10) NOT NULL,
    zone_name VARCHAR(100) NOT NULL,
    zone_type ENUM('receiving', 'storage', 'picking', 'shipping', 'returns', 'quarantine') NOT NULL,
    temperature_controlled BOOLEAN DEFAULT FALSE,
    climate_requirements TEXT DEFAULT NULL,
    access_level ENUM('public', 'restricted', 'secure') DEFAULT 'public',
    manager_name VARCHAR(100) DEFAULT NULL,
    capacity_limit INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_location_zone (location_id, zone_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Storage Racks (Racks within zones)
CREATE TABLE IF NOT EXISTS storage_racks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    rack_code VARCHAR(10) NOT NULL,
    rack_name VARCHAR(100) NOT NULL,
    rack_type ENUM('standard', 'pallet', 'cantilever', 'drive_in', 'flow', 'mobile') DEFAULT 'standard',
    total_levels INT DEFAULT 1,
    total_positions INT DEFAULT 1,
    weight_capacity_kg DECIMAL(8,2) DEFAULT NULL,
    dimensions_cm VARCHAR(50) DEFAULT NULL COMMENT 'LxWxH format',
    status ENUM('active', 'inactive', 'maintenance', 'damaged') DEFAULT 'active',
    installation_date DATE DEFAULT NULL,
    last_inspection_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (zone_id) REFERENCES warehouse_zones(id) ON DELETE CASCADE,
    UNIQUE KEY unique_zone_rack (zone_id, rack_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Inventory Bins (Specific storage locations)
CREATE TABLE IF NOT EXISTS inventory_bins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rack_id INT NOT NULL,
    bin_code VARCHAR(20) NOT NULL,
    bin_address VARCHAR(50) NOT NULL COMMENT 'Full address like WH001-A-R01-L01-P01',
    level_number INT NOT NULL DEFAULT 1,
    position_number INT NOT NULL DEFAULT 1,
    bin_type ENUM('standard', 'bulk', 'small_parts', 'hazmat', 'fragile', 'temperature_controlled') DEFAULT 'standard',
    
    -- Physical Properties
    length_cm DECIMAL(6,2) DEFAULT NULL,
    width_cm DECIMAL(6,2) DEFAULT NULL,
    height_cm DECIMAL(6,2) DEFAULT NULL,
    volume_liters DECIMAL(8,2) DEFAULT NULL,
    weight_capacity_kg DECIMAL(8,2) DEFAULT NULL,
    
    -- Current Status
    occupancy_status ENUM('empty', 'partial', 'full', 'reserved', 'blocked') DEFAULT 'empty',
    current_product_id INT DEFAULT NULL,
    current_quantity INT DEFAULT 0,
    utilization_percentage DECIMAL(5,2) DEFAULT 0.00,
    
    -- Special Attributes
    is_picking_location BOOLEAN DEFAULT FALSE,
    is_replenishment_location BOOLEAN DEFAULT FALSE,
    requires_certification BOOLEAN DEFAULT FALSE,
    barcode VARCHAR(100) UNIQUE DEFAULT NULL,
    qr_code VARCHAR(255) DEFAULT NULL,
    
    -- Tracking
    last_picked_at TIMESTAMP NULL,
    last_replenished_at TIMESTAMP NULL,
    last_counted_at TIMESTAMP NULL,
    cycle_count_frequency_days INT DEFAULT 90,
    
    status ENUM('active', 'inactive', 'maintenance', 'damaged', 'blocked') DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (rack_id) REFERENCES storage_racks(id) ON DELETE CASCADE,
    FOREIGN KEY (current_product_id) REFERENCES products(id) ON DELETE SET NULL,
    UNIQUE KEY unique_rack_bin (rack_id, bin_code),
    UNIQUE KEY unique_bin_address (bin_address),
    INDEX idx_bin_barcode (barcode),
    INDEX idx_occupancy_status (occupancy_status),
    INDEX idx_picking_location (is_picking_location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Product Bin Assignments (Many-to-many relationship)
CREATE TABLE IF NOT EXISTS product_bin_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    bin_id INT NOT NULL,
    assignment_type ENUM('primary', 'overflow', 'picking', 'bulk', 'reserve') DEFAULT 'primary',
    quantity INT NOT NULL DEFAULT 0,
    reserved_quantity INT DEFAULT 0,
    min_quantity INT DEFAULT 0,
    max_quantity INT DEFAULT NULL,
    
    -- Rotation and Dating
    lot_number VARCHAR(50) DEFAULT NULL,
    batch_number VARCHAR(50) DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,
    received_date DATE DEFAULT NULL,
    
    -- Cost Tracking
    unit_cost DECIMAL(10,2) DEFAULT NULL,
    total_value DECIMAL(12,2) DEFAULT NULL,
    
    assigned_by INT DEFAULT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_movement_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'depleted') DEFAULT 'active',
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (bin_id) REFERENCES inventory_bins(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_product_bin_type (product_id, bin_id, assignment_type),
    INDEX idx_product_assignments (product_id),
    INDEX idx_bin_assignments (bin_id),
    INDEX idx_expiry_date (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- INVENTORY CYCLE COUNTING
-- =====================================================

-- Cycle Count Plans
CREATE TABLE IF NOT EXISTS cycle_count_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(100) NOT NULL,
    location_id INT NOT NULL,
    plan_type ENUM('full', 'abc_analysis', 'zone_based', 'random', 'exception_based') DEFAULT 'abc_analysis',
    frequency_type ENUM('daily', 'weekly', 'monthly', 'quarterly', 'custom') DEFAULT 'monthly',
    frequency_value INT DEFAULT 1 COMMENT 'Every X frequency_type units',
    
    -- ABC Analysis Parameters
    class_a_frequency_days INT DEFAULT 30,
    class_b_frequency_days INT DEFAULT 60,
    class_c_frequency_days INT DEFAULT 90,
    
    -- Scheduling
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    next_scheduled_date DATE DEFAULT NULL,
    
    -- Assignment
    assigned_to INT DEFAULT NULL,
    team_members JSON DEFAULT NULL,
    
    -- Status
    status ENUM('active', 'paused', 'completed', 'cancelled') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Cycle Count Sessions
CREATE TABLE IF NOT EXISTS cycle_count_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    session_name VARCHAR(100) NOT NULL,
    scheduled_date DATE NOT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    
    -- Scope
    zone_id INT DEFAULT NULL,
    product_category VARCHAR(100) DEFAULT NULL,
    bin_range_start VARCHAR(20) DEFAULT NULL,
    bin_range_end VARCHAR(20) DEFAULT NULL,
    
    -- Results Summary
    total_items_planned INT DEFAULT 0,
    total_items_counted INT DEFAULT 0,
    total_discrepancies INT DEFAULT 0,
    total_adjustments_value DECIMAL(12,2) DEFAULT 0.00,
    accuracy_percentage DECIMAL(5,2) DEFAULT NULL,
    
    -- Assignment
    counter_id INT DEFAULT NULL,
    supervisor_id INT DEFAULT NULL,
    
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (plan_id) REFERENCES cycle_count_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES warehouse_zones(id) ON DELETE SET NULL,
    FOREIGN KEY (counter_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Cycle Count Details
CREATE TABLE IF NOT EXISTS cycle_count_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    product_id INT NOT NULL,
    bin_id INT DEFAULT NULL,
    
    -- Expected vs Actual
    expected_quantity INT NOT NULL,
    counted_quantity INT DEFAULT NULL,
    variance_quantity INT DEFAULT NULL,
    variance_percentage DECIMAL(7,4) DEFAULT NULL,
    
    -- Cost Impact
    unit_cost DECIMAL(10,2) DEFAULT NULL,
    variance_value DECIMAL(12,2) DEFAULT NULL,
    
    -- Count Information
    counted_by INT DEFAULT NULL,
    counted_at TIMESTAMP NULL,
    recount_required BOOLEAN DEFAULT FALSE,
    recount_reason VARCHAR(255) DEFAULT NULL,
    
    -- Resolution
    adjustment_made BOOLEAN DEFAULT FALSE,
    adjustment_reason VARCHAR(255) DEFAULT NULL,
    adjusted_by INT DEFAULT NULL,
    adjusted_at TIMESTAMP NULL,
    
    notes TEXT DEFAULT NULL,
    status ENUM('pending', 'counted', 'variance', 'adjusted', 'exception') DEFAULT 'pending',
    
    FOREIGN KEY (session_id) REFERENCES cycle_count_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (bin_id) REFERENCES inventory_bins(id) ON DELETE SET NULL,
    FOREIGN KEY (counted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (adjusted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- AUTOMATED REORDER MANAGEMENT
-- =====================================================

-- Reorder Rules
CREATE TABLE IF NOT EXISTS reorder_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT NOT NULL,
    supplier_id INT NOT NULL,
    
    -- Reorder Parameters
    reorder_point INT NOT NULL,
    reorder_quantity INT NOT NULL,
    max_stock_level INT DEFAULT NULL,
    safety_stock INT DEFAULT 0,
    lead_time_days INT DEFAULT 7,
    
    -- ABC Classification
    abc_class ENUM('A', 'B', 'C') DEFAULT 'C',
    velocity_category ENUM('fast', 'medium', 'slow', 'dead') DEFAULT 'medium',
    
    -- Seasonality
    seasonal_item BOOLEAN DEFAULT FALSE,
    peak_season_start DATE DEFAULT NULL,
    peak_season_end DATE DEFAULT NULL,
    peak_season_multiplier DECIMAL(3,2) DEFAULT 1.00,
    
    -- Cost Parameters
    last_purchase_cost DECIMAL(10,2) DEFAULT NULL,
    standard_cost DECIMAL(10,2) DEFAULT NULL,
    economic_order_quantity INT DEFAULT NULL,
    
    -- Automation Settings
    auto_generate_po BOOLEAN DEFAULT FALSE,
    auto_approve_threshold DECIMAL(10,2) DEFAULT NULL,
    require_approval_above DECIMAL(10,2) DEFAULT 1000.00,
    
    -- Status
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_triggered_at TIMESTAMP NULL,
    next_review_date DATE DEFAULT NULL,
    
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_location_supplier (product_id, location_id, supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Reorder Suggestions
CREATE TABLE IF NOT EXISTS reorder_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    product_id INT NOT NULL,
    supplier_id INT NOT NULL,
    location_id INT NOT NULL,
    
    -- Current Status
    current_stock INT NOT NULL,
    reorder_point INT NOT NULL,
    suggested_quantity INT NOT NULL,
    urgency_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    
    -- Financial Impact
    estimated_cost DECIMAL(12,2) DEFAULT NULL,
    potential_stockout_cost DECIMAL(12,2) DEFAULT NULL,
    carrying_cost DECIMAL(10,2) DEFAULT NULL,
    
    -- Timing
    suggested_order_date DATE DEFAULT NULL,
    expected_delivery_date DATE DEFAULT NULL,
    stockout_risk_date DATE DEFAULT NULL,
    
    -- Processing Status
    status ENUM('pending', 'reviewed', 'approved', 'rejected', 'ordered', 'cancelled') DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT DEFAULT NULL,
    
    -- Auto-generated PO
    purchase_order_id INT DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (rule_id) REFERENCES reorder_rules(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- VENDOR COMMUNICATION TRACKING
-- =====================================================

-- Vendor Contacts
CREATE TABLE IF NOT EXISTS vendor_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    contact_type ENUM('primary', 'sales', 'support', 'billing', 'technical', 'emergency') DEFAULT 'primary',
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    title VARCHAR(100) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    
    -- Contact Information
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    mobile VARCHAR(20) DEFAULT NULL,
    extension VARCHAR(10) DEFAULT NULL,
    direct_line VARCHAR(20) DEFAULT NULL,
    
    -- Availability
    time_zone VARCHAR(50) DEFAULT NULL,
    working_hours_start TIME DEFAULT NULL,
    working_hours_end TIME DEFAULT NULL,
    working_days VARCHAR(20) DEFAULT 'Mon-Fri',
    
    -- Preferences
    preferred_contact_method ENUM('email', 'phone', 'mobile', 'sms', 'whatsapp') DEFAULT 'email',
    language_preference VARCHAR(10) DEFAULT 'en',
    
    -- Status
    is_primary BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive', 'vacation', 'unavailable') DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Vendor Communication Log
CREATE TABLE IF NOT EXISTS vendor_communication_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    contact_id INT DEFAULT NULL,
    reorder_suggestion_id INT DEFAULT NULL,
    purchase_order_id INT DEFAULT NULL,
    
    -- Communication Details
    communication_type ENUM('email', 'phone', 'sms', 'meeting', 'video_call', 'in_person') NOT NULL,
    direction ENUM('outgoing', 'incoming') NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    message_content TEXT DEFAULT NULL,
    
    -- Participants
    initiated_by INT DEFAULT NULL,
    participants JSON DEFAULT NULL,
    
    -- Status and Results
    status ENUM('scheduled', 'in_progress', 'completed', 'failed', 'cancelled') DEFAULT 'completed',
    outcome ENUM('successful', 'no_response', 'busy', 'voicemail', 'error', 'follow_up_required') DEFAULT 'successful',
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_date DATE DEFAULT NULL,
    
    -- Automated Communication
    is_automated BOOLEAN DEFAULT FALSE,
    template_used VARCHAR(100) DEFAULT NULL,
    
    -- Attachments and References
    attachments JSON DEFAULT NULL,
    external_reference VARCHAR(100) DEFAULT NULL,
    
    scheduled_at TIMESTAMP NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES vendor_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (reorder_suggestion_id) REFERENCES reorder_suggestions(id) ON DELETE SET NULL,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- ENHANCED PURCHASE REQUISITION WORKFLOW
-- =====================================================

-- Purchase Requisitions
CREATE TABLE IF NOT EXISTS purchase_requisitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_number VARCHAR(50) UNIQUE NOT NULL,
    
    -- Request Details
    requested_by INT NOT NULL,
    department VARCHAR(100) DEFAULT NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    request_type ENUM('reorder', 'new_item', 'emergency', 'project', 'maintenance') DEFAULT 'reorder',
    
    -- Justification
    business_justification TEXT DEFAULT NULL,
    budget_code VARCHAR(50) DEFAULT NULL,
    project_code VARCHAR(50) DEFAULT NULL,
    
    -- Financial Information
    estimated_total DECIMAL(12,2) DEFAULT 0.00,
    approved_budget DECIMAL(12,2) DEFAULT NULL,
    currency_code VARCHAR(3) DEFAULT 'USD',
    
    -- Delivery Requirements
    required_by_date DATE DEFAULT NULL,
    delivery_location_id INT DEFAULT NULL,
    special_instructions TEXT DEFAULT NULL,
    
    -- Approval Workflow
    approval_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'cancelled') DEFAULT 'draft',
    approval_level INT DEFAULT 1,
    max_approval_level INT DEFAULT 3,
    
    -- Workflow Tracking
    submitted_at TIMESTAMP NULL,
    reviewed_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    rejected_at TIMESTAMP NULL,
    rejection_reason TEXT DEFAULT NULL,
    
    -- Conversion
    converted_to_po BOOLEAN DEFAULT FALSE,
    purchase_order_id INT DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Purchase Requisition Items
CREATE TABLE IF NOT EXISTS purchase_requisition_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    
    -- Item Details
    item_description TEXT NOT NULL,
    quantity_requested INT NOT NULL,
    unit_of_measure VARCHAR(20) DEFAULT 'each',
    estimated_unit_cost DECIMAL(10,2) DEFAULT NULL,
    estimated_total_cost DECIMAL(12,2) DEFAULT NULL,
    
    -- Supplier Preference
    preferred_supplier_id INT DEFAULT NULL,
    supplier_part_number VARCHAR(100) DEFAULT NULL,
    
    -- Specifications
    specifications TEXT DEFAULT NULL,
    quality_requirements TEXT DEFAULT NULL,
    
    -- Status
    status ENUM('pending', 'approved', 'rejected', 'ordered') DEFAULT 'pending',
    rejection_reason TEXT DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (preferred_supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Approval Workflow
CREATE TABLE IF NOT EXISTS requisition_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT NOT NULL,
    approval_level INT NOT NULL,
    approver_id INT NOT NULL,
    
    -- Approval Limits
    approval_limit DECIMAL(12,2) DEFAULT NULL,
    
    -- Decision
    decision ENUM('pending', 'approved', 'rejected', 'delegated') DEFAULT 'pending',
    decision_date TIMESTAMP NULL,
    comments TEXT DEFAULT NULL,
    
    -- Delegation
    delegated_to INT DEFAULT NULL,
    delegation_reason TEXT DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (delegated_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create indexes for performance
CREATE INDEX idx_bins_product_current ON inventory_bins(current_product_id);
CREATE INDEX idx_bins_status_type ON inventory_bins(status, bin_type);
CREATE INDEX idx_assignments_product_status ON product_bin_assignments(product_id, status);
CREATE INDEX idx_reorder_rules_status ON reorder_rules(status);
CREATE INDEX idx_cycle_count_scheduled ON cycle_count_sessions(scheduled_date, status);
CREATE INDEX idx_reorder_suggestions_status ON reorder_suggestions(status, urgency_level);
CREATE INDEX idx_vendor_contacts_supplier ON vendor_contacts(supplier_id, status);
CREATE INDEX idx_communication_log_supplier ON vendor_communication_log(supplier_id, completed_at);
CREATE INDEX idx_requisitions_status ON purchase_requisitions(approval_status);

-- Insert sample data for warehouse zones and bins
INSERT IGNORE INTO warehouse_zones (location_id, zone_code, zone_name, zone_type, status) 
SELECT id, 'A', 'Zone A - Electronics', 'storage', 'active' FROM inventory_locations WHERE location_code = 'WH001'
UNION ALL
SELECT id, 'B', 'Zone B - Apparel', 'storage', 'active' FROM inventory_locations WHERE location_code = 'WH001'
UNION ALL
SELECT id, 'C', 'Zone C - Home & Garden', 'storage', 'active' FROM inventory_locations WHERE location_code = 'WH001'
UNION ALL
SELECT id, 'R', 'Receiving Zone', 'receiving', 'active' FROM inventory_locations WHERE location_code = 'WH001'
UNION ALL
SELECT id, 'S', 'Shipping Zone', 'shipping', 'active' FROM inventory_locations WHERE location_code = 'WH001';

-- Insert sample racks
INSERT IGNORE INTO storage_racks (zone_id, rack_code, rack_name, rack_type, total_levels, total_positions, status)
SELECT z.id, 'R01', 'Rack 01', 'standard', 4, 10, 'active' FROM warehouse_zones z WHERE z.zone_code = 'A'
UNION ALL  
SELECT z.id, 'R02', 'Rack 02', 'standard', 4, 10, 'active' FROM warehouse_zones z WHERE z.zone_code = 'A'
UNION ALL
SELECT z.id, 'R01', 'Rack 01', 'standard', 3, 8, 'active' FROM warehouse_zones z WHERE z.zone_code = 'B'
UNION ALL
SELECT z.id, 'R01', 'Rack 01', 'pallet', 3, 6, 'active' FROM warehouse_zones z WHERE z.zone_code = 'C';

SELECT 'Enhanced Inventory Management Schema Created Successfully!' as Status;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;