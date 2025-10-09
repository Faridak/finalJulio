<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/HomepageController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Initialize database
$config = require_once __DIR__ . '/../config/database.php';
$database = new Database($config);

// Initialize controller
$controller = new HomepageController($database);

// Route handling
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? $_GET['path'] : '';
$requestBody = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            if ($path === 'banners') {
                $controller->getBanners();
            } elseif ($path === 'featured-products') {
                $controller->getFeaturedProducts();
            } elseif ($path === '') {
                $controller->getHomepageData();
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;
            
        case 'POST':
            if ($path === 'banners') {
                $controller->createBanner($requestBody);
            } elseif ($path === 'layout') {
                $controller->updateLayout($requestBody);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;
            
        case 'PUT':
            if (preg_match('/^banners\/(\d+)$/', $path, $matches)) {
                $bannerId = $matches[1];
                $controller->updateBanner($bannerId, $requestBody);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;
            
        case 'DELETE':
            if (preg_match('/^banners\/(\d+)$/', $path, $matches)) {
                $bannerId = $matches[1];
                $controller->deleteBanner($bannerId);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}