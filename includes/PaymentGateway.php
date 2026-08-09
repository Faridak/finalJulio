<?php
/**
 * Payment Gateway Integration System
 * Handles Stripe and PayPal payment processing for marketplace transactions
 */

require_once 'security.php';
require_once __DIR__ . '/CreditCheck.php';

class PaymentGateway {
    private $pdo;
    private $stripeSecretKey;
    private $stripePublishableKey;
    private $paypalClientId;
    private $paypalClientSecret;
    private $paypalMode; // 'sandbox' or 'live'
    private $paypalAccounts = []; // region-routed accounts: US, MX

    /**
     * Which PayPal account serves this order? Mexico ships -> PayPal Mexico;
     * everyone else -> PayPal USA.
     * @return array{region:string, client_id:string, secret:string, currency:string}
     */
    public function resolvePayPalAccount($orderId) {
        $stmt = $this->pdo->prepare("SELECT shipping_country FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $country = strtoupper((string) $stmt->fetchColumn());
        $region = ($country === 'MX') ? 'MX' : 'US';
        return ['region' => $region] + $this->paypalAccounts[$region];
    }
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->creditCheck = new CreditCheck($pdo);
        $this->loadConfiguration();
    }
    
    /**
     * Load payment gateway configuration
     */
    private function loadConfiguration() {
        // These should be loaded from environment variables or secure config
        $this->stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? 'sk_test_dummy';
        $this->stripePublishableKey = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? 'pk_test_dummy';
        // Two PayPal business accounts, routed by the order's shipping country:
        //   MX  -> PayPal Mexico   (PAYPAL_MX_CLIENT_ID / PAYPAL_MX_SECRET)
        //   else-> PayPal USA      (PAYPAL_US_CLIENT_ID / PAYPAL_US_SECRET)
        // Legacy single-account vars (PAYPAL_CLIENT_ID/SECRET) fall back to US.
        // Placeholder/empty ids -> 'sb' demo mode for that account.
        $clean = function ($v) { return ($v === '' || $v === null || strpos((string)$v, 'your_') === 0) ? 'sb' : $v; };
        $this->paypalAccounts = [
            'US' => [
                'client_id' => $clean($_ENV['PAYPAL_US_CLIENT_ID'] ?? ($_ENV['PAYPAL_CLIENT_ID'] ?? 'sb')),
                'secret'    => $_ENV['PAYPAL_US_SECRET'] ?? ($_ENV['PAYPAL_CLIENT_SECRET'] ?? ($_ENV['PAYPAL_SECRET'] ?? 'secret')),
                'currency'  => 'USD',
            ],
            'MX' => [
                'client_id' => $clean($_ENV['PAYPAL_MX_CLIENT_ID'] ?? 'sb'),
                'secret'    => $_ENV['PAYPAL_MX_SECRET'] ?? 'secret',
                'currency'  => $_ENV['PAYPAL_MX_CURRENCY'] ?? 'USD', // set MXN when pricing in pesos
            ],
        ];
        // Back-compat: default active account (US) for code paths without order context.
        $this->paypalClientId = $this->paypalAccounts['US']['client_id'];
        $this->paypalClientSecret = $this->paypalAccounts['US']['secret'];
        $this->paypalMode = $_ENV['PAYPAL_MODE'] ?? 'sandbox';
    }
    
