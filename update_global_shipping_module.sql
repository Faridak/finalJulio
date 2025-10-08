-- =====================================================
-- VentDepot Global Shipping & Geographical Module Update
-- Run this SQL to add complete global shipping system
-- =====================================================

USE finalJulio;

-- =====================================================
-- DROP EXISTING TABLES IF THEY EXIST
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS tracking_events;
DROP TABLE IF EXISTS shipments;
DROP TABLE IF EXISTS shipping_restrictions;
DROP TABLE IF EXISTS user_addresses;
DROP TABLE IF EXISTS shipping_rate_rules;
DROP TABLE IF EXISTS product_dimensions;
DROP TABLE IF EXISTS shipping_services;
DROP TABLE IF EXISTS shipping_providers;
DROP TABLE IF EXISTS shipping_insurance;
DROP TABLE IF EXISTS package_types;
DROP TABLE IF EXISTS shipping_types;
DROP TABLE IF EXISTS zone_countries;
DROP TABLE IF EXISTS shipping_zones;
DROP TABLE IF EXISTS tax_rules;
DROP TABLE IF EXISTS currency_rates;
DROP TABLE IF EXISTS cities;
DROP TABLE IF EXISTS states;
DROP TABLE IF EXISTS countries;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- ENHANCED GEOGRAPHICAL TABLES
-- =====================================================

-- Countries Table with GPS coordinates
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_countries_coordinates (latitude, longitude),
    INDEX idx_countries_code (code)
);

-- States/Provinces Table with GPS coordinates
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
    UNIQUE KEY unique_state_code (country_id, code),
    INDEX idx_states_coordinates (latitude, longitude),
    INDEX idx_states_code (country_id, code)
);

-- Cities Table with GPS coordinates
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

-- Shipping Zones Table
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

-- Zone Countries Mapping
CREATE TABLE zone_countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    country_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE,
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
    UNIQUE KEY unique_zone_country (zone_id, country_id)
);

-- =====================================================
-- SHIPPING SYSTEM TABLES
-- =====================================================

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

-- Enhanced Product Dimensions Table
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
    FOREIGN KEY (package_type_id) REFERENCES package_types(id),
    INDEX idx_product_dimensions_product (product_id)
);

-- Enhanced Shipping Rate Rules Table
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
    FOREIGN KEY (shipping_type_id) REFERENCES shipping_types(id),
    INDEX idx_shipping_rates_lookup (zone_id, weight_min_kg, weight_max_kg, volume_min_cm3, volume_max_cm3)
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
    FOREIGN KEY (insurance_id) REFERENCES shipping_insurance(id),
    INDEX idx_shipments_tracking (tracking_number),
    INDEX idx_shipments_status (status),
    INDEX idx_shipments_order (order_id)
);

-- Enhanced Tracking Events Table
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
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    INDEX idx_tracking_events_shipment (shipment_id),
    INDEX idx_tracking_events_time (event_time)
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
    INDEX idx_user_addresses_user (user_id),
    INDEX idx_user_addresses_location (country_code, state_code, postal_code)
);

-- Tax Rules Table
CREATE TABLE tax_rules (
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
    FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE CASCADE,
    INDEX idx_tax_rules_location (country_id, state_id)
);

-- Currency Exchange Rates Table
CREATE TABLE currency_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_currency VARCHAR(3) NOT NULL,
    to_currency VARCHAR(3) NOT NULL,
    rate DECIMAL(12,6) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_currency_pair (from_currency, to_currency),
    INDEX idx_currency_rates_pair (from_currency, to_currency)
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
    FOREIGN KEY (provider_id) REFERENCES shipping_providers(id) ON DELETE CASCADE,
    INDEX idx_restrictions_lookup (country_code, state_code, provider_id)
);

-- =====================================================
-- SAMPLE DATA INSERTION
-- =====================================================

-- Insert Worldwide Countries with GPS Coordinates
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

