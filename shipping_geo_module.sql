-- VentDepot Geographical and Shipping Management Module
-- Add these tables to your existing database

USE finalJulio;

-- Countries Table
CREATE TABLE countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(3) UNIQUE NOT NULL, -- ISO 3166-1 alpha-3 code (USA, CAN, MEX)
    name VARCHAR(100) NOT NULL,
    currency_code VARCHAR(3) NOT NULL, -- USD, CAD, MXN
    currency_symbol VARCHAR(10) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0.00, -- Default tax rate percentage
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- States/Provinces Table
CREATE TABLE states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    country_id INT NOT NULL,
    code VARCHAR(10) NOT NULL, -- CA, NY, ON, BC
    name VARCHAR(100) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0.00, -- State/province specific tax
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (country_id) REFERENCES countries(id),
    UNIQUE KEY unique_state_code (country_id, code)
);

-- Cities Table
CREATE TABLE cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    state_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    postal_code_pattern VARCHAR(20), -- Regex pattern for postal codes
    timezone VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (state_id) REFERENCES states(id),
    INDEX idx_cities_state (state_id)
);

-- Shipping Zones Table
CREATE TABLE shipping_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Zone Countries Mapping
CREATE TABLE zone_countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    country_id INT NOT NULL,
    FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE,
    FOREIGN KEY (country_id) REFERENCES countries(id),
    UNIQUE KEY unique_zone_country (zone_id, country_id)
);

-- Shipping Providers Table
CREATE TABLE shipping_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL, -- FEDEX, UPS, USPS, DHL
    api_endpoint VARCHAR(255),
    api_key_encrypted VARCHAR(255),
    tracking_url_template VARCHAR(255), -- URL with {tracking_number} placeholder
    is_active BOOLEAN DEFAULT TRUE,
    supports_international BOOLEAN DEFAULT FALSE,
    max_weight_kg DECIMAL(8,2),
    max_dimensions_cm VARCHAR(50), -- "100x100x100" format
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Shipping Services Table (Express, Standard, Economy, etc.)
CREATE TABLE shipping_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL,
    description TEXT,
    estimated_days_min INT,
    estimated_days_max INT,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (provider_id) REFERENCES shipping_providers(id),
    UNIQUE KEY unique_provider_service (provider_id, code)
);

-- Product Dimensions Table (for shipping calculations)
CREATE TABLE product_dimensions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNIQUE NOT NULL,
    weight_kg DECIMAL(8,3) NOT NULL DEFAULT 0.000,
    length_cm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    width_cm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    height_cm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    fragile BOOLEAN DEFAULT FALSE,
    hazardous BOOLEAN DEFAULT FALSE,
    requires_signature BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Shipping Rate Rules Table
CREATE TABLE shipping_rate_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    service_id INT NOT NULL,
    zone_id INT NOT NULL,
    weight_min_kg DECIMAL(8,3) DEFAULT 0.000,
    weight_max_kg DECIMAL(8,3) DEFAULT 999.999,
    base_cost DECIMAL(10,2) NOT NULL,
    cost_per_kg DECIMAL(10,2) DEFAULT 0.00,
    cost_per_cubic_cm DECIMAL(10,6) DEFAULT 0.000000,
    free_shipping_threshold DECIMAL(10,2), -- Free shipping if order total exceeds this
    is_active BOOLEAN DEFAULT TRUE,
    effective_date DATE,
    expiry_date DATE,
    FOREIGN KEY (provider_id) REFERENCES shipping_providers(id),
    FOREIGN KEY (service_id) REFERENCES shipping_services(id),
    FOREIGN KEY (zone_id) REFERENCES shipping_zones(id)
);

-- Shipment Tracking Table
CREATE TABLE shipments (
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
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (provider_id) REFERENCES shipping_providers(id),
    FOREIGN KEY (service_id) REFERENCES shipping_services(id)
);

-- Tracking Events Table
CREATE TABLE tracking_events (
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
    FOREIGN KEY (country_id) REFERENCES countries(id),
    FOREIGN KEY (state_id) REFERENCES states(id)
);

-- Currency Exchange Rates Table
CREATE TABLE currency_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_currency VARCHAR(3) NOT NULL,
    to_currency VARCHAR(3) NOT NULL,
    rate DECIMAL(12,6) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_currency_pair (from_currency, to_currency)
);

-- Insert Sample Data

