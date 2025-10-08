# Webhook System Documentation

## Overview

The webhook system provides a comprehensive solution for handling incoming and outgoing webhooks from various external services. It includes:

1. **Incoming Webhook Handler** - Processes webhooks from external services like Shopify, WooCommerce, Mailchimp, etc.
2. **Outgoing Webhook Manager** - Sends webhooks to external services when events occur in your system
3. **Webhook Monitoring** - Tracks webhook performance and sends alerts for issues
4. **Admin Interface** - Manages webhook configurations and monitors events
5. **Cron Job Processor** - Processes pending webhook events and retries failed ones

## Installation

1. Run the database schema script to create the necessary tables:
   ```sql
   SOURCE webhooks/webhook-schema.sql
   ```

2. Include the webhook manager and monitoring classes in your project:
   ```php
   require_once 'includes/WebhookManager.php';
   require_once 'includes/WebhookMonitoring.php';
   ```

## Configuration

### Environment Variables

Set the following environment variables in your `.env` file:

```env
# Shopify webhook secret
SHOPIFY_WEBHOOK_SECRET=your_shopify_secret

# WooCommerce webhook secret
WOOCOMMERCE_WEBHOOK_SECRET=your_woocommerce_secret

# Facebook verify token
FACEBOOK_VERIFY_TOKEN=your_facebook_token
```

## Incoming Webhooks

### Supported Services

The system currently supports webhooks from:

- Shopify
- WooCommerce
- Amazon
- Facebook
- Google
- Mailchimp
- ShipStation

### Endpoint URLs

Incoming webhooks should be sent to:

```
POST /webhooks/general-webhook.php?source={source}
```

Where `{source}` is one of the supported services (shopify, woocommerce, etc.).

### Example

For Shopify webhooks:
```
POST https://yoursite.com/webhooks/general-webhook.php?source=shopify
```

## Outgoing Webhooks

### Sending Webhooks

To send a webhook to an external service:

```php
$webhookManager = new WebhookManager($pdo);

// Register a webhook configuration
$result = $webhookManager->registerWebhook(
    'my-service',
    'https://external-service.com/webhook',
    'secret-key',
    'hmac'
);

if ($result['success']) {
    $configId = $result['config_id'];
    
    // Send a webhook event
    $payload = [
        'event' => 'order_created',
        'order_id' => 12345,
        'customer_name' => 'John Doe'
    ];
    
    $webhookManager->sendWebhook($configId, 'order_created', $payload);
}
```

### Webhook Events

The system tracks the following webhook event statuses:

- **Pending** - Webhook is queued for delivery
- **Delivered** - Webhook was successfully delivered
- **Failed** - Webhook delivery failed (will be retried)

## Monitoring and Alerts

The webhook monitoring system automatically checks for issues and sends alerts to administrators.

### Health Checks

The system monitors:

- High failure rates (>10%)
- Delivery delays (>5 minutes)
- Unprocessed events (>10 pending)

### Performance Metrics

The system tracks:

- Success rates
- Delivery times
- Event volumes

## Admin Interface

The admin interface at `/admin/webhooks.php` provides:

- Webhook configuration management
- Health status monitoring
- Event log viewing
- Manual event retrying

## Cron Jobs

Set up the following cron job to process webhook events:

```bash
* * * * * cd /path/to/your/project && php cron/webhook-processor.php
```

This runs every minute to process pending events and retry failed ones.

## Security

### Signature Verification

For services that support HMAC signatures (Shopify, WooCommerce), the system automatically verifies the signature using the configured secret key.

### Rate Limiting

The system implements retry delays that increase with each failed attempt:
- First retry: 1 minute
- Second retry: 2 minutes
- Third retry: 3 minutes
- And so on...

## Extending the System

### Adding New Services

To add support for a new service:

1. Add a case in the `processWebhook()` method in `general-webhook.php`
2. Implement the specific processing logic
3. Add verification logic if the service supports signatures

### Custom Event Processing

To customize how events are processed:

1. Extend the `WebhookManager` class
2. Override the `sendWebhookRequest()` method
3. Add custom logic for your specific requirements

## Troubleshooting

### Common Issues

1. **Webhooks not being received**
   - Check that the endpoint URL is correct
   - Verify that your server can receive POST requests
   - Check the webhook logs in the admin interface

2. **Signature verification failing**
   - Ensure the secret key is correctly configured
   - Verify that the service is sending the correct signature header

3. **Delivery failures**
   - Check that the destination URL is correct and accessible
   - Verify that the destination service is accepting webhooks

### Debugging

Enable debug logging by checking the webhook event logs in the admin interface or by examining the `webhooks_log` table in the database.

## API Reference

### WebhookManager Methods

- `registerWebhook($source, $webhookUrl, $secretKey, $verificationMethod, $configData)`
- `sendWebhook($configId, $eventType, $payload)`
- `sendWebhookWithRetry($configId, $eventType, $payload)`
- `sendWebhookToSubscribers($eventType, $payload)`
- `processPendingEvents()`
- `getWebhookStats()`
- `getRecentEvents($limit)`
- `retryEvent($eventId)`

### WebhookMonitoring Methods

- `checkForIssues()`
- `getPerformanceMetrics($timeRange)`
- `getHealthStatus()`
- `getEventLog($source, $limit)`
- `cleanupOldEvents($daysOld)`