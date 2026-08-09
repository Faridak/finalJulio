<?php
/**
 * Supplier delivery scorecard.
 *
 * Measures received POs against expected_delivery_date:
 *   on-time %  = received on/before the promise
 *   avg delay  = mean days late (late POs only)
 * and maps that to suppliers.delivery_rating (0–5) so procurement sees at a
 * glance who actually delivers when they promise.
 */
class SupplierScorecardService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Compute (and persist) the scorecard for every supplier with received POs. */
    public function scoreAll()
    {
        $rows = $this->pdo->query("
            SELECT s.id, s.company_name,
                   COUNT(po.id) AS pos_received,
                   SUM(po.actual_delivery_date <= po.expected_delivery_date) AS on_time,
                   AVG(CASE WHEN po.actual_delivery_date > po.expected_delivery_date
                            THEN DATEDIFF(po.actual_delivery_date, po.expected_delivery_date) END) AS avg_delay,
                   MAX(po.actual_delivery_date) AS last_delivery
            FROM suppliers s
            JOIN purchase_orders po ON po.supplier_id = s.id
            WHERE po.actual_delivery_date IS NOT NULL AND po.expected_delivery_date IS NOT NULL
            GROUP BY s.id, s.company_name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $upd = $this->pdo->prepare("UPDATE suppliers SET delivery_rating = ? WHERE id = ?");
        $out = [];
        foreach ($rows as $r) {
            $total = (int) $r['pos_received'];
            $onTimePct = $total > 0 ? round($r['on_time'] / $total * 100, 1) : 0;
            $avgDelay = $r['avg_delay'] !== null ? round((float) $r['avg_delay'], 1) : 0;

            // rating: on-time share drives it; heavy average delays subtract.
            $rating = round(max(0, min(5, ($onTimePct / 100) * 5 - min(1.5, $avgDelay / 10))), 2);
            $upd->execute([$rating, $r['id']]);

            $out[] = [
                'supplier_id' => (int) $r['id'], 'company_name' => $r['company_name'],
                'pos_received' => $total, 'on_time_pct' => $onTimePct,
                'avg_delay_days' => $avgDelay, 'last_delivery' => $r['last_delivery'],
                'delivery_rating' => $rating,
            ];
        }
        usort($out, fn($a, $b) => $b['delivery_rating'] <=> $a['delivery_rating']);
        return $out;
    }
}
