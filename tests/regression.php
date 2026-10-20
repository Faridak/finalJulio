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

/** Exact stock snapshot for test products: totals + per-location rows (restored after order tests). */
function snapshotStock(PDO $pdo, array $ids) {
    $in = implode(',', array_map('intval', $ids));
    return [
        'products'  => $pdo->query("SELECT id, inventory FROM products WHERE id IN ($in)")->fetchAll(PDO::FETCH_KEY_PAIR),
        'locations' => $pdo->query("SELECT id, quantity_on_hand, quantity_reserved FROM product_inventory WHERE product_id IN ($in)")->fetchAll(PDO::FETCH_ASSOC),
    ];
}
function restoreStock(PDO $pdo, array $snap) {
    foreach ($snap['products'] as $id => $inv) $pdo->prepare("UPDATE products SET inventory = ? WHERE id = ?")->execute([$inv, $id]);
    $u = $pdo->prepare("UPDATE product_inventory SET quantity_on_hand = ?, quantity_reserved = ? WHERE id = ?   -- quantity_available is generated");
    foreach ($snap['locations'] as $r) $u->execute([$r['quantity_on_hand'], $r['quantity_reserved'], $r['id']]);
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
$stock6 = snapshotStock($pdo, [$pid]);
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
    restoreStock($pdo, $stock6);   // exact per-location stock (the old +1 on products.inventory left product_inventory 1 unit short every run)
    echo "  (round-trip data cleaned)\n";
}

