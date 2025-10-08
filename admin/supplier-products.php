<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$supplierId = intval($_GET['supplier_id'] ?? 0);
$success = '';
$error = '';
$setupNeeded = false;

if (!$supplierId) {
    header('Location: suppliers.php');
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_product') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO supplier_products 
                (supplier_id, supplier_sku, product_name, description, category, brand, 
                 cost_price, suggested_retail_price, available_quantity, minimum_order_quantity, 
                 weight_kg, dimensions_cm, color, size, material, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $supplierId,
                $_POST['supplier_sku'],
                $_POST['product_name'],
                $_POST['description'],
                $_POST['category'],
                $_POST['brand'],
                floatval($_POST['cost_price']),
                floatval($_POST['suggested_retail_price']),
                intval($_POST['available_quantity']),
                intval($_POST['minimum_order_quantity']),
                floatval($_POST['weight_kg']),
                $_POST['dimensions_cm'],
                $_POST['color'],
                $_POST['size'],
                $_POST['material'],
                $_POST['status']
            ]);
            
            $success = "Product added successfully.";
            
        } catch (PDOException $e) {
            $error = "Error adding product: " . $e->getMessage();
        }
    } elseif ($action === 'update_product') {
        try {
            $productId = intval($_POST['product_id']);
            
            $stmt = $pdo->prepare("
                UPDATE supplier_products SET 
                supplier_sku = ?, product_name = ?, description = ?, category = ?, brand = ?,
                cost_price = ?, suggested_retail_price = ?, available_quantity = ?, minimum_order_quantity = ?,
                weight_kg = ?, dimensions_cm = ?, color = ?, size = ?, material = ?, status = ?
                WHERE id = ? AND supplier_id = ?
            ");
            
            $stmt->execute([
                $_POST['supplier_sku'],
                $_POST['product_name'],
                $_POST['description'],
                $_POST['category'],
                $_POST['brand'],
                floatval($_POST['cost_price']),
                floatval($_POST['suggested_retail_price']),
                intval($_POST['available_quantity']),
                intval($_POST['minimum_order_quantity']),
                floatval($_POST['weight_kg']),
                $_POST['dimensions_cm'],
                $_POST['color'],
                $_POST['size'],
                $_POST['material'],
                $_POST['status'],
                $productId,
                $supplierId
            ]);
            
            $success = "Product updated successfully.";
            
        } catch (PDOException $e) {
            $error = "Error updating product: " . $e->getMessage();
        }
    } elseif ($action === 'delete_product') {
        try {
            $productId = intval($_POST['product_id']);
            
            $stmt = $pdo->prepare("DELETE FROM supplier_products WHERE id = ? AND supplier_id = ?");
            $stmt->execute([$productId, $supplierId]);
            
            $success = "Product deleted successfully.";
            
        } catch (PDOException $e) {
            $error = "Error deleting product: " . $e->getMessage();
        }
    }
}

// Get supplier information
try {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch();
    
    if (!$supplier) {
        header('Location: suppliers.php?error=Supplier not found');
        exit;
    }
} catch (PDOException $e) {
    $error = "Error loading supplier: " . $e->getMessage();
    $supplier = null;
}

// Get products with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$whereConditions = ['sp.supplier_id = ?'];
$params = [$supplierId];

if (!empty($search)) {
    $whereConditions[] = "(sp.supplier_sku LIKE ? OR sp.product_name LIKE ? OR sp.brand LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($categoryFilter)) {
    $whereConditions[] = "sp.category = ?";
    $params[] = $categoryFilter;
}

