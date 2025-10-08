<?php
// Server-Sent Events endpoint for real-time inventory updates
// This script provides real-time inventory updates using SSE

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

require_once '../../../config/database.php';

// Get the last event ID if provided
$lastEventId = intval($_SERVER["HTTP_LAST_EVENT_ID"] ?? 0);
if ($lastEventId == 0) {
    $lastEventId = intval($_GET['lastEventId'] ?? 0);
}

// Function to send SSE message
function sendSSE($data, $eventType = 'message') {
    echo "event: {$eventType}\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Function to check for inventory updates
function checkForInventoryUpdates($pdo, $lastCheckTime) {
    try {
        // Check for recent inventory movements
        $stmt = $pdo->prepare("
            SELECT im.*, p.name as product_name, il.location_name
            FROM inventory_movements im
            JOIN products p ON im.product_id = p.id
            JOIN inventory_locations il ON im.location_id = il.id
            WHERE im.created_at > ?
            ORDER BY im.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$lastCheckTime]);
        $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $movements;
    } catch (Exception $e) {
        error_log("Error checking inventory updates: " . $e->getMessage());
        return [];
    }
}

// Function to get current inventory summary
function getInventorySummary($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(DISTINCT product_id) as total_products,
                SUM(quantity_on_hand) as total_items,
                COUNT(CASE WHEN quantity_on_hand <= reorder_point THEN 1 END) as low_stock_items
            FROM product_inventory
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting inventory summary: " . $e->getMessage());
        return null;
    }
}

// Send initial connection message
sendSSE([
    'message' => 'Connected to inventory update stream',
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => getInventorySummary($pdo)
], 'connected');

// Keep track of last check time
$lastCheckTime = date('Y-m-d H:i:s', time() - 30); // Check last 30 seconds

// Main loop for sending updates
while (true) {
    // Check for inventory updates
    $updates = checkForInventoryUpdates($pdo, $lastCheckTime);
    
    if (!empty($updates)) {
        foreach ($updates as $update) {
            sendSSE([
                'type' => 'inventory_movement',
                'data' => $update,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'update');
        }
    }
    
    // Send periodic summary every 30 seconds
    if (time() % 30 == 0) {
        $summary = getInventorySummary($pdo);
        if ($summary) {
            sendSSE([
                'type' => 'inventory_summary',
                'data' => $summary,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'summary');
        }
    }
    
    // Update last check time
    $lastCheckTime = date('Y-m-d H:i:s', time() - 5); // Check last 5 seconds
    
    // Sleep for 2 seconds before next check
    sleep(2);
    
    // Check if client is still connected
    if (connection_aborted()) {
        break;
    }
}

?>