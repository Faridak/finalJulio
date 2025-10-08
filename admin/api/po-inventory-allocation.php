<?php
// Simplified database configuration for VentDepot
$host = 'localhost';
$dbname = 'finalJulio';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

// Handle CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($method) {
        case 'POST':
            handlePostRequest($pdo, $input);
            break;
        case 'GET':
            handleGetRequest($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function handlePostRequest($pdo, $input) {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'allocate_po_items':
            allocatePOItems($pdo, $input);
            break;
        case 'update_po_status':
            updatePOStatus($pdo, $input);
            break;
        case 'allocate_items_to_bins':
            allocateItemsToBins($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

function handleGetRequest($pdo) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_empty_bins':
            getEmptyBins($pdo);
            break;
        case 'get_po_allocation':
            getPOAllocation($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

function updatePOStatus($pdo, $data) {
    $poId = $data['po_id'] ?? null;
    $newStatus = $data['status'] ?? null;
    
    if (!$poId || !$newStatus) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'PO ID and status are required']);
        return;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update PO status
        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ?, actual_delivery_date = ? WHERE id = ?");
        $stmt->execute([$newStatus, ($newStatus === 'received') ? date('Y-m-d') : null, $poId]);
        
        // If status is 'received', allocate items to inventory
        if ($newStatus === 'received') {
            allocatePOItemsToInventory($pdo, $poId);
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Purchase order status updated successfully']);
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error updating PO status: ' . $e->getMessage()]);
    }
}

function allocatePOItemsToInventory($pdo, $poId) {
    try {
        // Get PO items
        $stmt = $pdo->prepare("
            SELECT poi.*
            FROM purchase_order_items poi
            WHERE poi.purchase_order_id = ?
        ");
        $stmt->execute([$poId]);
        $items = $stmt->fetchAll();
        
        // Get empty bins for allocation
        $emptyBins = getEmptyBinsForAllocation($pdo);
        
        if (empty($emptyBins)) {
            throw new Exception("No empty bins available for allocation");
        }
        
        $binIndex = 0;
        
        foreach ($items as $item) {
            $quantity = $item['quantity_ordered'];
            // Use product_name directly from purchase_order_items
            $productName = $item['product_name'];
            $sku = !empty($item['supplier_sku']) ? $item['supplier_sku'] : 'N/A';
            
            // Allocate items to bins
            while ($quantity > 0 && $binIndex < count($emptyBins)) {
                $bin = $emptyBins[$binIndex];
                $binCapacity = 100; // Assuming each bin can hold 100 units (this should be configurable)
                $allocateQuantity = min($quantity, $binCapacity);
                
                // Insert inventory item into bin
                $stmt = $pdo->prepare("
                    INSERT INTO warehouse_inventory (bin_id, item_name, sku, quantity, date_arrived) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $bin['id'],
                    $productName,
                    $sku,
                    $allocateQuantity,
                    date('Y-m-d')
                ]);
                
                // Record the allocation in the linking table
                $stmt = $pdo->prepare("
                    INSERT INTO po_inventory_allocations 
                    (purchase_order_id, purchase_order_item_id, warehouse_bin_id, quantity_allocated, allocated_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $poId,
                    $item['id'],
                    $bin['id'],
                    $allocateQuantity,
                    $_SESSION['user_id'] ?? null
                ]);
                
                // Update bin status
                $newStatus = ($allocateQuantity < $binCapacity) ? 'partial' : 'full';
                $stmt = $pdo->prepare("UPDATE warehouse_bins SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $bin['id']]);
                
                $quantity -= $allocateQuantity;
                
                // Move to next bin if current one is full
                if ($newStatus === 'full') {
                    $binIndex++;
                }
            }
            
            // If we still have quantity left but no more bins, throw an error
            if ($quantity > 0) {
                throw new Exception("Not enough bins available to allocate all items. Remaining: $quantity");
            }
            
            // Mark item as fully allocated
            $stmt = $pdo->prepare("UPDATE purchase_order_items SET fully_allocated = TRUE, status = 'received', quantity_received = quantity_ordered WHERE id = ?");
            $stmt->execute([$item['id']]);
        }
        
    } catch (Exception $e) {
        throw new Exception("Error allocating items to inventory: " . $e->getMessage());
    }
}

function allocateItemsToBins($pdo, $data) {
    $poId = $data['po_id'] ?? null;
    $allocations = $data['allocations'] ?? [];
    
    if (!$poId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'PO ID is required']);
        return;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        foreach ($allocations as $allocation) {
            $itemId = $allocation['item_id'];
            $binId = $allocation['bin_id'];
            $quantity = $allocation['quantity'];
            
            // Get item details
            $stmt = $pdo->prepare("
                SELECT poi.*
                FROM purchase_order_items poi
                WHERE poi.id = ?
            ");
            $stmt->execute([$itemId]);
            $itemData = $stmt->fetch();
            
            if (!$itemData) {
                throw new Exception("Item not found: $itemId");
            }
            
            // Insert inventory item into bin
            $stmt = $pdo->prepare("
                INSERT INTO warehouse_inventory (bin_id, item_name, sku, quantity, date_arrived) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $binId,
                $itemData['product_name'],
                !empty($itemData['supplier_sku']) ? $itemData['supplier_sku'] : 'N/A',
                $quantity,
                date('Y-m-d')
            ]);
            
            // Record the allocation in the linking table
            $stmt = $pdo->prepare("
                INSERT INTO po_inventory_allocations 
                (purchase_order_id, purchase_order_item_id, warehouse_bin_id, quantity_allocated, allocated_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $poId,
                $itemId,
                $binId,
                $quantity,
                $_SESSION['user_id'] ?? null
            ]);
            
            // Update bin status
            $stmt = $pdo->prepare("UPDATE warehouse_bins SET status = 'partial' WHERE id = ?");
            $stmt->execute([$binId]);
            
            // Update item received quantity
            $stmt = $pdo->prepare("
                UPDATE purchase_order_items 
                SET quantity_received = quantity_received + ?, status = 'partial_received'
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $itemId]);
        }
        
        // Check if all items are fully allocated
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as remaining 
            FROM purchase_order_items 
            WHERE purchase_order_id = ? AND quantity_received < quantity_ordered
        ");
        $stmt->execute([$poId]);
        $remaining = $stmt->fetch();
        
        // If all items are allocated, update PO status
        if ($remaining['remaining'] == 0) {
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'received', actual_delivery_date = ? WHERE id = ?");
            $stmt->execute([date('Y-m-d'), $poId]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Items allocated successfully']);
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error allocating items: ' . $e->getMessage()]);
    }
}

function getEmptyBinsForAllocation($pdo) {
    try {
        // Get empty bins ordered by rack, shelf, and position for systematic allocation
        $stmt = $pdo->prepare("
            SELECT wb.id, wb.bin_code, ws.shelf_level, wr.rack_code
            FROM warehouse_bins wb
            JOIN warehouse_shelves ws ON wb.shelf_id = ws.id
            JOIN warehouse_racks wr ON ws.rack_id = wr.id
            WHERE wb.status IN ('empty', 'partial')
            ORDER BY wr.rack_code, ws.shelf_level, wb.bin_position
            LIMIT 50
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        throw new Exception("Error fetching empty bins: " . $e->getMessage());
    }
}

function getEmptyBins($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT wb.id, wb.bin_code, wb.status,
                   ws.shelf_level,
                   wr.rack_code, wr.name as rack_name
            FROM warehouse_bins wb
            JOIN warehouse_shelves ws ON wb.shelf_id = ws.id
            JOIN warehouse_racks wr ON ws.rack_id = wr.id
            WHERE wb.status IN ('empty', 'partial')
            ORDER BY wr.rack_code, ws.shelf_level, wb.bin_position
        ");
        $stmt->execute();
        $bins = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'bins' => $bins]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error fetching bins: ' . $e->getMessage()]);
    }
}

function getPOAllocation($pdo) {
    $poId = $_GET['po_id'] ?? null;
    
    if (!$poId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'PO ID is required']);
        return;
    }
    
    try {
        // Get PO details
        $stmt = $pdo->prepare("
            SELECT po.*, s.company_name, il.location_name
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.id
            JOIN inventory_locations il ON po.location_id = il.id
            WHERE po.id = ?
        ");
        $stmt->execute([$poId]);
        $po = $stmt->fetch();
        
        if (!$po) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
            return;
        }
        
        // Get PO items
        $stmt = $pdo->prepare("
            SELECT poi.*, sp.product_name, sp.supplier_sku as sku
            FROM purchase_order_items poi
            LEFT JOIN supplier_products sp ON poi.supplier_product_id = sp.id
            WHERE poi.purchase_order_id = ?
        ");
        $stmt->execute([$poId]);
        $items = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'po' => $po, 'items' => $items]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error fetching PO allocation: ' . $e->getMessage()]);
    }
}
?>