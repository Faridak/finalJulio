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

// Don't allow deleting default payment method
if ($payment['is_default']) {
    $_SESSION['error'] = 'Cannot delete default payment method. Please set another payment method as default first.';
    header('Location: ' . ($userRole === 'merchant' ? 'merchant/profile.php' : 'profile.php'));
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $stmt = $pdo->prepare("DELETE FROM banking_details WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$paymentId, $userId])) {
        $_SESSION['success'] = 'Payment method deleted successfully.';
    } else {
        $_SESSION['error'] = 'Failed to delete payment method.';
    }
    
    header('Location: ' . ($userRole === 'merchant' ? 'merchant/profile.php' : 'profile.php'));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Payment Method - VentDepot</title>
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
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-gray-900">Delete Payment Method</h1>
            <p class="text-gray-600 mt-2">Are you sure you want to delete this payment method?</p>
        </div>

        <!-- Payment Method Preview -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="border-l-4 border-red-400 pl-4">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-university text-gray-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($payment['bank_name']) ?></h3>
                        <p class="text-gray-600"><?= htmlspecialchars($payment['account_holder_name']) ?></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Account Type</p>
                        <p class="font-medium text-gray-900"><?= ucfirst($payment['account_type']) ?> Account</p>
                    </div>
                    
                    <div>
                        <p class="text-gray-500">Account Number</p>
                        <p class="font-medium text-gray-900"><?= htmlspecialchars($payment['account_number_encrypted']) ?></p>
                    </div>
                    
                    <div>
                        <p class="text-gray-500">Status</p>
                        <div class="flex items-center space-x-2">
                            <?php if ($payment['is_verified']): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <i class="fas fa-check-circle mr-1"></i>Verified
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    <i class="fas fa-clock mr-1"></i>Pending Verification
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <p class="text-gray-500">Added</p>
                        <p class="font-medium text-gray-900"><?= date('M j, Y', strtotime($payment['created_at'])) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warning -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Warning</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li>This action cannot be undone</li>
                            <li>The payment method will be permanently removed from your account</li>
                            <?php if ($userRole === 'merchant'): ?>
                                <li>Any pending payouts to this account may be delayed</li>
                                <li>You'll need to add a new payment method to receive future payments</li>
                            <?php else: ?>
                                <li>You won't be able to use this payment method for future purchases</li>
                                <li>Any saved payment preferences will be lost</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alternative Actions -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-lightbulb text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Consider These Alternatives</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li><a href="edit-payment.php?id=<?= $payment['id'] ?>" class="text-blue-600 hover:text-blue-800 underline">Edit the payment method</a> instead of deleting it</li>
                            <li>Keep it as a backup payment option</li>
                            <li>Contact support if you're having issues with this payment method</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" class="space-y-6">
                <div class="text-center">
                    <i class="fas fa-trash-alt text-4xl text-red-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Confirm Deletion</h3>
                    <p class="text-gray-700 mb-6">
                        Please confirm that you want to permanently delete this payment method. This action cannot be undone.
                    </p>
                </div>
                
                <!-- Confirmation Checkbox -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex items-start">
                        <input type="checkbox" id="confirm_understanding" required
                               class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded mt-1">
                        <label for="confirm_understanding" class="ml-2 text-sm text-gray-700">
                            I understand that this action is permanent and cannot be undone. I want to delete this payment method from my account.
                        </label>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="flex justify-center space-x-4">
                    <a href="<?= $userRole === 'merchant' ? 'merchant/profile.php' : 'profile.php' ?>" 
                       class="bg-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-400">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    
                    <button type="submit" name="confirm_delete" value="1"
                            class="bg-red-600 text-white px-6 py-2 rounded-md hover:bg-red-700"
                            onclick="return confirm('Are you absolutely sure you want to delete this payment method?')">
                        <i class="fas fa-trash mr-2"></i>Delete Payment Method
                    </button>
                </div>
            </form>
        </div>

        <!-- Support Information -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Need Help?</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Having Issues?</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Payment method not working properly?</li>
                        <li>• Need to update account information?</li>
                        <li>• Questions about verification?</li>
                    </ul>
                    <p class="text-sm text-blue-600 mt-2">
                        <a href="contact-support.php" class="hover:text-blue-800">Contact our support team</a> instead of deleting.
                    </p>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Contact Support</h4>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p><i class="fas fa-envelope mr-2"></i>support@ventdepot.com</p>
                        <p><i class="fas fa-phone mr-2"></i>1-800-VENTDEPOT</p>
                        <p><i class="fas fa-clock mr-2"></i>Mon-Fri 9AM-6PM EST</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Disable submit button until checkbox is checked
        document.addEventListener('DOMContentLoaded', function() {
            const checkbox = document.getElementById('confirm_understanding');
            const submitButton = document.querySelector('button[name="confirm_delete"]');
            
            function updateSubmitButton() {
                submitButton.disabled = !checkbox.checked;
                if (checkbox.checked) {
                    submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                    submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }
            
            checkbox.addEventListener('change', updateSubmitButton);
            updateSubmitButton(); // Initial state
        });
    </script>
</body>
</html>