    /**
     * Process payment through selected gateway
     */
    public function processPayment($orderId, $paymentMethod, $paymentData) {
        try {
            $this->pdo->beginTransaction();
            
            // Get order details
            $order = $this->getOrderDetails($orderId);
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            // Validate order status
            if ($order['status'] !== 'pending_payment' && $order['status'] !== 'pending') {
                throw new Exception("Order is not in a payable state (Status: " . $order['status'] . ")");
            }
            
            $result = null;
            
            switch ($paymentMethod) {
                case 'stripe':
                    $result = $this->processStripePayment($order, $paymentData);
                    break;
                    
                case 'paypal':
                    $result = $this->processPayPalPayment($order, $paymentData);
                    break;
                    
                case 'mock':
                    $result = $this->processMockPayment($order, $paymentData);
                    break;
                    
                default:
                    throw new Exception("Unsupported payment method");
            }
            
            if ($result['success']) {
                $transactionId = 0;
                
                // Check if this is a mock payment
                $isMock = false;
                if (isset($result['raw_response'])) {
                    $raw = json_decode($result['raw_response'], true);
                    if (isset($raw['mock']) && $raw['mock'] === true) {
                        $isMock = true;
                        $transactionId = 123456; // Dummy Transaction ID
                    }
                }

                if (!$isMock) {
                    // Record payment transaction
                    $transactionId = $this->recordTransaction($orderId, $paymentMethod, $result);
                    
                    // Update order status
                    $this->updateOrderStatus($orderId, 'paid', $transactionId);
                    
                    // Process commission splits
                    $this->processCommissionSplits($orderId, $result['net_amount']);
                    
                    // Release credit if it was reserved
                    $this->releaseCreditIfReserved($orderId, $order['user_id'], $order['total']);
                    
                    // Create accounts receivable entry with credit information
                    $this->createAccountsReceivableEntry($orderId, $order);

                    // Connect inventory, accounting (ledger) and shipping to this payment
                    $this->runFulfilmentOrchestration($orderId, $order['user_id'] ?? null);

                    $this->pdo->commit();
                } else {
                    // Update order status even for mock, so confirmation page shows "Paid"
                    // We assume 'orders' table exists if getOrderDetails succeeded.
                    try {
                        $this->updateOrderStatus($orderId, 'paid', $transactionId);
                        // Connect inventory, accounting (ledger) and shipping to this payment
                        $this->runFulfilmentOrchestration($orderId, $order['user_id'] ?? null);
                    } catch (Exception $e) {
                        // Ignore DB errors in mock mode
                    }

                    if ($this->pdo->inTransaction()) {
                        $this->pdo->commit();
                    }
                }
                
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'gateway_reference' => $result['gateway_reference'],
                    'message' => 'Payment processed successfully'
                ];
            } else {
                throw new Exception($result['error_message'] ?? 'Payment processing failed');
            }
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            // Log payment failure
            Security::logSecurityEvent('payment_failure', [
                'order_id' => $orderId,
                'payment_method' => $paymentMethod,
                'error' => $e->getMessage()
            ], 'error');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process Stripe payment
     */
    private function processStripePayment($order, $paymentData) {
        try {
            // Initialize Stripe (this is a simplified version - you'd need the actual Stripe PHP SDK)
            $stripe = [
                'secret_key' => $this->stripeSecretKey,
                'publishable_key' => $this->stripePublishableKey
            ];
            
            // Calculate amounts (in cents for Stripe)
            $totalAmount = round($order['total'] * 100);
            $platformFee = round($order['total'] * 0.029 * 100); // 2.9% platform fee
            
            // Create payment intent or charge
            $paymentIntent = $this->createStripePaymentIntent([
                'amount' => $totalAmount,
                'currency' => 'usd',
                'payment_method' => $paymentData['payment_method_id'],
                'confirm' => true,
                'metadata' => [
                    'order_id' => $order['id'],
                    'customer_id' => $order['user_id'],
                    'merchant_id' => $order['merchant_id']
                ],
                'application_fee_amount' => $platformFee
            ]);
            
            if ($paymentIntent['status'] === 'succeeded') {
                return [
                    'success' => true,
                    'gateway_reference' => $paymentIntent['id'],
                    'net_amount' => ($totalAmount - $platformFee) / 100,
                    'platform_fee' => $platformFee / 100,
                    'gateway_fee' => 0, // Included in platform fee
                    'raw_response' => json_encode($paymentIntent)
                ];
            } else {
                return [
                    'success' => false,
                    'error_message' => 'Payment requires additional authentication or failed'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process PayPal payment
     */
    private function processPayPalPayment($order, $paymentData) {
        try {
            // Route to the correct regional account (MX vs US) for this order.
            $account = $this->resolvePayPalAccount($order['id']);
            $this->paypalClientId = $account['client_id'];
            $this->paypalClientSecret = $account['secret'];

            // MOCK: If using the 'sb' dummy ID, return success immediately (dev only)
            if ($this->paypalClientId === 'sb') {
                $platformFee = number_format($order['total'] * 0.029, 2, '.', '');
                $merchantAmount = number_format($order['total'] - $platformFee, 2, '.', '');
                return [
                    'success' => true,
                    'gateway_reference' => 'mock_pp_' . uniqid(),
                    'net_amount' => floatval($merchantAmount),
                    'platform_fee' => floatval($platformFee),
                    'gateway_fee' => 0,
                    'raw_response' => json_encode(['status' => 'COMPLETED', 'mock' => true])
                ];
            }

            // Real flow (Orders v2): the buyer APPROVED the order in the PayPal
            // popup; the client posts us the order id and WE capture it server-side.
            // Never trust a client-side capture claim.
            $paypalOrderId = trim($paymentData['paypal_order_id'] ?? '');
            if ($paypalOrderId === '' || !preg_match('/^[A-Z0-9\-]{5,64}$/i', $paypalOrderId)) {
                throw new Exception('Missing or invalid PayPal order id.');
            }

            $accessToken = $this->getPayPalAccessToken();
            if (!$accessToken) {
                throw new Exception('Failed to get PayPal access token');
            }

            // Capture the approved order.
            $capture = $this->payPalRequest('POST', "/v2/checkout/orders/{$paypalOrderId}/capture", null, $accessToken);

            // Already captured (double-submit / webhook race)? Fetch and verify instead.
            if (isset($capture['name']) && $capture['name'] === 'UNPROCESSABLE_ENTITY'
                && ($capture['details'][0]['issue'] ?? '') === 'ORDER_ALREADY_CAPTURED') {
                $capture = $this->payPalRequest('GET', "/v2/checkout/orders/{$paypalOrderId}", null, $accessToken);
            }

            if (!isset($capture['status']) || $capture['status'] !== 'COMPLETED') {
                $issue = $capture['details'][0]['issue'] ?? ($capture['name'] ?? 'capture failed');
                throw new Exception('PayPal capture not completed: ' . $issue);
            }

            // Verify the captured amount matches OUR order total (prevents a
            // buyer approving a cheaper order and replaying its id).
            $pu = $capture['purchase_units'][0] ?? [];
            $cap = $pu['payments']['captures'][0] ?? null;
            $paidValue = (float) ($cap['amount']['value'] ?? ($pu['amount']['value'] ?? 0));
            $paidCurrency = $cap['amount']['currency_code'] ?? ($pu['amount']['currency_code'] ?? '');
            if ($paidCurrency !== $account['currency'] || abs($paidValue - (float) $order['total']) > 0.01) {
                Security::logSecurityEvent('paypal_amount_mismatch', [
                    'order_id' => $order['id'], 'expected' => $order['total'],
                    'paid' => $paidValue, 'currency' => $paidCurrency, 'paypal_order' => $paypalOrderId,
                ], 'error');
                throw new Exception('PayPal amount mismatch — payment not accepted.');
            }

            $paypalFee = (float) ($cap['seller_receivable_breakdown']['paypal_fee']['value'] ?? 0);
            $platformFee = round($order['total'] * 0.029, 2);

            return [
                'success' => true,
                'gateway_reference' => $cap['id'] ?? $paypalOrderId,
                'net_amount' => round($order['total'] - $platformFee, 2),
                'platform_fee' => $platformFee,
                'gateway_fee' => $paypalFee,
                'raw_response' => json_encode($capture)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_message' => $e->getMessage()
            ];
        }
    }

    /**
     * Server-side: create a PayPal order for OUR order total, so the amount the
     * buyer approves is set by the server, not the browser.
     * @return array{id?:string,error?:string}
     */
    public function createPayPalOrderFor($orderId) {
        $order = $this->getOrderDetails($orderId);
        if (!$order) return ['error' => 'Order not found'];

        // Route to the regional account for this order (MX vs US).
        $account = $this->resolvePayPalAccount($orderId);
        $this->paypalClientId = $account['client_id'];
        $this->paypalClientSecret = $account['secret'];

        if ($this->paypalClientId === 'sb') {
            return ['id' => 'MOCK-' . strtoupper(uniqid()), 'region' => $account['region']];
        }

        $accessToken = $this->getPayPalAccessToken();
        if (!$accessToken) return ['error' => 'PayPal auth failed'];

        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'order_' . $orderId,
                'custom_id' => (string) $orderId,
                'amount' => ['currency_code' => $account['currency'], 'value' => number_format($order['total'], 2, '.', '')],
            ]],
        ];
        $res = $this->payPalRequest('POST', '/v2/checkout/orders', $body, $accessToken);
        if (!empty($res['id'])) return ['id' => $res['id'], 'region' => $account['region']];
        return ['error' => $res['message'] ?? 'Could not create PayPal order'];
    }

    /** Authenticated JSON request to the PayPal REST API. */
    private function payPalRequest($method, $path, $body, $accessToken) {
        $baseUrl = $this->paypalMode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        $curl = curl_init();
        $opts = [
            CURLOPT_URL => $baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: vd-' . md5($path . serialize($body)), // idempotency
            ],
        ];
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        curl_setopt_array($curl, $opts);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($response === false) return ['name' => 'CURL_ERROR', 'message' => $err];
        return json_decode($response, true) ?: [];
    }
    
    /**
     * Record payment transaction
     */
    private function recordTransaction($orderId, $paymentMethod, $result) {
        $stmt = $this->pdo->prepare("
            INSERT INTO payment_transactions (
                order_id, payment_method, gateway_reference, amount, 
                platform_fee, gateway_fee, net_amount, status, 
                raw_response, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())
        ");
        
        $stmt->execute([
            $orderId,
            $paymentMethod,
            $result['gateway_reference'],
            $result['net_amount'] + $result['platform_fee'],
            $result['platform_fee'],
            $result['gateway_fee'],
            $result['net_amount'],
            $result['raw_response']
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Process commission splits between platform and merchant
     */
    private function processCommissionSplits($orderId, $netAmount) {
        $order = $this->getOrderDetails($orderId);
        
        // Create commission record for merchant
        $stmt = $this->pdo->prepare("
            INSERT INTO merchant_commissions (
                order_id, merchant_id, gross_amount, commission_rate, 
                commission_amount, net_amount, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending_payout', NOW())
        ");
        
        $grossAmount = $order['total'];
        $commissionRate = 0.05; // 5% commission
        $commissionAmount = $grossAmount * $commissionRate;
        $merchantNetAmount = $grossAmount - $commissionAmount;
        
        $stmt->execute([
            $orderId,
            $order['merchant_id'],
            $grossAmount,
            $commissionRate,
            $commissionAmount,
            $merchantNetAmount
        ]);
    }
    
    /**
     * Get order details
     */
    private function getOrderDetails($orderId) {
        $stmt = $this->pdo->prepare("
            SELECT o.*, u.email as customer_email, m.email as merchant_email
            FROM orders o
            JOIN users u ON o.user_id = u.id
            JOIN users m ON o.merchant_id = m.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch();
    }
    
    /**
     * Update order status
     */
    private function updateOrderStatus($orderId, $status, $transactionId = null) {
        // 'paid' is a PAYMENT state, not a fulfilment state — orders.status has
        // no 'paid' member (writing it silently stored '' before this fix).
        if ($status === 'paid') {
            $stmt = $this->pdo->prepare("
                UPDATE orders
                SET payment_status = 'paid', status = 'processing',
                    payment_transaction_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([$transactionId, $orderId]);
        }
        $stmt = $this->pdo->prepare("
            UPDATE orders
            SET status = ?, payment_transaction_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$status, $transactionId, $orderId]);
    }
    
    /**
     * Create Stripe Payment Intent (simplified version)
     */
    private function createStripePaymentIntent($data) {
        // This is a simplified simulation - you'd use the actual Stripe PHP SDK
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.stripe.com/v1/payment_intents",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->stripeSecretKey,
                "Content-Type: application/x-www-form-urlencoded"
            ],
            CURLOPT_POSTFIELDS => http_build_query($data)
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true);
    }
    
    /**
     * Get PayPal access token
     */
    private function getPayPalAccessToken() {
        $baseUrl = $this->paypalMode === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $baseUrl . "/v1/oauth2/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Accept-Language: en_US"
            ],
            CURLOPT_USERPWD => $this->paypalClientId . ":" . $this->paypalClientSecret,
            CURLOPT_POSTFIELDS => "grant_type=client_credentials"
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    
    /**
     * Create PayPal order
     */
    private function createPayPalOrder($orderData, $accessToken) {
        $baseUrl = $this->paypalMode === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $baseUrl . "/v2/checkout/orders",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $accessToken
            ],
            CURLOPT_POSTFIELDS => json_encode($orderData)
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true);
    }
    
    /**
     * Process refund
     */
    public function processRefund($transactionId, $amount = null, $reason = '') {
        try {
            $transaction = $this->getTransaction($transactionId);
            
            if (!$transaction) {
                throw new Exception("Transaction not found");
            }
            
            $refundAmount = $amount ?? $transaction['amount'];
            
            switch ($transaction['payment_method']) {
                case 'stripe':
                    $result = $this->processStripeRefund($transaction, $refundAmount, $reason);
                    break;
                    
                case 'paypal':
                    $result = $this->processPayPalRefund($transaction, $refundAmount, $reason);
                    break;
                    
                default:
                    throw new Exception("Unsupported payment method for refund");
            }
            
            if ($result['success']) {
                // Record refund transaction
                $this->recordRefund($transactionId, $refundAmount, $result['refund_id'], $reason);
                
                return [
                    'success' => true,
                    'refund_id' => $result['refund_id'],
                    'amount' => $refundAmount
                ];
            } else {
                throw new Exception($result['error_message']);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get transaction details
     */
    private function getTransaction($transactionId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM payment_transactions WHERE id = ?
        ");
        $stmt->execute([$transactionId]);
        return $stmt->fetch();
    }
    
    /**
     * Record refund
     */
    private function recordRefund($transactionId, $amount, $refundId, $reason) {
        $stmt = $this->pdo->prepare("
            INSERT INTO payment_refunds (
                transaction_id, amount, gateway_refund_id, reason, 
                status, created_at
            ) VALUES (?, ?, ?, ?, 'completed', NOW())
        ");
        
        return $stmt->execute([$transactionId, $amount, $refundId, $reason]);
    }
    
    /**
     * Process Stripe refund
     */
    private function processStripeRefund($transaction, $amount, $reason) {
        // Simplified Stripe refund process
        $refundData = [
            'charge' => $transaction['gateway_reference'],
            'amount' => round($amount * 100), // Convert to cents
            'reason' => 'requested_by_customer'
        ];
        
        // This would use the actual Stripe API
        return [
            'success' => true,
            'refund_id' => 're_' . uniqid(),
            'amount' => $amount
        ];
    }
    
    /**
     * Process PayPal refund
     */
    private function processPayPalRefund($transaction, $amount, $reason) {
        // Simplified PayPal refund process
        return [
            'success' => true,
            'refund_id' => 'paypal_refund_' . uniqid(),
            'amount' => $amount
        ];
    }
    
    /**
     * Release credit if it was reserved for the order
     */
    private function releaseCreditIfReserved($orderId, $customerId, $orderAmount) {
        try {
            // Check if credit was applied to this order by checking accounts_receivable
            $stmt = $this->pdo->prepare("SELECT credit_limit_applied FROM accounts_receivable WHERE id = (SELECT accounts_receivable_id FROM orders WHERE id = ?)");
            $stmt->execute([$orderId]);
            $receivable = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($receivable && $receivable['credit_limit_applied']) {
                // Release the reserved credit
                $this->creditCheck->releaseCreditForOrder($customerId, $orderAmount);
            }
        } catch (Exception $e) {
            // Log error but don't fail the payment
            error_log("Failed to release credit for order $orderId: " . $e->getMessage());
        }
    }
    
    /**
     * Create accounts receivable entry with credit information
     */
    private function createAccountsReceivableEntry($orderId, $order) {
        try {
            // Check if credit was applied
            $stmt = $this->pdo->prepare("SELECT id FROM customer_credit_limits WHERE customer_id = ?");
            $stmt->execute([$order['user_id']]);
            $creditLimit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $creditApplied = !empty($creditLimit);
            $creditApprovedAmount = $creditApplied ? $order['total'] : 0;
            
            // Create accounts receivable entry
            $this->creditCheck->createAccountsReceivableWithCredit(
                $orderId, 
                $order['user_id'], 
                $order['total'], 
                $creditApplied, 
                $creditApprovedAmount
            );
        } catch (Exception $e) {
            // Log error but don't fail the payment
            error_log("Failed to create accounts receivable entry for order $orderId: " . $e->getMessage());
        }
    }
    
    /**
     * Run the fulfilment orchestrator: connects the completed payment to the
     * inventory ledger, the accounting general ledger, and shipping.
     * Failures are logged and never abort the payment (the orchestrator is
     * idempotent and flag-guarded, so a reconciliation run can finish later).
     */
    private function runFulfilmentOrchestration($orderId, $userId = null) {
        try {
            require_once __DIR__ . '/../services/OrderFulfillmentOrchestrator.php';
            $orchestrator = new OrderFulfillmentOrchestrator($this->pdo);
            $orchestrator->onPaymentCompleted($orderId, $userId);
        } catch (Exception $e) {
            error_log("Fulfilment orchestration failed for order $orderId: " . $e->getMessage());
        }
    }

    /**
     * Get payment methods configuration for frontend
     */
    public function getPaymentMethodsConfig($orderId = null) {
        // With an order in context, expose the REGION-ROUTED PayPal account so
        // the JS SDK loads the right client-id (MX order -> PayPal Mexico).
        if ($orderId) {
            $acct = $this->resolvePayPalAccount($orderId);
            $ppClientId = $acct['client_id'];
            $ppCurrency = $acct['currency'];
            $ppRegion = $acct['region'];
        } else {
            $ppClientId = $this->paypalAccounts['US']['client_id'];
            $ppCurrency = $this->paypalAccounts['US']['currency'];
            $ppRegion = 'US';
        }
        return [
            'stripe' => [
                'enabled' => !empty($this->stripePublishableKey),
                'publishable_key' => $this->stripePublishableKey
            ],
            'paypal' => [
                'enabled' => !empty($ppClientId),
                'client_id' => $ppClientId,
                'currency' => $ppCurrency,
                'region' => $ppRegion,
                'mode' => $this->paypalMode
            ],
            'mock' => [
                'enabled' => true // Always enabled for testing
            ]
        ];
    }
    
    /**
     * Process Mock payment
     */
    private function processMockPayment($order, $paymentData) {
        $cardNumber = preg_replace('/\D/', '', $paymentData['card_number'] ?? '');
        $expiry = $paymentData['expiry'] ?? '';
        
        // Validate specific test card
        if ($cardNumber === '1231231231231233') {
            return [
                'success' => true,
                'gateway_reference' => 'mock_txn_' . uniqid(),
                'net_amount' => $order['total'], // In a real scenario, fees would be deducted
                'platform_fee' => 0,
                'gateway_fee' => 0,
                'raw_response' => json_encode(['status' => 'approved', 'mock' => true])
            ];
        } else {
            return [
                'success' => false,
                'error_message' => 'Invalid test card number. Use 1231231231231233'
            ];
        }
    }
}
?>