<?php
require_once '../config/database.php';
require_once '../includes/security.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$carouselId = intval($_GET['id'] ?? 0);
$productId = intval($_GET['product_id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    
    if ($action === 'add_carousel' || $action === 'edit_carousel') {
        $name = trim($_POST['name'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $maxProducts = intval($_POST['max_products'] ?? 10);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        
        // Validate required fields
        if (empty($name)) {
            $error = 'Carousel name is required.';
        } else {
            try {
                if ($action === 'add_carousel') {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_carousels 
                        (name, title, description, max_products, is_active, sort_order)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $name, $title, $description, $maxProducts, $isActive, $sortOrder
                    ]);
                    $success = 'Product carousel added successfully!';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE product_carousels 
                        SET name = ?, title = ?, description = ?, max_products = ?, 
                            is_active = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name, $title, $description, $maxProducts, $isActive, $sortOrder, $carouselId
                    ]);
                    $success = 'Product carousel updated successfully!';
                }
            } catch (Exception $e) {
                $error = 'Error saving product carousel: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'add_product') {
        $productId = intval($_POST['product_id'] ?? 0);
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        
        if (empty($productId) || empty($carouselId)) {
            $error = 'Product and carousel are required.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO carousel_products 
                    (carousel_id, product_id, sort_order, is_featured)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$carouselId, $productId, $sortOrder, $isFeatured]);
                $success = 'Product added to carousel successfully!';
            } catch (Exception $e) {
                $error = 'Error adding product to carousel: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_product') {
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        
        if (empty($productId) || empty($carouselId)) {
            $error = 'Product and carousel are required.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE carousel_products 
                    SET sort_order = ?, is_featured = ?
                    WHERE carousel_id = ? AND product_id = ?
                ");
                $stmt->execute([$sortOrder, $isFeatured, $carouselId, $productId]);
                $success = 'Product updated successfully!';
            } catch (Exception $e) {
                $error = 'Error updating product: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_carousel') {
        try {
            $stmt = $pdo->prepare("DELETE FROM product_carousels WHERE id = ?");
            $stmt->execute([$carouselId]);
            $success = 'Product carousel deleted successfully!';
        } catch (Exception $e) {
            $error = 'Error deleting product carousel: ' . $e->getMessage();
        }
    } elseif ($action === 'remove_product') {
        if (empty($productId) || empty($carouselId)) {
            $error = 'Product and carousel are required.';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM carousel_products WHERE carousel_id = ? AND product_id = ?");
                $stmt->execute([$carouselId, $productId]);
                $success = 'Product removed from carousel successfully!';
            } catch (Exception $e) {
                $error = 'Error removing product from carousel: ' . $e->getMessage();
            }
        }
    }
}

// Get carousel for edit
$carousel = null;
if (($action === 'edit_carousel' || $action === 'manage_products') && $carouselId) {
    $stmt = $pdo->prepare("SELECT * FROM product_carousels WHERE id = ?");
    $stmt->execute([$carouselId]);
    $carousel = $stmt->fetch();
    
    if (!$carousel) {
        $error = 'Product carousel not found.';
        $action = 'list';
    }
}

// Get all carousels for listing
$carousels = [];
if ($action === 'list') {
    $stmt = $pdo->query("SELECT *, (SELECT COUNT(*) FROM carousel_products WHERE carousel_id = product_carousels.id) as product_count FROM product_carousels ORDER BY sort_order, created_at DESC");
    $carousels = $stmt->fetchAll();
}

