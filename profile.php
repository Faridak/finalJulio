<?php
session_start();
require_once 'config/database.php';

// Require login - using the existing isLoggedIn() function
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dateOfBirth = $_POST['date_of_birth'] ?? null;
        $bio = trim($_POST['bio'] ?? '');
        
        // Update or insert profile
        $stmt = $pdo->prepare("
            INSERT INTO user_profiles (user_id, first_name, last_name, phone, date_of_birth, bio) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            phone = VALUES(phone),
            date_of_birth = VALUES(date_of_birth),
            bio = VALUES(bio),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        if ($stmt->execute([$userId, $firstName, $lastName, $phone, $dateOfBirth ?: null, $bio])) {
            $success = 'Profile updated successfully!';
        } else {
            $error = 'Failed to update profile.';
        }
    } elseif ($action === 'update_preferences') {
        $emailNotifications = isset($_POST['email_notifications']);
        $smsNotifications = isset($_POST['sms_notifications']);
        $marketingEmails = isset($_POST['marketing_emails']);
        $orderUpdates = isset($_POST['order_updates']);
        $newsletter = isset($_POST['newsletter']);
        $favoriteCategories = $_POST['favorite_categories'] ?? [];
        
        $stmt = $pdo->prepare("
            INSERT INTO customer_preferences (user_id, favorite_categories, email_notifications, sms_notifications, marketing_emails, order_updates, newsletter) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            favorite_categories = VALUES(favorite_categories),
            email_notifications = VALUES(email_notifications),
            sms_notifications = VALUES(sms_notifications),
            marketing_emails = VALUES(marketing_emails),
            order_updates = VALUES(order_updates),
            newsletter = VALUES(newsletter),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        if ($stmt->execute([$userId, json_encode($favoriteCategories), $emailNotifications, $smsNotifications, $marketingEmails, $orderUpdates, $newsletter])) {
            $success = 'Preferences updated successfully!';
        } else {
            $error = 'Failed to update preferences.';
        }
    }
}

