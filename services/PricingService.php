<?php
/**
 * PricingService — effective price with timed discounts, bulk (quantity) tiers,
 * and promotions; plus bulk price updates.
 *
 * Sources (already in the schema):
 *   products.price                     base price
 *   product_discounts                  timed + quantity-tiered % / fixed discounts
 *   product_promotions + promotion_products   named campaigns (%/fixed/buy-x-get-y)
 *
 * getEffectivePrice() returns the best single offer (discounts don't stack).
 */
class PricingService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{base:float, effective:float, unit_effective:float, discount:float,
     *               applied:?string, source:?string}
     */
    public function effectivePrice($productId, $qty = 1, $at = null)
    {
        // Use the DATABASE clock, not PHP's — the DB timezone can differ and
        // all stored discount windows are compared in SQL.
        $at = $at ?: $this->pdo->query('SELECT NOW()')->fetchColumn();
        $qty = max(1, (int) $qty);

        $base = (float) $this->scalar("SELECT price FROM products WHERE id=? LIMIT 1", [$productId]);
        $best = ['effective' => $base, 'discount' => 0, 'applied' => null, 'source' => null];

        // 1) Product-level timed / bulk discounts.
        $d = $this->pdo->prepare("
            SELECT discount_type, discount_value, min_quantity, max_quantity
            FROM product_discounts
            WHERE product_id=? AND is_active=1
              AND (start_date IS NULL OR start_date<=?) AND (end_date IS NULL OR end_date>=?)
              AND (min_quantity IS NULL OR min_quantity<=?) AND (max_quantity IS NULL OR max_quantity>=?)
        ");
        $d->execute([$productId, $at, $at, $qty, $qty]);
        foreach ($d as $row) {
            $eff = $row['discount_type'] === 'percentage'
                ? $base * (1 - $row['discount_value'] / 100)
                : $base - $row['discount_value'];
            $eff = max(0, round($eff, 2));
            if ($eff < $best['effective']) {
                $best = ['effective' => $eff, 'discount' => round($base - $eff, 2),
                         'applied' => ($row['discount_type'] === 'percentage' ? $row['discount_value'].'% off' : '$'.$row['discount_value'].' off')
                                      . ($row['min_quantity'] > 1 ? " (qty {$row['min_quantity']}+)" : ''),
                         'source' => 'discount'];
            }
        }

        // 2) Promotions the product belongs to.
        $pr = $this->pdo->prepare("
            SELECT pp.name, pp.promotion_type, pp.discount_value, pp.buy_quantity, pp.get_quantity
            FROM promotion_products x JOIN product_promotions pp ON x.promotion_id=pp.id
            WHERE x.product_id=? AND pp.is_active=1
              AND (pp.start_date IS NULL OR pp.start_date<=?) AND (pp.end_date IS NULL OR pp.end_date>=?)
        ");
        $pr->execute([$productId, $at, $at]);
        foreach ($pr as $row) {
            $eff = $best['effective'];
            if ($row['promotion_type'] === 'percentage') $eff = $base * (1 - $row['discount_value'] / 100);
            elseif ($row['promotion_type'] === 'fixed_amount') $eff = $base - $row['discount_value'];
            elseif ($row['promotion_type'] === 'buy_x_get_y' && $row['buy_quantity'] > 0) {
                // blended unit price across the buy+get group
                $group = $row['buy_quantity'] + $row['get_quantity'];
                if ($qty >= $group) $eff = $base * $row['buy_quantity'] / $group;
            }
            $eff = max(0, round($eff, 2));
            if ($eff < $best['effective']) {
                $best = ['effective' => $eff, 'discount' => round($base - $eff, 2),
                         'applied' => $row['name'], 'source' => 'promotion'];
            }
        }

        return [
            'base'           => round($base, 2),
            'unit_effective' => $best['effective'],
            'effective'      => round($best['effective'] * $qty, 2),
            'discount'       => $best['discount'],
            'applied'        => $best['applied'],
            'source'         => $best['source'],
        ];
    }

    /** Create a timed / bulk discount. */
    public function createDiscount($productId, $type, $value, $start = null, $end = null, $minQty = 1, $maxQty = null)
    {
        // product_discounts columns are NOT NULL — supply sensible bounds.
        // Default start comes from the DATABASE clock (see effectivePrice).
        $start  = $start  ?: $this->pdo->query('SELECT NOW()')->fetchColumn();
        $end    = $end    ?: '2099-12-31 23:59:59';
        $minQty = $minQty ?: 1;
        $maxQty = $maxQty ?: 999999;
        $this->pdo->prepare("
            INSERT INTO product_discounts (product_id, discount_type, discount_value, min_quantity, max_quantity, start_date, end_date, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ")->execute([$productId, $type, $value, $minQty, $maxQty, $start, $end]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Bulk price change. $scope: ['category_id'=>id] or ['ids'=>[...]] or ['all'=>true].
     * $method: 'percent' (+/-), 'fixed' (+/-), 'set'.
     * @return int rows affected
     */
    public function bulkUpdate(array $scope, $method, $value)
    {
        $value = (float) $value;
        $expr = $method === 'percent' ? "ROUND(price * (1 + ?/100), 2)"
              : ($method === 'fixed'  ? "GREATEST(0, ROUND(price + ?, 2))"
              : "?");
        $params = [$value];

        $where = "1=1";
        if (!empty($scope['category_id'])) {
            // include descendant categories
            $where = "(category_id = ? OR category_id IN (SELECT id FROM categories WHERE parent_id = ?))";
            $params[] = (int) $scope['category_id']; $params[] = (int) $scope['category_id'];
        } elseif (!empty($scope['ids'])) {
            $in = implode(',', array_map('intval', $scope['ids']));
            $where = "id IN ($in)";
        } elseif (empty($scope['all'])) {
            return 0; // refuse an unscoped update unless explicitly 'all'
        }

        $stmt = $this->pdo->prepare("UPDATE products SET price = $expr, updated_at=NOW() WHERE $where");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    private function scalar($sql, $args) { $s = $this->pdo->prepare($sql); $s->execute($args); return $s->fetchColumn(); }
}
