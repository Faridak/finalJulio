<?php
/**
 * OrderFulfillmentOrchestrator
 *
 * The connective tissue between payment, inventory, shipping and accounting.
 * Called once when an order's payment completes. Each step is independently
 * idempotent and guarded by a flag on the orders row, so the method is safe to
 * re-run (e.g. from a reconciliation job) without double-posting.
 *
 * Steps:
 *   1. Inventory  — write the sale movement ledger, sync stock, derive COGS.
 *   2. Accounting — post the balanced sale + COGS journal to general_ledger.
 *   3. Shipping   — create the shipment + first tracking event.
 *
 * A failure in any single step is logged and does not abort the others or the
 * payment; flags let a later run complete whatever is missing.
 */
require_once __DIR__ . '/AccountingService.php';
require_once __DIR__ . '/InventoryService.php';
require_once __DIR__ . '/ShippingService.php';

class OrderFulfillmentOrchestrator
{
    private $pdo;
    private $accounting;
    private $inventory;
    private $shipping;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->accounting = new AccountingService($pdo);
        $this->inventory  = new InventoryService($pdo);
        $this->shipping   = new ShippingService($pdo);
    }

    /**
     * Run fulfilment side effects for a paid order.
     *
     * @return array{success:bool,cogs:float,shipment_id:?int,errors:array}
     */
    public function onPaymentCompleted($orderId, $userId = null)
    {
        $order = $this->loadOrder($orderId);
        if (!$order) {
            return ['success' => false, 'cogs' => 0, 'shipment_id' => null, 'errors' => ['order_not_found']];
        }

        $errors = [];
        $cogs = 0.0;
        $shipmentId = null;

        // 1. Inventory ledger + stock sync (derives COGS).
        try {
            if (!$order['inventory_committed']) {
                $cogs = $this->inventory->commitSaleForOrder($orderId, $userId);
                $this->setFlag($orderId, 'inventory_committed');
            }
        } catch (Exception $e) {
            $errors['inventory'] = $e->getMessage();
            error_log("Orchestrator inventory step failed for order $orderId: " . $e->getMessage());
        }

        // 2. Accounting journal (sale recognition + COGS).
        try {
            if (!$order['accounting_posted'] && !$this->accounting->isPosted('order', $orderId)) {
                $this->postSaleJournal($order, $cogs);
                $this->setFlag($orderId, 'accounting_posted');
            }
        } catch (Exception $e) {
            $errors['accounting'] = $e->getMessage();
            error_log("Orchestrator accounting step failed for order $orderId: " . $e->getMessage());
        }

        // 3. Split into per-seller sub-orders + one shipment per seller.
        try {
            if (!$order['shipment_created']) {
                require_once __DIR__ . '/MultiVendorService.php';
                $split = (new MultiVendorService($this->pdo))->splitOrder($orderId);
                $shipmentId = $split['suborders'][0]['shipment_id'] ?? null;
                $this->setFlag($orderId, 'shipment_created');
            }
        } catch (Exception $e) {
            $errors['shipping'] = $e->getMessage();
            error_log("Orchestrator split/shipping step failed for order $orderId: " . $e->getMessage());
        }

        // 4. Order confirmation email (once per order, non-fatal).
        try {
            $this->sendConfirmationEmail($orderId);
        } catch (Exception $e) {
            error_log("Orchestrator email step failed for order $orderId: " . $e->getMessage());
        }

        // 5. Commit the stock hold — paid, so the deduction is permanent.
        try {
            require_once __DIR__ . '/ReservationService.php';
            (new ReservationService($this->pdo))->commit($orderId);
        } catch (Exception $e) {
            error_log("Orchestrator reservation-commit failed for order $orderId: " . $e->getMessage());
        }

        return [
            'success'     => empty($errors),
            'cogs'        => $cogs,
            'shipment_id' => $shipmentId,
            'errors'      => $errors,
        ];
    }

    /**
     * Sale recognition (retail / merchant-of-record view):
     *   Dr Cash                 (subtotal + shipping + tax)
     *   Cr Sales Revenue        (subtotal)
     *   Cr Shipping Revenue     (shipping)
     *   Cr Sales Tax Payable    (tax)
     * plus, when cost is known:
     *   Dr Cost of Goods Sold   (cogs)
     *   Cr Inventory            (cogs)
     */
    private function postSaleJournal(array $order, $cogs)
    {
        $subtotal = round((float) $order['subtotal'], 2);
        $shipping = round((float) $order['shipping_cost'], 2);
        $tax      = round((float) $order['tax'], 2);
        $cash     = round($subtotal + $shipping + $tax, 2);

        // Split own retail vs seller-owned (marketplace) merchandise.
        require_once __DIR__ . '/MarketplacePayoutService.php';
        $mp = new MarketplacePayoutService($this->pdo);
        $split = $mp->orderSplit((int) $order['id']);
        $ownSub = round($split['own_subtotal'], 2);
        $sellerNet = round($split['seller_net'], 2);
        $commission = round($split['commission'], 2);
        // Any rounding remainder stays on own revenue so the journal balances.
        $ownRev = round($subtotal - $sellerNet - $commission, 2);

        $lines = [
            ['code' => '1000', 'debit'  => $cash],           // Cash in
            ['code' => '4000', 'credit' => $ownRev],         // own retail revenue (+ rounding)
            ['code' => '2200', 'credit' => $sellerNet],      // owed to sellers (Merchant Payable)
            ['code' => '4100', 'credit' => $commission],     // platform Commission Revenue
            ['code' => '4200', 'credit' => $shipping],       // Shipping Revenue
            ['code' => '2100', 'credit' => $tax],            // Sales Tax Payable
        ];

        $this->accounting->postJournal(
            $lines,
            'Sale — order #' . $order['id'],
            'order',
            (int) $order['id']
        );

        // Record per-seller commission rows for later payout.
        try { $mp->recordCommissions((int) $order['id']); } catch (Exception $e) {
            error_log('recordCommissions failed for order ' . $order['id'] . ': ' . $e->getMessage());
        }

        if ($cogs > 0) {
            $this->accounting->postJournal(
                [
                    ['code' => '5000', 'debit'  => $cogs], // COGS
                    ['code' => '1200', 'credit' => $cogs], // Inventory
                ],
                'COGS — order #' . $order['id'],
                'order_cogs',
                (int) $order['id']
            );
        }
    }

    private function loadOrder($orderId)
    {
        $stmt = $this->pdo->prepare("
            SELECT id, user_id, subtotal, shipping_cost, tax, total,
                   inventory_committed, accounting_posted, shipment_created
            FROM orders WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Send the customer their order confirmation (idempotent per order). */
    private function sendConfirmationEmail($orderId)
    {
        // already sent?
        $exists = $this->pdo->prepare("SELECT 1 FROM email_queue WHERE reference_type='order' AND reference_id=? AND template='order_confirmation' LIMIT 1");
        $exists->execute([$orderId]);
        if ($exists->fetchColumn()) return;

        $o = $this->pdo->prepare("
            SELECT o.id, o.total, o.shipping_cost, o.tax, u.email, u.first_name
            FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ? LIMIT 1
        ");
        $o->execute([$orderId]);
        $order = $o->fetch(PDO::FETCH_ASSOC);
        if (!$order || empty($order['email'])) return;

        $it = $this->pdo->prepare("
            SELECT oi.product_id, oi.quantity, oi.total, p.name
            FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?
        ");
        $it->execute([$orderId]);
        $items = $it->fetchAll(PDO::FETCH_ASSOC);

        require_once __DIR__ . '/EmailService.php';
        (new EmailService($this->pdo))->orderConfirmation($order, $items, $order['email'], $order['first_name']);
    }

    private function setFlag($orderId, $column)
    {
        // Column name is from a fixed internal whitelist — never user input.
        $allowed = ['inventory_committed', 'accounting_posted', 'shipment_created'];
        if (!in_array($column, $allowed, true)) {
            return;
        }
        $stmt = $this->pdo->prepare("UPDATE orders SET `$column` = 1 WHERE id = ?");
        $stmt->execute([$orderId]);
    }
}
