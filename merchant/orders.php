<?php
require_once '../config/database.php';

// Require merchant login
requireRole('merchant');

$merchantId = $_SESSION['user_id'];

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = intval($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    
    if ($action === 'update_status' && $orderId > 0 && in_array($newStatus, ['pending', 'shipped', 'delivered', 'cancelled'])) {
        // Verify order contains merchant's products
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM orders o 
            JOIN products p ON p.merchant_id = ? 
            WHERE o.id = ?
        ");
        $checkStmt->execute([$merchantId, $orderId]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $updateStmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $updateStmt->execute([$newStatus, $orderId]);
            $success = 'Order status updated successfully.';
        }
    }
}

// Get orders for merchant's products
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build WHERE conditions and parameters
$whereConditions = [];
$params = [];

if (!empty($status)) {
    $whereConditions[] = 'o.status = ?';
    $params[] = $status;
}

if (!empty($search)) {
    $whereConditions[] = '(u.email LIKE ? OR o.id LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'AND ' . implode(' AND ', $whereConditions);
}

// Get total count
$countSql = "
    SELECT COUNT(DISTINCT o.id)
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.merchant_id = ? $whereClause
";
$countParams = array_merge([$merchantId], $params);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $perPage);

// Get orders
$sql = "
    SELECT DISTINCT o.*, u.email as customer_email, u.id as customer_id
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.merchant_id = ? $whereClause
    ORDER BY o.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($countParams);
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - VentDepot Merchant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
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
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                            <a href="../logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Order Management</h1>
                <p class="text-gray-600 mt-2">Manage orders for your products</p>
            </div>
            
            <div class="text-sm text-gray-600">
                Total: <?= $totalOrders ?> orders
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                           placeholder="Search by order ID or customer email...">
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" id="status"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Orders</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i class="fas fa-shopping-bag text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">No orders found</h3>
                <p class="text-gray-500">
                    <?php if (empty($search) && empty($status)): ?>
                        Orders for your products will appear here.
                    <?php else: ?>
                        Try adjusting your search criteria.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($orders as $order): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <!-- Order Header -->
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        Order #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        Customer: <?= htmlspecialchars($order['customer_email']) ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        Placed: <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                                    </p>
                                </div>
                                <div class="mt-2 sm:mt-0 flex items-center space-x-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php
                                        switch($order['status']) {
                                            case 'pending':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'shipped':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'delivered':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'cancelled':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                    <span class="text-lg font-semibold text-gray-900">
                                        $<?= number_format($order['total'], 2) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Details -->
                        <div class="px-6 py-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="font-medium text-gray-900 mb-2">Shipping Address</h4>
                                    <p class="text-sm text-gray-600">
                                        <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                                    </p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 mb-2">Payment & Shipping</h4>
                                    <div class="text-sm text-gray-600 space-y-1">
                                        <p><strong>Payment:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
                                        <p><strong>Shipping Cost:</strong> $<?= number_format($order['shipping_cost'], 2) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Actions -->
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex space-x-4 mb-2 sm:mb-0">
                                    <a href="order-details.php?id=<?= $order['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </a>
                                    
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <button onclick="printShippingLabel(<?= $order['id'] ?>)"
                                                class="text-green-600 hover:text-green-800 text-sm font-medium">
                                            <i class="fas fa-print mr-1"></i>Print Label
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Status Update -->
                                <div class="flex items-center space-x-2">
                                    <form method="POST" class="flex items-center space-x-2">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <select name="status" 
                                                class="text-sm border border-gray-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500">
                                            <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                            <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" 
                                                class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                            Update
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="flex justify-center mt-8">
                    <nav class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                               class="px-3 py-2 border rounded-md <?= $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        function printShippingLabel(orderId) {
            // Mock shipping label printing
            alert('Shipping label for Order #' + orderId.toString().padStart(6, '0') + ' would be printed here.\n\nIn a real application, this would integrate with shipping providers like FedEx, UPS, or USPS.');
        }
    </script>
</body>
</html>
