<?php
/**
 * VentDepot API Router
 * Routes API requests to appropriate handlers
 */

// Set error reporting for API
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in API responses

// Include the main API class
require_once 'index.php';

try {
    // Get the request URI and remove the base path
    $requestUri = $_SERVER['REQUEST_URI'];
    $basePath = '/finalJulio/api/';
    
    // Remove base path and query string
    $request = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));
    
    // Create API tables if they don't exist
    createAPITables($pdo);
    
    // Initialize and process API
    $api = new VentDepotAPI($pdo, $request);
    $api->processAPI();
    
} catch (Exception $e) {
    // Log the error
    error_log("API Error: " . $e->getMessage());
    
    // Return generic error response
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'An unexpected error occurred'
    ]);
}
?>