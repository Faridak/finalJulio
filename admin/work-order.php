<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/WorkOrderService.php';
require_once '../includes/AuditLog.php';

requireRole('admin');
requireScope('engineering');

$svc = new WorkOrderService($pdo);
$quoteId = (int) ($_GET['quote'] ?? ($_POST['quote_id'] ?? 0));
$notice = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(419); die('CSRF mismatch'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'add_material') {
        $code = trim($_POST['sku'] ?? '');
        $pid = (int) $pdo->query("SELECT id FROM products WHERE sku=" . $pdo->quote($code) . " OR barcode=" . $pdo->quote($code) . " OR id=" . (int) $code . " LIMIT 1")->fetchColumn();
        if (!$pid) $error = "No product found for \"$code\".";
        else {
            $r = $svc->addMaterial($quoteId, $pid, (int) $_POST['qty'], $_SESSION['user_id']);
            if ($r['success']) { $notice = 'Material consumed — $' . number_format($r['line_cost'], 2) . ' COGS posted, stock reduced.'; }
            else $error = $r['error'];
        }
    } elseif ($action === 'finalize') {
        $c = $svc->finalizeQuote($quoteId, (float) $_POST['hourly_rate'], (float) $_POST['markup'], (float) $_POST['overhead']);
        $notice = 'Quote priced at $' . number_format($c['final_amount'], 2) . '.';
    } elseif ($action === 'invoice') {
        $r = $svc->invoice($quoteId);
        if ($r['success']) {
            $notice = 'Invoiced $' . number_format($r['amount'], 2) . ' → Accounts Receivable.';
            AuditLog::record($pdo, 'eng.invoice', 'quote', $quoteId, "Work order #$quoteId invoiced \$" . number_format($r['amount'], 2), ['amount' => $r['amount']]);
        } else $error = $r['error'];
    } elseif ($action === 'payment') {
        $r = $svc->receivePayment($quoteId);
        if ($r['success']) {
            $notice = 'Payment of $' . number_format($r['amount'], 2) . ' received — A/R cleared.';
            AuditLog::record($pdo, 'eng.payment', 'quote', $quoteId, "Work order #$quoteId payment \$" . number_format($r['amount'], 2), ['amount' => $r['amount']]);
        } else $error = $r['error'];
    }
}

