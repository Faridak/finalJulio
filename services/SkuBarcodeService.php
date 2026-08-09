<?php
/**
 * SkuBarcodeService — internal identity for OUR products.
 *
 * Only 'own' products get an internal SKU + barcode so they can be scanned for
 * inventory and sales. Format mirrors the legacy scheme:
 *     [LOC2][FAM3]-[SEQ3]      e.g.  MX + LRF + 001  ->  MXLRF-001
 *   - LOC : 2-letter location/country code (default MX)
 *   - FAM : 3-letter product family (from category/name, or supplied)
 *   - SEQ : zero-padded running number within that LOC+FAM prefix
 *
 * The barcode value is the SKU itself (Code128-encodable, alphanumeric).
 * Sale price = supplier cost + margin.
 */
class SkuBarcodeService
{
    private $pdo;
    const DEFAULT_LOCATION = 'MX';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Generate the next internal SKU for a LOC+FAM prefix.
     */
    public function generateSku($family, $location = self::DEFAULT_LOCATION)
    {
        $loc = strtoupper(preg_replace('/[^A-Za-z]/', '', $location));
        $loc = substr(str_pad($loc, 2, 'X'), 0, 2);

        $fam = strtoupper(preg_replace('/[^A-Za-z]/', '', $family ?: 'GEN'));
        $fam = substr(str_pad($fam, 3, 'X'), 0, 3);

        $prefix = $loc . $fam;                 // e.g. MXLRF

        // Highest existing sequence for this prefix.
        $stmt = $this->pdo->prepare("
            SELECT sku FROM products
            WHERE sku REGEXP ?
            ORDER BY sku DESC LIMIT 1
        ");
        $stmt->execute(['^' . $prefix . '-[0-9]+$']);
        $last = $stmt->fetchColumn();
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1)) + 1 : 1;

        return sprintf('%s-%03d', $prefix, $seq);
    }

    /**
     * Assign an internal SKU + barcode to an OWN product that lacks them.
     * Family is derived from category (or name) unless provided.
     *
     * @return array{success:bool, sku?:string, barcode?:string, error?:string}
     */
    public function assignInternalIdentity($productId, $family = null, $location = self::DEFAULT_LOCATION)
    {
        $stmt = $this->pdo->prepare("SELECT id, ownership, sku, barcode, category, name FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            return ['success' => false, 'error' => 'Product not found.'];
        }
        if ($p['ownership'] !== 'own') {
            return ['success' => false, 'error' => 'Only own products get an internal SKU/barcode.'];
        }

        // Keep an existing valid internal SKU; only fill what's missing.
        $sku = $p['sku'];
        if (!$sku || !preg_match('/^[A-Z]{2}[A-Z]{3}-\d+$/', $sku)) {
            $fam = $family ?: $this->familyFrom($p['category'] ?: $p['name']);
            $sku = $this->generateSku($fam, $location);
        }
        $barcode = $p['barcode'] ?: $sku;

        $upd = $this->pdo->prepare("UPDATE products SET sku = ?, barcode = ? WHERE id = ?");
        $upd->execute([$sku, $barcode, $productId]);

        return ['success' => true, 'sku' => $sku, 'barcode' => $barcode];
    }

    /**
     * Sale price = supplier cost + margin. Optionally persists price + margin.
     */
    public function priceWithMargin($cost, $marginPercent, $productId = null)
    {
        $price = round(((float) $cost) * (1 + ((float) $marginPercent) / 100), 2);
        if ($productId !== null) {
            $this->pdo->prepare("UPDATE products SET price = ?, margin_percentage = ? WHERE id = ?")
                      ->execute([$price, $marginPercent, $productId]);
        }
        return $price;
    }

    /** Resolve a product by scanned barcode or SKU. */
    public function findByBarcode($code)
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, sku, barcode, price, ownership FROM products
            WHERE barcode = ? OR sku = ? LIMIT 1
        ");
        $stmt->execute([$code, $code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** 3-letter family code from a category or product name. */
    private function familyFrom($text)
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $text));
        return $letters !== '' ? substr(str_pad($letters, 3, 'X'), 0, 3) : 'GEN';
    }
}
