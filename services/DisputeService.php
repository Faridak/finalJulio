<?php
/**
 * DisputeService
 *
 * Buyer <-> seller disputes, one per seller portion of an order (multi-vendor
 * orders can dispute each seller separately). Flow:
 *
 *   buyer opens  -> 'open'             seller has 3 days to answer
 *   seller reply -> 'seller_responded'
 *   buyer escalates (or seller missed the deadline) -> 'under_review'
 *   admin decides -> 'resolved_buyer' | 'resolved_seller'   (or buyer withdraws -> 'closed')
 *
 * A buyer-favour decision creates an APPROVED return through ReturnService, so
 * the refund itself (payment record, restock, ledger reversal) is completed in
 * admin/returns.php exactly like any other return — one money path, not two.
 */
require_once __DIR__ . '/ReturnService.php';
require_once __DIR__ . '/EmailService.php';

class DisputeService
{
    const REASONS = [
        'not_received'     => 'Item not received',
        'not_as_described' => 'Not as described',
        'damaged'          => 'Arrived damaged',
        'wrong_item'       => 'Wrong item sent',
        'missing_parts'    => 'Missing parts',
        'other'            => 'Other',
    ];
    const OUTCOMES = [
        'full_refund'    => 'Full refund',
        'partial_refund' => 'Partial refund',
        'replacement'    => 'Replacement',
        'other'          => 'Other',
    ];
    const OPEN_STATUSES  = ['open', 'seller_responded', 'under_review'];
    const SELLER_DAYS    = 3;    // seller response window
    const MAX_ORDER_AGE  = 90;   // days after the order a dispute can be opened

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Sellers on an order the buyer can still open a dispute against.
     * @return array [ ['merchant_id'=>int, 'seller_name'=>string, 'items'=>int, 'subtotal'=>float, 'open_dispute_id'=>?int], ... ]
     */
    public function disputableSellers($orderId, $buyerId)
    {
        $order = $this->buyerOrder($orderId, $buyerId);
        if (!$order) return [];
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(oi.merchant_id, p.merchant_id) AS merchant_id,
                   COALESCE(ss.store_name, IF(p.ownership = 'own', 'VentDepot', 'Marketplace seller')) AS seller_name,
                   SUM(oi.quantity) AS items,
                   SUM(oi.quantity * oi.price_at_purchase) AS subtotal,
                   (SELECT d.id FROM order_disputes d
                     WHERE d.order_id = oi.order_id AND d.merchant_id = COALESCE(oi.merchant_id, p.merchant_id)
                       AND d.status IN ('open','seller_responded','under_review') LIMIT 1) AS open_dispute_id
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            LEFT JOIN supplier_store ss ON ss.merchant_id = COALESCE(oi.merchant_id, p.merchant_id)
            WHERE oi.order_id = ?
            GROUP BY COALESCE(oi.merchant_id, p.merchant_id), seller_name
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Why this order can't be disputed right now, or null when it can. */
    public function ineligibleReason(array $order)
    {
        if ($order['status'] === 'cancelled') return 'This order was cancelled.';
        if (($order['payment_status'] ?? '') !== 'paid') return 'Disputes can be opened once the order is paid.';
        $age = (int) floor((time() - strtotime($order['created_at'])) / 86400);
        if ($age > self::MAX_ORDER_AGE) return 'Disputes must be opened within ' . self::MAX_ORDER_AGE . ' days of the order.';
        return null;
    }

    public function open($buyerId, $orderId, $merchantId, $reason, $description, $outcome, $amountRequested = null)
    {
        $order = $this->buyerOrder($orderId, $buyerId);
        if (!$order) return ['success' => false, 'error' => 'Order not found.'];
        if ($why = $this->ineligibleReason($order)) return ['success' => false, 'error' => $why];
        if (!isset(self::REASONS[$reason])) return ['success' => false, 'error' => 'Choose a reason.'];
        if (!isset(self::OUTCOMES[$outcome])) $outcome = 'full_refund';
        $description = trim((string) $description);
        if (mb_strlen($description) < 20) return ['success' => false, 'error' => 'Please describe the problem (at least 20 characters).'];

        $seller = null;
        foreach ($this->disputableSellers($orderId, $buyerId) as $s) {
            if ((int) $s['merchant_id'] === (int) $merchantId) { $seller = $s; break; }
        }
        if (!$seller) return ['success' => false, 'error' => 'That seller has no items on this order.'];
        if ($seller['open_dispute_id']) return ['success' => false, 'error' => 'There is already an open dispute for this seller.', 'dispute_id' => (int) $seller['open_dispute_id']];

        $amount = null;
        if ($outcome === 'partial_refund') {
            $amount = round((float) $amountRequested, 2);
            if ($amount <= 0 || $amount > (float) $seller['subtotal']) {
                return ['success' => false, 'error' => 'Partial refund must be between $0.01 and $' . number_format($seller['subtotal'], 2) . '.'];
            }
        }
        $priority = in_array($reason, ['not_received', 'wrong_item'], true) || (float) $seller['subtotal'] >= 500 ? 'high' : 'medium';

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("
                INSERT INTO order_disputes
                    (order_id, merchant_id, buyer_id, reason, description, requested_outcome, amount_requested,
                     status, priority, seller_due_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?, DATE_ADD(NOW(), INTERVAL " . self::SELLER_DAYS . " DAY))
            ")->execute([$orderId, $merchantId, $buyerId, $reason, mb_substr($description, 0, 5000), $outcome, $amount, $priority]);
            $id = (int) $this->pdo->lastInsertId();
            $this->addMessage($id, $buyerId, 'buyer', $description);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('DisputeService::open failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Could not open the dispute. Please try again.'];
        }

        $this->notifyUser($merchantId, "New dispute on order #$orderId",
            "<p>A buyer opened a dispute (<b>" . htmlspecialchars(self::REASONS[$reason]) . "</b>) on order #$orderId.</p>"
            . "<p>Please respond within " . self::SELLER_DAYS . " days from your seller dashboard &rarr; Disputes. "
            . "If you don't respond, VentDepot will decide the case.</p>", $id);
        return ['success' => true, 'dispute_id' => $id];
    }

    /**
     * Load a dispute the user may see. $as: 'buyer' | 'seller' | 'admin'.
     * Sellers never get the buyer's email or address (PII policy).
     */
    public function get($disputeId, $userId, $as)
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, o.total AS order_total, o.status AS order_status, o.created_at AS order_date,
                   COALESCE(ss.store_name, 'VentDepot') AS seller_name,
                   rr.status AS return_status, rr.refund_amount AS return_refund_amount
            FROM order_disputes d
            JOIN orders o ON o.id = d.order_id
            LEFT JOIN supplier_store ss ON ss.merchant_id = d.merchant_id
            LEFT JOIN return_requests rr ON rr.id = d.return_request_id
            WHERE d.id = ?
        ");
        $stmt->execute([$disputeId]);
        $d = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$d) return null;
        if ($as === 'buyer' && (int) $d['buyer_id'] !== (int) $userId) return null;
        if ($as === 'seller' && (int) $d['merchant_id'] !== (int) $userId) return null;
        if ($as === 'admin') {
            $u = $this->pdo->prepare("SELECT id, email FROM users WHERE id IN (?, ?)");
            $u->execute([$d['buyer_id'], $d['merchant_id']]);
            foreach ($u->fetchAll(PDO::FETCH_KEY_PAIR) as $uid => $email) {
                if ((int) $uid === (int) $d['buyer_id']) $d['buyer_email'] = $email;
                if ((int) $uid === (int) $d['merchant_id']) $d['seller_email'] = $email;
            }
        }
        $m = $this->pdo->prepare("SELECT author_role, body, created_at FROM order_dispute_messages WHERE dispute_id = ? ORDER BY created_at, id");
        $m->execute([$disputeId]);
        $d['messages'] = $m->fetchAll(PDO::FETCH_ASSOC);
        $i = $this->pdo->prepare("
            SELECT p.name, oi.quantity, oi.price_at_purchase
            FROM order_items oi JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ? AND COALESCE(oi.merchant_id, p.merchant_id) = ?
        ");
        $i->execute([$d['order_id'], $d['merchant_id']]);
        $d['items'] = $i->fetchAll(PDO::FETCH_ASSOC);
        $d['seller_overdue'] = $d['status'] === 'open' && strtotime($d['seller_due_at']) < time();
        return $d;
    }

