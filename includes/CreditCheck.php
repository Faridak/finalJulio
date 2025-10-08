<?php
/**
 * Credit Check Utility for Order Processing
 * Integrates credit management with order processing and accounts receivable
 */

require_once 'security.php';
require_once __DIR__ . '/../classes/CreditManager.php';

class CreditCheck {
    private $pdo;
    private $creditManager;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->creditManager = new CreditManager($pdo);
    }
    
    /**
     * Check if customer has sufficient credit for an order
     * @param int $userId Customer user ID
     * @param float $orderAmount Order total amount
     * @return array Result with approval status and details
     */
    public function checkCreditForOrder($userId, $orderAmount) {
        try {
            // Check if customer has a credit limit set
            $creditInfo = $this->creditManager->getCustomerCreditLimit($userId);
            
            if (!$creditInfo) {
                // No credit limit set, approve order
                return [
                    'approved' => true,
                    'message' => 'No credit limit set for customer',
                    'credit_applied' => false
                ];
            }
            
            // Check if credit account is active
            if ($creditInfo['credit_status'] !== 'active') {
                return [
                    'approved' => false,
                    'message' => 'Credit account is not active',
                    'credit_applied' => true
                ];
            }
            
            // Check available credit
            $availableCredit = $creditInfo['available_credit'];
            
            if ($orderAmount <= $availableCredit) {
                return [
                    'approved' => true,
                    'available_credit' => $availableCredit,
                    'message' => 'Sufficient credit available',
                    'credit_applied' => true,
                    'credit_limit' => $creditInfo['credit_limit'],
                    'used_credit' => $creditInfo['used_credit']
                ];
            } else {
                return [
                    'approved' => false,
                    'available_credit' => $availableCredit,
                    'message' => 'Insufficient credit available',
                    'credit_applied' => true,
                    'credit_limit' => $creditInfo['credit_limit'],
                    'used_credit' => $creditInfo['used_credit']
                ];
            }
        } catch (Exception $e) {
            // Log error but don't block the order
            error_log("Credit check error for user $userId: " . $e->getMessage());
            return [
                'approved' => true,
                'message' => 'Credit check temporarily unavailable',
                'credit_applied' => false
            ];
        }
    }
    
    /**
     * Reserve credit for an order
     * @param int $userId Customer user ID
     * @param float $orderAmount Order total amount
     * @return array Result with reservation status
     */
    public function reserveCreditForOrder($userId, $orderAmount) {
        try {
            $result = $this->creditManager->reserveCredit($userId, $orderAmount);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Credit reserved successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['message']
                ];
            }
        } catch (Exception $e) {
            error_log("Credit reservation error for user $userId: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to reserve credit'
            ];
        }
    }
    
    /**
     * Set customer credit limit
     * @param int $userId Customer user ID
     * @param float $creditLimit Credit limit amount
     * @param int $creditScore Credit score
     * @param string $riskLevel Risk level
     * @return array Result with set status
     */
    public function setCustomerCreditLimit($userId, $creditLimit, $creditScore = 0, $riskLevel = 'low') {
        try {
            $result = $this->creditManager->setCustomerCreditLimit($userId, $creditLimit, $creditScore, $riskLevel);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Credit limit set successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['message']
                ];
            }
        } catch (Exception $e) {
            error_log("Credit limit set error for user $userId: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to set credit limit'
            ];
        }
    }
    
    /**
     * Release reserved credit (if order is cancelled)
     * @param int $userId Customer user ID
     * @param float $orderAmount Order total amount
     * @return array Result with release status
     */
    public function releaseCreditForOrder($userId, $orderAmount) {
        try {
            $result = $this->creditManager->releaseCredit($userId, $orderAmount);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Credit released successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['message']
                ];
            }
        } catch (Exception $e) {
            error_log("Credit release error for user $userId: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to release credit'
            ];
        }
    }
    
    /**
     * Create accounts receivable entry with credit information
     * @param int $orderId Order ID
     * @param int $userId Customer user ID
     * @param float $orderAmount Order total amount
     * @param bool $creditApplied Whether credit was applied
     * @param float $creditApprovedAmount Amount approved through credit
     * @return array Result with creation status
     */
    public function createAccountsReceivableWithCredit($orderId, $userId, $orderAmount, $creditApplied = false, $creditApprovedAmount = 0) {
        try {
            // Get user/customer information
            $stmt = $this->pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception("User not found");
            }
            
            // Create invoice number
            $invoiceNumber = "INV-" . date('Y') . "-" . str_pad($orderId, 6, '0', STR_PAD_LEFT);
            
            // Determine if credit was applied
            $creditLimitApplied = $creditApplied ? 1 : 0;
            
            // Insert accounts receivable entry
            $stmt = $this->pdo->prepare("
                INSERT INTO accounts_receivable 
                (customer_name, invoice_number, invoice_date, due_date, amount, description, credit_limit_applied, credit_approved_amount)
                VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user['email'],
                $invoiceNumber,
                $orderAmount,
                "Order #$orderId",
                $creditLimitApplied,
                $creditApprovedAmount
            ]);
            
            $receivableId = $this->pdo->lastInsertId();
            
            // Update order with receivable ID if needed
            // First check if the column exists
            try {
                $stmt = $this->pdo->prepare("UPDATE orders SET accounts_receivable_id = ? WHERE id = ?");
                $stmt->execute([$receivableId, $orderId]);
            } catch (Exception $e) {
                // Column doesn't exist, that's okay
                error_log("accounts_receivable_id column not found in orders table: " . $e->getMessage());
            }
            
            return [
                'success' => true,
                'receivable_id' => $receivableId,
                'invoice_number' => $invoiceNumber,
                'message' => 'Accounts receivable entry created successfully'
            ];
        } catch (Exception $e) {
            error_log("Accounts receivable creation error for order $orderId: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create accounts receivable entry'
            ];
        }
    }
    
    /**
     * Update collection status for overdue accounts
     * @param int $receivableId Accounts receivable ID
     * @return array Result with update status
     */
    public function updateCollectionStatusForOverdue($receivableId) {
        try {
            // Get receivable details
            $stmt = $this->pdo->prepare("
                SELECT id, due_date, amount, received_amount 
                FROM accounts_receivable 
                WHERE id = ?
            ");
            $stmt->execute([$receivableId]);
            $receivable = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$receivable) {
                return [
                    'success' => false,
                    'message' => 'Accounts receivable entry not found'
                ];
            }
            
            // Calculate days overdue
            $dueDate = new DateTime($receivable['due_date']);
            $now = new DateTime();
            $interval = $now->diff($dueDate);
            $daysOverdue = ($dueDate < $now) ? $interval->days : 0;
            
            // Determine collection status based on days overdue
            $collectionStatus = $this->creditManager->mapDaysToCollectionStatus($daysOverdue);
            
            // Update accounts receivable
            $stmt = $this->pdo->prepare("
                UPDATE accounts_receivable 
                SET days_overdue = ?, collection_status = ? 
                WHERE id = ?
            ");
            $stmt->execute([$daysOverdue, $collectionStatus, $receivableId]);
            
            // If significantly overdue, add to collections table
            if ($daysOverdue > 30) {
                $outstandingAmount = $receivable['amount'] - $receivable['received_amount'];
                
                if ($outstandingAmount > 0) {
                    // Get user ID from customer name (email)
                    $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$receivable['customer_name']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $userId = $user ? $user['id'] : null;
                    
                    if ($userId) {
                        // Add to collections
                        $this->creditManager->addToCollections(
                            $userId,
                            $receivableId,
                            $receivable['invoice_number'],
                            $outstandingAmount,
                            $receivable['due_date']
                        );
                    }
                }
            }
            
            return [
                'success' => true,
                'days_overdue' => $daysOverdue,
                'collection_status' => $collectionStatus,
                'message' => 'Collection status updated successfully'
            ];
        } catch (Exception $e) {
            error_log("Collection status update error for receivable $receivableId: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update collection status'
            ];
        }
    }
}
?>