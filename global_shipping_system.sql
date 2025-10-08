-- =====================================================
-- VentDepot Global Shipping System
-- Enhanced worldwide shipping with advanced features
-- =====================================================

USE finalJulio;

-- =====================================================
-- ENHANCED GEOGRAPHICAL TABLES
-- =====================================================

-- Enhanced Countries Table with coordinates
DROP TABLE IF EXISTS countries;
CREATE TABLE countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(3) UNIQUE NOT NULL COMMENT 'ISO 3166-1 alpha-3 code',
    name VARCHAR(100) NOT NULL,
    currency_code VARCHAR(3) NOT NULL,
    currency_symbol VARCHAR(10) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    latitude DECIMAL(10,8) NOT NULL COMMENT 'Country center latitude',
    longitude DECIMAL(11,8) NOT NULL COMMENT 'Country center longitude',
    timezone VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    shipping_allowed BOOLEAN DEFAULT TRUE,
    customs_required BOOLEAN DEFAULT FALSE,
    max_declared_value DECIMAL(10,2) DEFAULT 10000.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Enhanced States Table with coordinates
DROP TABLE IF EXISTS states;
CREATE TABLE states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    country_id INT NOT NULL,
    code VARCHAR(10) NOT NULL,
    name VARCHAR(100) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    timezone VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
    UNIQUE KEY unique_state_code (country_id, code)
);

-- Enhanced Cities Table with coordinates
DROP TABLE IF EXISTS cities;
CREATE TABLE cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    state_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    postal_code_pattern VARCHAR(20),
    timezone VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    is_major_city BOOLEAN DEFAULT FALSE,
    population INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE CASCADE,
    INDEX idx_cities_coordinates (latitude, longitude),
    INDEX idx_cities_state (state_id)
);

-- Shipping Types Table
CREATE TABLE shipping_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    base_multiplier DECIMAL(4,2) DEFAULT 1.00 COMMENT 'Cost multiplier for this type',
    max_weight_kg DECIMAL(8,2),
    max_dimensions_cm VARCHAR(50),
    requires_signature BOOLEAN DEFAULT FALSE,
    insurance_included BOOLEAN DEFAULT FALSE,
    tracking_included BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Package Types Table
CREATE TABLE package_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    volume_multiplier DECIMAL(4,2) DEFAULT 1.00 COMMENT 'Volume calculation multiplier',
    fragile_surcharge DECIMAL(5,2) DEFAULT 0.00,
    handling_fee DECIMAL(5,2) DEFAULT 0.00,
    max_weight_kg DECIMAL(8,2),
    dimensions_required BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Enhanced Shipping Providers Table
DROP TABLE IF EXISTS shipping_providers;
CREATE TABLE shipping_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    logo_url VARCHAR(255),
    api_endpoint VARCHAR(255),
    api_key_encrypted VARCHAR(255),
    tracking_url_template VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    supports_international BOOLEAN DEFAULT FALSE,
    supports_insurance BOOLEAN DEFAULT FALSE,
    supports_signature BOOLEAN DEFAULT FALSE,
    max_weight_kg DECIMAL(8,2),
    max_dimensions_cm VARCHAR(50),
    base_country_code VARCHAR(3) DEFAULT 'USA',
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Enhanced Shipping Services Table
DROP TABLE IF EXISTS shipping_services;
CREATE TABLE shipping_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    shipping_type_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL,
    description TEXT,
    estimated_days_min INT DEFAULT 1,
    estimated_days_max INT DEFAULT 7,
    is_express BOOLEAN DEFAULT FALSE,
    is_overnight BOOLEAN DEFAULT FALSE,
    supports_tracking BOOLEAN DEFAULT TRUE,
    supports_insurance BOOLEAN DEFAULT FALSE,
    max_insurance_value DECIMAL(10,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES shipping_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (shipping_type_id) REFERENCES shipping_types(id),
    UNIQUE KEY unique_provider_service (provider_id, code)
);

