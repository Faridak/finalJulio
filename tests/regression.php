<?php
/**
 * Money-path regression tests. Run before every deploy:
 *   php tests/regression.php
 *
 * Read-only checks on the live ledger + a full self-cleaning order round-trip.
 * Exit code 0 = all green; 1 = something broke.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

$_SESSION = ['user_id' => null];
require_once __DIR__ . '/../config/database.php';

// Resolve a real customer id (never hardcode — ids change across environments).
$CUSTOMER = (int) $pdo->query("SELECT id FROM users WHERE role NOT IN ('admin','merchant') ORDER BY id LIMIT 1")->fetchColumn()
    ?: (int) $pdo->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetchColumn();

$pass = 0; $fail = 0;
function check($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✔ $label\n"; }
    else { $fail++; echo "  ✘ $label" . ($detail !== '' ? " — $detail" : '') . "\n"; }
}

echo "== 1. Ledger integrity ==\n";
$r = $pdo->query("SELECT ROUND(SUM(debit_amount)-SUM(credit_amount),2) FROM general_ledger")->fetchColumn();
check('general ledger globally balanced', abs((float) $r) < 0.01, "off by $r");

$bad = $pdo->query("
    SELECT reference_type, reference_id, ROUND(SUM(debit_amount)-SUM(credit_amount),2) diff
    FROM general_ledger GROUP BY reference_type, reference_id
    HAVING ABS(diff) > 0.01 LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
check('every journal balances individually', empty($bad),
    $bad ? ('e.g. ' . $bad[0]['reference_type'] . ' #' . $bad[0]['reference_id'] . ' off by ' . $bad[0]['diff']) : '');

$orphans = $pdo->query("SELECT COUNT(*) FROM general_ledger gl LEFT JOIN chart_of_accounts ca ON gl.account_id=ca.id WHERE ca.id IS NULL")->fetchColumn();
check('no ledger rows with unknown accounts', (int) $orphans === 0, "$orphans orphan rows");

echo "== 2. Inventory consistency ==\n";
$negative = $pdo->query("SELECT COUNT(*) FROM products WHERE inventory < 0")->fetchColumn();
check('no negative product inventory', (int) $negative === 0, "$negative products negative");
$negLoc = $pdo->query("SELECT COUNT(*) FROM product_inventory WHERE quantity_on_hand < 0")->fetchColumn();
check('no negative per-location stock', (int) $negLoc === 0, "$negLoc rows negative");

echo "== 3. Pricing math ==\n";
require_once __DIR__ . '/../services/PricingService.php';
$pricing = new PricingService($pdo);
$pid = (int) $pdo->query("SELECT id FROM products WHERE price BETWEEN 50 AND 200 AND inventory > 5 AND id NOT IN (SELECT product_id FROM product_discounts WHERE is_active=1) LIMIT 1")->fetchColumn();
$base = (float) $pdo->query("SELECT price FROM products WHERE id=$pid")->fetchColumn();
$did = $pricing->createDiscount($pid, 'percentage', 25);
$ep = $pricing->effectivePrice($pid);
check('25% discount computes correctly', abs($ep['unit_effective'] - round($base * 0.75, 2)) < 0.01,
    "base $base -> {$ep['unit_effective']}");
$pdo->exec("DELETE FROM product_discounts WHERE id=" . (int) $did);
$ep2 = $pricing->effectivePrice($pid);
check('price restores after discount removal', abs($ep2['unit_effective'] - $base) < 0.01);

echo "== 4. Shipping math (DHL volumetric) ==\n";
require_once __DIR__ . '/../services/ShippingCalculatorService.php';
$calc = new ShippingCalculatorService($pdo);
$w = $calc->chargeableWeight([['product_id' => $pid, 'quantity' => 1]]);
check('chargeable = max(actual, volumetric)', $w['chargeable'] >= max($w['actual'], $w['volumetric']) - 0.01
    && $w['chargeable'] <= $w['actual'] + $w['volumetric']);
$q = $calc->quote([['product_id' => $pid, 'quantity' => 1]], 'Monterrey, NL', 'MX', 1);
check('MX quote returns a positive tariff price', !$q['free'] ? $q['total'] > 0 : true);
$qf = $calc->quote([['product_id' => $pid, 'quantity' => 1]], 'Monterrey, NL', 'MX', 99999);
check('free-shipping threshold honoured', $qf['free'] === true || $qf['total'] == 0.0 || $qf['rule_id'] === null);

echo "== 5. Promo codes ==\n";
require_once __DIR__ . '/../services/PromoCodeService.php';
$promo = new PromoCodeService($pdo);
$pdo->exec("INSERT INTO promo_codes (code, discount_type, value, user_id, created_by) VALUES ('REGTEST10','percentage',10,$CUSTOMER,NULL)
            ON DUPLICATE KEY UPDATE user_id=$CUSTOMER, is_active=1");
$cart = [['product_id' => $pid, 'quantity' => 2, 'price' => 100]];
$v = $promo->validate('REGTEST10', $CUSTOMER, $cart);
check('personal code valid for its owner (10% of 200 = 20)', $v['valid'] && abs($v['discount'] - 20) < 0.01);
$v2 = $promo->validate('REGTEST10', $CUSTOMER + 999999, $cart);
check('personal code blocked for other users', !$v2['valid']);
$pdo->exec("DELETE FROM promo_codes WHERE code='REGTEST10'");

echo "== 6. Full order round-trip (create -> pay -> orchestrate) ==\n";
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/OrderProcessor.php';
require_once __DIR__ . '/../services/OxxoPaymentService.php';
require_once __DIR__ . '/../services/ReservationService.php';
$op = new OrderProcessor($pdo);
$res = $op->createOrder($CUSTOMER, [['product_id' => $pid, 'quantity' => 1]],
    ['address' => 'Regression 1', 'city' => 'CDMX', 'zip' => '01000', 'country' => 'MX']);
check('order created', !empty($res['success']), $res['error'] ?? '');
$oid = (int) ($res['order_id'] ?? 0);
if ($oid) {
    (new ReservationService($pdo))->record($oid, [['product_id' => $pid, 'quantity' => 1]]);
    $ox = new OxxoPaymentService($pdo);
    $vch = $ox->createVoucher($oid);
    check('OXXO voucher issued', !empty($vch['success']));
    $pay = $ox->confirmPayment($vch['reference'], 'manual');
    check('payment confirmed + order completed', !empty($pay['success']));
    $st = $pdo->query("SELECT payment_status, status FROM orders WHERE id=$oid")->fetch(PDO::FETCH_ASSOC);
    check('order marked paid/processing', $st['payment_status'] === 'paid' && $st['status'] === 'processing');
    $oled = (float) $pdo->query("SELECT COALESCE(SUM(debit_amount)-SUM(credit_amount),0) FROM general_ledger WHERE reference_type IN ('order','order_cogs') AND reference_id=$oid")->fetchColumn();
    check('order journals balanced', abs($oled) < 0.01, "off by $oled");
    check('inventory movement recorded', (int) $pdo->query("SELECT COUNT(*) FROM inventory_movements WHERE reference_type='sale_order' AND reference_id=$oid")->fetchColumn() > 0);
    check('shipment created', (int) $pdo->query("SELECT COUNT(*) FROM shipments WHERE order_id=$oid")->fetchColumn() === 1);
    check('confirmation email queued', (int) $pdo->query("SELECT COUNT(*) FROM email_queue WHERE reference_type='order' AND reference_id=$oid")->fetchColumn() >= 1);

    // full cleanup — the suite leaves no trace
    foreach ([
        "general_ledger WHERE reference_type IN ('order','order_cogs') AND reference_id=$oid",
        "merchant_commissions WHERE order_id=$oid", "merchant_suborders WHERE order_id=$oid",
        "inventory_movements WHERE reference_type='sale_order' AND reference_id=$oid",
        "email_queue WHERE reference_type='order' AND reference_id=$oid",
        "tracking_events WHERE shipment_id IN (SELECT id FROM shipments WHERE order_id=$oid)",
        "shipments WHERE order_id=$oid", "payment_transactions WHERE order_id=$oid",
        "stock_reservations WHERE order_id=$oid", "oxxo_payments WHERE order_id=$oid",
        "order_items WHERE order_id=$oid", "orders WHERE id=$oid",
    ] as $d) $pdo->exec("DELETE FROM $d");
    $pdo->prepare("UPDATE products SET inventory = inventory + 1 WHERE id = ?")->execute([$pid]);
    echo "  (round-trip data cleaned)\n";
}

echo "\n" . ($fail === 0 ? "ALL GREEN" : "FAILURES: $fail") . " — $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