-- Insert US States with GPS Coordinates
INSERT INTO states (country_id, code, name, tax_rate, latitude, longitude, timezone) VALUES
((SELECT id FROM countries WHERE code = 'USA'), 'CA', 'California', 7.25, 36.7783, -119.4179, 'America/Los_Angeles'),
((SELECT id FROM countries WHERE code = 'USA'), 'NY', 'New York', 4.00, 40.7128, -74.0060, 'America/New_York'),
((SELECT id FROM countries WHERE code = 'USA'), 'TX', 'Texas', 6.25, 31.9686, -99.9018, 'America/Chicago'),
((SELECT id FROM countries WHERE code = 'USA'), 'FL', 'Florida', 6.00, 27.7663, -81.6868, 'America/New_York'),
((SELECT id FROM countries WHERE code = 'USA'), 'IL', 'Illinois', 6.25, 40.6331, -89.3985, 'America/Chicago'),
((SELECT id FROM countries WHERE code = 'USA'), 'PA', 'Pennsylvania', 6.00, 41.2033, -77.1945, 'America/New_York'),
((SELECT id FROM countries WHERE code = 'USA'), 'OH', 'Ohio', 5.75, 40.4173, -82.9071, 'America/New_York'),
((SELECT id FROM countries WHERE code = 'USA'), 'GA', 'Georgia', 4.00, 32.1656, -82.9001, 'America/New_York'),
((SELECT id FROM countries WHERE code = 'USA'), 'NC', 'North Carolina', 4.75, 35.7596, -79.0193, 'America/New_York'),
((SELECT id FROM countries WHERE code = 'USA'), 'MI', 'Michigan', 6.00, 44.3467, -85.4102, 'America/Detroit'),
((SELECT id FROM countries WHERE code = 'USA'), 'WA', 'Washington', 6.50, 47.7511, -120.7401, 'America/Los_Angeles'),
((SELECT id FROM countries WHERE code = 'USA'), 'AZ', 'Arizona', 5.60, 34.0489, -111.0937, 'America/Phoenix'),
((SELECT id FROM countries WHERE code = 'USA'), 'NV', 'Nevada', 6.85, 38.8026, -116.4194, 'America/Los_Angeles'),
((SELECT id FROM countries WHERE code = 'USA'), 'OR', 'Oregon', 0.00, 43.8041, -120.5542, 'America/Los_Angeles'),
((SELECT id FROM countries WHERE code = 'USA'), 'CO', 'Colorado', 2.90, 39.5501, -105.7821, 'America/Denver');

-- Insert Canadian Provinces with GPS Coordinates
INSERT INTO states (country_id, code, name, tax_rate, latitude, longitude, timezone) VALUES
((SELECT id FROM countries WHERE code = 'CAN'), 'ON', 'Ontario', 13.00, 50.0000, -85.0000, 'America/Toronto'),
((SELECT id FROM countries WHERE code = 'CAN'), 'QC', 'Quebec', 14.975, 53.0000, -70.0000, 'America/Toronto'),
((SELECT id FROM countries WHERE code = 'CAN'), 'BC', 'British Columbia', 12.00, 53.7267, -127.6476, 'America/Vancouver'),
((SELECT id FROM countries WHERE code = 'CAN'), 'AB', 'Alberta', 5.00, 53.9333, -116.5765, 'America/Edmonton'),
((SELECT id FROM countries WHERE code = 'CAN'), 'MB', 'Manitoba', 12.00, 53.7609, -98.8139, 'America/Winnipeg'),
((SELECT id FROM countries WHERE code = 'CAN'), 'SK', 'Saskatchewan', 11.00, 52.9399, -106.4509, 'America/Regina'),
((SELECT id FROM countries WHERE code = 'CAN'), 'NS', 'Nova Scotia', 15.00, 44.6820, -63.7443, 'America/Halifax'),
((SELECT id FROM countries WHERE code = 'CAN'), 'NB', 'New Brunswick', 15.00, 46.5653, -66.4619, 'America/Moncton');

