<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireRole('admin');
requireScope('finance');

// Filters
$fAction = trim($_GET['action'] ?? '');
$fUser   = trim($_GET['user'] ?? '');
$fFrom   = $_GET['from'] ?? '';
$fTo     = $_GET['to'] ?? '';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$per     = 50;

$where = ['1=1']; $args = [];
if ($fAction !== '') { $where[] = 'action = ?'; $args[] = $fAction; }
if ($fUser !== '')   { $where[] = '(user_email LIKE ? OR user_id = ?)'; $args[] = "%$fUser%"; $args[] = (int) $fUser; }
if ($fFrom !== '')   { $where[] = 'created_at >= ?'; $args[] = $fFrom . ' 00:00:00'; }
if ($fTo !== '')     { $where[] = 'created_at <= ?'; $args[] = $fTo . ' 23:59:59'; }
$w = implode(' AND ', $where);

$total = (int) (function() use ($pdo, $w, $args) { $s = $pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE $w"); $s->execute($args); return $s->fetchColumn(); })();
$pages = max(1, (int) ceil($total / $per));
$page = min($page, $pages);
$offset = ($page - 1) * $per;

$stmt = $pdo->prepare("SELECT * FROM audit_log WHERE $w ORDER BY id DESC LIMIT $per OFFSET $offset");
$stmt->execute($args);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct actions for the filter dropdown.
$actions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// CSV export of the filtered set.
if (($_GET['export'] ?? '') === 'csv') {
    $all = $pdo->prepare("SELECT created_at, user_email, action, entity_type, entity_id, summary, ip_address FROM audit_log WHERE $w ORDER BY id DESC");
    $all->execute($args);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit-log-'.date('Ymd').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['When', 'User', 'Action', 'Entity', 'Entity ID', 'Summary', 'IP']);
    foreach ($all as $r) fputcsv($out, $r);
    fclose($out); exit;
}

$actionColor = function($a) {
    if (strpos($a, 'payout') !== false || strpos($a, 'pay') !== false) return 'bg-indigo-100 text-indigo-800';
    if (strpos($a, 'price') !== false) return 'bg-amber-100 text-amber-800';
    if (strpos($a, 'writeoff') !== false) return 'bg-red-100 text-red-800';
    if (strpos($a, 'approve') !== false) return 'bg-green-100 text-green-800';
    return 'bg-gray-100 text-gray-700';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-gray-900">Audit Log</h1>
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export'=>'csv']))) ?>" class="bg-green-600 text-white px-4 py-1.5 rounded text-sm">⬇ Export CSV</a>
    </div>
    <p class="text-sm text-gray-500 mb-5">Append-only record of sensitive admin actions — price changes, payouts, write-off approvals, PO approve/pay. <b><?= number_format($total) ?></b> event(s).</p>

    <form method="GET" class="flex flex-wrap gap-2 items-end mb-5 text-sm bg-white rounded-lg shadow p-3">
        <div><label class="block text-xs text-gray-500">Action</label>
            <select name="action" class="px-2 py-1 border rounded">
                <option value="">All</option>
                <?php foreach ($actions as $a): ?><option value="<?= htmlspecialchars($a) ?>" <?= $fAction===$a?'selected':'' ?>><?= htmlspecialchars($a) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label class="block text-xs text-gray-500">User (email or id)</label><input name="user" value="<?= htmlspecialchars($fUser) ?>" class="px-2 py-1 border rounded"></div>
        <div><label class="block text-xs text-gray-500">From</label><input type="date" name="from" value="<?= htmlspecialchars($fFrom) ?>" class="px-2 py-1 border rounded"></div>
        <div><label class="block text-xs text-gray-500">To</label><input type="date" name="to" value="<?= htmlspecialchars($fTo) ?>" class="px-2 py-1 border rounded"></div>
        <button class="bg-gray-800 text-white px-4 py-1.5 rounded">Filter</button>
        <a href="audit-log.php" class="text-blue-600 hover:underline">Reset</a>
    </form>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 bg-gray-50 border-b">
                <th class="px-4 py-2">When</th><th>User</th><th>Action</th><th>Detail</th><th>IP</th>
            </tr></thead>
            <tbody>
                <?php if (empty($rows)): ?><tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No audit events for these filters.</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr class="border-b align-top">
                    <td class="px-4 py-2 whitespace-nowrap text-gray-500"><?= htmlspecialchars(date('M j, Y H:i', strtotime($r['created_at']))) ?></td>
                    <td class="py-2"><?= htmlspecialchars($r['user_email'] ?? ('#'.$r['user_id'])) ?: '<em>system</em>' ?></td>
                    <td class="py-2"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $actionColor($r['action']) ?>"><?= htmlspecialchars($r['action']) ?></span></td>
                    <td class="py-2"><?= htmlspecialchars($r['summary'] ?? '') ?>
                        <?php if ($r['entity_type']): ?><span class="text-xs text-gray-400"><?= htmlspecialchars($r['entity_type'].' #'.$r['entity_id']) ?></span><?php endif; ?></td>
                    <td class="py-2 text-gray-400 font-mono text-xs"><?= htmlspecialchars($r['ip_address'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="flex justify-center gap-1 mt-6">
        <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
            <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page'=>$i]))) ?>" class="px-3 py-1 rounded text-sm <?= $i==$page?'bg-blue-600 text-white':'bg-white border text-gray-700' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