// Get products for a carousel
$carouselProducts = [];
$availableProducts = [];
if ($action === 'manage_products' && $carouselId) {
    // Get products in this carousel
    $stmt = $pdo->prepare("
        SELECT cp.*, p.name as product_name, p.image_url, p.price
        FROM carousel_products cp
        JOIN products p ON cp.product_id = p.id
        WHERE cp.carousel_id = ?
        ORDER BY cp.sort_order, cp.created_at
    ");
    $stmt->execute([$carouselId]);
    $carouselProducts = $stmt->fetchAll();
    
    // Get available products (not in this carousel)
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.image_url, p.price
        FROM products p
        WHERE p.id NOT IN (SELECT product_id FROM carousel_products WHERE carousel_id = ?)
        ORDER BY p.name
        LIMIT 50
    ");
    $stmt->execute([$carouselId]);
    $availableProducts = $stmt->fetchAll();
}

// Get all products for dropdown (if needed)
$allProducts = [];
if ($action === 'add_product' || $action === 'manage_products') {
    $stmt = $pdo->query("SELECT id, name, image_url, price FROM products ORDER BY name LIMIT 100");
    $allProducts = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Carousel Management - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    <?php if ($action === 'add_carousel'): ?>
                        Add New Product Carousel
                    <?php elseif ($action === 'edit_carousel'): ?>
                        Edit Product Carousel
                    <?php elseif ($action === 'manage_products'): ?>
                        Manage Products: <?= htmlspecialchars($carousel['name'] ?? '') ?>
                    <?php else: ?>
                        Product Carousel Management
                    <?php endif; ?>
                </h1>
                <p class="text-gray-600 mt-2">
                    <?php if ($action === 'add_carousel'): ?>
                        Create a new product carousel for your frontend
                    <?php elseif ($action === 'edit_carousel'): ?>
                        Update product carousel details
                    <?php elseif ($action === 'manage_products'): ?>
                        Add or remove products from this carousel
                    <?php else: ?>
                        Manage all product carousels
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex space-x-3">
                <?php if ($action === 'list'): ?>
                    <a href="cms-products.php?action=add_carousel" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Add Carousel
                    </a>
                <?php endif; ?>
                <a href="cms-dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to CMS
                </a>
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

        <?php if ($action === 'list'): ?>
            <!-- Carousel List -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">All Product Carousels</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Carousel</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Products</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($carousels)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                        No product carousels found. <a href="cms-products.php?action=add_carousel" class="text-blue-600 hover:text-blue-800">Add your first carousel</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($carousels as $c): ?>
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($c['name']) ?></div>
                                            <?php if ($c['title']): ?>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($c['title']) ?></div>
                                            <?php endif; ?>
                                            <?php if ($c['description']): ?>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars(substr($c['description'], 0, 50)) ?>...</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <?= $c['product_count'] ?> products
                                            (max: <?= $c['max_products'] ?>)
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($c['is_active']): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium">
                                            <a href="cms-products.php?action=manage_products&id=<?= $c['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-cog"></i> Manage
                                            </a>
                                            <a href="cms-products.php?action=edit_carousel&id=<?= $c['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="cms-products.php?action=delete_carousel&id=<?= $c['id'] ?>" 
                                               onclick="return confirm('Are you sure you want to delete this carousel? All products will be removed from it.')" 
                                               class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($action === 'add_carousel' || $action === 'edit_carousel'): ?>
            <!-- Add/Edit Carousel Form -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <?php if ($action === 'add_carousel'): ?>
                            Carousel Details
                        <?php else: ?>
                            Edit Carousel
                        <?php endif; ?>
                    </h2>
                </div>
                <form method="POST" class="p-6">
                    <?= Security::getCSRFInput() ?>
                    <input type="hidden" name="action" value="<?= $action ?>">
                    <?php if ($action === 'edit_carousel'): ?>
                        <input type="hidden" name="id" value="<?= $carousel['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($carousel['name'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($carousel['title'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($carousel['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Max Products</label>
                            <input type="number" name="max_products" value="<?= $carousel['max_products'] ?? 10 ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" value="<?= $carousel['sort_order'] ?? 0 ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <div class="flex items-center">
                                <input type="checkbox" name="is_active" id="is_active" <?= (isset($carousel['is_active']) && $carousel['is_active']) ? 'checked' : '' ?> 
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <a href="cms-products.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                            Cancel
                        </a>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <?php if ($action === 'add_carousel'): ?>
                                Add Carousel
                            <?php else: ?>
                                Update Carousel
                            <?php endif; ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif ($action === 'manage_products'): ?>
            <!-- Manage Products in Carousel -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Products in Carousel -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">Products in Carousel</h2>
                    </div>
                    <div class="p-6">
                        <?php if (empty($carouselProducts)): ?>
                            <p class="text-gray-500 text-center py-4">No products in this carousel yet.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($carouselProducts as $cp): ?>
                                    <form method="POST" class="flex items-center justify-between p-3 border border-gray-200 rounded">
                                        <?= Security::getCSRFInput() ?>
                                        <input type="hidden" name="action" value="update_product">
                                        <input type="hidden" name="id" value="<?= $carouselId ?>">
                                        <input type="hidden" name="product_id" value="<?= $cp['product_id'] ?>">
                                        
                                        <div class="flex items-center">
                                            <img src="<?= htmlspecialchars($cp['image_url'] ?? 'https://via.placeholder.com/50x50') ?>" 
                                                 alt="<?= htmlspecialchars($cp['product_name']) ?>"
                                                 class="w-12 h-12 object-cover rounded">
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($cp['product_name']) ?></div>
                                                <div class="text-sm text-gray-500">$<?= number_format($cp['price'], 2) ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <input type="number" name="sort_order" value="<?= $cp['sort_order'] ?>" 
                                                   class="w-16 px-2 py-1 text-sm border border-gray-300 rounded">
                                            <label class="flex items-center">
                                                <input type="checkbox" name="is_featured" <?= $cp['is_featured'] ? 'checked' : '' ?> 
                                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <span class="ml-1 text-sm text-gray-600">Featured</span>
                                            </label>
                                            <button type="submit" class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-save"></i>
                                            </button>
                                            <a href="cms-products.php?action=remove_product&id=<?= $carouselId ?>&product_id=<?= $cp['product_id'] ?>" 
                                               onclick="return confirm('Remove this product from the carousel?')"
                                               class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        </div>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Add Products to Carousel -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">Add Products to Carousel</h2>
                    </div>
                    <form method="POST" class="p-6">
                        <?= Security::getCSRFInput() ?>
                        <input type="hidden" name="action" value="add_product">
                        <input type="hidden" name="id" value="<?= $carouselId ?>">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Product</label>
                            <select name="product_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Choose a product</option>
                                <?php foreach ($availableProducts as $product): ?>
                                    <option value="<?= $product['id'] ?>">
                                        <?= htmlspecialchars($product['name']) ?> - $<?= number_format($product['price'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" value="0" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_featured" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Featured Product</span>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            Add Product to Carousel
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="mt-6">
                <a href="cms-products.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Carousels
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>