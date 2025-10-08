-- =====================================================
-- VentDepot Shipping & Geographical Module
-- Add these tables to your existing finalJulio database
-- =====================================================

USE finalJulio;

-- =====================================================
-- GEOGRAPHICAL TABLES
-- =====================================================

-- Countries Table
CREATE TABLE IF NOT EXISTS countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(3) UNIQUE NOT NULL COMMENT 'ISO 3166-1 alpha-3 code (USA, CAN, MEX)',
    name VARCHAR(100) NOT NULL,
    currency_code VARCHAR(3) NOT NULL COMMENT 'USD, CAD, MXN',
    currency_symbol VARCHAR(10) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Default tax rate percentage',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- States/Provinces Table
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
);

-- Cities Table
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
);

-- Shipping Zones Table
CREATE TABLE IF NOT EXISTS shipping_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Zone Countries Mapping
CREATE TABLE IF NOT EXISTS zone_countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    country_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE,
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
    UNIQUE KEY unique_zone_country (zone_id, country_id)
);

-- =====================================================
-- SHIPPING TABLES
-- =====================================================

-- Shipping Providers Table
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
    max_dimensions_cm VARCHAR(50) COMMENT '"100x100x100" format',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Shipping Services Table (Express, Standard, Economy, etc.)
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
);

-- Product Dimensions Table (for shipping calculations)
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
);

-- Shipping Rate Rules Table
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
);

-- Shipment Tracking Table
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
);

-- Tracking Events Table
CREATE TABLE IF NOT EXISTS tracking_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    description TEXT,
    location VARCHAR(255),
    event_time DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
);

-- Tax Rules Table
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
);

-- Currency Exchange Rates Table
CREATE TABLE IF NOT EXISTS currency_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_currency VARCHAR(3) NOT NULL,
    to_currency VARCHAR(3) NOT NULL,
    rate DECIMAL(12,6) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_currency_pair (from_currency, to_currency)
);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

CREATE INDEX idx_shipping_rates_zone_weight ON shipping_rate_rules(zone_id, weight_min_kg, weight_max_kg);
CREATE INDEX idx_shipments_tracking ON shipments(tracking_number);
CREATE INDEX idx_shipments_order ON shipments(order_id);
CREATE INDEX idx_tracking_events_shipment ON tracking_events(shipment_id);
CREATE INDEX idx_product_dimensions_product ON product_dimensions(product_id);
CREATE INDEX idx_tax_rules_location ON tax_rules(country_id, state_id);
CREATE INDEX idx_countries_code ON countries(code);
CREATE INDEX idx_states_code ON states(country_id, code);
CREATE INDEX idx_currency_rates_pair ON currency_rates(from_currency, to_currency);

-- =====================================================
-- SAMPLE DATA INSERTION
-- =====================================================

-- Insert Countries
INSERT IGNORE INTO countries (code, name, currency_code, currency_symbol, tax_rate) VALUES
('USA', 'United States', 'USD', '$', 0.00),
('CAN', 'Canada', 'CAD', 'C$', 5.00),
('MEX', 'Mexico', 'MXN', '$', 16.00),
('GBR', 'United Kingdom', 'GBP', '£', 20.00),
('DEU', 'Germany', 'EUR', '€', 19.00),
('FRA', 'France', 'EUR', '€', 20.00),
('JPN', 'Japan', 'JPY', '¥', 10.00),
('AUS', 'Australia', 'AUD', 'A$', 10.00);

