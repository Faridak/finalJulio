<?php
/**
 * Database recovery: rebuilds base data after the schema reset, then the ETL
 * and seed scripts restore the catalog and operational structure.
 *
 *   php scripts/recover-database.php
 *
 * Idempotent — safe to re-run.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../config/database.php';

echo "== 1. Core users ==\n";
$users = [
    // email, role, scope, nickname
    ['admin@ventdepot.com',   'admin',    'all',  'Farida'],
    ['sales1@ventdepot.com',  'admin',    'all',  'Carlos (Ventas)'],
    ['engineer1@ventdepot.com','admin',   'all',  'Lupita (Ingeniería)'],
    ['engineer2@ventdepot.com','admin',   'all',  'Miguel (Ingeniería)'],
    ['catalog@ventdepot.com', 'merchant', 'all',  'Catálogo VentDepot'],
    ['alaa@123.com',          'merchant', 'all',  'Alaa Tools'],
    ['farida@123.com',        'customer', 'all',  null],
];
$hash = password_hash('Ventdepot#2026', PASSWORD_DEFAULT);
$ins = $pdo->prepare("INSERT INTO users (email, password, role, staff_scope, chat_nickname, created_at)
                      SELECT ?, ?, ?, ?, ?, NOW() WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = ?)");
foreach ($users as [$e, $r, $s, $n]) { $ins->execute([$e, $hash, $r, $s, $n, $e]); }
echo "  users: " . $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() . " (temp password Ventdepot#2026 — CHANGE IT)\n";

echo "== 2. Chart of accounts ==\n";
$accounts = [
    ['1000','Cash','asset'], ['1100','Accounts Receivable','asset'], ['1200','Inventory','asset'],
    ['1250','Inventory In-Transit','asset'], ['2000','Accounts Payable','liability'],
    ['2100','Sales Tax Payable','liability'], ['2200','Merchant Payable','liability'],
    ['2300','Payroll Taxes Payable','liability'], ['3000','Owner Equity','equity'],
    ['4000','Sales Revenue','revenue'], ['4100','Commission Revenue','revenue'],
    ['4200','Shipping Revenue','revenue'], ['4300','Inventory Adjustment Gain','revenue'],
    ['4400','Engineering Revenue','revenue'], ['5000','Cost of Goods Sold','expense'],
    ['5100','Shipping Expense','expense'], ['5200','Inventory Shrinkage & Loss','expense'],
    ['6000','Payroll Expense','expense'],
];
$ai = $pdo->prepare("INSERT INTO chart_of_accounts (account_code, account_name, account_type, balance)
                     SELECT ?, ?, ?, 0 WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code = ?)");
foreach ($accounts as [$c, $n, $t]) $ai->execute([$c, $n, $t, $c]);
echo "  accounts: " . $pdo->query("SELECT COUNT(*) FROM chart_of_accounts")->fetchColumn() . "\n";

echo "== 3. Warehouse location ==\n";
$pdo->exec("INSERT INTO inventory_locations (location_code, location_name, status, created_at)
            SELECT 'WH001', 'Main Warehouse', 'active', NOW()
            WHERE NOT EXISTS (SELECT 1 FROM inventory_locations WHERE location_code='WH001')");
echo "  locations: " . $pdo->query("SELECT COUNT(*) FROM inventory_locations")->fetchColumn() . "\n";

echo "== 4. Shipping base (provider/service/type/zones/tariff) ==\n";
$pdo->exec("INSERT INTO shipping_providers (name, code, is_active, base_country_code, created_at) SELECT 'VentDepot Logistics','VDL',1,'MX',NOW()
            WHERE NOT EXISTS (SELECT 1 FROM shipping_providers LIMIT 1)");
$prov = $pdo->query("SELECT id FROM shipping_providers LIMIT 1")->fetchColumn();
$pdo->exec("INSERT INTO shipping_types (name, code, is_active, created_at) SELECT 'Ground','GND',1,NOW()
            WHERE NOT EXISTS (SELECT 1 FROM shipping_types LIMIT 1)");
$typ0 = $pdo->query("SELECT id FROM shipping_types LIMIT 1")->fetchColumn();
$pdo->exec("INSERT INTO shipping_services (provider_id, shipping_type_id, name, code, is_active, created_at) SELECT $prov,$typ0,'Standard','STD',1,NOW()
            WHERE NOT EXISTS (SELECT 1 FROM shipping_services LIMIT 1)");
$zones = [1 => 'Domestic US', 2 => 'Canada', 3 => 'Mexico', 4 => 'Europe', 5 => 'Asia Pacific', 6 => 'International'];
$zi = $pdo->prepare("INSERT INTO shipping_zones (id, name, is_active) SELECT ?, ?, 1 WHERE NOT EXISTS (SELECT 1 FROM shipping_zones WHERE id = ?)");
foreach ($zones as $id => $name) $zi->execute([$id, $name, $id]);
$svc = $pdo->query("SELECT id FROM shipping_services LIMIT 1")->fetchColumn();
$typ = $pdo->query("SELECT id FROM shipping_types LIMIT 1")->fetchColumn();
if (!(int) $pdo->query("SELECT COUNT(*) FROM shipping_rate_rules WHERE is_active=1")->fetchColumn()) {
    $ri = $pdo->prepare("INSERT INTO shipping_rate_rules (provider_id,service_id,shipping_type_id,zone_id,weight_min_kg,weight_max_kg,base_cost,cost_per_kg,cost_per_km,fuel_surcharge_rate,insurance_rate,free_shipping_threshold,is_active,effective_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,CURDATE())");
    $rules = [
        [3,0,30,5.99,0.90,0.010,10,1.0,150], [3,30,999,12.99,0.75,0.012,10,1.0,0],
        [1,0,999,14.99,3.50,0.004,14,1.0,300], [2,0,999,17.99,3.90,0.004,14,1.0,0],
        [4,0,999,24.99,6.00,0,16,1.5,0], [5,0,999,29.99,7.50,0,16,1.5,0], [6,0,999,34.99,8.50,0,18,2.0,0],
    ];
    foreach ($rules as $r) $ri->execute([$prov,$svc,$typ,$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7],$r[8]]);
    echo "  tariff: 7 rules seeded\n";
}

echo "== 5. Suppliers (procurement demo set) ==\n";
if (!(int) $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn()) {
    $si = $pdo->prepare("INSERT INTO suppliers (supplier_code, company_name, email, lead_time_days, customs_clearance_days, status, payment_terms, currency_code, country_code, created_at)
                         VALUES (?,?,?,?,?,'active','NET30','USD',?,NOW())");
    $si->execute(['SUP001','Ventilación Industrial MX','ventas@vimx.example',7,0,'MX']);
    $si->execute(['SUP002','ElectroPartes del Norte','pedidos@epn.example',10,0,'MX']);
    $si->execute(['SUP003','Global HVAC Imports','orders@ghi.example',21,7,'CN']);
    echo "  3 suppliers created\n";
}

echo "== 6. Chat channels ==\n";
foreach (['General','Marketing','Engineering','Sales','Warehouse','Finance'] as $ch) {
    $pdo->prepare("INSERT INTO chat_channels (name, type) SELECT ?, 'group'
                   WHERE NOT EXISTS (SELECT 1 FROM chat_channels WHERE name = ? AND type='group')")->execute([$ch, $ch]);
}
echo "  channels: " . $pdo->query("SELECT COUNT(*) FROM chat_channels")->fetchColumn() . "\n";

echo "== 7. Site settings ==\n";
$pdo->exec("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('theme','default'),('popup_enabled','0')");
echo "  defaults set\n";

echo "\nBase recovery done. Now run, in order:\n";
echo "  php migration/run_etl.php\n";
echo "  php scripts/rebuild-categories.php\n";
echo "  php migration/seed_warehouse.php\n";
echo "  php migration/run_putaway.php\n";
echo "  php migrate.php --baseline\n";
echo "  php tests/regression.php\n";