-- Enhanced Shipping Zones Table
DROP TABLE IF EXISTS shipping_zones;
CREATE TABLE shipping_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    base_distance_km INT DEFAULT 0 COMMENT 'Base distance from origin (California)',
    distance_multiplier DECIMAL(4,2) DEFAULT 1.00,
    customs_required BOOLEAN DEFAULT FALSE,
    max_processing_days INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Enhanced Product Dimensions Table
DROP TABLE IF EXISTS product_dimensions;
CREATE TABLE product_dimensions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNIQUE NOT NULL,
    package_type_id INT NOT NULL,
    weight_kg DECIMAL(8,3) NOT NULL DEFAULT 0.500,
    length_cm DECIMAL(8,2) NOT NULL DEFAULT 20.00,
    width_cm DECIMAL(8,2) NOT NULL DEFAULT 15.00,
    height_cm DECIMAL(8,2) NOT NULL DEFAULT 10.00,
    volume_cm3 DECIMAL(12,3) GENERATED ALWAYS AS (length_cm * width_cm * height_cm) STORED,
    fragile BOOLEAN DEFAULT FALSE,
    hazardous BOOLEAN DEFAULT FALSE,
    liquid BOOLEAN DEFAULT FALSE,
    perishable BOOLEAN DEFAULT FALSE,
    requires_signature BOOLEAN DEFAULT FALSE,
    requires_adult_signature BOOLEAN DEFAULT FALSE,
    declared_value DECIMAL(10,2) DEFAULT 0.00,
    country_of_origin VARCHAR(3) DEFAULT 'USA',
    hs_code VARCHAR(20) COMMENT 'Harmonized System code for customs',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (package_type_id) REFERENCES package_types(id)
);

-- Enhanced Shipping Rate Rules Table
DROP TABLE IF EXISTS shipping_rate_rules;
CREATE TABLE shipping_rate_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    service_id INT NOT NULL,
    zone_id INT NOT NULL,
    shipping_type_id INT NOT NULL,
    weight_min_kg DECIMAL(8,3) DEFAULT 0.000,
    weight_max_kg DECIMAL(8,3) DEFAULT 999.999,
    volume_min_cm3 DECIMAL(12,3) DEFAULT 0.000,
    volume_max_cm3 DECIMAL(12,3) DEFAULT 999999999.999,
    distance_min_km INT DEFAULT 0,
    distance_max_km INT DEFAULT 999999,
    base_cost DECIMAL(10,2) NOT NULL,
    cost_per_kg DECIMAL(10,2) DEFAULT 0.00,
    cost_per_km DECIMAL(10,4) DEFAULT 0.0000,
    cost_per_cm3 DECIMAL(10,6) DEFAULT 0.000000,
    insurance_rate DECIMAL(5,4) DEFAULT 0.0000 COMMENT 'Insurance cost as % of declared value',
    fuel_surcharge_rate DECIMAL(5,2) DEFAULT 0.00,
    customs_fee DECIMAL(10,2) DEFAULT 0.00,
    free_shipping_threshold DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    effective_date DATE,
    expiry_date DATE,
    created_by INT COMMENT 'User ID who created this rule',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES shipping_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES shipping_services(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE,
    FOREIGN KEY (shipping_type_id) REFERENCES shipping_types(id)
);

-- Shipping Insurance Table
CREATE TABLE shipping_insurance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    coverage_type ENUM('basic', 'standard', 'premium', 'custom') DEFAULT 'basic',
    max_coverage_amount DECIMAL(12,2) NOT NULL,
    rate_percentage DECIMAL(5,4) NOT NULL COMMENT 'Insurance rate as % of declared value',
    minimum_fee DECIMAL(8,2) DEFAULT 0.00,
    maximum_fee DECIMAL(8,2) DEFAULT 0.00,
    deductible_amount DECIMAL(8,2) DEFAULT 0.00,
    covers_theft BOOLEAN DEFAULT TRUE,
    covers_damage BOOLEAN DEFAULT TRUE,
    covers_loss BOOLEAN DEFAULT TRUE,
    covers_delay BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Enhanced Shipments Table