-- Insert Shipping Zones
INSERT INTO shipping_zones (name, code, description, base_distance_km, distance_multiplier, customs_required) VALUES
('Domestic US', 'US_DOMESTIC', 'United States domestic shipping', 0, 1.00, FALSE),
('Canada', 'CANADA', 'Canada shipping zone', 2500, 1.20, TRUE),
('Mexico', 'MEXICO', 'Mexico shipping zone', 2000, 1.15, TRUE),
('Europe', 'EUROPE', 'European Union countries', 8500, 1.50, TRUE),
('Asia Pacific', 'ASIA_PACIFIC', 'Asia Pacific region', 11000, 1.75, TRUE),
('International', 'INTERNATIONAL', 'Rest of world', 12000, 2.00, TRUE);

-- Map Countries to Zones
INSERT INTO zone_countries (zone_id, country_id) VALUES
((SELECT id FROM shipping_zones WHERE code = 'US_DOMESTIC'), (SELECT id FROM countries WHERE code = 'USA')),
((SELECT id FROM shipping_zones WHERE code = 'CANADA'), (SELECT id FROM countries WHERE code = 'CAN')),
((SELECT id FROM shipping_zones WHERE code = 'MEXICO'), (SELECT id FROM countries WHERE code = 'MEX')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'GBR')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'DEU')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'FRA')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'ITA')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'ESP')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'NLD')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'BEL')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'CHE')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'AUT')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'SWE')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'NOR')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'DNK')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'FIN')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'POL')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'CZE')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'HUN')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'ROU')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'BGR')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'HRV')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'GRC')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'PRT')),
((SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM countries WHERE code = 'IRL')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'JPN')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'CHN')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'KOR')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'SGP')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'HKG')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'TWN')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'THA')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'MYS')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'IDN')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'PHL')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'VNM')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'IND')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'AUS')),
((SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM countries WHERE code = 'NZL')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'ARE')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'SAU')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'ISR')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'TUR')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'BRA')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'ARG')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'CHL')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'COL')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'PER')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'ZAF')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'EGY')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'NGA')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'KEN')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'MAR')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'RUS')),
((SELECT id FROM shipping_zones WHERE code = 'INTERNATIONAL'), (SELECT id FROM countries WHERE code = 'UKR'));

-- Insert Shipping Types
INSERT INTO shipping_types (name, code, description, base_multiplier, max_weight_kg, requires_signature, insurance_included) VALUES
('Standard Ground', 'STANDARD', 'Standard ground shipping service', 1.00, 70.0, FALSE, FALSE),
('Express', 'EXPRESS', 'Express shipping service', 1.50, 50.0, FALSE, FALSE),
('Overnight', 'OVERNIGHT', 'Next business day delivery', 2.50, 30.0, TRUE, TRUE),
('International Express', 'INTL_EXPRESS', 'International express delivery', 3.00, 50.0, TRUE, TRUE),
('International Standard', 'INTL_STANDARD', 'International standard delivery', 2.00, 70.0, FALSE, FALSE),
('Same Day', 'SAME_DAY', 'Same day delivery service', 5.00, 20.0, TRUE, TRUE),
('Freight', 'FREIGHT', 'Heavy freight shipping', 0.80, 1000.0, TRUE, FALSE),
('White Glove', 'WHITE_GLOVE', 'Premium delivery with setup', 4.00, 100.0, TRUE, TRUE);

