<?php
require_once '../config/database.php';

// Require merchant login
requireRole('merchant');

$merchantId = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        // Update or insert profile
        $stmt = $pdo->prepare("
            INSERT INTO user_profiles (user_id, first_name, last_name, phone, bio) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            phone = VALUES(phone),
            bio = VALUES(bio),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        if ($stmt->execute([$merchantId, $firstName, $lastName, $phone, $bio])) {
            $success = 'Profile updated successfully!';
        } else {
            $error = 'Failed to update profile.';
        }
    }
}

// Get merchant profile data
$profileStmt = $pdo->prepare("
    SELECT up.*, u.email, u.role, u.created_at as member_since
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE u.id = ?
");
$profileStmt->execute([$merchantId]);
$profile = $profileStmt->fetch();

// Get merchant business addresses
$addressesStmt = $pdo->prepare("SELECT * FROM shipping_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$addressesStmt->execute([$merchantId]);
$addresses = $addressesStmt->fetchAll();

// Get merchant banking details
$bankingStmt = $pdo->prepare("SELECT * FROM banking_details WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$bankingStmt->execute([$merchantId]);
$bankingDetails = $bankingStmt->fetchAll();

// Get merchant statistics
$statsQuery = "
    SELECT 
        COUNT(DISTINCT p.id) as total_products,
        COALESCE(SUM(p.stock), 0) as total_inventory,
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(o.total), 0) as total_revenue,
        COUNT(CASE WHEN o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as orders_this_month,
        COALESCE(SUM(CASE WHEN o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN o.total END), 0) as revenue_this_month
    FROM products p
    LEFT JOIN orders o ON o.user_id IN (SELECT DISTINCT user_id FROM orders)
    WHERE p.merchant_id = ?
";
$statsStmt = $pdo->prepare($statsQuery);
$statsStmt->execute([$merchantId]);
$stats = $statsStmt->fetch();

// Get recent orders for merchant's products
$recentOrdersQuery = "
    SELECT DISTINCT o.*, u.email as customer_email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN products p ON p.merchant_id = ?
    ORDER BY o.created_at DESC
    LIMIT 5
";
$ordersStmt = $pdo->prepare($recentOrdersQuery);
$ordersStmt->execute([$merchantId]);
$recentOrders = $ordersStmt->fetchAll();

// Get top selling products
$topProductsQuery = "
    SELECT p.name, p.price, p.stock, COUNT(o.id) as order_count
    FROM products p
    LEFT JOIN orders o ON o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE p.merchant_id = ?
    GROUP BY p.id
    ORDER BY order_count DESC
    LIMIT 5
";
$topProductsStmt = $pdo->prepare($topProductsQuery);
$topProductsStmt->execute([$merchantId]);
$topProducts = $topProductsStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Profile - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Merchant Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                    <span class="text-gray-400">|</span>
                    <a href="dashboard.php" class="text-lg font-semibold text-gray-700 hover:text-blue-600">Merchant Dashboard</a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 text-gray-600 hover:text-blue-600">
                            <i class="fas fa-user"></i>
                            <span><?= htmlspecialchars($_SESSION['user_email']) ?></span>
                            <i class="fas fa-chevron-down text-sm"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" 
                             class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                            <a href="../index.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">View Store</a>
                            <a href="../logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Merchant Profile</h1>
            <p class="text-gray-600 mt-2">Manage your business profile and account settings</p>
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

        <!-- Business Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-box text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Products</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['total_products'] ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-shopping-cart text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Orders</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['total_orders'] ?></p>
                        <p class="text-xs text-gray-500"><?= $stats['orders_this_month'] ?> this month</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-dollar-sign text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-900">$<?= number_format($stats['total_revenue'], 2) ?></p>
                        <p class="text-xs text-gray-500">$<?= number_format($stats['revenue_this_month'], 2) ?> this month</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <i class="fas fa-warehouse text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Inventory</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['total_inventory'] ?></p>
                        <p class="text-xs text-gray-500">items in stock</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Tabs -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden" x-data="{ activeTab: 'profile' }">
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6">
                    <button @click="activeTab = 'profile'" 
                            :class="activeTab === 'profile' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-store mr-2"></i>Business Profile
                    </button>
                    <button @click="activeTab = 'addresses'" 
                            :class="activeTab === 'addresses' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-map-marker-alt mr-2"></i>Business Addresses
                    </button>
                    <button @click="activeTab = 'banking'" 
                            :class="activeTab === 'banking' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-university mr-2"></i>Banking Details
                    </button>
                    <button @click="activeTab = 'performance'" 
                            :class="activeTab === 'performance' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-chart-line mr-2"></i>Performance
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Business Profile Tab -->
                <div x-show="activeTab === 'profile'" class="space-y-6">
                    <div class="flex items-center space-x-6 mb-6">
                        <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-store text-3xl text-green-600"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">
                                <?= htmlspecialchars(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: 'Business Owner' ?>
                            </h2>
                            <p class="text-gray-600"><?= htmlspecialchars($profile['email']) ?></p>
                            <p class="text-sm text-gray-500">
                                Merchant since <?= date('M Y', strtotime($profile['member_since'])) ?>
                            </p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">Business Owner First Name</label>
                                <input type="text" name="first_name" id="first_name" 
                                       value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Business Owner Last Name</label>
                                <input type="text" name="last_name" id="last_name" 
                                       value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Business Phone</label>
                                <input type="tel" name="phone" id="phone" 
                                       value="<?= htmlspecialchars($profile['phone'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Business Email</label>
                                <input type="email" value="<?= htmlspecialchars($profile['email']) ?>" disabled
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100 text-gray-500">
                                <p class="text-xs text-gray-500 mt-1">Contact support to change email address</p>
                            </div>
                        </div>
                        
                        <div>
                            <label for="bio" class="block text-sm font-medium text-gray-700 mb-2">Business Description</label>
                            <textarea name="bio" id="bio" rows="4"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                      placeholder="Describe your business, specialties, and what makes you unique..."><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                                Update Business Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Business Addresses Tab -->
                <div x-show="activeTab === 'addresses'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Business Addresses</h3>
                        <a href="../add-address.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Address
                        </a>
                    </div>
                    
                    <?php if (empty($addresses)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-building text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No business addresses saved yet.</p>
                            <a href="../add-address.php" class="text-blue-600 hover:text-blue-800">Add your business address</a>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($addresses as $address): ?>
                                <div class="border border-gray-200 rounded-lg p-4 <?= $address['is_default'] ? 'ring-2 ring-blue-500' : '' ?>">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-medium text-gray-900"><?= htmlspecialchars($address['address_name']) ?></h4>
                                        <?php if ($address['is_default']): ?>
                                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Primary</span>
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
                                        <a href="../edit-address.php?id=<?= $address['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                                        <?php if (!$address['is_default']): ?>
                                            <a href="../delete-address.php?id=<?= $address['id'] ?>" class="text-red-600 hover:text-red-800 text-sm">Delete</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Banking Details Tab -->
                <div x-show="activeTab === 'banking'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Banking & Payment Details</h3>
                        <a href="../add-payment.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Bank Account
                        </a>
                    </div>
                    
                    <?php if (empty($bankingDetails)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-university text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No banking details saved yet.</p>
                            <p class="text-sm text-gray-400 mb-4">Add your business bank account to receive payments</p>
                            <a href="../add-payment.php" class="text-blue-600 hover:text-blue-800">Add bank account</a>
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
                                                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Primary</span>
                                                <?php endif; ?>
                                                <?php if ($banking['is_verified']): ?>
                                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Verified</span>
                                                <?php else: ?>
                                                    <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">Pending Verification</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-sm text-gray-600">
                                                <?= htmlspecialchars($banking['account_holder_name']) ?><br>
                                                <?= ucfirst($banking['account_type']) ?> Account: <?= htmlspecialchars($banking['account_number_encrypted']) ?>
                                            </p>
                                        </div>
                                        <div class="flex space-x-2">
                                            <a href="../edit-payment.php?id=<?= $banking['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                                            <?php if (!$banking['is_default']): ?>
                                                <a href="../delete-payment.php?id=<?= $banking['id'] ?>" class="text-red-600 hover:text-red-800 text-sm">Delete</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Performance Tab -->
                <div x-show="activeTab === 'performance'" class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900">Business Performance</h3>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Recent Orders -->
                        <div>
                            <h4 class="font-medium text-gray-900 mb-4">Recent Orders</h4>
                            <?php if (empty($recentOrders)): ?>
                                <p class="text-gray-500 text-center py-8">No recent orders</p>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($recentOrders as $order): ?>
                                        <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                            <div>
                                                <p class="font-medium text-gray-900">Order #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></p>
                                                <p class="text-sm text-gray-600"><?= htmlspecialchars($order['customer_email']) ?></p>
                                                <p class="text-xs text-gray-500"><?= date('M j, Y', strtotime($order['created_at'])) ?></p>
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
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Top Products -->
                        <div>
                            <h4 class="font-medium text-gray-900 mb-4">Top Selling Products</h4>
                            <?php if (empty($topProducts)): ?>
                                <p class="text-gray-500 text-center py-8">No product data available</p>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($topProducts as $product): ?>
                                        <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                            <div>
                                                <p class="font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></p>
                                                <p class="text-sm text-gray-600">$<?= number_format($product['price'], 2) ?></p>
                                                <p class="text-xs text-gray-500">Stock: <?= $product['stock'] ?></p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-lg font-semibold text-blue-600"><?= $product['order_count'] ?></p>
                                                <p class="text-xs text-gray-500">orders</p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <a href="analytics.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                            <div class="p-2 bg-purple-100 rounded-lg">
                                <i class="fas fa-chart-bar text-purple-600"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-medium text-gray-900">View Analytics</h3>
                                <p class="text-sm text-gray-600">Detailed performance metrics</p>
                            </div>
                        </a>

                        <a href="orders.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                            <div class="p-2 bg-yellow-100 rounded-lg">
                                <i class="fas fa-shopping-bag text-yellow-600"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-medium text-gray-900">Manage Orders</h3>
                                <p class="text-sm text-gray-600">View and update orders</p>
                            </div>
                        </a>

                        <a href="products.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                            <div class="p-2 bg-green-100 rounded-lg">
                                <i class="fas fa-box text-green-600"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-medium text-gray-900">Manage Products</h3>
                                <p class="text-sm text-gray-600">Edit inventory and listings</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
