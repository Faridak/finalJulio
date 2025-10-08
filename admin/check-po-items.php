<?php
// Check purchase order items data
$host = 'localhost';
$dbname = 'finalJulio';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Get PO items for PO ID 2
    $stmt = $pdo->prepare("
        SELECT poi.*, sp.product_name, sp.supplier_sku
        FROM purchase_order_items poi
        LEFT JOIN supplier_products sp ON poi.supplier_product_id = sp.id
        WHERE poi.purchase_order_id = ?
    ");
    $stmt->execute([2]);
    $items = $stmt->fetchAll();
    
    echo "PO Items for PO ID 2:\n";
    foreach ($items as $item) {
        echo "- ID: {$item['id']}\n";
        echo "  Product Name: '{$item['product_name']}'\n";
        echo "  Supplier SKU: '{$item['supplier_sku']}'\n";
        echo "  Quantity Ordered: {$item['quantity_ordered']}\n";
        echo "  Supplier Product ID: {$item['supplier_product_id']}\n\n";
    }
    
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>