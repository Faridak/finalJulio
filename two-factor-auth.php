<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/TwoFactorAuth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$twoFA = new TwoFactorAuth($pdo);
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'enable_2fa':
            $secret = $_POST['secret'] ?? '';
            $code = $_POST['verification_code'] ?? '';
            
            if ($secret && $code) {
                // Verify the code before enabling
                if ($twoFA->verifyCode($secret, $code)) {
                    $result = $twoFA->enableTwoFactor($userId, $secret);
                    if ($result['success']) {
                        // Generate backup codes
                        $backupResult = $twoFA->generateBackupCodes($userId);
                        $_SESSION['2fa_enabled'] = true;
                        $_SESSION['backup_codes'] = $backupResult['codes'] ?? [];
                        $_SESSION['success_message'] = 'Two-Factor Authentication enabled successfully!';
                    } else {
                        $_SESSION['error_message'] = $result['error'];
                    }
                } else {
                    $_SESSION['error_message'] = 'Invalid verification code. Please try again.';
                }
            } else {
                $_SESSION['error_message'] = 'Missing required fields.';
            }
            break;
            
        case 'disable_2fa':
            $password = $_POST['password'] ?? '';
            $code = $_POST['verification_code'] ?? '';
            
            // Verify password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Verify 2FA code
                if ($twoFA->verifyUserCode($userId, $code)) {
                    $result = $twoFA->disableTwoFactor($userId);
                    if ($result['success']) {
                        $_SESSION['success_message'] = 'Two-Factor Authentication disabled successfully.';
                    } else {
                        $_SESSION['error_message'] = $result['error'];
                    }
                } else {
                    $_SESSION['error_message'] = 'Invalid verification code.';
                }
            } else {
                $_SESSION['error_message'] = 'Invalid password.';
            }
            break;
            
        case 'regenerate_backup_codes':
            $password = $_POST['password'] ?? '';
            
            // Verify password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $result = $twoFA->generateBackupCodes($userId);
                if ($result['success']) {
                    $_SESSION['backup_codes'] = $result['codes'];
                    $_SESSION['success_message'] = 'New backup codes generated successfully!';
                } else {
                    $_SESSION['error_message'] = $result['error'];
                }
            } else {
                $_SESSION['error_message'] = 'Invalid password.';
            }
            break;
            
        case 'remove_trusted_device':
            $deviceId = (int)($_POST['device_id'] ?? 0);
            if ($deviceId > 0) {
                if ($twoFA->removeTrustedDevice($userId, $deviceId)) {
                    $_SESSION['success_message'] = 'Trusted device removed successfully.';
                } else {
                    $_SESSION['error_message'] = 'Failed to remove trusted device.';
                }
            }
            break;
    }
    
    // Redirect to avoid form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$is2FAEnabled = $twoFA->isEnabled($userId);
$trustedDevices = $twoFA->getTrustedDevices($userId);
$remainingBackupCodes = $twoFA->getRemainingBackupCodes($userId);

