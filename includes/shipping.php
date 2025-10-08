<?php
// Mock shipping cost calculation (in real app: integrate with FedEx/UPS API)
function calculateShipping($zip, $weight) {
    // Real app: $api = new FedExAPI(); $cost = $api->getRate($zip, $weight);
    return $weight > 5 ? 12.99 : 5.99;
}

// Mock payment processing (real app: Stripe/PayPal SDK)
function processPayment($amount, $method) {
    // Real app: $stripe->charges->create(...);
    return [
        'status' => 'success',
        'transaction_id' => 'txn_' . uniqid(),
        'message' => 'Payment processed via ' . $method
    ];
}
?>