-- Countries
INSERT INTO countries (code, name, currency_code, currency_symbol, tax_rate) VALUES
('USA', 'United States', 'USD', '$', 0.00), -- Tax varies by state
('CAN', 'Canada', 'CAD', 'C$', 5.00), -- GST
('MEX', 'Mexico', 'MXN', '$', 16.00), -- IVA
('GBR', 'United Kingdom', 'GBP', '£', 20.00), -- VAT
('DEU', 'Germany', 'EUR', '€', 19.00), -- VAT
('FRA', 'France', 'EUR', '€', 20.00), -- VAT
('JPN', 'Japan', 'JPY', '¥', 10.00), -- Consumption tax
('AUS', 'Australia', 'AUD', 'A$', 10.00); -- GST

-- US States
INSERT INTO states (country_id, code, name, tax_rate) VALUES
(1, 'CA', 'California', 7.25),
(1, 'NY', 'New York', 4.00),
(1, 'TX', 'Texas', 6.25),
(1, 'FL', 'Florida', 6.00),
(1, 'IL', 'Illinois', 6.25),
(1, 'PA', 'Pennsylvania', 6.00),
(1, 'OH', 'Ohio', 5.75),
(1, 'GA', 'Georgia', 4.00),
(1, 'NC', 'North Carolina', 4.75),
(1, 'MI', 'Michigan', 6.00);

-- Canadian Provinces
INSERT INTO states (country_id, code, name, tax_rate) VALUES
(2, 'ON', 'Ontario', 13.00), -- HST
(2, 'QC', 'Quebec', 14.975), -- GST + QST
(2, 'BC', 'British Columbia', 12.00), -- GST + PST
(2, 'AB', 'Alberta', 5.00), -- GST only
(2, 'MB', 'Manitoba', 12.00), -- GST + PST
(2, 'SK', 'Saskatchewan', 11.00); -- GST + PST

-- Shipping Zones
INSERT INTO shipping_zones (name, description) VALUES
('Domestic US', 'United States domestic shipping'),
('Canada', 'Canada shipping zone'),
('Mexico', 'Mexico shipping zone'),
('Europe', 'European Union countries'),
('Asia Pacific', 'Asia Pacific region'),
('International', 'Rest of world');

-- Zone Countries Mapping
INSERT INTO zone_countries (zone_id, country_id) VALUES
(1, 1), -- US Domestic
(2, 2), -- Canada
(3, 3), -- Mexico
(4, 4), (4, 5), (4, 6), -- Europe
(5, 7), (5, 8), -- Asia Pacific
(6, 4), (6, 5), (6, 6), (6, 7), (6, 8); -- International (overlapping for demo)

-- Shipping Providers
INSERT INTO shipping_providers (name, code, tracking_url_template, is_active, supports_international, max_weight_kg, max_dimensions_cm) VALUES
('FedEx', 'FEDEX', 'https://www.fedex.com/fedextrack/?trknbr={tracking_number}', TRUE, TRUE, 68.0, '274x274x274'),
('UPS', 'UPS', 'https://www.ups.com/track?tracknum={tracking_number}', TRUE, TRUE, 70.0, '270x270x270'),
('USPS', 'USPS', 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking_number}', TRUE, TRUE, 32.0, '108x108x108'),
('DHL', 'DHL', 'https://www.dhl.com/en/express/tracking.html?AWB={tracking_number}', TRUE, TRUE, 70.0, '300x300x300'),
('Local Courier', 'LOCAL', 'https://ventdepot.com/track/{tracking_number}', TRUE, FALSE, 25.0, '100x100x100');

-- Shipping Services
INSERT INTO shipping_services (provider_id, name, code, description, estimated_days_min, estimated_days_max) VALUES
-- FedEx Services
(1, 'FedEx Overnight', 'FEDEX_OVERNIGHT', 'Next business day delivery', 1, 1),
(1, 'FedEx 2Day', 'FEDEX_2DAY', 'Delivery in 2 business days', 2, 2),
(1, 'FedEx Ground', 'FEDEX_GROUND', 'Ground delivery service', 1, 5),
(1, 'FedEx International', 'FEDEX_INTL', 'International express delivery', 2, 7),

-- UPS Services
(2, 'UPS Next Day Air', 'UPS_NEXT_DAY', 'Next business day delivery', 1, 1),
(2, 'UPS 2nd Day Air', 'UPS_2DAY', 'Second business day delivery', 2, 2),
(2, 'UPS Ground', 'UPS_GROUND', 'Ground delivery service', 1, 5),
(2, 'UPS Worldwide Express', 'UPS_WORLDWIDE', 'International express delivery', 1, 5),

