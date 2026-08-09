<?php
/**
 * ReorderService — demand-driven reorder alerts so stock never runs out.
 *
 *   reorder point = avg daily demand x (supplier lead time + customs clearance)
 *                   + safety stock (safety days of demand)
 *
 * An item is flagged when  on_hand + on_order  <=  reorder point.
 * "on_order" (expected inventory) = open, un-received PO quantities.
 */
class ReorderService
{
    private $pdo;
    const DEMAND_WINDOW_DAYS = 90;
    const SAFETY_DAYS = 7;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Average daily outbound demand over the window. */
    public function demandRate($productId, $days = self::DEMAND_WINDOW_DAYS)
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(quantity),0)
            FROM inventory_movements
            WHERE product_id = ? AND movement_type = 'out'
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$productId, $days]);
        return (float) $stmt->fetchColumn() / max(1, $days);
    }

    /** Expected inventory: open PO quantities not yet received. */
    public function onOrder($productId)
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(poi.quantity_ordered - poi.quantity_received),0)
            FROM purchase_order_items poi
            JOIN purchase_orders po ON poi.purchase_order_id = po.id
            WHERE poi.product_id = ?
              AND po.status NOT IN ('cancelled','received')
              AND po.approval_status <> 'rejected'
        ");
        $stmt->execute([$productId]);
        return (int) $stmt->fetchColumn();
    }

    /** Reorder point given the product's supplier lead + customs time. */
    public function reorderPoint($productId, $leadDays = null, $customsDays = null)
    {
        if ($leadDays === null || $customsDays === null) {
            $s = $this->pdo->prepare("
                SELECT COALESCE(sup.lead_time_days,7) lead, COALESCE(sup.customs_clearance_days,0) customs
                FROM products p LEFT JOIN suppliers sup ON p.source_supplier_id = sup.id
                WHERE p.id = ? LIMIT 1
            ");
            $s->execute([$productId]);
            $r = $s->fetch(PDO::FETCH_ASSOC) ?: ['lead' => 7, 'customs' => 0];
            $leadDays = (int) $r['lead']; $customsDays = (int) $r['customs'];
        }
        $daily = $this->demandRate($productId);
        return (int) ceil($daily * ($leadDays + $customsDays) + $daily * self::SAFETY_DAYS);
    }

    /**
     * Reorder alerts for a location: items where on_hand + on_order <= reorder point,
     * most urgent (fewest days of cover) first, with a suggested order quantity.
     */
    public function alerts($locationId, $limit = 100)
    {
        $rows = $this->pdo->prepare("
            SELECT pi.product_id, pi.quantity_on_hand, pi.reorder_quantity, pi.max_stock_level,
                   p.sku, p.name, p.source_supplier_id,
                   COALESCE(sup.company_name,'—') supplier, COALESCE(sup.lead_time_days,7) lead,
                   COALESCE(sup.customs_clearance_days,0) customs
            FROM product_inventory pi
            JOIN products p ON pi.product_id = p.id
            LEFT JOIN suppliers sup ON p.source_supplier_id = sup.id
            WHERE pi.location_id = ?
        ");
        $rows->execute([$locationId]);

        $alerts = [];
        foreach ($rows as $r) {
            $pid = (int) $r['product_id'];
            $daily = $this->demandRate($pid);
            if ($daily <= 0) continue;                     // no demand -> no reorder pressure

            $rop = $this->reorderPoint($pid, (int) $r['lead'], (int) $r['customs']);
            $onHand = (int) $r['quantity_on_hand'];
            $onOrder = $this->onOrder($pid);
            $available = $onHand + $onOrder;
            if ($available > $rop) continue;               // healthy

            $leadTotal = (int) $r['lead'] + (int) $r['customs'];
            $daysCover = $daily > 0 ? floor($onHand / $daily) : 999;
            // order up to max stock (or reorder_quantity), covering the gap
            $target = max((int) $r['max_stock_level'], $rop * 2);
            $suggest = max((int) $r['reorder_quantity'], $target - $available);

            $alerts[] = [
                'product_id'   => $pid,
                'sku'          => $r['sku'],
                'name'         => $r['name'],
                'supplier'     => $r['supplier'],
                'supplier_id'  => $r['source_supplier_id'],
                'on_hand'      => $onHand,
                'on_order'     => $onOrder,
                'reorder_point' => $rop,
                'daily_demand' => round($daily, 2),
                'days_cover'   => (int) $daysCover,
                'lead_total'   => $leadTotal,
                'suggest_qty'  => (int) $suggest,
                'stockout_risk' => $daysCover < $leadTotal,   // will run out before replenishment arrives
            ];
        }

        usort($alerts, fn($a, $b) => $a['days_cover'] <=> $b['days_cover']);
        return array_slice($alerts, 0, $limit);
    }
}
