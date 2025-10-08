<?php
require_once '../../config/database.php';
require_once '../../includes/security.php';

// Require admin login
requireRole('admin');

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $locationId = $input['location_id'] ?? null;
    
    $emptySpaces = [];
    $whereConditions = ["ib.occupancy_status = 'empty'", "ib.status = 'active'"];
    $params = [];
    
    if ($locationId) {
        $whereConditions[] = "il.id = ?";
        $params[] = $locationId;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get empty bins with full hierarchy information
    $query = "
        SELECT 
            ib.id,
            ib.bin_code,
            ib.bin_address,
            ib.level_number,
            ib.position_number,
            ib.bin_type,
            ib.occupancy_status,
            sr.rack_code,
            sr.rack_name,
            sr.levels as rack_levels,
            sr.positions as rack_positions,
            wz.zone_code,
            wz.zone_name,
            il.id as location_id,
            il.location_name,
            il.location_code,
            CONCAT(il.location_name, ' → ', wz.zone_name, ' → ', sr.rack_name, ' → Level ', ib.level_number, ' → ', ib.bin_address) as full_path
        FROM inventory_bins ib
        JOIN storage_racks sr ON ib.rack_id = sr.id
        JOIN warehouse_zones wz ON sr.zone_id = wz.id
        JOIN inventory_locations il ON wz.location_id = il.id
        WHERE $whereClause
        ORDER BY il.location_name, wz.zone_code, sr.rack_code, ib.level_number, ib.position_number
        LIMIT 500
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $bins = $stmt->fetchAll();
    
    foreach ($bins as $bin) {
        $emptySpaces[] = [
            'id' => 'bin-' . $bin['id'],
            'type' => 'bin',
            'location_id' => $bin['location_id'],
            'location_name' => $bin['location_name'],
            'location_code' => $bin['location_code'],
            'address' => $bin['bin_address'],
            'full_path' => $bin['full_path'],
            'bin_id' => $bin['id'],
            'bin_address' => $bin['bin_address'],
            'capacity' => 'medium' // Default capacity for filtering
        ];
    }
    
    // Get statistics
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT il.id) as empty_locations,
            COUNT(DISTINCT sr.id) as empty_racks,
            COUNT(DISTINCT CONCAT(sr.id, '-', ib.level_number)) as empty_shelves,
            COUNT(ib.id) as empty_bins
        FROM inventory_bins ib
        JOIN storage_racks sr ON ib.rack_id = sr.id
        JOIN warehouse_zones wz ON sr.zone_id = wz.id
        JOIN inventory_locations il ON wz.location_id = il.id
        WHERE $whereClause
    ";
    
    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute($params);
    $stats = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'spaces' => $emptySpaces,
        'stats' => [
            'locations' => intval($stats['empty_locations']),
            'racks' => intval($stats['empty_racks']),
            'shelves' => intval($stats['empty_shelves']),
            'bins' => intval($stats['empty_bins'])
        ]
    ]);
    
} catch (PDOException $e) {
    // If database error, return mock data as fallback
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'spaces' => [],
        'stats' => [
            'locations' => 0,
            'racks' => 0,
            'shelves' => 0,
            'bins' => 0
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'spaces' => [],
        'stats' => [
            'locations' => 0,
            'racks' => 0,
            'shelves' => 0,
            'bins' => 0
        ]
    ]);
}
?>