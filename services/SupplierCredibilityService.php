<?php
/**
 * SupplierCredibilityService — scores a seller (users.role='merchant') from real
 * platform signals and maps the score to a tier that adjusts their subscription
 * and insurance fees. Good sellers pay less; risky sellers (high refunds, poor
 * ratings) pay more.
 *
 *   score (0..100) = volume trust + satisfaction − refund penalty
 *   tier: A ≥85 · B 70–84 · C 50–69 · D <50
 *   subscription_fee = base × tier multiplier   (A .8 · B 1.0 · C 1.2 · D 1.5)
 *   insurance_rate   = base × tier multiplier
 */
class SupplierCredibilityService
{
    private $pdo;
    const BASE_SUBSCRIPTION = 99.00;   // $/month
    const BASE_INSURANCE = 1.000;      // % of stored value / month

    private static $tierMult = ['A' => 0.8, 'B' => 1.0, 'C' => 1.2, 'D' => 1.5];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Compute + persist credibility for one merchant. */
    public function score($merchantId)
    {
        // sales volume
        $o = $this->pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(total),0) rev FROM orders WHERE merchant_id=?");
        $o->execute([$merchantId]);
        $ord = $o->fetch(PDO::FETCH_ASSOC);
        $orders = (int) $ord['n']; $revenue = (float) $ord['rev'];

        // returns / refunds against this merchant's orders
        $r = $this->pdo->prepare("
            SELECT COUNT(*) n FROM return_requests rr JOIN orders o ON rr.order_id=o.id
            WHERE o.merchant_id=? AND rr.status NOT IN ('rejected','cancelled')
        ");
        $r->execute([$merchantId]);
        $returns = (int) $r->fetchColumn();
        $refundRate = $orders > 0 ? round($returns / $orders, 3) : 0;

        // customer satisfaction (product reviews on this merchant's products)
        $rt = $this->pdo->prepare("
            SELECT AVG(rating) a, COUNT(*) c FROM reviews rv
            JOIN products p ON rv.product_id=p.id WHERE p.merchant_id=?
        ");
        $rt->execute([$merchantId]);
        $rr = $rt->fetch(PDO::FETCH_ASSOC);
        $avgRating = $rr['c'] > 0 ? round((float) $rr['a'], 2) : null;

        // --- score components ---
        // volume trust: ramps to 30 pts by ~50 orders
        $volume = min(30, round($orders / 50 * 30));
        // satisfaction: 0..40 from rating (3.0 baseline). No ratings -> neutral 28.
        $satisfaction = $avgRating !== null ? max(0, min(40, round(($avgRating - 3) / 2 * 40 + 20))) : 28;
        // refund penalty: up to −40 as refund rate approaches 20%+
        $refundPenalty = min(40, round($refundRate / 0.20 * 40));
        // base trust 40
        $score = max(0, min(100, 40 + $volume + $satisfaction - $refundPenalty));

        $tier = $score >= 85 ? 'A' : ($score >= 70 ? 'B' : ($score >= 50 ? 'C' : 'D'));
        $mult = self::$tierMult[$tier];
        $subscription = round(self::BASE_SUBSCRIPTION * $mult, 2);
        $insurance = round(self::BASE_INSURANCE * $mult, 3);

        // Detect a tier change so we can notify the seller (fees change with it).
        $prevTier = $this->pdo->prepare("SELECT tier FROM merchant_credibility WHERE merchant_id = ?");
        $prevTier->execute([$merchantId]);
        $oldTier = $prevTier->fetchColumn();

        $this->pdo->prepare("
            INSERT INTO merchant_credibility
                (merchant_id, orders_count, revenue, returns_count, refund_rate, avg_rating, score, tier,
                 base_subscription, subscription_fee, insurance_rate, last_scored_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                orders_count=VALUES(orders_count), revenue=VALUES(revenue), returns_count=VALUES(returns_count),
                refund_rate=VALUES(refund_rate), avg_rating=VALUES(avg_rating), score=VALUES(score), tier=VALUES(tier),
                subscription_fee=VALUES(subscription_fee), insurance_rate=VALUES(insurance_rate), last_scored_at=NOW()
        ")->execute([$merchantId, $orders, $revenue, $returns, $refundRate, $avgRating, $score, $tier,
                     self::BASE_SUBSCRIPTION, $subscription, $insurance]);

        if ($oldTier && $oldTier !== $tier) {
            require_once __DIR__ . '/SellerNotificationService.php';
            (new SellerNotificationService($this->pdo))->tierChanged($merchantId, $oldTier, $tier, $subscription);
        }

        return [
            'merchant_id' => $merchantId, 'orders' => $orders, 'revenue' => $revenue,
            'returns' => $returns, 'refund_rate' => $refundRate, 'avg_rating' => $avgRating,
            'score' => $score, 'tier' => $tier, 'subscription_fee' => $subscription, 'insurance_rate' => $insurance,
        ];
    }

    /** Recompute all merchants; returns the ranked list. */
    public function scoreAll()
    {
        $ids = $this->pdo->query("SELECT id FROM users WHERE role='merchant'")->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ($ids as $id) $out[] = $this->score((int) $id);
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return $out;
    }

    public function get($merchantId)
    {
        $s = $this->pdo->prepare("SELECT * FROM merchant_credibility WHERE merchant_id=?");
        $s->execute([$merchantId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
