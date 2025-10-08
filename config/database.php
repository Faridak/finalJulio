<?php
// Database configuration for VentDepot
$host = 'localhost';
$dbname = 'finalJulio';
$username = 'root';
$password = '';

// Include security helper
require_once __DIR__ . '/../includes/security.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Session configuration with enhanced security - only if session not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Enhanced helper functions with security
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function requireLogin($redirectUrl = 'login.php') {
    if (!isLoggedIn()) {
        Security::logSecurityEvent('unauthorized_access_attempt', [
            'requested_url' => $_SERVER['REQUEST_URI'] ?? '',
            'redirect_url' => $redirectUrl
        ]);
        header('Location: ' . $redirectUrl);
        exit;
    }
}

function requireRole($role, $redirectUrl = 'index.php') {
    requireLogin();
    if (!Security::hasPermission($role)) {
        Security::logSecurityEvent('insufficient_permissions', [
            'required_role' => $role,
            'user_role' => getUserRole(),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        header('Location: ' . $redirectUrl);
        exit;
    }
}

function requireCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!Security::validateCSRFToken($token)) {
            Security::logSecurityEvent('csrf_token_mismatch', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'form_action' => $_SERVER['REQUEST_URI'] ?? ''
            ], 'warning');
            die('CSRF token mismatch. Please refresh and try again.');
        }
    }
}

// Cart functions
function getCartItems() {
    return $_SESSION['cart'] ?? [];
}

function addToCart($productId, $quantity = 1) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] += $quantity;
    } else {
        $_SESSION['cart'][$productId] = $quantity;
    }
}

function removeFromCart($productId) {
    unset($_SESSION['cart'][$productId]);
}

function getCartTotal($pdo) {
    $cart = getCartItems();
    if (empty($cart)) return 0;
    
    $productIds = array_keys($cart);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    $stmt = $pdo->prepare("SELECT id, price FROM products WHERE id IN ($placeholders)");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $total = 0;
    foreach ($cart as $productId => $quantity) {
        if (isset($products[$productId])) {
            $total += $products[$productId] * $quantity;
        }
    }
    
    return $total;
}

function getCartCount() {
    return array_sum(getCartItems());
}
?>
