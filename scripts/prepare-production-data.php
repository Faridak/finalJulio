<?php
/**
 * One-time: turn an imported copy of the dev database into clean production
 * data. Run ON THE SERVER after the import (never on your dev machine):
 *
 *   php scripts/prepare-production-data.php            # dry run: shows what would change
 *   php scripts/prepare-production-data.php --confirm  # applies it (take a backup first)
 *
 * Decisions (2026-10-19):
 *   - stock starts at ZERO everywhere; real counts are entered before selling
 *   - only admin@ventdepot.com is kept among staff; seeded staff + test users removed
 *   - local test leftovers (orphan refunds, dev security log) removed
 * Catalog, categories, images, suppliers, chart of accounts, shipping rates,
 * warehouse layout (bins/racks/zones) and settings are kept.
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
require_once __DIR__ . '/../config/database.php';

$confirm = in_array('--confirm', $argv, true);
$REMOVE_USERS = ['sales1@ventdepot.com', 'engineer1@ventdepot.com', 'engineer2@ventdepot.com',
                 'alaa@123.com', 'farida@123.com'];

$in = fn(array $a) => implode(',', array_fill(0, count($a), '?'));
$steps = [
    ['Remove seeded staff + test accounts (' . implode(', ', $REMOVE_USERS) . ')',
     "SELECT COUNT(*) FROM users WHERE email IN ({$in($REMOVE_USERS)})",
     ["DELETE FROM user_profiles WHERE user_id IN (SELECT id FROM users WHERE email IN ({$in($REMOVE_USERS)}))",
      "DELETE FROM chat_members  WHERE user_id IN (SELECT id FROM users WHERE email IN ({$in($REMOVE_USERS)}))",
      "DELETE FROM users WHERE email IN ({$in($REMOVE_USERS)})"], $REMOVE_USERS],
    ['Stock to zero: products.inventory',
     "SELECT COUNT(*) FROM products WHERE inventory <> 0",
     ["UPDATE products SET inventory = 0"], []],
    ['Stock to zero: per-location inventory',
     "SELECT COUNT(*) FROM product_inventory WHERE quantity_on_hand <> 0 OR quantity_reserved <> 0 OR quantity_available <> 0",
     ["UPDATE product_inventory SET quantity_on_hand = 0, quantity_reserved = 0, quantity_available = 0, last_counted_at = NULL"], []],
    ['Stock to zero: bin assignments (slots kept, quantity 0)',
     "SELECT COUNT(*) FROM product_bin_assignments WHERE quantity <> 0",
     ["UPDATE product_bin_assignments SET quantity = 0"], []],
    ['Stock to zero: bins emptied (layout kept)',
     "SELECT COUNT(*) FROM inventory_bins WHERE current_quantity <> 0 OR occupancy_status <> 'empty'",
     ["UPDATE inventory_bins SET current_product_id = NULL, current_quantity = 0, current_volume_cm3 = 0,
       current_weight_kg = 0, utilization_percentage = 0, occupancy_status = 'empty', last_counted_at = NULL
       WHERE status <> 'blocked' OR status IS NULL"], []],
    ['Dev leftovers: orphan refund rows',
     "SELECT COUNT(*) FROM payment_refunds",
     ["DELETE FROM payment_refunds"], []],
    ['Dev leftovers: local security log',
     "SELECT COUNT(*) FROM security_events",
     ["DELETE FROM security_events"], []],
];

echo ($confirm ? "APPLYING" : "DRY RUN — nothing changes") . " on database '" . env('DB_DATABASE', 'ventdepot') . "'\n\n";
if ($confirm && !preg_match('/production/i', (string) env('APP_ENV', ''))) {
    exit("Refusing: APP_ENV is not 'production'. This script is for the server copy only.\n");
}

$pdo->beginTransaction();
try {
    foreach ($steps as [$label, $countSql, $sqls, $params]) {
        $c = $pdo->prepare($countSql); $c->execute($params);
        printf("  %-62s %6d row(s)\n", $label, (int) $c->fetchColumn());
        if ($confirm) {
            foreach ($sqls as $sql) { $pdo->prepare($sql)->execute($params); }
        }
    }
    $confirm ? $pdo->commit() : $pdo->rollBack();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit("FAILED, nothing changed: " . $e->getMessage() . "\n");
}

$staff = $pdo->query("SELECT email, role FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "\nAccounts " . ($confirm ? "now" : "currently") . ": " . implode(', ', array_map(fn($u) => "{$u['email']} ({$u['role']})", $staff)) . "\n";
echo "Stock units " . ($confirm ? "now" : "currently") . ": " . (int) $pdo->query("SELECT SUM(inventory) FROM products")->fetchColumn() . "\n";
if ($confirm) {
    echo "\nNext: log in as admin@ventdepot.com → change password → enable 2FA; enter real stock before selling.\n";
} else {
    echo "\nRun with --confirm to apply.\n";
}
