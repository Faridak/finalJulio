# Comprehensive Accounting System Documentation

## Overview
This document provides detailed information about the comprehensive accounting system implemented for the e-commerce platform. The system includes all the features requested, including chart of accounts, sales commission tracking, marketing expense management, operations costing, product costing, payroll integration, financial reporting, and monetary value attribution.

## System Components

### 1. Chart of Accounts
The system implements a flexible chart of accounts structure with the following categories:

#### Assets (1000-1999)
- 1000: Cash
- 1100: Accounts Receivable
- 1200: Inventory
- 1300: Prepaid Expenses
- 1400: Equipment
- 1500: Accumulated Depreciation

#### Liabilities (2000-2999)
- 2000: Accounts Payable
- 2100: Sales Tax Payable
- 2200: Income Tax Payable
- 2300: Loan Payable
- 2400: Accrued Commissions Payable
- 2500: Accrued Payroll Payable
- 2600: Accrued Expenses

#### Equity (3000-3999)
- 3000: Owner Equity
- 3100: Retained Earnings
- 3200: Common Stock

#### Revenue (4000-4999)
- 4000: Product Sales
- 4100: Service Revenue
- 4200: Shipping Revenue
- 4300: Commission Income
- 4400: Other Income

#### Expenses (5000-5999)
- 5000: Cost of Goods Sold
- 5100: Salaries and Wages
- 5200: Rent Expense
- 5300: Utilities Expense
- 5400: Marketing Expense
- 5500: Shipping Expense
- 5600: Bank Fees
- 5700: Insurance Expense
- 5800: Depreciation Expense
- 5900: Miscellaneous Expense
- 5910: Commission Expense
- 5920: Social Media Ads
- 5930: Processing Fees
- 5940: Promotional Costs
- 5950: Shipping and Packaging
- 5960: Payment Processing Fees

### 2. Sales Commission System

#### Features
- Threshold Management: Track cumulative sales per salesperson
- Tier Progression: Automatic tier upgrades (5% → 7.5% → 10% → 12.5% → 15%)
- Performance Monitoring: 3-month sales tracking with alerts
- Commission Calculations: Real-time commission computation

#### Commission Tiers
1. Bronze: 0+ sales, 5% commission rate
2. Silver: $10,000+ sales, 7.5% commission rate
3. Gold: $25,000+ sales, 10% commission rate
4. Platinum: $50,000+ sales, 12.5% commission rate
5. Diamond: $100,000+ sales, 15% commission rate

#### Accounting Integration
- Debit: Commission Expense (5910)
- Credit: Accrued Commissions Payable (2400)
- When paid: Debit Accrued Commissions (2400), Credit Cash (1000)

### 3. Marketing & Operations Costing

#### Marketing Expense Tracking
- Campaign Attribution: Link expenses to specific campaigns
- ROI Calculation: Revenue attribution per marketing channel
- Cost Categories: Social media ads, processing fees, promotional costs

#### Operations Cost Allocation
- Direct Costs: Shipping, packaging, payment processing
- Indirect Costs: Utilities, rent, software subscriptions
- Cost Centers: Warehouse, customer service, admin

### 4. Product Costing & Inventory
- COGS Calculation: FIFO, LIFO, or weighted average methods
- Inventory Valuation: Real-time inventory value tracking
- Production Costs: Materials, labor, overhead allocation

### 5. Payroll Integration
#### Payroll Categories
- Staff: Fixed salaries with benefits
- Contract Employees: Variable payments
- Sales Team: Base + commission structure

#### Accounting Entries
- Salary Expense / Commission Expense (Debit)
- Payroll Taxes Payable (Credit)
- Net Pay Payable (Credit)

### 6. Financial Reporting

#### Balance Sheet Components
- Current Ratio: Current Assets / Current Liabilities
- Quick Ratio: (Current Assets - Inventory) / Current Liabilities
- Debt-to-Equity: Total Debt / Total Equity
- Inventory Turnover: COGS / Average Inventory

#### P&L Statement
- Gross Revenue
- Less: Returns & Refunds
- Net Revenue
- Less: COGS
- Gross Profit
- Less: Operating Expenses (Marketing, Payroll, Operations)
- Operating Income
- Less: Interest & Taxes
- Net Income

### 7. Monetary Value Attribution System

#### Key Metrics Tracked
- Customer Acquisition Cost (CAC): Marketing spend / New customers
- Customer Lifetime Value (CLV): Average order value × Purchase frequency × Customer lifespan
- Marketing ROI: (Revenue - Marketing Cost) / Marketing Cost
- Commission ROI: (Sales generated - Commission paid) / Commission paid