-- Insert Package Types
INSERT INTO package_types (name, code, description, volume_multiplier, fragile_surcharge, handling_fee, max_weight_kg) VALUES
('Standard Box', 'STD_BOX', 'Standard cardboard box packaging', 1.00, 0.00, 0.00, 50.0),
('Padded Envelope', 'PADDED_ENV', 'Padded envelope for small items', 0.50, 0.00, 0.00, 2.0),
('Fragile Box', 'FRAGILE_BOX', 'Reinforced box for fragile items', 1.20, 5.00, 2.00, 30.0),
('Tube', 'TUBE', 'Cylindrical tube packaging', 0.80, 0.00, 1.00, 10.0),
('Pallet', 'PALLET', 'Pallet shipping for large items', 2.00, 0.00, 25.00, 1000.0),
('Custom Crate', 'CUSTOM_CRATE', 'Custom wooden crate', 1.50, 10.00, 50.00, 500.0),
('Temperature Controlled', 'TEMP_CTRL', 'Temperature controlled packaging', 2.50, 0.00, 15.00, 25.0),
('Hazmat Container', 'HAZMAT', 'Hazardous materials container', 3.00, 0.00, 75.00, 20.0);

-- Insert Shipping Providers
INSERT INTO shipping_providers (name, code, tracking_url_template, is_active, supports_international, supports_insurance, max_weight_kg, max_dimensions_cm) VALUES
('FedEx', 'FEDEX', 'https://www.fedex.com/fedextrack/?trknbr={tracking_number}', TRUE, TRUE, TRUE, 68.0, '274x274x274'),
('UPS', 'UPS', 'https://www.ups.com/track?tracknum={tracking_number}', TRUE, TRUE, TRUE, 70.0, '270x270x270'),
('USPS', 'USPS', 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking_number}', TRUE, TRUE, FALSE, 32.0, '108x108x108'),
('DHL', 'DHL', 'https://www.dhl.com/en/express/tracking.html?AWB={tracking_number}', TRUE, TRUE, TRUE, 70.0, '300x300x300'),
('Local Courier', 'LOCAL', 'https://ventdepot.com/track/{tracking_number}', TRUE, FALSE, FALSE, 25.0, '100x100x100');

-- Insert Insurance Options
INSERT INTO shipping_insurance (name, code, description, coverage_type, max_coverage_amount, rate_percentage, minimum_fee, maximum_fee) VALUES
('Basic Coverage', 'BASIC', 'Basic shipping insurance coverage', 'basic', 1000.00, 0.0050, 2.00, 50.00),
('Standard Coverage', 'STANDARD', 'Standard shipping insurance coverage', 'standard', 5000.00, 0.0075, 5.00, 100.00),
('Premium Coverage', 'PREMIUM', 'Premium shipping insurance coverage', 'premium', 25000.00, 0.0100, 10.00, 250.00),
('High Value Coverage', 'HIGH_VALUE', 'High value item insurance', 'custom', 100000.00, 0.0150, 25.00, 500.00);

-- Insert Currency Exchange Rates
INSERT INTO currency_rates (from_currency, to_currency, rate) VALUES
('USD', 'CAD', 1.35),
('USD', 'MXN', 17.50),
('USD', 'EUR', 0.85),
('USD', 'GBP', 0.73),
('USD', 'JPY', 110.00),
('USD', 'AUD', 1.45),
('USD', 'CHF', 0.92),
('USD', 'SEK', 8.75),
('USD', 'NOK', 8.50),
('USD', 'DKK', 6.35),
('USD', 'PLN', 3.85),
('USD', 'CZK', 21.50),
('USD', 'HUF', 295.00),
('USD', 'RON', 4.15),
('USD', 'BGN', 1.66),
('USD', 'HRK', 6.40),
('USD', 'CNY', 6.45),
('USD', 'KRW', 1180.00),
('USD', 'SGD', 1.35),
('USD', 'HKD', 7.80),
('USD', 'TWD', 28.50),
('USD', 'THB', 33.00),
('USD', 'MYR', 4.15),
('USD', 'IDR', 14250.00),
('USD', 'PHP', 50.00),
('USD', 'VND', 23000.00),
('USD', 'INR', 74.50),
('USD', 'NZD', 1.42),
('USD', 'AED', 3.67),
('USD', 'SAR', 3.75),
('USD', 'ILS', 3.25),
('USD', 'TRY', 8.50),
('USD', 'BRL', 5.20),
('USD', 'ARS', 98.50),
('USD', 'CLP', 750.00),
('USD', 'COP', 3650.00),
('USD', 'PEN', 3.60),
('USD', 'ZAR', 14.50),
('USD', 'EGP', 15.70),
('USD', 'NGN', 410.00),
('USD', 'KES', 108.00),
('USD', 'MAD', 9.00),
('USD', 'RUB', 73.50),
('USD', 'UAH', 27.00);

