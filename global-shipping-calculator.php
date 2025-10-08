<?php
require_once 'config/database.php';
require_once 'classes/GlobalShippingCalculator.php';

// This is an AJAX endpoint for calculating global shipping rates
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    $destinationAddress = $input['destination_address'] ?? [];
    $options = $input['options'] ?? [];
    
    // Validate required fields
    if (empty($cartItems)) {
        throw new Exception('Cart items are required');
    }
    
    if (empty($destinationAddress['country_code'])) {
        throw new Exception('Destination country is required');
    }
    
    $globalShipping = new GlobalShippingCalculator($pdo);
    
    // Calculate global shipping rates
    $result = $globalShipping->calculateGlobalShippingRates(
        $cartItems, 
        $destinationAddress, 
        $options
    );
    
    if (!$result['success']) {
        throw new Exception($result['error'] ?? 'Failed to calculate shipping rates');
    }
    
    // Get country information
    $stmt = $pdo->prepare("
        SELECT name, currency_code, currency_symbol, tax_rate, timezone
        FROM countries 
        WHERE code = ?
    ");
    $stmt->execute([$destinationAddress['country_code']]);
    $countryInfo = $stmt->fetch();
    
    // Calculate taxes if requested
    $taxInfo = null;
    if ($options['calculate_taxes'] ?? false) {
        $orderTotal = array_sum(array_map(function($item) {
            return $item['price'] * $item['quantity'];
        }, $cartItems));
        
        $taxInfo = $globalShipping->calculateTaxes(
            $orderTotal, 
            $destinationAddress['country_code'], 
            $destinationAddress['state_code'] ?? null
        );
    }
    
    // Get currency conversion if needed
    $currencyRates = null;
    if ($options['target_currency'] && $options['target_currency'] !== 'USD') {
        $stmt = $pdo->prepare("
            SELECT rate FROM currency_rates 
            WHERE from_currency = 'USD' AND to_currency = ?
        ");
        $stmt->execute([$options['target_currency']]);
        $rate = $stmt->fetchColumn();
        
        if ($rate) {
            $currencyRates = [
                'from' => 'USD',
                'to' => $options['target_currency'],
                'rate' => $rate
            ];
            
            // Convert all prices
            foreach ($result['rates'] as &$rateOption) {
                $rateOption['total_cost_converted'] = round($rateOption['total_cost'] * $rate, 2);
                $rateOption['base_cost_converted'] = round($rateOption['base_cost'] * $rate, 2);
                $rateOption['currency'] = $options['target_currency'];
            }
        }
    }
    
    // Add additional information
    $response = [
        'success' => true,
        'shipping_rates' => $result['rates'],
        'package_details' => $result['package_details'],
        'distance_km' => $result['distance_km'],
        'destination_coordinates' => $result['destination_coordinates'],
        'country_info' => $countryInfo,
        'tax_info' => $taxInfo,
        'currency_rates' => $currencyRates,
        'calculation_time' => date('Y-m-d H:i:s'),
        'base_location' => [
            'city' => 'Los Angeles',
            'state' => 'California',
            'country' => 'United States',
            'latitude' => 34.0522,
            'longitude' => -118.2437
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
