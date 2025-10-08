<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$action = $input['action'] ?? '';
$productId = intval($input['product_id'] ?? 0);
$quantity = intval($input['quantity'] ?? 1);

// Validate product exists
if ($productId > 0) {
    $stmt = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
}

switch ($action) {
    case 'add':
        if ($productId <= 0 || $quantity <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID or quantity']);
            exit;
        }
        
        // Check stock availability
        $currentCart = getCartItems();
        $currentQuantity = $currentCart[$productId] ?? 0;
        $newQuantity = $currentQuantity + $quantity;
        
        if ($newQuantity > $product['stock']) {
            echo json_encode([
                'success' => false, 
                'message' => 'Not enough stock available. Only ' . $product['stock'] . ' items in stock.'
            ]);
            exit;
        }
        
        addToCart($productId, $quantity);
        
        echo json_encode([
            'success' => true,
            'message' => 'Product added to cart',
            'cart_count' => getCartCount(),
            'cart_total' => getCartTotal($pdo)
        ]);
        break;
        
    case 'update':
        if ($productId <= 0 || $quantity < 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID or quantity']);
            exit;
        }
        
        if ($quantity === 0) {
            removeFromCart($productId);
        } else {
            // Check stock availability
            if ($quantity > $product['stock']) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Not enough stock available. Only ' . $product['stock'] . ' items in stock.'
                ]);
                exit;
            }
            
            $_SESSION['cart'][$productId] = $quantity;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Cart updated',
            'cart_count' => getCartCount(),
            'cart_total' => getCartTotal($pdo)
        ]);
        break;
        
    case 'remove':
        if ($productId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit;
        }
        
        removeFromCart($productId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Product removed from cart',
            'cart_count' => getCartCount(),
            'cart_total' => getCartTotal($pdo)
        ]);
        break;
        
    case 'clear':
        $_SESSION['cart'] = [];
        
        echo json_encode([
            'success' => true,
            'message' => 'Cart cleared',
            'cart_count' => 0,
            'cart_total' => 0
        ]);
        break;
        
    case 'get':
        $cart = getCartItems();
        $cartDetails = [];
        
        if (!empty($cart)) {
            $productIds = array_keys($cart);
            $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
            
            $stmt = $pdo->prepare("SELECT id, name, price, image_url, stock FROM products WHERE id IN ($placeholders)");
            $stmt->execute($productIds);
            $products = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            foreach ($cart as $productId => $quantity) {
                if (isset($products[$productId])) {
                    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch();
                    
                    $cartDetails[] = [
                        'product_id' => $productId,
                        'name' => $product['name'],
                        'price' => $product['price'],
                        'image_url' => $product['image_url'],
                        'quantity' => $quantity,
                        'subtotal' => $product['price'] * $quantity,
                        'stock' => $product['stock']
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'cart' => $cartDetails,
            'cart_count' => getCartCount(),
            'cart_total' => getCartTotal($pdo)
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
