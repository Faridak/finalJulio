<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/PurchaseOrderService.php';
require_once '../services/StockReplenishmentService.php';
require_once '../includes/AuditLog.php';

requireRole('admin');
requireScope('purchasing');

$notice = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(419); die('CSRF mismatch'); }
    $poId = (int) ($_POST['po_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $poSvc = new PurchaseOrderService($pdo);

    if ($action === 'approve') {
        $r = $poSvc->approve($poId, $_SESSION['user_id']);
        $notice = $r['success'] ? "PO #$poId approved — booked \$".number_format($r['total'],2)." to Accounts Payable (expected inventory)." : $r['error'];
        if ($r['success']) AuditLog::record($pdo, 'po.approve', 'purchase_order', $poId,
            "Approved PO #$poId — \$".number_format($r['total'],2)." to A/P", ['total'=>$r['total']]);
    } elseif ($action === 'pay') {
        $r = $poSvc->markPaid($poId, $_SESSION['user_id']);
        $notice = $r['success'] ? "PO #$poId paid — \$".number_format($r['paid'],2)." bank transfer recorded." : $r['error'];
        if ($r['success']) AuditLog::record($pdo, 'po.pay', 'purchase_order', $poId,
            "Paid PO #$poId — \$".number_format($r['paid'],2)." bank transfer", ['paid'=>$r['paid']]);
    } elseif ($action === 'receive') {
        // Receive every outstanding line.
        $items = $pdo->prepare("SELECT id, (quantity_ordered - quantity_received) AS outstanding FROM purchase_order_items WHERE purchase_order_id=? AND quantity_received < quantity_ordered");
        $items->execute([$poId]);
        $receipts = [];
        foreach ($items as $it) $receipts[] = ['po_item_id' => (int) $it['id'], 'quantity' => (int) $it['outstanding']];
        if ($receipts) {
            $r = (new StockReplenishmentService($pdo))->receivePurchaseOrder($poId, $receipts, $_SESSION['user_id']);
            $notice = $r['success'] ? "PO #$poId received — {$r['received']} units into inventory (In-Transit → on-hand)." : ($r['error'] ?? 'Receive failed.');
        } else $error = 'Nothing outstanding to receive.';
    }
}

$pos = $pdo->query("
    SELECT po.id, po.po_number, po.total_amount, po.status, po.approval_status, po.payment_status,
           po.order_date, po.expected_delivery_date, s.company_name,
           (SELECT COUNT(*) FROM purchase_order_items x WHERE x.purchase_order_id=po.id) items,
           (SELECT COALESCE(SUM(quantity_ordered - quantity_received),0) FROM purchase_order_items x WHERE x.purchase_order_id=po.id) outstanding
    FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id = s.id
    ORDER BY po.id DESC LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

$appBadge = ['pending'=>'bg-yellow-100 text-yellow-800','approved'=>'bg-green-100 text-green-800','rejected'=>'bg-red-100 text-red-800'];
$payBadge = ['unpaid'=>'bg-gray-100 text-gray-700','partial'=>'bg-amber-100 text-amber-800','paid'=>'bg-green-100 text-green-800'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Purchase Orders</h1>
        <div class="flex gap-2">
            <a href="reorder-alerts.php" class="text-blue-600 hover:underline text-sm self-center">Reorder alerts</a>
            <a href="create-po.php" class="bg-blue-600 text-white px-4 py-2 rounded text-sm">+ New PO</a>
        </div>
    </div>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 bg-gray-50 border-b">
                    <th class="px-4 py-3">PO #</th><th>Supplier</th><th class="text-right">Total</th>
                    <th>Approval</th><th>Payment</th><th>Fulfilment</th><th>ETA</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pos)): ?><tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No purchase orders yet.</td></tr><?php endif; ?>
                <?php foreach ($pos as $po): ?>
                <tr class="border-b">
                    <td class="px-4 py-3 font-mono"><?= htmlspecialchars($po['po_number']) ?><div class="text-xs text-gray-400"><?= (int)$po['items'] ?> items</div></td>
                    <td><?= htmlspecialchars($po['company_name'] ?? '—') ?></td>
                    <td class="text-right font-semibold">$<?= number_format($po['total_amount'],2) ?></td>
                    <td><span class="px-2 py-0.5 rounded-full text-xs <?= $appBadge[$po['approval_status']]??'' ?>"><?= $po['approval_status'] ?></span></td>
                    <td><span class="px-2 py-0.5 rounded-full text-xs <?= $payBadge[$po['payment_status']]??'' ?>"><?= $po['payment_status'] ?></span></td>
                    <td class="text-gray-600"><?= htmlspecialchars($po['status']) ?><?= $po['outstanding']>0 ? ' <span class="text-xs text-amber-600">('.$po['outstanding'].' due)</span>' : '' ?></td>
                    <td class="text-gray-500"><?= $po['expected_delivery_date'] ? htmlspecialchars(date('M j', strtotime($po['expected_delivery_date']))) : '—' ?></td>
                    <td>
                        <form method="POST" class="inline-flex gap-1">
                            <?= Security::getCSRFInput() ?>
                            <input type="hidden" name="po_id" value="<?= (int)$po['id'] ?>">
                            <?php if ($po['approval_status'] === 'pending'): ?>
                                <button name="action" value="approve" class="bg-green-600 text-white text-xs px-3 py-1 rounded">Approve</button>
                            <?php endif; ?>
                            <?php if ($po['approval_status'] === 'approved' && $po['payment_status'] !== 'paid'): ?>
                                <button name="action" value="pay" class="bg-indigo-600 text-white text-xs px-3 py-1 rounded">Pay (bank transfer)</button>
                            <?php endif; ?>
                            <?php if ($po['approval_status'] === 'approved' && $po['outstanding'] > 0): ?>
                                <button name="action" value="receive" class="bg-blue-600 text-white text-xs px-3 py-1 rounded">Receive</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 mt-3">Approve → books Accounts Payable + expected inventory · Pay → bank transfer (Dr A/P / Cr Cash) · Receive → In-Transit becomes on-hand.</p>
</div>
</body>
</html>
