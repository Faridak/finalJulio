<?php
require_once 'config/database.php';

// Insert Countries
$countriesSql = "
INSERT IGNORE INTO countries (code, name, currency_code, currency_symbol, tax_rate) VALUES
('USA', 'United States', 'USD', '$', 0.00),
('CAN', 'Canada', 'CAD', 'C$', 5.00),
('MEX', 'Mexico', 'MXN', '$', 16.00),
('GBR', 'United Kingdom', 'GBP', '£', 20.00),
('DEU', 'Germany', 'EUR', '€', 19.00),
('FRA', 'France', 'EUR', '€', 20.00),
('JPN', 'Japan', 'JPY', '¥', 10.00),
('AUS', 'Australia', 'AUD', 'A$', 10.00)
";

try {
    $pdo->exec($countriesSql);
    echo "Sample countries inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting countries: " . $e->getMessage() . "\n";
}

// Insert US States
$statesSql = "
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
((SELECT id FROM countries WHERE code = 'USA'), 'MI', 'Michigan', 6.00)
";

try {
    $pdo->exec($statesSql);
    echo "Sample US states inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting US states: " . $e->getMessage() . "\n";
}

// Insert Canadian Provinces
$canadaStatesSql = "
INSERT IGNORE INTO states (country_id, code, name, tax_rate) VALUES
((SELECT id FROM countries WHERE code = 'CAN'), 'ON', 'Ontario', 13.00),
((SELECT id FROM countries WHERE code = 'CAN'), 'QC', 'Quebec', 14.975),
((SELECT id FROM countries WHERE code = 'CAN'), 'BC', 'British Columbia', 12.00),
((SELECT id FROM countries WHERE code = 'CAN'), 'AB', 'Alberta', 5.00),
((SELECT id FROM countries WHERE code = 'CAN'), 'MB', 'Manitoba', 12.00),
((SELECT id FROM countries WHERE code = 'CAN'), 'SK', 'Saskatchewan', 11.00)
";

try {
    $pdo->exec($canadaStatesSql);
    echo "Sample Canadian provinces inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting Canadian provinces: " . $e->getMessage() . "\n";
}

// Insert Shipping Zones
$zonesSql = "
INSERT IGNORE INTO shipping_zones (name, description) VALUES
('Domestic US', 'United States domestic shipping'),
('Canada', 'Canada shipping zone'),
('Mexico', 'Mexico shipping zone'),
('Europe', 'European Union countries'),
('Asia Pacific', 'Asia Pacific region'),
('International', 'Rest of world')
";

try {
    $pdo->exec($zonesSql);
    echo "Sample shipping zones inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting shipping zones: " . $e->getMessage() . "\n";
}

// Map Countries to Zones
$zoneCountriesSql = "
INSERT IGNORE INTO zone_countries (zone_id, country_id) VALUES
((SELECT id FROM shipping_zones WHERE name = 'Domestic US'), (SELECT id FROM countries WHERE code = 'USA')),
((SELECT id FROM shipping_zones WHERE name = 'Canada'), (SELECT id FROM countries WHERE code = 'CAN')),
((SELECT id FROM shipping_zones WHERE name = 'Mexico'), (SELECT id FROM countries WHERE code = 'MEX')),
((SELECT id FROM shipping_zones WHERE name = 'Europe'), (SELECT id FROM countries WHERE code = 'GBR')),
((SELECT id FROM shipping_zones WHERE name = 'Europe'), (SELECT id FROM countries WHERE code = 'DEU')),
((SELECT id FROM shipping_zones WHERE name = 'Europe'), (SELECT id FROM countries WHERE code = 'FRA')),
((SELECT id FROM shipping_zones WHERE name = 'Asia Pacific'), (SELECT id FROM countries WHERE code = 'JPN')),
((SELECT id FROM shipping_zones WHERE name = 'Asia Pacific'), (SELECT id FROM countries WHERE code = 'AUS'))
";

try {
    $pdo->exec($zoneCountriesSql);
    echo "Sample zone-country mappings inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting zone-country mappings: " . $e->getMessage() . "\n";
}

// Insert Shipping Providers
$providersSql = "
INSERT IGNORE INTO shipping_providers (name, code, tracking_url_template, is_active, supports_international, max_weight_kg, max_dimensions_cm) VALUES
('FedEx', 'FEDEX', 'https://www.fedex.com/fedextrack/?trknbr={tracking_number}', TRUE, TRUE, 68.0, '274x274x274'),
('UPS', 'UPS', 'https://www.ups.com/track?tracknum={tracking_number}', TRUE, TRUE, 70.0, '270x270x270'),
('USPS', 'USPS', 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking_number}', TRUE, TRUE, 32.0, '108x108x108'),
('DHL', 'DHL', 'https://www.dhl.com/en/express/tracking.html?AWB={tracking_number}', TRUE, TRUE, 70.0, '300x300x300'),
('Local Courier', 'LOCAL', 'https://ventdepot.com/track/{tracking_number}', TRUE, FALSE, 25.0, '100x100x100')
";

