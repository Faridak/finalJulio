<?php
/**
 * BankReconciliationService — import a bank / credit-card statement and match
 * each line to the general ledger's cash movements. Unmatched lines are the
 * discrepancies (bank fees, timing, errors) a human must resolve.
 *
 * Matching rule: a statement deposit (+) matches a ledger DEBIT to the bank's
 * GL cash account of the same amount within a date tolerance; a withdrawal (-)
 * matches a CREDIT. Each ledger row is consumed by at most one statement line.
 */
class BankReconciliationService
{
    private $pdo;
    const DATE_TOLERANCE_DAYS = 4;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Import parsed lines: each ['txn_date','description','amount','reference'?,'running_balance'?]. */
    public function importLines($bankAccountId, array $lines, $batch = null)
    {
        $batch = $batch ?: 'IMP-' . date('YmdHis');
        $ins = $this->pdo->prepare("
            INSERT IGNORE INTO bank_statement_lines
                (bank_account_id, txn_date, description, reference, amount, running_balance, import_batch, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $n = 0;
        foreach ($lines as $l) {
            if (empty($l['txn_date']) || !isset($l['amount'])) continue;
            $ins->execute([$bankAccountId, $l['txn_date'], $l['description'] ?? null, $l['reference'] ?? null,
                           round((float) $l['amount'], 2), $l['running_balance'] ?? null, $batch]);
            $n += $ins->rowCount();
        }
        return ['imported' => $n, 'batch' => $batch];
    }

    /** Parse a simple CSV: date, description, amount[, reference]. Amount signed. */
    public function importCsv($bankAccountId, $csv)
    {
        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($csv)));
        $lines = [];
        foreach ($rows as $i => $r) {
            if ($i === 0 && !is_numeric(str_replace([',', '$', '-', '.'], '', $r[2] ?? ''))) continue; // header
            if (count($r) < 3) continue;
            $lines[] = [
                'txn_date'    => date('Y-m-d', strtotime(trim($r[0]))),
                'description' => trim($r[1] ?? ''),
                'amount'      => (float) preg_replace('/[^0-9.\-]/', '', $r[2]),
                'reference'   => isset($r[3]) ? trim($r[3]) : null,
            ];
        }
        return $this->importLines($bankAccountId, $lines);
    }

    /** Auto-match unmatched lines to the bank's GL cash account. */
    public function autoMatch($bankAccountId, $userId = null)
    {
        $glCode = $this->pdo->query("SELECT gl_account_code FROM bank_accounts WHERE id=" . (int) $bankAccountId)->fetchColumn() ?: '1000';

        // GL cash rows already consumed by a matched line.
        $used = $this->pdo->query("SELECT match_ref_id FROM bank_statement_lines WHERE match_ref_type='ledger' AND match_ref_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $used = array_flip($used);

        $lines = $this->pdo->prepare("SELECT id, txn_date, amount, reference FROM bank_statement_lines WHERE bank_account_id=? AND status='unmatched'");
        $lines->execute([$bankAccountId]);

        $find = $this->pdo->prepare("
            SELECT g.id, g.transaction_date, ABS(DATEDIFF(g.transaction_date, ?)) AS ddiff
            FROM general_ledger g JOIN chart_of_accounts c ON g.account_id=c.id
            WHERE c.account_code = ?
              AND ABS(DATEDIFF(g.transaction_date, ?)) <= ?
              AND ((? > 0 AND g.debit_amount = ?) OR (? < 0 AND g.credit_amount = ?))
            ORDER BY ddiff ASC, g.id ASC
        ");
        $set = $this->pdo->prepare("
            UPDATE bank_statement_lines
            SET status='matched', match_ref_type='ledger', match_ref_id=?, match_confidence=?, matched_by=?, matched_at=NOW()
            WHERE id=?
        ");

        $matched = 0;
        foreach ($lines as $l) {
            $amt = (float) $l['amount']; $abs = abs($amt);
            $find->execute([$l['txn_date'], $glCode, $l['txn_date'], self::DATE_TOLERANCE_DAYS, $amt, $abs, $amt, $abs]);
            $cand = null;
            foreach ($find as $c) { if (!isset($used[$c['id']])) { $cand = $c; break; } }
            if ($cand) {
                $conf = round(1 - min(1, $cand['ddiff'] / (self::DATE_TOLERANCE_DAYS + 1)), 3);
                $set->execute([$cand['id'], $conf, $userId, $l['id']]);
                $used[$cand['id']] = true;
                $matched++;
            }
        }
        return ['matched' => $matched];
    }

    public function manualMatch($lineId, $refType, $refId, $userId = null)
    {
        $this->pdo->prepare("UPDATE bank_statement_lines SET status='matched', match_ref_type=?, match_ref_id=?, match_confidence=1, matched_by=?, matched_at=NOW() WHERE id=?")
                  ->execute([$refType, $refId, $userId, $lineId]);
    }
    public function ignore($lineId) { $this->pdo->prepare("UPDATE bank_statement_lines SET status='ignored' WHERE id=?")->execute([$lineId]); }

    /** Reconciliation summary + the unmatched lines to resolve. */
    public function summary($bankAccountId)
    {
        $g = fn($sql) => $this->pdo->prepare($sql);
        $c = $g("SELECT status, COUNT(*) n, COALESCE(SUM(amount),0) amt FROM bank_statement_lines WHERE bank_account_id=? GROUP BY status");
        $c->execute([$bankAccountId]);
        $by = []; foreach ($c as $r) $by[$r['status']] = $r;

        $opening = (float) $this->pdo->query("SELECT opening_balance FROM bank_accounts WHERE id=" . (int) $bankAccountId)->fetchColumn();
        $stmtNet = (float) $this->pdo->query("SELECT COALESCE(SUM(amount),0) FROM bank_statement_lines WHERE bank_account_id=" . (int) $bankAccountId)->fetchColumn();

        $un = $this->pdo->prepare("SELECT id, txn_date, description, reference, amount FROM bank_statement_lines WHERE bank_account_id=? AND status='unmatched' ORDER BY txn_date");
        $un->execute([$bankAccountId]);

        return [
            'matched'   => (int) ($by['matched']['n'] ?? 0),
            'unmatched' => (int) ($by['unmatched']['n'] ?? 0),
            'ignored'   => (int) ($by['ignored']['n'] ?? 0),
            'statement_balance' => round($opening + $stmtNet, 2),
            'unmatched_value'   => round((float) ($by['unmatched']['amt'] ?? 0), 2),
            'unmatched_lines'   => $un->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
