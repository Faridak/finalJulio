<?php
/**
 * MultiVendorService — splits a customer order into per-seller sub-orders.
 *
 * A single order may contain items from several sellers. Each seller gets a
 * merchant_suborders row (its own subtotal, commission, fulfilment status and
 * shipment), so sellers fulfil independently and payouts/commissions are exact.
 * Single-vendor orders simply produce one sub-order.
 */
require_once __DIR__ . '/ShippingService.php';
require_once __DIR__ . '/MarketplacePayoutService.php';

class MultiVendorService
{
    private $pdo;
    private $shipping;
    private $payout;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->shipping = new ShippingService($pdo);
        $this->payout = new MarketplacePayoutService($pdo);
    }

    /** Build per-seller sub-orders + shipments. Idempotent. */
    public function splitOrder($orderId)
    {
        // 1. tag each line with its seller
        $this->pdo->prepare("
            UPDATE order_items oi JOIN products p ON oi.product_id = p.id
            SET oi.merchant_id = p.merchant_id
            WHERE oi.order_id = ? AND (oi.merchant_id IS NULL OR oi.merchant_id = 0)
        ")->execute([$orderId]);

        // 2. group by seller
        $g = $this->pdo->prepare("
            SELECT p.merchant_id, p.ownership, SUM(oi.total) AS subtotal, COUNT(*) AS items
            FROM order_items oi JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            GROUP BY p.merchant_id, p.ownership
        ");
        $g->execute([$orderId]);
        $groups = $g->fetchAll(PDO::FETCH_ASSOC);

        $sub = $this->pdo->prepare("
            INSERT INTO merchant_suborders (order_id, merchant_id, subtotal, commission, net, item_count, ownership, status, shipment_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'processing', ?, NOW())
            ON DUPLICATE KEY UPDATE subtotal=VALUES(subtotal), commission=VALUES(commission), net=VALUES(net),
                item_count=VALUES(item_count), ownership=VALUES(ownership), shipment_id=COALESCE(merchant_suborders.shipment_id, VALUES(shipment_id))
        ");

        $result = [];
        foreach ($groups as $grp) {
            $mid = (int) $grp['merchant_id'];
            $subtotal = round((float) $grp['subtotal'], 2);
            $isMarket = $grp['ownership'] === 'marketplace';
            $commission = $isMarket ? round($subtotal * MarketplacePayoutService::DEFAULT_COMMISSION, 2) : 0;
            $net = round($subtotal - $commission, 2);

            // one shipment per seller
            $shipmentId = $this->shipping->createShipmentForOrder($orderId, $mid);

            $sub->execute([$orderId, $mid, $subtotal, $commission, $net, (int) $grp['items'], $grp['ownership'], $shipmentId]);

            // Notify marketplace sellers of their new order (not our own catalog).
            if ($isMarket) {
                require_once __DIR__ . '/SellerNotificationService.php';
                (new SellerNotificationService($this->pdo))->newOrder($mid, $orderId, (int) $grp['items'], $net);
            }

            $result[] = [
                'merchant_id' => $mid, 'subtotal' => $subtotal, 'commission' => $commission,
                'net' => $net, 'items' => (int) $grp['items'], 'shipment_id' => $shipmentId,
            ];
        }
        return ['sellers' => count($result), 'suborders' => $result];
    }

    /** A seller updates the fulfilment status of their portion of an order. */
    public function updateSubStatus($orderId, $merchantId, $status)
    {
        if (!in_array($status, ['processing', 'shipped', 'delivered', 'cancelled'], true)) return false;
        $shippedAt = $status === 'shipped' ? 'NOW()' : 'shipped_at';
        $this->pdo->prepare("UPDATE merchant_suborders SET status=?, shipped_at=" . ($status === 'shipped' ? 'NOW()' : 'shipped_at') . " WHERE order_id=? AND merchant_id=?")
                  ->execute([$status, $orderId, $merchantId]);

        // keep the seller's shipment in sync + tell the customer it shipped
        // (shipments.status enum uses carrier terms: 'in_transit' = shipped)
        if ($status === 'shipped') {
            $this->pdo->prepare("UPDATE shipments SET status='in_transit' WHERE order_id=? AND (merchant_id=? OR merchant_id IS NULL)")
                ->execute([$orderId, $merchantId]);
            $this->sendShippedEmail($orderId, $merchantId);
        }
        if ($status === 'delivered') {
            $this->pdo->prepare("UPDATE shipments SET status='delivered', actual_delivery=NOW() WHERE order_id=? AND (merchant_id=? OR merchant_id IS NULL)")
                ->execute([$orderId, $merchantId]);
        }

        // roll the parent order up: delivered only when all sub-orders delivered
        $this->rollUpOrderStatus($orderId);
        return true;
    }

    /** "Your order shipped" email with the tracking number (best effort, once per shipment). */
    private function sendShippedEmail($orderId, $merchantId)
    {
        try {
            $s = $this->pdo->prepare("
                SELECT s.id, s.tracking_number, u.email, u.id AS uid
                FROM shipments s
                JOIN orders o ON s.order_id = o.id
                JOIN users u ON o.user_id = u.id
                WHERE s.order_id = ? AND (s.merchant_id = ? OR s.merchant_id IS NULL)
                LIMIT 1");
            $s->execute([$orderId, $merchantId]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) return;

            // one email per shipment
            $dup = $this->pdo->prepare("SELECT 1 FROM email_queue WHERE template='order_shipped' AND reference_type='shipment' AND reference_id=? LIMIT 1");
            $dup->execute([$row['id']]);
            if ($dup->fetchColumn()) return;

            require_once __DIR__ . '/EmailService.php';
            $ref = 'VD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
            $base = rtrim($_ENV['APP_URL'] ?? 'http://localhost/finalJulio', '/');
            $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto">'
                  . '<h2 style="color:#2563eb">Your order is on its way! 📦</h2>'
                  . '<p>Order <b>#' . $ref . '</b> has shipped.</p>'
                  . '<p>Tracking number: <b style="font-family:monospace">' . htmlspecialchars($row['tracking_number'] ?? '—') . '</b></p>'
                  . '<p><a href="' . $base . '/order-details.php?id=' . (int) $orderId . '" style="background:#2563eb;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">Track your order</a></p></div>';
            (new EmailService($this->pdo))->queue($row['email'], null, "Order #$ref shipped — tracking inside", $html, 'order_shipped', 'shipment', (int) $row['id']);
        } catch (\Throwable $e) { /* never break fulfilment over an email */ }
    }

    private function rollUpOrderStatus($orderId)
    {
        $s = $this->pdo->prepare("
            SELECT COUNT(*) total,
                   SUM(status='delivered') delivered,
                   SUM(status='shipped') shipped,
                   SUM(status='cancelled') cancelled
            FROM merchant_suborders WHERE order_id=?
        ");
        $s->execute([$orderId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!$r || !$r['total']) return;

        if ($r['delivered'] == $r['total']) $new = 'delivered';
        elseif ($r['cancelled'] == $r['total']) $new = 'cancelled';
        elseif (($r['shipped'] + $r['delivered']) > 0) $new = 'shipped';
        else $new = 'processing';

        $this->pdo->prepare("UPDATE orders SET status=? WHERE id=? AND status NOT IN ('cancelled','refunded')")
                  ->execute([$new, $orderId]);
    }

    public function subOrdersFor($orderId)
    {
        $s = $this->pdo->prepare("
            SELECT ms.*, u.email AS seller_email FROM merchant_suborders ms
            LEFT JOIN users u ON ms.merchant_id=u.id WHERE ms.order_id=? ORDER BY ms.id
        ");
        $s->execute([$orderId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}
