<?php
// Credit Management Class
class CreditManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get customer credit limit
     */
    public function getCustomerCreditLimit($customerId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM customer_credit_limits WHERE customer_id = ?");
            $stmt->execute([$customerId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting customer credit limit: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if customer has sufficient credit for an order
     */
    public function checkCreditLimit($customerId, $amount) {
        try {
            $creditInfo = $this->getCustomerCreditLimit($customerId);
            
            if (!$creditInfo) {
                // If no credit limit exists, approve the order
                return ['approved' => true, 'message' => 'No credit limit set for customer'];
            }
            
            if ($creditInfo['credit_status'] !== 'active') {
                return ['approved' => false, 'message' => 'Credit account is not active'];
            }
            
            $availableCredit = $creditInfo['available_credit'];
            
            if ($amount <= $availableCredit) {
                return ['approved' => true, 'available_credit' => $availableCredit, 'message' => 'Sufficient credit available'];
            } else {
                return ['approved' => false, 'available_credit' => $availableCredit, 'message' => 'Insufficient credit available'];
            }
        } catch (Exception $e) {
            error_log("Error checking credit limit: " . $e->getMessage());
            return ['approved' => false, 'message' => 'Error checking credit limit'];
        }
    }
    
    /**
     * Reserve credit for an order
     */
    public function reserveCredit($customerId, $amount) {
        try {
            // Get current credit info
            $stmt = $this->pdo->prepare("SELECT id, used_credit, available_credit FROM customer_credit_limits WHERE customer_id = ?");
            $stmt->execute([$customerId]);
            $creditInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$creditInfo) {
                return ['success' => false, 'message' => 'No credit limit found for customer'];
            }
            
            // Check if sufficient credit is available
            if ($amount > $creditInfo['available_credit']) {
                return ['success' => false, 'message' => 'Insufficient credit available'];
            }
            
            // Update credit usage
            $newUsedCredit = $creditInfo['used_credit'] + $amount;
            $newAvailableCredit = $creditInfo['available_credit'] - $amount;
            
            $stmt = $this->pdo->prepare("UPDATE customer_credit_limits SET used_credit = ?, available_credit = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newUsedCredit, $newAvailableCredit, $creditInfo['id']]);
            
            return ['success' => true, 'message' => 'Credit reserved successfully'];
        } catch (Exception $e) {
            error_log("Error reserving credit: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error reserving credit'];
        }
    }
    
    /**
     * Release reserved credit
     */
    public function releaseCredit($customerId, $amount) {
        try {
            // Get current credit info
            $stmt = $this->pdo->prepare("SELECT id, used_credit, available_credit FROM customer_credit_limits WHERE customer_id = ?");
            $stmt->execute([$customerId]);
            $creditInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$creditInfo) {
                return ['success' => false, 'message' => 'No credit limit found for customer'];
            }
            
            // Update credit usage
            $newUsedCredit = max(0, $creditInfo['used_credit'] - $amount);
            $newAvailableCredit = $creditInfo['available_credit'] + $amount;
            
            $stmt = $this->pdo->prepare("UPDATE customer_credit_limits SET used_credit = ?, available_credit = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newUsedCredit, $newAvailableCredit, $creditInfo['id']]);
            
            return ['success' => true, 'message' => 'Credit released successfully'];
        } catch (Exception $e) {
            error_log("Error releasing credit: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error releasing credit'];
        }
    }
    
    /**
     * Create or update customer credit limit
     */
    public function setCustomerCreditLimit($customerId, $creditLimit, $creditScore = 0, $riskLevel = 'low') {
        try {
            // Check if credit limit already exists
            $stmt = $this->pdo->prepare("SELECT id FROM customer_credit_limits WHERE customer_id = ?");
            $stmt->execute([$customerId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing credit limit
                $stmt = $this->pdo->prepare("UPDATE customer_credit_limits SET credit_limit = ?, available_credit = credit_limit - used_credit, credit_score = ?, risk_level = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$creditLimit, $creditScore, $riskLevel, $existing['id']]);
            } else {
                // Create new credit limit
                $availableCredit = $creditLimit; // Initially all credit is available
                $stmt = $this->pdo->prepare("INSERT INTO customer_credit_limits (customer_id, credit_limit, available_credit, credit_score, risk_level) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$customerId, $creditLimit, $availableCredit, $creditScore, $riskLevel]);
            }
            
            // Update user's credit score and risk level
            $stmt = $this->pdo->prepare("UPDATE users SET credit_score = ?, risk_level = ? WHERE id = ?");
            $stmt->execute([$creditScore, $riskLevel, $customerId]);
            
            return ['success' => true, 'message' => 'Credit limit set successfully'];
        } catch (Exception $e) {
            error_log("Error setting credit limit: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error setting credit limit'];
        }
    }
    
    /**
     * Submit credit application
     */
    public function submitCreditApplication($customerId, $requestedLimit, $supportingDocuments = []) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO credit_applications (customer_id, application_date, requested_credit_limit, supporting_documents) VALUES (?, CURDATE(), ?, ?)");
            $stmt->execute([$customerId, $requestedLimit, json_encode($supportingDocuments)]);
            return ['success' => true, 'application_id' => $this->pdo->lastInsertId(), 'message' => 'Credit application submitted successfully'];
        } catch (Exception $e) {
            error_log("Error submitting credit application: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error submitting credit application'];
        }
    }
    
    /**
     * Process credit application
     */
    public function processCreditApplication($applicationId, $decision, $approvedLimit = 0, $reviewerId = null, $notes = '') {
        try {
            // Get application details
            $stmt = $this->pdo->prepare("SELECT customer_id, requested_credit_limit FROM credit_applications WHERE id = ?");
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$application) {
                return ['success' => false, 'message' => 'Credit application not found'];
            }
            
            $status = ($decision === 'approved') ? 'approved' : (($decision === 'rejected') ? 'rejected' : 'under_review');
            
            // Update application
            $stmt = $this->pdo->prepare("UPDATE credit_applications SET application_status = ?, approved_credit_limit = ?, reviewer_id = ?, review_date = CURDATE(), review_notes = ? WHERE id = ?");
            $stmt->execute([$status, $approvedLimit, $reviewerId, $notes, $applicationId]);
            
            // If approved, update customer credit limit
            if ($decision === 'approved') {
                $this->setCustomerCreditLimit($application['customer_id'], $approvedLimit);
            }
            
            return ['success' => true, 'message' => 'Credit application processed successfully'];
        } catch (Exception $e) {
            error_log("Error processing credit application: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error processing credit application'];
        }
    }
    
    /**
     * Add account to collections
     */
    public function addToCollections($customerId, $invoiceId, $invoiceNumber, $amount, $dueDate) {
        try {
            // Calculate days overdue
            $dueDateTime = new DateTime($dueDate);
            $now = new DateTime();
            $interval = $now->diff($dueDateTime);
            $daysOverdue = ($dueDateTime < $now) ? $interval->days : 0;
            
            // Determine collection status based on days overdue
            if ($daysOverdue <= 0) {
                $collectionStatus = 'new';
            } elseif ($daysOverdue <= 30) {
                $collectionStatus = 'in_progress';
            } elseif ($daysOverdue <= 60) {
                $collectionStatus = 'escalated';
            } else {
                $collectionStatus = 'escalated';
            }
            
            $stmt = $this->pdo->prepare("INSERT INTO collections (customer_id, invoice_id, invoice_number, original_amount, outstanding_amount, due_date, days_overdue, collection_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customerId, $invoiceId, $invoiceNumber, $amount, $amount, $dueDate, $daysOverdue, $collectionStatus]);
            
            // Update accounts receivable collection status
            $stmt = $this->pdo->prepare("UPDATE accounts_receivable SET days_overdue = ?, collection_status = ? WHERE id = ?");
            $stmt->execute([$daysOverdue, $this->mapDaysToCollectionStatus($daysOverdue), $invoiceId]);
            
            return ['success' => true, 'collection_id' => $this->pdo->lastInsertId(), 'message' => 'Account added to collections successfully'];
        } catch (Exception $e) {
            error_log("Error adding to collections: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error adding to collections'];
        }
    }
    
    /**
     * Update collection status
     */
    public function updateCollectionStatus($collectionId, $status, $notes = '', $resolutionAmount = 0, $resolutionType = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Update collection record
            $stmt = $this->pdo->prepare("UPDATE collections SET collection_status = ?, resolution_date = CURDATE(), resolution_amount = ?, resolution_type = ?, collection_notes = CONCAT(IFNULL(collection_notes, ''), '\n', ?) WHERE id = ?");
            $stmt->execute([$status, $resolutionAmount, $resolutionType, $notes, $collectionId]);
            
            // If resolved, update accounts receivable
            if (in_array($status, ['resolved', 'written_off'])) {
                $stmt = $this->pdo->prepare("SELECT invoice_id FROM collections WHERE id = ?");
                $stmt->execute([$collectionId]);
                $collection = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($collection) {
                    $stmt = $this->pdo->prepare("UPDATE accounts_receivable SET status = ?, received_amount = received_amount + ? WHERE id = ?");
                    $arStatus = ($resolutionType === 'paid' || $resolutionType === 'partial_payment') ? 'paid' : 'cancelled';
                    $stmt->execute([$arStatus, $resolutionAmount, $collection['invoice_id']]);
                }
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Collection status updated successfully'];
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("Error updating collection status: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating collection status'];
        }
    }
    
    /**
     * Generate aging report for a customer
     */
    public function generateAgingReport($customerId) {
        try {
            // Get all receivables for the customer
            $stmt = $this->pdo->prepare("SELECT id, amount, received_amount, due_date FROM accounts_receivable WHERE customer_id = ? AND status = 'pending'");
            $stmt->execute([$customerId]);
            $receivables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $agingData = [
                'current' => 0,
                'days_1_30' => 0,
                'days_31_60' => 0,
                'days_61_90' => 0,
                'days_91_120' => 0,
                'days_over_120' => 0,
                'total_outstanding' => 0
            ];
            
            $now = new DateTime();
            
            foreach ($receivables as $receivable) {
                $dueDate = new DateTime($receivable['due_date']);
                $interval = $now->diff($dueDate);
                $daysOverdue = ($dueDate < $now) ? $interval->days : 0;
                
                $outstanding = $receivable['amount'] - $receivable['received_amount'];
                
                if ($daysOverdue <= 0) {
                    $agingData['current'] += $outstanding;
                } elseif ($daysOverdue <= 30) {
                    $agingData['days_1_30'] += $outstanding;
                } elseif ($daysOverdue <= 60) {
                    $agingData['days_31_60'] += $outstanding;
                } elseif ($daysOverdue <= 90) {
                    $agingData['days_61_90'] += $outstanding;
                } elseif ($daysOverdue <= 120) {
                    $agingData['days_91_120'] += $outstanding;
                } else {
                    $agingData['days_over_120'] += $outstanding;
                }
                
                $agingData['total_outstanding'] += $outstanding;
            }
            
            // Save aging report
            $stmt = $this->pdo->prepare("INSERT INTO aging_reports (customer_id, report_date, current_amount, days_1_30, days_31_60, days_61_90, days_91_120, days_over_120, total_outstanding) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $customerId,
                $agingData['current'],
                $agingData['days_1_30'],
                $agingData['days_31_60'],
                $agingData['days_61_90'],
                $agingData['days_91_120'],
                $agingData['days_over_120'],
                $agingData['total_outstanding']
            ]);
            
            return ['success' => true, 'data' => $agingData, 'report_id' => $this->pdo->lastInsertId()];
        } catch (Exception $e) {
            error_log("Error generating aging report: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error generating aging report'];
        }
    }
    
    /**
     * Calculate credit risk score for a customer
     */
    public function calculateCreditRiskScore($customerId) {
        try {
            // This is a simplified risk scoring algorithm
            // In a real implementation, this would be much more complex
            
            // Payment history (40% weight)
            $paymentHistoryScore = $this->calculatePaymentHistoryScore($customerId);
            
            // Credit utilization (25% weight)
            $creditUtilizationScore = $this->calculateCreditUtilizationScore($customerId);
            
            // Length of credit history (15% weight)
            $lengthOfCreditScore = $this->calculateLengthOfCreditScore($customerId);
            
            // New credit inquiries (10% weight)
            $newCreditScore = $this->calculateNewCreditScore($customerId);
            
            // Credit mix (10% weight)
            $creditMixScore = $this->calculateCreditMixScore($customerId);
            
            // Calculate overall score (weighted average)
            $overallScore = round(
                ($paymentHistoryScore * 0.4) +
                ($creditUtilizationScore * 0.25) +
                ($lengthOfCreditScore * 0.15) +
                ($newCreditScore * 0.1) +
                ($creditMixScore * 0.1)
            );
            
            // Determine risk category
            if ($overallScore >= 80) {
                $riskCategory = 'excellent';
            } elseif ($overallScore >= 70) {
                $riskCategory = 'good';
            } elseif ($overallScore >= 60) {
                $riskCategory = 'fair';
            } elseif ($overallScore >= 50) {
                $riskCategory = 'poor';
            } else {
                $riskCategory = 'very_poor';
            }
            
            // Save risk score
            $stmt = $this->pdo->prepare("INSERT INTO credit_risk_scores (customer_id, score_date, payment_history_score, credit_utilization_score, length_of_credit_score, new_credit_score, credit_mix_score, overall_score, risk_category) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $customerId,
                $paymentHistoryScore,
                $creditUtilizationScore,
                $lengthOfCreditScore,
                $newCreditScore,
                $creditMixScore,
                $overallScore,
                $riskCategory
            ]);
            
            return [
                'success' => true,
                'score' => $overallScore,
                'category' => $riskCategory,
                'factors' => [
                    'payment_history' => $paymentHistoryScore,
                    'credit_utilization' => $creditUtilizationScore,
                    'length_of_credit' => $lengthOfCreditScore,
                    'new_credit' => $newCreditScore,
                    'credit_mix' => $creditMixScore
                ]
            ];
        } catch (Exception $e) {
            error_log("Error calculating credit risk score: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error calculating credit risk score'];
        }
    }
    
    // Helper methods for risk scoring
    private function calculatePaymentHistoryScore($customerId) {
        // Simplified implementation - in reality, this would analyze actual payment history
        return rand(70, 95);
    }
    
    private function calculateCreditUtilizationScore($customerId) {
        // Simplified implementation - in reality, this would analyze actual credit usage
        return rand(60, 90);
    }
    
    private function calculateLengthOfCreditScore($customerId) {
        // Simplified implementation - in reality, this would analyze account age
        return rand(50, 85);
    }
    
    private function calculateNewCreditScore($customerId) {
        // Simplified implementation - in reality, this would analyze recent credit inquiries
        return rand(65, 85);
    }
    
    private function calculateCreditMixScore($customerId) {
        // Simplified implementation - in reality, this would analyze types of credit accounts
        return rand(55, 80);
    }
    
    private function mapDaysToCollectionStatus($daysOverdue) {
        if ($daysOverdue <= 0) {
            return 'not_due';
        } elseif ($daysOverdue <= 30) {
            return 'overdue_30';
        } elseif ($daysOverdue <= 60) {
            return 'overdue_60';
        } elseif ($daysOverdue <= 90) {
            return 'overdue_90';
        } elseif ($daysOverdue <= 120) {
            return 'overdue_120';
        } else {
            return 'written_off';
        }
    }
    
    /**
     * Get all credit applications
     */
    public function getCreditApplications($status = null) {
        try {
            if ($status) {
                $stmt = $this->pdo->prepare("SELECT * FROM credit_applications WHERE application_status = ? ORDER BY application_date DESC");
                $stmt->execute([$status]);
            } else {
                $stmt = $this->pdo->prepare("SELECT * FROM credit_applications ORDER BY application_date DESC");
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting credit applications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get collections for a customer
     */
    public function getCustomerCollections($customerId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM collections WHERE customer_id = ? ORDER BY due_date ASC");
            $stmt->execute([$customerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting customer collections: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get aging report history for a customer
     */
    public function getAgingReportHistory($customerId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM aging_reports WHERE customer_id = ? ORDER BY report_date DESC LIMIT ?");
            $stmt->execute([$customerId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting aging report history: " . $e->getMessage());
            return [];
        }
    }
}
?>