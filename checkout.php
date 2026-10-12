<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/PaymentGateway.php';
require_once 'includes/CreditCheck.php';
require_once 'includes/OrderProcessor.php';
// require_once 'includes/dummy_data.php'; // Removed dummy data

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// The "mock" payment method captures raw card data and must never be
// available in production (PCI). Only expose it outside production.
$allowMockPayment = env('APP_ENV', 'production') !== 'production';

// Get cart items or order ID
$orderId = intval($_GET['order_id'] ?? 0);
$cartItems = [];
$orderTotal = 0;
$shippingCost = 0;
$taxAmount = 0;

if ($orderId > 0) {
    // Load existing order
    $stmt = $pdo->prepare("
        SELECT o.*, oi.*, oi.price_at_purchase as price, p.name as product_name, p.image_url
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.id = ? AND o.user_id = ? AND o.status = 'pending'
    ");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $orderItems = $stmt->fetchAll();
    
    if (empty($orderItems)) {
        header('Location: cart.php');
        exit;
    }
    
    $orderTotal = $orderItems[0]['total'];
    $shippingCost = $orderItems[0]['shipping_cost'];
    $taxAmount = 0; // Tax column is missing in DB
    $cartItems = $orderItems;
} else {
    // Load cart items from DB
    if (empty($_SESSION['cart'])) {
        header('Location: cart.php');
        exit;
    }
    
    // Calculate totals from cart using EFFECTIVE prices (discounts/bulk/promos)
    // — must match what OrderProcessor charges when the order is created.
    require_once 'services/PricingService.php';
    $pricingSvc = new PricingService($pdo);
    $cartItems = [];
    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $ep = $pricingSvc->effectivePrice((int) $productId, (int) $quantity);
            $unit = round((float) ($ep['unit_effective'] ?? $product['price']), 2);
            $itemTotal = $unit * $quantity;
            $orderTotal += $itemTotal;
            // Map DB columns to cart structure
            $cartItems[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'price' => $unit,
                'quantity' => $quantity,
                'image_url' => !empty($product['image_url']) ? $product['image_url'] : 'assets/images/placeholder.jpg'
            ];
        }
    }
    
    // Shipping ESTIMATE for the summary (assumes Mexico until the address is
    // entered); the exact cost is recalculated from the real address + country
    // when the order is created (OrderProcessor).
    require_once 'services/ShippingCalculatorService.php';
    $estQuote = (new ShippingCalculatorService($pdo))->quote($cartItems, '', 'MX', $orderTotal);
    $shippingCost = (float) $estQuote['total'];
    
    // Tax Calculation
    $taxAmount = $orderTotal * 0.08; // 8% tax
    $orderTotal += $shippingCost + $taxAmount;
}

