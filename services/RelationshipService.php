<?php
/**
 * RelationshipService — product accessories, components, and alternatives.
 *
 * Models the "a toilet needs a seat, hose, knob, connectors (components) and a
 * wax ring (accessory); the hose comes in copper/plastic/metal (alternatives)"
 * scenario. Used to suggest fundamental add-ons in the storefront and to pull a
 * complete item list into a PO / quote.
 */
require_once __DIR__ . '/PricingService.php';

class RelationshipService
{
    private $pdo;
    private $pricing;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pricing = new PricingService($pdo);
    }

    /** Create/replace a relationship. */
    public function link($productId, $relatedId, $type = 'accessory', $isRequired = false, $note = null, $sort = 0)
    {
        if ((int) $productId === (int) $relatedId) return false;
        $this->pdo->prepare("
            INSERT INTO product_relationships (product_id, related_product_id, relationship_type, is_required, sort_order, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE is_required=VALUES(is_required), sort_order=VALUES(sort_order), note=VALUES(note)
        ")->execute([$productId, $relatedId, $type, $isRequired ? 1 : 0, $sort, $note]);
        return true;
    }

    /** Related products of a given type (with live effective price). */
    public function related($productId, $type = null, $withPrice = true)
    {
        $sql = "
            SELECT r.relationship_type, r.is_required, r.note, r.sort_order,
                   p.id, p.sku, p.name, p.price, p.image_url
            FROM product_relationships r
            JOIN products p ON r.related_product_id = p.id
            WHERE r.product_id = ?";
        $args = [$productId];
        if ($type) { $sql .= " AND r.relationship_type = ?"; $args[] = $type; }
        $sql .= " ORDER BY r.is_required DESC, r.sort_order, p.name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($withPrice) {
            foreach ($rows as &$r) {
                $ep = $this->pricing->effectivePrice($r['id']);
                $r['effective_price'] = $ep['unit_effective'];
                $r['on_offer'] = $ep['applied'];
            }
        }
        return $rows;
    }

    public function components($productId)  { return $this->related($productId, 'component'); }
    public function accessories($productId) { return $this->related($productId, 'accessory'); }
    public function alternatives($productId){ return $this->related($productId, 'alternative'); }

    /**
     * Everything you'd add to a PO/quote when ordering this product:
     * required components first, then optional accessories.
     */
    public function forOrder($productId)
    {
        $all = $this->related($productId);
        return [
            'required'  => array_values(array_filter($all, fn($r) => $r['is_required'] && in_array($r['relationship_type'], ['component','accessory']))),
            'optional'  => array_values(array_filter($all, fn($r) => !$r['is_required'] && in_array($r['relationship_type'], ['accessory','compatible','bundle']))),
            'alternatives' => array_values(array_filter($all, fn($r) => $r['relationship_type'] === 'alternative')),
        ];
    }
}