// Generate new secret for setup
$newSecret = $twoFA->generateSecret();
$qrCodeUrl = $twoFA->getQRCodeUrl($userEmail, $newSecret);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication Settings - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Header -->
            <div class="bg-blue-600 text-white p-6">
                <h1 class="text-2xl font-bold flex items-center">
                    <i class="fas fa-shield-alt mr-3"></i>
                    Two-Factor Authentication
                </h1>
                <p class="mt-2 opacity-90">Enhance your account security with two-factor authentication</p>
            </div>

            <!-- Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 m-6 rounded">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <?php unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 m-6 rounded">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <div class="p-6">
                <?php if (!$is2FAEnabled): ?>
                    <!-- Enable 2FA Section -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-plus-circle text-green-600 mr-2"></i>
                            Enable Two-Factor Authentication
                        </h2>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <p class="text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                Two-factor authentication adds an extra layer of security to your account by requiring both your password and a verification code from your phone.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Setup Instructions -->
                            <div>
                                <h3 class="font-semibold mb-4">Setup Instructions:</h3>
                                <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                    <li>Install an authenticator app on your phone (Google Authenticator, Authy, etc.)</li>
                                    <li>Scan the QR code or enter the secret key manually</li>
                                    <li>Enter the 6-digit code from your authenticator app</li>
                                    <li>Click "Enable 2FA" to complete setup</li>
                                </ol>

                                <div class="mt-6 p-4 bg-gray-50 rounded">
                                    <p class="text-sm font-medium mb-2">Manual Entry Key:</p>
                                    <code class="bg-white px-2 py-1 rounded border text-sm font-mono"><?= $newSecret ?></code>
                                </div>
                            </div>

                            <!-- QR Code -->
                            <div class="text-center">
                                <h3 class="font-semibold mb-4">Scan QR Code:</h3>
                                <div class="inline-block p-4 bg-white border rounded-lg">
                                    <img src="<?= $qrCodeUrl ?>" alt="2FA QR Code" class="w-48 h-48">
                                </div>
                            </div>
                        </div>

                        <!-- Verification Form -->
                        <form method="POST" class="mt-8">
                            <?= generateCSRFInput() ?>
                            <input type="hidden" name="action" value="enable_2fa">
                            <input type="hidden" name="secret" value="<?= $newSecret ?>">
                            
                            <div class="max-w-sm">
                                <label for="verification_code" class="block text-sm font-medium text-gray-700 mb-2">
                                    Enter verification code from your authenticator app:
                                </label>
                                <input type="text" 
                                       id="verification_code" 
                                       name="verification_code" 
                                       required 
                                       maxlength="6" 
                                       pattern="[0-9]{6}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="000000">
                            </div>
                            
                            <button type="submit" 
                                    class="mt-4 bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                <i class="fas fa-shield-alt mr-2"></i>
                                Enable Two-Factor Authentication
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                    <!-- 2FA Enabled Section -->
                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-semibold flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                Two-Factor Authentication Enabled
                            </h2>
                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                Active
                            </span>
                        </div>

                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                            <p class="text-green-800">
                                <i class="fas fa-shield-alt mr-2"></i>
                                Your account is protected with two-factor authentication. You'll need to enter a code from your authenticator app when signing in.
                            </p>
                        </div>

                        <!-- Backup Codes Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4">Backup Codes</h3>
                            <div class="flex items-center justify-between mb-4">
                                <p class="text-gray-600">
                                    You have <strong><?= $remainingBackupCodes ?></strong> unused backup codes remaining.
                                </p>
                                <button onclick="showRegenerateBackupCodesModal()" 
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                    <i class="fas fa-sync-alt mr-2"></i>
                                    Regenerate Codes
                                </button>
                            </div>
                            
                            <?php if (isset($_SESSION['backup_codes']) && !empty($_SESSION['backup_codes'])): ?>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <p class="text-yellow-800 font-medium mb-3">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        Save these backup codes in a safe place. Each code can only be used once.
                                    </p>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 font-mono text-sm">
                                        <?php foreach ($_SESSION['backup_codes'] as $code): ?>
                                            <div class="bg-white px-3 py-2 border rounded"><?= $code ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button onclick="printBackupCodes()" 
                                            class="mt-3 text-blue-600 hover:text-blue-800 text-sm">
                                        <i class="fas fa-print mr-1"></i>
                                        Print Codes
                                    </button>
                                </div>
                                <?php unset($_SESSION['backup_codes']); ?>
                            <?php endif; ?>
                        </div>

                        <!-- Trusted Devices -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4">Trusted Devices</h3>
                            <?php if (!empty($trustedDevices)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($trustedDevices as $device): ?>
                                        <div class="flex items-center justify-between p-4 border rounded-lg">
                                            <div>
                                                <div class="font-medium"><?= htmlspecialchars($device['device_name']) ?></div>
                                                <div class="text-sm text-gray-600">
                                                    IP: <?= htmlspecialchars($device['ip_address']) ?> | 
                                                    Added: <?= date('M j, Y', strtotime($device['created_at'])) ?>
                                                    <?php if ($device['last_used']): ?>
                                                        | Last used: <?= date('M j, Y', strtotime($device['last_used'])) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <form method="POST" class="inline">
                                                <?= generateCSRFInput() ?>
                                                <input type="hidden" name="action" value="remove_trusted_device">
                                                <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                                                <button type="submit" 
                                                        onclick="return confirm('Remove this trusted device?')"
                                                        class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-600">No trusted devices configured.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Disable 2FA -->
                        <div class="border-t pt-6">
                            <h3 class="text-lg font-semibold mb-4 text-red-600">Disable Two-Factor Authentication</h3>
                            <p class="text-gray-600 mb-4">
                                Disabling 2FA will make your account less secure. Make sure you understand the risks.
                            </p>
                            <button onclick="showDisable2FAModal()" 
                                    class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                                <i class="fas fa-times-circle mr-2"></i>
                                Disable 2FA
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Disable 2FA Modal -->
    <div id="disable2FAModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold mb-4">Disable Two-Factor Authentication</h3>
            <form method="POST">
                <?= generateCSRFInput() ?>
                <input type="hidden" name="action" value="disable_2fa">
                
                <div class="mb-4">
                    <label for="disable_password" class="block text-sm font-medium text-gray-700 mb-2">
                        Enter your password:
                    </label>
                    <input type="password" 
                           id="disable_password" 
                           name="password" 
                           required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label for="disable_verification_code" class="block text-sm font-medium text-gray-700 mb-2">
                        Enter 2FA code:
                    </label>
                    <input type="text" 
                           id="disable_verification_code" 
                           name="verification_code" 
                           required 
                           maxlength="6" 
                           pattern="[0-9]{6}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="000000">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" 
                            onclick="hideDisable2FAModal()" 
                            class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                        Disable 2FA
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Regenerate Backup Codes Modal -->
    <div id="regenerateBackupCodesModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold mb-4">Regenerate Backup Codes</h3>
            <p class="text-gray-600 mb-4">
                This will invalidate all existing backup codes and generate new ones.
            </p>
            <form method="POST">
                <?= generateCSRFInput() ?>
                <input type="hidden" name="action" value="regenerate_backup_codes">
                
                <div class="mb-4">
                    <label for="regenerate_password" class="block text-sm font-medium text-gray-700 mb-2">
                        Enter your password to confirm:
                    </label>
                    <input type="password" 
                           id="regenerate_password" 
                           name="password" 
                           required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" 
                            onclick="hideRegenerateBackupCodesModal()" 
                            class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Regenerate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showDisable2FAModal() {
            document.getElementById('disable2FAModal').classList.remove('hidden');
        }

        function hideDisable2FAModal() {
            document.getElementById('disable2FAModal').classList.add('hidden');
        }

        function showRegenerateBackupCodesModal() {
            document.getElementById('regenerateBackupCodesModal').classList.remove('hidden');
        }

        function hideRegenerateBackupCodesModal() {
            document.getElementById('regenerateBackupCodesModal').classList.add('hidden');
        }

        function printBackupCodes() {
            window.print();
        }

        // Auto-format verification code input
        document.addEventListener('DOMContentLoaded', function() {
            const codeInputs = document.querySelectorAll('input[name="verification_code"]');
            codeInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    // Remove any non-digit characters
                    this.value = this.value.replace(/\D/g, '');
                    
                    // Limit to 6 digits
                    if (this.value.length > 6) {
                        this.value = this.value.slice(0, 6);
                    }
                });
            });
        });
    </script>
</body>
</html>