<?php
/**
 * Order Processing System
 * Handles order creation, stock management, and transaction recording
 */

class OrderProcessor {
    private $pdo;
    private $pricing;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        require_once __DIR__ . '/../services/PricingService.php';
        $this->pricing = new PricingService($pdo);
    }
    
    /**
     * Process a new order
     */
    public function createOrder($userId, $cartItems, $shippingInfo, $paymentMethod = 'credit_card', $promoCode = null) {
        try {
            $this->pdo->beginTransaction();
            
            // 1. Calculate totals and validate stock again
            $subtotal = 0;
            $itemsToProcess = [];
            
            foreach ($cartItems as $item) {
                // Fetch fresh product data to ensure price and stock are current
                $stmt = $this->pdo->prepare("SELECT price, inventory, merchant_id FROM products WHERE id = ? FOR UPDATE");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    throw new Exception("Product ID {$item['product_id']} not found.");
                }
                
                if ($product['inventory'] < $item['quantity']) {
                    throw new Exception("Insufficient stock for product ID {$item['product_id']}. Available: {$product['inventory']}");
                }
                
                // Charge the EFFECTIVE price (timed discounts, bulk tiers,
                // promotions) — what the customer saw on the product page.
                $ep = $this->pricing->effectivePrice($item['product_id'], $item['quantity']);
                $unitPrice = round((float) ($ep['unit_effective'] ?? $product['price']), 2);
                $itemTotal = $unitPrice * $item['quantity'];
                $subtotal += $itemTotal;

                $itemsToProcess[] = [
                    'product_id' => $item['product_id'],
                    'merchant_id' => $product['merchant_id'],
                    'qty' => $item['quantity'],
                    'price' => $unitPrice,
                    'total' => $itemTotal
                ];
            }
            
            // 2. Determine primary merchant (simplified: first item's merchant for now, or split order logic)
            // For MVP, we will assume single order for now or assign to first merchant found
            // Real implementation might need split orders or a platform 'orders' table.
            // Let's assume we create one order record.
            $merchantId = $itemsToProcess[0]['merchant_id'];

            // Our own shipping calculation: DHL chargeable weight + distance
            // from the warehouse, priced from shipping_rate_rules.
            require_once __DIR__ . '/../services/ShippingCalculatorService.php';
            $destAddress = trim(($shippingInfo['address'] ?? '') . ', ' . ($shippingInfo['city'] ?? '') . ' ' . ($shippingInfo['zip'] ?? ''), ', ');
            $country = strtoupper($shippingInfo['country'] ?? 'MX');
            $quote = (new ShippingCalculatorService($this->pdo))
                ->quote($itemsToProcess, $destAddress, $country, $subtotal);
            $shippingCost = (float) $quote['total'];

            // Promo code (marketing): validated against fresh cart prices; the
            // discount reduces the taxable subtotal.
            $discount = 0.0; $appliedCode = null;
            if ($promoCode) {
                require_once __DIR__ . '/../services/PromoCodeService.php';
                $promoSvc = new PromoCodeService($this->pdo);
                $chk = $promoSvc->validate($promoCode, $userId,
                    array_map(fn($i) => ['product_id' => $i['product_id'], 'quantity' => $i['qty'], 'price' => $i['price']], $itemsToProcess));
                if (!$chk['valid']) throw new Exception('Promo code: ' . $chk['error']);
                $discount = min((float) $chk['discount'], $subtotal);
                $appliedCode = strtoupper(trim($promoCode));
                $promoSvc->redeem($appliedCode);
            }

            $tax = ($subtotal - $discount) * 0.08; // 8% tax on the discounted amount
            $total = $subtotal - $discount + $shippingCost + $tax;
            
            // 3. Create Order
            $stmt = $this->pdo->prepare("
                INSERT INTO orders (
                    user_id, merchant_id, subtotal, shipping_cost, tax, total,
                    promo_code, discount_amount,
                    status, payment_status, payment_method, shipping_address, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, NOW())
            ");

            $addressString = $shippingInfo['address'] . ", " . $shippingInfo['city'] . ", " . $shippingInfo['zip'];

            $stmt->execute([
                $userId,
                $merchantId,
                $subtotal,
                $shippingCost,
                $tax,
                $total,
                $appliedCode,
                $discount,
                $paymentMethod,
                $addressString
            ]);
            
            $orderId = $this->pdo->lastInsertId();
            
            // 4. Create Order Items and Deduct Stock
            $itemStmt = $this->pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase, total)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stockStmt = $this->pdo->prepare("
                UPDATE products SET inventory = inventory - ? WHERE id = ?
            ");
            
            foreach ($itemsToProcess as $item) {
                // Insert Item
                $itemStmt->execute([
                    $orderId, 
                    $item['product_id'], 
                    $item['qty'], 
                    $item['price'], 
                    $item['total']
                ]);
                
                // Deduct Stock
                $stockStmt->execute([$item['qty'], $item['product_id']]);
            }
            
            $this->pdo->commit();
            return ['success' => true, 'order_id' => $orderId];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>
