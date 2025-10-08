# Advanced Security System

## Overview

The Advanced Security System provides comprehensive security features including role-based access control, data encryption, session management, API security, and monitoring capabilities. It enhances the basic security measures with enterprise-grade security features.

## Key Features

1. **Role-Based Access Control (RBAC)** - Fine-grained permission management
2. **Data Encryption** - AES-256 encryption for sensitive data
3. **API Security** - Token-based authentication with rate limiting
4. **Session Management** - Secure session handling
5. **Security Monitoring** - Audit logs and suspicious activity detection
6. **IP Blocking** - Temporary IP address blocking
7. **Rate Limiting** - Protection against abuse and DoS attacks

## Installation

1. Run the security schema script to create the necessary tables:
   ```sql
   SOURCE security/security-schema.sql
   ```

2. Include the AdvancedSecurity class in your project:
   ```php
   require_once 'includes/AdvancedSecurity.php';
   ```

3. Set up the encryption key in your `.env` file:
   ```env
   ENCRYPTION_KEY=your_secure_encryption_key_here
   ```

## Configuration

### Environment Variables

Set the following environment variables in your `.env` file:

```env
# Encryption key for data encryption
ENCRYPTION_KEY=your_secure_256_bit_encryption_key

# Session configuration
SESSION_TIMEOUT=3600
```

## Role-Based Access Control

### Default Roles

The system includes the following default roles:

1. **super_admin** - Full system access
2. **admin** - Administrative access to most functions
3. **manager** - Business operations access
4. **sales** - Sales team access
5. **customer** - Customer account access
6. **guest** - Limited guest access

### Permissions

Permissions are grouped by category:

- **System** - User and role management
- **Analytics** - Reports and analytics
- **Catalog** - Product management
- **Sales** - Order management
- **Inventory** - Inventory management
- **Finance** - Financial data and payments
- **Marketing** - Marketing campaigns
- **Customers** - Customer data
- **Operations** - Shipping and fulfillment
- **Security** - Security logs and audit trails

## Usage

### Permission Checking

To check if a user has a specific permission:

```php
$advancedSecurity = new AdvancedSecurity($pdo);

// Check if current user has permission
if ($advancedSecurity->hasPermission($_SESSION['user_id'], 'manage_products')) {
    // User has permission to manage products
    echo "Access granted";
} else {
    // User does not have permission
    echo "Access denied";
}
```

### Require Permission

To require a specific permission for access:

```php
$advancedSecurity = new AdvancedSecurity($pdo);

// This will die with 403 error if user doesn't have permission
$advancedSecurity->requirePermission('manage_users');
```

### Data Encryption

To encrypt sensitive data:

```php
$advancedSecurity = new AdvancedSecurity($pdo);

// Encrypt data
$encrypted = $advancedSecurity->encryptData('sensitive information');

// Decrypt data
$decrypted = $advancedSecurity->decryptData($encrypted);
```

### Password Hashing

To hash and verify passwords:

```php
$advancedSecurity = new AdvancedSecurity($pdo);

// Hash password
$hashedPassword = $advancedSecurity->hashData('user_password');

// Verify password
if ($advancedSecurity->verifyHash('user_password', $hashedPassword)) {
    echo "Password is correct";
}
```

### API Token Management

To create an API token:

```php
$advancedSecurity = new AdvancedSecurity($pdo);

// Create API token for user (expires in 30 days)
$token = $advancedSecurity->createApiToken($userId, 30);

// Validate API token
$tokenInfo = $advancedSecurity->validateApiToken($token);

// Revoke API token
$advancedSecurity->revokeApiToken($token);
```

### Rate Limiting

To implement rate limiting:

```php
$advancedSecurity = new AdvancedSecurity($pdo);

// Check rate limit for API endpoint (60 requests per hour)
if ($advancedSecurity->checkRateLimit('api_endpoint_' . $_SERVER['REMOTE_ADDR'])) {
    // Within rate limit - process request
    processApiRequest();
} else {
    // Rate limit exceeded
    http_response_code(429);
    echo "Rate limit exceeded";
}
```

### Security Logging

To log security events:

```php
$advancedSecurity = new AdvancedSecurity($pdo);

// Log security event
$advancedSecurity->logSecurityEvent('failed_login', [
    'username' => $username,
    'ip_address' => $_SERVER['REMOTE_ADDR']
], 'warning');

// Get security logs
$logs = $advancedSecurity->getSecurityLogs(100, 'warning');
```

### Suspicious Activity Detection

To check for suspicious activities:

