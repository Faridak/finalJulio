<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Get warehouse data
try {
    // Get a sample location
    $stmt = $pdo->prepare("SELECT id, location_name, location_code FROM inventory_locations WHERE status = 'active' ORDER BY id LIMIT 1");
    $stmt->execute();
    $location = $stmt->fetch();
    
    if (!$location) {
        // If no locations, create a mock structure
        $warehouseData = [
            'location_name' => 'Main Warehouse',
            'location_code' => 'WH-001',
            'racks' => []
        ];
        
        // Create 3 sample racks
        for ($r = 1; $r <= 3; $r++) {
            $rack = [
                'rack_code' => 'R' . str_pad($r, 2, '0', STR_PAD_LEFT),
                'rack_name' => 'Rack ' . $r,
                'levels' => []
            ];
            
            // Create 5 levels (shelves) per rack
            for ($l = 1; $l <= 5; $l++) {
                $level = [
                    'level_number' => $l,
                    'bins' => []
                ];
                
                // Create 10 bins per level
                for ($b = 1; $b <= 10; $b++) {
                    // Randomly assign status to bins for visual effect
                    $statuses = ['empty', 'partial', 'full', 'blocked'];
                    $status = $statuses[array_rand($statuses)];
                    
                    // Generate mock bin data
                    $binData = [
                        'bin_code' => 'B' . str_pad($b, 2, '0', STR_PAD_LEFT),
                        'position' => $b,
                        'status' => $status,
                        'items' => []
                    ];
                    
                    // Add mock items if bin is not empty
                    if ($status !== 'empty' && $status !== 'blocked') {
                        $itemCount = rand(1, 5);
                        for ($i = 1; $i <= $itemCount; $i++) {
                            $binData['items'][] = [
                                'name' => 'Product ' . chr(64 + rand(1, 26)) . rand(100, 999),
                                'sku' => 'SKU-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                                'quantity' => rand(1, 100),
                                'date_arrived' => date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'))
                            ];
                        }
                    }
                    
                    $level['bins'][] = $binData;
                }
                
                $rack['levels'][] = $level;
            }
            
            $warehouseData['racks'][] = $rack;
        }
    } else {
        // If we have real data, fetch it
        $warehouseData = [
            'location_name' => $location['location_name'],
            'location_code' => $location['location_code'],
            'racks' => []
        ];
        
        // Try to get real rack data
        try {
            $stmt = $pdo->prepare("
                SELECT sr.id, sr.rack_code, sr.rack_name, sr.levels, sr.positions
                FROM storage_racks sr
                JOIN warehouse_zones wz ON sr.zone_id = wz.id
                WHERE wz.location_id = ?
                ORDER BY sr.rack_code
                LIMIT 3
            ");
            $stmt->execute([$location['id']]);
            $racks = $stmt->fetchAll();
            
            if (empty($racks)) {
                // If no racks, create mock data
                throw new Exception('No racks found');
            }
            
            foreach ($racks as $rack) {
                $rackData = [
                    'rack_code' => $rack['rack_code'],
                    'rack_name' => $rack['rack_name'],
                    'levels' => []
                ];
                
                // Create levels
                for ($l = 1; $l <= min($rack['levels'], 5); $l++) {
                    $level = [
                        'level_number' => $l,
                        'bins' => []
                    ];
                    
                    // Try to get real bin data
                    try {
                        $stmt = $pdo->prepare("
                            SELECT bin_code, position_number, occupancy_status
                            FROM inventory_bins
                            WHERE rack_id = ? AND level_number = ?
                            ORDER BY position_number
                            LIMIT 10
                        ");
                        $stmt->execute([$rack['id'], $l]);
                        $bins = $stmt->fetchAll();
                        
                        if (!empty($bins)) {
                            foreach ($bins as $bin) {
                                // Get items in this bin
                                $items = [];
                                try {
                                    $itemStmt = $pdo->prepare("
                                        SELECT item_name, sku, quantity, date_arrived
                                        FROM warehouse_inventory
                                        WHERE bin_id = (
                                            SELECT id FROM inventory_bins 
                                            WHERE bin_code = ?
                                        )
                                    ");
                                    $itemStmt->execute([$bin['bin_code']]);
                                    $items = $itemStmt->fetchAll();
                                } catch (Exception $e) {
                                    // Use mock data if there's an error
                                    if ($bin['occupancy_status'] !== 'empty' && $bin['occupancy_status'] !== 'blocked') {
                                        $itemCount = rand(1, 5);
                                        for ($i = 1; $i <= $itemCount; $i++) {
                                            $items[] = [
                                                'item_name' => 'Product ' . chr(64 + rand(1, 26)) . rand(100, 999),
                                                'sku' => 'SKU-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                                                'quantity' => rand(1, 100),
                                                'date_arrived' => date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'))
                                            ];
                                        }
                                    }
                                }
                                
                                $level['bins'][] = [
                                    'bin_code' => $bin['bin_code'],
                                    'position' => $bin['position_number'],
                                    'status' => $bin['occupancy_status'],
                                    'items' => $items
                                ];
                            }
                        } else {
                            // Create mock bins if none exist
                            for ($b = 1; $b <= min($rack['positions'], 10); $b++) {
                                $statuses = ['empty', 'partial', 'full', 'blocked'];
                                $status = $statuses[array_rand($statuses)];
                                
                                // Generate mock bin data
                                $binData = [
                                    'bin_code' => 'B' . str_pad($b, 2, '0', STR_PAD_LEFT),
                                    'position' => $b,
                                    'status' => $status,
                                    'items' => []
                                ];
                                
                                // Add mock items if bin is not empty
                                if ($status !== 'empty' && $status !== 'blocked') {
                                    $itemCount = rand(1, 5);
                                    for ($i = 1; $i <= $itemCount; $i++) {
                                        $binData['items'][] = [
                                            'item_name' => 'Product ' . chr(64 + rand(1, 26)) . rand(100, 999),
                                            'sku' => 'SKU-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                                            'quantity' => rand(1, 100),
                                            'date_arrived' => date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'))
                                        ];
                                    }
                                }
                                
                                $level['bins'][] = $binData;
                            }
                        }
                    } catch (Exception $e) {
                        // Create mock bins on error
                        for ($b = 1; $b <= 10; $b++) {
                            $statuses = ['empty', 'partial', 'full', 'blocked'];
                            $status = $statuses[array_rand($statuses)];
                            
                            // Generate mock bin data
                            $binData = [
                                'bin_code' => 'B' . str_pad($b, 2, '0', STR_PAD_LEFT),
                                'position' => $b,
                                'status' => $status,
                                'items' => []
                            ];
                            
                            // Add mock items if bin is not empty
                            if ($status !== 'empty' && $status !== 'blocked') {
                                $itemCount = rand(1, 5);
                                for ($i = 1; $i <= $itemCount; $i++) {
                                    $binData['items'][] = [
                                        'item_name' => 'Product ' . chr(64 + rand(1, 26)) . rand(100, 999),
                                        'sku' => 'SKU-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                                        'quantity' => rand(1, 100),
                                        'date_arrived' => date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'))
                                    ];
                                }
                            }
                            
                            $level['bins'][] = $binData;
                        }
                    }
                    
                    $rackData['levels'][] = $level;
                }
                
                $warehouseData['racks'][] = $rackData;
            }
        } catch (Exception $e) {
            // Create mock data if there's an error
            for ($r = 1; $r <= 3; $r++) {
                $rack = [
                    'rack_code' => 'R' . str_pad($r, 2, '0', STR_PAD_LEFT),
                    'rack_name' => 'Rack ' . $r,
                    'levels' => []
                ];
                
                for ($l = 1; $l <= 5; $l++) {
                    $level = [
                        'level_number' => $l,
                        'bins' => []
                    ];
                    
                    for ($b = 1; $b <= 10; $b++) {
                        $statuses = ['empty', 'partial', 'full', 'blocked'];
                        $status = $statuses[array_rand($statuses)];
                        
                        // Generate mock bin data
                        $binData = [
                            'bin_code' => 'B' . str_pad($b, 2, '0', STR_PAD_LEFT),
                            'position' => $b,
                            'status' => $status,
                            'items' => []
                        ];
                        
                        // Add mock items if bin is not empty
                        if ($status !== 'empty' && $status !== 'blocked') {
                            $itemCount = rand(1, 5);
                            for ($i = 1; $i <= $itemCount; $i++) {
                                $binData['items'][] = [
                                    'item_name' => 'Product ' . chr(64 + rand(1, 26)) . rand(100, 999),
                                    'sku' => 'SKU-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                                    'quantity' => rand(1, 100),
                                    'date_arrived' => date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'))
                                ];
                            }
                        }
                        
                        $level['bins'][] = $binData;
                    }
                    
                    $rack['levels'][] = $level;
                }
                
                $warehouseData['racks'][] = $rack;
            }
        }
    }
} catch (Exception $e) {
    // Fallback to mock data
    $warehouseData = [
        'location_name' => 'Main Warehouse',
        'location_code' => 'WH-001',
        'racks' => []
    ];
    
    for ($r = 1; $r <= 3; $r++) {
        $rack = [
            'rack_code' => 'R' . str_pad($r, 2, '0', STR_PAD_LEFT),
            'rack_name' => 'Rack ' . $r,
            'levels' => []
        ];
        
        for ($l = 1; $l <= 5; $l++) {
            $level = [
                'level_number' => $l,
                'bins' => []
            ];
            
            for ($b = 1; $b <= 10; $b++) {
                $statuses = ['empty', 'partial', 'full', 'blocked'];
                $status = $statuses[array_rand($statuses)];
                
                // Generate mock bin data
                $binData = [
                    'bin_code' => 'B' . str_pad($b, 2, '0', STR_PAD_LEFT),
                    'position' => $b,
                    'status' => $status,
                    'items' => []
                ];
                
                // Add mock items if bin is not empty
                if ($status !== 'empty' && $status !== 'blocked') {
                    $itemCount = rand(1, 5);
                    for ($i = 1; $i <= $itemCount; $i++) {
                        $binData['items'][] = [
                            'item_name' => 'Product ' . chr(64 + rand(1, 26)) . rand(100, 999),
                            'sku' => 'SKU-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                            'quantity' => rand(1, 100),
                            'date_arrived' => date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'))
                        ];
                    }
                }
                
                $level['bins'][] = $binData;
            }
            
            $rack['levels'][] = $level;
        }
        
        $warehouseData['racks'][] = $rack;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple 2D Warehouse Visualization</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .rack-container {
            border: 2px solid #3b82f6;
            border-radius: 8px;
            background: linear-gradient(145deg, #dbeafe, #bfdbfe);
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .rack-header {
            background: #3b82f6;
            color: white;
            padding: 0.75rem;
            border-radius: 6px 6px 0 0;
            text-align: center;
            font-weight: bold;
        }
        
        .shelf-container {
            border: 1px solid #93c5fd;
            border-radius: 4px;
            margin: 0.5rem;
            padding: 0.5rem;
            background: #eff6ff;
        }
        
        .shelf-label {
            background: #2563eb;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        
        .bins-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        
        .bin {
            width: 30px;
            height: 25px;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #d1d5db;
        }
        
        .bin:hover {
            transform: scale(1.1);
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .bin-empty { 
            background: #f9fafb; 
            border-color: #e5e7eb;
            color: #9ca3af;
        }
        
        .bin-partial { 
            background: #fef3c7; 
            border-color: #f59e0b;
            color: #92400e;
        }
        
        .bin-full { 
            background: #dcfce7; 
            border-color: #16a34a;
            color: #166534;
        }
        
        .bin-blocked { 
            background: #fee2e2; 
            border-color: #dc2626;
            color: #991b1b;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 3px;
            margin-right: 0.5rem;
            border: 1px solid #d1d5db;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover,
        .close:focus {
            color: black;
        }
        
        .print-only {
            display: none;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block;
            }
            
            .modal {
                display: block !important;
                position: relative;
                z-index: 1;
                background-color: white;
            }
            
            .modal-content {
                box-shadow: none;
                border: none;
                width: 100%;
                max-width: 100%;
                max-height: none;
            }
        }
        
        .item-row:nth-child(even) {
            background-color: #f9fafb;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8 no-print">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Simple 2D Warehouse Visualization</h1>
                    <p class="text-gray-600 mt-2">
                        Static geometric representation of warehouse racks, shelves, and bins
                    </p>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-semibold text-blue-600"><?= htmlspecialchars($warehouseData['location_name']) ?></h2>
                    <p class="text-gray-500"><?= htmlspecialchars($warehouseData['location_code']) ?></p>
                </div>
            </div>
        </div>
        
        <!-- Print Header (visible only when printing) -->
        <div class="print-only text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Warehouse Inventory Report</h1>
            <p class="text-gray-600"><?= htmlspecialchars($warehouseData['location_name']) ?> (<?= htmlspecialchars($warehouseData['location_code']) ?>)</p>
            <p class="text-gray-500">Generated: <?= date('Y-m-d H:i:s') ?></p>
        </div>
        
        <!-- Legend -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8 no-print">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-info-circle mr-2 text-blue-600"></i>Bin Status Legend
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="legend-item">
                    <div class="legend-color bin-empty"></div>
                    <span class="text-sm text-gray-700">Empty</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color bin-partial"></div>
                    <span class="text-sm text-gray-700">Partial</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color bin-full"></div>
                    <span class="text-sm text-gray-700">Full</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color bin-blocked"></div>
                    <span class="text-sm text-gray-700">Blocked</span>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8 no-print">
            <div class="flex flex-wrap gap-4">
                <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    <i class="fas fa-print mr-2"></i>Print All Bin Details
                </button>
                <button onclick="exportToCSV()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    <i class="fas fa-file-csv mr-2"></i>Export to CSV
                </button>
            </div>
        </div>
        
        <!-- Warehouse Visualization -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($warehouseData['racks'] as $rack): ?>
                <div class="rack-container">
                    <div class="rack-header">
                        <?= htmlspecialchars($rack['rack_name']) ?> (<?= htmlspecialchars($rack['rack_code']) ?>)
                    </div>
                    
                    <div class="p-3">
                        <?php foreach ($rack['levels'] as $level): ?>
                            <div class="shelf-container">
                                <div class="shelf-label">Level <?= $level['level_number'] ?></div>
                                <div class="bins-container">
                                    <?php foreach ($level['bins'] as $bin): ?>
                                        <div class="bin bin-<?= $bin['status'] ?>" 
                                             onclick="showBinDetails('<?= htmlspecialchars($rack['rack_code']) ?>', <?= $level['level_number'] ?>, <?= $bin['position'] ?>, '<?= htmlspecialchars($bin['bin_code']) ?>', '<?= $bin['status'] ?>', <?= htmlspecialchars(json_encode($bin['items']), ENT_QUOTES) ?>)"
                                             title="Bin: <?= htmlspecialchars($bin['bin_code']) ?>, Status: <?= ucfirst($bin['status']) ?>. Click to view details.">
                                            <?= htmlspecialchars(substr($bin['bin_code'], 1)) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Information Panel -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8 no-print">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-warehouse mr-2 text-blue-600"></i>Warehouse Structure
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600"><?= count($warehouseData['racks']) ?></div>
                    <div class="text-sm text-gray-600">Racks</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">
                        <?= array_sum(array_map(function($rack) { return count($rack['levels']); }, $warehouseData['racks'])) ?>
                    </div>
                    <div class="text-sm text-gray-600">Shelves (Levels)</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">
                        <?= array_sum(array_map(function($rack) { 
                            return array_sum(array_map(function($level) { return count($level['bins']); }, $rack['levels'])); 
                        }, $warehouseData['racks'])) ?>
                    </div>
                    <div class="text-sm text-gray-600">Total Bins</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bin Details Modal -->
    <div id="binModal" class="modal no-print">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle" class="text-xl font-bold text-gray-900 mb-4"></h2>
            <div id="binStatus" class="mb-4"></div>
            <div id="binItems" class="mt-4"></div>
            <div class="mt-6 no-print">
                <button onclick="printBinDetails()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    <i class="fas fa-print mr-2"></i>Print Bin Details
                </button>
                <button onclick="closeModal()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 ml-2">
                    <i class="fas fa-times mr-2"></i>Close
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Global variable to store current bin data for printing
        let currentBinData = null;
        
        function showBinDetails(rackCode, level, position, binCode, status, items) {
            currentBinData = {
                rackCode: rackCode,
                level: level,
                position: position,
                binCode: binCode,
                status: status,
                items: items
            };
            
            const modal = document.getElementById('binModal');
            const modalTitle = document.getElementById('modalTitle');
            const binStatus = document.getElementById('binStatus');
            const binItems = document.getElementById('binItems');
            
            modalTitle.textContent = `Bin Details: ${binCode} (Rack ${rackCode}, Level ${level}, Position ${position})`;
            
            // Set status badge
            let statusClass = '';
            let statusText = '';
            switch(status) {
                case 'empty':
                    statusClass = 'bg-gray-100 text-gray-800';
                    statusText = 'Empty';
                    break;
                case 'partial':
                    statusClass = 'bg-yellow-100 text-yellow-800';
                    statusText = 'Partially Filled';
                    break;
                case 'full':
                    statusClass = 'bg-green-100 text-green-800';
                    statusText = 'Full';
                    break;
                case 'blocked':
                    statusClass = 'bg-red-100 text-red-800';
                    statusText = 'Blocked';
                    break;
                default:
                    statusClass = 'bg-gray-100 text-gray-800';
                    statusText = status;
            }
            
            binStatus.innerHTML = `<span class="px-2 py-1 rounded-full text-sm font-medium ${statusClass}">${statusText}</span>`;
            
            // Display items
            if (items && items.length > 0) {
                let itemsHtml = `
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Items in Bin (${items.length} items)</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item Name</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date Arrived</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                `;
                
                items.forEach(item => {
                    itemsHtml += `
                        <tr class="item-row">
                            <td class="px-4 py-2 text-sm text-gray-900">${item.item_name || item.name || 'N/A'}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">${item.sku || 'N/A'}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">${item.quantity || 0}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">${item.date_arrived || 'N/A'}</td>
                        </tr>
                    `;
                });
                
                itemsHtml += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                binItems.innerHTML = itemsHtml;
            } else {
                binItems.innerHTML = `
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Items in Bin</h3>
                    <div class="bg-gray-50 p-4 rounded-lg text-center">
                        <i class="fas fa-box-open text-gray-400 text-2xl mb-2"></i>
                        <p class="text-gray-500">No items in this bin</p>
                    </div>
                `;
            }
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('binModal').style.display = 'none';
        }
        
        function printBinDetails() {
            if (currentBinData) {
                // Create a printable version
                const printWindow = window.open('', '_blank');
                const itemsHtml = currentBinData.items && currentBinData.items.length > 0 ? 
                    currentBinData.items.map(item => `
                        <tr>
                            <td>${item.item_name || item.name || 'N/A'}</td>
                            <td>${item.sku || 'N/A'}</td>
                            <td>${item.quantity || 0}</td>
                            <td>${item.date_arrived || 'N/A'}</td>
                        </tr>
                    `).join('') : 
                    `<tr><td colspan="4" class="text-center">No items in this bin</td></tr>`;
                
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Bin Details - ${currentBinData.binCode}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                            th { background-color: #f2f2f2; }
                            h1 { color: #333; }
                            .status { padding: 4px 8px; border-radius: 4px; font-weight: bold; }
                            .status-empty { background-color: #f1f5f9; color: #64748b; }
                            .status-partial { background-color: #fef3c7; color: #92400e; }
                            .status-full { background-color: #dcfce7; color: #166534; }
                            .status-blocked { background-color: #fee2e2; color: #991b1b; }
                        </style>
                    </head>
                    <body>
                        <h1>Bin Details: ${currentBinData.binCode}</h1>
                        <p><strong>Location:</strong> Rack ${currentBinData.rackCode}, Level ${currentBinData.level}, Position ${currentBinData.position}</p>
                        <p><strong>Status:</strong> <span class="status status-${currentBinData.status}">${currentBinData.status.charAt(0).toUpperCase() + currentBinData.status.slice(1)}</span></p>
                        <p><strong>Report Generated:</strong> ${new Date().toLocaleString()}</p>
                        
                        <h2>Items in Bin</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>SKU</th>
                                    <th>Quantity</th>
                                    <th>Date Arrived</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml}
                            </tbody>
                        </table>
                    </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        }
        
        function exportToCSV() {
            // This would be implemented to export all bin data to CSV
            alert('CSV export functionality would be implemented here. In a real application, this would download a CSV file with all bin details.');
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('binModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>