<?php
require_once 'config/database.php';

// Require login
requireLogin();

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$addressId = intval($_GET['id'] ?? 0);

// Get address and verify ownership
$stmt = $pdo->prepare("SELECT * FROM shipping_addresses WHERE id = ? AND user_id = ?");
$stmt->execute([$addressId, $userId]);
$address = $stmt->fetch();

if (!$address) {
    header('Location: ' . ($userRole === 'merchant' ? 'merchant/profile.php' : 'profile.php'));
    exit;
}

// Don't allow deleting default address
if ($address['is_default']) {
    $_SESSION['error'] = 'Cannot delete default address. Please set another address as default first.';
    header('Location: ' . ($userRole === 'merchant' ? 'merchant/profile.php' : 'profile.php'));
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $stmt = $pdo->prepare("DELETE FROM shipping_addresses WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$addressId, $userId])) {
        $_SESSION['success'] = 'Address deleted successfully.';
    } else {
        $_SESSION['error'] = 'Failed to delete address.';
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
    <title>Delete Address - VentDepot</title>
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
            <h1 class="text-3xl font-bold text-gray-900">Delete Address</h1>
            <p class="text-gray-600 mt-2">Are you sure you want to delete this address?</p>
        </div>

        <!-- Address Preview -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="border-l-4 border-red-400 pl-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-2"><?= htmlspecialchars($address['address_name']) ?></h3>
                <div class="text-gray-600">
                    <p><?= htmlspecialchars($address['recipient_name']) ?></p>
                    <p><?= htmlspecialchars($address['address_line1']) ?></p>
                    <?php if ($address['address_line2']): ?>
                        <p><?= htmlspecialchars($address['address_line2']) ?></p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['state']) ?> <?= htmlspecialchars($address['postal_code']) ?></p>
                    <p><?= htmlspecialchars($address['country']) ?></p>
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
                        <p>This action cannot be undone. The address will be permanently deleted from your account.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" class="space-y-6">
                <div class="text-center">
                    <p class="text-gray-700 mb-6">
                        Please confirm that you want to delete this address. This action cannot be undone.
                    </p>
                </div>
                
                <!-- Form Actions -->
                <div class="flex justify-center space-x-4">
                    <a href="<?= $userRole === 'merchant' ? 'merchant/profile.php' : 'profile.php' ?>" 
                       class="bg-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-400">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    
                    <button type="submit" name="confirm_delete" value="1"
                            class="bg-red-600 text-white px-6 py-2 rounded-md hover:bg-red-700">
                        <i class="fas fa-trash mr-2"></i>Delete Address
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
