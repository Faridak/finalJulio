<?php
/**
 * ReservationService — time-limited stock holds with abandonment recovery.
 *
 * OrderProcessor deducts catalog stock (products.inventory) under a FOR UPDATE
 * lock at order creation, so overselling can't happen. What was missing: if the
 * customer never pays, that stock stayed deducted forever. This service records
 * the deduction as a hold, commits it on payment, and RESTORES it (and cancels
 * the order) when the hold expires.
 */
class ReservationService
{
    private $pdo;
    const DEFAULT_TTL_MIN = 30;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Record holds for a freshly created (unpaid) order. */
    public function record($orderId, array $items, $ttlMin = self::DEFAULT_TTL_MIN)
    {
        // don't double-record
        $chk = $this->pdo->prepare("SELECT 1 FROM stock_reservations WHERE order_id=? LIMIT 1");
        $chk->execute([$orderId]);
        if ($chk->fetchColumn()) return ['success' => true, 'already' => true];

        $ins = $this->pdo->prepare("
            INSERT INTO stock_reservations (order_id, product_id, location_id, quantity, status, expires_at, created_at)
            VALUES (?, ?, ?, ?, 'held', DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())
        ");
        $n = 0;
        foreach ($items as $it) {
            $pid = (int) ($it['product_id'] ?? 0);
            $qty = (int) ($it['quantity'] ?? 0);
            if ($pid <= 0 || $qty <= 0) continue;
            $loc = $this->primaryLocation($pid);
            $ins->execute([$orderId, $pid, $loc, $qty, $ttlMin]);
            $n++;
        }
        return ['success' => true, 'held' => $n];
    }

    /** Order paid → holds become permanent (stock stays deducted). */
    public function commit($orderId)
    {
        $this->pdo->prepare("UPDATE stock_reservations SET status='committed' WHERE order_id=? AND status='held'")->execute([$orderId]);
    }

    /** Manual cancel → restore stock + release holds. */
    public function releaseForOrder($orderId)
    {
        return $this->restoreHolds("order_id = ?", [$orderId], 'released');
    }

    /**
     * Expire abandoned holds: unpaid orders whose hold TTL passed → restore stock
     * and cancel the order. Run from cron.
     */
    public function expireStale($limit = 200)
    {
        $rows = $this->pdo->prepare("
            SELECT r.id FROM stock_reservations r
            JOIN orders o ON r.order_id = o.id
            WHERE r.status='held' AND r.expires_at < NOW()
              AND o.payment_status <> 'paid' AND o.status NOT IN ('shipped','delivered','cancelled')
            LIMIT $limit
        ");
        $rows->execute();
        $ids = $rows->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) return ['expired' => 0];

        $in = implode(',', array_map('intval', $ids));
        $restored = $this->restoreHolds("id IN ($in)", [], 'expired');

        // cancel the now-empty abandoned orders
        $this->pdo->query("
            UPDATE orders o SET o.status='cancelled'
            WHERE o.id IN (SELECT DISTINCT order_id FROM stock_reservations WHERE id IN ($in))
              AND o.payment_status <> 'paid'
        ");
        return ['expired' => $restored['count'], 'orders_cancelled' => true];
    }

    /** Restore catalog + per-location stock for the matched holds, then mark them. */
    private function restoreHolds($where, $params, $newStatus)
    {
        $sel = $this->pdo->prepare("SELECT id, product_id, location_id, quantity FROM stock_reservations WHERE $where AND status='held'");
        $sel->execute($params);
        $holds = $sel->fetchAll(PDO::FETCH_ASSOC);

        $cat = $this->pdo->prepare("UPDATE products SET inventory = inventory + ? WHERE id = ?");
        $loc = $this->pdo->prepare("UPDATE product_inventory SET quantity_on_hand = quantity_on_hand + ?, quantity_available = quantity_available + ?, last_movement_at = NOW() WHERE product_id = ? AND location_id = ?");
        $mark = $this->pdo->prepare("UPDATE stock_reservations SET status = ? WHERE id = ?");

        $count = 0;
        foreach ($holds as $h) {
            $cat->execute([$h['quantity'], $h['product_id']]);
            if ($h['location_id']) $loc->execute([$h['quantity'], $h['quantity'], $h['product_id'], $h['location_id']]);
            $mark->execute([$newStatus, $h['id']]);
            $count++;
        }
        return ['count' => $count];
    }

    private function primaryLocation($pid)
    {
        $s = $this->pdo->prepare("SELECT location_id FROM product_inventory WHERE product_id=? ORDER BY quantity_on_hand DESC LIMIT 1");
        $s->execute([$pid]);
        return $s->fetchColumn() ?: null;
    }
}
