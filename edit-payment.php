<?php
require_once 'config/database.php';

// Require login
requireLogin();

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$paymentId = intval($_GET['id'] ?? 0);

// Get payment method and verify ownership
$stmt = $pdo->prepare("SELECT * FROM banking_details WHERE id = ? AND user_id = ?");
$stmt->execute([$paymentId, $userId]);
$payment = $stmt->fetch();

if (!$payment) {
    header('Location: ' . ($userRole === 'merchant' ? 'merchant/profile.php' : 'profile.php'));
    exit;
}

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
    if (empty($bankName) || empty($accountHolderName)) {
        $error = 'Please fill in all required fields.';
    } elseif (!empty($accountNumber) && (strlen($accountNumber) < 8 || strlen($accountNumber) > 17)) {
        $error = 'Account number must be between 8 and 17 digits.';
    } elseif (!empty($routingNumber) && strlen($routingNumber) !== 9) {
        $error = 'Routing number must be exactly 9 digits.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // If this is set as default, remove default from other banking details
            if ($isDefault) {
                $stmt = $pdo->prepare("UPDATE banking_details SET is_default = FALSE WHERE user_id = ? AND id != ?");
                $stmt->execute([$userId, $paymentId]);
            }
            
            // Prepare update data
            $updateFields = [];
            $updateValues = [];
            
            $updateFields[] = "account_type = ?";
            $updateValues[] = $accountType;
            
            $updateFields[] = "bank_name = ?";
            $updateValues[] = $bankName;
            
            $updateFields[] = "account_holder_name = ?";
            $updateValues[] = $accountHolderName;
            
            $updateFields[] = "is_default = ?";
            $updateValues[] = $isDefault;
            
            // Only update account details if provided (for security)
            if (!empty($accountNumber)) {
                $accountNumberEncrypted = '****' . substr($accountNumber, -4);
                $updateFields[] = "account_number_encrypted = ?";
                $updateValues[] = $accountNumberEncrypted;
            }
            
            if (!empty($routingNumber)) {
                $routingNumberEncrypted = '****' . substr($routingNumber, -4);
                $updateFields[] = "routing_number_encrypted = ?";
                $updateValues[] = $routingNumberEncrypted;
            }
            
            $updateValues[] = $paymentId;
            $updateValues[] = $userId;
            
            // Update banking details
            $sql = "UPDATE banking_details SET " . implode(', ', $updateFields) . " WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute($updateValues)) {
                $pdo->commit();
                $success = 'Payment method updated successfully!';
                
                // Refresh payment data
                $stmt = $pdo->prepare("SELECT * FROM banking_details WHERE id = ? AND user_id = ?");
                $stmt->execute([$paymentId, $userId]);
                $payment = $stmt->fetch();
            } else {
                $pdo->rollBack();
                $error = 'Failed to update payment method. Please try again.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'An error occurred while updating the payment method.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Payment Method - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            <h1 class="text-3xl font-bold text-gray-900">Edit Payment Method</h1>
            <p class="text-gray-600 mt-2">Update your banking information</p>
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

        <!-- Current Payment Method Info -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-university text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Current Payment Method</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p><strong>Bank:</strong> <?= htmlspecialchars($payment['bank_name']) ?></p>
                        <p><strong>Account Holder:</strong> <?= htmlspecialchars($payment['account_holder_name']) ?></p>
                        <p><strong>Account:</strong> <?= htmlspecialchars($payment['account_number_encrypted']) ?></p>
                        <p><strong>Type:</strong> <?= ucfirst($payment['account_type']) ?></p>
                        <?php if ($payment['is_default']): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mt-1">
                                Default Payment Method
                            </span>
                        <?php endif; ?>
                        <?php if ($payment['is_verified']): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 mt-1">
                                Verified
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mt-1">
                                Pending Verification
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Notice -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-shield-alt text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Security Notice</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>For security reasons, you can only update basic information. To change account or routing numbers, please contact support or add a new payment method.</p>
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
                            <option value="business" <?= $payment['account_type'] === 'business' ? 'selected' : '' ?>>Business Account</option>
                            <option value="checking" <?= $payment['account_type'] === 'checking' ? 'selected' : '' ?>>Personal Checking</option>
                            <option value="savings" <?= $payment['account_type'] === 'savings' ? 'selected' : '' ?>>Personal Savings</option>
                        <?php else: ?>
                            <option value="checking" <?= $payment['account_type'] === 'checking' ? 'selected' : '' ?>>Checking Account</option>
                            <option value="savings" <?= $payment['account_type'] === 'savings' ? 'selected' : '' ?>>Savings Account</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div>
                    <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Bank Name *
                    </label>
                    <input type="text" name="bank_name" id="bank_name" required
                           value="<?= htmlspecialchars($payment['bank_name']) ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., Chase Bank, Bank of America">
                </div>
                
                <div>
                    <label for="account_holder_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Account Holder Name *
                    </label>
                    <input type="text" name="account_holder_name" id="account_holder_name" required
                           value="<?= htmlspecialchars($payment['account_holder_name']) ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                           placeholder="<?= $userRole === 'merchant' ? 'Business name or account holder' : 'Full name as it appears on account' ?>">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="routing_number" class="block text-sm font-medium text-gray-700 mb-2">
                            Routing Number
                        </label>
                        <input type="text" name="routing_number" id="routing_number"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                               placeholder="Leave blank to keep current"
                               pattern="[0-9]{9}"
                               maxlength="9">
                        <p class="text-xs text-gray-500 mt-1">Only enter if you want to change it</p>
                    </div>
                    
                    <div>
                        <label for="account_number" class="block text-sm font-medium text-gray-700 mb-2">
                            Account Number
                        </label>
                        <input type="text" name="account_number" id="account_number"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                               placeholder="Leave blank to keep current"
                               pattern="[0-9]{8,17}"
                               maxlength="17">
                        <p class="text-xs text-gray-500 mt-1">Only enter if you want to change it</p>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" name="is_default" id="is_default" 
                           <?= $payment['is_default'] ? 'checked' : '' ?>
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="is_default" class="ml-2 text-sm text-gray-700">
                        Set as default payment method
                    </label>
                </div>
                
                <!-- Verification Notice -->
                <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">Re-verification Required</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <?php if ($userRole === 'merchant'): ?>
                                    <p>If you change account details, your account will need to be re-verified. This process usually takes 1-2 business days.</p>
                                <?php else: ?>
                                    <p>If you change account details, we'll make two small deposits to verify your account. This process usually takes 1-2 business days.</p>
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
                    
                    <div class="space-x-4">
                        <?php if (!$payment['is_default']): ?>
                            <a href="delete-payment.php?id=<?= $payment['id'] ?>" 
                               onclick="return confirm('Are you sure you want to delete this payment method?')"
                               class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                                <i class="fas fa-trash mr-2"></i>Delete
                            </a>
                        <?php endif; ?>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-save mr-2"></i>Update Payment Method
                        </button>
                    </div>
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
                        <h4 class="font-medium text-gray-900">Why can't I change my account number?</h4>
                        <p class="text-sm text-gray-600">For security reasons, major account changes require verification. Contact support or add a new payment method instead.</p>
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
                    <i class="fas fa-phone text-blue-600 mt-1 mr-3"></i>
                    <div>
                        <h4 class="font-medium text-gray-900">Need to make major changes?</h4>
                        <p class="text-sm text-gray-600">Contact our support team at support@ventdepot.com or 1-800-VENTDEPOT for assistance with account changes.</p>
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
            
            if (routingNumber && routingNumber.length !== 9) {
                e.preventDefault();
                alert('Routing number must be exactly 9 digits');
                return false;
            }
            
            if (accountNumber && (accountNumber.length < 8 || accountNumber.length > 17)) {
                e.preventDefault();
                alert('Account number must be between 8 and 17 digits');
                return false;
            }
        });
    </script>
</body>
</html>
