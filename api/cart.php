<?php
require_once __DIR__ . '/../config/database.php';

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

// Mirror cart mutations to the DB for logged-in users (survives logout/devices).
register_shutdown_function(function () use ($pdo, $action) {
    if (in_array($action, ['add', 'update', 'remove', 'clear'], true) && isLoggedIn()) {
        require_once __DIR__ . '/../services/CartService.php';
        try { (new CartService($pdo))->persist($_SESSION['user_id']); } catch (\Throwable $e) { /* best effort */ }
    }
});

// Helper function to get product from DB
function getProductFromDB($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT p.* 
        FROM products p 
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        // Map DB columns to expected keys if necessary
        $product['stock'] = $product['inventory']; // Use 'inventory' column from schema
        
        // Ensure image_url has a fallback
        if (empty($product['image_url'])) {
            $product['image_url'] = 'assets/images/placeholder.jpg'; // Default placeholder
        }
    }
    
    return $product;
}

// Helper to calculate total from EFFECTIVE prices (discounts/bulk/promos),
// matching what OrderProcessor will actually charge.
function getCartTotalFromDB($pdo) {
    if (empty($_SESSION['cart'])) return 0;

    require_once __DIR__ . '/../services/PricingService.php';
    $pricing = new PricingService($pdo);

    $total = 0;
    foreach ($_SESSION['cart'] as $id => $qty) {
        $ep = $pricing->effectivePrice((int) $id, (int) $qty);
        if (!empty($ep['unit_effective'])) $total += (float) $ep['unit_effective'] * $qty;
    }
    return round($total, 2);
}



// Validate product exists logic
$product = null;
if ($productId > 0) {
    $product = getProductFromDB($pdo, $productId);
    
    if (!$product) {
        // If action is remove or clear, we might not need the product to exist, but for 'add'/'update' we do.
        // For 'get', we skip this check in the loop.
        if ($action === 'add' || $action === 'update') {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }
    }
}

switch ($action) {
    case 'add':
        if ($productId <= 0 || $quantity <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID or quantity']);
            exit;
        }
        
        // Helper function fallback for session manipulation
        if (!function_exists('addToCart')) {
            function addToCart($id, $qty) {
                if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
                if (isset($_SESSION['cart'][$id])) {
                    $_SESSION['cart'][$id] += $qty;
                } else {
                    $_SESSION['cart'][$id] = $qty;
                }
            }
        }
        
        // Check stock availability
        $currentCart = $_SESSION['cart'] ?? [];
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
        
        // Update session totals
        $_SESSION['cart_count'] = getCartCount();
        $_SESSION['cart_total'] = getCartTotalFromDB($pdo);

        echo json_encode([
            'success' => true,
            'message' => 'Product added to cart',
            'cart_count' => $_SESSION['cart_count'],
            'cart_total' => $_SESSION['cart_total']
        ]);
        break;
        
    case 'update':
        if ($productId <= 0 || $quantity < 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID or quantity']);
            exit;
        }
        
        if ($quantity === 0) {
            if (isset($_SESSION['cart'][$productId])) {
                unset($_SESSION['cart'][$productId]);
            }
        } else {
            // Check stock
            if ($quantity > $product['stock']) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Not enough stock available. Only ' . $product['stock'] . ' items in stock.'
                ]);
                exit;
            }
            
            $_SESSION['cart'][$productId] = $quantity;
        }
        
        $_SESSION['cart_count'] = getCartCount();
        $_SESSION['cart_total'] = getCartTotalFromDB($pdo);

        echo json_encode([
            'success' => true,
            'message' => 'Cart updated',
            'cart_count' => $_SESSION['cart_count'],
            'cart_total' => $_SESSION['cart_total']
        ]);
        break;
        
    case 'remove':
        if ($productId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit;
        }
        
        if (isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
        }
        
        $_SESSION['cart_count'] = getCartCount();
        $_SESSION['cart_total'] = getCartTotalFromDB($pdo);

        echo json_encode([
            'success' => true,
            'message' => 'Product removed from cart',
            'cart_count' => $_SESSION['cart_count'],
            'cart_total' => $_SESSION['cart_total']
        ]);
        break;
        
    case 'clear':
        $_SESSION['cart'] = [];
        $_SESSION['cart_count'] = 0;
        $_SESSION['cart_total'] = 0;
        
        echo json_encode([
            'success' => true,
            'message' => 'Cart cleared',
            'cart_count' => 0,
            'cart_total' => 0
        ]);
        break;
        
    case 'get':
        $cart = $_SESSION['cart'] ?? [];
        $cartDetails = [];
        
        if (!empty($cart)) {
            $ids = array_keys($cart);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            
            // Fetch all cart products in one query
            $stmt = $pdo->prepare("
                SELECT p.* 
                FROM products p 
                WHERE p.id IN ($placeholders)
            ");
            $stmt->execute($ids);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Re-map by ID for easy access
            $productMap = [];
            foreach ($products as $p) {
                $productMap[$p['id']] = $p;
            }
            
            require_once __DIR__ . '/../services/PricingService.php';
            $pricingSvc = new PricingService($pdo);
            foreach ($cart as $id => $qty) {
                if (isset($productMap[$id])) {
                    $p = $productMap[$id];
                    // Handle image fallback
                    $img = !empty($p['image_url']) ? $p['image_url'] : 'assets/images/placeholder.jpg';
                    $ep = $pricingSvc->effectivePrice((int) $id, (int) $qty);
                    $unit = (float) ($ep['unit_effective'] ?? $p['price']);

                    $cartDetails[] = [
                        'product_id' => $id,
                        'name' => $p['name'],
                        'price' => $unit,
                        'base_price' => ($ep['discount'] ?? 0) > 0 ? (float) $p['price'] : null,
                        'image_url' => $img,
                        'quantity' => $qty,
                        'subtotal' => $unit * $qty,
                        'stock' => $p['inventory']
                    ];
                }
            }
        }
        
        // Recalculate total just in case
        $total = 0;
        foreach ($cartDetails as $item) {
            $total += $item['subtotal'];
        }
        $_SESSION['cart_total'] = $total;
        $_SESSION['cart_count'] = getCartCount();
        
        echo json_encode([
            'success' => true,
            'cart' => $cartDetails,
            'cart_count' => $_SESSION['cart_count'],
            'cart_total' => $_SESSION['cart_total']
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
