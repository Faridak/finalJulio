<?php
/**
 * Currency API
 * Handles currency conversion, exchange rate updates, and FX calculations
 */

require_once '../../../config/database.php';
require_once '../../../classes/CurrencyConverter.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $currencyConverter = new CurrencyConverter($pdo);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'convert':
        convertCurrency($currencyConverter);
        break;
        
    case 'get_rate':
        getExchangeRate($currencyConverter);
        break;
        
    case 'update_rates':
        updateExchangeRates($currencyConverter);
        break;
        
    case 'calculate_fx':
        calculateFXGainLoss($currencyConverter, $pdo);
        break;
        
    case 'get_currencies':
        getAvailableCurrencies($currencyConverter);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Convert currency amount
 */
function convertCurrency($currencyConverter) {
    $fromCurrency = $_GET['from'] ?? '';
    $toCurrency = $_GET['to'] ?? '';
    $amount = $_GET['amount'] ?? 0;
    
    if (!$fromCurrency || !$toCurrency || !$amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        return;
    }
    
    $convertedAmount = $currencyConverter->convert($amount, $fromCurrency, $toCurrency);
    
    if ($convertedAmount === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Currency conversion failed']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'amount' => $amount,
        'from_currency' => $fromCurrency,
        'to_currency' => $toCurrency,
        'converted_amount' => $convertedAmount,
        'rate' => $currencyConverter->getExchangeRate($fromCurrency, $toCurrency)
    ]);
}

/**
 * Get exchange rate between two currencies
 */
function getExchangeRate($currencyConverter) {
    $fromCurrency = $_GET['from'] ?? '';
    $toCurrency = $_GET['to'] ?? '';
    
    if (!$fromCurrency || !$toCurrency) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        return;
    }
    
    $rate = $currencyConverter->getExchangeRate($fromCurrency, $toCurrency);
    
    if ($rate === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Exchange rate not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'from_currency' => $fromCurrency,
        'to_currency' => $toCurrency,
        'rate' => $rate
    ]);
}

/**
 * Update exchange rates from external source
 */
function updateExchangeRates($currencyConverter) {
    $success = $currencyConverter->updateExchangeRates();
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Exchange rates updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update exchange rates'
        ]);
    }
}

/**
 * Calculate FX gain/loss for an order
 */
function calculateFXGainLoss($currencyConverter, $pdo) {
    $orderId = $_GET['order_id'] ?? 0;
    
    if (!$orderId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        return;
    }
    
    // Check if order exists
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }
    
    $fxGainLoss = $currencyConverter->calculateFXGainLoss($orderId);
    
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'fx_gain_loss' => $fxGainLoss
    ]);
}

/**
 * Get list of available currencies
 */
function getAvailableCurrencies($currencyConverter) {
    $currencies = $currencyConverter->getAvailableCurrencies();
    
    echo json_encode([
        'success' => true,
        'currencies' => $currencies
    ]);
}
?>