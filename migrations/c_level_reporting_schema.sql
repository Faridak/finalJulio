-- C-Level Financial Reporting Schema
-- This migration adds tables for executive financial reporting including cash flow forecasting, budget vs actual, unit economics, and growth metrics

-- Create cash flow forecasting table
CREATE TABLE IF NOT EXISTS cash_flow_forecasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forecast_date DATE NOT NULL,
    period ENUM('daily', 'weekly', 'monthly') NOT NULL,
    cash_inflows DECIMAL(15,2) DEFAULT 0.00,
    cash_outflows DECIMAL(15,2) DEFAULT 0.00,
    net_cash_flow DECIMAL(15,2) DEFAULT 0.00,
    opening_balance DECIMAL(15,2) DEFAULT 0.00,
    closing_balance DECIMAL(15,2) DEFAULT 0.00,
    forecast_type ENUM('actual', 'predicted', 'budget') DEFAULT 'predicted',
    confidence_level DECIMAL(5,2) DEFAULT 0.00, -- For predictive forecasts
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cash_flow_forecast_date (forecast_date),
    INDEX idx_cash_flow_forecast_type (forecast_type),
    INDEX idx_cash_flow_forecast_period (period, forecast_date)
);

-- Create budget vs actual reporting table
CREATE TABLE IF NOT EXISTS budget_vs_actual (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_period DATE NOT NULL,
    budget_category VARCHAR(100) NOT NULL,
    budget_amount DECIMAL(15,2) DEFAULT 0.00,
    actual_amount DECIMAL(15,2) DEFAULT 0.00,
    variance_amount DECIMAL(15,2) DEFAULT 0.00,
    variance_percentage DECIMAL(5,2) DEFAULT 0.00,
    variance_type ENUM('favorable', 'unfavorable') DEFAULT 'favorable',
    report_type ENUM('revenue', 'expense', 'capex') NOT NULL,
    department VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_budget_vs_actual_period (report_period),
    INDEX idx_budget_vs_actual_category (budget_category),
    INDEX idx_budget_vs_actual_type (report_type)
);

-- Create unit economics tracking table
CREATE TABLE IF NOT EXISTS unit_economics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    calculation_date DATE NOT NULL,
    customer_acquisition_cost DECIMAL(10,2) DEFAULT 0.00,
    customer_lifetime_value DECIMAL(10,2) DEFAULT 0.00,
    payback_period DECIMAL(5,2) DEFAULT 0.00, -- in months
    ltv_to_cac_ratio DECIMAL(5,2) DEFAULT 0.00,
    gross_margin DECIMAL(5,2) DEFAULT 0.00,
    retention_rate DECIMAL(5,2) DEFAULT 0.00,
    churn_rate DECIMAL(5,2) DEFAULT 0.00,
    average_order_value DECIMAL(10,2) DEFAULT 0.00,
    purchase_frequency DECIMAL(5,2) DEFAULT 0.00, -- per year
    customer_lifespan DECIMAL(5,2) DEFAULT 0.00, -- in years
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_unit_economics_date (calculation_date)
);

-- Create growth metrics tracking table
CREATE TABLE IF NOT EXISTS growth_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL,
    arr DECIMAL(15,2) DEFAULT 0.00, -- Annual Recurring Revenue
    mrr DECIMAL(15,2) DEFAULT 0.00, -- Monthly Recurring Revenue
    churn_rate DECIMAL(5,4) DEFAULT 0.0000, -- Monthly churn rate
    net_revenue_retention DECIMAL(5,2) DEFAULT 0.00,
    customer_count INT DEFAULT 0,
    new_customers INT DEFAULT 0,
    nps_score DECIMAL(5,2) DEFAULT 0.00, -- Net Promoter Score
    market_share DECIMAL(5,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_growth_metrics_date (metric_date)
);

-- Create operational metrics tracking table
CREATE TABLE IF NOT EXISTS operational_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL,
    burn_rate DECIMAL(15,2) DEFAULT 0.00, -- Monthly burn rate
    runway_months DECIMAL(5,2) DEFAULT 0.00,
    employee_count INT DEFAULT 0,
    revenue_per_employee DECIMAL(10,2) DEFAULT 0.00,
    orders_per_day DECIMAL(10,2) DEFAULT 0.00,
    conversion_rate DECIMAL(5,4) DEFAULT 0.0000,
    average_order_processing_time DECIMAL(5,2) DEFAULT 0.00, -- in hours
    fulfillment_accuracy DECIMAL(5,4) DEFAULT 0.0000,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_operational_metrics_date (metric_date)
);