```php
$advancedSecurity = new AdvancedSecurity($pdo);

// Check for suspicious activities
$alerts = $advancedSecurity->checkForSuspiciousActivities();

foreach ($alerts as $alert) {
    echo "Suspicious activity: " . $alert['message'] . "\n";
}
```

### IP Blocking

To block IP addresses:

```php
$advancedSecurity = new AdvancedSecurity($pdo);

// Block IP address for 60 minutes
$advancedSecurity->blockIpAddress($_SERVER['REMOTE_ADDR'], 60);

// Check if IP is blocked
if ($advancedSecurity->isIpAddressBlocked($_SERVER['REMOTE_ADDR'])) {
    die('Access denied - IP blocked');
}

// Clean up expired blocks
$advancedSecurity->cleanupExpiredBlocks();
```

## Admin Interface

The admin interface at `/admin/security.php` provides:

- API token management
- Role and permission overview
- Security logs viewing
- Suspicious activity checking

## Security Audit

Run the security audit script to check for vulnerabilities:

```bash
cd security
php security-audit.php
```

Or use the batch script on Windows:

```cmd
security\run-security-audit.bat
```

The audit checks for:

- Weak password policies
- Excessive permissions
- Expired API tokens
- Suspicious activities
- Unencrypted sensitive data
- Database maintenance issues

## API Reference

### AdvancedSecurity Methods

- `hasPermission($userId, $permission)` - Check if user has permission
- `requirePermission($permission)` - Require specific permission
- `encryptData($data)` - Encrypt sensitive data
- `decryptData($encryptedData)` - Decrypt sensitive data
- `hashData($data)` - Hash data
- `verifyHash($data, $hash)` - Verify hashed data
- `generateToken($length)` - Generate secure token
- `validateApiToken($token)` - Validate API token
- `createApiToken($userId, $expiresInDays)` - Create API token
- `revokeApiToken($token)` - Revoke API token
- `checkRateLimit($identifier, $maxRequests, $timeWindow)` - Check rate limit
- `logSecurityEvent($eventType, $details, $severity)` - Log security event
- `getSecurityLogs($limit, $severity)` - Get security logs
- `checkForSuspiciousActivities()` - Check for suspicious activities
- `blockIpAddress($ipAddress, $durationMinutes)` - Block IP address
- `isIpAddressBlocked($ipAddress)` - Check if IP is blocked
- `cleanupExpiredBlocks()` - Clean up expired blocks
- `getUserAuditTrail($userId, $limit)` - Get user audit trail
- `rotateEncryptionKey($newKey)` - Rotate encryption key

## Customization

### Adding New Roles

To add new roles:

1. Insert the role into the `user_roles` table
2. Assign appropriate permissions in the `role_permissions` table

### Adding New Permissions

To add new permissions:

1. Insert the permission into the `permissions` table
2. Assign the permission to appropriate roles

### Custom Security Checks

To add custom security checks:

1. Add methods to the `AdvancedSecurity` class
2. Call them from your application code
3. Log events using the `logSecurityEvent` method

## Best Practices

### Password Security

1. Always hash passwords using `hashData()`
2. Enforce strong password policies
3. Implement password expiration
4. Use multi-factor authentication

### Data Encryption

1. Encrypt all sensitive data at rest
2. Use different encryption keys for different data types
3. Rotate encryption keys regularly
4. Securely store encryption keys

### API Security

1. Use HTTPS for all API endpoints
2. Implement rate limiting
3. Validate all input data
4. Use short-lived tokens
5. Implement token revocation

### Session Management

1. Set appropriate session timeouts
2. Regenerate session IDs after login
3. Destroy sessions on logout
4. Implement concurrent session limits

### Monitoring

1. Log all security-relevant events
2. Monitor for suspicious activities
3. Regularly review security logs
4. Set up alerts for critical events

## Troubleshooting

### Common Issues

1. **Permission denied errors**
   - Check that the user has the correct role
   - Verify that the role has the required permission
   - Check the role-permission mapping

2. **Encryption/decryption failures**
   - Verify that the encryption key is correctly set
   - Check that encrypted data hasn't been corrupted
   - Ensure consistent use of encryption methods

3. **Rate limiting issues**
   - Check rate limit configuration
   - Verify that identifiers are unique
   - Monitor rate limit table size

4. **API token validation failures**
   - Check that tokens haven't expired
   - Verify token format
   - Confirm user account status

### Debugging

Enable debug logging by checking the security logs or by adding custom logging to specific methods.

## Compliance

The system helps with compliance requirements by:

- Providing audit trails
- Implementing access controls
- Protecting sensitive data
- Detecting security incidents
- Supporting data retention policies

Regular security audits and updates are recommended to maintain compliance.