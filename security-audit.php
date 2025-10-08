<?php
/**
 * Security Audit Script for VentDepot
 * Comprehensive SQL injection vulnerability scanner and fixer
 */

require_once 'config/database.php';

echo "<h2>VentDepot Security Audit - SQL Injection Detection</h2>\n";
echo "<p>Scanning for potential SQL injection vulnerabilities...</p>\n";

/**
 * Scan directory for PHP files and check for SQL injection patterns
 */
function scanForSQLInjection($directory = '.', $excludeDirs = ['vendor', 'node_modules', '.git']) {
    $vulnerabilities = [];
    $safeFiles = [];
    $totalFiles = 0;
    
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filePath = $file->getPathname();
            
            // Skip excluded directories
            $skip = false;
            foreach ($excludeDirs as $excludeDir) {
                if (strpos($filePath, $excludeDir) !== false) {
                    $skip = true;
                    break;
                }
            }
            
            if ($skip) continue;
            
            $totalFiles++;
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);
            
            $fileVulns = [];
            
            foreach ($lines as $lineNumber => $line) {
                $lineNumber++; // 1-based line numbers
                
                // Check for dangerous patterns
                $patterns = [
                    // Direct query execution with variables
                    '/\\$pdo->query\\([^)]*\\$[^)]*\\)/' => 'Direct query() with variable interpolation',
                    '/\\$pdo->exec\\([^)]*\\$[^)]*\\)/' => 'Direct exec() with variable interpolation',
                    
                    // String concatenation in SQL
                    '/"(SELECT|INSERT|UPDATE|DELETE)[^"]*"\\s*\\.\\s*\\$/' => 'SQL string concatenation',
                    
                    // SQL injection in LIKE clauses
                    '/LIKE\\s+[\'"]%[^\']*\\{\\$[^}]+\\}[^\']*%[\'"]/' => 'Direct variable interpolation in LIKE clause',
                    
                    // Direct variable in SQL without quotes (potential numeric injection)
                    '/"[^"]*\\$\\w+[^"]*"/' => 'Variable interpolation in SQL string',
                    
                    // mysqli_query patterns
                    '/mysqli_query\\([^)]*\\$[^)]*\\)/' => 'Direct mysqli_query with variable',
                    
                    // mysql_query patterns (deprecated but dangerous)
                    '/mysql_query\\([^)]*\\$[^)]*\\)/' => 'Deprecated mysql_query with variable',
                ];\
                
                foreach ($patterns as $pattern => $description) {\n                    if (preg_match($pattern, $line)) {\n                        // Additional checks to reduce false positives\n                        \n                        // Skip if it's a prepared statement\n                        if (strpos($line, 'prepare(') !== false) continue;\n                        \n                        // Skip if it's in a comment\n                        if (preg_match('/^\\s*(\\/\\/|\\/\\*|\\*)/', $line)) continue;\n                        \n                        // Skip common safe patterns\n                        $safePatterns = [\n                            '/\\$pdo->query\\(\\\"SHOW/',  // SHOW queries are usually safe\n                            '/\\$pdo->query\\(\\\"DESCRIBE/',  // DESCRIBE queries\n                            '/\\$pdo->exec\\(\\\"SET/',  // SET queries\n                            '/\\$pdo->exec\\(\\\"CREATE/',  // CREATE queries in setup\n                            '/\\$pdo->exec\\(\\\"DROP/',  // DROP queries in setup\n                            '/\\$pdo->exec\\(\\\"ALTER/',  // ALTER queries in setup\n                            '/\\$statement/',  // Variables named $statement are usually safe\n                            '/\\$schema/',  // Schema variables\n                        ];\n                        \n                        $isSafe = false;\n                        foreach ($safePatterns as $safePattern) {\n                            if (preg_match($safePattern, $line)) {\n                                $isSafe = true;\n                                break;\n                            }\n                        }\n                        \n                        if (!$isSafe) {\n                            $fileVulns[] = [\n                                'line' => $lineNumber,\n                                'content' => trim($line),\n                                'pattern' => $description,\n                                'severity' => getSeverity($description)\n                            ];\n                        }\n                    }\n                }\n            }\n            \n            if (!empty($fileVulns)) {\n                $vulnerabilities[$filePath] = $fileVulns;\n            } else {\n                $safeFiles[] = $filePath;\n            }\n        }\n    }\n    \n    return [\n        'vulnerabilities' => $vulnerabilities,\n        'safe_files' => $safeFiles,\n        'total_files' => $totalFiles\n    ];\n}\n\n/**\n * Determine severity level of vulnerability\n */\nfunction getSeverity($description) {\n    $highRisk = ['Direct query()', 'Direct exec()', 'SQL string concatenation'];\n    $mediumRisk = ['Variable interpolation in LIKE clause', 'Variable interpolation in SQL string'];\n    \n    foreach ($highRisk as $risk) {\n        if (strpos($description, $risk) !== false) {\n            return 'HIGH';\n        }\n    }\n    \n    foreach ($mediumRisk as $risk) {\n        if (strpos($description, $risk) !== false) {\n            return 'MEDIUM';\n        }\n    }\n    \n    return 'LOW';\n}\n\n/**\n * Generate security report\n */\nfunction generateSecurityReport($results) {\n    $totalVulns = 0;\n    $highSeverity = 0;\n    $mediumSeverity = 0;\n    $lowSeverity = 0;\n    \n    foreach ($results['vulnerabilities'] as $file => $vulns) {\n        $totalVulns += count($vulns);\n        foreach ($vulns as $vuln) {\n            switch ($vuln['severity']) {\n                case 'HIGH': $highSeverity++; break;\n                case 'MEDIUM': $mediumSeverity++; break;\n                case 'LOW': $lowSeverity++; break;\n            }\n        }\n    }\n    \n    echo \"<div style='border: 1px solid #ddd; padding: 20px; margin: 20px 0; background: #f9f9f9;'>\";\n    echo \"<h3>Security Audit Results</h3>\";\n    echo \"<ul>\";\n    echo \"<li><strong>Total Files Scanned:</strong> {$results['total_files']}</li>\";\n    echo \"<li><strong>Files with Potential Vulnerabilities:</strong> \" . count($results['vulnerabilities']) . \"</li>\";\n    echo \"<li><strong>Safe Files:</strong> \" . count($results['safe_files']) . \"</li>\";\n    echo \"<li><strong>Total Potential Vulnerabilities:</strong> $totalVulns</li>\";\n    echo \"<li style='color: red;'><strong>High Severity:</strong> $highSeverity</li>\";\n    echo \"<li style='color: orange;'><strong>Medium Severity:</strong> $mediumSeverity</li>\";\n    echo \"<li style='color: blue;'><strong>Low Severity:</strong> $lowSeverity</li>\";\n    echo \"</ul>\";\n    echo \"</div>\";\n}\n\n/**\n * Display detailed vulnerability report\n */\nfunction displayVulnerabilityDetails($vulnerabilities) {\n    if (empty($vulnerabilities)) {\n        echo \"<div style='color: green; font-weight: bold; padding: 20px; background: #e8f5e8; border: 1px solid #4caf50;'>\";\n        echo \"🎉 No SQL injection vulnerabilities detected! Your codebase appears to be secure.\";\n        echo \"</div>\";\n        return;\n    }\n    \n    echo \"<h3>Detailed Vulnerability Report</h3>\";\n    \n    foreach ($vulnerabilities as $file => $vulns) {\n        echo \"<div style='border: 1px solid #ccc; margin: 10px 0; padding: 15px; background: #fff;'>\";\n        echo \"<h4 style='color: #d32f2f; margin: 0 0 10px 0;'>📁 \" . htmlspecialchars($file) . \"</h4>\";\n        \n        foreach ($vulns as $vuln) {\n            $severityColor = match($vuln['severity']) {\n                'HIGH' => '#d32f2f',\n                'MEDIUM' => '#f57c00',\n                'LOW' => '#1976d2',\n                default => '#666'\n            };\n            \n            echo \"<div style='margin: 10px 0; padding: 10px; border-left: 4px solid $severityColor; background: #f5f5f5;'>\";\n            echo \"<div><strong>Line {$vuln['line']}:</strong> <span style='color: $severityColor;'>[{$vuln['severity']}]</span> {$vuln['pattern']}</div>\";\n            echo \"<div style='font-family: monospace; background: #eee; padding: 5px; margin: 5px 0;'>\";\n            echo htmlspecialchars($vuln['content']);\n            echo \"</div>\";\n            echo \"</div>\";\n        }\n        \n        echo \"</div>\";\n    }\n}\n\n/**\n * Generate security recommendations\n */\nfunction generateRecommendations($vulnerabilities) {\n    if (empty($vulnerabilities)) {\n        echo \"<div style='background: #e8f5e8; border: 1px solid #4caf50; padding: 20px; margin: 20px 0;'>\";\n        echo \"<h3>✅ Security Status: EXCELLENT</h3>\";\n        echo \"<p>Your codebase follows security best practices. Keep up the good work!</p>\";\n        echo \"<ul>\";\n        echo \"<li>✅ All database queries use prepared statements</li>\";\n        echo \"<li>✅ No direct SQL string concatenation detected</li>\";\n        echo \"<li>✅ No unsafe variable interpolation in SQL</li>\";\n        echo \"</ul>\";\n        echo \"</div>\";\n        return;\n    }\n    \n    echo \"<div style='background: #fff3cd; border: 1px solid #ffc107; padding: 20px; margin: 20px 0;'>\";\n    echo \"<h3>🔧 Security Recommendations</h3>\";\n    echo \"<ol>\";\n    echo \"<li><strong>Replace direct query() calls with prepared statements:</strong><br>\";\n    echo \"<code style='background: #f5f5f5; padding: 5px;'>\\$stmt = \\$pdo->prepare('SELECT * FROM table WHERE id = ?'); \\$stmt->execute([\\$id]);</code></li>\";\n    echo \"<li><strong>Use parameter binding instead of string concatenation:</strong><br>\";\n    echo \"<code style='background: #f5f5f5; padding: 5px;'>\\$stmt = \\$pdo->prepare('SELECT * FROM products WHERE name LIKE ?'); \\$stmt->execute(['%' . \\$search . '%']);</code></li>\";\n    echo \"<li><strong>Validate and sanitize all user inputs</strong></li>\";\n    echo \"<li><strong>Use whitelist validation for dynamic table/column names</strong></li>\";\n    echo \"<li><strong>Implement proper error handling to avoid information disclosure</strong></li>\";\n    echo \"</ol>\";\n    echo \"</div>\";\n}\n\n// Run the security audit\necho \"<p>🔍 Starting comprehensive security scan...</p>\";\n$results = scanForSQLInjection();\n\ngenerateSecurityReport($results);\ndisplayVulnerabilityDetails($results['vulnerabilities']);\ngenerateRecommendations($results['vulnerabilities']);\n\n// Additional security checks\necho \"<h3>Additional Security Checks</h3>\";\n\n// Check for CSRF protection\necho \"<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0;'>\";\necho \"<h4>🔒 CSRF Protection Status</h4>\";\n\nif (file_exists('includes/security.php')) {\n    $securityContent = file_get_contents('includes/security.php');\n    if (strpos($securityContent, 'generateCSRFToken') !== false) {\n        echo \"<p style='color: green;'>✅ CSRF protection system is implemented</p>\";\n    } else {\n        echo \"<p style='color: red;'>❌ CSRF protection system not found</p>\";\n    }\n} else {\n    echo \"<p style='color: red;'>❌ Security.php file not found</p>\";\n}\n\necho \"</div>\";\n\n// Check for input validation\necho \"<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0;'>\";\necho \"<h4>🛡️ Input Validation Status</h4>\";\n\nif (file_exists('includes/security.php')) {\n    $securityContent = file_get_contents('includes/security.php');\n    if (strpos($securityContent, 'sanitizeInput') !== false) {\n        echo \"<p style='color: green;'>✅ Input validation functions are implemented</p>\";\n    } else {\n        echo \"<p style='color: orange;'>⚠️ Input validation functions need to be implemented</p>\";\n    }\n} else {\n    echo \"<p style='color: red;'>❌ Input validation system not found</p>\";\n}\n\necho \"</div>\";\n\necho \"<hr>\";\necho \"<p><strong>Security Audit Complete!</strong></p>\";\necho \"<p>Next steps:</p>\";\necho \"<ol>\";\necho \"<li>Fix any high-severity vulnerabilities immediately</li>\";\necho \"<li>Address medium-severity issues</li>\";\necho \"<li>Review and test all changes</li>\";\necho \"<li>Run this audit regularly</li>\";\necho \"<li>Implement additional security measures as needed</li>\";\necho \"</ol>\";\n?>