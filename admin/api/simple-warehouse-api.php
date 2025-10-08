<?php
// Simplified database configuration
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
    
    switch ($method) {
        case 'GET':
            handleGetRequest($pdo);
            break;
        case 'POST':
            handlePostRequest($pdo);
            break;
        case 'PUT':
            handlePutRequest($pdo);
            break;
        case 'DELETE':
            handleDeleteRequest($pdo);
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

function handleGetRequest($pdo) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'warehouse_structure':
            getWarehouseStructure($pdo);
            break;
        case 'bin_inventory':
            getBinInventory($pdo);
            break;
        case 'rack_inventory':
            getRackInventory($pdo);
            break;
        default:
            getAllRacks($pdo);
            break;
    }
}

function handlePostRequest($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'add_rack':
            addRack($pdo, $input);
            break;
        case 'remove_rack':
            removeRack($pdo, $input);
            break;
        case 'update_bin_inventory':
            updateBinInventory($pdo, $input);
            break;
        case 'move_inventory':
            moveInventory($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

function handlePutRequest($pdo) {
    // PUT requests can be handled similar to POST
    handlePostRequest($pdo);
}

function handleDeleteRequest($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $rackId = $input['rack_id'] ?? null;
    
    if (!$rackId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Rack ID is required']);
        return;
    }
    
    removeRack($pdo, ['rack_id' => $rackId]);
}

function getAllRacks($pdo) {
    try {
        // Fetch all racks
        $stmt = $pdo->prepare("SELECT * FROM warehouse_racks ORDER BY id");
        $stmt->execute();
        $racks = $stmt->fetchAll();
        
        // Fetch shelves and bins for each rack
        foreach ($racks as &$rack) {
            // Fetch shelves for this rack
            $shelfStmt = $pdo->prepare("SELECT * FROM warehouse_shelves WHERE rack_id = ? ORDER BY shelf_level");
            $shelfStmt->execute([$rack['id']]);
            $rack['shelves'] = $shelfStmt->fetchAll();
            
            // Fetch bins for each shelf
            foreach ($rack['shelves'] as &$shelf) {
                $binStmt = $pdo->prepare("SELECT * FROM warehouse_bins WHERE shelf_id = ? ORDER BY bin_position");
                $binStmt->execute([$shelf['id']]);
                $shelf['bins'] = $binStmt->fetchAll();
                
                // Fetch inventory for each bin
                foreach ($shelf['bins'] as &$bin) {
                    $inventoryStmt = $pdo->prepare("SELECT * FROM warehouse_inventory WHERE bin_id = ?");
                    $inventoryStmt->execute([$bin['id']]);
                    $bin['items'] = $inventoryStmt->fetchAll();
                }
            }
        }
        
        echo json_encode(['success' => true, 'data' => $racks]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error fetching racks: ' . $e->getMessage()]);
    }
}

function getWarehouseStructure($pdo) {
    getAllRacks($pdo);
}

function getBinInventory($pdo) {
    $binId = $_GET['bin_id'] ?? '';
    
    if (!$binId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bin ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM warehouse_inventory WHERE bin_id = ?");
        $stmt->execute([$binId]);
        $items = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'items' => $items]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error fetching bin inventory: ' . $e->getMessage()]);
    }
}

function getRackInventory($pdo) {
    $rackId = $_GET['rack_id'] ?? '';
    
    if (!$rackId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Rack ID is required']);
        return;
    }
    
    try {
        // Fetch shelves for this rack
        $shelfStmt = $pdo->prepare("SELECT * FROM warehouse_shelves WHERE rack_id = ? ORDER BY shelf_level");
        $shelfStmt->execute([$rackId]);
        $shelves = $shelfStmt->fetchAll();
        
        // Group inventory by shelf
        foreach ($shelves as &$shelf) {
            // Get all bins for this shelf
            $binStmt = $pdo->prepare("SELECT id FROM warehouse_bins WHERE shelf_id = ?");
            $binStmt->execute([$shelf['id']]);
            $binIds = $binStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($binIds)) {
                // Get all inventory items for all bins in this shelf
                $placeholders = str_repeat('?,', count($binIds) - 1) . '?';
                $inventoryStmt = $pdo->prepare("SELECT wi.*, wb.bin_code FROM warehouse_inventory wi 
                    JOIN warehouse_bins wb ON wi.bin_id = wb.id
                    WHERE wi.bin_id IN ($placeholders)");
                $inventoryStmt->execute($binIds);
                $shelf['items'] = $inventoryStmt->fetchAll();
            } else {
                $shelf['items'] = [];
            }
        }
        
        echo json_encode(['success' => true, 'shelves' => $shelves]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error fetching rack inventory: ' . $e->getMessage()]);
    }
}

function addRack($pdo, $data) {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get the next rack code
        $stmt = $pdo->prepare("SELECT MAX(id) as max_id FROM warehouse_racks");
        $stmt->execute();
        $result = $stmt->fetch();
        $nextId = ($result['max_id'] ?? 0) + 1;
        $rackCode = "R" . $nextId;
        
        // Insert the new rack
        $stmt = $pdo->prepare("INSERT INTO warehouse_racks (rack_code, name) VALUES (?, ?)");
        $stmt->execute([$rackCode, "Rack " . $nextId]);
        $rackId = $pdo->lastInsertId();
        
        // Insert shelves (5 shelves)
        for ($shelfLevel = 1; $shelfLevel <= 5; $shelfLevel++) {
            $stmt = $pdo->prepare("INSERT INTO warehouse_shelves (rack_id, shelf_level, name) VALUES (?, ?, ?)");
            $stmt->execute([$rackId, $shelfLevel, "Level " . $shelfLevel]);
            $shelfId = $pdo->lastInsertId();
            
            // Insert bins (10 bins per shelf)
            for ($binPos = 1; $binPos <= 10; $binPos++) {
                $binCode = "R{$nextId}-S{$shelfLevel}-B" . str_pad($binPos, 2, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO warehouse_bins (shelf_id, bin_position, bin_code) VALUES (?, ?, ?)");
                $stmt->execute([$shelfId, $binPos, $binCode]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Rack added successfully', 'rack_id' => $rackId]);
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error adding rack: ' . $e->getMessage()]);
    }
}

function removeRack($pdo, $data) {
    $rackId = $data['rack_id'] ?? null;
    
    if (!$rackId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Rack ID is required']);
        return;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete the rack (this will cascade delete shelves and bins due to foreign key constraints)
        $stmt = $pdo->prepare("DELETE FROM warehouse_racks WHERE id = ?");
        $stmt->execute([$rackId]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Rack removed successfully']);
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error removing rack: ' . $e->getMessage()]);
    }
}

function updateBinInventory($pdo, $data) {
    $binId = $data['bin_id'] ?? '';
    $items = $data['items'] ?? [];
    $status = $data['status'] ?? 'empty';
    
    if (!$binId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bin ID is required']);
        return;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update bin status
        $stmt = $pdo->prepare("UPDATE warehouse_bins SET status = ? WHERE id = ?");
        $stmt->execute([$status, $binId]);
        
        // Delete existing inventory for this bin
        $stmt = $pdo->prepare("DELETE FROM warehouse_inventory WHERE bin_id = ?");
        $stmt->execute([$binId]);
        
        // Insert new inventory items
        if (!empty($items)) {
            $stmt = $pdo->prepare("INSERT INTO warehouse_inventory (bin_id, item_name, sku, quantity, date_arrived) VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmt->execute([
                    $binId,
                    $item['name'] ?? '',
                    $item['sku'] ?? null,
                    $item['quantity'] ?? 1,
                    $item['date_arrived'] ?? null
                ]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Bin inventory updated successfully']);
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error updating bin inventory: ' . $e->getMessage()]);
    }
}

function moveInventory($pdo, $data) {
    $fromBinId = $data['from_bin_id'] ?? '';
    $toBinId = $data['to_bin_id'] ?? '';
    $itemId = $data['item_id'] ?? '';
    $quantity = $data['quantity'] ?? 0;
    
    if (!$fromBinId || !$toBinId || !$itemId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'From bin, to bin, and item ID are required']);
        return;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get the item from the source bin
        $stmt = $pdo->prepare("SELECT * FROM warehouse_inventory WHERE id = ? AND bin_id = ?");
        $stmt->execute([$itemId, $fromBinId]);
        $item = $stmt->fetch();
        
        if (!$item) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Item not found in source bin']);
            return;
        }
        
        // If moving partial quantity
        if ($quantity > 0 && $quantity < $item['quantity']) {
            // Update quantity in source bin
            $stmt = $pdo->prepare("UPDATE warehouse_inventory SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$quantity, $itemId]);
            
            // Insert new item in destination bin
            $stmt = $pdo->prepare("INSERT INTO warehouse_inventory (bin_id, item_name, sku, quantity, date_arrived) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $toBinId,
                $item['item_name'],
                $item['sku'],
                $quantity,
                $item['date_arrived']
            ]);
        } else {
            // Move entire item
            $stmt = $pdo->prepare("UPDATE warehouse_inventory SET bin_id = ? WHERE id = ?");
            $stmt->execute([$toBinId, $itemId]);
        }
        
        // Update bin statuses
        updateBinStatus($pdo, $fromBinId);
        updateBinStatus($pdo, $toBinId);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Inventory moved successfully']);
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error moving inventory: ' . $e->getMessage()]);
    }
}

function updateBinStatus($pdo, $binId) {
    try {
        // Count items in bin
        $stmt = $pdo->prepare("SELECT COUNT(*) as item_count FROM warehouse_inventory WHERE bin_id = ?");
        $stmt->execute([$binId]);
        $result = $stmt->fetch();
        $itemCount = $result['item_count'];
        
        // Determine status
        if ($itemCount == 0) {
            $status = 'empty';
        } else if ($itemCount < 5) {
            $status = 'partial';
        } else {
            $status = 'full';
        }
        
        // Update bin status
        $stmt = $pdo->prepare("UPDATE warehouse_bins SET status = ? WHERE id = ?");
        $stmt->execute([$status, $binId]);
    } catch (Exception $e) {
        // Log error but don't fail the operation
        error_log("Error updating bin status: " . $e->getMessage());
    }
}
?>