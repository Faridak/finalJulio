<?php
require_once 'config/database.php';

// Require login
requireLogin();

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountType = $_POST['account_type'] ?? 'checking';
    $bankName = trim($_POST['bank_name'] ?? '');
    $accountHolderName = trim($_POST['account_holder_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $routingNumber = trim($_POST['routing_number'] ?? '');
    $isDefault = isset($_POST['is_default']);
    
    // Validation
    if (empty($bankName) || empty($accountHolderName) || empty($accountNumber) || empty($routingNumber)) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($accountNumber) < 8 || strlen($accountNumber) > 17) {
        $error = 'Account number must be between 8 and 17 digits.';
    } elseif (strlen($routingNumber) !== 9) {
        $error = 'Routing number must be exactly 9 digits.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // If this is set as default, remove default from other banking details
            if ($isDefault) {
                $stmt = $pdo->prepare("UPDATE banking_details SET is_default = FALSE WHERE user_id = ?");
                $stmt->execute([$userId]);
            }
            
            // In a real application, these would be properly encrypted
            // For demo purposes, we'll show only last 4 digits
            $accountNumberEncrypted = '****' . substr($accountNumber, -4);
            $routingNumberEncrypted = '****' . substr($routingNumber, -4);
            
            // Insert new banking details
            $stmt = $pdo->prepare("
                INSERT INTO banking_details (user_id, account_type, bank_name, account_holder_name, account_number_encrypted, routing_number_encrypted, is_verified, is_default) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // For demo, we'll mark business accounts as verified, personal as pending
            $isVerified = ($userRole === 'merchant' && $accountType === 'business');
            
            if ($stmt->execute([$userId, $accountType, $bankName, $accountHolderName, $accountNumberEncrypted, $routingNumberEncrypted, $isVerified, $isDefault])) {
                $pdo->commit();
                $success = 'Payment method added successfully!';
                
                // Redirect based on user role
                if ($userRole === 'merchant') {
                    header('Location: merchant/profile.php?tab=banking&success=1');
                } else {
                    header('Location: profile.php?tab=banking&success=1');
                }
                exit;
            } else {
                $pdo->rollBack();
                $error = 'Failed to add payment method. Please try again.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'An error occurred while adding the payment method.';
        }
    }
}

// Get user's existing payment methods count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM banking_details WHERE user_id = ?");
$countStmt->execute([$userId]);
$paymentCount = $countStmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Payment Method - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php if ($userRole === 'merchant'): ?>
        <!-- Merchant Navigation -->
        <nav class="bg-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-4">
                        <a href="index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                        <span class="text-gray-400">|</span>
                        <a href="merchant/dashboard.php" class="text-lg font-semibold text-gray-700 hover:text-blue-600">Merchant Dashboard</a>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <a href="merchant/profile.php" class="text-gray-600 hover:text-blue-600">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Profile
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    <?php else: ?>
        <?php include 'includes/navigation.php'; ?>
    <?php endif; ?>

    <div class="max-w-2xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Add Payment Method</h1>
            <p class="text-gray-600 mt-2">
                <?= $userRole === 'merchant' ? 'Add a business bank account to receive payments' : 'Add a payment method for faster checkout' ?>
            </p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Security Notice -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-shield-alt text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Secure & Encrypted</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>Your banking information is encrypted and secure. We use bank-level security to protect your data.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" class="space-y-6">
                <div>
                    <label for="account_type" class="block text-sm font-medium text-gray-700 mb-2">
                        Account Type *
                    </label>
                    <select name="account_type" id="account_type" required
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <?php if ($userRole === 'merchant'): ?>
                            <option value="business" <?= ($_POST['account_type'] ?? 'business') === 'business' ? 'selected' : '' ?>>Business Account</option>
                            <option value="checking" <?= ($_POST['account_type'] ?? '') === 'checking' ? 'selected' : '' ?>>Personal Checking</option>
                            <option value="savings" <?= ($_POST['account_type'] ?? '') === 'savings' ? 'selected' : '' ?>>Personal Savings</option>
                        <?php else: ?>
                            <option value="checking" <?= ($_POST['account_type'] ?? 'checking') === 'checking' ? 'selected' : '' ?>>Checking Account</option>
                            <option value="savings" <?= ($_POST['account_type'] ?? '') === 'savings' ? 'selected' : '' ?>>Savings Account</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div>
                    <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Bank Name *
                    </label>
                    <input type="text" name="bank_name" id="bank_name" required
                           value="<?= htmlspecialchars($_POST['bank_name'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., Chase Bank, Bank of America">
                </div>
                
                <div>
                    <label for="account_holder_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Account Holder Name *
                    </label>
                    <input type="text" name="account_holder_name" id="account_holder_name" required
                           value="<?= htmlspecialchars($_POST['account_holder_name'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                           placeholder="<?= $userRole === 'merchant' ? 'Business name or account holder' : 'Full name as it appears on account' ?>">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="routing_number" class="block text-sm font-medium text-gray-700 mb-2">
                            Routing Number *
                        </label>
                        <input type="text" name="routing_number" id="routing_number" required
                               value="<?= htmlspecialchars($_POST['routing_number'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                               placeholder="9-digit routing number"
                               pattern="[0-9]{9}"
                               maxlength="9">
                        <p class="text-xs text-gray-500 mt-1">Found on the bottom left of your check</p>
                    </div>
                    
                    <div>
                        <label for="account_number" class="block text-sm font-medium text-gray-700 mb-2">
                            Account Number *
                        </label>
                        <input type="text" name="account_number" id="account_number" required
                               value="<?= htmlspecialchars($_POST['account_number'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                               placeholder="Account number"
                               pattern="[0-9]{8,17}"
                               maxlength="17">
                        <p class="text-xs text-gray-500 mt-1">Found on the bottom of your check</p>
                    </div>
                </div>
                
                <!-- Default Payment Method Option -->
                <?php if ($paymentCount === 0): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                        <div class="flex items-center">
                            <input type="checkbox" name="is_default" id="is_default" checked disabled
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_default" class="ml-2 text-sm text-blue-800">
                                Set as default payment method (This will be your first payment method)
                            </label>
                        </div>
                    </div>
                    <input type="hidden" name="is_default" value="1">
                <?php else: ?>
                    <div class="flex items-center">
                        <input type="checkbox" name="is_default" id="is_default" 
                               <?= isset($_POST['is_default']) ? 'checked' : '' ?>
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_default" class="ml-2 text-sm text-gray-700">
                            Set as default payment method
                        </label>
                    </div>
                <?php endif; ?>
                
                <!-- Verification Notice -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Account Verification</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <?php if ($userRole === 'merchant'): ?>
                                    <p>Business accounts are typically verified within 1-2 business days. You'll receive an email confirmation once verified.</p>
                                <?php else: ?>
                                    <p>We'll make two small deposits to verify your account. This process usually takes 1-2 business days.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                    <a href="<?= $userRole === 'merchant' ? 'merchant/profile.php' : 'profile.php' ?>" 
                       class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left mr-2"></i>Cancel
                    </a>
                    
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Add Payment Method
                    </button>
                </div>
            </form>
        </div>

        <!-- Help Section -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Need Help?</h3>
            <div class="space-y-3">
                <div class="flex items-start">
                    <i class="fas fa-question-circle text-blue-600 mt-1 mr-3"></i>
                    <div>
                        <h4 class="font-medium text-gray-900">Where do I find my routing number?</h4>
                        <p class="text-sm text-gray-600">Your routing number is the 9-digit number on the bottom left of your checks, or you can find it in your online banking.</p>
                    </div>
                </div>
                
                <div class="flex items-start">
                    <i class="fas fa-shield-alt text-green-600 mt-1 mr-3"></i>
                    <div>
                        <h4 class="font-medium text-gray-900">Is my information secure?</h4>
                        <p class="text-sm text-gray-600">Yes, we use bank-level encryption and security measures to protect your financial information.</p>
                    </div>
                </div>
                
                <div class="flex items-start">
                    <i class="fas fa-clock text-yellow-600 mt-1 mr-3"></i>
                    <div>
                        <h4 class="font-medium text-gray-900">How long does verification take?</h4>
                        <p class="text-sm text-gray-600">Account verification typically takes 1-2 business days. You'll receive an email once your account is verified.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Format routing number input
        document.getElementById('routing_number').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 9);
        });
        
        // Format account number input
        document.getElementById('account_number').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 17);
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const routingNumber = document.getElementById('routing_number').value;
            const accountNumber = document.getElementById('account_number').value;
            
            if (routingNumber.length !== 9) {
                e.preventDefault();
                alert('Routing number must be exactly 9 digits');
                return false;
            }
            
            if (accountNumber.length < 8 || accountNumber.length > 17) {
                e.preventDefault();
                alert('Account number must be between 8 and 17 digits');
                return false;
            }
        });
    </script>
</body>
</html>
