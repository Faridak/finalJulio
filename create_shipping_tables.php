<?php
require_once 'config/database.php';

// Create countries table
$countriesSql = "
CREATE TABLE IF NOT EXISTS countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(3) UNIQUE NOT NULL COMMENT 'ISO 3166-1 alpha-3 code (USA, CAN, MEX)',
    name VARCHAR(100) NOT NULL,
    currency_code VARCHAR(3) NOT NULL COMMENT 'USD, CAD, MXN',
    currency_symbol VARCHAR(10) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Default tax rate percentage',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($countriesSql);
    echo "countries table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating countries table: " . $e->getMessage() . "\n";
}

// Create states table
$statesSql = "
CREATE TABLE IF NOT EXISTS states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    country_id INT NOT NULL,
    code VARCHAR(10) NOT NULL COMMENT 'CA, NY, ON, BC',
    name VARCHAR(100) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'State/province specific tax',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
    UNIQUE KEY unique_state_code (country_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($statesSql);
    echo "states table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating states table: " . $e->getMessage() . "\n";
}

// Create cities table
$citiesSql = "
CREATE TABLE IF NOT EXISTS cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    state_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    postal_code_pattern VARCHAR(20) COMMENT 'Regex pattern for postal codes',
    timezone VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE CASCADE,
    INDEX idx_cities_state (state_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($citiesSql);
    echo "cities table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating cities table: " . $e->getMessage() . "\n";
}

// Create shipping_zones table
$shippingZonesSql = "
CREATE TABLE IF NOT EXISTS shipping_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($shippingZonesSql);
    echo "shipping_zones table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating shipping_zones table: " . $e->getMessage() . "\n";
}

// Create zone_countries table
$zoneCountriesSql = "
CREATE TABLE IF NOT EXISTS zone_countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    country_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE,
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
    UNIQUE KEY unique_zone_country (zone_id, country_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($zoneCountriesSql);
    echo "zone_countries table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating zone_countries table: " . $e->getMessage() . "\n";
}

// Create shipping_providers table
$shippingProvidersSql = "
CREATE TABLE IF NOT EXISTS shipping_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL COMMENT 'FEDEX, UPS, USPS, DHL',
    api_endpoint VARCHAR(255),
    api_key_encrypted VARCHAR(255),
    tracking_url_template VARCHAR(255) COMMENT 'URL with {tracking_number} placeholder',
    is_active BOOLEAN DEFAULT TRUE,
    supports_international BOOLEAN DEFAULT FALSE,
    max_weight_kg DECIMAL(8,2),
    max_dimensions_cm VARCHAR(50) COMMENT '\"100x100x100\" format',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($shippingProvidersSql);
    echo "shipping_providers table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating shipping_providers table: " . $e->getMessage() . "\n";
}

// Create shipping_services table
$shippingServicesSql = "
CREATE TABLE IF NOT EXISTS shipping_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL,
    description TEXT,
    estimated_days_min INT DEFAULT 1,
    estimated_days_max INT DEFAULT 7,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES shipping_providers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_provider_service (provider_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($shippingServicesSql);
    echo "shipping_services table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating shipping_services table: " . $e->getMessage() . "\n";
}

// Create product_dimensions table
$productDimensionsSql = "
CREATE TABLE IF NOT EXISTS product_dimensions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNIQUE NOT NULL,
    weight_kg DECIMAL(8,3) NOT NULL DEFAULT 0.500,
    length_cm DECIMAL(8,2) NOT NULL DEFAULT 20.00,
    width_cm DECIMAL(8,2) NOT NULL DEFAULT 15.00,
    height_cm DECIMAL(8,2) NOT NULL DEFAULT 10.00,
    fragile BOOLEAN DEFAULT FALSE,
    hazardous BOOLEAN DEFAULT FALSE,
    requires_signature BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($productDimensionsSql);
    echo "product_dimensions table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating product_dimensions table: " . $e->getMessage() . "\n";
}

