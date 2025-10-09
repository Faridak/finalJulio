<?php

/**
 * VentDepot - E-commerce Marketplace Platform
 * 
 * Main entry point for the application
 */

// Load composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Define application constants
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('CONFIG_PATH', ROOT_PATH . '/config');

// Initialize the application
$app = new VentDepot\Core\Application();

// Handle the request
try {
    $response = $app->handle($_REQUEST);
    echo $response;
} catch (Exception $e) {
    // Log the error
    error_log($e->getMessage());
    
    // Show a generic error message in production
    if ($_ENV['APP_ENV'] === 'production') {
        http_response_code(500);
        echo "An error occurred. Please try again later.";
    } else {
        // Show detailed error in development
        http_response_code(500);
        echo "<h1>Error</h1>";
        echo "<p><strong>" . htmlspecialchars($e->getMessage()) . "</strong></p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}