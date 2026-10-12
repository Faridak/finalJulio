<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/StockAdjustmentService.php';
require_once '../includes/AuditLog.php';

requireRole('admin');
requireScope('warehouse');

$locations = $pdo->query("SELECT id, location_name FROM inventory_locations WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$locationId = (int) ($_GET['loc'] ?? ($locations[0]['id'] ?? 1));
$notice = '';

// Approve / reject pending adjustments.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(403); die('CSRF mismatch'); }
    $svc = new StockAdjustmentService($pdo);
    $adjId = (int) ($_POST['adjustment_id'] ?? 0);
    if (($_POST['decision'] ?? '') === 'approve') {
        $r = $svc->approve($adjId, $_SESSION['user_id']);
        $notice = $r['success'] ? "Adjustment #$adjId approved — \$".number_format($r['value']??0,2)." written off." : $r['error'];
        if ($r['success']) AuditLog::record($pdo, 'inventory.writeoff_approve', 'stock_adjustment', $adjId,
            "Approved write-off #$adjId — \$".number_format($r['value']??0,2), ['value'=>$r['value']??0]);
    }
    elseif (($_POST['decision'] ?? '') === 'reject') {
        $svc->reject($adjId, $_SESSION['user_id']); $notice = "Adjustment #$adjId rejected.";
        AuditLog::record($pdo, 'inventory.writeoff_reject', 'stock_adjustment', $adjId, "Rejected write-off #$adjId");
    }
}

function scalar($pdo,$sql,$args=[]) { $s=$pdo->prepare($sql); $s->execute($args); return $s->fetchColumn(); }

