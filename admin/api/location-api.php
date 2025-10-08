<?php
/**
 * Location API
 * 
 * This API provides endpoints for location-related data needed for tax management
 */

require_once '../../../config/database.php';
require_once '../../../config/db-connection-pool.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isLoggedIn() || getUserRole() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Use connection pooling for better performance
    $pdo = getOptimizedDBConnection();
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_states':
        getStates($pdo);
        break;
        
    case 'get_countries':
        getCountries($pdo);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Get states/provinces for a country
 */
function getStates($pdo) {
    $countryId = $_GET['country_id'] ?? 0;
    
    if (!$countryId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Country ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, name 
            FROM states 
            WHERE country_id = ? 
            ORDER BY name
        ");
        $stmt->execute([$countryId]);
        $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $states
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve states: ' . $e->getMessage()]);
    }
}

/**
 * Get all countries
 */
function getCountries($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, name, code 
            FROM countries 
            ORDER BY name
        ");
        $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $countries
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve countries: ' . $e->getMessage()]);
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    // This is a placeholder - implement your actual authentication check
    session_start();
    return isset($_SESSION['user_id']);
}

/**
 * Get user role
 */
function getUserRole() {
    // This is a placeholder - implement your actual role check
    return $_SESSION['user_role'] ?? 'customer';
}
?>