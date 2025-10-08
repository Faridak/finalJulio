# Business Metrics Monitoring System

## Overview

The Business Metrics Monitoring system tracks key performance indicators and sends alerts when important thresholds are breached. It provides real-time insights into business operations and helps identify potential issues before they become critical.

## Key Features

1. **Real-time Monitoring** - Continuously tracks business metrics
2. **Automated Alerts** - Sends notifications when thresholds are breached
3. **Dashboard Interface** - Visual representation of key metrics
4. **Cron Job Processing** - Automated periodic checks
5. **Comprehensive Reporting** - Detailed analytics and trends

## Monitored Metrics

### Sales Metrics
- Sales velocity (orders and revenue per hour)
- Conversion rates
- Order volumes and revenue trends

### Inventory Metrics
- Low stock alerts
- Out of stock notifications
- Inventory value tracking

### Financial Metrics
- Payment success/failure rates
- Product margin analysis
- Revenue tracking

### User Metrics
- New user registrations
- Active user counts
- User engagement

### Commission Metrics
- Salesperson performance tracking
- Commission tier progression alerts

## Installation

1. Include the BusinessMetricsMonitor class in your project:
   ```php
   require_once 'includes/BusinessMetricsMonitor.php';
   ```

2. Set up the cron job to run periodic checks:
   ```bash
   */15 * * * * cd /path/to/your/project && php cron/business-metrics-monitor.php
   ```

## Configuration

The system uses default thresholds for alerting:

- Sales velocity drop: >50% decrease from average
- Conversion rate drop: >30% decrease from 7-day average
- Low inventory: Stock level at or below reorder point
- Out of stock: Stock level at zero
- Low margin: Product margin < 10%
- Payment failures: >5 failed payments in 24 hours
- Commission tiers: Within $1000 of next tier threshold

## Usage

### Manual Check

To manually check metrics and send alerts:

```php
$metricsMonitor = new BusinessMetricsMonitor($pdo);
$result = $metricsMonitor->checkMetricsAndAlert();

if ($result['success']) {
    echo "Sent {$result['alerts_sent']} alerts";
}
```

### Dashboard Data

To retrieve dashboard metrics:

```php
$metricsMonitor = new BusinessMetricsMonitor($pdo);
$dashboardMetrics = $metricsMonitor->getDashboardMetrics();

echo "Orders (24h): " . $dashboardMetrics['sales']['orders_last_24h'];
echo "Revenue (24h): $" . number_format($dashboardMetrics['sales']['revenue_last_24h'], 2);
```

### Sales Reports

To generate sales reports:

```php
$metricsMonitor = new BusinessMetricsMonitor($pdo);
$salesReport = $metricsMonitor->getSalesReport('30d'); // 30 days

foreach ($salesReport['daily_data'] as $day) {
    echo "{$day['date']}: {$day['orders']} orders, $" . number_format($day['revenue'], 2) . " revenue\n";
}
```

### Top Selling Products

To get top selling products:

```php
$metricsMonitor = new BusinessMetricsMonitor($pdo);
$topProducts = $metricsMonitor->getTopSellingProducts(10, '30d'); // Top 10, 30 days

foreach ($topProducts as $product) {
    echo "{$product['name']}: {$product['units_sold']} units, $" . number_format($product['revenue'], 2) . " revenue\n";
}
```

## Alert Types

The system generates the following alert types:

1. **Sales Velocity Drop** - Significant decrease in orders or revenue
2. **Sales Velocity Spike** - Significant increase in orders or revenue
3. **Conversion Rate Drop** - Decrease in order completion rate
4. **Commission Tier Approaching** - Salesperson near next commission tier
5. **Low Inventory** - Stock levels at or below reorder point
6. **Out of Stock** - Products with zero inventory
7. **Low Product Margin** - Products with margins below threshold
8. **Payment Failures** - High number of failed payments

## Admin Interface

The admin interface at `/admin/business-metrics.php` provides:

- Real-time dashboard with key metrics
- Sales trend visualization
- Top selling products list
- Inventory summary
- Financial overview
- Manual metric checking

## Cron Job

Set up the following cron job to automatically check metrics:

```bash
*/15 * * * * cd /path/to/your/project && php cron/business-metrics-monitor.php
```

This runs every 15 minutes to check metrics and send alerts.

## Customization

### Adding New Metrics

To add new metrics to monitor:

1. Add a new method in the `BusinessMetricsMonitor` class
2. Add the check to the `checkMetricsAndAlert()` method
3. Add alert handling in the `sendAlerts()` method

### Modifying Thresholds

To modify alert thresholds:

1. Edit the threshold values in the individual check methods
2. Adjust the conditions that trigger alerts

### Custom Alert Actions

To customize alert actions:

1. Modify the `sendAlertToAdmins()` method
2. Add additional notification channels (email, SMS, Slack, etc.)
3. Customize the alert message formatting

## API Reference

### BusinessMetricsMonitor Methods

- `checkMetricsAndAlert()` - Check all metrics and send alerts
- `getDashboardMetrics()` - Get key dashboard metrics
- `getSalesReport($period)` - Get sales report for specified period
- `getTopSellingProducts($limit, $period)` - Get top selling products
- `checkSalesVelocity()` - Check sales velocity metrics
- `checkConversionRates()` - Check conversion rate metrics
- `checkCommissionThresholds()` - Check commission thresholds
- `checkInventoryLevels()` - Check inventory levels
- `checkFinancialMetrics()` - Check financial metrics

## Troubleshooting

### Common Issues

1. **Alerts not being sent**
   - Check that admin users exist in the database
   - Verify that the NotificationSystem is working
   - Check the error logs for specific issues

2. **Metrics not updating**
   - Ensure the cron job is running
   - Check database connectivity
   - Verify that orders and other data are being recorded

3. **Performance issues**
   - Optimize database queries
   - Consider caching frequently accessed data
   - Adjust cron job frequency for lower-impact checks

### Debugging

Enable debug logging by checking the application error logs or by adding custom logging to specific methods.

## Security

The system follows standard security practices:

- Only accessible to admin users
- Uses prepared statements to prevent SQL injection
- Validates all input data
- Follows principle of least privilege