<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/ShippingCalculatorService.php';
require_once '../includes/AuditLog.php';

requireRole('admin');
requireScope('orders');

$notice = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(419); die('CSRF mismatch'); }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_rule') {
            $id = (int) ($_POST['rule_id'] ?? 0);
            $vals = [
                (int) $_POST['zone_id'], (float) $_POST['weight_min_kg'], (float) $_POST['weight_max_kg'],
                (float) $_POST['base_cost'], (float) $_POST['cost_per_kg'], (float) $_POST['cost_per_km'],
                (float) $_POST['fuel_surcharge_rate'], (float) $_POST['insurance_rate'],
                (float) $_POST['free_shipping_threshold'], isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($id) {
                $pdo->prepare("UPDATE shipping_rate_rules SET zone_id=?, weight_min_kg=?, weight_max_kg=?, base_cost=?, cost_per_kg=?, cost_per_km=?, fuel_surcharge_rate=?, insurance_rate=?, free_shipping_threshold=?, is_active=? WHERE id=?")
                    ->execute(array_merge($vals, [$id]));
                $notice = "Rule #$id updated.";
            } else {
                $prov = $pdo->query("SELECT id FROM shipping_providers LIMIT 1")->fetchColumn();
                $svc = $pdo->query("SELECT id FROM shipping_services LIMIT 1")->fetchColumn();
                $typ = $pdo->query("SELECT id FROM shipping_types LIMIT 1")->fetchColumn();
                $pdo->prepare("INSERT INTO shipping_rate_rules (provider_id,service_id,shipping_type_id,zone_id,weight_min_kg,weight_max_kg,base_cost,cost_per_kg,cost_per_km,fuel_surcharge_rate,insurance_rate,free_shipping_threshold,is_active,effective_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,CURDATE())")
                    ->execute(array_merge([$prov, $svc, $typ], $vals));
                $notice = 'Rule added.';
            }
            AuditLog::record($pdo, 'shipping.rate_update', 'rate_rule', $id ?: $pdo->lastInsertId(),
                "Shipping rate rule " . ($id ? "#$id updated" : 'added') . " (zone {$_POST['zone_id']})");
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$zones = $pdo->query("SELECT id, name FROM shipping_zones ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
$rules = $pdo->query("SELECT * FROM shipping_rate_rules WHERE is_active=1 ORDER BY zone_id, weight_min_kg")->fetchAll(PDO::FETCH_ASSOC);

// ---- Test calculator ----
$test = null;
if (isset($_GET['test_pid'])) {
    $calc = new ShippingCalculatorService($pdo);
    $test = $calc->quote(
        [['product_id' => (int) $_GET['test_pid'], 'quantity' => max(1, (int) ($_GET['test_qty'] ?? 1))]],
        $_GET['test_addr'] ?? '', strtoupper($_GET['test_country'] ?? 'MX'), (float) ($_GET['test_subtotal'] ?? 0)
    );
}
$m = fn($n) => '$' . number_format((float) $n, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Rates - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Shipping Rates — Our Tariff</h1>
    <p class="text-sm text-gray-500 mb-6">DHL-style: chargeable weight = max(actual, L×W×H÷5000) · price = base + $/kg + $/km (Google Maps distance from the warehouse) + fuel %. Envia provides the label; the price is ours.</p>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Test calculator -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <h2 class="font-semibold text-gray-800 mb-2">Rate tester</h2>
        <form method="GET" class="flex flex-wrap gap-2 items-end text-sm">
            <div><label class="block text-xs text-gray-500">Product ID</label><input name="test_pid" value="<?= (int) ($_GET['test_pid'] ?? 1) ?>" class="px-2 py-1.5 border rounded w-24"></div>
            <div><label class="block text-xs text-gray-500">Qty</label><input name="test_qty" type="number" value="<?= (int) ($_GET['test_qty'] ?? 1) ?>" min="1" class="px-2 py-1.5 border rounded w-16"></div>
            <div><label class="block text-xs text-gray-500">Country</label>
                <select name="test_country" class="px-2 py-1.5 border rounded">
                    <?php foreach (['MX','US','CA','GB','ES','CN','BR'] as $cc): ?><option <?= ($_GET['test_country'] ?? 'MX') === $cc ? 'selected' : '' ?>><?= $cc ?></option><?php endforeach; ?>
                </select></div>
            <div class="flex-1 min-w-[16rem]"><label class="block text-xs text-gray-500">Destination address (for Google distance)</label>
                <input name="test_addr" value="<?= htmlspecialchars($_GET['test_addr'] ?? '') ?>" placeholder="Av. Reforma 100, Monterrey, NL" class="w-full px-2 py-1.5 border rounded"></div>
            <div><label class="block text-xs text-gray-500">Order subtotal $</label><input name="test_subtotal" value="<?= htmlspecialchars($_GET['test_subtotal'] ?? '0') ?>" class="px-2 py-1.5 border rounded w-24"></div>
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Quote</button>
        </form>
        <?php if ($test): ?>
        <div class="mt-3 bg-gray-50 rounded p-3 text-sm">
            <?php if ($test['free']): ?>
                <b class="text-green-700">FREE shipping</b> — subtotal cleared the $<?= number_format($test['breakdown']['free_threshold'], 2) ?> threshold.
            <?php else: ?>
                <b class="text-lg"><?= $m($test['total']) ?></b> —
                weight: actual <?= $test['weight']['actual'] ?>kg / volumetric <?= $test['weight']['volumetric'] ?>kg →
                <b>chargeable <?= $test['weight']['chargeable'] ?>kg</b> ·
                distance <?= $test['distance_km'] ?> km (<?= $test['distance_source'] ?>) ·
                <?php if (empty($test['breakdown']['fallback'])): ?>
                    base <?= $m($test['breakdown']['base']) ?> + weight <?= $m($test['breakdown']['weight_cost']) ?> + distance <?= $m($test['breakdown']['distance_cost']) ?> + fuel <?= $test['breakdown']['fuel_pct'] ?>% (<?= $m($test['breakdown']['fuel']) ?>)
                <?php else: ?><span class="text-red-600">no rule for this zone — fallback pricing used</span><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Rules table -->
    <div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 bg-gray-50 border-b">
                <th class="px-3 py-2">Zone</th><th>Weight kg</th><th class="text-right">Base</th>
                <th class="text-right">$/kg</th><th class="text-right">$/km</th>
                <th class="text-right">Fuel %</th><th class="text-right">Ins %</th>
                <th class="text-right">Free ></th><th></th>
            </tr></thead>
            <tbody>
                <?php foreach ($rules as $r): ?>
                <form method="POST">
                <tr class="border-b">
                    <?= Security::getCSRFInput() ?>
                    <input type="hidden" name="action" value="save_rule"><input type="hidden" name="rule_id" value="<?= (int) $r['id'] ?>">
                    <td class="px-3 py-1.5"><select name="zone_id" class="border rounded px-1 py-1"><?php foreach ($zones as $zid => $zn): ?><option value="<?= $zid ?>" <?= $r['zone_id'] == $zid ? 'selected' : '' ?>><?= htmlspecialchars($zn) ?></option><?php endforeach; ?></select></td>
                    <td><input name="weight_min_kg" value="<?= $r['weight_min_kg'] + 0 ?>" class="w-14 border rounded px-1 py-1 text-right"> – <input name="weight_max_kg" value="<?= $r['weight_max_kg'] + 0 ?>" class="w-14 border rounded px-1 py-1 text-right"></td>
                    <td class="text-right"><input name="base_cost" value="<?= $r['base_cost'] + 0 ?>" class="w-16 border rounded px-1 py-1 text-right"></td>
                    <td class="text-right"><input name="cost_per_kg" value="<?= $r['cost_per_kg'] + 0 ?>" class="w-16 border rounded px-1 py-1 text-right"></td>
                    <td class="text-right"><input name="cost_per_km" value="<?= $r['cost_per_km'] + 0 ?>" class="w-16 border rounded px-1 py-1 text-right"></td>
                    <td class="text-right"><input name="fuel_surcharge_rate" value="<?= $r['fuel_surcharge_rate'] + 0 ?>" class="w-12 border rounded px-1 py-1 text-right"></td>
                    <td class="text-right"><input name="insurance_rate" value="<?= $r['insurance_rate'] + 0 ?>" class="w-12 border rounded px-1 py-1 text-right"></td>
                    <td class="text-right"><input name="free_shipping_threshold" value="<?= $r['free_shipping_threshold'] + 0 ?>" class="w-16 border rounded px-1 py-1 text-right"></td>
                    <td class="px-2 whitespace-nowrap"><label class="text-xs"><input type="checkbox" name="is_active" checked> on</label> <button class="bg-gray-800 text-white text-xs px-2 py-1 rounded">Save</button></td>
                </tr>
                </form>
                <?php endforeach; ?>
                <!-- new rule row -->
                <form method="POST">
                <tr class="bg-blue-50">
                    <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="save_rule">
                    <td class="px-3 py-1.5"><select name="zone_id" class="border rounded px-1 py-1"><?php foreach ($zones as $zid => $zn): ?><option value="<?= $zid ?>"><?= htmlspecialchars($zn) ?></option><?php endforeach; ?></select></td>
                    <td><input name="weight_min_kg" value="0" class="w-14 border rounded px-1 py-1 text-right"> – <input name="weight_max_kg" value="999" class="w-14 border rounded px-1 py-1 text-right"></td>
                    <td class="text-right"><input name="base_cost" placeholder="base" class="w-16 border rounded px-1 py-1 text-right"></td>
                    <td class="text-right"><input name="cost_per_kg" placeholder="/kg" class="w-16 border rounded px-1 py-1 text-right"></td>
                    <td class="text-right"><input name="cost_per_km" placeholder="/km" value="0" class="w-16 border rounded px-1 py-1 text-right"></td>
                    <td class="text-right"><input name="fuel_surcharge_rate" value="10" class="w-12 border rounded px-1 py-1 text-right"></td>
                    <td class="text-right"><input name="insurance_rate" value="1" class="w-12 border rounded px-1 py-1 text-right"></td>
                    <td class="text-right"><input name="free_shipping_threshold" value="0" class="w-16 border rounded px-1 py-1 text-right"></td>
                    <td class="px-2"><input type="hidden" name="is_active" value="1"><button class="bg-blue-600 text-white text-xs px-2 py-1 rounded">＋ Add</button></td>
                </tr>
                </form>
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400">Distance uses the Google Maps Distance Matrix once <code>GOOGLE_MAPS_API_KEY</code> is set in .env (until then a per-country estimate is used). Origin = <code>SHIPPING_ORIGIN_ADDRESS</code>. Overseas zones price with $/km = 0 (zone-flat).</p>
</div>
</body>
</html>