-- Insert US States
INSERT IGNORE INTO states (country_id, code, name, tax_rate) VALUES
((SELECT id FROM countries WHERE code = 'USA'), 'CA', 'California', 7.25),
((SELECT id FROM countries WHERE code = 'USA'), 'NY', 'New York', 4.00),
((SELECT id FROM countries WHERE code = 'USA'), 'TX', 'Texas', 6.25),
((SELECT id FROM countries WHERE code = 'USA'), 'FL', 'Florida', 6.00),
((SELECT id FROM countries WHERE code = 'USA'), 'IL', 'Illinois', 6.25),
((SELECT id FROM countries WHERE code = 'USA'), 'PA', 'Pennsylvania', 6.00),
((SELECT id FROM countries WHERE code = 'USA'), 'OH', 'Ohio', 5.75),
((SELECT id FROM countries WHERE code = 'USA'), 'GA', 'Georgia', 4.00),
((SELECT id FROM countries WHERE code = 'USA'), 'NC', 'North Carolina', 4.75),
((SELECT id FROM countries WHERE code = 'USA'), 'MI', 'Michigan', 6.00);

-- Insert Canadian Provinces
INSERT IGNORE INTO states (country_id, code, name, tax_rate) VALUES
((SELECT id FROM countries WHERE code = 'CAN'), 'ON', 'Ontario', 13.00),
((SELECT id FROM countries WHERE code = 'CAN'), 'QC', 'Quebec', 14.975),
((SELECT id FROM countries WHERE code = 'CAN'), 'BC', 'British Columbia', 12.00),
((SELECT id FROM countries WHERE code = 'CAN'), 'AB', 'Alberta', 5.00),
((SELECT id FROM countries WHERE code = 'CAN'), 'MB', 'Manitoba', 12.00),
((SELECT id FROM countries WHERE code = 'CAN'), 'SK', 'Saskatchewan', 11.00);

-- Insert Shipping Zones
INSERT IGNORE INTO shipping_zones (name, description) VALUES
('Domestic US', 'United States domestic shipping'),
('Canada', 'Canada shipping zone'),
('Mexico', 'Mexico shipping zone'),
('Europe', 'European Union countries'),
('Asia Pacific', 'Asia Pacific region'),
('International', 'Rest of world');

-- Map Countries to Zones
INSERT IGNORE INTO zone_countries (zone_id, country_id) VALUES
((SELECT id FROM shipping_zones WHERE name = 'Domestic US'), (SELECT id FROM countries WHERE code = 'USA')),
((SELECT id FROM shipping_zones WHERE name = 'Canada'), (SELECT id FROM countries WHERE code = 'CAN')),
((SELECT id FROM shipping_zones WHERE name = 'Mexico'), (SELECT id FROM countries WHERE code = 'MEX')),
((SELECT id FROM shipping_zones WHERE name = 'Europe'), (SELECT id FROM countries WHERE code = 'GBR')),
((SELECT id FROM shipping_zones WHERE name = 'Europe'), (SELECT id FROM countries WHERE code = 'DEU')),
((SELECT id FROM shipping_zones WHERE name = 'Europe'), (SELECT id FROM countries WHERE code = 'FRA')),
((SELECT id FROM shipping_zones WHERE name = 'Asia Pacific'), (SELECT id FROM countries WHERE code = 'JPN')),
((SELECT id FROM shipping_zones WHERE name = 'Asia Pacific'), (SELECT id FROM countries WHERE code = 'AUS'));

-- Insert Shipping Providers
INSERT IGNORE INTO shipping_providers (name, code, tracking_url_template, is_active, supports_international, max_weight_kg, max_dimensions_cm) VALUES
('FedEx', 'FEDEX', 'https://www.fedex.com/fedextrack/?trknbr={tracking_number}', TRUE, TRUE, 68.0, '274x274x274'),
('UPS', 'UPS', 'https://www.ups.com/track?tracknum={tracking_number}', TRUE, TRUE, 70.0, '270x270x270'),
('USPS', 'USPS', 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking_number}', TRUE, TRUE, 32.0, '108x108x108'),
('DHL', 'DHL', 'https://www.dhl.com/en/express/tracking.html?AWB={tracking_number}', TRUE, TRUE, 70.0, '300x300x300'),
('Local Courier', 'LOCAL', 'https://ventdepot.com/track/{tracking_number}', TRUE, FALSE, 25.0, '100x100x100');