DROP TABLE IF EXISTS shipments;
CREATE TABLE shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    provider_id INT NOT NULL,
    service_id INT NOT NULL,
    shipping_type_id INT NOT NULL,
    insurance_id INT,
    tracking_number VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('created', 'label_printed', 'picked_up', 'in_transit', 'customs_clearance', 'out_for_delivery', 'delivered', 'exception', 'returned', 'cancelled') DEFAULT 'created',
    estimated_delivery DATE,
    actual_delivery DATETIME,
    shipping_cost DECIMAL(10,2),
    insurance_cost DECIMAL(10,2) DEFAULT 0.00,
    fuel_surcharge DECIMAL(10,2) DEFAULT 0.00,
    customs_fee DECIMAL(10,2) DEFAULT 0.00,
    total_cost DECIMAL(10,2),
    weight_kg DECIMAL(8,3),
    volume_cm3 DECIMAL(12,3),
    distance_km INT,
    declared_value DECIMAL(10,2),
    origin_latitude DECIMAL(10,8),
    origin_longitude DECIMAL(11,8),
    origin_address TEXT,
    destination_latitude DECIMAL(10,8),
    destination_longitude DECIMAL(11,8),
    destination_address TEXT,
    customs_declaration TEXT,
    special_instructions TEXT,
    signature_required BOOLEAN DEFAULT FALSE,
    adult_signature_required BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES shipping_providers(id),
    FOREIGN KEY (service_id) REFERENCES shipping_services(id),
    FOREIGN KEY (shipping_type_id) REFERENCES shipping_types(id),
    FOREIGN KEY (insurance_id) REFERENCES shipping_insurance(id)
);

-- Enhanced Tracking Events Table
DROP TABLE IF EXISTS tracking_events;
CREATE TABLE tracking_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    description TEXT,
    location VARCHAR(255),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    facility_name VARCHAR(255),
    event_time DATETIME NOT NULL,
    estimated_delivery DATETIME,
    next_location VARCHAR(255),
    delay_reason VARCHAR(255),
    created_by_system BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
);

-- User Addresses with Coordinates
CREATE TABLE user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address_name VARCHAR(100) NOT NULL,
    recipient_name VARCHAR(100) NOT NULL,
    company_name VARCHAR(100),
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state_code VARCHAR(10),
    postal_code VARCHAR(20) NOT NULL,
    country_code VARCHAR(3) NOT NULL,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    phone VARCHAR(20),
    email VARCHAR(100),
    is_default BOOLEAN DEFAULT FALSE,
    is_business BOOLEAN DEFAULT FALSE,
    delivery_instructions TEXT,
    access_code VARCHAR(50),
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_addresses_coordinates (latitude, longitude),
    INDEX idx_user_addresses_user (user_id)
);

-- Shipping Restrictions Table
CREATE TABLE shipping_restrictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    country_code VARCHAR(3),
    state_code VARCHAR(10),
    provider_id INT,
    product_category VARCHAR(100),
    restriction_type ENUM('prohibited', 'restricted', 'documentation_required', 'additional_fees') NOT NULL,
    description TEXT,
    additional_fee DECIMAL(10,2) DEFAULT 0.00,
    required_documents TEXT,
    max_quantity INT,
    max_value DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES shipping_providers(id) ON DELETE CASCADE
);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

CREATE INDEX idx_countries_coordinates ON countries(latitude, longitude);
CREATE INDEX idx_states_coordinates ON states(latitude, longitude);
CREATE INDEX idx_shipping_rates_lookup ON shipping_rate_rules(zone_id, weight_min_kg, weight_max_kg, volume_min_cm3, volume_max_cm3);
CREATE INDEX idx_shipments_tracking ON shipments(tracking_number);
CREATE INDEX idx_shipments_status ON shipments(status);
CREATE INDEX idx_tracking_events_time ON tracking_events(event_time);
CREATE INDEX idx_user_addresses_location ON user_addresses(country_code, state_code, postal_code);
CREATE INDEX idx_restrictions_lookup ON shipping_restrictions(country_code, state_code, provider_id);

-- =====================================================
-- SAMPLE DATA - SHIPPING TYPES
-- =====================================================

