<?php
/**
 * FinancialMetricsService — P&L, balances, aging and KPIs derived from the
 * general ledger + A/P / A/R sub-ledgers. All automated: nothing is keyed by
 * hand, it's computed from what operations posted.
 */
class FinancialMetricsService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Profit & loss for a period (defaults to last 30 days). */
    public function profitAndLoss($from = null, $to = null)
    {
        $from = $from ?: date('Y-m-d', strtotime('-30 days'));
        $to   = $to ?: date('Y-m-d');

        $sum = function ($where) use ($from, $to) {
            $s = $this->pdo->prepare("
                SELECT COALESCE(SUM(g.debit_amount),0) d, COALESCE(SUM(g.credit_amount),0) c
                FROM general_ledger g JOIN chart_of_accounts a ON g.account_id=a.id
                WHERE g.transaction_date BETWEEN ? AND ? AND $where
            ");
            $s->execute([$from, $to]);
            return $s->fetch(PDO::FETCH_ASSOC);
        };

        $rev = $sum("a.account_type='revenue'");
        $revenue = (float) $rev['c'] - (float) $rev['d'];
        $cogsR = $sum("a.account_code='5000'");  $cogs = (float) $cogsR['d'] - (float) $cogsR['c'];
        $opexR = $sum("a.account_type='expense' AND a.account_code<>'5000'");
        $opex = (float) $opexR['d'] - (float) $opexR['c'];

        $gross = $revenue - $cogs;
        return [
            'from' => $from, 'to' => $to,
            'revenue' => round($revenue, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($gross, 2),
            'gross_margin_pct' => $revenue > 0 ? round($gross / $revenue * 100, 1) : 0,
            'opex' => round($opex, 2),
            'net_income' => round($gross - $opex, 2),
            'net_margin_pct' => $revenue > 0 ? round(($gross - $opex) / $revenue * 100, 1) : 0,
        ];
    }

    /** Outstanding A/R or A/P with simple aging buckets. */
    public function aging($which = 'ar')
    {
        $table = $which === 'ap' ? 'accounts_payable' : 'accounts_receivable';
        $paidCol = $which === 'ap' ? 'paid_amount' : 'received_amount';
        $rows = $this->pdo->query("
            SELECT (amount - COALESCE($paidCol,0)) AS bal, due_date
            FROM $table WHERE status <> 'paid'
        ")->fetchAll(PDO::FETCH_ASSOC);

        $buckets = ['current' => 0, 'd1_30' => 0, 'd31_60' => 0, 'd60_plus' => 0];
        $total = 0;
        $today = strtotime(date('Y-m-d'));
        foreach ($rows as $r) {
            $bal = (float) $r['bal']; if ($bal <= 0) continue;
            $total += $bal;
            $overdue = $r['due_date'] ? floor(($today - strtotime($r['due_date'])) / 86400) : 0;
            if ($overdue <= 0) $buckets['current'] += $bal;
            elseif ($overdue <= 30) $buckets['d1_30'] += $bal;
            elseif ($overdue <= 60) $buckets['d31_60'] += $bal;
            else $buckets['d60_plus'] += $bal;
        }
        $buckets = array_map(fn($v) => round($v, 2), $buckets);
        return ['total' => round($total, 2)] + $buckets;
    }

    private function balance($code)
    {
        return (float) ($this->pdo->query("SELECT balance FROM chart_of_accounts WHERE account_code='$code'")->fetchColumn() ?: 0);
    }

    private function typeTotal($type)
    {
        return (float) ($this->pdo->query("SELECT COALESCE(SUM(balance),0) FROM chart_of_accounts WHERE account_type='$type'")->fetchColumn());
    }

    private function accountsByType($type)
    {
        $s = $this->pdo->prepare("SELECT account_code, account_name, balance FROM chart_of_accounts WHERE account_type=? AND (balance<>0 OR is_active=1) ORDER BY account_code");
        $s->execute([$type]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Balance sheet: Assets = Liabilities + Equity (incl. retained earnings). */
    public function balanceSheet()
    {
        $retained = $this->typeTotal('revenue') - $this->typeTotal('expense'); // net income to date
        $assets = $this->typeTotal('asset');
        $liab = $this->typeTotal('liability');
        $equityAccts = $this->typeTotal('equity');
        $equity = $equityAccts + $retained;
        return [
            'assets_list' => $this->accountsByType('asset'),
            'liabilities_list' => $this->accountsByType('liability'),
            'equity_list' => $this->accountsByType('equity'),
            'total_assets' => round($assets, 2),
            'total_liabilities' => round($liab, 2),
            'retained_earnings' => round($retained, 2),
            'total_equity' => round($equity, 2),
            'balanced' => abs($assets - ($liab + $equity)) < 0.01,
        ];
    }

    /** Solvency & liquidity ratios. */
    public function solvency()
    {
        $cash = $this->balance('1000'); $ar = $this->balance('1100'); $inv = $this->balance('1200');
        $currentAssets = $cash + $ar + $inv + $this->balance('1250');
        $currentLiab = $this->balance('2000') + $this->balance('2100') + $this->balance('2200');
        $totalLiab = $this->typeTotal('liability');
        $equity = $this->typeTotal('equity') + ($this->typeTotal('revenue') - $this->typeTotal('expense'));
        return [
            'current_ratio' => $currentLiab != 0 ? round($currentAssets / $currentLiab, 2) : null,
            'quick_ratio' => $currentLiab != 0 ? round(($cash + $ar) / $currentLiab, 2) : null,
            'working_capital' => round($currentAssets - $currentLiab, 2),
            'debt_to_equity' => $equity != 0 ? round($totalLiab / $equity, 2) : null,
            'equity_ratio' => $this->typeTotal('asset') != 0 ? round($equity / $this->typeTotal('asset'), 2) : null,
        ];
    }

    /** Direct-method cash flow for a period. */
    public function cashFlow($from = null, $to = null)
    {
        $from = $from ?: date('Y-m-d', strtotime('-30 days'));
        $to = $to ?: date('Y-m-d');
        $cashId = (int) $this->pdo->query("SELECT id FROM chart_of_accounts WHERE account_code='1000'")->fetchColumn();

        $mv = function ($sign, $touchesType = null, $touchesCode = null) use ($from, $to, $cashId) {
            // sum cash debit/credit for journals that also touch a given account type/code
            $cond = $touchesType ? "a2.account_type = " . $this->pdo->quote($touchesType)
                  : ($touchesCode ? "a2.account_code = " . $this->pdo->quote($touchesCode) : "1=1");
            $col = $sign === 'in' ? 'g.debit_amount' : 'g.credit_amount';
            $sql = "SELECT COALESCE(SUM($col),0) FROM general_ledger g
                    WHERE g.account_id=$cashId AND g.transaction_date BETWEEN ? AND ?
                      AND EXISTS (SELECT 1 FROM general_ledger g2 JOIN chart_of_accounts a2 ON g2.account_id=a2.id
                                  WHERE g2.reference_type=g.reference_type AND g2.reference_id=g.reference_id
                                    AND a2.account_code<>'1000' AND $cond)";
            $s = $this->pdo->prepare($sql); $s->execute([$from, $to]);
            return (float) $s->fetchColumn();
        };

        $fromCustomers = $mv('in', 'revenue') + $mv('in', null, '1100');
        $toSuppliers = $mv('out', null, '2000') + $mv('out', 'expense') + $mv('out', null, '1200');
        $financing = $mv('in', 'equity') - $mv('out', 'equity');

        $endingCash = $this->balance('1000');
        $totalIn = (float) $this->scalarCash('debit', $from, $to, $cashId);
        $totalOut = (float) $this->scalarCash('credit', $from, $to, $cashId);
        $net = $totalIn - $totalOut;

        return [
            'from' => $from, 'to' => $to,
            'operating_in' => round($fromCustomers, 2),
            'operating_out' => round($toSuppliers, 2),
            'operating_net' => round($fromCustomers - $toSuppliers, 2),
            'financing' => round($financing, 2),
            'net_change' => round($net, 2),
            'ending_cash' => round($endingCash, 2),
            'beginning_cash' => round($endingCash - $net, 2),
        ];
    }

    private function scalarCash($col, $from, $to, $cashId)
    {
        $s = $this->pdo->prepare("SELECT COALESCE(SUM($col" . "_amount),0) FROM general_ledger WHERE account_id=? AND transaction_date BETWEEN ? AND ?");
        $s->execute([$cashId, $from, $to]);
        return $s->fetchColumn();
    }

    /**
     * Tax report for a period: sales tax collected/remitted/owed, taxable sales,
     * and an estimated income (federal) tax on net profit.
     */
    public function taxReport($from = null, $to = null, $federalRate = 21.0)
    {
        $from = $from ?: date('Y-m-01');
        $to = $to ?: date('Y-m-t');
        $pnl = $this->profitAndLoss($from, $to);

        // Sales tax payable account (2100): credits = collected, debits = remitted.
        $s = $this->pdo->prepare("
            SELECT COALESCE(SUM(g.credit_amount),0) collected, COALESCE(SUM(g.debit_amount),0) remitted
            FROM general_ledger g JOIN chart_of_accounts a ON g.account_id=a.id
            WHERE a.account_code='2100' AND g.transaction_date BETWEEN ? AND ?
        ");
        $s->execute([$from, $to]);
        $st = $s->fetch(PDO::FETCH_ASSOC);

        $collected = (float) $st['collected'];
        $remitted = (float) $st['remitted'];
        $federal = max(0, round($pnl['net_income'] * $federalRate / 100, 2));

        return [
            'period' => "$from to $to",
            'from' => $from, 'to' => $to,
            'taxable_sales' => $pnl['revenue'],
            'sales_tax_collected' => round($collected, 2),
            'sales_tax_remitted' => round($remitted, 2),
            'sales_tax_owed' => round($collected - $remitted, 2),
            'net_income' => $pnl['net_income'],
            'federal_rate' => $federalRate,
            'estimated_federal_tax' => $federal,
        ];
    }

    /** KPI bundle for the dashboard. */
    public function kpis()
    {
        $pnl = $this->profitAndLoss();
        $ar = $this->aging('ar');
        $ap = $this->aging('ap');
        $cash = $this->balance('1000');
        $inventory = $this->balance('1200');
        $taxPayable = $this->balance('2100');

        $days = 30;
        $dso = $pnl['revenue'] > 0 ? round($ar['total'] / ($pnl['revenue'] / $days), 1) : 0;
        $dpo = $pnl['cogs'] > 0 ? round($ap['total'] / ($pnl['cogs'] / $days), 1) : 0;

        $currentAssets = $cash + $ar['total'] + $inventory;
        $currentLiab = $ap['total'] + $taxPayable;
        $currentRatio = $currentLiab > 0 ? round($currentAssets / $currentLiab, 2) : null;

        $shrink = abs($this->balance('5200'));

        return [
            'pnl' => $pnl,
            'cash' => round($cash, 2),
            'inventory' => round($inventory, 2),
            'ar' => $ar, 'ap' => $ap,
            'dso_days' => $dso, 'dpo_days' => $dpo,
            'current_ratio' => $currentRatio,
            'shrinkage' => round($shrink, 2),
        ];
    }
}
