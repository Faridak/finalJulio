<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $binId = $input['bin_id'] ?? '';
    
    // Mock data for demonstration
    $mockItems = [
        [
            'id' => 1,
            'name' => 'iPhone 14 Pro',
            'sku' => 'APL-IP14P-256',
            'quantity' => 5,
            'image_url' => 'https://via.placeholder.com/100x100?text=iPhone',
            'price' => 999.99
        ],
        [
            'id' => 2,
            'name' => 'Samsung Galaxy S23',
            'sku' => 'SAM-GS23-128',
            'quantity' => 3,
            'image_url' => 'https://via.placeholder.com/100x100?text=Galaxy',
            'price' => 799.99
        ]
    ];
    
    echo json_encode(['success' => true, 'items' => $mockItems]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