## Technical Implementation

### Real-time Processing
- Trigger-based commission calculations
- Automated journal entries for transactions
- Real-time inventory updates

### Data Integrity
- Double-entry validation (Debits = Credits)
- Audit trails for all financial transactions
- Reconciliation processes

### Performance Optimization
- Indexed tables for financial queries
- Materialized views for complex reports
- Caching for frequently accessed ratios

### Security & Compliance
- Role-based access control
- Audit logging
- Data backup and recovery
- Tax compliance features

## API Endpoints

### Chart of Accounts
- `GET /api/accounting-api.php?action=get_chart_of_accounts`
- `GET /api/accounting-api.php?action=get_account_balance&account_id={id}`

### General Ledger
- `POST /api/accounting-api.php?action=add_journal_entry`
- `GET /api/accounting-api.php?action=get_general_ledger`

### Accounts Payable/Receivable
- `GET /api/accounting-api.php?action=get_accounts_payable`
- `GET /api/accounting-api.php?action=get_accounts_receivable`
- `POST /api/accounting-api.php?action=add_account_payable`
- `POST /api/accounting-api.php?action=add_account_receivable`
- `POST /api/accounting-api.php?action=pay_account_payable`
- `POST /api/accounting-api.php?action=receive_account_receivable`

### Sales Commission
- `GET /api/accounting-api.php?action=get_sales_commissions`
- `POST /api/accounting-api.php?action=add_sales_commission`
- `GET /api/accounting-api.php?action=get_commission_tiers`
- `GET /api/accounting-api.php?action=calculate_commission&salesperson_id={id}&sales_amount={amount}`

### Marketing
- `GET /api/accounting-api.php?action=get_marketing_campaigns`
- `POST /api/accounting-api.php?action=add_marketing_campaign`
- `GET /api/accounting-api.php?action=get_marketing_expenses`
- `POST /api/accounting-api.php?action=add_marketing_expense`

### Operations
- `GET /api/accounting-api.php?action=get_operations_costs`
- `POST /api/accounting-api.php?action=add_operations_cost`

### Product Costing
- `GET /api/accounting-api.php?action=get_product_costing&product_id={id}`
- `POST /api/accounting-api.php?action=update_product_costing`

### Payroll
- `GET /api/accounting-api.php?action=get_payroll`
- `POST /api/accounting-api.php?action=add_payroll`

### Financial Reports
- `GET /api/accounting-api.php?action=generate_financial_report&report_type={type}&start_date={date}&end_date={date}`
- `GET /api/accounting-api.php?action=get_financial_ratios&end_date={date}`
- `GET /api/accounting-api.php?action=get_monetary_attribution`

## Implementation Priority

1. Set up basic chart of accounts and general ledger
2. Implement sales commission tracking
3. Add product costing and inventory management
4. Build marketing expense attribution
5. Create automated financial reporting
6. Add advanced analytics and ratios

## User Interfaces

### Accounting Dashboard
- Financial summary cards
- Recent transactions view
- Chart of accounts overview
- Quick report generation

### Commission Tracking
- Commission summary metrics
- Tier level visualization
- Individual commission records
- Commission payment processing

### Marketing Expenses
- Campaign performance tracking
- Expense categorization
- ROI calculations
- Budget vs. actual spending

### Accounts Payable/Receivable
- Invoice management
- Payment processing
- Aging reports
- Status tracking

### Financial Reports
- Income statement generation
- Balance sheet creation
- Cash flow statements
- Custom date range reporting

## Database Schema

### Core Tables
1. `chart_of_accounts` - Account structure
2. `general_ledger` - Transaction records
3. `accounts_payable` - Vendor invoices
4. `accounts_receivable` - Customer invoices
5. `financial_reports` - Generated reports

### Enhanced Tables
1. `sales_commissions` - Commission tracking
2. `commission_tiers` - Commission rate structure
3. `marketing_campaigns` - Campaign management
4. `marketing_expenses` - Marketing costs
5. `operations_costs` - Operational expenses
6. `product_costing` - Product cost tracking
7. `payroll` - Employee compensation

## Security Considerations

- All API endpoints require admin authentication
- Role-based access control implemented
- CSRF protection for form submissions
- Secure session management
- Input validation and sanitization
- SQL injection prevention through prepared statements

## Testing and Validation

The system has been tested with:
- Sample data insertion for all modules
- API endpoint validation
- User interface functionality checks
- Data integrity verification
- Performance benchmarking

## Maintenance and Updates

Regular maintenance tasks include:
- Database backup procedures
- Report generation scheduling
- Commission calculation updates
- Campaign performance reviews
- System security audits