    public function listFor($userId, $as, $statusFilter = null)
    {
        $where = $as === 'buyer' ? 'd.buyer_id = ?' : ($as === 'seller' ? 'd.merchant_id = ?' : '1 = 1');
        $params = $as === 'admin' ? [] : [$userId];
        if ($statusFilter === 'active') {
            $where .= " AND d.status IN ('open','seller_responded','under_review')";
        } elseif ($statusFilter === 'resolved') {
            $where .= " AND d.status IN ('resolved_buyer','resolved_seller','closed')";
        }
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.order_id, d.merchant_id, d.reason, d.requested_outcome, d.status, d.priority,
                   d.seller_due_at, d.created_at, d.updated_at,
                   COALESCE(ss.store_name, 'VentDepot') AS seller_name,
                   (SELECT SUM(oi.quantity * oi.price_at_purchase) FROM order_items oi JOIN products p ON p.id = oi.product_id
                     WHERE oi.order_id = d.order_id AND COALESCE(oi.merchant_id, p.merchant_id) = d.merchant_id) AS disputed_amount
            FROM order_disputes d
            LEFT JOIN supplier_store ss ON ss.merchant_id = d.merchant_id
            WHERE $where
            ORDER BY FIELD(d.status, 'under_review','open','seller_responded','resolved_buyer','resolved_seller','closed'),
                     FIELD(d.priority, 'urgent','high','medium','low'), d.created_at
            LIMIT 500
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reply($disputeId, $userId, $as, $body)
    {
        $d = $this->get($disputeId, $userId, $as);
        if (!$d) return ['success' => false, 'error' => 'Dispute not found.'];
        if (!in_array($d['status'], self::OPEN_STATUSES, true)) return ['success' => false, 'error' => 'This dispute is closed.'];
        $body = trim((string) $body);
        if ($body === '') return ['success' => false, 'error' => 'Message cannot be empty.'];

        $this->addMessage($disputeId, $userId, $as, mb_substr($body, 0, 5000));
        if ($as === 'seller' && $d['status'] === 'open') {
            $this->setStatus($disputeId, 'seller_responded');
        }
        if ($as === 'seller') {
            $this->notifyUser($d['buyer_id'], "Seller replied to your dispute #$disputeId",
                "<p>The seller replied to your dispute on order #{$d['order_id']}. Open <b>My Disputes</b> to read it.</p>", $disputeId);
        } elseif ($as === 'buyer') {
            $this->notifyUser($d['merchant_id'], "Buyer replied on dispute #$disputeId",
                "<p>The buyer added a message to dispute #$disputeId (order #{$d['order_id']}).</p>", $disputeId);
        }
        return ['success' => true];
    }

    /** Buyer asks VentDepot to decide: allowed once the seller answered or missed the deadline. */
    public function escalate($disputeId, $buyerId)
    {
        $d = $this->get($disputeId, $buyerId, 'buyer');
        if (!$d) return ['success' => false, 'error' => 'Dispute not found.'];
        if ($d['status'] === 'seller_responded' || $d['seller_overdue']) {
            $this->setStatus($disputeId, 'under_review');
            $this->addMessage($disputeId, $buyerId, 'buyer', '[Escalated to VentDepot for a decision]');
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'You can escalate once the seller responds or the ' . self::SELLER_DAYS . '-day response window ends.'];
    }

    public function withdraw($disputeId, $buyerId)
    {
        $d = $this->get($disputeId, $buyerId, 'buyer');
        if (!$d || !in_array($d['status'], self::OPEN_STATUSES, true)) return ['success' => false, 'error' => 'Dispute not found.'];
        $this->pdo->prepare("UPDATE order_disputes SET status = 'closed', resolution_notes = 'Withdrawn by buyer', resolved_at = NOW() WHERE id = ?")
            ->execute([$disputeId]);
        $this->addMessage($disputeId, $buyerId, 'buyer', '[Dispute withdrawn by buyer]');
        return ['success' => true];
    }

    /**
     * Admin decision.
     * @param string     $winner       'buyer' | 'seller'
     * @param float|null $refundAmount buyer win: null = full refund of this seller's items (+ tax share);
     *                                 a smaller number = partial refund (no restock)
     * @param bool       $restock      buyer win, full refund: put the goods back in stock (item was returned)
     */
    public function resolve($disputeId, $adminId, $winner, $notes, $refundAmount = null, $restock = false)
    {
        $d = $this->get($disputeId, $adminId, 'admin');
        if (!$d) return ['success' => false, 'error' => 'Dispute not found.'];
        if (!in_array($d['status'], self::OPEN_STATUSES, true)) return ['success' => false, 'error' => 'Dispute is already closed.'];
        $notes = trim((string) $notes);
        if ($notes === '') return ['success' => false, 'error' => 'Write the decision notes (both parties will see them).'];

        $returnId = null;
        if ($winner === 'buyer') {
            $items = $this->pdo->prepare("
                SELECT oi.id AS order_item_id, oi.quantity
                FROM order_items oi JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = ? AND COALESCE(oi.merchant_id, p.merchant_id) = ?
            ");
            $items->execute([$d['order_id'], $d['merchant_id']]);
            $svc = new ReturnService($this->pdo);
            $r = $svc->createReturn((int) $d['order_id'], (int) $d['buyer_id'], $items->fetchAll(PDO::FETCH_ASSOC),
                'Dispute #' . $disputeId . ': ' . mb_substr($notes, 0, 400), false);
            if (!$r['success']) return ['success' => false, 'error' => 'Could not create the refund: ' . $r['error']];
            $returnId = (int) $r['return_id'];

            $partial = $refundAmount !== null && $refundAmount !== '' && (float) $refundAmount < (float) $r['refund_amount'];
            if ($partial && (float) $refundAmount <= 0) {
                $svc->setStatus($returnId, 'cancelled');
                return ['success' => false, 'error' => 'Refund amount must be greater than zero.'];
            }
            $this->pdo->prepare("UPDATE return_requests SET refund_amount = ?, restock = ? WHERE id = ?")
                ->execute([$partial ? round((float) $refundAmount, 2) : $r['refund_amount'], ($restock && !$partial) ? 1 : 0, $returnId]);
            $svc->setStatus($returnId, 'approved');
        } elseif ($winner !== 'seller') {
            return ['success' => false, 'error' => 'Choose who the decision favours.'];
        }

        $this->pdo->prepare("
            UPDATE order_disputes
            SET status = ?, resolution_notes = ?, return_request_id = ?, resolved_by = ?, resolved_at = NOW()
            WHERE id = ?
        ")->execute([$winner === 'buyer' ? 'resolved_buyer' : 'resolved_seller', $notes, $returnId, $adminId, $disputeId]);
        $this->addMessage($disputeId, $adminId, 'admin', 'Decision: ' . $notes);

        require_once __DIR__ . '/../includes/AuditLog.php';
        AuditLog::record($this->pdo, 'dispute.resolve', 'order_dispute', $disputeId,
            "Dispute #$disputeId resolved for $winner" . ($returnId ? " (return #$returnId)" : ''),
            ['order_id' => (int) $d['order_id'], 'return_id' => $returnId]);
        $msg = "<p>Dispute #$disputeId (order #{$d['order_id']}) has been decided in favour of the <b>$winner</b>.</p><p>"
             . nl2br(htmlspecialchars($notes)) . "</p>";
        $this->notifyUser($d['buyer_id'], "Decision on dispute #$disputeId", $msg, $disputeId);
        $this->notifyUser($d['merchant_id'], "Decision on dispute #$disputeId", $msg, $disputeId);
        return ['success' => true, 'return_id' => $returnId];
    }

    public function stats()
    {
        return $this->pdo->query("
            SELECT
              SUM(status IN ('open','seller_responded','under_review')) AS active,
              SUM(status = 'under_review') AS under_review,
              SUM(status = 'open' AND seller_due_at < NOW()) AS seller_overdue,
              SUM(status IN ('resolved_buyer','resolved_seller') AND resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS resolved_30d,
              SUM(status = 'resolved_buyer' AND resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS buyer_wins_30d,
              AVG(CASE WHEN resolved_at IS NOT NULL AND resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                       THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) AS avg_hours_30d
            FROM order_disputes
        ")->fetch(PDO::FETCH_ASSOC);
    }

    // ---- helpers ---------------------------------------------------------

    private function buyerOrder($orderId, $buyerId)
    {
        $s = $this->pdo->prepare("SELECT id, user_id, status, payment_status, total, created_at FROM orders WHERE id = ? AND user_id = ?");
        $s->execute([$orderId, $buyerId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function addMessage($disputeId, $userId, $role, $body)
    {
        $this->pdo->prepare("INSERT INTO order_dispute_messages (dispute_id, user_id, author_role, body) VALUES (?, ?, ?, ?)")
            ->execute([$disputeId, $userId, $role, $body]);
        $this->pdo->prepare("UPDATE order_disputes SET updated_at = NOW() WHERE id = ?")->execute([$disputeId]);
    }

    private function setStatus($disputeId, $status)
    {
        $this->pdo->prepare("UPDATE order_disputes SET status = ? WHERE id = ?")->execute([$status, $disputeId]);
    }

    /** Best-effort email; a mail failure never breaks the dispute flow. */
    private function notifyUser($userId, $subject, $html, $disputeId)
    {
        try {
            $u = $this->pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
            $u->execute([$userId]);
            if ($row = $u->fetch(PDO::FETCH_ASSOC)) {
                (new EmailService($this->pdo))->queue($row['email'], $row['first_name'] ?: '', $subject, $html, 'dispute', 'order_dispute', $disputeId);
            }
        } catch (Throwable $e) {
            error_log('Dispute notification failed: ' . $e->getMessage());
        }
    }
}
