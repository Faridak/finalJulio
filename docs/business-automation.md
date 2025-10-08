# Business Automation System

## Overview

The Business Automation System automates key business processes to reduce manual work, improve accuracy, and ensure timely execution of important tasks. It includes automated commission tier progression, inventory alerts, financial period closing, and marketing ROI calculations.

## Key Features

1. **Commission Tier Progression** - Automatically advances salespeople to higher commission tiers
2. **Inventory Alerts** - Sends notifications for low stock items
3. **Financial Period Closing** - Automates month-end financial processing
4. **Marketing ROI Calculation** - Calculates and tracks marketing campaign performance
5. **Scheduled Task Management** - Configurable automation schedules
6. **Process Logging** - Detailed logs of all automation activities
7. **Manual Controls** - Ability to trigger automations manually

## Installation

1. Run the automation schema script to create the necessary tables:
   ```sql
   SOURCE automation/automation-schema.sql
   ```

2. Include the BusinessAutomation class in your project:
   ```php
   require_once 'includes/BusinessAutomation.php';
   ```

## Configuration

The system uses default schedules for automation processes:

- **Commission Tier Progression**: Daily at 2:00 AM
- **Inventory Alerts**: Every 30 minutes
- **Marketing ROI Calculation**: Daily at 3:00 AM
- **Financial Period Closing**: Monthly on the 1st at 1:00 AM
- **Cleanup Old Records**: Monthly on the 1st at 4:00 AM

## Usage

### Automatic Commission Tier Progression

To automatically progress sales commissions to the next tier:

```php
$businessAutomation = new BusinessAutomation($pdo);
$result = $businessAutomation->autoProgressCommissionTiers();

if ($result['success']) {
    echo "Progressed {$result['progressed_count']} salespeople";
}
```

### Inventory Alerts

To check inventory levels and send alerts for low stock:

```php
$businessAutomation = new BusinessAutomation($pdo);
$result = $businessAutomation->checkInventoryAndAlert();

if ($result['success']) {
    echo "Sent {$result['alert_count']} alerts";
}
```

### Financial Period Closing

To automatically close financial periods:

```php
$businessAutomation = new BusinessAutomation($pdo);
$result = $businessAutomation->autoCloseFinancialPeriod();

if ($result['success']) {
    if (isset($result['message'])) {
        echo $result['message'];
    } else {
        echo "Period {$result['period']} closed with net income: $" . number_format($result['net_income'], 2);
    }
}
```

### Marketing ROI Calculation

To calculate and update marketing ROI:

```php
$businessAutomation = new BusinessAutomation($pdo);
$result = $businessAutomation->calculateMarketingROI();

if ($result['success']) {
    echo "Updated {$result['updated_count']} campaigns";
}
```

### Run All Automations

To run all automated business processes:

```php
$businessAutomation = new BusinessAutomation($pdo);
$results = $businessAutomation->runAllAutomations();

foreach ($results as $process => $result) {
    if ($result['success']) {
        echo "{$process}: Success\n";
    } else {
        echo "{$process}: Failed - {$result['error']}\n";
    }
}
```

### Get Automation Statistics

To retrieve automation statistics:

```php
$businessAutomation = new BusinessAutomation($pdo);
$stats = $businessAutomation->getAutomationStats();

echo "Total salespeople: " . array_sum(array_column($stats['commission_tiers'], 'salesperson_count'));
echo "Low stock items: " . ($stats['inventory']['low_stock_items'] ?? 0);
```

### Clean Up Old Records

To clean up old automation records:

```php
$businessAutomation = new BusinessAutomation($pdo);
$result = $businessAutomation->cleanupOldRecords(90); // 90 days

if ($result['success']) {
    echo "Deleted {$result['deleted_notifications']} notifications and {$result['deleted_logs']} logs";
}
```

## Admin Interface

The admin interface at `/admin/automation.php` provides:

- Manual automation controls
- Scheduled task management
- Automation statistics dashboard
- Recent automation logs
- Cleanup controls

## Cron Job

Set up the following cron job to automatically run business automations:

```bash
*/30 * * * * cd /path/to/your/project && php cron/business-automation.php
```

This runs every 30 minutes to check inventory alerts and other time-sensitive automations.

For more resource-intensive processes, you can set specific schedules:

```bash
0 2 * * * cd /path/to/your/project && php cron/business-automation.php --process=commission
0 3 * * * cd /path/to/your/project && php cron/business-automation.php --process=marketing
0 1 1 * * cd /path/to/your/project && php cron/business-automation.php --process=financial
```

## Scheduled Tasks

The system manages scheduled tasks through the `scheduled_tasks` table:

- **commission_tier_progression** - Daily commission tier checks
- **inventory_alerts** - Regular inventory monitoring
- **marketing_roi_calculation** - Marketing performance updates
- **financial_period_closing** - Month-end financial processing
- **cleanup_old_records** - Periodic data cleanup

## Customization

### Adding New Automations

To add new automated processes:

1. Add a method to the `BusinessAutomation` class
2. Add the process to the `runAllAutomations()` method
3. Add a scheduled task entry in the database
4. Add manual control to the admin interface

### Modifying Schedules

To modify automation schedules:

1. Update the cron expressions in the `scheduled_tasks` table
2. Adjust the logic in the cron job script
3. Update the admin interface controls if needed

### Custom Alert Actions

To customize alert actions:

1. Modify the notification creation methods
2. Add additional communication channels (email, SMS, Slack, etc.)
3. Customize the alert message formatting

## API Reference

### BusinessAutomation Methods

- `autoProgressCommissionTiers()` - Automatically progress sales commissions
- `checkInventoryAndAlert()` - Check inventory and send alerts
- `autoCloseFinancialPeriod()` - Close financial periods
- `calculateMarketingROI()` - Calculate marketing ROI
- `runAllAutomations()` - Run all automated processes
- `getAutomationStats()` - Get automation statistics
- `cleanupOldRecords($daysOld)` - Clean up old records

## Troubleshooting

### Common Issues

1. **Automations not running**
   - Check that cron jobs are properly configured
   - Verify database connectivity
   - Check automation logs for errors

2. **Commission tier progression failing**
   - Verify commission tiers are properly configured
   - Check sales commission data integrity
   - Ensure sales data is up to date

3. **Inventory alerts not sending**
   - Check notification system is working
   - Verify inventory levels and reorder points
   - Confirm user roles for receiving alerts

4. **Financial period closing issues**
   - Check that previous periods are closed
   - Verify financial data integrity
   - Ensure proper permissions for financial operations

### Debugging

Enable debug logging by checking the automation logs or by adding custom logging to specific methods.

## Best Practices

### Commission Management

1. Regularly review and update commission tiers
2. Monitor salesperson progression
3. Validate commission calculations
4. Communicate tier changes to sales team

### Inventory Management

1. Set appropriate reorder points
2. Regularly review inventory alerts
3. Monitor stock turnover rates
4. Optimize inventory levels based on demand

### Financial Processing

1. Close financial periods consistently
2. Reconcile automated data with manual records
3. Monitor financial performance metrics
4. Maintain audit trails for all financial operations

### Marketing Analytics

1. Track campaign performance regularly
2. Adjust marketing strategies based on ROI
3. Monitor campaign costs vs. revenue
4. Optimize marketing spend allocation

## Security

The system follows standard security practices:

- Only accessible to admin users
- Uses prepared statements to prevent SQL injection
- Validates all input data
- Follows principle of least privilege
- Maintains audit trails for all operations

## Compliance

The system helps with compliance requirements by:

- Providing audit trails
- Automating consistent processes
- Maintaining accurate financial records
- Supporting data retention policies

Regular reviews and updates are recommended to maintain compliance.