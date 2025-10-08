<?php
/**
 * Security Audit Script for finalJulio E-commerce Platform
 * Scans for potential SQL injection vulnerabilities and security issues
 */

echo "<html><head><title>Security Audit - finalJulio</title></head><body>";
echo "<h1>🔒 Security Audit Report</h1>";

require_once 'config/database.php';

function scanForSQLInjection() {
    $patterns = [
        '/\$pdo->query\([^)]*\$[^)]*\)/' => 'Direct PDO query() with variable',
        '/\$pdo->exec\([^)]*\$[^)]*\)/' => 'Direct PDO exec() with variable',
        '/mysqli_query\([^)]*\$[^)]*\)/' => 'Direct mysqli_query with variable',
        '/mysql_query\([^)]*\$[^)]*\)/' => 'Deprecated mysql_query with variable',
    ];
    
    $vulnerabilities = [];
    $safeFiles = [];
    $totalFiles = 0;
    
    $phpFiles = glob('*.php');
    $phpFiles = array_merge($phpFiles, glob('*/*.php'));
    $phpFiles = array_merge($phpFiles, glob('*/*/*.php'));
    
    foreach ($phpFiles as $filePath) {
        $totalFiles++;
        $fileContent = file_get_contents($filePath);
        $lines = explode("\n", $fileContent);
        $fileVulns = [];
        
        foreach ($lines as $lineNumber => $line) {
            foreach ($patterns as $pattern => $description) {
                if (preg_match($pattern, $line)) {
                    // Skip if it's a prepared statement
                    if (strpos($line, 'prepare(') !== false) continue;
                    
                    // Skip if it's in a comment
                    if (preg_match('/^\s*(\/\/|\/\*|\*)/', $line)) continue;
                    
                    // Skip common safe patterns
                    $safePatterns = [
                        '/\$pdo->query\(\"SHOW/',
                        '/\$pdo->query\(\"DESCRIBE/',
                        '/\$pdo->exec\(\"SET/',
                        '/\$pdo->exec\(\"CREATE/',
                        '/\$pdo->exec\(\"DROP/',
                        '/\$pdo->exec\(\"ALTER/',
                    ];
                    
                    $isSafe = false;
                    foreach ($safePatterns as $safePattern) {
                        if (preg_match($safePattern, $line)) {
                            $isSafe = true;
                            break;
                        }
                    }
                    
                    if (!$isSafe) {
                        $fileVulns[] = [
                            'line' => $lineNumber + 1,
                            'content' => trim($line),
                            'pattern' => $description,
                            'severity' => 'MEDIUM'
                        ];
                    }
                }
            }
        }
        
        if (!empty($fileVulns)) {
            $vulnerabilities[$filePath] = $fileVulns;
        } else {
            $safeFiles[] = $filePath;
        }
    }
    
    return [
        'vulnerabilities' => $vulnerabilities,
        'safe_files' => $safeFiles,
        'total_files' => $totalFiles
    ];
}

// Run the security audit
echo "<p>🔍 Starting comprehensive security scan...</p>";
$results = scanForSQLInjection();

$totalVulns = 0;
foreach ($results['vulnerabilities'] as $file => $vulns) {
    $totalVulns += count($vulns);
}

echo "<div style='border: 1px solid #ddd; padding: 20px; margin: 20px 0; background: #f9f9f9;'>";
echo "<h3>Security Audit Results</h3>";
echo "<ul>";
echo "<li><strong>Total Files Scanned:</strong> {$results['total_files']}</li>";
echo "<li><strong>Files with Potential Vulnerabilities:</strong> " . count($results['vulnerabilities']) . "</li>";
echo "<li><strong>Safe Files:</strong> " . count($results['safe_files']) . "</li>";
echo "<li><strong>Total Potential Vulnerabilities:</strong> $totalVulns</li>";
echo "</ul>";
echo "</div>";

if (empty($results['vulnerabilities'])) {
    echo "<div style='color: green; font-weight: bold; padding: 20px; background: #e8f5e8; border: 1px solid #4caf50;'>";
    echo "🎉 No SQL injection vulnerabilities detected! Your codebase appears to be secure.";
    echo "</div>";
} else {
    echo "<h3>Detailed Vulnerability Report</h3>";
    
    foreach ($results['vulnerabilities'] as $file => $vulns) {
        echo "<div style='border: 1px solid #ccc; margin: 10px 0; padding: 15px; background: #fff;'>";
        echo "<h4 style='color: #d32f2f; margin: 0 0 10px 0;'>📁 " . htmlspecialchars($file) . "</h4>";
        
        foreach ($vulns as $vuln) {
            echo "<div style='margin: 10px 0; padding: 10px; border-left: 4px solid #f57c00; background: #f5f5f5;'>";
            echo "<div><strong>Line {$vuln['line']}:</strong> <span style='color: #f57c00;'>[{$vuln['severity']}]</span> {$vuln['pattern']}</div>";
            echo "<div style='font-family: monospace; background: #eee; padding: 5px; margin: 5px 0;'>";
            echo htmlspecialchars($vuln['content']);
            echo "</div>";
            echo "</div>";
        }
        
        echo "</div>";
    }
}

// Additional security checks
echo "<h3>Additional Security Checks</h3>";

// Check for CSRF protection
echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0;'>";
echo "<h4>🔒 CSRF Protection Status</h4>";

if (file_exists('includes/security.php')) {
    $securityContent = file_get_contents('includes/security.php');
    if (strpos($securityContent, 'generateCSRFToken') !== false) {
        echo "<p style='color: green;'>✅ CSRF protection system is implemented</p>";
    } else {
        echo "<p style='color: red;'>❌ CSRF protection system not found</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Security.php file not found</p>";
}

echo "</div>";

// Check for input validation
echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0;'>";
echo "<h4>🛡️ Input Validation Status</h4>";

if (file_exists('includes/security.php')) {
    $securityContent = file_get_contents('includes/security.php');
    if (strpos($securityContent, 'sanitizeInput') !== false) {
        echo "<p style='color: green;'>✅ Input validation functions are implemented</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Input validation functions need to be implemented</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Input validation system not found</p>";
}

echo "</div>";

echo "<hr>";
echo "<p><strong>Security Audit Complete!</strong></p>";
echo "<p>Next steps:</p>";
echo "<ol>";
echo "<li>Fix any high-severity vulnerabilities immediately</li>";
echo "<li>Address medium-severity issues</li>";
echo "<li>Review and test all changes</li>";
echo "<li>Run this audit regularly</li>";
echo "<li>Implement additional security measures as needed</li>";
echo "</ol>";
echo "</body></html>";
?>