-- USPS Services
(3, 'USPS Priority Express', 'USPS_EXPRESS', 'Overnight delivery', 1, 2),
(3, 'USPS Priority Mail', 'USPS_PRIORITY', 'Priority mail service', 1, 3),
(3, 'USPS Ground Advantage', 'USPS_GROUND', 'Ground delivery service', 2, 5),
(3, 'USPS International', 'USPS_INTL', 'International mail service', 6, 21),

-- DHL Services
(4, 'DHL Express Worldwide', 'DHL_EXPRESS', 'Express worldwide delivery', 1, 4),
(4, 'DHL Economy Select', 'DHL_ECONOMY', 'Economy international delivery', 4, 8),

-- Local Courier
(5, 'Same Day Delivery', 'LOCAL_SAME_DAY', 'Same day local delivery', 0, 0),
(5, 'Next Day Local', 'LOCAL_NEXT_DAY', 'Next day local delivery', 1, 1);

-- Sample Shipping Rate Rules
INSERT INTO shipping_rate_rules (provider_id, service_id, zone_id, weight_min_kg, weight_max_kg, base_cost, cost_per_kg, free_shipping_threshold) VALUES
-- FedEx US Domestic
(1, 1, 1, 0.000, 2.000, 25.99, 5.00, 100.00), -- Overnight
(1, 2, 1, 0.000, 2.000, 15.99, 3.00, 75.00),  -- 2Day
(1, 3, 1, 0.000, 10.000, 8.99, 1.50, 50.00),  -- Ground

-- UPS US Domestic
(2, 5, 1, 0.000, 2.000, 24.99, 4.50, 100.00), -- Next Day
(2, 6, 1, 0.000, 2.000, 14.99, 2.75, 75.00),  -- 2Day
(2, 7, 1, 0.000, 10.000, 7.99, 1.25, 50.00),  -- Ground

-- USPS US Domestic
(3, 9, 1, 0.000, 2.000, 22.99, 4.00, 100.00),  -- Express
(3, 10, 1, 0.000, 5.000, 9.99, 2.00, 50.00),   -- Priority
(3, 11, 1, 0.000, 10.000, 5.99, 1.00, 35.00),  -- Ground

-- International rates (higher costs)
(1, 4, 4, 0.000, 5.000, 45.99, 8.00, 200.00),  -- FedEx to Europe
(2, 8, 4, 0.000, 5.000, 42.99, 7.50, 200.00),  -- UPS to Europe
(4, 13, 4, 0.000, 5.000, 39.99, 7.00, 150.00), -- DHL to Europe

-- Canada rates
(1, 4, 2, 0.000, 5.000, 25.99, 4.00, 100.00),  -- FedEx to Canada
(2, 8, 2, 0.000, 5.000, 23.99, 3.75, 100.00),  -- UPS to Canada

-- Local courier rates
(5, 15, 1, 0.000, 5.000, 12.99, 2.00, 75.00),  -- Same day
(5, 16, 1, 0.000, 10.000, 6.99, 1.00, 50.00);  -- Next day local

-- Currency Exchange Rates (sample rates)
INSERT INTO currency_rates (from_currency, to_currency, rate) VALUES
('USD', 'CAD', 1.35),
('USD', 'MXN', 17.50),
('USD', 'EUR', 0.85),
('USD', 'GBP', 0.73),
('USD', 'JPY', 110.00),
('USD', 'AUD', 1.45),
('CAD', 'USD', 0.74),
('EUR', 'USD', 1.18),
('GBP', 'USD', 1.37);

-- Create indexes for better performance
CREATE INDEX idx_shipping_rates_zone_weight ON shipping_rate_rules(zone_id, weight_min_kg, weight_max_kg);
CREATE INDEX idx_shipments_tracking ON shipments(tracking_number);
CREATE INDEX idx_shipments_order ON shipments(order_id);
CREATE INDEX idx_tracking_events_shipment ON tracking_events(shipment_id);
CREATE INDEX idx_product_dimensions_product ON product_dimensions(product_id);
CREATE INDEX idx_tax_rules_location ON tax_rules(country_id, state_id);

-- Show summary
SELECT 'Geographical and Shipping Module Setup Complete!' as Status;
SELECT 
    'Countries' as Table_Name, COUNT(*) as Records FROM countries
UNION ALL SELECT 
    'States' as Table_Name, COUNT(*) as Records FROM states
UNION ALL SELECT 
    'Shipping Providers' as Table_Name, COUNT(*) as Records FROM shipping_providers
UNION ALL SELECT 
    'Shipping Services' as Table_Name, COUNT(*) as Records FROM shipping_services
UNION ALL SELECT 
    'Rate Rules' as Table_Name, COUNT(*) as Records FROM shipping_rate_rules;