-- Insert Shipping Services
INSERT IGNORE INTO shipping_services (provider_id, name, code, description, estimated_days_min, estimated_days_max) VALUES
-- FedEx Services
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), 'FedEx Overnight', 'FEDEX_OVERNIGHT', 'Next business day delivery', 1, 1),
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), 'FedEx 2Day', 'FEDEX_2DAY', 'Delivery in 2 business days', 2, 2),
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), 'FedEx Ground', 'FEDEX_GROUND', 'Ground delivery service', 1, 5),
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), 'FedEx International', 'FEDEX_INTL', 'International express delivery', 2, 7),

-- UPS Services
((SELECT id FROM shipping_providers WHERE code = 'UPS'), 'UPS Next Day Air', 'UPS_NEXT_DAY', 'Next business day delivery', 1, 1),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), 'UPS 2nd Day Air', 'UPS_2DAY', 'Second business day delivery', 2, 2),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), 'UPS Ground', 'UPS_GROUND', 'Ground delivery service', 1, 5),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), 'UPS Worldwide Express', 'UPS_WORLDWIDE', 'International express delivery', 1, 5),

-- USPS Services
((SELECT id FROM shipping_providers WHERE code = 'USPS'), 'USPS Priority Express', 'USPS_EXPRESS', 'Overnight delivery', 1, 2),
((SELECT id FROM shipping_providers WHERE code = 'USPS'), 'USPS Priority Mail', 'USPS_PRIORITY', 'Priority mail service', 1, 3),
((SELECT id FROM shipping_providers WHERE code = 'USPS'), 'USPS Ground Advantage', 'USPS_GROUND', 'Ground delivery service', 2, 5),
((SELECT id FROM shipping_providers WHERE code = 'USPS'), 'USPS International', 'USPS_INTL', 'International mail service', 6, 21),

-- DHL Services
((SELECT id FROM shipping_providers WHERE code = 'DHL'), 'DHL Express Worldwide', 'DHL_EXPRESS', 'Express worldwide delivery', 1, 4),
((SELECT id FROM shipping_providers WHERE code = 'DHL'), 'DHL Economy Select', 'DHL_ECONOMY', 'Economy international delivery', 4, 8),

-- Local Courier
((SELECT id FROM shipping_providers WHERE code = 'LOCAL'), 'Same Day Delivery', 'LOCAL_SAME_DAY', 'Same day local delivery', 0, 0),
((SELECT id FROM shipping_providers WHERE code = 'LOCAL'), 'Next Day Local', 'LOCAL_NEXT_DAY', 'Next day local delivery', 1, 1);

-- Insert Sample Shipping Rate Rules
INSERT IGNORE INTO shipping_rate_rules (provider_id, service_id, zone_id, weight_min_kg, weight_max_kg, base_cost, cost_per_kg, free_shipping_threshold) VALUES
-- FedEx US Domestic
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT id FROM shipping_services WHERE code = 'FEDEX_OVERNIGHT'), (SELECT id FROM shipping_zones WHERE name = 'Domestic US'), 0.000, 2.000, 25.99, 5.00, 100.00),
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT id FROM shipping_services WHERE code = 'FEDEX_2DAY'), (SELECT id FROM shipping_zones WHERE name = 'Domestic US'), 0.000, 2.000, 15.99, 3.00, 75.00),
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT id FROM shipping_services WHERE code = 'FEDEX_GROUND'), (SELECT id FROM shipping_zones WHERE name = 'Domestic US'), 0.000, 10.000, 8.99, 1.50, 50.00),

-- UPS US Domestic
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT id FROM shipping_services WHERE code = 'UPS_NEXT_DAY'), (SELECT id FROM shipping_zones WHERE name = 'Domestic US'), 0.000, 2.000, 24.99, 4.50, 100.00),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT id FROM shipping_services WHERE code = 'UPS_2DAY'), (SELECT id FROM shipping_zones WHERE name = 'Domestic US'), 0.000, 2.000, 14.99, 2.75, 75.00),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT id FROM shipping_services WHERE code = 'UPS_GROUND'), (SELECT id FROM shipping_zones WHERE name = 'Domestic US'), 0.000, 10.000, 7.99, 1.25, 50.00),