echo "== 7. Refund accounting (own + marketplace, full + partial) ==\n";
require_once __DIR__ . '/../services/ReturnService.php';
require_once __DIR__ . '/../services/MarketplacePayoutService.php';
$SELLER = (int) $pdo->query("SELECT id FROM users WHERE role = 'merchant' ORDER BY id DESC LIMIT 1")->fetchColumn();
$mpid = (int) $pdo->query("SELECT id FROM products WHERE price BETWEEN 50 AND 200 AND inventory > 5 AND id <> $pid
                           AND id NOT IN (SELECT product_id FROM product_discounts WHERE is_active=1) ORDER BY id DESC LIMIT 1")->fetchColumn();
$orig = $pdo->query("SELECT merchant_id, ownership FROM products WHERE id=$mpid")->fetch(PDO::FETCH_ASSOC);
$stock7 = snapshotStock($pdo, [$pid, $mpid]);
$acct = function ($oid, $retIds, $code) use ($pdo) {   // net debit on an account for this order's sale + refunds
    $ids = implode(',', array_map('intval', $retIds ?: [0]));
    return round((float) $pdo->query("SELECT COALESCE(SUM(gl.debit_amount - gl.credit_amount),0)
        FROM general_ledger gl JOIN chart_of_accounts a ON a.id = gl.account_id
        WHERE a.account_code = '$code' AND ((gl.reference_type = 'order' AND gl.reference_id = $oid)
           OR (gl.reference_type = 'return' AND gl.reference_id IN ($ids)))")->fetchColumn(), 2);
};
$cleanup7 = [];
try {
    // A marketplace product (seller-owned) + an own product on one order.
    $pdo->prepare("UPDATE products SET ownership='marketplace', merchant_id=? WHERE id=?")->execute([$SELLER, $mpid]);
    $res = $op->createOrder($CUSTOMER, [['product_id' => $pid, 'quantity' => 1], ['product_id' => $mpid, 'quantity' => 2]],
        ['address' => 'Regression 7', 'city' => 'CDMX', 'zip' => '01000', 'country' => 'MX']);
    $oid7 = (int) ($res['order_id'] ?? 0); $cleanup7['order'] = $oid7;
    (new ReservationService($pdo))->record($oid7, [['product_id' => $pid, 'quantity' => 1], ['product_id' => $mpid, 'quantity' => 2]]);
    $ox = new OxxoPaymentService($pdo);
    $ox->confirmPayment($ox->createVoucher($oid7)['reference'], 'manual');
    $mp = new MarketplacePayoutService($pdo);
    $pendingBefore = $mp->pendingPayable($SELLER);
    $saleNet = (float) $pdo->query("SELECT net_amount FROM merchant_commissions WHERE order_id=$oid7 AND merchant_id=$SELLER AND gross_amount > 0")->fetchColumn();
    check('sale split recorded seller net', $saleNet > 0, "net=$saleNet");

    $rs = new ReturnService($pdo);
    $items7 = $pdo->query("SELECT oi.id, oi.product_id FROM order_items oi WHERE oi.order_id=$oid7")->fetchAll(PDO::FETCH_KEY_PAIR);
    $ownItem = array_search($pid, $items7); $mpItem = array_search($mpid, $items7);

    // (a) full refund of the marketplace line
    $r1 = $rs->createReturn($oid7, $CUSTOMER, [['order_item_id' => $mpItem, 'quantity' => 2]], 'regression mkt full');
    $cleanup7['returns'][] = $r1['return_id'] ?? 0;
    $pdo->prepare("UPDATE return_requests SET restock=0 WHERE id=?")->execute([$r1['return_id']]);
    $rs->setStatus($r1['return_id'], 'approved');
    check('marketplace refund completes', !empty($rs->completeReturn($r1['return_id'])['success']));
    check('Merchant Payable fully reversed for the refunded line', abs($acct($oid7, [$r1['return_id']], '2200')) < 0.02,
          'net 2200 = ' . $acct($oid7, [$r1['return_id']], '2200'));
    check('Commission Revenue fully reversed', abs($acct($oid7, [$r1['return_id']], '4100')) < 0.02);
    check('seller balance reduced by the refund', abs($mp->pendingPayable($SELLER) - ($pendingBefore - $saleNet)) < 0.02,
          'pending ' . $mp->pendingPayable($SELLER) . ' vs ' . ($pendingBefore - $saleNet));
    $rs->completeReturn($r1['return_id']);   // re-run must not double-deduct
    check('refund is idempotent (no second seller deduction)',
          (int) $pdo->query("SELECT COUNT(*) FROM merchant_commissions WHERE payout_reference='REFUND-R" . (int) $r1['return_id'] . "'")->fetchColumn() === 1);

    // (b) partial refund (half) of the own line -> only Sales Revenue moves
    $r2 = $rs->createReturn($oid7, $CUSTOMER, [['order_item_id' => $ownItem, 'quantity' => 1]], 'regression own partial');
    $cleanup7['returns'][] = $r2['return_id'] ?? 0;
    $half = round($r2['refund_amount'] / 2, 2);
    $pdo->prepare("UPDATE return_requests SET refund_amount=?, restock=0 WHERE id=?")->execute([$half, $r2['return_id']]);
    $rs->setStatus($r2['return_id'], 'approved');
    $rs->completeReturn($r2['return_id']);
    $j2 = $pdo->query("SELECT a.account_code, SUM(gl.debit_amount) d, SUM(gl.credit_amount) c FROM general_ledger gl
                       JOIN chart_of_accounts a ON a.id = gl.account_id
                       WHERE gl.reference_type='return' AND gl.reference_id=" . (int) $r2['return_id'] . " GROUP BY a.account_code")->fetchAll(PDO::FETCH_UNIQUE);
    check('own partial refund: cash out = refund amount', abs((float) ($j2['1000']['c'] ?? 0) - $half) < 0.01);
    check('own partial refund: no Merchant Payable / Commission movement',
          (float) ($j2['2200']['d'] ?? 0) == 0.0 && (float) ($j2['4100']['d'] ?? 0) == 0.0);

    $bal = (float) $pdo->query("SELECT COALESCE(SUM(debit_amount)-SUM(credit_amount),0) FROM general_ledger
                                WHERE (reference_type IN ('order','order_cogs') AND reference_id=$oid7)
                                   OR (reference_type IN ('return','return_cogs') AND reference_id IN (" . implode(',', array_map('intval', $cleanup7['returns'])) . "))")->fetchColumn();
    check('order + refund journals balanced', abs($bal) < 0.01, "off by $bal");
} finally {
    $pdo->prepare("UPDATE products SET ownership=?, merchant_id=? WHERE id=?")->execute([$orig['ownership'], $orig['merchant_id'], $mpid]);
    $oid7 = (int) ($cleanup7['order'] ?? 0);
    $rids = implode(',', array_map('intval', $cleanup7['returns'] ?? [0]) ?: [0]);
    if ($oid7) {
        foreach ([
            "general_ledger WHERE (reference_type IN ('order','order_cogs') AND reference_id=$oid7) OR (reference_type IN ('return','return_cogs') AND reference_id IN ($rids))",
            "inventory_movements WHERE (reference_type='sale_order' AND reference_id=$oid7) OR (reference_type='return' AND reference_id IN ($rids))",
            "payment_refunds WHERE transaction_id IN (SELECT id FROM payment_transactions WHERE order_id=$oid7)",
            "return_request_items WHERE return_request_id IN ($rids)", "return_requests WHERE id IN ($rids)",
            "merchant_commissions WHERE order_id=$oid7", "merchant_suborders WHERE order_id=$oid7",
            "email_queue WHERE reference_id=$oid7 AND reference_type IN ('order','seller_order')",
            "tracking_events WHERE shipment_id IN (SELECT id FROM shipments WHERE order_id=$oid7)",
            "shipments WHERE order_id=$oid7", "payment_transactions WHERE order_id=$oid7",
            "stock_reservations WHERE order_id=$oid7", "oxxo_payments WHERE order_id=$oid7",
            "order_items WHERE order_id=$oid7", "orders WHERE id=$oid7",
        ] as $d) { try { $pdo->exec("DELETE FROM $d"); } catch (Throwable $e) { echo "  (cleanup: " . $e->getMessage() . ")\n"; } }
        restoreStock($pdo, $stock7);
        echo "  (refund test data cleaned)\n";
    }
}

echo "\n" . ($fail === 0 ? "ALL GREEN" : "FAILURES: $fail") . " — $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