-- Insert Shipping Services
INSERT INTO shipping_services (provider_id, shipping_type_id, name, code, description, estimated_days_min, estimated_days_max, is_express, supports_insurance, max_insurance_value) VALUES
-- FedEx Services
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT id FROM shipping_types WHERE code = 'OVERNIGHT'), 'FedEx Overnight', 'FEDEX_OVERNIGHT', 'Next business day delivery', 1, 1, TRUE, TRUE, 25000.00),
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT id FROM shipping_types WHERE code = 'EXPRESS'), 'FedEx 2Day', 'FEDEX_2DAY', 'Delivery in 2 business days', 2, 2, TRUE, TRUE, 25000.00),
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT id FROM shipping_types WHERE code = 'STANDARD'), 'FedEx Ground', 'FEDEX_GROUND', 'Ground delivery service', 1, 5, FALSE, TRUE, 10000.00),
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT id FROM shipping_types WHERE code = 'INTL_EXPRESS'), 'FedEx International', 'FEDEX_INTL', 'International express delivery', 2, 7, TRUE, TRUE, 50000.00),

-- UPS Services
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT id FROM shipping_types WHERE code = 'OVERNIGHT'), 'UPS Next Day Air', 'UPS_NEXT_DAY', 'Next business day delivery', 1, 1, TRUE, TRUE, 25000.00),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT id FROM shipping_types WHERE code = 'EXPRESS'), 'UPS 2nd Day Air', 'UPS_2DAY', 'Second business day delivery', 2, 2, TRUE, TRUE, 25000.00),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT id FROM shipping_types WHERE code = 'STANDARD'), 'UPS Ground', 'UPS_GROUND', 'Ground delivery service', 1, 5, FALSE, TRUE, 10000.00),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT id FROM shipping_types WHERE code = 'INTL_EXPRESS'), 'UPS Worldwide Express', 'UPS_WORLDWIDE', 'International express delivery', 1, 5, TRUE, TRUE, 50000.00),

-- USPS Services
((SELECT id FROM shipping_providers WHERE code = 'USPS'), (SELECT id FROM shipping_types WHERE code = 'OVERNIGHT'), 'USPS Priority Express', 'USPS_EXPRESS', 'Overnight delivery', 1, 2, TRUE, FALSE, 0.00),
((SELECT id FROM shipping_providers WHERE code = 'USPS'), (SELECT id FROM shipping_types WHERE code = 'EXPRESS'), 'USPS Priority Mail', 'USPS_PRIORITY', 'Priority mail service', 1, 3, FALSE, FALSE, 0.00),
((SELECT id FROM shipping_providers WHERE code = 'USPS'), (SELECT id FROM shipping_types WHERE code = 'STANDARD'), 'USPS Ground Advantage', 'USPS_GROUND', 'Ground delivery service', 2, 5, FALSE, FALSE, 0.00),
((SELECT id FROM shipping_providers WHERE code = 'USPS'), (SELECT id FROM shipping_types WHERE code = 'INTL_STANDARD'), 'USPS International', 'USPS_INTL', 'International mail service', 6, 21, FALSE, FALSE, 0.00),

