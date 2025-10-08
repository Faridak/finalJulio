<?php
/**
 * TaxManager Class
 * 
 * This class handles all advanced tax management functionality including:
 * - Tax calculations based on jurisdictions
 * - Tax exemptions for B2B and non-profit customers
 * - Reverse charge VAT for EU B2B transactions
 * - Tax audit trail logging
 * - Tax reporting
 */

class TaxManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Calculate tax for an order based on customer location and product category
     * 
     * @param int $customerId
     * @param int $countryId
     * @param int $stateId
     * @param string $productCategory
     * @param float $amount
     * @return array Tax calculation details
     */
    public function calculateTax($customerId, $countryId, $stateId, $productCategory, $amount) {
        // Check if customer has tax exemption
        $exemption = $this->getCustomerExemption($customerId);
        if ($exemption && $exemption['is_active']) {
            return [
                'tax_amount' => 0.00,
                'tax_rate' => 0.00,
                'exemption_applied' => true,
                'exemption_id' => $exemption['id'],
                'tax_type' => 'exempt',
                'notes' => 'Tax exemption applied: ' . $exemption['exemption_type']
            ];
        }
        
        // Check for reverse charge VAT (EU B2B transactions)
        $reverseCharge = $this->checkReverseChargeVAT($customerId, $countryId);
        if ($reverseCharge) {
            return [
                'tax_amount' => 0.00,
                'tax_rate' => 0.00,
                'reverse_charge_applied' => true,
                'tax_type' => 'reverse_charge',
                'notes' => 'Reverse charge VAT applied'
            ];
        }
        
        // Get applicable tax rule
        $taxRule = $this->getApplicableTaxRule($countryId, $stateId, $productCategory);
        
        if ($taxRule) {
            $taxAmount = $amount * ($taxRule['tax_rate'] / 100);
            return [
                'tax_amount' => round($taxAmount, 2),
                'tax_rate' => $taxRule['tax_rate'],
                'tax_type' => $taxRule['tax_type'],
                'tax_rule_id' => $taxRule['id'],
                'exemption_applied' => false,
                'reverse_charge_applied' => false,
                'notes' => 'Standard tax calculation'
            ];
        }
        
        // No applicable tax rule found
        return [
            'tax_amount' => 0.00,
            'tax_rate' => 0.00,
            'tax_type' => 'none',
            'exemption_applied' => false,
            'reverse_charge_applied' => false,
            'notes' => 'No applicable tax rule found'
        ];
    }
    
    /**
     * Get customer tax exemption if exists
     * 
     * @param int $customerId
     * @return array|null Exemption data or null
     */
    public function getCustomerExemption($customerId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM tax_exemptions 
            WHERE customer_id = ? 
            AND is_active = 1 
            AND effective_date <= CURDATE() 
            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if reverse charge VAT applies for this transaction
     * 
     * @param int $customerId
     * @param int $countryId
     * @return bool
     */
    public function checkReverseChargeVAT($customerId, $countryId) {
        // Get customer profile to check if they're a business
        $stmt = $this->pdo->prepare("
            SELECT up.* FROM user_profiles up 
            JOIN users u ON up.user_id = u.id 
            WHERE u.id = ? AND u.role = 'merchant'
        ");
        $stmt->execute([$customerId]);
        $customerProfile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If customer is a merchant, check for reverse charge rules
        if ($customerProfile) {
            // Get seller country (assuming it's stored somewhere, for now using a default)
            $sellerCountryId = 1; // Default to USA, should be configurable
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM reverse_charge_vat 
                WHERE seller_country_id = ? 
                AND buyer_country_id = ? 
                AND is_active = 1 
                AND effective_date <= CURDATE() 
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
            ");
            $stmt->execute([$sellerCountryId, $countryId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
        }
        
        return false;
    }
    
    /**
     * Get applicable tax rule based on location and product category
     * 
     * @param int $countryId
     * @param int $stateId
     * @param string $productCategory
     * @return array|null Tax rule data or null
     */
    public function getApplicableTaxRule($countryId, $stateId, $productCategory) {
        // First try to find a specific rule for this product category
        $stmt = $this->pdo->prepare("
            SELECT * FROM tax_rules 
            WHERE country_id = ? 
            AND state_id = ? 
            AND product_category = ? 
            AND is_active = 1 
            AND (effective_date IS NULL OR effective_date <= CURDATE()) 
            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$countryId, $stateId, $productCategory]);
        $taxRule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($taxRule) {
            return $taxRule;
        }
        
        // If no specific rule, try to find a general rule for the location
        $stmt = $this->pdo->prepare("
            SELECT * FROM tax_rules 
            WHERE country_id = ? 
            AND state_id = ? 
            AND product_category IS NULL 
            AND is_active = 1 
            AND (effective_date IS NULL OR effective_date <= CURDATE()) 
            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$countryId, $stateId]);
        $taxRule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($taxRule) {
            return $taxRule;
        }
        
        // If still no rule, try country-level rule
        $stmt = $this->pdo->prepare("
            SELECT * FROM tax_rules 
            WHERE country_id = ? 
            AND state_id IS NULL 
            AND is_active = 1 
            AND (effective_date IS NULL OR effective_date <= CURDATE()) 
            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$countryId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Log tax calculation to audit trail
     * 
     * @param array $taxCalculation
     * @param int $transactionId
     * @param string $transactionType
     * @param int $calculatedBy
     * @return int Audit trail ID
     */
    public function logTaxCalculation($taxCalculation, $transactionId, $transactionType, $calculatedBy = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO tax_audit_trail (
                transaction_id, transaction_type, tax_rule_id, customer_id, country_id, 
                state_id, product_category, tax_type, tax_rate_applied, tax_amount_calculated, 
                exemption_applied, exemption_id, reverse_charge_applied, calculated_by, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $transactionId,
            $transactionType,
            $taxCalculation['tax_rule_id'] ?? null,
            $taxCalculation['customer_id'] ?? null,
            $taxCalculation['country_id'] ?? null,
            $taxCalculation['state_id'] ?? null,
            $taxCalculation['product_category'] ?? null,
            $taxCalculation['tax_type'] ?? 'none',
            $taxCalculation['tax_rate'] ?? 0.00,
            $taxCalculation['tax_amount'] ?? 0.00,
            $taxCalculation['exemption_applied'] ?? false,
            $taxCalculation['exemption_id'] ?? null,
            $taxCalculation['reverse_charge_applied'] ?? false,
            $calculatedBy,
            $taxCalculation['notes'] ?? ''
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Add tax exemption for a customer
     * 
     * @param int $customerId
     * @param string $exemptionType
     * @param string $certificateNumber
     * @param float $exemptionRate
     * @param string $effectiveDate
     * @param string $expiryDate
     * @param string $notes
     * @return int Exemption ID
     */
    public function addTaxExemption($customerId, $exemptionType, $certificateNumber, $exemptionRate, $effectiveDate, $expiryDate = null, $notes = '') {
        $stmt = $this->pdo->prepare("
            INSERT INTO tax_exemptions (
                customer_id, exemption_type, exemption_certificate_number, 
                exemption_rate, effective_date, expiry_date, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $customerId,
            $exemptionType,
            $certificateNumber,
            $exemptionRate,
            $effectiveDate,
            $expiryDate,
            $notes
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Add reverse charge VAT rule
     * 
     * @param int $sellerCountryId
     * @param int $buyerCountryId
     * @param string $productCategory
     * @param string $effectiveDate
     * @param string $expiryDate
     * @return int Rule ID
     */
    public function addReverseChargeVATRule($sellerCountryId, $buyerCountryId, $productCategory = null, $effectiveDate = null, $expiryDate = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO reverse_charge_vat (
                seller_country_id, buyer_country_id, product_category, 
                effective_date, expiry_date
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $sellerCountryId,
            $buyerCountryId,
            $productCategory,
            $effectiveDate ?? date('Y-m-d'),
            $expiryDate
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Get tax audit trail for a transaction
     * 
     * @param int $transactionId
     * @param string $transactionType
     * @return array Audit trail records
     */
    public function getTaxAuditTrail($transactionId, $transactionType) {
        $stmt = $this->pdo->prepare("
            SELECT tat.*, 
                   tr.tax_rate as rule_tax_rate, 
                   tr.tax_type as rule_tax_type,
                   te.exemption_type,
                   te.exemption_certificate_number,
                   c.name as country_name,
                   s.name as state_name
            FROM tax_audit_trail tat
            LEFT JOIN tax_rules tr ON tat.tax_rule_id = tr.id
            LEFT JOIN tax_exemptions te ON tat.exemption_id = te.id
            LEFT JOIN countries c ON tat.country_id = c.id
            LEFT JOIN states s ON tat.state_id = s.id
            WHERE tat.transaction_id = ? AND tat.transaction_type = ?
            ORDER BY tat.calculated_at DESC
        ");
        
        $stmt->execute([$transactionId, $transactionType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get tax report for a date range
     * 
     * @param string $startDate
     * @param string $endDate
     * @return array Tax report data
     */
    public function getTaxReport($startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT 
                tat.tax_type,
                COUNT(*) as transaction_count,
                SUM(tat.tax_amount_calculated) as total_tax_collected,
                AVG(tat.tax_rate_applied) as average_tax_rate
            FROM tax_audit_trail tat
            WHERE DATE(tat.calculated_at) BETWEEN ? AND ?
            GROUP BY tat.tax_type
            ORDER BY total_tax_collected DESC
        ");
        
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get exemption report
     * 
     * @param string $startDate
     * @param string $endDate
     * @return array Exemption report data
     */
    public function getExemptionReport($startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT 
                te.exemption_type,
                COUNT(*) as exemption_count,
                COUNT(DISTINCT tat.customer_id) as unique_customers
            FROM tax_exemptions te
            LEFT JOIN tax_audit_trail tat ON te.id = tat.exemption_id
            WHERE te.effective_date BETWEEN ? AND ?
            GROUP BY te.exemption_type
            ORDER BY exemption_count DESC
        ");
        
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>