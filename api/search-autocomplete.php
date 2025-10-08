<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/ProductSearch.php';

// Get query parameter
$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['suggestions' => []]);
    exit;
}

try {
    $productSearch = new ProductSearch($pdo);
    $suggestions = $productSearch->autoComplete($query, 8);
    
    // Format suggestions for frontend
    $formattedSuggestions = [];
    foreach ($suggestions as $suggestion) {
        $formattedSuggestions[] = [
            'text' => $suggestion['name'],
            'type' => $suggestion['type'],
            'id' => $suggestion['id'],
            'url' => $suggestion['type'] === 'product' 
                ? 'product.php?id=' . $suggestion['id']
                : 'search.php?category=' . urlencode($suggestion['name'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'suggestions' => $formattedSuggestions,
        'query' => $query
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Search error',
        'suggestions' => []
    ]);
}
?>