INSERT INTO shipping_types (name, code, description, base_multiplier, max_weight_kg, requires_signature, insurance_included) VALUES
('Standard Ground', 'STANDARD', 'Standard ground shipping service', 1.00, 70.0, FALSE, FALSE),
('Express', 'EXPRESS', 'Express shipping service', 1.50, 50.0, FALSE, FALSE),
('Overnight', 'OVERNIGHT', 'Next business day delivery', 2.50, 30.0, TRUE, TRUE),
('International Express', 'INTL_EXPRESS', 'International express delivery', 3.00, 50.0, TRUE, TRUE),
('International Standard', 'INTL_STANDARD', 'International standard delivery', 2.00, 70.0, FALSE, FALSE),
('Same Day', 'SAME_DAY', 'Same day delivery service', 5.00, 20.0, TRUE, TRUE),
('Freight', 'FREIGHT', 'Heavy freight shipping', 0.80, 1000.0, TRUE, FALSE),
('White Glove', 'WHITE_GLOVE', 'Premium delivery with setup', 4.00, 100.0, TRUE, TRUE);

-- =====================================================
-- SAMPLE DATA - PACKAGE TYPES
-- =====================================================

INSERT INTO package_types (name, code, description, volume_multiplier, fragile_surcharge, handling_fee, max_weight_kg) VALUES
('Standard Box', 'STD_BOX', 'Standard cardboard box packaging', 1.00, 0.00, 0.00, 50.0),
('Padded Envelope', 'PADDED_ENV', 'Padded envelope for small items', 0.50, 0.00, 0.00, 2.0),
('Fragile Box', 'FRAGILE_BOX', 'Reinforced box for fragile items', 1.20, 5.00, 2.00, 30.0),
('Tube', 'TUBE', 'Cylindrical tube packaging', 0.80, 0.00, 1.00, 10.0),
('Pallet', 'PALLET', 'Pallet shipping for large items', 2.00, 0.00, 25.00, 1000.0),
('Custom Crate', 'CUSTOM_CRATE', 'Custom wooden crate', 1.50, 10.00, 50.00, 500.0),
('Temperature Controlled', 'TEMP_CTRL', 'Temperature controlled packaging', 2.50, 0.00, 15.00, 25.0),
('Hazmat Container', 'HAZMAT', 'Hazardous materials container', 3.00, 0.00, 75.00, 20.0);

-- =====================================================
-- SAMPLE DATA - INSURANCE OPTIONS
-- =====================================================

INSERT INTO shipping_insurance (name, code, description, coverage_type, max_coverage_amount, rate_percentage, minimum_fee, maximum_fee) VALUES
('Basic Coverage', 'BASIC', 'Basic shipping insurance coverage', 'basic', 1000.00, 0.0050, 2.00, 50.00),
('Standard Coverage', 'STANDARD', 'Standard shipping insurance coverage', 'standard', 5000.00, 0.0075, 5.00, 100.00),
('Premium Coverage', 'PREMIUM', 'Premium shipping insurance coverage', 'premium', 25000.00, 0.0100, 10.00, 250.00),
('High Value Coverage', 'HIGH_VALUE', 'High value item insurance', 'custom', 100000.00, 0.0150, 25.00, 500.00);

-- =====================================================
-- WORLDWIDE COUNTRIES DATA WITH COORDINATES
-- =====================================================

INSERT INTO countries (code, name, currency_code, currency_symbol, tax_rate, latitude, longitude, timezone, shipping_allowed, customs_required) VALUES
-- North America
('USA', 'United States', 'USD', '$', 0.00, 39.8283, -98.5795, 'America/New_York', TRUE, FALSE),
('CAN', 'Canada', 'CAD', 'C$', 5.00, 56.1304, -106.3468, 'America/Toronto', TRUE, TRUE),
('MEX', 'Mexico', 'MXN', '$', 16.00, 23.6345, -102.5528, 'America/Mexico_City', TRUE, TRUE),