-- Create financial risk indicators table
CREATE TABLE IF NOT EXISTS financial_risk_indicators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    indicator_date DATE NOT NULL,
    current_ratio DECIMAL(5,2) DEFAULT 0.00,
    quick_ratio DECIMAL(5,2) DEFAULT 0.00,
    debt_to_equity_ratio DECIMAL(5,2) DEFAULT 0.00,
    interest_coverage_ratio DECIMAL(5,2) DEFAULT 0.00,
    accounts_receivable_turnover DECIMAL(5,2) DEFAULT 0.00,
    inventory_turnover DECIMAL(5,2) DEFAULT 0.00,
    working_capital DECIMAL(15,2) DEFAULT 0.00,
    cash_ratio DECIMAL(5,2) DEFAULT 0.00,
    risk_level ENUM('low', 'medium', 'high') DEFAULT 'low',
    risk_factors JSON,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_financial_risk_date (indicator_date),
    INDEX idx_financial_risk_level (risk_level)
);

-- Create executive dashboard configuration table
CREATE TABLE IF NOT EXISTS executive_dashboard_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dashboard_name VARCHAR(100) NOT NULL,
    user_id INT NOT NULL,
    config_data JSON NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_dashboard_config_user (user_id),
    INDEX idx_dashboard_config_default (is_default)
);

-- Create executive reports table
CREATE TABLE IF NOT EXISTS executive_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_name VARCHAR(255) NOT NULL,
    report_type ENUM('cash_flow', 'budget_variance', 'unit_economics', 'growth_metrics', 'operational_metrics', 'risk_indicators') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    report_data JSON NOT NULL,
    generated_by INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_executive_reports_type (report_type),
    INDEX idx_executive_reports_period (period_start, period_end),
    INDEX idx_executive_reports_generated (generated_at)
);

-- Insert default dashboard configuration
INSERT IGNORE INTO executive_dashboard_config (dashboard_name, user_id, config_data, is_default) 
VALUES ('CFO Dashboard', 1, JSON_OBJECT(
    'widgets', JSON_ARRAY(
        'cash_flow_forecast',
        'budget_variance',
        'unit_economics',
        'growth_metrics',
        'risk_indicators'
    ),
    'refresh_interval', 300,
    'date_range', 'last_90_days'
), TRUE);

-- Create views for reporting

-- Cash Flow Forecast Summary View
CREATE OR REPLACE VIEW cash_flow_forecast_summary AS
SELECT 
    forecast_date,
    period,
    SUM(cash_inflows) as total_inflows,
    SUM(cash_outflows) as total_outflows,
    SUM(net_cash_flow) as net_cash_flow,
    SUM(opening_balance) as opening_balance,
    SUM(closing_balance) as closing_balance,
    AVG(confidence_level) as avg_confidence
FROM cash_flow_forecasts
WHERE forecast_type IN ('actual', 'predicted')
GROUP BY forecast_date, period
ORDER BY forecast_date DESC;

-- Budget Variance Summary View
CREATE OR REPLACE VIEW budget_variance_summary AS
SELECT 
    report_period,
    budget_category,
    report_type,
    SUM(budget_amount) as total_budget,
    SUM(actual_amount) as total_actual,
    SUM(variance_amount) as total_variance,
    AVG(variance_percentage) as avg_variance_pct
FROM budget_vs_actual
GROUP BY report_period, budget_category, report_type
ORDER BY report_period DESC, budget_category;

-- Unit Economics Summary View
CREATE OR REPLACE VIEW unit_economics_summary AS
SELECT 
    calculation_date,
    customer_acquisition_cost,
    customer_lifetime_value,
    payback_period,
    ltv_to_cac_ratio,
    gross_margin,
    retention_rate,
    churn_rate,
    average_order_value,
    purchase_frequency,
    customer_lifespan
FROM unit_economics
ORDER BY calculation_date DESC;

-- Growth Metrics Summary View
CREATE OR REPLACE VIEW growth_metrics_summary AS
SELECT 
    metric_date,
    arr,
    mrr,
    churn_rate,
    net_revenue_retention,
    customer_count,
    new_customers,
    nps_score,
    market_share
FROM growth_metrics
ORDER BY metric_date DESC;

-- Operational Metrics Summary View
CREATE OR REPLACE VIEW operational_metrics_summary AS
SELECT 
    metric_date,
    burn_rate,
    runway_months,
    employee_count,
    revenue_per_employee,
    orders_per_day,
    conversion_rate,
    average_order_processing_time,
    fulfillment_accuracy
FROM operational_metrics
ORDER BY metric_date DESC;

-- Financial Risk Indicators Summary View
CREATE OR REPLACE VIEW financial_risk_summary AS
SELECT 
    indicator_date,
    current_ratio,
    quick_ratio,
    debt_to_equity_ratio,
    interest_coverage_ratio,
    accounts_receivable_turnover,
    inventory_turnover,
    working_capital,
    cash_ratio,
    risk_level
FROM financial_risk_indicators
ORDER BY indicator_date DESC;