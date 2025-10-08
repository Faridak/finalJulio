<?php
// Manual allocation demonstration
$host = 'localhost';
$dbname = 'finalJulio';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Get a PO that hasn't been allocated yet
    $stmt = $pdo->query("SELECT id, po_number FROM purchase_orders WHERE status = 'confirmed' AND id NOT IN (SELECT DISTINCT purchase_order_id FROM po_inventory_allocations) LIMIT 1");
    $po = $stmt->fetch();
    
    if (!$po) {
        echo "No unallocated confirmed POs found.\n";
        exit;
    }
    
    $poId = $po['id'];
    $poNumber = $po['po_number'];
    
    echo "Demonstrating manual allocation for PO: $poNumber (ID: $poId)\n\n";
    
    // Get PO items
    $stmt = $pdo->prepare("SELECT id, product_name, quantity_ordered FROM purchase_order_items WHERE purchase_order_id = ?");
    $stmt->execute([$poId]);
    $items = $stmt->fetchAll();
    
    echo "PO Items:\n";
    foreach ($items as $item) {
        echo "- {$item['product_name']} (ID: {$item['id']}, Quantity: {$item['quantity_ordered']})\n";
    }
    
    echo "\n";
    
    // Get empty bins
    $stmt = $pdo->query("
        SELECT wb.id, wb.bin_code
        FROM warehouse_bins wb
        WHERE wb.status IN ('empty', 'partial')
        ORDER BY wb.id
        LIMIT 5
    ");
    $bins = $stmt->fetchAll();
    
    echo "Available Bins:\n";
    foreach ($bins as $bin) {
        echo "- {$bin['bin_code']} (ID: {$bin['id']})\n";
    }
    
    echo "\n";
    
    // Create allocation data
    $allocations = [];
    $binIndex = 0;
    
    foreach ($items as $item) {
        if ($binIndex < count($bins)) {
            $allocations[] = [
                'item_id' => $item['id'],
                'bin_id' => $bins[$binIndex]['id'],
                'quantity' => min($item['quantity_ordered'], 50) // Allocate up to 50 per bin
            ];
            $binIndex++;
        }
    }
    
    echo "Allocation Data to Send:\n";
    foreach ($allocations as $allocation) {
        echo "- Item ID: {$allocation['item_id']}, Bin ID: {$allocation['bin_id']}, Quantity: {$allocation['quantity']}\n";
    }
    
    echo "\n";
    
    // Prepare data for API call
    $data = json_encode([
        'action' => 'allocate_items_to_bins',
        'po_id' => $poId,
        'allocations' => $allocations
    ]);
    
    echo "API Request Data:\n$data\n\n";
    
    // Use cURL to make the POST request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/finalJulio/admin/api/po-inventory-allocation.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status Code: $httpCode\n";
    echo "API Response: $response\n\n";
    
    if ($httpCode == 200) {
        echo "SUCCESS: Manual allocation completed!\n";
        echo "You can now view the allocations in the inventory allocation interface.\n";
    } else {
        echo "ERROR: Manual allocation failed.\n";
    }
    
} catch(PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>