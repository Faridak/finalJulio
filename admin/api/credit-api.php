<?php
// API endpoints for credit management
require_once '../../../config/database.php';
require_once '../../../config/db-connection-pool.php';
require_once '../../../classes/CreditManager.php';

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
    $creditManager = new CreditManager($pdo);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_customer_credit_limit':
        getCustomerCreditLimit($creditManager);
        break;
        
    case 'check_credit_limit':
        checkCreditLimit($creditManager);
        break;
        
    case 'set_customer_credit_limit':
        setCustomerCreditLimit($creditManager, $pdo);
        break;
        
    case 'submit_credit_application':
        submitCreditApplication($creditManager, $pdo);
        break;
        
    case 'process_credit_application':
        processCreditApplication($creditManager, $pdo);
        break;
        
    case 'get_credit_applications':
        getCreditApplications($creditManager);
        break;
        
    case 'add_to_collections':
        addToCollections($creditManager, $pdo);
        break;
        
    case 'update_collection_status':
        updateCollectionStatus($creditManager, $pdo);
        break;
        
    case 'get_customer_collections':
        getCustomerCollections($creditManager);
        break;
        
    case 'generate_aging_report':
        generateAgingReport($creditManager, $pdo);
        break;
        
    case 'get_aging_report_history':
        getAgingReportHistory($creditManager);
        break;
        
    case 'calculate_credit_risk_score':
        calculateCreditRiskScore($creditManager, $pdo);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// API Functions

function getCustomerCreditLimit($creditManager) {
    $customerId = $_GET['customer_id'] ?? 0;
    
    if (!$customerId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
        return;
    }
    
    $creditLimit = $creditManager->getCustomerCreditLimit($customerId);
    
    if ($creditLimit) {
        echo json_encode(['success' => true, 'data' => $creditLimit]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No credit limit found for customer']);
    }
}

function checkCreditLimit($creditManager) {
    $customerId = $_GET['customer_id'] ?? 0;
    $amount = $_GET['amount'] ?? 0;
    
    if (!$customerId || !$amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Customer ID and amount are required']);
        return;
    }
    
    $result = $creditManager->checkCreditLimit($customerId, $amount);
    echo json_encode($result);
}

function setCustomerCreditLimit($creditManager, $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $customerId = $data['customer_id'] ?? 0;
    $creditLimit = $data['credit_limit'] ?? 0;
    $creditScore = $data['credit_score'] ?? 0;
    $riskLevel = $data['risk_level'] ?? 'low';
    
    if (!$customerId || !$creditLimit) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Customer ID and credit limit are required']);
        return;
    }
    
    $result = $creditManager->setCustomerCreditLimit($customerId, $creditLimit, $creditScore, $riskLevel);
    echo json_encode($result);
}

function submitCreditApplication($creditManager, $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $customerId = $data['customer_id'] ?? 0;
    $requestedLimit = $data['requested_limit'] ?? 0;
    $supportingDocuments = $data['supporting_documents'] ?? [];
    
    if (!$customerId || !$requestedLimit) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Customer ID and requested limit are required']);
        return;
    }
    
    $result = $creditManager->submitCreditApplication($customerId, $requestedLimit, $supportingDocuments);
    echo json_encode($result);
}

function processCreditApplication($creditManager, $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $applicationId = $data['application_id'] ?? 0;
    $decision = $data['decision'] ?? '';
    $approvedLimit = $data['approved_limit'] ?? 0;
    $reviewerId = $data['reviewer_id'] ?? null;
    $notes = $data['notes'] ?? '';
    
    if (!$applicationId || !$decision) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID and decision are required']);
        return;
    }
    
    $result = $creditManager->processCreditApplication($applicationId, $decision, $approvedLimit, $reviewerId, $notes);
    echo json_encode($result);
}

function getCreditApplications($creditManager) {
    $status = $_GET['status'] ?? null;
    $applications = $creditManager->getCreditApplications($status);
    echo json_encode(['success' => true, 'data' => $applications]);
}

function addToCollections($creditManager, $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $customerId = $data['customer_id'] ?? 0;
    $invoiceId = $data['invoice_id'] ?? 0;
    $invoiceNumber = $data['invoice_number'] ?? '';
    $amount = $data['amount'] ?? 0;
    $dueDate = $data['due_date'] ?? '';
    
    if (!$customerId || !$invoiceId || !$invoiceNumber || !$amount || !$dueDate) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    $result = $creditManager->addToCollections($customerId, $invoiceId, $invoiceNumber, $amount, $dueDate);
    echo json_encode($result);
}

function updateCollectionStatus($creditManager, $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $collectionId = $data['collection_id'] ?? 0;
    $status = $data['status'] ?? '';
    $notes = $data['notes'] ?? '';
    $resolutionAmount = $data['resolution_amount'] ?? 0;
    $resolutionType = $data['resolution_type'] ?? null;
    
    if (!$collectionId || !$status) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Collection ID and status are required']);
        return;
    }
    
    $result = $creditManager->updateCollectionStatus($collectionId, $status, $notes, $resolutionAmount, $resolutionType);
    echo json_encode($result);
}

function getCustomerCollections($creditManager) {
    $customerId = $_GET['customer_id'] ?? 0;
    
    if (!$customerId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
        return;
    }
    
    $collections = $creditManager->getCustomerCollections($customerId);
    echo json_encode(['success' => true, 'data' => $collections]);
}

function generateAgingReport($creditManager, $pdo) {
    $customerId = $_GET['customer_id'] ?? 0;
    
    if (!$customerId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
        return;
    }
    
    $result = $creditManager->generateAgingReport($customerId);
    echo json_encode($result);
}

function getAgingReportHistory($creditManager) {
    $customerId = $_GET['customer_id'] ?? 0;
    $limit = $_GET['limit'] ?? 10;
    
    if (!$customerId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
        return;
    }
    
    $history = $creditManager->getAgingReportHistory($customerId, $limit);
    echo json_encode(['success' => true, 'data' => $history]);
}

function calculateCreditRiskScore($creditManager, $pdo) {
    $customerId = $_GET['customer_id'] ?? 0;
    
    if (!$customerId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
        return;
    }
    
    $result = $creditManager->calculateCreditRiskScore($customerId);
    echo json_encode($result);
}
?>