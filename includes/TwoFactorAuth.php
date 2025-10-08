<?php
/**
 * Two-Factor Authentication System for VentDepot
 * Implements TOTP (Time-based One-Time Password) authentication
 */

require_once 'security.php';

class TwoFactorAuth {
    private $pdo;
    private $issuer = 'VentDepot';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate a secret key for TOTP
     */
    public function generateSecret() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }
    
    /**
     * Generate QR code URL for authenticator apps
     */
    public function getQRCodeUrl($email, $secret) {
        $label = urlencode($this->issuer . ':' . $email);
        $issuer = urlencode($this->issuer);
        $url = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}";
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($url);
    }
    
    /**
     * Verify TOTP code
     */
    public function verifyCode($secret, $code, $window = 1) {
        $timestamp = time();
        
        // Check current window and adjacent windows for clock drift
        for ($i = -$window; $i <= $window; $i++) {
            $timeSlice = intval(($timestamp + ($i * 30)) / 30);
            $calculatedCode = $this->generateTOTP($secret, $timeSlice);
            
            if (hash_equals($calculatedCode, str_pad($code, 6, '0', STR_PAD_LEFT))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate TOTP code
     */
    private function generateTOTP($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = intval(time() / 30);
        }
        
        $secretKey = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Base32 decode
     */
    private function base32Decode($secret) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $decoded = '';
        
        for ($i = 0; $i < strlen($secret); $i += 8) {
            $chunk = substr($secret, $i, 8);
            $chunk = str_pad($chunk, 8, '=');
            
            $binaryString = '';
            for ($j = 0; $j < 8; $j++) {
                if ($chunk[$j] !== '=') {
                    $binaryString .= str_pad(decbin(strpos($chars, $chunk[$j])), 5, '0', STR_PAD_LEFT);
                }
            }
            
            $chunks = str_split($binaryString, 8);
            foreach ($chunks as $binChunk) {
                if (strlen($binChunk) === 8) {
                    $decoded .= chr(bindec($binChunk));
                }
            }
        }
        
        return $decoded;
    }
    
    /**
     * Enable 2FA for user
     */
    public function enableTwoFactor($userId, $secret) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_2fa (user_id, secret_key, is_enabled, created_at) 
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE 
                    secret_key = VALUES(secret_key),
                    is_enabled = 1,
                    updated_at = NOW()
            ");
            $stmt->execute([$userId, $secret]);
            
            // Log security event
            Security::logSecurityEvent('2fa_enabled', ['user_id' => $userId], 'info');
            
            return ['success' => true, 'message' => '2FA enabled successfully'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to enable 2FA'];
        }
    }
    
    /**
     * Disable 2FA for user
     */
    public function disableTwoFactor($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_2fa SET is_enabled = 0, updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            // Log security event
            Security::logSecurityEvent('2fa_disabled', ['user_id' => $userId], 'warning');
            
            return ['success' => true, 'message' => '2FA disabled successfully'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to disable 2FA'];
        }
    }
    
    /**
     * Check if user has 2FA enabled
     */
    public function isEnabled($userId) {
        $stmt = $this->pdo->prepare("
            SELECT is_enabled FROM user_2fa WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result ? (bool)$result['is_enabled'] : false;
    }
    
    /**
     * Get user's 2FA secret
     */
    public function getUserSecret($userId) {
        $stmt = $this->pdo->prepare("
            SELECT secret_key FROM user_2fa WHERE user_id = ? AND is_enabled = 1
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result ? $result['secret_key'] : null;
    }
    
    /**
     * Verify user's 2FA code
     */
    public function verifyUserCode($userId, $code) {
        $secret = $this->getUserSecret($userId);
        
        if (!$secret) {
            return false;
        }
        
        $isValid = $this->verifyCode($secret, $code);
        
        // Log attempt
        Security::logSecurityEvent('2fa_verification', [
            'user_id' => $userId,
            'success' => $isValid
        ], $isValid ? 'info' : 'warning');
        
        return $isValid;
    }
    
    /**
     * Generate backup codes
     */
    public function generateBackupCodes($userId, $count = 8) {
        $codes = [];
        
        try {
            $this->pdo->beginTransaction();
            
            // Delete existing backup codes
            $stmt = $this->pdo->prepare("DELETE FROM user_2fa_backup_codes WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Generate new codes
            for ($i = 0; $i < $count; $i++) {
                $code = $this->generateBackupCode();
                $codes[] = $code;
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_2fa_backup_codes (user_id, code_hash, created_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$userId, password_hash($code, PASSWORD_DEFAULT)]);
            }
            
            $this->pdo->commit();
            
            // Log security event
            Security::logSecurityEvent('2fa_backup_codes_generated', ['user_id' => $userId], 'info');
            
            return ['success' => true, 'codes' => $codes];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => 'Failed to generate backup codes'];
        }
    }
    
    /**
     * Generate a single backup code
     */
    private function generateBackupCode() {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, 35)];
        }
        return $code;
    }
    
    /**
     * Verify backup code
     */
    public function verifyBackupCode($userId, $code) {
        $stmt = $this->pdo->prepare("
            SELECT id, code_hash FROM user_2fa_backup_codes 
            WHERE user_id = ? AND is_used = 0
        ");
        $stmt->execute([$userId]);
        $backupCodes = $stmt->fetchAll();
        
        foreach ($backupCodes as $backupCode) {
            if (password_verify($code, $backupCode['code_hash'])) {
                // Mark code as used
                $stmt = $this->pdo->prepare("
                    UPDATE user_2fa_backup_codes 
                    SET is_used = 1, used_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$backupCode['id']]);
                
                // Log usage
                Security::logSecurityEvent('2fa_backup_code_used', [
                    'user_id' => $userId,
                    'backup_code_id' => $backupCode['id']
                ], 'warning');
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get remaining backup codes count
     */
    public function getRemainingBackupCodes($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM user_2fa_backup_codes 
            WHERE user_id = ? AND is_used = 0
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Check if 2FA is required for specific actions
     */
    public function isRequiredForAction($action, $userRole = 'customer') {
        $requiredActions = [
            'admin' => ['password_change', 'account_settings', 'sensitive_data_access'],
            'merchant' => ['password_change', 'payout_settings', 'bank_account_change'],
            'customer' => ['password_change', 'payment_method_add']
        ];
        
        return isset($requiredActions[$userRole]) && 
               in_array($action, $requiredActions[$userRole]);
    }
    
    /**
     * Create trusted device
     */
    public function createTrustedDevice($userId, $deviceName, $userAgent, $ipAddress) {
        $deviceFingerprint = hash('sha256', $userAgent . $ipAddress . $userId);
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_trusted_devices 
                (user_id, device_name, device_fingerprint, user_agent, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $deviceName, $deviceFingerprint, $userAgent, $ipAddress]);
            
            return ['success' => true, 'device_id' => $this->pdo->lastInsertId()];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to create trusted device'];
        }
    }
    
    /**
     * Check if device is trusted
     */
    public function isTrustedDevice($userId, $userAgent, $ipAddress) {
        $deviceFingerprint = hash('sha256', $userAgent . $ipAddress . $userId);
        
        $stmt = $this->pdo->prepare("
            SELECT id FROM user_trusted_devices 
            WHERE user_id = ? AND device_fingerprint = ? AND is_active = 1
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$userId, $deviceFingerprint]);
        
        return $stmt->fetch() !== false;
    }
    
    /**
     * Remove trusted device
     */
    public function removeTrustedDevice($userId, $deviceId) {
        $stmt = $this->pdo->prepare("
            UPDATE user_trusted_devices 
            SET is_active = 0, updated_at = NOW() 
            WHERE user_id = ? AND id = ?
        ");
        return $stmt->execute([$userId, $deviceId]);
    }
    
    /**
     * Get user's trusted devices
     */
    public function getTrustedDevices($userId) {
        $stmt = $this->pdo->prepare("
            SELECT id, device_name, ip_address, created_at, last_used
            FROM user_trusted_devices 
            WHERE user_id = ? AND is_active = 1
            ORDER BY last_used DESC, created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
?>