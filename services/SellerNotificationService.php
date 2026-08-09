<?php
/**
 * Seller-facing transactional emails, queued through the standard email
 * pipeline (email_queue + cron/email-worker.php). Failures never break the
 * calling flow.
 */
require_once __DIR__ . '/EmailService.php';

class SellerNotificationService
{
    private $pdo;
    private $email;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->email = new EmailService($pdo);
    }

    private function sellerEmail($merchantId)
    {
        $s = $this->pdo->prepare("SELECT email FROM users WHERE id = ? AND role = 'merchant'");
        $s->execute([$merchantId]);
        return $s->fetchColumn() ?: null;
    }

    private function url($path)
    {
        return rtrim($_ENV['APP_URL'] ?? 'http://localhost/finalJulio', '/') . '/' . $path;
    }

    /** You received a new order (your portion of it). */
    public function newOrder($merchantId, $orderId, $itemCount, $net)
    {
        try {
            if (!$to = $this->sellerEmail($merchantId)) return;
            $ref = 'VD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
            $html = '<h2>New order 🎉</h2>'
                  . '<p>You sold <b>' . (int) $itemCount . ' item(s)</b> in order <b>#' . $ref . '</b>.'
                  . ' Your net earnings for this sale: <b>$' . number_format((float) $net, 2) . '</b>.</p>'
                  . '<p>Please prepare the items for fulfilment.</p>'
                  . '<p><a href="' . $this->url('merchant/orders.php') . '" style="background:#2563eb;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">View order</a></p>';
            $this->email->queue($to, null, "New order #$ref — you earned $" . number_format((float) $net, 2), $html, 'seller_new_order', 'order', $orderId);
        } catch (\Throwable $e) { /* best effort */ }
    }

    /** Your payout was sent. */
    public function payoutSent($merchantId, $payoutId, $net, $reference)
    {
        try {
            if (!$to = $this->sellerEmail($merchantId)) return;
            $html = '<h2>Payout sent 💸</h2>'
                  . '<p>We transferred <b>$' . number_format((float) $net, 2) . '</b> to you (reference <b>' . htmlspecialchars($reference) . '</b>).</p>'
                  . '<p><a href="' . $this->url('merchant/earnings.php') . '" style="background:#2563eb;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">View earnings</a></p>';
            $this->email->queue($to, null, 'Your VentDepot payout: $' . number_format((float) $net, 2), $html, 'seller_payout', 'payout', $payoutId);
        } catch (\Throwable $e) { /* best effort */ }
    }

    /** Your credibility tier changed (fees change with it). */
    public function tierChanged($merchantId, $oldTier, $newTier, $subscriptionFee)
    {
        try {
            if (!$to = $this->sellerEmail($merchantId)) return;
            $up = strcmp($newTier, $oldTier) < 0; // A < B alphabetically = upgrade
            $html = '<h2>' . ($up ? 'Tier upgrade 🏆' : 'Tier change') . '</h2>'
                  . '<p>Your seller tier moved from <b>' . htmlspecialchars($oldTier) . '</b> to <b>' . htmlspecialchars($newTier) . '</b>.'
                  . ' Your monthly subscription is now <b>$' . number_format((float) $subscriptionFee, 2) . '</b>.</p>'
                  . ($up ? '<p>Great work — keep ratings high and refunds low to stay there.</p>'
                         : '<p>Improve customer satisfaction and reduce refunds to move back up (and pay lower fees).</p>')
                  . '<p><a href="' . $this->url('merchant/earnings.php') . '" style="color:#2563eb">See your standing</a></p>';
            $this->email->queue($to, null, "Your seller tier is now $newTier", $html, 'seller_tier_change', 'merchant', $merchantId);
        } catch (\Throwable $e) { /* best effort */ }
    }
}
