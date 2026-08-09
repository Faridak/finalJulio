<?php
/**
 * HR module: employees, hiring, overtime, leave, and ledger-posted payroll.
 *
 * Payroll money flow (double entry):
 *   Pay run  ->  Dr 6000 Payroll Expense (gross)
 *                Cr 2300 Payroll Taxes Payable (withheld)
 *                Cr 1000 Cash (net paid out)
 */
require_once __DIR__ . '/AccountingService.php';

class HRService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ---- Employees ---------------------------------------------------------

    public function addEmployee(array $e)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO employees (full_name, email, phone, position, department, employment_type,
                                   pay_type, monthly_salary, hourly_rate, tax_rate_pct,
                                   vacation_days_per_year, sick_days_per_year, hire_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $e['full_name'], $e['email'] ?? null, $e['phone'] ?? null,
            $e['position'], $e['department'] ?? 'general', $e['employment_type'] ?? 'full_time',
            $e['pay_type'] ?? 'salary',
            $e['pay_type'] === 'hourly' ? null : (float) ($e['monthly_salary'] ?? 0),
            $e['pay_type'] === 'hourly' ? (float) ($e['hourly_rate'] ?? 0) : null,
            (float) ($e['tax_rate_pct'] ?? 10),
            (float) ($e['vacation_days'] ?? 12), (float) ($e['sick_days'] ?? 6),
            $e['hire_date'] ?? date('Y-m-d'),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function terminate($employeeId)
    {
        $this->pdo->prepare("UPDATE employees SET status='terminated', terminated_at=CURDATE() WHERE id=?")
            ->execute([$employeeId]);
    }

    // ---- Hiring pipeline ---------------------------------------------------

    /** Advance an application; 'hired' converts the applicant into an employee. */
    public function setApplicationStatus($appId, $status, array $hireData = [])
    {
        $app = $this->pdo->prepare("SELECT a.*, o.title, o.department FROM job_applications a JOIN job_openings o ON a.opening_id=o.id WHERE a.id=?");
        $app->execute([$appId]);
        $a = $app->fetch(PDO::FETCH_ASSOC);
        if (!$a) return ['success' => false, 'error' => 'Application not found'];

        $empId = null;
        if ($status === 'hired') {
            $empId = $this->addEmployee([
                'full_name' => $a['full_name'], 'email' => $a['email'], 'phone' => $a['phone'],
                'position' => $a['title'], 'department' => $a['department'],
                'pay_type' => $hireData['pay_type'] ?? 'salary',
                'monthly_salary' => $hireData['monthly_salary'] ?? 0,
                'hourly_rate' => $hireData['hourly_rate'] ?? 0,
                'hire_date' => $hireData['hire_date'] ?? date('Y-m-d'),
            ]);
        }
        $this->pdo->prepare("UPDATE job_applications SET status=?, employee_id=? WHERE id=?")
            ->execute([$status, $empId, $appId]);
        return ['success' => true, 'employee_id' => $empId];
    }

    // ---- Overtime ----------------------------------------------------------

    public function logOvertime($employeeId, $date, $hours, $reason = null, $multiplier = 1.5)
    {
        $this->pdo->prepare("INSERT INTO overtime_entries (employee_id, work_date, hours, multiplier, reason) VALUES (?,?,?,?,?)")
            ->execute([$employeeId, $date, $hours, $multiplier, $reason]);
        return (int) $this->pdo->lastInsertId();
    }

    public function decideOvertime($entryId, $approve, $adminId)
    {
        $this->pdo->prepare("UPDATE overtime_entries SET status=?, approved_by=? WHERE id=? AND status='pending'")
            ->execute([$approve ? 'approved' : 'rejected', $adminId, $entryId]);
    }

    // ---- Leave -------------------------------------------------------------

    public function requestLeave($employeeId, $type, $start, $end, $reason = null)
    {
        $days = max(1, (strtotime($end) - strtotime($start)) / 86400 + 1);
        // weekday count (skip Sat/Sun)
        $d = 0; $t = strtotime($start);
        while ($t <= strtotime($end)) { $w = (int) date('N', $t); if ($w < 6) $d++; $t += 86400; }
        $days = max(1, $d);

        $this->pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, days, reason) VALUES (?,?,?,?,?,?)")
            ->execute([$employeeId, $type, $start, $end, $days, $reason]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Approve/reject; approving vacation/sick checks the remaining balance. */
    public function decideLeave($requestId, $approve, $adminId)
    {
        $req = $this->pdo->prepare("SELECT * FROM leave_requests WHERE id=? AND status='pending'");
        $req->execute([$requestId]);
        $r = $req->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['success' => false, 'error' => 'Request not found or already decided'];

        if ($approve && in_array($r['leave_type'], ['vacation', 'sick'], true)) {
            $bal = $this->leaveBalance((int) $r['employee_id']);
            $left = $r['leave_type'] === 'vacation' ? $bal['vacation_left'] : $bal['sick_left'];
            if ($r['days'] > $left + 0.001) {
                return ['success' => false, 'error' => "Insufficient {$r['leave_type']} balance ({$left} day(s) left, requested {$r['days']})"];
            }
        }
        $this->pdo->prepare("UPDATE leave_requests SET status=?, approved_by=? WHERE id=?")
            ->execute([$approve ? 'approved' : 'rejected', $adminId, $requestId]);
        return ['success' => true];
    }

    /** Remaining vacation/sick days for the current year. */
    public function leaveBalance($employeeId)
    {
        $emp = $this->pdo->prepare("SELECT vacation_days_per_year, sick_days_per_year FROM employees WHERE id=?");
        $emp->execute([$employeeId]);
        $e = $emp->fetch(PDO::FETCH_ASSOC) ?: ['vacation_days_per_year' => 0, 'sick_days_per_year' => 0];

        $used = $this->pdo->prepare("
            SELECT leave_type, COALESCE(SUM(days),0) d FROM leave_requests
            WHERE employee_id=? AND status='approved' AND YEAR(start_date)=YEAR(CURDATE())
            GROUP BY leave_type
        ");
        $used->execute([$employeeId]);
        $u = $used->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'vacation_left' => round((float) $e['vacation_days_per_year'] - (float) ($u['vacation'] ?? 0), 2),
            'sick_left'     => round((float) $e['sick_days_per_year'] - (float) ($u['sick'] ?? 0), 2),
            'unpaid_taken'  => (float) ($u['unpaid'] ?? 0),
        ];
    }

    // ---- Payroll -----------------------------------------------------------

    /**
     * Draft a payroll run for a period: base pay + approved overtime −
     * unpaid-leave days, then simple % tax withholding per employee.
     */
    public function generateRun($periodStart, $periodEnd, $adminId)
    {
        $exists = $this->pdo->prepare("SELECT id FROM payroll_runs WHERE period_start=? AND period_end=?");
        $exists->execute([$periodStart, $periodEnd]);
        if ($exists->fetchColumn()) return ['success' => false, 'error' => 'A run for this period already exists'];

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("INSERT INTO payroll_runs (period_start, period_end, created_by) VALUES (?,?,?)")
                ->execute([$periodStart, $periodEnd, $adminId]);
            $runId = (int) $this->pdo->lastInsertId();

            $emps = $this->pdo->query("SELECT * FROM employees WHERE status='active'")->fetchAll(PDO::FETCH_ASSOC);
            $tGross = $tTax = $tNet = 0;

            foreach ($emps as $e) {
                // Base pay for the period
                $hourlyEquiv = $e['pay_type'] === 'hourly'
                    ? (float) $e['hourly_rate']
                    : ((float) $e['monthly_salary'] / max(1, (float) $e['std_hours_per_period']));
                $base = $e['pay_type'] === 'hourly'
                    ? $hourlyEquiv * (float) $e['std_hours_per_period']
                    : (float) $e['monthly_salary'];

                // Approved overtime in period, not yet paid
                $ot = $this->pdo->prepare("
                    SELECT id, hours, multiplier FROM overtime_entries
                    WHERE employee_id=? AND status='approved' AND payroll_item_id IS NULL
                      AND work_date BETWEEN ? AND ?
                ");
                $ot->execute([$e['id'], $periodStart, $periodEnd]);
                $otRows = $ot->fetchAll(PDO::FETCH_ASSOC);
                $otPay = 0;
                foreach ($otRows as $o) $otPay += (float) $o['hours'] * $hourlyEquiv * (float) $o['multiplier'];

                // Unpaid leave days in period reduce pay (day = std hours / ~21.67 workdays)
                $ul = $this->pdo->prepare("
                    SELECT COALESCE(SUM(days),0) FROM leave_requests
                    WHERE employee_id=? AND status='approved' AND leave_type='unpaid'
                      AND start_date <= ? AND end_date >= ?
                ");
                $ul->execute([$e['id'], $periodEnd, $periodStart]);
                $unpaidDays = (float) $ul->fetchColumn();
                $dayRate = $base / 21.67;
                $deduction = round($unpaidDays * $dayRate, 2);

                $gross = round(max(0, $base + $otPay - $deduction), 2);
                $tax = round($gross * (float) $e['tax_rate_pct'] / 100, 2);
                $net = round($gross - $tax, 2);

                $this->pdo->prepare("
                    INSERT INTO payroll_items (run_id, employee_id, base_pay, overtime_pay, unpaid_leave_deduction, gross_pay, tax_withheld, net_pay)
                    VALUES (?,?,?,?,?,?,?,?)
                ")->execute([$runId, $e['id'], round($base, 2), round($otPay, 2), $deduction, $gross, $tax, $net]);
                $itemId = (int) $this->pdo->lastInsertId();

                foreach ($otRows as $o) {
                    $this->pdo->prepare("UPDATE overtime_entries SET payroll_item_id=? WHERE id=?")->execute([$itemId, $o['id']]);
                }
                $tGross += $gross; $tTax += $tax; $tNet += $net;
            }

            $this->pdo->prepare("UPDATE payroll_runs SET total_gross=?, total_tax=?, total_net=? WHERE id=?")
                ->execute([round($tGross, 2), round($tTax, 2), round($tNet, 2), $runId]);
            $this->pdo->commit();
            return ['success' => true, 'run_id' => $runId, 'employees' => count($emps), 'gross' => round($tGross, 2), 'net' => round($tNet, 2)];
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $ex->getMessage()];
        }
    }

    public function approveRun($runId)
    {
        $this->pdo->prepare("UPDATE payroll_runs SET status='approved' WHERE id=? AND status='draft'")->execute([$runId]);
    }

    /** Pay an approved run: balanced ledger post + mark paid. */
    public function payRun($runId)
    {
        $run = $this->pdo->prepare("SELECT * FROM payroll_runs WHERE id=? AND status='approved'");
        $run->execute([$runId]);
        $r = $run->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['success' => false, 'error' => 'Run not found or not approved'];

        $acc = new AccountingService($this->pdo);
        $this->pdo->beginTransaction();
        try {
            $acc->postJournal([
                ['code' => '6000', 'debit' => (float) $r['total_gross']],
                ['code' => '2300', 'credit' => (float) $r['total_tax']],
                ['code' => '1000', 'credit' => (float) $r['total_net']],
            ], "Payroll {$r['period_start']} – {$r['period_end']}", 'payroll_run', $runId);

            $this->pdo->prepare("UPDATE payroll_runs SET status='paid', paid_at=NOW() WHERE id=?")->execute([$runId]);
            $this->pdo->commit();
            return ['success' => true, 'gross' => $r['total_gross'], 'net' => $r['total_net'], 'tax' => $r['total_tax']];
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $ex->getMessage()];
        }
    }
}
