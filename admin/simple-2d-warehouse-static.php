<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple 2D Warehouse Visualization - Static</title>
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
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Simple 2D Warehouse Visualization</h1>
                    <p class="text-gray-600 mt-2">
                        Static geometric representation of warehouse racks, shelves, and bins
                    </p>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-semibold text-blue-600">Main Warehouse</h2>
                    <p class="text-gray-500">WH-001</p>
                </div>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
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
        
        <!-- Warehouse Visualization -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php for ($r = 1; $r <= 3; $r++): ?>
                <div class="rack-container">
                    <div class="rack-header">
                        Rack <?= $r ?> (R<?= str_pad($r, 2, '0', STR_PAD_LEFT) ?>)
                    </div>
                    
                    <div class="p-3">
                        <?php for ($l = 1; $l <= 5; $l++): ?>
                            <div class="shelf-container">
                                <div class="shelf-label">Level <?= $l ?></div>
                                <div class="bins-container">
                                    <?php for ($b = 1; $b <= 10; $b++): ?>
                                        <?php
                                        // Randomly assign status to bins for visual effect
                                        $statuses = ['empty', 'partial', 'full', 'blocked'];
                                        $status = $statuses[array_rand($statuses)];
                                        ?>
                                        <div class="bin bin-<?= $status ?>" 
                                             title="Bin: B<?= str_pad($b, 2, '0', STR_PAD_LEFT) ?>, Status: <?= ucfirst($status) ?>">
                                            <?= str_pad($b, 2, '0', STR_PAD_LEFT) ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        
        <!-- Information Panel -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-warehouse mr-2 text-blue-600"></i>Warehouse Structure
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">3</div>
                    <div class="text-sm text-gray-600">Racks</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">15</div>
                    <div class="text-sm text-gray-600">Shelves (Levels)</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">150</div>
                    <div class="text-sm text-gray-600">Total Bins</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>