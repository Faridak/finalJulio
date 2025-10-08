<?php
// Check purchase order data
$host = 'localhost';
$dbname = 'finalJulio';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Count purchase orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM purchase_orders");
    $result = $stmt->fetch();
    echo "Purchase Orders Count: " . $result['count'] . "\n";
    
    // Count warehouse bins
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM warehouse_bins");
    $result = $stmt->fetch();
    echo "Warehouse Bins Count: " . $result['count'] . "\n";
    
    // Count warehouse inventory
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM warehouse_inventory");
    $result = $stmt->fetch();
    echo "Warehouse Inventory Items Count: " . $result['count'] . "\n";
    
    // Count po inventory allocations
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM po_inventory_allocations");
    $result = $stmt->fetch();
    echo "PO Inventory Allocations Count: " . $result['count'] . "\n";
    
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>