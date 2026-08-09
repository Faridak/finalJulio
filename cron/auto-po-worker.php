<?php
/**
 * Auto-draft purchase orders from reorder alerts. Run nightly:
 *   php cron/auto-po-worker.php
 *
 * For each location, groups alert items by supplier and creates ONE draft PO
 * per supplier (approval_status stays pending — an admin approves in
 * admin/po-manage.php). Items already covered by an open PO are skipped by
 * the alert math itself (on-order counts toward availability).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/ReorderService.php';
require_once __DIR__ . '/../services/PurchaseOrderService.php';
require_once __DIR__ . '/../includes/AuditLog.php';

$reorder = new ReorderService($pdo);
$poSvc = new PurchaseOrderService($pdo);

$locations = $pdo->query("SELECT id FROM inventory_locations WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN);
$created = 0; $skipped = 0;

foreach ($locations as $locId) {
    $alerts = $reorder->alerts((int) $locId);

    // Group by supplier; resolve each product to the supplier's catalog row.
    $bySupplier = [];
    foreach ($alerts as $a) {
        if (empty($a['supplier_id'])) { $skipped++; continue; }
        $sp = $pdo->prepare("SELECT id FROM supplier_products WHERE supplier_id=? AND product_id=? AND status='active' LIMIT 1");
        $sp->execute([(int) $a['supplier_id'], (int) $a['product_id']]);
        $spId = (int) $sp->fetchColumn();
        if (!$spId) { $skipped++; continue; }
        $bySupplier[(int) $a['supplier_id']][] = ['supplier_product_id' => $spId, 'qty' => max(1, (int) $a['suggest_qty'])];
    }

    foreach ($bySupplier as $supplierId => $lines) {
        $r = $poSvc->create($supplierId, (int) $locId, $lines, null,
            'AUTO-DRAFT from reorder alerts ' . date('Y-m-d') . ' — review & approve');
        if (!empty($r['success'])) {
            $created++;
            AuditLog::record($pdo, 'po.auto_draft', 'purchase_order', $r['po_id'] ?? null,
                "Auto-drafted {$r['po_number']} (supplier #$supplierId, " . count($lines) . " line(s), \$" . number_format($r['total'], 2) . ")",
                ['supplier_id' => $supplierId, 'lines' => count($lines), 'total' => $r['total']]);
            echo "  drafted {$r['po_number']}: supplier #$supplierId, " . count($lines) . " line(s), \$" . number_format($r['total'], 2) . "\n";
        }
    }
}
echo date('c') . " auto-po: drafted $created PO(s), skipped $skipped alert item(s) without supplier link\n";
