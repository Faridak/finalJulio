<?php
/**
 * Tax Management API
 * 
 * This API provides endpoints for advanced tax management functionality including:
 * - Tax calculations
 * - Tax exemptions
 * - Reverse charge VAT
 * - Tax audit trails
 * - Tax reporting
 */

require_once '../../../config/database.php';
require_once '../../../config/db-connection-pool.php';
require_once '../../../classes/TaxManager.php';

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
    $taxManager = new TaxManager($pdo);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'calculate_tax':
        calculateTax($taxManager, $pdo);
        break;
        
    case 'add_tax_exemption':
        addTaxExemption($taxManager, $pdo);
        break;
        
    case 'add_reverse_charge_rule':
        addReverseChargeRule($taxManager, $pdo);
        break;
        
    case 'get_tax_audit_trail':
        getTaxAuditTrail($taxManager, $pdo);
        break;
        
    case 'get_tax_report':
        getTaxReport($taxManager, $pdo);
        break;
        
    case 'get_exemption_report':
        getExemptionReport($taxManager, $pdo);
        break;
        
    case 'get_customer_exemption':
        getCustomerExemption($taxManager, $pdo);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Calculate tax for an order
 */
function calculateTax($taxManager, $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $customerId = $data['customer_id'] ?? 0;
    $countryId = $data['country_id'] ?? 0;
    $stateId = $data['state_id'] ?? 0;
    $productCategory = $data['product_category'] ?? '';
    $amount = $data['amount'] ?? 0;
    $transactionId = $data['transaction_id'] ?? 0;
    $transactionType = $data['transaction_type'] ?? 'order';
    
    if (!$customerId || !$countryId || !$amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }
    
    try {
        // Calculate tax
        $taxCalculation = $taxManager->calculateTax($customerId, $countryId, $stateId, $productCategory, $amount);
        
        // Add customer and location info to the calculation for logging
        $taxCalculation['customer_id'] = $customerId;
        $taxCalculation['country_id'] = $countryId;
        $taxCalculation['state_id'] = $stateId;
        $taxCalculation['product_category'] = $productCategory;
        
        // Log to audit trail
        $auditTrailId = $taxManager->logTaxCalculation($taxCalculation, $transactionId, $transactionType, $_SESSION['user_id'] ?? null);
        
        // Update the calculation with audit trail ID
        $taxCalculation['audit_trail_id'] = $auditTrailId;
        
        echo json_encode([
            'success' => true,
            'data' => $taxCalculation,
            'message' => 'Tax calculated successfully'
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to calculate tax: ' . $e->getMessage()]);
    }
}

/**
 * Add tax exemption for a customer
 */
function addTaxExemption($taxManager, $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $customerId = $data['customer_id'] ?? 0;
    $exemptionType = $data['exemption_type'] ?? '';
    $certificateNumber = $data['certificate_number'] ?? '';
    $exemptionRate = $data['exemption_rate'] ?? 0;
    $effectiveDate = $data['effective_date'] ?? date('Y-m-d');
    $expiryDate = $data['expiry_date'] ?? null;
    $notes = $data['notes'] ?? '';
    
    if (!$customerId || !$exemptionType) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }
    
    try {
        $exemptionId = $taxManager->addTaxExemption(
            $customerId, 
            $exemptionType, 
            $certificateNumber, 
            $exemptionRate, 
            $effectiveDate, 
            $expiryDate, 
            $notes
        );
        
        echo json_encode([
            'success' => true,
            'exemption_id' => $exemptionId,
            'message' => 'Tax exemption added successfully'
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add tax exemption: ' . $e->getMessage()]);
    }
}

/**
 * Add reverse charge VAT rule
 */
function addReverseChargeRule($taxManager, $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $sellerCountryId = $data['seller_country_id'] ?? 0;
    $buyerCountryId = $data['buyer_country_id'] ?? 0;
    $productCategory = $data['product_category'] ?? null;
    $effectiveDate = $data['effective_date'] ?? date('Y-m-d');
    $expiryDate = $data['expiry_date'] ?? null;
    
    if (!$sellerCountryId || !$buyerCountryId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }
    
    try {
        $ruleId = $taxManager->addReverseChargeVATRule(
            $sellerCountryId, 
            $buyerCountryId, 
            $productCategory, 
            $effectiveDate, 
            $expiryDate
        );
        
        echo json_encode([
            'success' => true,
            'rule_id' => $ruleId,
            'message' => 'Reverse charge VAT rule added successfully'
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add reverse charge VAT rule: ' . $e->getMessage()]);
    }
}

/**
 * Get tax audit trail for a transaction
 */
function getTaxAuditTrail($taxManager, $pdo) {
    $transactionId = $_GET['transaction_id'] ?? 0;
    $transactionType = $_GET['transaction_type'] ?? 'order';
    
    if (!$transactionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
        return;
    }
    
    try {
        $auditTrail = $taxManager->getTaxAuditTrail($transactionId, $transactionType);
        
        echo json_encode([
            'success' => true,
            'data' => $auditTrail
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve tax audit trail: ' . $e->getMessage()]);
    }
}

/**
 * Get tax report for a date range
 */
function getTaxReport($taxManager, $pdo) {
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    
    try {
        $report = $taxManager->getTaxReport($startDate, $endDate);
        
        echo json_encode([
            'success' => true,
            'data' => $report,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to generate tax report: ' . $e->getMessage()]);
    }
}

/**
 * Get exemption report for a date range
 */
function getExemptionReport($taxManager, $pdo) {
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    
    try {
        $report = $taxManager->getExemptionReport($startDate, $endDate);
        
        echo json_encode([
            'success' => true,
            'data' => $report,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to generate exemption report: ' . $e->getMessage()]);
    }
}

/**
 * Get customer tax exemption
 */
function getCustomerExemption($taxManager, $pdo) {
    $customerId = $_GET['customer_id'] ?? 0;
    
    if (!$customerId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
        return;
    }
    
    try {
        $exemption = $taxManager->getCustomerExemption($customerId);
        
        echo json_encode([
            'success' => true,
            'data' => $exemption
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve customer exemption: ' . $e->getMessage()]);
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