-- USPS US Domestic
((SELECT id FROM shipping_providers WHERE code = 'USPS'), (SELECT id FROM shipping_services WHERE code = 'USPS_EXPRESS'), (SELECT id FROM shipping_zones WHERE name = 'Domestic US'), 0.000, 2.000, 22.99, 4.00, 100.00),
((SELECT id FROM shipping_providers WHERE code = 'USPS'), (SELECT id FROM shipping_services WHERE code = 'USPS_PRIORITY'), (SELECT id FROM shipping_zones WHERE name = 'Domestic US'), 0.000, 5.000, 9.99, 2.00, 50.00),
((SELECT id FROM shipping_providers WHERE code = 'USPS'), (SELECT id FROM shipping_services WHERE code = 'USPS_GROUND'), (SELECT id FROM shipping_zones WHERE name = 'Domestic US'), 0.000, 10.000, 5.99, 1.00, 35.00),

-- International rates
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT id FROM shipping_services WHERE code = 'FEDEX_INTL'), (SELECT id FROM shipping_zones WHERE name = 'Europe'), 0.000, 5.000, 45.99, 8.00, 200.00),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT id FROM shipping_services WHERE code = 'UPS_WORLDWIDE'), (SELECT id FROM shipping_zones WHERE name = 'Europe'), 0.000, 5.000, 42.99, 7.50, 200.00),
((SELECT id FROM shipping_providers WHERE code = 'DHL'), (SELECT id FROM shipping_services WHERE code = 'DHL_EXPRESS'), (SELECT id FROM shipping_zones WHERE name = 'Europe'), 0.000, 5.000, 39.99, 7.00, 150.00),

-- Canada rates
((SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT id FROM shipping_services WHERE code = 'FEDEX_INTL'), (SELECT id FROM shipping_zones WHERE name = 'Canada'), 0.000, 5.000, 25.99, 4.00, 100.00),
((SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT id FROM shipping_services WHERE code = 'UPS_WORLDWIDE'), (SELECT id FROM shipping_zones WHERE name = 'Canada'), 0.000, 5.000, 23.99, 3.75, 100.00),

-- Local courier rates
((SELECT id FROM shipping_providers WHERE code = 'LOCAL'), (SELECT id FROM shipping_services WHERE code = 'LOCAL_SAME_DAY'), (SELECT id FROM shipping_zones WHERE name = 'Domestic US'), 0.000, 5.000, 12.99, 2.00, 75.00),
((SELECT id FROM shipping_providers WHERE code = 'LOCAL'), (SELECT id FROM shipping_services WHERE code = 'LOCAL_NEXT_DAY'), (SELECT id FROM shipping_zones WHERE name = 'Domestic US'), 0.000, 10.000, 6.99, 1.00, 50.00);

-- Insert Currency Exchange Rates
INSERT IGNORE INTO currency_rates (from_currency, to_currency, rate) VALUES
('USD', 'CAD', 1.35),
('USD', 'MXN', 17.50),
('USD', 'EUR', 0.85),
('USD', 'GBP', 0.73),
('USD', 'JPY', 110.00),
('USD', 'AUD', 1.45),
('CAD', 'USD', 0.74),
('EUR', 'USD', 1.18),
('GBP', 'USD', 1.37);

-- =====================================================
-- COMPLETION MESSAGE
-- =====================================================

SELECT 'Shipping & Geographical Module Added Successfully!' as Status,
       'Tables Created: 13' as Tables_Created,
       'Sample Countries: 8' as Countries_Added,
       'Sample States: 16' as States_Added,
       'Shipping Providers: 5' as Providers_Added,
       'Shipping Services: 16' as Services_Added,
       'Rate Rules: 15' as Rate_Rules_Added,
       'Currency Rates: 9' as Exchange_Rates;

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
    'currency_rates' as Table_Name, COUNT(*) as Records FROM currency_rates;