-- DHL Services
((SELECT id FROM shipping_providers WHERE code = 'DHL'), (SELECT id FROM shipping_types WHERE code = 'INTL_EXPRESS'), 'DHL Express Worldwide', 'DHL_EXPRESS', 'Express worldwide delivery', 1, 4, TRUE, TRUE, 100000.00),
((SELECT id FROM shipping_providers WHERE code = 'DHL'), (SELECT id FROM shipping_types WHERE code = 'INTL_STANDARD'), 'DHL Economy Select', 'DHL_ECONOMY', 'Economy international delivery', 4, 8, FALSE, TRUE, 25000.00),

-- Local Courier
((SELECT id FROM shipping_providers WHERE code = 'LOCAL'), (SELECT id FROM shipping_types WHERE code = 'SAME_DAY'), 'Same Day Delivery', 'LOCAL_SAME_DAY', 'Same day local delivery', 0, 0, TRUE, FALSE, 0.00),
((SELECT id FROM shipping_providers WHERE code = 'LOCAL'), (SELECT id FROM shipping_types WHERE code = 'STANDARD'), 'Next Day Local', 'LOCAL_NEXT_DAY', 'Next day local delivery', 1, 1, FALSE, FALSE, 0.00);

-- Insert Sample Shipping Rate Rules
INSERT INTO shipping_rate_rules (provider_id, service_id, zone_id, shipping_type_id, weight_min_kg, weight_max_kg, volume_min_cm3, volume_max_cm3, distance_min_km, distance_max_km, base_cost, cost_per_kg, cost_per_km, cost_per_cm3, insurance_rate, fuel_surcharge_rate, customs_fee, free_shipping_threshold) VALUES

-- FedEx US Domestic Rates
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'FEDEX' AND ss.code = 'FEDEX_OVERNIGHT'), (SELECT id FROM shipping_zones WHERE code = 'US_DOMESTIC'), (SELECT id FROM shipping_types WHERE code = 'OVERNIGHT'), 0.000, 10.000, 0.000, 100000.000, 0, 5000, 25.99, 5.00, 0.0050, 0.000100, 0.0050, 12.50, 0.00, 100.00),
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'FEDEX' AND ss.code = 'FEDEX_2DAY'), (SELECT id FROM shipping_zones WHERE code = 'US_DOMESTIC'), (SELECT id FROM shipping_types WHERE code = 'EXPRESS'), 0.000, 10.000, 0.000, 100000.000, 0, 5000, 15.99, 3.00, 0.0030, 0.000075, 0.0050, 12.50, 0.00, 75.00),
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'FEDEX' AND ss.code = 'FEDEX_GROUND'), (SELECT id FROM shipping_zones WHERE code = 'US_DOMESTIC'), (SELECT id FROM shipping_types WHERE code = 'STANDARD'), 0.000, 50.000, 0.000, 500000.000, 0, 5000, 8.99, 1.50, 0.0015, 0.000025, 0.0050, 12.50, 0.00, 50.00),

-- UPS US Domestic Rates
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'UPS' AND ss.code = 'UPS_NEXT_DAY'), (SELECT id FROM shipping_zones WHERE code = 'US_DOMESTIC'), (SELECT id FROM shipping_types WHERE code = 'OVERNIGHT'), 0.000, 10.000, 0.000, 100000.000, 0, 5000, 24.99, 4.50, 0.0045, 0.000095, 0.0050, 12.50, 0.00, 100.00),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'UPS' AND ss.code = 'UPS_2DAY'), (SELECT id FROM shipping_zones WHERE code = 'US_DOMESTIC'), (SELECT id FROM shipping_types WHERE code = 'EXPRESS'), 0.000, 10.000, 0.000, 100000.000, 0, 5000, 14.99, 2.75, 0.0025, 0.000070, 0.0050, 12.50, 0.00, 75.00),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'UPS' AND ss.code = 'UPS_GROUND'), (SELECT id FROM shipping_zones WHERE code = 'US_DOMESTIC'), (SELECT id FROM shipping_types WHERE code = 'STANDARD'), 0.000, 50.000, 0.000, 500000.000, 0, 5000, 7.99, 1.25, 0.0012, 0.000020, 0.0050, 12.50, 0.00, 50.00),

