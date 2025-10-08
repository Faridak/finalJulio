<?php
/**
 * Security Audit Script
 * Checks for common security vulnerabilities and misconfigurations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/AdvancedSecurity.php';

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

echo "Starting security audit...\n";

try {
    $advancedSecurity = new AdvancedSecurity($pdo);
    
    $issues = [];
    
    // Check 1: Weak password policies
    echo "Checking for weak password policies...\n";
    $stmt = $pdo->query("
        SELECT id, username, email
        FROM users 
        WHERE password LIKE '%\$1\$%' 
        OR password LIKE '%\$2\$%' 
        OR password LIKE '%\$5\$%' 
        OR password LIKE '%\$6\$%'
        LIMIT 5
    ");
    $weakPasswordUsers = $stmt->fetchAll();
    
    if (!empty($weakPasswordUsers)) {
        $issues[] = [
            'type' => 'weak_passwords',
            'severity' => 'high',
            'description' => 'Users with potentially weak password hashes found',
            'details' => count($weakPasswordUsers) . ' users with weak hashes'
        ];
    }
    
    // Check 2: Excessive permissions
    echo "Checking for excessive permissions...\n";
    $stmt = $pdo->query("
        SELECT ur.role_name, COUNT(rp.permission_id) as permission_count
        FROM user_roles ur
        JOIN role_permissions rp ON ur.id = rp.role_id
        GROUP BY ur.id, ur.role_name
        HAVING COUNT(rp.permission_id) > 10
    ");
    $excessivePermissions = $stmt->fetchAll();
    
    if (!empty($excessivePermissions)) {
        foreach ($excessivePermissions as $role) {
            $issues[] = [
                'type' => 'excessive_permissions',
                'severity' => 'medium',
                'description' => "Role '{$role['role_name']}' has excessive permissions",
                'details' => $role['permission_count'] . ' permissions assigned'
            ];
        }
    }
    
    // Check 3: Expired API tokens still in database
    echo "Checking for expired API tokens...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as expired_count
        FROM api_tokens 
        WHERE expires_at < NOW()
    ");
    $expiredTokens = $stmt->fetch();
    
    if ($expiredTokens['expired_count'] > 0) {
        $issues[] = [
            'type' => 'expired_tokens',
            'severity' => 'medium',
            'description' => 'Expired API tokens found in database',
            'details' => $expiredTokens['expired_count'] . ' expired tokens'
        ];
    }
    
    // Check 4: Suspicious activities
    echo "Checking for suspicious activities...\n";
    $suspiciousActivities = $advancedSecurity->checkForSuspiciousActivities();
    
    if (!empty($suspiciousActivities)) {
        foreach ($suspiciousActivities as $activity) {
            $issues[] = [
                'type' => 'suspicious_activity',
                'severity' => $activity['severity'],
                'description' => $activity['message'],
                'details' => 'Suspicious activity detected'
            ];
        }
    }
    
    // Check 5: Blocked IPs that should be unblocked
    echo "Checking for expired IP blocks...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as expired_blocks
        FROM blocked_ips 
        WHERE blocked_until < NOW()
    ");
    $expiredBlocks = $stmt->fetch();
    
    if ($expiredBlocks['expired_blocks'] > 0) {
        $issues[] = [
            'type' => 'expired_blocks',
            'severity' => 'low',
            'description' => 'Expired IP blocks found in database',
            'details' => $expiredBlocks['expired_blocks'] . ' expired blocks'
        ];
    }
    
    // Check 6: Unencrypted sensitive data
    echo "Checking for unencrypted sensitive data...\n";
    // This is a simplified check - in reality, you'd need to check specific fields
    $stmt = $pdo->query("
        SELECT table_name, column_name
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND (column_name LIKE '%password%' 
             OR column_name LIKE '%secret%' 
             OR column_name LIKE '%key%' 
             OR column_name LIKE '%token%')
        AND table_name NOT IN ('security_logs', 'api_tokens')
        LIMIT 10
    ");
    $sensitiveColumns = $stmt->fetchAll();
    
    if (!empty($sensitiveColumns)) {
        $issues[] = [
            'type' => 'unencrypted_data',
            'severity' => 'high',
            'description' => 'Potential unencrypted sensitive data found',
            'details' => count($sensitiveColumns) . ' columns with sensitive data names'
        ];
    }
    
    // Check 7: Rate limit entries cleanup
    echo "Checking rate limit table size...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as rate_limit_count
        FROM rate_limits
    ");
    $rateLimitCount = $stmt->fetch();
    
    if ($rateLimitCount['rate_limit_count'] > 10000) {
        $issues[] = [
            'type' => 'rate_limit_table_size',
            'severity' => 'medium',
            'description' => 'Rate limit table is large and may need cleanup',
            'details' => $rateLimitCount['rate_limit_count'] . ' entries in rate_limits table'
        ];
    }
    
    // Report findings
    echo "\n=== SECURITY AUDIT RESULTS ===\n";
    echo "Total issues found: " . count($issues) . "\n\n";
    
    $severityCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
    
    foreach ($issues as $issue) {
        $severity = $issue['severity'];
        $severityCounts[$severity]++;
        
        echo strtoupper($severity) . ": " . $issue['description'] . "\n";
        echo "  Details: " . $issue['details'] . "\n";
        echo "  Type: " . $issue['type'] . "\n\n";
    }
    
    echo "Severity breakdown:\n";
    foreach ($severityCounts as $severity => $count) {
        echo "  " . strtoupper($severity) . ": " . $count . "\n";
    }
    
    // Recommendations
    echo "\n=== RECOMMENDATIONS ===\n";
    
    if ($severityCounts['high'] > 0 || $severityCounts['critical'] > 0) {
        echo "CRITICAL: Address high and critical severity issues immediately\n";
    }
    
    if ($severityCounts['medium'] > 0) {
        echo "MEDIUM: Plan to address medium severity issues in the next maintenance window\n";
    }
    
    if (!empty($weakPasswordUsers)) {
        echo "- Force password resets for users with weak hashes\n";
    }
    
    if (!empty($excessivePermissions)) {
        echo "- Review and reduce excessive permissions for roles\n";
    }
    
    if ($expiredTokens['expired_count'] > 0) {
        echo "- Clean up expired API tokens from database\n";
    }
    
    if ($expiredBlocks['expired_blocks'] > 0) {
        echo "- Clean up expired IP blocks\n";
    }
    
    if (!empty($sensitiveColumns)) {
        echo "- Review sensitive data storage and implement encryption where needed\n";
    }
    
    if ($rateLimitCount['rate_limit_count'] > 10000) {
        echo "- Implement regular cleanup of rate limit table\n";
    }
    
    echo "\nSecurity audit completed.\n";
    
} catch (Exception $e) {
    echo "Security audit failed: " . $e->getMessage() . "\n";
    error_log("Security audit error: " . $e->getMessage());
}
?>