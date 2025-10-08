<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$merchantId = intval($_GET['id'] ?? 0);

if (!$merchantId) {
    header('Location: merchants.php');
    exit;
}

// Get merchant details
$merchantQuery = "
    SELECT u.*, up.first_name, up.last_name, up.phone, up.bio,
           COUNT(DISTINCT p.id) as total_products,
           COUNT(DISTINCT CASE WHEN p.stock > 0 THEN p.id END) as active_products,
           COALESCE(SUM(CASE WHEN o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN oi.price * oi.quantity END), 0) as monthly_revenue
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN products p ON u.id = p.merchant_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE u.id = ? AND u.role = 'merchant'
    GROUP BY u.id
";
$stmt = $pdo->prepare($merchantQuery);
$stmt->execute([$merchantId]);
$merchant = $stmt->fetch();

if (!$merchant) {
    header('Location: merchants.php');
    exit;
}

// Get merchant's products
$productsQuery = "
    SELECT p.*, 
           COUNT(DISTINCT oi.order_id) as times_sold,
           COALESCE(SUM(oi.quantity), 0) as total_sold,
           COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenue
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled'
    WHERE p.merchant_id = ?
    GROUP BY p.id
    ORDER BY p.stock DESC, p.created_at DESC
";
$stmt = $pdo->prepare($productsQuery);
$stmt->execute([$merchantId]);
$products = $stmt->fetchAll();

// Get recent orders for this merchant
$ordersQuery = "
    SELECT DISTINCT o.*, u.email as customer_email,
           COALESCE(up.first_name, '') as customer_first_name,
           COALESCE(up.last_name, '') as customer_last_name
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE p.merchant_id = ?
    ORDER BY o.created_at DESC
    LIMIT 10
";
$stmt = $pdo->prepare($ordersQuery);
$stmt->execute([$merchantId]);
$recentOrders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($merchant['first_name'] . ' ' . $merchant['last_name']) ?> Store - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                    <span class="text-gray-400">|</span>
                    <a href="dashboard.php" class="text-lg font-semibold text-red-600 hover:text-red-700">Admin Panel</a>
                    <span class="text-gray-400">|</span>
                    <span class="text-lg text-gray-600">Merchant Store</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    <?= htmlspecialchars(trim($merchant['first_name'] . ' ' . $merchant['last_name']) ?: $merchant['email']) ?>'s Store
                </h1>
                <p class="text-gray-600 mt-2">Admin view of merchant store and inventory</p>
            </div>
            <div class="flex space-x-3">
                <a href="user-details.php?id=<?= $merchantId ?>" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    <i class="fas fa-user mr-2"></i>View Details
                </a>
                <a href="merchant-sales.php?id=<?= $merchantId ?>" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                    <i class="fas fa-chart-line mr-2"></i>Track Sales
                </a>
                <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Merchant Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-box text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Products</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($merchant['total_products']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Products</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($merchant['active_products']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-dollar-sign text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Monthly Revenue</p>
                        <p class="text-2xl font-semibold text-gray-900">$<?= number_format($merchant['monthly_revenue'], 2) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-calendar text-orange-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Member Since</p>
                        <p class="text-lg font-semibold text-gray-900"><?= date('M Y', strtotime($merchant['created_at'])) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Inventory -->
        <div class="bg-white rounded-lg shadow-md mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-900">Product Inventory</h2>
                    <a href="products.php?merchant=<?= $merchantId ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        <i class="fas fa-external-link-alt mr-1"></i>Manage in Products
                    </a>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sales</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                    No products found for this merchant.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
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
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        $<?= number_format($product['price'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= number_format($product['stock']) ?>
                                        <?php if ($product['stock'] < 10): ?>
                                            <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Low Stock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= number_format($product['total_sold']) ?> units
                                        <div class="text-xs text-gray-500"><?= $product['times_sold'] ?> orders</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        $<?= number_format($product['total_revenue'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?= $product['stock'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= $product['stock'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="../product.php?id=<?= $product['id'] ?>" target="_blank" 
                                               class="text-blue-600 hover:text-blue-900" title="View Product">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="products.php?search=<?= $product['id'] ?>" target="_blank" 
                                               class="text-green-600 hover:text-green-900" title="Edit Product">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-900">Recent Orders</h2>
                    <a href="orders.php?merchant=<?= $merchantId ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        <i class="fas fa-external-link-alt mr-1"></i>View All Orders
                    </a>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No recent orders found for this merchant.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars(trim($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?: $order['customer_email']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($order['customer_email']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= date('M j, Y', strtotime($order['created_at'])) ?>
                                        <div class="text-xs text-gray-500"><?= date('H:i', strtotime($order['created_at'])) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        $<?= number_format($order['total'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch(strtolower($order['status'])) {
                                                case 'completed': case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'processing': case 'shipped': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="order-details.php?id=<?= $order['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="View Order Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
