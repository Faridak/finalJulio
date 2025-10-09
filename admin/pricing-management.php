<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_price' && isset($_POST['product_id'])) {
        $productId = intval($_POST['product_id']);
        $newPrice = floatval($_POST['new_price']);
        $reason = $_POST['reason'] ?? 'Manual price update';
        
        try {
            // Update the product price in the products table
            $stmt = $pdo->prepare("UPDATE products SET price = ? WHERE id = ?");
            $stmt->execute([$newPrice, $productId]);
            
            // Log price change in history
            $stmt = $pdo->prepare("INSERT INTO price_history (product_id, old_price, new_price, reason, changed_by) 
                                  SELECT ?, price, ?, ?, ? FROM products WHERE id = ?");
            // Get old price for history
            $stmtOld = $pdo->prepare("SELECT price FROM products WHERE id = ?");
            $stmtOld->execute([$productId]);
            $oldPrice = $stmtOld->fetchColumn();
            
            $stmt->execute([$productId, $oldPrice, $newPrice, $reason, $_SESSION['user_id'], $productId]);
            
            $success = "Price updated successfully!";
        } catch (Exception $e) {
            $error = "Error updating price: " . $e->getMessage();
        }
    } elseif ($action === 'create_discount') {
        $productId = intval($_POST['product_id']);
        $discountType = $_POST['discount_type'];
        $discountValue = floatval($_POST['discount_value']);
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO product_discounts (product_id, discount_type, discount_value, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$productId, $discountType, $discountValue, $startDate, $endDate]);
            $success = "Discount created successfully!";
        } catch (Exception $e) {
            $error = "Error creating discount: " . $e->getMessage();
        }
    } elseif ($action === 'create_promotion') {
        $name = $_POST['promotion_name'];
        $description = $_POST['promotion_description'];
        $promotionType = $_POST['promotion_type'];
        $discountType = $_POST['discount_type'];
        $discountValue = floatval($_POST['discount_value']);
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $productIds = $_POST['product_ids'] ?? [];
        
        try {
            $pdo->beginTransaction();
            
            // Insert promotion
            $stmt = $pdo->prepare("INSERT INTO product_promotions (name, description, promotion_type, discount_type, discount_value, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $promotionType, $discountType, $discountValue, $startDate, $endDate]);
            $promotionId = $pdo->lastInsertId();
            
            // Link products to promotion
            if (!empty($productIds)) {
                $stmt = $pdo->prepare("INSERT INTO promotion_products (promotion_id, product_id) VALUES (?, ?)");
                foreach ($productIds as $productId) {
                    $stmt->execute([$promotionId, intval($productId)]);
                }
            }
            
            $pdo->commit();
            $success = "Promotion created successfully!";
        } catch (Exception $e) {
            $pdo->rollback();
            $error = "Error creating promotion: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$salesFilter = $_GET['sales_filter'] ?? '';

// Build query for products
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $whereConditions[] = "p.category = ?";
    $params[] = $category;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get products with sales data
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$productsQuery = "
    SELECT p.*, 
           COALESCE(sales.last_sale_date, '1970-01-01') as last_sale_date,
           COALESCE(sales.total_sales_90_days, 0) as total_sales_90_days,
           COALESCE(discounts.current_discount, 0) as current_discount,
           COALESCE(discounts.discount_type, '') as discount_type
    FROM products p
    LEFT JOIN (
        SELECT oi.product_id,
               MAX(o.created_at) as last_sale_date,
               SUM(CASE 
                   WHEN o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) 
                   THEN oi.quantity 
                   ELSE 0 
               END) as total_sales_90_days
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        GROUP BY oi.product_id
    ) sales ON p.id = sales.product_id
    LEFT JOIN (
        SELECT product_id,
               discount_value as current_discount,
               discount_type
        FROM product_discounts
        WHERE is_active = 1 
        AND start_date <= NOW() 
        AND end_date >= NOW()
    ) discounts ON p.id = discounts.product_id
    $whereClause
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
";

$products = $pdo->prepare($productsQuery);
$products->execute($params);
$products = $products->fetchAll();

// Get total count for pagination
$countQuery = "SELECT COUNT(*) FROM products p $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $limit);

// Get categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Get promotions for display
$promotions = $pdo->query("
    SELECT pp.*, COUNT(prp.product_id) as product_count 
    FROM product_promotions pp 
    LEFT JOIN promotion_products prp ON pp.id = prp.promotion_id 
    GROUP BY pp.id 
    ORDER BY pp.created_at DESC 
    LIMIT 10
")->fetchAll();

// Get statistics
$stats = [
    'total_products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'products_no_sales_90' => $pdo->query("
        SELECT COUNT(*) FROM products p
        LEFT JOIN (
            SELECT oi.product_id
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY oi.product_id
        ) recent_sales ON p.id = recent_sales.product_id
        WHERE recent_sales.product_id IS NULL
    ")->fetchColumn(),
    'active_promotions' => $pdo->query("SELECT COUNT(*) FROM product_promotions WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()")->fetchColumn(),
    'active_discounts' => $pdo->query("SELECT COUNT(*) FROM product_discounts WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price Management - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .alert-product {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
        }
        .promotion-badge {
            background-color: #dbeafe;
            color: #1d4ed8;
        }
        .discount-badge {
            background-color: #dcfce7;
            color: #166534;
        }
    </style>
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
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Hello, <?= htmlspecialchars($_SESSION['user_email']) ?></span>
                    <a href="../logout.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Price Management</h1>
                <p class="text-gray-600 mt-2">Manage product pricing, discounts, and promotions</p>
            </div>
            <div class="flex space-x-3">
                <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
                <button onclick="openCreatePromotionModal()" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                    <i class="fas fa-plus mr-2"></i>Create Promotion
                </button>
            </div>
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

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-box text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Products</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_products']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">No Sales (90 days)</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['products_no_sales_90']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tag text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Promotions</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['active_promotions']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-percent text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Discounts</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['active_discounts']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Product name or description"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sales Filter</label>
                    <select name="sales_filter" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Products</option>
                        <option value="no_sales_90" <?= $salesFilter === 'no_sales_90' ? 'selected' : '' ?>>No Sales (90 days)</option>
                        <option value="low_sales" <?= $salesFilter === 'low_sales' ? 'selected' : '' ?>>Low Sales</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Active Promotions -->
        <?php if (!empty($promotions)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-900">Active Promotions</h2>
                <a href="#" class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($promotions as $promotion): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex justify-between items-start">
                        <h3 class="font-medium text-gray-900"><?= htmlspecialchars($promotion['name']) ?></h3>
                        <span class="text-xs px-2 py-1 rounded-full <?= $promotion['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= $promotion['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars(substr($promotion['description'], 0, 60)) ?><?= strlen($promotion['description']) > 60 ? '...' : '' ?></p>
                    <div class="mt-2 flex justify-between items-center">
                        <span class="text-sm font-medium text-purple-600">
                            <?= $promotion['promotion_type'] === 'percentage' ? $promotion['discount_value'] . '%' : '$' . $promotion['discount_value'] ?>
                        </span>
                        <span class="text-xs text-gray-500"><?= $promotion['product_count'] ?> products</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Products Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Products (<?= number_format($totalProducts) ?> total)</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sales (90 days)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Sale</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                    No products found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <?php 
                                $noSales90Days = ($product['last_sale_date'] == '1970-01-01' || strtotime($product['last_sale_date']) < strtotime('-90 days'));
                                $alertClass = $noSales90Days ? 'alert-product' : '';
                                ?>
                                <tr class="<?= $alertClass ?>">
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
                                                <div class="text-sm text-gray-500">ID: <?= $product['id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="font-medium">$<?= number_format($product['price'], 2) ?></div>
                                        <?php if ($product['current_discount'] > 0): ?>
                                            <div class="text-xs text-green-600">
                                                <?php if ($product['discount_type'] === 'percentage'): ?>
                                                    <?= $product['current_discount'] ?>% off
                                                <?php else: ?>
                                                    $<?= $product['current_discount'] ?> off
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="<?= $product['total_sales_90_days'] == 0 ? 'text-red-600 font-medium' : '' ?>">
                                            <?= number_format($product['total_sales_90_days']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($product['last_sale_date'] == '1970-01-01'): ?>
                                            <span class="text-red-600">Never</span>
                                        <?php else: ?>
                                            <?= date('M j, Y', strtotime($product['last_sale_date'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($noSales90Days): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>No Sales
                                            </span>
                                        <?php elseif ($product['total_sales_90_days'] < 5): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                Low Sales
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="openPriceModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', <?= $product['price'] ?>)" 
                                                    class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-edit"></i> Price
                                            </button>
                                            <button onclick="openDiscountModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')" 
                                                    class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-percent"></i> Discount
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                               class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> to 
                                <span class="font-medium"><?= min($page * $limit, $totalProducts) ?></span> of 
                                <span class="font-medium"><?= number_format($totalProducts) ?></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border text-sm font-medium
                                              <?= $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Price Update Modal -->
    <div id="priceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Update Price</h3>
            </div>
            <form method="POST" class="px-6 py-4">
                <input type="hidden" name="action" value="update_price">
                <input type="hidden" name="product_id" id="priceProductId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                    <input type="text" id="priceProductName" class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100" disabled>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Price</label>
                    <input type="text" id="currentPrice" class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100" disabled>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Price</label>
                    <input type="number" name="new_price" id="newPrice" step="0.01" min="0" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Change</label>
                    <select name="reason" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="Manual price update">Manual price update</option>
                        <option value="Seasonal adjustment">Seasonal adjustment</option>
                        <option value="Competitor pricing">Competitor pricing</option>
                        <option value="Clearance sale">Clearance sale</option>
                        <option value="Promotional pricing">Promotional pricing</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closePriceModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Update Price
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Discount Modal -->
    <div id="discountModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Create Discount</h3>
            </div>
            <form method="POST" class="px-6 py-4">
                <input type="hidden" name="action" value="create_discount">
                <input type="hidden" name="product_id" id="discountProductId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                    <input type="text" id="discountProductName" class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100" disabled>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Discount Type</label>
                    <select name="discount_type" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="percentage">Percentage</option>
                        <option value="fixed_amount">Fixed Amount</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Discount Value</label>
                    <input type="number" name="discount_value" step="0.01" min="0" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDiscountModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-white bg-green-600 rounded-md hover:bg-green-700">
                        Create Discount
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Promotion Modal -->
    <div id="promotionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-screen overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Create Promotion</h3>
            </div>
            <form method="POST" class="px-6 py-4">
                <input type="hidden" name="action" value="create_promotion">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Promotion Name</label>
                        <input type="text" name="promotion_name" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Promotion Type</label>
                        <select name="promotion_type" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <option value="bulk">Bulk Discount</option>
                            <option value="bundle">Bundle Deal</option>
                            <option value="seasonal">Seasonal</option>
                            <option value="holiday">Holiday</option>
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="promotion_description" rows="2"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Discount Type</label>
                        <select name="discount_type" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <option value="percentage">Percentage</option>
                            <option value="fixed_amount">Fixed Amount</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Discount Value</label>
                        <input type="number" name="discount_value" step="0.01" min="0" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Products</label>
                        <select multiple name="product_ids[]" size="3"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closePromotionModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-white bg-purple-600 rounded-md hover:bg-purple-700">
                        Create Promotion
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Price Modal Functions
        function openPriceModal(productId, productName, currentPrice) {
            document.getElementById('priceProductId').value = productId;
            document.getElementById('priceProductName').value = productName;
            document.getElementById('currentPrice').value = '$' + currentPrice.toFixed(2);
            document.getElementById('newPrice').value = currentPrice;
            document.getElementById('priceModal').classList.remove('hidden');
            document.getElementById('priceModal').classList.add('flex');
        }
        
        function closePriceModal() {
            document.getElementById('priceModal').classList.add('hidden');
            document.getElementById('priceModal').classList.remove('flex');
        }
        
        // Discount Modal Functions
        function openDiscountModal(productId, productName) {
            document.getElementById('discountProductId').value = productId;
            document.getElementById('discountProductName').value = productName;
            document.getElementById('discountModal').classList.remove('hidden');
            document.getElementById('discountModal').classList.add('flex');
        }
        
        function closeDiscountModal() {
            document.getElementById('discountModal').classList.add('hidden');
            document.getElementById('discountModal').classList.remove('flex');
        }
        
        // Promotion Modal Functions
        function openCreatePromotionModal() {
            document.getElementById('promotionModal').classList.remove('hidden');
            document.getElementById('promotionModal').classList.add('flex');
        }
        
        function closePromotionModal() {
            document.getElementById('promotionModal').classList.add('hidden');
            document.getElementById('promotionModal').classList.remove('flex');
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.id === 'priceModal') {
                closePriceModal();
            } else if (event.target.id === 'discountModal') {
                closeDiscountModal();
            } else if (event.target.id === 'promotionModal') {
                closePromotionModal();
            }
        }
    </script>
</body>
</html>