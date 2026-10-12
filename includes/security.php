<?php
/**
 * Security Helper Class for VentDepot
 * Handles CSRF protection, input validation, sanitization, and other security features
 */
class Security {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF token input field HTML
     */
    public static function getCSRFInput() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Post-login redirect target, or $default when it could leave the site.
     * Accepts relative or same-host paths to a .php page with an optional query
     * ("cart.php", "/finalJulio/product.php?id=5"). Rejects schemes, hosts,
     * protocol-relative "//evil.com", backslashes and "..".
     */
    public static function safeRedirect($target, $default = 'index.php') {
        $t = html_entity_decode(trim((string) $target), ENT_QUOTES, 'UTF-8');
        if ($t === '' || strpos($t, '//') === 0 || preg_match('/[\x00-\x1F\\\\]/', $t)) {
            return $default;
        }
        $parts = parse_url($t);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return $default;
        }
        $path = $parts['path'] ?? '';
        if (strpos($path, '..') !== false || !preg_match('#^[A-Za-z0-9/._-]+\.php$#', $path)) {
            return $default;
        }
        return $t;
    }

    /**
     * Comprehensive input validation
     */
    public static function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Required field check
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = ucfirst($field) . ' is required.';
                continue;
            }
            
            // Skip validation if field is empty and not required
            if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
                continue;
            }
            
            // Type validation
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = 'Please enter a valid email address.';
                        }
                        break;
                        
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[$field] = ucfirst($field) . ' must be a number.';
                        }
                        break;
                        
                    case 'integer':
                        if (!filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[$field] = ucfirst($field) . ' must be an integer.';
                        }
                        break;
                        
                    case 'float':
                        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                            $errors[$field] = ucfirst($field) . ' must be a decimal number.';
                        }
                        break;
                        
                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[$field] = 'Please enter a valid URL.';
                        }
                        break;
                        
                    case 'phone':
                        if (!preg_match('/^[\+]?[1-9][\d]{0,15}$/', $value)) {
                            $errors[$field] = 'Please enter a valid phone number.';
                        }
                        break;
                        
                    case 'alphanumeric':
                        if (!ctype_alnum($value)) {
                            $errors[$field] = ucfirst($field) . ' must contain only letters and numbers.';
                        }
                        break;
                }
            }
            
            // Length validation
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = ucfirst($field) . ' must be at least ' . $rule['min_length'] . ' characters long.';
            }
            
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = ucfirst($field) . ' must not exceed ' . $rule['max_length'] . ' characters.';
            }
            
            // Value range validation
            if (isset($rule['min_value']) && $value < $rule['min_value']) {
                $errors[$field] = ucfirst($field) . ' must be at least ' . $rule['min_value'] . '.';
            }
            
            if (isset($rule['max_value']) && $value > $rule['max_value']) {
                $errors[$field] = ucfirst($field) . ' must not exceed ' . $rule['max_value'] . '.';
            }
            
            // Custom pattern validation
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $errors[$field] = isset($rule['pattern_message']) 
                    ? $rule['pattern_message'] 
                    : ucfirst($field) . ' format is invalid.';
            }
            
            // Array validation
            if (isset($rule['in_array']) && !in_array($value, $rule['in_array'])) {
                $errors[$field] = 'Please select a valid ' . $field . '.';
            }
        }
        
        return $errors;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data, $type = 'string') {
        switch ($type) {
            case 'string':
                return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
                
            case 'email':
                return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
                
            case 'url':
                return filter_var(trim($data), FILTER_SANITIZE_URL);
                
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'html':
                // Allow safe HTML tags
                return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
                
            case 'sql':
                // For SQL queries - use prepared statements instead
                return trim($data);
                
            default:
                return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
        }
    }
    
    /**
     * Sanitize array of data
     */
    public static function sanitizeArray($data, $types = []) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $type = $types[$key] ?? 'string';
            
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value, $types);
            } else {
                $sanitized[$key] = self::sanitizeInput($value, $type);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Check if user has permission
     */
    public static function hasPermission($requiredRole) {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }
        
        $roleHierarchy = [
            'customer' => 1,
            'merchant' => 2,
            'admin' => 3
        ];
        
        $userRole = $_SESSION['user_role'];
        
        return isset($roleHierarchy[$userRole]) && 
               isset($roleHierarchy[$requiredRole]) && 
               $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 3600) {
        $key = $action . '_' . self::getClientIP();
        
        if (!isset($_SESSION['rate_limits'])) {
            $_SESSION['rate_limits'] = [];
        }
        
        $now = time();
        
        // Clean old entries
        foreach ($_SESSION['rate_limits'] as $limitKey => $data) {
            if ($data['reset_time'] < $now) {
                unset($_SESSION['rate_limits'][$limitKey]);
            }
        }
        
        if (!isset($_SESSION['rate_limits'][$key])) {
            $_SESSION['rate_limits'][$key] = [
                'attempts' => 1,
                'reset_time' => $now + $timeWindow
            ];
            return true;
        }
        
        if ($_SESSION['rate_limits'][$key]['attempts'] >= $maxAttempts) {
            return false;
        }
        
        $_SESSION['rate_limits'][$key]['attempts']++;
        return true;
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Log security events
     */
    public static function logSecurityEvent($event, $details = [], $severity = 'info') {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => self::getClientIP(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'event' => $event,
            'details' => $details,
            'severity' => $severity,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        // Anchored to the project (a cwd-relative '../logs' wrote outside the
        // app from root pages). Logging must never break the request.
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents($logDir . '/security_' . date('Y-m-d') . '.log', json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);

        // Queryable copy for the profile "recent security activity" panel.
        global $pdo;
        if ($pdo instanceof PDO) {
            try {
                $pdo->prepare("
                    INSERT INTO security_events (user_id, event_type, severity, ip_address, user_agent, details)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([
                    $details['user_id'] ?? $logEntry['user_id'],
                    substr((string) $event, 0, 64),
                    in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'info',
                    $logEntry['ip'],
                    substr((string) $logEntry['user_agent'], 0, 500),
                    json_encode($details),
                ]);
            } catch (\Throwable $e) {
                // table missing (migration 050 not run yet) — file log above still has it
            }
        }
    }
    
    /**
     * Generate secure password hash
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random token
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) { // 5MB default
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'File is too large.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'File upload was interrupted.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = 'No file was uploaded.';
                    break;
                default:
                    $errors[] = 'File upload error.';
            }
            return $errors;
        }
        
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size.';
        }
        
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                $errors[] = 'File type not allowed.';
            }
        }
        
        return $errors;
    }
}

// Procedural CSRF helpers used by several pages (cms-*, two-factor-auth,
// product reviews, dispute center…). They were called but never defined, so
// every POST on those pages died with "Call to undefined function".
if (!function_exists('generateCSRFInput')) {
    function generateCSRFInput() {
        return Security::getCSRFInput();
    }
}

if (!function_exists('requireCSRF')) {
    /** Abort the request (403) unless the POSTed csrf_token matches the session. */
    function requireCSRF() {
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            Security::logSecurityEvent('csrf_token_mismatch', ['page' => $_SERVER['SCRIPT_NAME'] ?? '']);
            http_response_code(403);   // not 419: Apache rewrites unknown codes to 500
            exit('CSRF token mismatch. Please refresh the page and try again.');
        }
    }
}
?>