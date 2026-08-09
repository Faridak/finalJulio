<?php
/**
 * LAUNCH-DAY demo-data wipe. Removes development/test artifacts while keeping
 * the real catalog (3,4xx products), categories, suppliers, and structure.
 *
 *   php scripts/clean-demo-data.php --dry-run    show what would be deleted
 *   php scripts/clean-demo-data.php --confirm    actually delete
 *
 * BACK UP FIRST: mysqldump -u root ventdepot > backup-before-clean.sql
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
$mode = $argv[1] ?? '--dry-run';
if (!in_array($mode, ['--dry-run', '--confirm'], true)) exit("Use --dry-run or --confirm\n");
$dry = $mode === '--dry-run';

require_once __DIR__ . '/../config/database.php';

$steps = [
    // label => [count SQL, delete SQLs...]
    'Demo sample products (Sample Product / via.placeholder)' => [
        "SELECT COUNT(*) FROM products WHERE name LIKE 'Sample Product%' OR image_url LIKE '%via.placeholder%'",
        "DELETE FROM product_bin_assignments WHERE product_id IN (SELECT id FROM products WHERE name LIKE 'Sample Product%' OR image_url LIKE '%via.placeholder%')",
        "DELETE FROM product_inventory WHERE product_id IN (SELECT id FROM products WHERE name LIKE 'Sample Product%' OR image_url LIKE '%via.placeholder%')",
        "DELETE FROM products WHERE name LIKE 'Sample Product%' OR image_url LIKE '%via.placeholder%'",
    ],
    'Test orders + their trails (shipping_address markers)' => [
        "SELECT COUNT(*) FROM orders WHERE shipping_address IN ('x','t','demo','Regression 1, CDMX, 01000')",
        "DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE shipping_address IN ('x','t','demo','Regression 1, CDMX, 01000'))",
        "DELETE FROM shipments WHERE order_id IN (SELECT id FROM orders WHERE shipping_address IN ('x','t','demo','Regression 1, CDMX, 01000'))",
        "DELETE FROM orders WHERE shipping_address IN ('x','t','demo','Regression 1, CDMX, 01000')",
    ],
    'ALL ledger entries (start clean books at go-live)' => [
        "SELECT COUNT(*) FROM general_ledger",
        "DELETE FROM general_ledger",
        "UPDATE chart_of_accounts SET balance = 0",
    ],
    'Financial history (transactions, AP/AR, bank lines, payouts)' => [
        "SELECT COUNT(*) FROM payment_transactions",
        "DELETE FROM payment_transactions", "DELETE FROM bank_statement_lines",
        "DELETE FROM merchant_commissions", "DELETE FROM merchant_payouts", "DELETE FROM merchant_suborders",
        "DELETE FROM oxxo_payments",
    ],
    'Demo HR records (Juan Pérez / María López project)' => [
        "SELECT COUNT(*) FROM employees WHERE email LIKE '%@test.mx' OR full_name IN ('Juan Pérez','María López')",
        "DELETE FROM payroll_items WHERE employee_id IN (SELECT id FROM employees WHERE email LIKE '%@test.mx' OR full_name IN ('Juan Pérez','María López'))",
        "DELETE FROM overtime_entries WHERE employee_id IN (SELECT id FROM employees WHERE email LIKE '%@test.mx' OR full_name IN ('Juan Pérez','María López'))",
        "DELETE FROM leave_requests WHERE employee_id IN (SELECT id FROM employees WHERE email LIKE '%@test.mx' OR full_name IN ('Juan Pérez','María López'))",
        "DELETE FROM employees WHERE email LIKE '%@test.mx' OR full_name IN ('Juan Pérez','María López')",
        "DELETE FROM payroll_runs WHERE id NOT IN (SELECT DISTINCT run_id FROM payroll_items)",
    ],
    'Demo engineering project (Monterrey extraction system)' => [
        "SELECT COUNT(*) FROM quotes WHERE product_type LIKE 'Sistema de extracción%'",
        "DELETE FROM engineering_tasks WHERE quote_id IN (SELECT id FROM quotes WHERE product_type LIKE 'Sistema de extracción%')",
        "DELETE FROM work_order_materials WHERE quote_id IN (SELECT id FROM quotes WHERE product_type LIKE 'Sistema de extracción%')",
        "DELETE FROM quotes WHERE product_type LIKE 'Sistema de extracción%'",
    ],
    'Chat messages (keep channels)' => [
        "SELECT COUNT(*) FROM chat_messages", "DELETE FROM chat_messages",
        "UPDATE chat_members SET last_read_id = 0",
    ],
    'Email queue (dev messages never sent)' => [
        "SELECT COUNT(*) FROM email_queue WHERE status <> 'sent'", "DELETE FROM email_queue WHERE status <> 'sent'",
    ],
    'Audit log + reviews + wishlists + carts + reservations (dev)' => [
        "SELECT COUNT(*) FROM audit_log",
        "DELETE FROM audit_log", "DELETE FROM product_reviews", "DELETE FROM review_votes",
        "UPDATE products SET average_rating=NULL, total_reviews=0",
        "DELETE FROM wishlist_items", "DELETE FROM cart_items", "DELETE FROM stock_reservations",
        "DELETE FROM password_resets", "DELETE FROM shipping_distance_cache",
        "DELETE FROM marketing_campaigns", "DELETE FROM promo_codes", "DELETE FROM newsletters",
        "DELETE FROM support_tickets", "DELETE FROM ab_tests",
    ],
];

echo ($dry ? "DRY RUN — nothing will be deleted.\n\n" : "DELETING…\n\n");
foreach ($steps as $label => $sqls) {
    $countSql = array_shift($sqls);
    $n = $pdo->query($countSql)->fetchColumn();
    echo sprintf("%-64s %s row(s)\n", $label, $n);
    if (!$dry) {
        foreach ($sqls as $sql) { try { $pdo->exec($sql); } catch (PDOException $e) { echo "    warn: " . substr($e->getMessage(), 0, 90) . "\n"; } }
    }
}
echo $dry
    ? "\nRun with --confirm to execute (after a mysqldump backup!).\n"
    : "\nDone. Post-clean checklist: seed opening balances (Dr Cash / Dr Inventory / Cr Equity),\nreview seed users (keep admins, remove test customers), run php tests/regression.php.\n";
