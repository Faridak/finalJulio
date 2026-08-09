<?php
/**
 * AccountingService
 *
 * Double-entry posting into the existing `general_ledger` table against
 * `chart_of_accounts`. Every journal must balance (sum of debits == credits).
 *
 * Account codes used by the marketplace (from chart_of_accounts):
 *   1000 Cash · 1100 Accounts Receivable · 1200 Inventory
 *   2100 Sales Tax Payable · 2200 Merchant Payable
 *   4000 Sales Revenue · 4100 Commission Revenue · 4200 Shipping Revenue
 *   5000 Cost of Goods Sold · 5100 Shipping Expense
 */
class AccountingService
{
    private $pdo;
    /** @var array<string,int> account_code => account_id cache */
    private $accountCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Post a balanced journal entry.
     *
     * @param array  $lines  each: ['code' => '1000', 'debit' => 0, 'credit' => 0]
     * @param string $description
     * @param string $referenceType e.g. 'order', 'payout', 'refund'
     * @param int    $referenceId
     * @param string|null $date  Y-m-d; defaults to today
     * @throws Exception when the journal does not balance or an account is unknown
     */
    public function postJournal(array $lines, $description, $referenceType, $referenceId, $date = null)
    {
        $date = $date ?: date('Y-m-d');

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lines as $line) {
            $totalDebit  += (float) ($line['debit'] ?? 0);
            $totalCredit += (float) ($line['credit'] ?? 0);
        }

        // Guard against unbalanced journals (allow sub-cent float noise).
        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new Exception(sprintf(
                'Unbalanced journal for %s #%d: debit %.2f != credit %.2f',
                $referenceType, $referenceId, $totalDebit, $totalCredit
            ));
        }

        $insert = $this->pdo->prepare("
            INSERT INTO general_ledger
                (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($lines as $line) {
            $debit  = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if ($debit == 0.0 && $credit == 0.0) {
                continue; // skip empty lines
            }
            $accountId = $this->accountId($line['code']);
            $insert->execute([$accountId, $date, $description, $debit, $credit, $referenceType, $referenceId]);
            $this->applyBalance($line['code'], $debit, $credit);
        }
    }

    /**
     * Whether a journal has already been posted for a given source document.
     */
    public function isPosted($referenceType, $referenceId)
    {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM general_ledger WHERE reference_type = ? AND reference_id = ? LIMIT 1
        ");
        $stmt->execute([$referenceType, $referenceId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Resolve an account code to its id (cached).
     */
    private function accountId($code)
    {
        if (isset($this->accountCache[$code])) {
            return $this->accountCache[$code];
        }
        $stmt = $this->pdo->prepare("SELECT id FROM chart_of_accounts WHERE account_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new Exception("Unknown ledger account code: $code");
        }
        return $this->accountCache[$code] = (int) $id;
    }

    /**
     * Keep the denormalised running balance on chart_of_accounts in sync.
     * Debits increase assets/expenses; credits increase liabilities/equity/revenue.
     */
    private function applyBalance($code, $debit, $credit)
    {
        $stmt = $this->pdo->prepare("SELECT account_type FROM chart_of_accounts WHERE account_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $type = $stmt->fetchColumn();

        $delta = in_array($type, ['asset', 'expense'], true)
            ? ($debit - $credit)
            : ($credit - $debit);

        $upd = $this->pdo->prepare("UPDATE chart_of_accounts SET balance = balance + ? WHERE account_code = ?");
        $upd->execute([$delta, $code]);
    }
}
