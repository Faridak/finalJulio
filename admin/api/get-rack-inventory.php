<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $rackId = $input['rack_id'] ?? '';
    $rackCode = $input['rack_code'] ?? '';
    
    // Mock data for demonstration - grouped by shelf levels
    $mockData = [
        'shelves' => [
            [
                'level' => 1,
                'level_name' => 'Ground Level',
                'items' => [
                    [
                        'name' => 'Laptop Dell XPS 13',
                        'sku' => 'DELL-XPS13-001',
                        'quantity' => 8,
                        'bin_address' => $rackCode . '-L1-P01',
                        'image_url' => 'https://via.placeholder.com/80x80?text=Laptop',
                        'price' => 1299.99
                    ],
                    [
                        'name' => 'Monitor Samsung 27"',
                        'sku' => 'SAM-MON27-024',
                        'quantity' => 12,
                        'bin_address' => $rackCode . '-L1-P02',
                        'image_url' => 'https://via.placeholder.com/80x80?text=Monitor',
                        'price' => 299.99
                    ]
                ]
            ],
            [
                'level' => 2,
                'level_name' => 'Second Level',
                'items' => [
                    [
                        'name' => 'iPhone 14 Pro Max',
                        'sku' => 'APL-IP14PM-512',
                        'quantity' => 15,
                        'bin_address' => $rackCode . '-L2-P01',
                        'image_url' => 'https://via.placeholder.com/80x80?text=iPhone',
                        'price' => 1099.99
                    ],
                    [
                        'name' => 'AirPods Pro 2nd Gen',
                        'sku' => 'APL-AIRP2-PRO',
                        'quantity' => 25,
                        'bin_address' => $rackCode . '-L2-P03',
                        'image_url' => 'https://via.placeholder.com/80x80?text=AirPods',
                        'price' => 249.99
                    ]
                ]
            ],
            [
                'level' => 3,
                'level_name' => 'Third Level',
                'items' => [
                    [
                        'name' => 'Gaming Mouse Logitech',
                        'sku' => 'LOG-GM-X001',
                        'quantity' => 30,
                        'bin_address' => $rackCode . '-L3-P01',
                        'image_url' => 'https://via.placeholder.com/80x80?text=Mouse',
                        'price' => 79.99
                    ]
                ]
            ]
        ],
        'summary' => [
            'total_items' => 90,
            'total_value' => 28549.15,
            'occupancy_rate' => 75
        ]
    ];
    
    echo json_encode(['success' => true, 'data' => $mockData]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>