// Get user profile data
$profileStmt = $pdo->prepare("
    SELECT up.*, u.email, u.role, u.created_at as member_since
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE u.id = ?
");
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch();

// Get user preferences
$preferencesStmt = $pdo->prepare("SELECT * FROM customer_preferences WHERE user_id = ?");
$preferencesStmt->execute([$userId]);
$preferences = $preferencesStmt->fetch();

// Get shipping addresses
$addressesStmt = $pdo->prepare("SELECT * FROM shipping_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$addressesStmt->execute([$userId]);
$addresses = $addressesStmt->fetchAll();

// Get banking details
$bankingStmt = $pdo->prepare("SELECT * FROM banking_details WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$bankingStmt->execute([$userId]);
$bankingDetails = $bankingStmt->fetchAll();

// Get recent orders
$ordersStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$ordersStmt->execute([$userId]);
$recentOrders = $ordersStmt->fetchAll();

// Get support tickets
$supportStmt = $pdo->prepare("SELECT * FROM support_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$supportStmt->execute([$userId]);
$supportTickets = $supportStmt->fetchAll();

// Get available categories for preferences
$categoriesStmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
$categoriesStmt->execute();
$availableCategories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">My Profile</h1>
            <p class="text-gray-600 mt-2">Manage your account settings and preferences</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Profile Tabs -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden" x-data="{ activeTab: 'profile' }">
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6">
                    <button @click="activeTab = 'profile'" 
                            :class="activeTab === 'profile' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-user mr-2"></i>Profile Info
                    </button>
                    <button @click="activeTab = 'addresses'" 
                            :class="activeTab === 'addresses' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-map-marker-alt mr-2"></i>Addresses
                    </button>
                    <button @click="activeTab = 'banking'" 
                            :class="activeTab === 'banking' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-credit-card mr-2"></i>Payment Methods
                    </button>
                    <button @click="activeTab = 'preferences'" 
                            :class="activeTab === 'preferences' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-cog mr-2"></i>Preferences
                    </button>
                    <button @click="activeTab = 'security'" 
                            :class="activeTab === 'security' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-shield-alt mr-2"></i>Security
                    </button>
                    <button @click="activeTab = 'orders'" 
                            :class="activeTab === 'orders' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-shopping-bag mr-2"></i>Recent Orders
                    </button>
                    <button @click="activeTab = 'support'" 
                            :class="activeTab === 'support' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-headset mr-2"></i>Support
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Profile Info Tab -->
                <div x-show="activeTab === 'profile'" class="space-y-6">
                    <div class="flex items-center space-x-6 mb-6">
                        <div class="w-24 h-24 bg-gray-300 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-3xl text-gray-600"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">
                                <?= htmlspecialchars(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: 'Welcome!' ?>
                            </h2>
                            <p class="text-gray-600"><?= htmlspecialchars($profile['email']) ?></p>
                            <p class="text-sm text-gray-500">
                                <?= ucfirst($profile['role']) ?> since <?= date('M Y', strtotime($profile['member_since'])) ?>
                            </p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                <input type="text" name="first_name" id="first_name" 
                                       value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                <input type="text" name="last_name" id="last_name" 
                                       value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" name="phone" id="phone" 
                                       value="<?= htmlspecialchars($profile['phone'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                                <input type="date" name="date_of_birth" id="date_of_birth" 
                                       value="<?= htmlspecialchars($profile['date_of_birth'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div>
                            <label for="bio" class="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                            <textarea name="bio" id="bio" rows="4"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                      placeholder="Tell us about yourself..."><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Addresses Tab -->
                <div x-show="activeTab === 'addresses'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Shipping Addresses</h3>
                        <a href="add-address.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Address
                        </a>
                    </div>
                    
                    <?php if (empty($addresses)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-map-marker-alt text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No addresses saved yet.</p>
                            <a href="add-address.php" class="text-blue-600 hover:text-blue-800">Add your first address</a>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($addresses as $address): ?>
                                <div class="border border-gray-200 rounded-lg p-4 <?= $address['is_default'] ? 'ring-2 ring-blue-500' : '' ?>">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-medium text-gray-900"><?= htmlspecialchars($address['address_name']) ?></h4>
                                        <?php if ($address['is_default']): ?>
                                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Default</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-600">
                                        <?= htmlspecialchars($address['recipient_name']) ?><br>
                                        <?= htmlspecialchars($address['address_line1']) ?><br>
                                        <?php if ($address['address_line2']): ?>
                                            <?= htmlspecialchars($address['address_line2']) ?><br>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['state']) ?> <?= htmlspecialchars($address['postal_code']) ?>
                                    </p>
                                    <div class="mt-3 flex space-x-2">
                                        <a href="edit-address.php?id=<?= $address['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                                        <?php if (!$address['is_default']): ?>
                                            <a href="delete-address.php?id=<?= $address['id'] ?>" class="text-red-600 hover:text-red-800 text-sm">Delete</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Banking Tab -->
                <div x-show="activeTab === 'banking'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Payment Methods</h3>
                        <a href="add-payment.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Payment Method
                        </a>
                    </div>
                    
                    <?php if (empty($bankingDetails)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-credit-card text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No payment methods saved yet.</p>
                            <a href="add-payment.php" class="text-blue-600 hover:text-blue-800">Add your first payment method</a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($bankingDetails as $banking): ?>
                                <div class="border border-gray-200 rounded-lg p-4 <?= $banking['is_default'] ? 'ring-2 ring-blue-500' : '' ?>">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="flex items-center space-x-2 mb-2">
                                                <i class="fas fa-university text-gray-600"></i>
                                                <h4 class="font-medium text-gray-900"><?= htmlspecialchars($banking['bank_name']) ?></h4>
                                                <?php if ($banking['is_default']): ?>
                                                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Default</span>
                                                <?php endif; ?>
                                                <?php if ($banking['is_verified']): ?>
                                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Verified</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-sm text-gray-600">
                                                <?= htmlspecialchars($banking['account_holder_name']) ?><br>
                                                <?= ucfirst($banking['account_type']) ?> Account: <?= htmlspecialchars($banking['account_number_encrypted']) ?>
                                            </p>
                                        </div>
                                        <div class="flex space-x-2">
                                            <a href="edit-payment.php?id=<?= $banking['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                                            <?php if (!$banking['is_default']): ?>
                                                <a href="delete-payment.php?id=<?= $banking['id'] ?>" class="text-red-600 hover:text-red-800 text-sm">Delete</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Preferences Tab -->
                <div x-show="activeTab === 'preferences'" class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900">Account Preferences</h3>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_preferences">
                        
                        <!-- Favorite Categories -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Favorite Categories</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <?php 
                                $favoriteCategories = $preferences ? json_decode($preferences['favorite_categories'], true) : [];
                                foreach ($availableCategories as $category): 
                                ?>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="favorite_categories[]" value="<?= htmlspecialchars($category) ?>"
                                               <?= in_array($category, $favoriteCategories) ? 'checked' : '' ?>
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700"><?= htmlspecialchars($category) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Notification Preferences -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Notification Preferences</label>
                            <div class="space-y-3">
                                <label class="flex items-center">
                                    <input type="checkbox" name="email_notifications" 
                                           <?= ($preferences['email_notifications'] ?? true) ? 'checked' : '' ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Email notifications</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="sms_notifications" 
                                           <?= ($preferences['sms_notifications'] ?? false) ? 'checked' : '' ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">SMS notifications</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="marketing_emails" 
                                           <?= ($preferences['marketing_emails'] ?? true) ? 'checked' : '' ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Marketing emails</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="order_updates" 
                                           <?= ($preferences['order_updates'] ?? true) ? 'checked' : '' ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Order status updates</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="newsletter" 
                                           <?= ($preferences['newsletter'] ?? false) ? 'checked' : '' ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Newsletter subscription</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                                Update Preferences
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security Tab -->
                <div x-show="activeTab === 'security'" class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900">Security Settings</h3>
                    
                    <!-- Two-Factor Authentication -->
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 flex items-center">
                                    <i class="fas fa-shield-alt text-blue-600 mr-2"></i>
                                    Two-Factor Authentication
                                </h4>
                                <p class="text-sm text-gray-600 mt-1">
                                    Add an extra layer of security to your account
                                </p>
                            </div>
                            <div>
                                <?php
                                require_once 'includes/TwoFactorAuth.php';
                                $twoFA = new TwoFactorAuth($pdo);
                                $is2FAEnabled = $twoFA->isEnabled($userId);
                                ?>
                                <?php if ($is2FAEnabled): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check mr-1"></i>Enabled
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                        <i class="fas fa-times mr-1"></i>Disabled
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="text-sm text-gray-600 mb-4">
                            Two-factor authentication (2FA) helps protect your account by requiring both your password and a verification code from your phone when signing in.
                        </div>
                        
                        <a href="two-factor-auth.php" 
                           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <i class="fas fa-cog mr-2"></i>
                            Manage 2FA Settings
                        </a>
                    </div>
                    
                    <!-- Password Change -->
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 flex items-center">
                                    <i class="fas fa-key text-gray-600 mr-2"></i>
                                    Password
                                </h4>
                                <p class="text-sm text-gray-600 mt-1">
                                    Change your account password
                                </p>
                            </div>
                        </div>
                        
                        <a href="change-password.php" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <i class="fas fa-edit mr-2"></i>
                            Change Password
                        </a>
                    </div>
                    
                    <!-- Login Activity -->
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 flex items-center">
                                    <i class="fas fa-history text-gray-600 mr-2"></i>
                                    Recent Activity
                                </h4>
                                <p class="text-sm text-gray-600 mt-1">
                                    View your recent login activity and security events
                                </p>
                            </div>
                        </div>
                        
                        <?php
                        // Get recent security events for the user
                        $securityStmt = $pdo->prepare("
                            SELECT event_type, created_at, ip_address 
                            FROM security_events 
                            WHERE user_id = ? 
                            ORDER BY created_at DESC 
                            LIMIT 5
                        ");
                        $securityStmt->execute([$userId]);
                        $securityEvents = $securityStmt->fetchAll();
                        ?>
                        
                        <?php if (!empty($securityEvents)): ?>
                            <div class="space-y-2">
                                <?php foreach ($securityEvents as $event): ?>
                                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                                        <div class="flex items-center">
                                            <div class="text-sm text-gray-900">
                                                <?php
                                                $eventLabels = [
                                                    'user_login_success' => 'Successful login',
                                                    'user_login_success_2fa' => 'Successful login with 2FA',
                                                    'user_login_success_trusted_device' => 'Login from trusted device',
                                                    '2fa_enabled' => '2FA enabled',
                                                    '2fa_disabled' => '2FA disabled',
                                                    'login_failed' => 'Failed login attempt'
                                                ];
                                                echo $eventLabels[$event['event_type']] ?? ucfirst(str_replace('_', ' ', $event['event_type']));
                                                ?>
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= date('M j, Y g:i A', strtotime($event['created_at'])) ?>
                                            <br>IP: <?= htmlspecialchars($event['ip_address']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-gray-500">No recent security events found.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Account Deletion -->
                    <div class="border border-red-200 rounded-lg p-6 bg-red-50">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-lg font-medium text-red-900 flex items-center">
                                    <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                                    Danger Zone
                                </h4>
                                <p class="text-sm text-red-600 mt-1">
                                    Permanent actions that cannot be undone
                                </p>
                            </div>
                        </div>
                        
                        <button onclick="alert('Account deletion functionality would be implemented here.')" 
                                class="inline-flex items-center px-4 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50">
                            <i class="fas fa-trash mr-2"></i>
                            Delete Account
                        </button>
                    </div>
                </div>

                <!-- Recent Orders Tab -->
                <div x-show="activeTab === 'orders'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Orders</h3>
                        <a href="orders.php" class="text-blue-600 hover:text-blue-800">View All Orders</a>
                    </div>
                    
                    <?php if (empty($recentOrders)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-shopping-bag text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No orders yet.</p>
                            <a href="search.php" class="text-blue-600 hover:text-blue-800">Start shopping</a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-medium text-gray-900">Order #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></h4>
                                            <p class="text-sm text-gray-600">
                                                Placed on <?= date('M j, Y', strtotime($order['created_at'])) ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold text-gray-900">$<?= number_format($order['total'], 2) ?></p>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php
                                                switch($order['status']) {
                                                    case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'shipped': echo 'bg-blue-100 text-blue-800'; break;
                                                    case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                                    case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                                }
                                                ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Support Tab -->
                <div x-show="activeTab === 'support'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Support Tickets</h3>
                        <a href="contact-support.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>New Ticket
                        </a>
                    </div>
                    
                    <?php if (empty($supportTickets)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-headset text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No support tickets yet.</p>
                            <a href="contact-support.php" class="text-blue-600 hover:text-blue-800">Contact support</a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($supportTickets as $ticket): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-medium text-gray-900"><?= htmlspecialchars($ticket['subject']) ?></h4>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch($ticket['status']) {
                                                case 'open': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'in_progress': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'resolved': echo 'bg-green-100 text-green-800'; break;
                                                case 'closed': echo 'bg-gray-100 text-gray-800'; break;
                                            }
                                            ?>">
                                            <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-2"><?= htmlspecialchars(substr($ticket['message'], 0, 150)) ?>...</p>
                                    <div class="flex justify-between items-center text-xs text-gray-500">
                                        <span>Ticket: <?= htmlspecialchars($ticket['ticket_id']) ?></span>
                                        <span><?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
