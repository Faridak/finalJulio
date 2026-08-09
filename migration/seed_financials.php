<?php
/**
 * Seed a realistic ~30 days of financial activity so bank reconciliation and the
 * KPI dashboard have data: sales (cash in), PO payments (cash out), A/R invoices
 * + receipts, and a bank statement whose lines mostly match the ledger — plus a
 * couple of unmatched lines (bank fee, interest) to exercise reconciliation.
 *
 * Idempotent-ish: clears its own demo rows first. Usage: php migration/seed_financials.php
 */
require_once __DIR__ . '/../services/AccountingService.php';

$pdo = new PDO("mysql:host=127.0.0.1;dbname=ventdepot;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$acc = new AccountingService($pdo);

// --- reset demo financials -------------------------------------------------
$pdo->exec("DELETE FROM general_ledger WHERE reference_type IN ('seed_opening','seed_sale','seed_sale_cogs','seed_po_bill','seed_po_pay','seed_ar_invoice','seed_ar_receipt')");
$pdo->exec("DELETE FROM bank_statement_lines");
$pdo->exec("DELETE FROM bank_accounts");
$pdo->exec("DELETE FROM accounts_payable WHERE description LIKE 'Seed %'");
$pdo->exec("DELETE FROM accounts_receivable WHERE description LIKE 'Seed %'");
$pdo->exec("UPDATE chart_of_accounts SET balance = 0");

// --- bank account ----------------------------------------------------------
$pdo->prepare("INSERT INTO bank_accounts (name, account_kind, account_mask, gl_account_code, opening_balance, created_at) VALUES ('Main Checking','bank','4471','1000',50000,NOW())")->execute();
$bankId = (int) $pdo->lastInsertId();
$line = $pdo->prepare("INSERT INTO bank_statement_lines (bank_account_id, txn_date, description, reference, amount, import_batch, created_at) VALUES (?,?,?,?,?, 'SEED', NOW())");

$d = fn($daysAgo) => date('Y-m-d', strtotime("-$daysAgo days"));
mt_srand(42);

// --- opening balance journal (so the balance sheet balances) ---------------
//   Dr Cash 50,000 + Dr Inventory 40,000 / Cr Owner Equity 90,000
$acc->postJournal([
    ['code'=>'1000','debit'=>50000], ['code'=>'1200','debit'=>40000], ['code'=>'3000','credit'=>90000],
], 'Opening balances', 'seed_opening', 1, $d(30));

// --- sales (cash in) : Dr Cash / Cr Revenue + Tax ; Dr COGS / Cr Inventory --
$salesTotal = 0;
for ($i = 0; $i < 14; $i++) {
    $day = $d(mt_rand(1, 29));
    $subtotal = mt_rand(400, 3000);
    $tax = round($subtotal * 0.08, 2);
    $total = round($subtotal + $tax, 2);
    $cogs = round($subtotal * 0.6, 2);
    $salesTotal += $total;
    $acc->postJournal([
        ['code'=>'1000','debit'=>$total], ['code'=>'4000','credit'=>$subtotal], ['code'=>'2100','credit'=>$tax],
    ], 'Sales deposit', 'seed_sale', $i + 1, $day);
    $acc->postJournal([['code'=>'5000','debit'=>$cogs], ['code'=>'1200','credit'=>$cogs]], 'COGS', 'seed_sale_cogs', $i + 1, $day);
    // matching bank deposit
    $line->execute([$bankId, $day, 'Card settlement batch', 'DEP' . str_pad($i+1,4,'0',STR_PAD_LEFT), $total]);
}

// --- PO payments (cash out) : Dr A/P / Cr Cash + a paid A/P row -------------
for ($i = 0; $i < 5; $i++) {
    $day = $d(mt_rand(2, 28));
    $amt = mt_rand(800, 4000);
    $inv = 'PO-SEED-' . (100 + $i);
    $pdo->prepare("INSERT INTO accounts_payable (vendor_name, invoice_number, invoice_date, due_date, amount, paid_amount, status, description, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")
        ->execute(['Supplier '.($i+1), $inv, $day, $d(-15), $amt, $amt, 'paid', 'Seed PO payment', ]);
    // bill (goods on credit) then payment — A/P nets to zero, inventory rises
    $acc->postJournal([['code'=>'1200','debit'=>$amt], ['code'=>'2000','credit'=>$amt]], 'Supplier bill', 'seed_po_bill', $i + 1, $day);
    $acc->postJournal([['code'=>'2000','debit'=>$amt], ['code'=>'1000','credit'=>$amt]], 'Supplier bank transfer', 'seed_po_pay', $i + 1, $day);
    $line->execute([$bankId, $day, 'Wire to '.$inv, $inv, -$amt]);
}

// --- A/R : 4 invoices (Dr A/R / Cr Revenue), 2 receipts (Dr Cash / Cr A/R) --
for ($i = 0; $i < 4; $i++) {
    $day = $d(mt_rand(5, 25));
    $amt = mt_rand(1000, 6000);
    $inv = 'INV-SEED-' . (200 + $i);
    $paid = $i < 2;                    // first two are collected
    $pdo->prepare("INSERT INTO accounts_receivable (customer_name, invoice_number, invoice_date, due_date, amount, received_amount, status, description, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")
        ->execute(['Customer '.($i+1), $inv, $day, $d(-20), $amt, $paid ? $amt : 0, $paid ? 'paid' : 'pending', 'Seed AR invoice']);
    $acc->postJournal([['code'=>'1100','debit'=>$amt], ['code'=>'4000','credit'=>$amt]], 'Credit sale invoice', 'seed_ar_invoice', $i + 1, $day);
    if ($paid) {
        $rday = $d(mt_rand(1, 4));
        $acc->postJournal([['code'=>'1000','debit'=>$amt], ['code'=>'1100','credit'=>$amt]], 'Customer payment', 'seed_ar_receipt', $i + 1, $rday);
        $line->execute([$bankId, $rday, 'ACH from '.('Customer '.($i+1)), $inv, $amt]);
    }
}

// --- unmatched statement lines (no ledger entry) : reconciliation must flag --
$line->execute([$bankId, $d(3), 'Monthly bank service fee', 'FEE', -25.00]);
$line->execute([$bankId, $d(1), 'Interest earned', 'INT', 4.10]);

echo "Seeded financials on bank account #$bankId\n";
echo "  ledger rows: " . $pdo->query("SELECT COUNT(*) FROM general_ledger")->fetchColumn() . "\n";
echo "  bank lines : " . $pdo->query("SELECT COUNT(*) FROM bank_statement_lines")->fetchColumn() . " (2 intentionally unmatched)\n";
echo "  A/P rows   : " . $pdo->query("SELECT COUNT(*) FROM accounts_payable")->fetchColumn() . "\n";
echo "  A/R rows   : " . $pdo->query("SELECT COUNT(*) FROM accounts_receivable")->fetchColumn() . "\n";
