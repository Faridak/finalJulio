<?php
require_once 'config/database.php';
require_once 'classes/ShippingCalculator.php';
require_once 'classes/GeographicalManager.php';

// This is an AJAX endpoint for calculating shipping rates
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $cartItems = $input['cart_items'] ?? [];
    $destinationCountry = $input['destination_country'] ?? '';
    $destinationState = $input['destination_state'] ?? '';
    $destinationCity = $input['destination_city'] ?? '';
    
    if (empty($cartItems) || empty($destinationCountry)) {
        throw new Exception('Missing required parameters');
    }
    
    $shippingCalc = new ShippingCalculator($pdo);
    $geoManager = new GeographicalManager($pdo);
    
    // Calculate shipping rates
    $shippingRates = $shippingCalc->calculateShippingRates(
        $cartItems, 
        $destinationCountry, 
        $destinationState, 
        $destinationCity
    );
    
    // Calculate taxes
    $orderTotal = array_sum(array_map(function($item) {
        return $item['price'] * $item['quantity'];
    }, $cartItems));
    
    $taxInfo = $shippingCalc->calculateTaxes($orderTotal, $destinationCountry, $destinationState);
    
    // Get country info for currency
    $countryInfo = $geoManager->getCountryInfo($destinationCountry);
    
    echo json_encode([
        'success' => true,
        'shipping_rates' => $shippingRates,
        'tax_info' => $taxInfo,
        'country_info' => $countryInfo,
        'order_total' => $orderTotal
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
