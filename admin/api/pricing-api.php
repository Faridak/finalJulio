<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

// Allow CORS for API access
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_product_price':
            $productId = intval($_GET['product_id'] ?? 0);
            if (!$productId) {
                throw new Exception("Product ID is required");
            }
            
            // Get current product price with active discounts
            $stmt = $pdo->prepare("
                SELECT p.price as base_price, 
                       p.name,
                       d.discount_type,
                       d.discount_value,
                       CASE 
                           WHEN d.discount_type = 'percentage' THEN p.price * (1 - d.discount_value / 100)
                           WHEN d.discount_type = 'fixed_amount' THEN p.price - d.discount_value
                           ELSE p.price
                       END as final_price
                FROM products p
                LEFT JOIN product_discounts d ON p.id = d.product_id 
                    AND d.is_active = 1 
                    AND d.start_date <= NOW() 
                    AND d.end_date >= NOW()
                WHERE p.id = ?
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception("Product not found");
            }
            
            echo json_encode([
                'success' => true,
                'data' => $product
            ]);
            break;
            
        case 'get_active_promotions':
            $stmt = $pdo->prepare("
                SELECT pp.*, COUNT(prp.product_id) as product_count 
                FROM product_promotions pp 
                LEFT JOIN promotion_products prp ON pp.id = prp.promotion_id 
                WHERE pp.is_active = 1 
                AND pp.start_date <= NOW() 
                AND pp.end_date >= NOW()
                GROUP BY pp.id 
                ORDER BY pp.created_at DESC
            ");
            $stmt->execute();
            $promotions = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $promotions
            ]);
            break;
            
        case 'get_product_promotions':
            $productId = intval($_GET['product_id'] ?? 0);
            if (!$productId) {
                throw new Exception("Product ID is required");
            }
            
            $stmt = $pdo->prepare("
                SELECT pp.* 
                FROM product_promotions pp
                JOIN promotion_products prp ON pp.id = prp.promotion_id
                WHERE prp.product_id = ?
                AND pp.is_active = 1 
                AND pp.start_date <= NOW() 
                AND pp.end_date >= NOW()
                ORDER BY pp.created_at DESC
            ");
            $stmt->execute([$productId]);
            $promotions = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $promotions
            ]);
            break;
            
        default:
            throw new Exception("Invalid action specified");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>