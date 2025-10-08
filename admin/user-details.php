<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$userId = intval($_GET['id'] ?? 0);

if (!$userId) {
    header('Location: users.php');
    exit;
}

// Get user details with profile information
$userQuery = "
    SELECT u.*, up.first_name, up.last_name, up.phone, up.date_of_birth, up.bio
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE u.id = ?
";
$stmt = $pdo->prepare($userQuery);
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// Get user statistics
$stats = [];

if ($user['role'] === 'customer') {
    // Customer statistics
    $stats = [
        'total_orders' => $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?"),
        'total_spent' => $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = ? AND status != 'cancelled'"),
        'avg_order_value' => $pdo->prepare("SELECT COALESCE(AVG(total), 0) FROM orders WHERE user_id = ? AND status != 'cancelled'"),
        'last_order' => $pdo->prepare("SELECT created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1")
    ];
    
    foreach ($stats as $key => $stmt) {
        $stmt->execute([$userId]);
        $stats[$key] = $stmt->fetchColumn();
    }
    
    // Get recent orders
    $ordersQuery = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
    $stmt = $pdo->prepare($ordersQuery);
    $stmt->execute([$userId]);
    $recentOrders = $stmt->fetchAll();
    
} elseif ($user['role'] === 'merchant') {
    // Merchant statistics
    $stats = [
        'total_products' => $pdo->prepare("SELECT COUNT(*) FROM products WHERE merchant_id = ?"),
        'active_products' => $pdo->prepare("SELECT COUNT(*) FROM products WHERE merchant_id = ? AND stock > 0"),
        'total_sales' => $pdo->prepare("SELECT COALESCE(SUM(oi.price * oi.quantity), 0) FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE p.merchant_id = ?"),
        'total_orders' => $pdo->prepare("SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE p.merchant_id = ?")
    ];
    
    foreach ($stats as $key => $stmt) {
        $stmt->execute([$userId]);
        $stats[$key] = $stmt->fetchColumn();
    }
    
    // Get recent products
    $productsQuery = "SELECT * FROM products WHERE merchant_id = ? ORDER BY created_at DESC LIMIT 10";
    $stmt = $pdo->prepare($productsQuery);
    $stmt->execute([$userId]);
    $recentProducts = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h1>
                <p class="text-gray-600 mt-2"><?= ucfirst($user['role']) ?> • Joined <?= date('F j, Y', strtotime($user['created_at'])) ?></p>
            </div>
            <button onclick="window.close()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                <i class="fas fa-times mr-2"></i>Close
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- User Information -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="text-center mb-6">
                        <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user text-gray-400 text-2xl"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            <?php
                            switch($user['role']) {
                                case 'admin': echo 'bg-red-100 text-red-800'; break;
                                case 'merchant': echo 'bg-blue-100 text-blue-800'; break;
                                case 'customer': echo 'bg-green-100 text-green-800'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Email</label>
                            <p class="text-gray-900"><?= htmlspecialchars($user['email']) ?></p>
                            <span class="inline-flex items-center text-xs text-blue-600">
                                <i class="fas fa-user mr-1"></i>Registered User
                            </span>
                        </div>
                        
                        <?php if ($user['phone']): ?>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Phone</label>
                            <p class="text-gray-900"><?= htmlspecialchars($user['phone']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <label class="text-sm font-medium text-gray-500">Member Since</label>
                            <p class="text-gray-900"><?= date('F j, Y', strtotime($user['created_at'])) ?></p>
                        </div>
                        

                        
                        <div>
                            <label class="text-sm font-medium text-gray-500">Account Status</label>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Active
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
                    <div class="space-y-3">
                        <a href="users.php?search=<?= $user['email'] ?>" target="_blank" 
                           class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-center block">
                            <i class="fas fa-external-link-alt mr-2"></i>View in Users
                        </a>
                        <a href="mailto:<?= htmlspecialchars($user['email']) ?>" 
                           class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 text-center block">
                            <i class="fas fa-envelope mr-2"></i>Send Email
                        </a>
                        <?php if ($user['role'] === 'customer'): ?>
                        <a href="orders.php?user=<?= $user['id'] ?>" target="_blank" 
                           class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 text-center block">
                            <i class="fas fa-shopping-cart mr-2"></i>View Orders
                        </a>
                        <?php elseif ($user['role'] === 'merchant'): ?>
                        <a href="products.php?merchant=<?= $user['id'] ?>" target="_blank" 
                           class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 text-center block">
                            <i class="fas fa-box mr-2"></i>View Products
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics and Activity -->
            <div class="lg:col-span-2">
                <!-- Statistics -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistics</h3>
                    
                    <?php if ($user['role'] === 'customer'): ?>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600"><?= number_format($stats['total_orders']) ?></div>
                                <div class="text-sm text-gray-600">Total Orders</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">$<?= number_format($stats['total_spent'], 2) ?></div>
                                <div class="text-sm text-gray-600">Total Spent</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600">$<?= number_format($stats['avg_order_value'], 2) ?></div>
                                <div class="text-sm text-gray-600">Avg Order</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-orange-600">
                                    <?= $stats['last_order'] ? date('M j', strtotime($stats['last_order'])) : 'Never' ?>
                                </div>
                                <div class="text-sm text-gray-600">Last Order</div>
                            </div>
                        </div>
                    <?php elseif ($user['role'] === 'merchant'): ?>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600"><?= number_format($stats['total_products']) ?></div>
                                <div class="text-sm text-gray-600">Total Products</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600"><?= number_format($stats['active_products']) ?></div>
                                <div class="text-sm text-gray-600">Active Products</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600">$<?= number_format($stats['total_sales'], 2) ?></div>
                                <div class="text-sm text-gray-600">Total Sales</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-orange-600"><?= number_format($stats['total_orders']) ?></div>
                                <div class="text-sm text-gray-600">Orders Fulfilled</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Recent <?= $user['role'] === 'customer' ? 'Orders' : 'Products' ?>
                    </h3>
                    
                    <?php if ($user['role'] === 'customer' && !empty($recentOrders)): ?>
                        <div class="space-y-4">
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                    <div>
                                        <div class="font-medium text-gray-900">Order #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></div>
                                        <div class="text-sm text-gray-500"><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium text-gray-900">$<?= number_format($order['total'], 2) ?></div>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            <?php
                                            switch(strtolower($order['status'])) {
                                                case 'completed': echo 'bg-green-100 text-green-800'; break;
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-blue-100 text-blue-800';
                                            }
                                            ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($user['role'] === 'merchant' && !empty($recentProducts)): ?>
                        <div class="space-y-4">
                            <?php foreach ($recentProducts as $product): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                    <div class="flex items-center">
                                        <?php if ($product['image_url']): ?>
                                            <img class="h-10 w-10 rounded-lg object-cover mr-4" 
                                                 src="<?= htmlspecialchars($product['image_url']) ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>">
                                        <?php else: ?>
                                            <div class="h-10 w-10 bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                                                <i class="fas fa-image text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= date('M j, Y', strtotime($product['created_at'])) ?></div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium text-gray-900">$<?= number_format($product['price'], 2) ?></div>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            <?= $product['stock'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= $product['stock'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