-- Europe
('GBR', 'United Kingdom', 'GBP', '£', 20.00, 55.3781, -3.4360, 'Europe/London', TRUE, TRUE),
('DEU', 'Germany', 'EUR', '€', 19.00, 51.1657, 10.4515, 'Europe/Berlin', TRUE, TRUE),
('FRA', 'France', 'EUR', '€', 20.00, 46.2276, 2.2137, 'Europe/Paris', TRUE, TRUE),
('ITA', 'Italy', 'EUR', '€', 22.00, 41.8719, 12.5674, 'Europe/Rome', TRUE, TRUE),
('ESP', 'Spain', 'EUR', '€', 21.00, 40.4637, -3.7492, 'Europe/Madrid', TRUE, TRUE),
('NLD', 'Netherlands', 'EUR', '€', 21.00, 52.1326, 5.2913, 'Europe/Amsterdam', TRUE, TRUE),
('BEL', 'Belgium', 'EUR', '€', 21.00, 50.5039, 4.4699, 'Europe/Brussels', TRUE, TRUE),
('CHE', 'Switzerland', 'CHF', 'Fr', 7.70, 46.8182, 8.2275, 'Europe/Zurich', TRUE, TRUE),
('AUT', 'Austria', 'EUR', '€', 20.00, 47.5162, 14.5501, 'Europe/Vienna', TRUE, TRUE),
('SWE', 'Sweden', 'SEK', 'kr', 25.00, 60.1282, 18.6435, 'Europe/Stockholm', TRUE, TRUE),
('NOR', 'Norway', 'NOK', 'kr', 25.00, 60.4720, 8.4689, 'Europe/Oslo', TRUE, TRUE),
('DNK', 'Denmark', 'DKK', 'kr', 25.00, 56.2639, 9.5018, 'Europe/Copenhagen', TRUE, TRUE),
('FIN', 'Finland', 'EUR', '€', 24.00, 61.9241, 25.7482, 'Europe/Helsinki', TRUE, TRUE),
('POL', 'Poland', 'PLN', 'zł', 23.00, 51.9194, 19.1451, 'Europe/Warsaw', TRUE, TRUE),
('CZE', 'Czech Republic', 'CZK', 'Kč', 21.00, 49.8175, 15.4730, 'Europe/Prague', TRUE, TRUE),
('HUN', 'Hungary', 'HUF', 'Ft', 27.00, 47.1625, 19.5033, 'Europe/Budapest', TRUE, TRUE),
('ROU', 'Romania', 'RON', 'lei', 19.00, 45.9432, 24.9668, 'Europe/Bucharest', TRUE, TRUE),
('BGR', 'Bulgaria', 'BGN', 'лв', 20.00, 42.7339, 25.4858, 'Europe/Sofia', TRUE, TRUE),
('HRV', 'Croatia', 'EUR', '€', 25.00, 45.1000, 15.2000, 'Europe/Zagreb', TRUE, TRUE),
('GRC', 'Greece', 'EUR', '€', 24.00, 39.0742, 21.8243, 'Europe/Athens', TRUE, TRUE),
('PRT', 'Portugal', 'EUR', '€', 23.00, 39.3999, -8.2245, 'Europe/Lisbon', TRUE, TRUE),
('IRL', 'Ireland', 'EUR', '€', 23.00, 53.4129, -8.2439, 'Europe/Dublin', TRUE, TRUE),