// Create shipping_rate_rules table
$shippingRateRulesSql = "
CREATE TABLE IF NOT EXISTS shipping_rate_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    service_id INT NOT NULL,
    zone_id INT NOT NULL,
    weight_min_kg DECIMAL(8,3) DEFAULT 0.000,
    weight_max_kg DECIMAL(8,3) DEFAULT 999.999,
    base_cost DECIMAL(10,2) NOT NULL,
    cost_per_kg DECIMAL(10,2) DEFAULT 0.00,
    cost_per_cubic_cm DECIMAL(10,6) DEFAULT 0.000000,
    free_shipping_threshold DECIMAL(10,2) COMMENT 'Free shipping if order total exceeds this',
    is_active BOOLEAN DEFAULT TRUE,
    effective_date DATE,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES shipping_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES shipping_services(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($shippingRateRulesSql);
    echo "shipping_rate_rules table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating shipping_rate_rules table: " . $e->getMessage() . "\n";
}

// Create shipments table
$shipmentsSql = "
CREATE TABLE IF NOT EXISTS shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    provider_id INT NOT NULL,
    service_id INT NOT NULL,
    tracking_number VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('created', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'exception', 'returned') DEFAULT 'created',
    estimated_delivery DATE,
    actual_delivery DATETIME,
    shipping_cost DECIMAL(10,2),
    weight_kg DECIMAL(8,3),
    dimensions_cm VARCHAR(50),
    origin_address TEXT,
    destination_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES shipping_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES shipping_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($shipmentsSql);
    echo "shipments table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating shipments table: " . $e->getMessage() . "\n";
}

// Create tracking_events table
$trackingEventsSql = "
CREATE TABLE IF NOT EXISTS tracking_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    description TEXT,
    location VARCHAR(255),
    event_time DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($trackingEventsSql);
    echo "tracking_events table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating tracking_events table: " . $e->getMessage() . "\n";
}

// Create tax_rules table
$taxRulesSql = "
CREATE TABLE IF NOT EXISTS tax_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    country_id INT,
    state_id INT,
    product_category VARCHAR(100),
    tax_type ENUM('sales', 'vat', 'gst', 'hst') NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    effective_date DATE,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
    FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($taxRulesSql);
    echo "tax_rules table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating tax_rules table: " . $e->getMessage() . "\n";
}

// Create currency_rates table
$currencyRatesSql = "
CREATE TABLE IF NOT EXISTS currency_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_currency VARCHAR(3) NOT NULL,
    to_currency VARCHAR(3) NOT NULL,
    rate DECIMAL(12,6) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_currency_pair (from_currency, to_currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($currencyRatesSql);
    echo "currency_rates table created successfully\n";
} catch(PDOException $e) {
    echo "Error creating currency_rates table: " . $e->getMessage() . "\n";
}

// Create indexes
$indexesSql = "
CREATE INDEX IF NOT EXISTS idx_shipping_rates_zone_weight ON shipping_rate_rules(zone_id, weight_min_kg, weight_max_kg);
CREATE INDEX IF NOT EXISTS idx_shipments_tracking ON shipments(tracking_number);
CREATE INDEX IF NOT EXISTS idx_shipments_order ON shipments(order_id);
CREATE INDEX IF NOT EXISTS idx_tracking_events_shipment ON tracking_events(shipment_id);
CREATE INDEX IF NOT EXISTS idx_product_dimensions_product ON product_dimensions(product_id);
CREATE INDEX IF NOT EXISTS idx_tax_rules_location ON tax_rules(country_id, state_id);
CREATE INDEX IF NOT EXISTS idx_countries_code ON countries(code);
CREATE INDEX IF NOT EXISTS idx_states_code ON states(country_id, code);
CREATE INDEX IF NOT EXISTS idx_currency_rates_pair ON currency_rates(from_currency, to_currency);
";

try {
    $pdo->exec($indexesSql);
    echo "Indexes created successfully\n";
} catch(PDOException $e) {
    echo "Error creating indexes: " . $e->getMessage() . "\n";
}

echo "\nAll shipping tables created successfully!\n";
?>