// Summary metrics.
$totalBins = scalar($pdo, "SELECT COUNT(*) FROM inventory_bins b JOIN storage_racks r ON b.rack_id=r.id JOIN warehouse_zones z ON r.zone_id=z.id WHERE z.location_id=?", [$locationId]);
$counted30 = scalar($pdo, "SELECT COUNT(*) FROM inventory_bins b JOIN storage_racks r ON b.rack_id=r.id JOIN warehouse_zones z ON r.zone_id=z.id WHERE z.location_id=? AND b.last_counted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$locationId]);
$neverCounted = scalar($pdo, "SELECT COUNT(*) FROM inventory_bins b JOIN storage_racks r ON b.rack_id=r.id JOIN warehouse_zones z ON r.zone_id=z.id WHERE z.location_id=? AND b.last_counted_at IS NULL AND b.occupancy_status<>'empty'", [$locationId]);
$pendingCount = scalar($pdo, "SELECT COUNT(*) FROM inventory_adjustments WHERE status='pending_approval'");
$shrink30 = scalar($pdo, "SELECT COALESCE(SUM(value),0) FROM inventory_adjustments WHERE status='applied' AND delta<0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");

// Pending approvals.
$pending = $pdo->query("
    SELECT ia.id, ia.reason, ia.delta, ia.value, ia.notes, ia.created_at,
           pr.sku, pr.name, u.email AS counter
    FROM inventory_adjustments ia
    JOIN products pr ON ia.product_id = pr.id
    LEFT JOIN users u ON ia.counted_by = u.id
    WHERE ia.status='pending_approval' ORDER BY ia.value DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Shrinkage by reason (applied losses).
$byReason = $pdo->query("
    SELECT reason, COUNT(*) n, COALESCE(SUM(value),0) value
    FROM inventory_adjustments WHERE status='applied' AND delta<0
    GROUP BY reason ORDER BY value DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Risk-ranked count worklist (value x staleness), highest first.
$work = $pdo->prepare("
    SELECT b.id, b.bin_address, b.last_counted_at,
           DATEDIFF(NOW(), b.last_counted_at) AS days,
           (SELECT COALESCE(SUM(pa.quantity*pr.cost_price),0) FROM product_bin_assignments pa JOIN products pr ON pa.product_id=pr.id WHERE pa.bin_id=b.id AND pa.status='active') AS val
    FROM inventory_bins b
    JOIN storage_racks r ON b.rack_id=r.id JOIN warehouse_zones z ON r.zone_id=z.id
    WHERE z.location_id=? AND b.occupancy_status<>'empty'
    ORDER BY (SELECT COALESCE(SUM(pa.quantity*pr.cost_price),0) FROM product_bin_assignments pa JOIN products pr ON pa.product_id=pr.id WHERE pa.bin_id=b.id AND pa.status='active')
             * (COALESCE(DATEDIFF(NOW(), b.last_counted_at),400)+1) DESC
    LIMIT 15
");
$work->execute([$locationId]);
$worklist = $work->fetchAll(PDO::FETCH_ASSOC);

$badge = ['theft'=>'bg-red-100 text-red-800','damage'=>'bg-amber-100 text-amber-800','loss'=>'bg-orange-100 text-orange-800','count'=>'bg-blue-100 text-blue-800','expiry'=>'bg-purple-100 text-purple-800','found'=>'bg-green-100 text-green-800'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cycle Count & Loss Control - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Cycle Count &amp; Loss Control</h1>
        <a href="warehouse-3d.php?loc=<?= $locationId ?>" class="text-blue-600 hover:underline text-sm">3D warehouse →</a>
    </div>

    <?php if ($notice): ?><div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

    <!-- Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        <?php
        $cards = [
            ['Bins', $totalBins, 'text-gray-800'],
            ['Counted (30d)', $counted30, 'text-green-700'],
            ['Never counted', $neverCounted, 'text-amber-700'],
            ['Pending approvals', $pendingCount, 'text-red-700'],
            ['Shrinkage (30d)', '$'.number_format($shrink30,2), 'text-red-700'],
        ];
        foreach ($cards as $c): ?>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-xs uppercase text-gray-400"><?= $c[0] ?></div>
                <div class="text-2xl font-bold <?= $c[2] ?>"><?= is_numeric($c[1])?number_format($c[1]):$c[1] ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <!-- Pending approvals (the loss-control gate) -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Pending write-off approvals</h2>
            <?php if (empty($pending)): ?>
                <p class="text-gray-400 text-sm">Nothing awaiting approval. ✅</p>
            <?php else: foreach ($pending as $a): ?>
                <div class="border-b py-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="px-2 py-0.5 rounded-full text-xs <?= $badge[$a['reason']]??'bg-gray-100' ?>"><?= $a['reason'] ?></span>
                            <span class="font-mono text-sm ml-1"><?= htmlspecialchars($a['sku']) ?></span>
                            <span class="text-sm text-gray-600"><?= htmlspecialchars($a['name']) ?></span>
                        </div>
                        <div class="text-red-700 font-bold">$<?= number_format($a['value'],2) ?></div>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        qty <?= $a['delta'] ?> · by <?= htmlspecialchars($a['counter']??'—') ?> · <?= htmlspecialchars($a['notes']??'') ?>
                    </div>
                    <form method="POST" class="mt-2 flex gap-2">
                        <?= Security::getCSRFInput() ?>
                        <input type="hidden" name="adjustment_id" value="<?= (int)$a['id'] ?>">
                        <button name="decision" value="approve" class="bg-green-600 text-white text-xs px-3 py-1 rounded">Approve write-off</button>
                        <button name="decision" value="reject" class="bg-gray-200 text-gray-700 text-xs px-3 py-1 rounded">Reject</button>
                    </form>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Shrinkage by reason -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Where the loss is going</h2>
            <?php if (empty($byReason)): ?>
                <p class="text-gray-400 text-sm">No losses recorded yet.</p>
            <?php else:
                $max = max(array_map(fn($r)=>(float)$r['value'], $byReason)) ?: 1;
                foreach ($byReason as $r): ?>
                <div class="mb-2">
                    <div class="flex justify-between text-sm">
                        <span class="capitalize"><?= htmlspecialchars($r['reason']) ?> <span class="text-gray-400">(<?= $r['n'] ?>)</span></span>
                        <span class="font-semibold text-red-700">$<?= number_format($r['value'],2) ?></span>
                    </div>
                    <div class="w-full bg-gray-100 rounded h-2"><div class="bg-red-500 h-2 rounded" style="width: <?= round($r['value']/$max*100) ?>%"></div></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Risk-ranked worklist -->
    <div class="bg-white rounded-lg shadow p-5 mt-6">
        <h2 class="font-semibold text-gray-800 mb-1">Count worklist — highest risk first</h2>
        <p class="text-xs text-gray-500 mb-3">Ranked by stock value × time since last count (ABC cycle counting).</p>
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-400 border-b"><th class="py-2">Bin</th><th>Value at risk</th><th>Last counted</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($worklist as $w): ?>
                <tr class="border-b">
                    <td class="py-2 font-mono"><?= htmlspecialchars($w['bin_address']) ?></td>
                    <td class="font-semibold">$<?= number_format($w['val'],2) ?></td>
                    <td class="text-gray-500"><?= $w['last_counted_at'] ? htmlspecialchars(date('M j, Y', strtotime($w['last_counted_at']))) : '<span class="text-amber-600">never</span>' ?></td>
                    <td class="text-right"><a href="scan.php?mode=count&bin=<?= urlencode($w['bin_address']) ?>" class="bg-blue-600 text-white text-xs px-3 py-1 rounded">Count now</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