-- USPS US Domestic Rates
((SELECT id FROM shipping_providers WHERE code = 'USPS'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'USPS' AND ss.code = 'USPS_EXPRESS'), (SELECT id FROM shipping_zones WHERE code = 'US_DOMESTIC'), (SELECT id FROM shipping_types WHERE code = 'OVERNIGHT'), 0.000, 10.000, 0.000, 50000.000, 0, 5000, 22.99, 4.00, 0.0040, 0.000080, 0.0000, 10.00, 0.00, 100.00),
((SELECT id FROM shipping_providers WHERE code = 'USPS'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'USPS' AND ss.code = 'USPS_PRIORITY'), (SELECT id FROM shipping_zones WHERE code = 'US_DOMESTIC'), (SELECT id FROM shipping_types WHERE code = 'EXPRESS'), 0.000, 20.000, 0.000, 100000.000, 0, 5000, 9.99, 2.00, 0.0020, 0.000040, 0.0000, 10.00, 0.00, 50.00),
((SELECT id FROM shipping_providers WHERE code = 'USPS'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'USPS' AND ss.code = 'USPS_GROUND'), (SELECT id FROM shipping_zones WHERE code = 'US_DOMESTIC'), (SELECT id FROM shipping_types WHERE code = 'STANDARD'), 0.000, 30.000, 0.000, 200000.000, 0, 5000, 5.99, 1.00, 0.0010, 0.000015, 0.0000, 10.00, 0.00, 35.00),

-- Canada International Rates
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'FEDEX' AND ss.code = 'FEDEX_INTL'), (SELECT id FROM shipping_zones WHERE code = 'CANADA'), (SELECT id FROM shipping_types WHERE code = 'INTL_EXPRESS'), 0.000, 20.000, 0.000, 200000.000, 2000, 3000, 25.99, 4.00, 0.0080, 0.000120, 0.0075, 15.00, 15.00, 100.00),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'UPS' AND ss.code = 'UPS_WORLDWIDE'), (SELECT id FROM shipping_zones WHERE code = 'CANADA'), (SELECT id FROM shipping_types WHERE code = 'INTL_EXPRESS'), 0.000, 20.000, 0.000, 200000.000, 2000, 3000, 23.99, 3.75, 0.0075, 0.000115, 0.0075, 15.00, 15.00, 100.00),

-- Europe International Rates
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'FEDEX' AND ss.code = 'FEDEX_INTL'), (SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM shipping_types WHERE code = 'INTL_EXPRESS'), 0.000, 15.000, 0.000, 150000.000, 8000, 10000, 45.99, 8.00, 0.0120, 0.000200, 0.0100, 18.00, 25.00, 200.00),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'UPS' AND ss.code = 'UPS_WORLDWIDE'), (SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM shipping_types WHERE code = 'INTL_EXPRESS'), 0.000, 15.000, 0.000, 150000.000, 8000, 10000, 42.99, 7.50, 0.0115, 0.000190, 0.0100, 18.00, 25.00, 200.00),
((SELECT id FROM shipping_providers WHERE code = 'DHL'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'DHL' AND ss.code = 'DHL_EXPRESS'), (SELECT id FROM shipping_zones WHERE code = 'EUROPE'), (SELECT id FROM shipping_types WHERE code = 'INTL_EXPRESS'), 0.000, 20.000, 0.000, 200000.000, 8000, 10000, 39.99, 7.00, 0.0110, 0.000180, 0.0100, 18.00, 25.00, 150.00),

