<?php
/**
 * Our own shipping calculation — DHL-style chargeable weight + Google Maps
 * distance, priced from OUR rate table (shipping_rate_rules).
 *
 *   volumetric weight = L × W × H (cm) / 5000        (DHL divisor)
 *   chargeable weight = max(actual, volumetric) per item × qty
 *   price = base + $/kg × chargeable + $/km × distance
 *           × (1 + fuel surcharge)
 *   free shipping when the order subtotal clears the rule's threshold.
 *
 * Envia (or any carrier API) can later provide the LABEL; the price the
 * customer pays stays ours.
 */
require_once __DIR__ . '/DistanceService.php';

class ShippingCalculatorService
{
    const VOLUMETRIC_DIVISOR = 5000;   // DHL cm³ per kg

    // ISO country -> shipping_zones.id (1 US · 2 Canada · 3 Mexico · 4 Europe · 5 Asia Pacific · 6 International)
    private static $countryZone = [
        'MX' => 3, 'US' => 1, 'CA' => 2,
        'GB' => 4, 'ES' => 4, 'DE' => 4, 'FR' => 4, 'IT' => 4, 'NL' => 4, 'PT' => 4,
        'CN' => 5, 'JP' => 5, 'KR' => 5, 'AU' => 5, 'IN' => 5,
    ];

    private $pdo;
    private $distance;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->distance = new DistanceService($pdo);
    }

    public static function zoneFor($countryCode)
    {
        return self::$countryZone[strtoupper((string) $countryCode)] ?? 6; // default International
    }

    /**
     * DHL chargeable weight for a set of items [['product_id'=>, 'quantity'=>], ...].
     * @return array{actual: float, volumetric: float, chargeable: float}
     */
    public function chargeableWeight(array $items)
    {
        $actual = 0.0; $volumetric = 0.0; $chargeable = 0.0;
        $dim = $this->pdo->prepare("
            SELECT COALESCE(pd.weight_kg, 0.5) w,
                   COALESCE(pd.length_cm, 20) l, COALESCE(pd.width_cm, 15) wd, COALESCE(pd.height_cm, 10) h
            FROM products p
            LEFT JOIN product_dimensions pd ON pd.product_id = p.id
            WHERE p.id = ?
        ");
        foreach ($items as $it) {
            $qty = max(1, (int) ($it['quantity'] ?? $it['qty'] ?? 1));
            $dim->execute([(int) ($it['product_id'] ?? 0)]);
            $d = $dim->fetch(PDO::FETCH_ASSOC) ?: ['w' => 0.5, 'l' => 20, 'wd' => 15, 'h' => 10];
            $act = max(0.01, (float) $d['w']);
            $vol = ((float) $d['l'] * (float) $d['wd'] * (float) $d['h']) / self::VOLUMETRIC_DIVISOR;
            $actual += $act * $qty;
            $volumetric += $vol * $qty;
            $chargeable += max($act, $vol) * $qty;   // per-item DHL rule
        }
        return ['actual' => round($actual, 2), 'volumetric' => round($volumetric, 2), 'chargeable' => round($chargeable, 2)];
    }

    /**
     * Full quote with breakdown.
     * @param array  $items       [['product_id'=>, 'quantity'=>], ...]
     * @param string $destAddress full address text (for Google distance)
     * @param string $country     ISO-2 country
     * @param float  $subtotal    order goods subtotal (free-shipping threshold)
     */
    public function quote(array $items, $destAddress, $country = 'MX', $subtotal = 0.0)
    {
        $w = $this->chargeableWeight($items);
        $zone = self::zoneFor($country);
        $dist = $this->distance->distanceTo($destAddress, $country);

        // Best active rule for this zone + weight bracket (cheapest wins).
        $rule = $this->pdo->prepare("
            SELECT * FROM shipping_rate_rules
            WHERE zone_id = ? AND is_active = 1
              AND weight_min_kg <= ? AND (weight_max_kg IS NULL OR weight_max_kg = 0 OR weight_max_kg >= ?)
            ORDER BY (base_cost + cost_per_kg * ?) ASC
            LIMIT 1
        ");
        $rule->execute([$zone, $w['chargeable'], $w['chargeable'], $w['chargeable']]);
        $r = $rule->fetch(PDO::FETCH_ASSOC);

        if (!$r) {
            // No tariff configured for the zone: conservative fallback.
            return [
                'total' => round(15 + 4 * $w['chargeable'], 2), 'free' => false, 'rule_id' => null,
                'zone_id' => $zone, 'weight' => $w, 'distance_km' => $dist['km'], 'distance_source' => $dist['source'],
                'breakdown' => ['fallback' => true],
            ];
        }

        // Free-shipping threshold?
        if ((float) $r['free_shipping_threshold'] > 0 && $subtotal >= (float) $r['free_shipping_threshold']) {
            return [
                'total' => 0.0, 'free' => true, 'rule_id' => (int) $r['id'], 'zone_id' => $zone,
                'weight' => $w, 'distance_km' => $dist['km'], 'distance_source' => $dist['source'],
                'breakdown' => ['free_threshold' => (float) $r['free_shipping_threshold']],
            ];
        }

        $base = (float) $r['base_cost'];
        $weightCost = round((float) $r['cost_per_kg'] * $w['chargeable'], 2);
        $distCost = round((float) $r['cost_per_km'] * $dist['km'], 2);
        $sub = $base + $weightCost + $distCost;
        $fuel = round($sub * (float) $r['fuel_surcharge_rate'] / 100, 2);
        $total = round($sub + $fuel, 2);

        return [
            'total' => $total, 'free' => false, 'rule_id' => (int) $r['id'], 'zone_id' => $zone,
            'weight' => $w, 'distance_km' => $dist['km'], 'distance_source' => $dist['source'],
            'breakdown' => [
                'base' => $base, 'weight_cost' => $weightCost, 'distance_cost' => $distCost,
                'fuel_pct' => (float) $r['fuel_surcharge_rate'], 'fuel' => $fuel,
            ],
        ];
    }
}