try {
    $pdo->exec($providersSql);
    echo "Sample shipping providers inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting shipping providers: " . $e->getMessage() . "\n";
}

// Insert Shipping Services
$servicesSql = "
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
((SELECT id FROM shipping_providers WHERE code = 'LOCAL'), 'Next Day Local', 'LOCAL_NEXT_DAY', 'Next day local delivery', 1, 1)
";

try {
    $pdo->exec($servicesSql);
    echo "Sample shipping services inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting shipping services: " . $e->getMessage() . "\n";
}

// Insert Sample Shipping Rate Rules
$rateRulesSql = "
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
((SELECT id FROM shipping_providers WHERE code = 'LOCAL'), (SELECT id FROM shipping_services WHERE code = 'LOCAL_NEXT_DAY'), (SELECT id FROM shipping_zones WHERE name = 'Domestic US'), 0.000, 10.000, 6.99, 1.00, 50.00)
";

try {
    $pdo->exec($rateRulesSql);
    echo "Sample shipping rate rules inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting shipping rate rules: " . $e->getMessage() . "\n";
}

// Insert Currency Exchange Rates
$currencyRatesSql = "
INSERT IGNORE INTO currency_rates (from_currency, to_currency, rate) VALUES
('USD', 'CAD', 1.35),
('USD', 'MXN', 17.50),
('USD', 'EUR', 0.85),
('USD', 'GBP', 0.73),
('USD', 'JPY', 110.00),
('USD', 'AUD', 1.45),
('CAD', 'USD', 0.74),
('EUR', 'USD', 1.18),
('GBP', 'USD', 1.37)
";

try {
    $pdo->exec($currencyRatesSql);
    echo "Sample currency rates inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting currency rates: " . $e->getMessage() . "\n";
}

// Insert sample shipments
$shipmentsSql = "
INSERT IGNORE INTO shipments (order_id, provider_id, service_id, tracking_number, status, estimated_delivery, shipping_cost, weight_kg) VALUES
(1, (SELECT id FROM shipping_providers WHERE code = 'FEDEX'), (SELECT id FROM shipping_services WHERE code = 'FEDEX_GROUND'), '123456789012', 'delivered', '2024-01-20', 8.99, 1.250),
(2, (SELECT id FROM shipping_providers WHERE code = 'UPS'), (SELECT id FROM shipping_services WHERE code = 'UPS_GROUND'), '987654321098', 'in_transit', '2024-01-22', 7.99, 0.850),
(3, (SELECT id FROM shipping_providers WHERE code = 'USPS'), (SELECT id FROM shipping_services WHERE code = 'USPS_PRIORITY'), '456789123456', 'out_for_delivery', '2024-01-19', 9.99, 0.500)
";

try {
    $pdo->exec($shipmentsSql);
    echo "Sample shipments inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting shipments: " . $e->getMessage() . "\n";
}

// Insert sample tracking events
$trackingEventsSql = "
INSERT IGNORE INTO tracking_events (shipment_id, status, description, location, event_time) VALUES
(1, 'picked_up', 'Package picked up by FedEx', 'New York, NY', '2024-01-18 09:30:00'),
(1, 'in_transit', 'Package in transit to destination', 'Chicago, IL', '2024-01-19 14:45:00'),
(1, 'out_for_delivery', 'Package out for delivery', 'New York, NY', '2024-01-20 08:15:00'),
(1, 'delivered', 'Package delivered to recipient', 'New York, NY', '2024-01-20 16:30:00'),
(2, 'picked_up', 'Package picked up by UPS', 'Los Angeles, CA', '2024-01-19 10:20:00'),
(2, 'in_transit', 'Package in transit to destination', 'Phoenix, AZ', '2024-01-20 12:00:00'),
(2, 'in_transit', 'Package arriving at destination facility', 'Chicago, IL', '2024-01-21 15:30:00'),
(3, 'picked_up', 'Package picked up by USPS', 'Austin, TX', '2024-01-18 11:45:00'),
(3, 'in_transit', 'Package in transit to destination', 'Dallas, TX', '2024-01-18 18:20:00'),
(3, 'out_for_delivery', 'Package out for delivery', 'Austin, TX', '2024-01-19 09:15:00')
";

try {
    $pdo->exec($trackingEventsSql);
    echo "Sample tracking events inserted successfully\n";
} catch(PDOException $e) {
    echo "Error inserting tracking events: " . $e->getMessage() . "\n";
}

echo "\nAll sample shipping data inserted successfully!\n";
?>