-- Asia Pacific International Rates
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'FEDEX' AND ss.code = 'FEDEX_INTL'), (SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM shipping_types WHERE code = 'INTL_EXPRESS'), 0.000, 15.000, 0.000, 150000.000, 10000, 15000, 55.99, 10.00, 0.0150, 0.000250, 0.0125, 20.00, 35.00, 250.00),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'UPS' AND ss.code = 'UPS_WORLDWIDE'), (SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM shipping_types WHERE code = 'INTL_EXPRESS'), 0.000, 15.000, 0.000, 150000.000, 10000, 15000, 52.99, 9.50, 0.0145, 0.000240, 0.0125, 20.00, 35.00, 250.00),
((SELECT id FROM shipping_providers WHERE code = 'DHL'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'DHL' AND ss.code = 'DHL_EXPRESS'), (SELECT id FROM shipping_zones WHERE code = 'ASIA_PACIFIC'), (SELECT id FROM shipping_types WHERE code = 'INTL_EXPRESS'), 0.000, 20.000, 0.000, 200000.000, 10000, 15000, 49.99, 9.00, 0.0140, 0.000230, 0.0125, 20.00, 35.00, 200.00),

-- Local Courier Rates
((SELECT id FROM shipping_providers WHERE code = 'LOCAL'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'LOCAL' AND ss.code = 'LOCAL_SAME_DAY'), (SELECT id FROM shipping_zones WHERE code = 'US_DOMESTIC'), (SELECT id FROM shipping_types WHERE code = 'SAME_DAY'), 0.000, 20.000, 0.000, 100000.000, 0, 100, 12.99, 2.00, 0.0500, 0.000050, 0.0000, 5.00, 0.00, 75.00),
((SELECT id FROM shipping_providers WHERE code = 'LOCAL'), (SELECT ss.id FROM shipping_services ss JOIN shipping_providers sp ON ss.provider_id = sp.id WHERE sp.code = 'LOCAL' AND ss.code = 'LOCAL_NEXT_DAY'), (SELECT id FROM shipping_zones WHERE code = 'US_DOMESTIC'), (SELECT id FROM shipping_types WHERE code = 'STANDARD'), 0.000, 25.000, 0.000, 150000.000, 0, 200, 6.99, 1.00, 0.0200, 0.000025, 0.0000, 5.00, 0.00, 50.00);

-- =====================================================
-- COMPLETION MESSAGE
-- =====================================================

SELECT 'Global Shipping System Update Complete!' as Status,
       'Tables Created: 13' as Tables_Created,
       'Countries Added: 50+' as Countries_Added,
       'States Added: 23' as States_Added,
       'Shipping Providers: 5' as Providers_Added,
       'Shipping Services: 16' as Services_Added,
       'Rate Rules: 20+' as Rate_Rules_Added,
       'Currency Rates: 43' as Exchange_Rates,
       'Package Types: 8' as Package_Types,
       'Insurance Options: 4' as Insurance_Options;

-- Show detailed table counts
SELECT
    'countries' as Table_Name, COUNT(*) as Records FROM countries
UNION ALL SELECT
    'states' as Table_Name, COUNT(*) as Records FROM states
UNION ALL SELECT
    'shipping_zones' as Table_Name, COUNT(*) as Records FROM shipping_zones
UNION ALL SELECT
    'zone_countries' as Table_Name, COUNT(*) as Records FROM zone_countries
UNION ALL SELECT
    'shipping_providers' as Table_Name, COUNT(*) as Records FROM shipping_providers
UNION ALL SELECT
    'shipping_services' as Table_Name, COUNT(*) as Records FROM shipping_services
UNION ALL SELECT
    'shipping_rate_rules' as Table_Name, COUNT(*) as Records FROM shipping_rate_rules
UNION ALL SELECT
    'currency_rates' as Table_Name, COUNT(*) as Records FROM currency_rates
UNION ALL SELECT
    'shipping_types' as Table_Name, COUNT(*) as Records FROM shipping_types
UNION ALL SELECT
    'package_types' as Table_Name, COUNT(*) as Records FROM package_types
UNION ALL SELECT
    'shipping_insurance' as Table_Name, COUNT(*) as Records FROM shipping_insurance;