if (!empty($statusFilter)) {
    $whereConditions[] = "sp.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $whereConditions);

try {
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM supplier_products sp WHERE $whereClause";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalProducts = $stmt->fetchColumn();
    
    // Get products
    $productsQuery = "
        SELECT sp.*, p.name as linked_product_name, p.id as linked_product_id
        FROM supplier_products sp
        LEFT JOIN products p ON sp.product_id = p.id
        WHERE $whereClause
        ORDER BY sp.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($productsQuery);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get categories for filter
    $categoriesQuery = "SELECT DISTINCT category FROM supplier_products WHERE supplier_id = ? AND category IS NOT NULL ORDER BY category";
    $stmt = $pdo->prepare($categoriesQuery);
    $stmt->execute([$supplierId]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get statistics
    $statsQueries = [
        'total_products' => "SELECT COUNT(*) FROM supplier_products WHERE supplier_id = ?",
        'active_products' => "SELECT COUNT(*) FROM supplier_products WHERE supplier_id = ? AND status = 'active'",
        'total_value' => "SELECT COALESCE(SUM(cost_price * available_quantity), 0) FROM supplier_products WHERE supplier_id = ?",
        'avg_cost' => "SELECT COALESCE(AVG(cost_price), 0) FROM supplier_products WHERE supplier_id = ?"
    ];
    
    $stats = [];
    foreach ($statsQueries as $key => $query) {
        try {
            $stmt = $pdo->prepare($query);
            if ($key === 'active_products') {
                $stmt->execute([$supplierId]);
            } else {
                $stmt->execute([$supplierId]);
            }
            $stats[$key] = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $stats[$key] = 0;
        }
    }
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $error = "Supplier products table is missing. Please import the supplier inventory module schema first.";
        $setupNeeded = true;
        $products = [];
        $categories = [];
        $stats = ['total_products' => 0, 'active_products' => 0, 'total_value' => 0, 'avg_cost' => 0];
    } else {
        throw $e;
    }
}

$totalPages = ceil($totalProducts / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $supplier ? htmlspecialchars($supplier['company_name']) : 'Supplier' ?> Products - VentDepot Admin</title>
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
                    <a href="dashboard.php" class="text-lg font-semibold text-red-600 hover:text-red-700">Admin Panel</a>
                    <span class="text-gray-400">|</span>
                    <a href="suppliers.php" class="text-lg text-blue-600 hover:text-blue-700">Suppliers</a>
                    <span class="text-gray-400">|</span>
                    <span class="text-lg text-gray-600">Products</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="suppliers.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Suppliers
                    </a>
                    <?php if ($supplier): ?>
                        <a href="supplier-details.php?id=<?= $supplier['id'] ?>" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-info-circle mr-2"></i>Supplier Details
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <?php if ($supplier): ?>
                    <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($supplier['company_name']) ?></h1>
                    <p class="text-gray-600 mt-2">Supplier Products Management</p>
                <?php else: ?>
                    <h1 class="text-3xl font-bold text-gray-900">Supplier Products</h1>
                <?php endif; ?>
            </div>
            <?php if (!$setupNeeded): ?>
                <button onclick="openAddProductModal()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add Product
                </button>
            <?php endif; ?>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
                <?php if ($setupNeeded): ?>
                    <div class="mt-2">
                        <a href="../setup-supplier-module.php" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition-colors duration-200">
                            <i class="fas fa-database mr-2"></i>Setup Database Schema
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$setupNeeded): ?>
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-box text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Products</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_products']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Products</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['active_products']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <i class="fas fa-dollar-sign text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Value</p>
                        <p class="text-2xl font-bold text-gray-900">$<?= number_format($stats['total_value'], 2) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Avg Cost</p>
                        <p class="text-2xl font-bold text-gray-900">$<?= number_format($stats['avg_cost'], 2) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="supplier_id" value="<?= $supplierId ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="SKU, name, or brand..." 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="discontinued" <?= $statusFilter === 'discontinued' ? 'selected' : '' ?>>Discontinued</option>
                        <option value="out_of_stock" <?= $statusFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pricing</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Inventory</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-box text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-lg font-medium">No Products Found</p>
                                    <p class="text-sm">Add products to this supplier's catalog</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-start space-x-4">
                                            <div class="flex-shrink-0">
                                                <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-box text-gray-400 text-xl"></i>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-medium text-gray-900"><?= htmlspecialchars($product['product_name']) ?></div>
                                                <div class="text-sm text-gray-500">SKU: <?= htmlspecialchars($product['supplier_sku']) ?></div>
                                                <?php if ($product['brand']): ?>
                                                    <div class="text-sm text-gray-500">Brand: <?= htmlspecialchars($product['brand']) ?></div>
                                                <?php endif; ?>
                                                <?php if ($product['linked_product_name']): ?>
                                                    <div class="text-sm text-blue-600">
                                                        <i class="fas fa-link mr-1"></i>Linked: <?= htmlspecialchars($product['linked_product_name']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?></div>
                                        <?php if ($product['weight_kg']): ?>
                                            <div class="text-sm text-gray-500"><?= number_format($product['weight_kg'], 2) ?> kg</div>
                                        <?php endif; ?>
                                        <?php if ($product['dimensions_cm']): ?>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($product['dimensions_cm']) ?> cm</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">Cost: $<?= number_format($product['cost_price'], 2) ?></div>
                                        <?php if ($product['suggested_retail_price']): ?>
                                            <div class="text-sm text-gray-500">MSRP: $<?= number_format($product['suggested_retail_price'], 2) ?></div>
                                        <?php endif; ?>
                                        <?php if ($product['bulk_price'] && $product['bulk_quantity']): ?>
                                            <div class="text-sm text-green-600">Bulk: $<?= number_format($product['bulk_price'], 2) ?> (<?= $product['bulk_quantity'] ?>+)</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= number_format($product['available_quantity']) ?> available</div>
                                        <div class="text-sm text-gray-500">Min order: <?= number_format($product['minimum_order_quantity']) ?></div>
                                        <?php if ($product['lead_time_days']): ?>
                                            <div class="text-sm text-gray-500">Lead time: <?= $product['lead_time_days'] ?> days</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch($product['status']) {
                                                case 'active': echo 'bg-green-100 text-green-800'; break;
                                                case 'inactive': echo 'bg-gray-100 text-gray-800'; break;
                                                case 'discontinued': echo 'bg-red-100 text-red-800'; break;
                                                case 'out_of_stock': echo 'bg-yellow-100 text-yellow-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= ucfirst(str_replace('_', ' ', $product['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="editProduct(<?= htmlspecialchars(json_encode($product)) ?>)" 
                                                    class="text-blue-600 hover:text-blue-900" title="Edit Product">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteProduct(<?= $product['id'] ?>, '<?= htmlspecialchars($product['product_name']) ?>')" 
                                                    class="text-red-600 hover:text-red-900" title="Delete Product">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-between items-center mt-6">
                <div class="text-sm text-gray-700">
                    Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $perPage, $totalProducts)) ?> of <?= number_format($totalProducts) ?> results
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?supplier_id=<?= $supplierId ?>&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&status=<?= urlencode($statusFilter) ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm bg-white hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?supplier_id=<?= $supplierId ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&status=<?= urlencode($statusFilter) ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm <?= $i === $page ? 'bg-green-600 text-white' : 'bg-white hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?supplier_id=<?= $supplierId ?>&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&status=<?= urlencode($statusFilter) ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm bg-white hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Add New Product</h3>
                </div>

                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="add_product">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Information -->
                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Basic Information</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Supplier SKU *</label>
                            <input type="text" name="supplier_sku" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                            <input type="text" name="product_name" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <input type="text" name="category"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Brand</label>
                            <input type="text" name="brand"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="3"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500"></textarea>
                        </div>

                        <!-- Pricing -->
                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4 mt-6">Pricing Information</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cost Price *</label>
                            <input type="number" name="cost_price" step="0.01" min="0" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Suggested Retail Price</label>
                            <input type="number" name="suggested_retail_price" step="0.01" min="0"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <!-- Inventory -->
                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4 mt-6">Inventory</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Available Quantity</label>
                            <input type="number" name="available_quantity" min="0" value="0"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Order Quantity</label>
                            <input type="number" name="minimum_order_quantity" min="1" value="1"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <!-- Specifications -->
                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4 mt-6">Specifications</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Weight (kg)</label>
                            <input type="number" name="weight_kg" step="0.001" min="0"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Dimensions (LxWxH cm)</label>
                            <input type="text" name="dimensions_cm" placeholder="e.g., 10x20x5"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                            <input type="text" name="color"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Size</label>
                            <input type="text" name="size"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Material</label>
                            <input type="text" name="material"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="discontinued">Discontinued</option>
                                <option value="out_of_stock">Out of Stock</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 mt-6 pt-6 border-t border-gray-200">
                        <button type="button" onclick="closeAddProductModal()"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                            Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editProductModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Edit Product</h3>
                </div>

                <form method="POST" class="p-6" id="editProductForm">
                    <input type="hidden" name="action" value="update_product">
                    <input type="hidden" name="product_id" id="editProductId">

                    <!-- Form fields identical to add form with ids for editing -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Basic Information</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Supplier SKU *</label>
                            <input type="text" name="supplier_sku" id="editSupplierSku" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                            <input type="text" name="product_name" id="editProductName" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <input type="text" name="category" id="editCategory"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Brand</label>
                            <input type="text" name="brand" id="editBrand"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" id="editDescription" rows="3"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500"></textarea>
                        </div>

                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4 mt-6">Pricing Information</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cost Price *</label>
                            <input type="number" name="cost_price" id="editCostPrice" step="0.01" min="0" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Suggested Retail Price</label>
                            <input type="number" name="suggested_retail_price" id="editSuggestedPrice" step="0.01" min="0"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4 mt-6">Inventory</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Available Quantity</label>
                            <input type="number" name="available_quantity" id="editAvailableQuantity" min="0"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Order Quantity</label>
                            <input type="number" name="minimum_order_quantity" id="editMinOrderQuantity" min="1"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4 mt-6">Specifications</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Weight (kg)</label>
                            <input type="number" name="weight_kg" id="editWeight" step="0.001" min="0"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Dimensions (LxWxH cm)</label>
                            <input type="text" name="dimensions_cm" id="editDimensions"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                            <input type="text" name="color" id="editColor"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Size</label>
                            <input type="text" name="size" id="editSize"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Material</label>
                            <input type="text" name="material" id="editMaterial"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" id="editStatus" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="discontinued">Discontinued</option>
                                <option value="out_of_stock">Out of Stock</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 mt-6 pt-6 border-t border-gray-200">
                        <button type="button" onclick="closeEditProductModal()"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                            Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" id="deleteProductId">
                    
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Delete Product</h3>
                        <button type="button" onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-gray-700">Are you sure you want to delete <strong id="deleteProductName"></strong>?</p>
                        <p class="text-red-600 text-sm mt-2">This action cannot be undone.</p>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="closeDeleteModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            Delete Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddProductModal() {
            document.getElementById('addProductModal').classList.remove('hidden');
        }

        function closeAddProductModal() {
            document.getElementById('addProductModal').classList.add('hidden');
        }

        function editProduct(product) {
            // Populate form fields
            document.getElementById('editProductId').value = product.id;
            document.getElementById('editSupplierSku').value = product.supplier_sku || '';
            document.getElementById('editProductName').value = product.product_name || '';
            document.getElementById('editCategory').value = product.category || '';
            document.getElementById('editBrand').value = product.brand || '';
            document.getElementById('editDescription').value = product.description || '';
            document.getElementById('editCostPrice').value = product.cost_price || '';
            document.getElementById('editSuggestedPrice').value = product.suggested_retail_price || '';
            document.getElementById('editAvailableQuantity').value = product.available_quantity || '';
            document.getElementById('editMinOrderQuantity').value = product.minimum_order_quantity || '';
            document.getElementById('editWeight').value = product.weight_kg || '';
            document.getElementById('editDimensions').value = product.dimensions_cm || '';
            document.getElementById('editColor').value = product.color || '';
            document.getElementById('editSize').value = product.size || '';
            document.getElementById('editMaterial').value = product.material || '';
            document.getElementById('editStatus').value = product.status || 'active';
            
            document.getElementById('editProductModal').classList.remove('hidden');
        }

        function closeEditProductModal() {
            document.getElementById('editProductModal').classList.add('hidden');
        }

        function deleteProduct(productId, productName) {
            document.getElementById('deleteProductId').value = productId;
            document.getElementById('deleteProductName').textContent = productName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const modals = ['addProductModal', 'editProductModal', 'deleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        });

        // ESC key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = ['addProductModal', 'editProductModal', 'deleteModal'];
                modals.forEach(modalId => {
                    document.getElementById(modalId).classList.add('hidden');
                });
            }
        });
    </script>

</body>
</html>