-- Asia Pacific
('JPN', 'Japan', 'JPY', '¥', 10.00, 36.2048, 138.2529, 'Asia/Tokyo', TRUE, TRUE),
('CHN', 'China', 'CNY', '¥', 13.00, 35.8617, 104.1954, 'Asia/Shanghai', TRUE, TRUE),
('KOR', 'South Korea', 'KRW', '₩', 10.00, 35.9078, 127.7669, 'Asia/Seoul', TRUE, TRUE),
('SGP', 'Singapore', 'SGD', 'S$', 7.00, 1.3521, 103.8198, 'Asia/Singapore', TRUE, TRUE),
('HKG', 'Hong Kong', 'HKD', 'HK$', 0.00, 22.3193, 114.1694, 'Asia/Hong_Kong', TRUE, TRUE),
('TWN', 'Taiwan', 'TWD', 'NT$', 5.00, 23.6978, 120.9605, 'Asia/Taipei', TRUE, TRUE),
('THA', 'Thailand', 'THB', '฿', 7.00, 15.8700, 100.9925, 'Asia/Bangkok', TRUE, TRUE),
('MYS', 'Malaysia', 'MYR', 'RM', 6.00, 4.2105, 101.9758, 'Asia/Kuala_Lumpur', TRUE, TRUE),
('IDN', 'Indonesia', 'IDR', 'Rp', 10.00, -0.7893, 113.9213, 'Asia/Jakarta', TRUE, TRUE),
('PHL', 'Philippines', 'PHP', '₱', 12.00, 12.8797, 121.7740, 'Asia/Manila', TRUE, TRUE),
('VNM', 'Vietnam', 'VND', '₫', 10.00, 14.0583, 108.2772, 'Asia/Ho_Chi_Minh', TRUE, TRUE),
('IND', 'India', 'INR', '₹', 18.00, 20.5937, 78.9629, 'Asia/Kolkata', TRUE, TRUE),
('AUS', 'Australia', 'AUD', 'A$', 10.00, -25.2744, 133.7751, 'Australia/Sydney', TRUE, TRUE),
('NZL', 'New Zealand', 'NZD', 'NZ$', 15.00, -40.9006, 174.8860, 'Pacific/Auckland', TRUE, TRUE),

-- Middle East
('ARE', 'United Arab Emirates', 'AED', 'د.إ', 5.00, 23.4241, 53.8478, 'Asia/Dubai', TRUE, TRUE),
('SAU', 'Saudi Arabia', 'SAR', '﷼', 15.00, 23.8859, 45.0792, 'Asia/Riyadh', TRUE, TRUE),
('ISR', 'Israel', 'ILS', '₪', 17.00, 31.0461, 34.8516, 'Asia/Jerusalem', TRUE, TRUE),
('TUR', 'Turkey', 'TRY', '₺', 18.00, 38.9637, 35.2433, 'Europe/Istanbul', TRUE, TRUE),

-- South America
('BRA', 'Brazil', 'BRL', 'R$', 17.00, -14.2350, -51.9253, 'America/Sao_Paulo', TRUE, TRUE),
('ARG', 'Argentina', 'ARS', '$', 21.00, -38.4161, -63.6167, 'America/Argentina/Buenos_Aires', TRUE, TRUE),
('CHL', 'Chile', 'CLP', '$', 19.00, -35.6751, -71.5430, 'America/Santiago', TRUE, TRUE),
('COL', 'Colombia', 'COP', '$', 19.00, 4.5709, -74.2973, 'America/Bogota', TRUE, TRUE),
('PER', 'Peru', 'PEN', 'S/', 18.00, -9.1900, -75.0152, 'America/Lima', TRUE, TRUE),

-- Africa
('ZAF', 'South Africa', 'ZAR', 'R', 15.00, -30.5595, 22.9375, 'Africa/Johannesburg', TRUE, TRUE),
('EGY', 'Egypt', 'EGP', '£', 14.00, 26.0975, 31.2357, 'Africa/Cairo', TRUE, TRUE),
('NGA', 'Nigeria', 'NGN', '₦', 7.50, 9.0820, 8.6753, 'Africa/Lagos', TRUE, TRUE),
('KEN', 'Kenya', 'KES', 'KSh', 16.00, -0.0236, 37.9062, 'Africa/Nairobi', TRUE, TRUE),
('MAR', 'Morocco', 'MAD', 'د.م.', 20.00, 31.7917, -7.0926, 'Africa/Casablanca', TRUE, TRUE),

-- Others
('RUS', 'Russia', 'RUB', '₽', 20.00, 61.5240, 105.3188, 'Europe/Moscow', TRUE, TRUE),
('UKR', 'Ukraine', 'UAH', '₴', 20.00, 48.3794, 31.1656, 'Europe/Kiev', TRUE, TRUE);

SELECT 'Global Shipping System Tables Created Successfully!' as Status;
