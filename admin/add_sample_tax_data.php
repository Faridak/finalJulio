<?php
/**
 * Add Sample Tax Data
 * 
 * This script adds sample data for testing the enhanced tax management features
 */

require_once '../config/database.php';
require_once '../config/db-connection-pool.php';
require_once '../classes/TaxManager.php';

try {
    $pdo = getOptimizedDBConnection();
    $taxManager = new TaxManager($pdo);
    
    // Add sample tax exemptions
    echo "Adding sample tax exemptions...\n";
    
    // Add B2B exemption for customer ID 2 (John Smith - merchant)
    $exemptionId1 = $taxManager->addTaxExemption(
        2, // customer_id
        'b2b', // exemption_type
        'B2B-EXEMPT-001', // certificate_number
        0.00, // exemption_rate
        date('Y-m-d'), // effective_date
        date('Y-m-d', strtotime('+1 year')), // expiry_date
        'B2B customer exemption' // notes
    );
    echo "Added B2B exemption with ID: $exemptionId1\n";
    
    // Add non-profit exemption for customer ID 3 (Sarah Johnson - merchant)
    $exemptionId2 = $taxManager->addTaxExemption(
        3, // customer_id
        'non_profit', // exemption_type
        'NONPROFIT-EXEMPT-001', // certificate_number
        0.00, // exemption_rate
        date('Y-m-d'), // effective_date
        date('Y-m-d', strtotime('+1 year')), // expiry_date
        'Non-profit organization exemption' // notes
    );
    echo "Added non-profit exemption with ID: $exemptionId2\n";
    
    // Add reverse charge VAT rules
    echo "Adding sample reverse charge VAT rules...\n";
    
    // EU B2B transaction rule (Germany buyer, USA seller)
    $reverseChargeId1 = $taxManager->addReverseChargeVATRule(
        1, // seller_country_id (USA)
        5, // buyer_country_id (Germany)
        null, // product_category (applies to all)
        date('Y-m-d'), // effective_date
        date('Y-m-d', strtotime('+1 year')) // expiry_date
    );
    echo "Added reverse charge VAT rule with ID: $reverseChargeId1\n";
    
    // EU B2B transaction rule (France buyer, USA seller)
    $reverseChargeId2 = $taxManager->addReverseChargeVATRule(
        1, // seller_country_id (USA)
        6, // buyer_country_id (France)
        null, // product_category (applies to all)
        date('Y-m-d'), // effective_date
        date('Y-m-d', strtotime('+1 year')) // expiry_date
    );
    echo "Added reverse charge VAT rule with ID: $reverseChargeId2\n";
    
    echo "Sample tax data added successfully!\n";
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>