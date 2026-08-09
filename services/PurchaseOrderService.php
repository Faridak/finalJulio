<?php
/**
 * PurchaseOrderService — create POs against a supplier's own catalog.
 *
 * Ordering uses the SUPPLIER's SKU (supplier_products.supplier_sku), never our
 * internal SKU. Each line still records our product_id so that receiving maps
 * the goods straight into our inventory (StockReplenishmentService::receivePurchaseOrder).
 */
require_once __DIR__ . '/AccountingService.php';

class PurchaseOrderService
{
    private $pdo;
    private $accounting;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->accounting = new AccountingService($pdo);
    }

    /**
     * @param array $lines  [ ['supplier_product_id'=>int, 'qty'=>int], ... ]
     * @return array{success:bool, po_id?:int, po_number?:int|string, total?:float, error?:string}
     */
    public function create($supplierId, $locationId, array $lines, $userId = null, $notes = null)
    {
        $sup = $this->pdo->prepare("SELECT id, company_name, lead_time_days FROM suppliers WHERE id=? LIMIT 1");
        $sup->execute([$supplierId]);
        $supplier = $sup->fetch(PDO::FETCH_ASSOC);
        if (!$supplier) return ['success' => false, 'error' => 'Supplier not found.'];

        // Resolve lines against the supplier's catalog (by supplier SKU).
        $resolved = [];
        $subtotal = 0;
        foreach ($lines as $l) {
            $spId = (int) ($l['supplier_product_id'] ?? 0);
            $qty = (int) ($l['qty'] ?? 0);
            if ($spId <= 0 || $qty <= 0) continue;

            $sp = $this->pdo->prepare("
                SELECT id, product_id, supplier_sku, product_name, cost_price
                FROM supplier_products WHERE id=? AND supplier_id=? LIMIT 1
            ");
            $sp->execute([$spId, $supplierId]);
            $row = $sp->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue;

            $lineTotal = round($row['cost_price'] * $qty, 2);
            $subtotal += $lineTotal;
            $resolved[] = [
                'supplier_product_id' => (int) $row['id'],
                'product_id'          => $row['product_id'] ? (int) $row['product_id'] : null,
                'supplier_sku'        => $row['supplier_sku'],
                'product_name'        => $row['product_name'],
                'unit_cost'           => (float) $row['cost_price'],
                'qty'                 => $qty,
                'total'               => $lineTotal,
            ];
        }
        if (empty($resolved)) return ['success' => false, 'error' => 'No valid supplier items on the order.'];

        try {
            $this->pdo->beginTransaction();

            $poNumber = 'PO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $lead = (int) ($supplier['lead_time_days'] ?? 7);

            $this->pdo->prepare("
                INSERT INTO purchase_orders
                    (po_number, supplier_id, location_id, order_date, expected_delivery_date,
                     subtotal, total_amount, status, notes, created_by, created_at)
                VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), ?, ?, 'sent', ?, ?, NOW())
            ")->execute([$poNumber, $supplierId, $locationId, $lead, $subtotal, $subtotal, $notes, $userId]);
            $poId = (int) $this->pdo->lastInsertId();

            $item = $this->pdo->prepare("
                INSERT INTO purchase_order_items
                    (purchase_order_id, supplier_product_id, product_id, product_name, supplier_sku,
                     quantity_ordered, quantity_received, unit_cost, total_cost, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 'pending', NOW())
            ");
            foreach ($resolved as $r) {
                $item->execute([$poId, $r['supplier_product_id'], $r['product_id'], $r['product_name'],
                                $r['supplier_sku'], $r['qty'], $r['unit_cost'], $r['total']]);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('createPO failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Could not create the purchase order.'];
        }

        return ['success' => true, 'po_id' => $poId, 'po_number' => $poNumber, 'total' => round($subtotal, 2)];
    }

    /**
     * Approve a PO: it becomes a commitment + an Accounts Payable bill, and the
     * ordered goods show as expected (Inventory In-Transit).
     *   Dr Inventory In-Transit (1250) / Cr Accounts Payable (2000)
     */
    public function approve($poId, $userId)
    {
        $po = $this->pdo->prepare("
            SELECT po.id, po.po_number, po.total_amount, po.approval_status, po.supplier_id,
                   s.company_name, s.payment_terms
            FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id = s.id
            WHERE po.id = ? LIMIT 1
        ");
        $po->execute([$poId]);
        $order = $po->fetch(PDO::FETCH_ASSOC);
        if (!$order) return ['success' => false, 'error' => 'PO not found.'];
        if ($order['approval_status'] === 'approved') return ['success' => false, 'error' => 'Already approved.'];

        $total = (float) $order['total_amount'];
        $dueDays = preg_match('/net_(\d+)/', (string) $order['payment_terms'], $m) ? (int) $m[1] : 0;

        try {
            $this->pdo->beginTransaction();

            // Accounts Payable bill.
            $this->pdo->prepare("
                INSERT INTO accounts_payable (vendor_name, invoice_number, invoice_date, due_date, amount, paid_amount, status, description, created_at)
                VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), ?, 0, 'pending', ?, NOW())
            ")->execute([$order['company_name'] ?? 'Supplier', $order['po_number'], $dueDays, $total, 'Purchase order ' . $order['po_number']]);
            $apId = (int) $this->pdo->lastInsertId();

            $this->pdo->prepare("
                UPDATE purchase_orders SET approval_status='approved', status='confirmed', approved_by=?, approved_at=NOW(), ap_id=? WHERE id=?
            ")->execute([$userId, $apId, $poId]);

            if ($total > 0) {
                $this->accounting->postJournal(
                    [['code' => '1250', 'debit' => $total], ['code' => '2000', 'credit' => $total]],
                    'PO approved ' . $order['po_number'] . ' (expected inventory)', 'purchase_order', $poId);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
        return ['success' => true, 'ap_id' => $apId, 'total' => $total];
    }

    /**
     * Record the bank transfer paying a PO.
     *   Dr Accounts Payable (2000) / Cr Cash (1000)
     */
    public function markPaid($poId, $userId)
    {
        $po = $this->pdo->prepare("SELECT id, po_number, total_amount, ap_id, payment_status, approval_status FROM purchase_orders WHERE id=? LIMIT 1");
        $po->execute([$poId]);
        $order = $po->fetch(PDO::FETCH_ASSOC);
        if (!$order) return ['success' => false, 'error' => 'PO not found.'];
        if ($order['approval_status'] !== 'approved') return ['success' => false, 'error' => 'Approve the PO first.'];
        if ($order['payment_status'] === 'paid') return ['success' => false, 'error' => 'Already paid.'];

        $total = (float) $order['total_amount'];
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare("UPDATE purchase_orders SET payment_status='paid', paid_at=NOW() WHERE id=?")->execute([$poId]);
            if ($order['ap_id']) {
                $this->pdo->prepare("UPDATE accounts_payable SET status='paid', paid_amount=amount WHERE id=?")->execute([$order['ap_id']]);
            }
            if ($total > 0) {
                $this->accounting->postJournal(
                    [['code' => '2000', 'debit' => $total], ['code' => '1000', 'credit' => $total]],
                    'PO paid ' . $order['po_number'] . ' (bank transfer)', 'purchase_order_payment', $poId);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
        return ['success' => true, 'paid' => $total];
    }
}