$c = $quoteId ? $svc->costing($quoteId) : null;
if (!$c) { header('Location: engineering-board.php'); exit; }
$q = $c['quote'];
$m = fn($n) => '$' . number_format((float) $n, 2);
$paid = (new AccountingService($pdo))->isPosted('work_order_payment', $quoteId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Order #<?= $quoteId ?> - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
        <h1 class="text-2xl font-bold text-gray-900">Work Order #<?= $quoteId ?> — <?= htmlspecialchars($q['product_type']) ?></h1>
        <a href="engineering-board.php" class="text-blue-600 hover:underline text-sm">← PM Board</a>
    </div>
    <p class="text-sm text-gray-500 mb-5">Status: <b><?= htmlspecialchars($q['status']) ?></b><?= $paid ? ' · <span class="text-green-700 font-semibold">PAID ✓</span>' : '' ?><?= $q['source_order_id'] ? ' · from order #' . (int) $q['source_order_id'] : '' ?></p>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="grid md:grid-cols-3 gap-6">
        <!-- Materials (BOM) -->
        <div class="md:col-span-2 bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Materials consumed (BOM)</h2>
            <table class="w-full text-sm mb-3">
                <thead><tr class="text-left text-gray-500 border-b"><th class="py-1">SKU</th><th>Item</th><th class="text-right">Qty</th><th class="text-right">Unit cost</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    <?php if (empty($c['materials'])): ?><tr><td colspan="5" class="py-4 text-center text-gray-400">No materials yet — scan/type below.</td></tr><?php endif; ?>
                    <?php foreach ($c['materials'] as $mm): ?>
                    <tr class="border-b"><td class="py-1.5 font-mono text-xs"><?= htmlspecialchars($mm['sku']) ?></td>
                        <td><?= htmlspecialchars($mm['name']) ?></td>
                        <td class="text-right"><?= (int) $mm['quantity'] ?></td>
                        <td class="text-right"><?= $m($mm['unit_cost']) ?></td>
                        <td class="text-right font-medium"><?= $m($mm['total_cost']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($q['status'] !== 'invoiced'): ?>
            <form method="POST" class="flex gap-2 text-sm">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="add_material"><input type="hidden" name="quote_id" value="<?= $quoteId ?>">
                <input name="sku" required placeholder="SKU / barcode / product id" class="flex-1 px-3 py-2 border rounded font-mono">
                <input name="qty" type="number" min="1" value="1" class="w-20 px-3 py-2 border rounded text-right">
                <button class="bg-blue-600 text-white px-4 rounded">Consume</button>
            </form>
            <p class="text-xs text-gray-400 mt-1">Consuming posts Dr COGS / Cr Inventory at cost and reduces stock (movement type <code>work_order</code>).</p>
            <?php endif; ?>
        </div>

        <!-- Costing -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Costing & quote</h2>
            <table class="w-full text-sm mb-3">
                <tr class="border-b"><td class="py-1.5 text-gray-600">Materials</td><td class="text-right"><?= $m($c['material_cost']) ?></td></tr>
                <tr class="border-b"><td class="py-1.5 text-gray-600">Labor (<?= $c['hours'] ?>h × <?= $m($c['hourly_rate']) ?>)</td><td class="text-right"><?= $m($c['labor_cost']) ?></td></tr>
                <tr class="border-b"><td class="py-1.5 text-gray-600">Overhead</td><td class="text-right"><?= $m($c['overhead']) ?></td></tr>
                <tr class="border-b font-medium"><td class="py-1.5">Total cost</td><td class="text-right"><?= $m($c['total_cost']) ?></td></tr>
                <tr class="font-bold text-lg"><td class="py-1.5">Quote (+<?= $c['markup_pct'] ?>%)</td><td class="text-right text-blue-700"><?= $m($c['final_amount']) ?></td></tr>
            </table>

            <?php if ($q['status'] !== 'invoiced'): ?>
            <form method="POST" class="space-y-2 text-sm border-t pt-3">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="finalize"><input type="hidden" name="quote_id" value="<?= $quoteId ?>">
                <div class="grid grid-cols-3 gap-2">
                    <label class="text-xs text-gray-500">Rate $/h<input name="hourly_rate" value="<?= $c['hourly_rate'] ?>" class="w-full px-2 py-1 border rounded"></label>
                    <label class="text-xs text-gray-500">Markup %<input name="markup" value="<?= $c['markup_pct'] ?>" class="w-full px-2 py-1 border rounded"></label>
                    <label class="text-xs text-gray-500">Overhead $<input name="overhead" value="<?= $c['overhead'] ?>" class="w-full px-2 py-1 border rounded"></label>
                </div>
                <button class="w-full bg-gray-800 text-white py-2 rounded">Recalculate quote</button>
            </form>
            <form method="POST" class="mt-2">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="invoice"><input type="hidden" name="quote_id" value="<?= $quoteId ?>">
                <button class="w-full bg-indigo-600 text-white py-2 rounded" onclick="return confirm('Invoice <?= $m($c['final_amount']) ?> to the customer (posts to A/R)?')">🧾 Invoice → A/R</button>
            </form>
            <?php elseif (!$paid): ?>
            <form method="POST" class="mt-2">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="payment"><input type="hidden" name="quote_id" value="<?= $quoteId ?>">
                <button class="w-full bg-green-600 text-white py-2 rounded" onclick="return confirm('Record payment of <?= $m($q['final_quote_amount']) ?>?')">💰 Record payment received</button>
            </form>
            <?php else: ?>
            <div class="bg-green-50 border border-green-300 text-green-800 rounded p-3 text-sm text-center">Invoiced & paid ✓ — <?= $m($q['final_quote_amount']) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
