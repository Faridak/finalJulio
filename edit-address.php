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

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $addressName = trim($_POST['address_name'] ?? '');
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $addressLine1 = trim($_POST['address_line1'] ?? '');
    $addressLine2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $country = trim($_POST['country'] ?? 'United States');
    $isDefault = isset($_POST['is_default']);
    
    // Validation
    if (empty($addressName) || empty($recipientName) || empty($addressLine1) || empty($city) || empty($state) || empty($postalCode)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // If this is set as default, remove default from other addresses
            if ($isDefault) {
                $stmt = $pdo->prepare("UPDATE shipping_addresses SET is_default = FALSE WHERE user_id = ? AND id != ?");
                $stmt->execute([$userId, $addressId]);
            }
            
            // Update address
            $stmt = $pdo->prepare("
                UPDATE shipping_addresses 
                SET address_name = ?, recipient_name = ?, address_line1 = ?, address_line2 = ?, 
                    city = ?, state = ?, postal_code = ?, country = ?, is_default = ?
                WHERE id = ? AND user_id = ?
            ");
            
            if ($stmt->execute([$addressName, $recipientName, $addressLine1, $addressLine2, $city, $state, $postalCode, $country, $isDefault, $addressId, $userId])) {
                $pdo->commit();
                $success = 'Address updated successfully!';
                
                // Refresh address data
                $stmt = $pdo->prepare("SELECT * FROM shipping_addresses WHERE id = ? AND user_id = ?");
                $stmt->execute([$addressId, $userId]);
                $address = $stmt->fetch();
            } else {
                $pdo->rollBack();
                $error = 'Failed to update address. Please try again.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'An error occurred while updating the address.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Address - VentDepot</title>
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
            <h1 class="text-3xl font-bold text-gray-900">Edit Address</h1>
            <p class="text-gray-600 mt-2">Update your address information</p>
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

        <!-- Address Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="address_name" class="block text-sm font-medium text-gray-700 mb-2">Address Name *</label>
                        <input type="text" name="address_name" id="address_name" required
                               value="<?= htmlspecialchars($address['address_name']) ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="recipient_name" class="block text-sm font-medium text-gray-700 mb-2">Recipient Name *</label>
                        <input type="text" name="recipient_name" id="recipient_name" required
                               value="<?= htmlspecialchars($address['recipient_name']) ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div>
                    <label for="address_line1" class="block text-sm font-medium text-gray-700 mb-2">Address Line 1 *</label>
                    <input type="text" name="address_line1" id="address_line1" required
                           value="<?= htmlspecialchars($address['address_line1']) ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="address_line2" class="block text-sm font-medium text-gray-700 mb-2">Address Line 2</label>
                    <input type="text" name="address_line2" id="address_line2"
                           value="<?= htmlspecialchars($address['address_line2'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700 mb-2">City *</label>
                        <input type="text" name="city" id="city" required
                               value="<?= htmlspecialchars($address['city']) ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="state" class="block text-sm font-medium text-gray-700 mb-2">State *</label>
                        <input type="text" name="state" id="state" required
                               value="<?= htmlspecialchars($address['state']) ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-2">ZIP Code *</label>
                        <input type="text" name="postal_code" id="postal_code" required
                               value="<?= htmlspecialchars($address['postal_code']) ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700 mb-2">Country *</label>
                    <input type="text" name="country" id="country" required
                           value="<?= htmlspecialchars($address['country']) ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" name="is_default" id="is_default" 
                           <?= $address['is_default'] ? 'checked' : '' ?>
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="is_default" class="ml-2 text-sm text-gray-700">
                        Set as default address
                    </label>
                </div>
                
                <!-- Form Actions -->
                <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                    <a href="<?= $userRole === 'merchant' ? 'merchant/profile.php' : 'profile.php' ?>" 
                       class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left mr-2"></i>Cancel
                    </a>
                    
                    <div class="space-x-4">
                        <?php if (!$address['is_default']): ?>
                            <a href="delete-address.php?id=<?= $address['id'] ?>" 
                               onclick="return confirm('Are you sure you want to delete this address?')"
                               class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                                <i class="fas fa-trash mr-2"></i>Delete
                            </a>
                        <?php endif; ?>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-save mr-2"></i>Update Address
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
