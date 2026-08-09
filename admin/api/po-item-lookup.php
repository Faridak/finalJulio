<?php
/**
 * PO item lookup — scan a barcode / item number and auto-fill line details.
 * Resolves our barcode/SKU or a supplier SKU to the item + its supplier terms.
 */
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../services/SkuBarcodeService.php';

header('Content-Type: application/json');
if (!isLoggedIn() || getUserRole() !== 'admin') { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$code = trim($_GET['code'] ?? '');
$supplierId = (int) ($_GET['supplier_id'] ?? 0);
if ($code === '') { echo json_encode(['found' => false]); exit; }

// 1) direct supplier catalog match (supplier SKU) — scoped to supplier when given.
$sql = "SELECT sp.id AS supplier_product_id, sp.supplier_id, sp.supplier_sku, sp.product_id, sp.product_name,
               sp.cost_price, sp.available_quantity, s.company_name, s.lead_time_days, s.customs_clearance_days,
               pr.sku AS our_sku, pr.barcode
        FROM supplier_products sp
        JOIN suppliers s ON sp.supplier_id = s.id
        LEFT JOIN products pr ON sp.product_id = pr.id
        WHERE sp.status='active' AND (sp.supplier_sku = ? OR pr.sku = ? OR pr.barcode = ?)";
$args = [$code, $code, $code];
if ($supplierId) { $sql .= " AND sp.supplier_id = ?"; $args[] = $supplierId; }
$sql .= " LIMIT 1";
$st = $pdo->prepare($sql);
$st->execute($args);
$row = $st->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode(['found' => true] + $row);
    exit;
}

// 2) fall back to our product (barcode/SKU) even if not linked to this supplier.
$sku = new SkuBarcodeService($pdo);
$p = $sku->findByBarcode($code);
if ($p) {
    echo json_encode(['found' => true, 'product_id' => $p['id'], 'our_sku' => $p['sku'],
                      'product_name' => $p['name'], 'cost_price' => $p['price'], 'supplier_product_id' => null,
                      'note' => 'Product found but not in this supplier catalog']);
    exit;
}

echo json_encode(['found' => false]);
