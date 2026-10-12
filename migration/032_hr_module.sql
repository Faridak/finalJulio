-- Migration 032: HR module — employees, hiring pipeline, payroll, overtime, leave.

CREATE TABLE IF NOT EXISTS employees (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NULL,                              -- optional link to a login account
    full_name      VARCHAR(150) NOT NULL,
    email          VARCHAR(190) NULL,
    phone          VARCHAR(40) NULL,
    position       VARCHAR(100) NOT NULL,
    department     VARCHAR(80) NOT NULL DEFAULT 'general', -- warehouse, sales, finance, admin...
    employment_type ENUM('full_time','part_time','contract') NOT NULL DEFAULT 'full_time',
    pay_type       ENUM('salary','hourly') NOT NULL DEFAULT 'salary',
    monthly_salary DECIMAL(10,2) NULL,                    -- when pay_type = salary
    hourly_rate    DECIMAL(8,2) NULL,                     -- when pay_type = hourly
    std_hours_per_period DECIMAL(6,2) NOT NULL DEFAULT 160, -- hourly: default hours per month
    tax_rate_pct   DECIMAL(5,2) NOT NULL DEFAULT 10.00,   -- simple withholding %
    vacation_days_per_year DECIMAL(5,2) NOT NULL DEFAULT 12,
    sick_days_per_year     DECIMAL(5,2) NOT NULL DEFAULT 6,
    hire_date      DATE NOT NULL,
    status         ENUM('active','terminated') NOT NULL DEFAULT 'active',
    terminated_at  DATE NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_emp_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hiring pipeline
CREATE TABLE IF NOT EXISTS job_openings (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(120) NOT NULL,
    department  VARCHAR(80) NOT NULL DEFAULT 'general',
    description TEXT NULL,
    status      ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS job_applications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    opening_id  INT NOT NULL,
    full_name   VARCHAR(150) NOT NULL,
    email       VARCHAR(190) NULL,
    phone       VARCHAR(40) NULL,
    notes       TEXT NULL,
    status      ENUM('applied','interview','offer','hired','rejected') NOT NULL DEFAULT 'applied',
    employee_id INT NULL,                                 -- set when hired
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_opening FOREIGN KEY (opening_id) REFERENCES job_openings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Overtime
CREATE TABLE IF NOT EXISTS overtime_entries (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    work_date   DATE NOT NULL,
    hours       DECIMAL(5,2) NOT NULL,
    multiplier  DECIMAL(4,2) NOT NULL DEFAULT 1.50,
    reason      VARCHAR(200) NULL,
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    approved_by INT NULL,
    payroll_item_id INT NULL,                             -- set when paid
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ot_emp (employee_id, status),
    CONSTRAINT fk_ot_emp FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Leave
CREATE TABLE IF NOT EXISTS leave_requests (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type  ENUM('vacation','sick','personal','unpaid') NOT NULL DEFAULT 'vacation',
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    days        DECIMAL(5,2) NOT NULL,
    reason      VARCHAR(255) NULL,
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    approved_by INT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lv_emp (employee_id, status),
    CONSTRAINT fk_lv_emp FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payroll
CREATE TABLE IF NOT EXISTS payroll_runs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    period_start DATE NOT NULL,
    period_end   DATE NOT NULL,
    status       ENUM('draft','approved','paid') NOT NULL DEFAULT 'draft',
    total_gross  DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_tax    DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_net    DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by   INT NULL,
    paid_at      DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_run_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    run_id       INT NOT NULL,
    employee_id  INT NOT NULL,
    base_pay     DECIMAL(10,2) NOT NULL DEFAULT 0,
    overtime_pay DECIMAL(10,2) NOT NULL DEFAULT 0,
    unpaid_leave_deduction DECIMAL(10,2) NOT NULL DEFAULT 0,
    gross_pay    DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_withheld DECIMAL(10,2) NOT NULL DEFAULT 0,
    net_pay      DECIMAL(10,2) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_item (run_id, employee_id),
    CONSTRAINT fk_pi_run FOREIGN KEY (run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_pi_emp FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ledger accounts for payroll
INSERT INTO chart_of_accounts (account_code, account_name, account_type)
SELECT '6000','Payroll Expense','expense' WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='6000');
INSERT INTO chart_of_accounts (account_code, account_name, account_type)
SELECT '2300','Payroll Taxes Payable','liability' WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='2300');