// Initialize payment gateway, credit check and order processor
$paymentGateway = new PaymentGateway($pdo);
$creditCheck = new CreditCheck($pdo);
$orderProcessor = new OrderProcessor($pdo);
$paymentConfig = $paymentGateway->getPaymentMethodsConfig($orderId ?: null);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token Validation Failed");
    }
    
    $action = $_POST['action'];
    
    if ($action === 'create_order' && $orderId === 0) {
        // Collect shipping info (country drives shipping calc + PayPal routing)
        $shippingInfo = [
           'address' => $_POST['address'] ?? '',
           'city' => $_POST['city'] ?? '',
           'zip' => $_POST['zip'] ?? '',
           'country' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $_POST['country'] ?? 'MX'), 0, 2)) ?: 'MX',
        ];

        // Check credit limit before creating order
        $creditResult = $creditCheck->checkCreditForOrder($_SESSION['user_id'], $orderTotal);
        
        if (!$creditResult['approved']) {
            $error = "Credit Check Failed: " . $creditResult['message'];
        } else {
            // Create new order
            // Note: We pass $cartItems (structure with product_id, quantity) to OrderProcessor
            $result = $orderProcessor->createOrder($_SESSION['user_id'], $cartItems, $shippingInfo, 'credit_card', trim($_POST['promo_code'] ?? '') ?: null);

            if ($result['success']) {
                $orderId = $result['order_id'];

                // Shipping country — drives PayPal account routing (MX vs US).
                $country = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $_POST['country'] ?? ''), 0, 2)) ?: null;
                if ($country) {
                    $pdo->prepare("UPDATE orders SET shipping_country = ? WHERE id = ?")->execute([$country, $orderId]);
                }

                // Hold the deducted stock for a limited window; it is restored
                // automatically if the customer abandons before paying.
                require_once 'services/ReservationService.php';
                (new ReservationService($pdo))->record($orderId, $cartItems);

                // If credit was applied, reserve it
                if ($creditResult['credit_applied']) {
                    $reserveResult = $creditCheck->reserveCreditForOrder($_SESSION['user_id'], $orderTotal);
                    if (!$reserveResult['success']) {
                         // Warning: Credit reservation failed but order created. 
                         // In production, we should log this critical error.
                    }
                }
                
                // Clear cart (session + saved DB copy)
                require_once 'services/CartService.php';
                (new CartService($pdo))->clear($_SESSION['user_id']);
                
                // Redirect to checkout with order ID
                header("Location: checkout.php?order_id=$orderId");
                exit;
                
            } else {
                $error = "Failed to create order: " . $result['error'];
            }
        }
    }
    
    if ($action === 'process_payment' && $orderId > 0) {
        $paymentMethod = $_POST['payment_method'] ?? '';
        $paymentData = [];
        
        if ($paymentMethod === 'oxxo') {
            // Issue the cash voucher and send the customer to it — the order
            // completes when the cash payment is confirmed.
            require_once 'services/OxxoPaymentService.php';
            $vx = (new OxxoPaymentService($pdo))->createVoucher($orderId);
            if ($vx['success']) { header("Location: oxxo-voucher.php?order_id=$orderId"); exit; }
            $error = $vx['error'];
        } elseif ($paymentMethod === 'stripe') {
            $paymentData['payment_method_id'] = $_POST['stripe_payment_method_id'] ?? '';
        } elseif ($paymentMethod === 'paypal') {
            $paymentData['paypal_order_id'] = $_POST['paypal_order_id'] ?? '';
        } elseif ($paymentMethod === 'mock') {
            if (!$allowMockPayment) {
                $error = 'This payment method is not available.';
                Security::logSecurityEvent('mock_payment_blocked', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'order_id' => $orderId
                ], 'warning');
            } else {
                $paymentData['card_number'] = $_POST['card_number'] ?? '';
                $paymentData['expiry'] = $_POST['expiry'] ?? '';
            }
        }
        
        $result = empty($error)
            ? $paymentGateway->processPayment($orderId, $paymentMethod, $paymentData)
            : ['success' => false, 'error' => $error];

        if ($result['success']) {
            // Redirect to success page
            header("Location: order-confirmation.php?order_id=$orderId&transaction_id=" . $result['transaction_id']);
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - VentDepot</title>
    <link rel="stylesheet" href="public/vendor/css/tailwind.min.css">
    <link rel="stylesheet" href="public/vendor/css/fontawesome.min.css">
    
    <!-- Stripe.js -->
    <?php if ($paymentConfig['stripe']['enabled']): ?>
    <script src="https://js.stripe.com/v3/"></script>
    <?php endif; ?>
    
    <!-- PayPal SDK -->
    <?php if ($paymentConfig['paypal']['enabled']): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?= urlencode($paymentConfig["paypal"]["client_id"]) ?>&currency=<?= urlencode($paymentConfig["paypal"]["currency"] ?? "USD") ?>"></script>
    <?php endif; ?>
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Order Summary -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Order Summary</h2>
                
                <div class="space-y-4 mb-6">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="flex items-center space-x-4">
                            <img src="<?= htmlspecialchars(asset_url($item['image_url'] ?? '') ?: 'https://via.placeholder.com/80x80') ?>" 
                                 alt="<?= htmlspecialchars($item['product_name']) ?>"
                                 class="w-16 h-16 object-cover rounded-lg">
                            <div class="flex-1">
                                <h3 class="font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></h3>
                                <p class="text-gray-600">Quantity: <?= $item['quantity'] ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium">$<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="border-t pt-4 space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal</span>
                        <span>$<?= number_format($orderTotal - $shippingCost - $taxAmount, 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Shipping</span>
                        <span>$<?= number_format($shippingCost, 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tax</span>
                        <span>$<?= number_format($taxAmount, 2) ?></span>
                    </div>
                    <div class="flex justify-between font-semibold text-lg border-t pt-2">
                        <span>Total</span>
                        <span>$<?= number_format($orderTotal, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Section -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Payment Information</h2>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($orderId === 0): ?>
                    <!-- Create Order Form -->
                    <form method="POST" id="create-order-form" class="space-y-4">
                        <?= Security::getCSRFInput() ?>
                        <input type="hidden" name="action" value="create_order">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Shipping Address</label>
                            <input type="text" name="address" required placeholder="123 Main St"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">City</label>
                                <input type="text" name="city" required placeholder="New York"
                                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">ZIP</label>
                                <input type="text" name="zip" required placeholder="10001"
                                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Promo code <span class="text-gray-400">(optional)</span></label>
                            <input type="text" name="promo_code" placeholder="e.g. VERANO15"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 font-mono uppercase focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Country</label>
                            <select name="country" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="MX">Mexico</option>
                                <option value="US">United States</option>
                                <option value="CA">Canada</option>
                                <option value="GB">United Kingdom</option>
                                <option value="ES">Spain</option>
                                <option value="OT">Other</option>
                            </select>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                            Continue to Payment
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Payment Methods -->
                    <div class="space-y-6">
                        <!-- Payment Method Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Choose Payment Method</label>
                            <div class="space-y-3">
                                <?php if ($paymentConfig['stripe']['enabled']): ?>
                                    <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="payment_method" value="stripe" class="mr-3" checked>
                                        <div class="flex items-center">
                                            <i class="fab fa-cc-stripe text-2xl text-blue-600 mr-3"></i>
                                            <div>
                                                <div class="font-medium">Credit/Debit Card</div>
                                                <div class="text-sm text-gray-600">Visa, Mastercard, American Express</div>
                                            </div>
                                        </div>
                                    </label>
                                <?php endif; ?>
                                
                                <?php if ($paymentConfig['paypal']['enabled']): ?>
                                    <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="payment_method" value="paypal" class="mr-3">
                                        <div class="flex items-center">
                                            <i class="fab fa-paypal text-2xl text-blue-700 mr-3"></i>
                                            <div>
                                                <div class="font-medium">PayPal or Card</div>
                                                <div class="text-sm text-gray-600">PayPal account or debit/credit card (no account needed)</div>
                                            </div>
                                        </div>
                                    </label>
                                <?php endif; ?>

                                <!-- OXXO cash voucher (Mexico) -->
                                <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="payment_method" value="oxxo" class="mr-3">
                                    <div class="flex items-center">
                                        <span class="text-xl font-black text-red-600 mr-3 tracking-tight">OXXO</span>
                                        <div>
                                            <div class="font-medium">Pago en efectivo en OXXO</div>
                                            <div class="text-sm text-gray-600">Te damos una referencia — paga en cualquier tienda OXXO (72 h)</div>
                                        </div>
                                    </div>
                                </label>

                                <!-- Mock Option (non-production only) -->
                                <?php if ($allowMockPayment): ?>
                                <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="payment_method" value="mock" class="mr-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-flask text-2xl text-purple-600 mr-3"></i>
                                        <div>
                                            <div class="font-medium">Test Credit Card</div>
                                            <div class="text-sm text-gray-600">For testing purposes only</div>
                                        </div>
                                    </div>
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Stripe Payment Form -->
                        <?php if ($paymentConfig['stripe']['enabled']): ?>
                            <div id="stripe-payment" class="payment-form">
                                <form id="stripe-payment-form" method="POST">
                                    <?= Security::getCSRFInput() ?>
                                    <input type="hidden" name="action" value="process_payment">
                                    <input type="hidden" name="payment_method" value="stripe">
                                    <input type="hidden" name="stripe_payment_method_id" id="stripe-payment-method-id">
                                    
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Card Information</label>
                                        <div id="stripe-card-element" class="p-3 border rounded-lg">
                                            <!-- Stripe Elements will create form elements here -->
                                        </div>
                                        <div id="stripe-card-errors" class="text-red-600 text-sm mt-2"></div>
                                    </div>
                                    
                                    <button type="submit" id="stripe-submit-button" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                                        <span id="stripe-button-text">Pay $<?= number_format($orderTotal, 2) ?></span>
                                        <span id="stripe-spinner" class="hidden">
                                            <i class="fas fa-spinner fa-spin mr-2"></i>Processing...
                                        </span>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- PayPal Payment -->
                        <?php if ($paymentConfig['paypal']['enabled']): ?>
                            <div id="paypal-payment" class="payment-form hidden">
                                <div id="paypal-button-container"></div>
                                <form id="paypal-payment-form" method="POST" class="hidden">
                                     <?= Security::getCSRFInput() ?>
                                    <input type="hidden" name="action" value="process_payment">
                                    <input type="hidden" name="payment_method" value="paypal">
                                    <input type="hidden" name="paypal_order_id" id="paypal-order-id">
                                </form>
                            </div>
                            </div>
                        <?php endif; ?>

                        <!-- OXXO Payment -->
                        <div id="oxxo-payment" class="payment-form hidden">
                            <form method="POST">
                                <?= Security::getCSRFInput() ?>
                                <input type="hidden" name="action" value="process_payment">
                                <input type="hidden" name="payment_method" value="oxxo">
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4 text-sm text-red-900">
                                    Te generamos una <b>referencia de pago</b>. Llévala (impresa o en tu teléfono) a cualquier
                                    tienda OXXO y paga en efectivo. Tu pedido se procesa al confirmarse el pago
                                    (normalmente el mismo día). La referencia vence en <b>72 horas</b>.
                                </div>
                                <button class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg">
                                    Generar referencia OXXO
                                </button>
                            </form>
                        </div>

                        <!-- Mock Payment (non-production only) -->
                        <?php if ($allowMockPayment): ?>
                        <div id="mock-payment" class="payment-form hidden">
                            <form id="mock-payment-form" method="POST">
                                <?= Security::getCSRFInput() ?>
                                <input type="hidden" name="action" value="process_payment">
                                <input type="hidden" name="payment_method" value="mock">
                                
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                    <p class="text-sm text-yellow-800">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Test Mode: Use card <strong>1231231231231233</strong>
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Card Number</label>
                                        <input type="text" name="card_number" required placeholder="1231231231231233"
                                            class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Expiry</label>
                                        <input type="text" name="expiry" required placeholder="MM/YY"
                                            class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">CVC</label>
                                        <input type="text" name="cvc" required placeholder="123"
                                            class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>

                                <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors mt-6">
                                    Pay $<?= number_format($orderTotal, 2) ?>
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Security Notice -->
                    <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-lock mr-2"></i>
                            Your payment information is encrypted and secure. We never store your credit card details.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Payment method switching
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.payment-form').forEach(form => {
                    form.classList.add('hidden');
                });
                
                if (this.value === 'stripe') {
                    document.getElementById('stripe-payment').classList.remove('hidden');
                } else if (this.value === 'paypal') {
                    document.getElementById('paypal-payment').classList.remove('hidden');
                } else if (this.value === 'mock') {
                    document.getElementById('mock-payment').classList.remove('hidden');
                } else if (this.value === 'oxxo') {
                    document.getElementById('oxxo-payment').classList.remove('hidden');
                }
            });
        });

        <?php if ($paymentConfig['stripe']['enabled'] && $orderId > 0): ?>
        // Initialize Stripe
        const stripe = Stripe('<?= $paymentConfig['stripe']['publishable_key'] ?>');
        const elements = stripe.elements();
        
        const cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#424770',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                },
            },
        });
        
        cardElement.mount('#stripe-card-element');
        
        cardElement.on('change', function(event) {
            const displayError = document.getElementById('stripe-card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
        
        const stripeForm = document.getElementById('stripe-payment-form');
        stripeForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            
            const submitButton = document.getElementById('stripe-submit-button');
            const buttonText = document.getElementById('stripe-button-text');
            const spinner = document.getElementById('stripe-spinner');
            
            submitButton.disabled = true;
            buttonText.classList.add('hidden');
            spinner.classList.remove('hidden');
            
            const {paymentMethod, error} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
            });
            
            if (error) {
                document.getElementById('stripe-card-errors').textContent = error.message;
                submitButton.disabled = false;
                buttonText.classList.remove('hidden');
                spinner.classList.add('hidden');
            } else {
                document.getElementById('stripe-payment-method-id').value = paymentMethod.id;
                stripeForm.submit();
            }
        });
        <?php endif; ?>

        <?php if ($paymentConfig['paypal']['enabled'] && $orderId > 0): ?>
        // PayPal (Orders v2, server-authoritative):
        //  1. createOrder -> OUR server creates the PayPal order with the amount
        //     from the database (browser never sets the price).
        //  2. Buyer approves in the PayPal popup.
        //  3. onApprove -> we submit the approved order id; the SERVER captures
        //     and verifies the amount before marking anything paid.
        paypal.Buttons({
            createOrder: function() {
                return fetch('api/paypal-create-order.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        order_id: <?= (int) $orderId ?>,
                        csrf_token: <?= json_encode(Security::generateCSRFToken()) ?>
                    })
                }).then(r => r.json()).then(d => {
                    if (!d.id) throw new Error(d.error || 'Could not start PayPal');
                    return d.id;
                });
            },
            onApprove: function(data) {
                // No client-side capture — the server captures & verifies.
                document.getElementById('paypal-order-id').value = data.orderID;
                document.getElementById('paypal-payment-form').submit();
            },
            onError: function(err) {
                console.error('PayPal Error:', err);
                alert('An error occurred with PayPal. Please try again.');
            }
        }).render('#paypal-button-container');
        <?php endif; ?>
    </script>
</body>
</html>