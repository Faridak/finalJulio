<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$supplierId = intval($_GET['id'] ?? 0);
$success = '';
$error = '';

if (!$supplierId) {
    header('Location: suppliers.php');
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_supplier') {
        try {
            $updateFields = [
                'company_name' => $_POST['company_name'],
                'contact_person' => $_POST['contact_person'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'website' => $_POST['website'],
                'address_line1' => $_POST['address_line1'],
                'address_line2' => $_POST['address_line2'],
                'city' => $_POST['city'],
                'state' => $_POST['state'],
                'postal_code' => $_POST['postal_code'],
                'country_code' => $_POST['country_code'],
                'payment_terms' => $_POST['payment_terms'],
                'lead_time_days' => intval($_POST['lead_time_days']),
                'minimum_order_amount' => floatval($_POST['minimum_order_amount']),
                'notes' => $_POST['notes']
            ];
            
            $sql = "UPDATE suppliers SET " . 
                   implode(' = ?, ', array_keys($updateFields)) . " = ? WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $params = array_values($updateFields);
            $params[] = $supplierId;
            $stmt->execute($params);
            
            $success = "Supplier information updated successfully.";
            
        } catch (PDOException $e) {
            $error = "Error updating supplier: " . $e->getMessage();
        }
    }
}

try {
    // Get supplier details
    $supplierQuery = "
        SELECT s.*, c.name as country_name 
        FROM suppliers s
        LEFT JOIN countries c ON s.country_code = c.code
        WHERE s.id = ?
    ";
    $stmt = $pdo->prepare($supplierQuery);
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch();
    
    if (!$supplier) {
        header('Location: suppliers.php?error=Supplier not found');
        exit;
    }
    
    // Get supplier statistics
    $stats = [];
    
    // Count of supplier products
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM supplier_products WHERE supplier_id = ?");
        $stmt->execute([$supplierId]);
        $stats['product_count'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['product_count'] = 'N/A';
    }
    
    // Count of purchase orders
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ?");
        $stmt->execute([$supplierId]);
        $stats['purchase_order_count'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['purchase_order_count'] = 'N/A';
    }
    
    // Total purchases value
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_orders WHERE supplier_id = ? AND status != 'cancelled'");
        $stmt->execute([$supplierId]);
        $stats['total_purchases'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['total_purchases'] = 0;
    }
    
    // Recent products
    try {
        $stmt = $pdo->prepare("
            SELECT sp.*, p.name as linked_product_name
            FROM supplier_products sp
            LEFT JOIN products p ON sp.product_id = p.id
            WHERE sp.supplier_id = ?
            ORDER BY sp.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$supplierId]);
        $recentProducts = $stmt->fetchAll();
    } catch (PDOException $e) {
        $recentProducts = [];
    }
    
    // Recent purchase orders
    try {
        $stmt = $pdo->prepare("
            SELECT po.*, il.location_name
            FROM purchase_orders po
            LEFT JOIN inventory_locations il ON po.location_id = il.id
            WHERE po.supplier_id = ?
            ORDER BY po.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$supplierId]);
        $recentOrders = $stmt->fetchAll();
    } catch (PDOException $e) {
        $recentOrders = [];
    }
    
    // Get countries for dropdown
    $countries = $pdo->query("SELECT code, name FROM countries ORDER BY name")->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error loading supplier details: " . $e->getMessage();
    $supplier = null;
    $stats = [];
    $recentProducts = [];
    $recentOrders = [];
    $countries = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $supplier ? htmlspecialchars($supplier['company_name']) : 'Supplier' ?> - Details</title>
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
                    <a href="suppliers.php" class="text-lg text-blue-600 hover:text-blue-700">Suppliers</a>
                    <span class="text-gray-400">|</span>
                    <span class="text-lg text-gray-600">Supplier Details</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="suppliers.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Suppliers
                    </a>
                    <button onclick="window.close()" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                        <i class="fas fa-times mr-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($supplier): ?>
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-start">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-truck text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($supplier['company_name']) ?></h1>
                            <p class="text-gray-600">Supplier Code: <?= htmlspecialchars($supplier['supplier_code']) ?></p>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-2
                                <?php
                                switch($supplier['status']) {
                                    case 'active': echo 'bg-green-100 text-green-800'; break;
                                    case 'inactive': echo 'bg-gray-100 text-gray-800'; break;
                                    case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'suspended': echo 'bg-red-100 text-red-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?= ucfirst($supplier['status']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <?php if ($supplier['email']): ?>
                            <a href="mailto:<?= htmlspecialchars($supplier['email']) ?>" 
                               class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                <i class="fas fa-envelope mr-2"></i>Email
                            </a>
                        <?php endif; ?>
                        <?php if ($supplier['phone']): ?>
                            <a href="tel:<?= htmlspecialchars($supplier['phone']) ?>" 
                               class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                <i class="fas fa-phone mr-2"></i>Call
                            </a>
                        <?php endif; ?>
                        <button onclick="openEditModal()" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                            <i class="fas fa-edit mr-2"></i>Edit
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-box text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Products</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $stats['product_count'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-file-invoice text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Purchase Orders</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $stats['purchase_order_count'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Purchases</p>
                            <p class="text-2xl font-bold text-gray-900">$<?= number_format($stats['total_purchases'], 2) ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100">
                            <i class="fas fa-star text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Quality Rating</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($supplier['quality_rating'], 1) ?>/5</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Contact Information -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-address-card mr-2 text-blue-600"></i>Contact Information
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Contact Person:</span>
                            <span class="font-medium"><?= htmlspecialchars($supplier['contact_person']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Email:</span>
                            <a href="mailto:<?= htmlspecialchars($supplier['email']) ?>" 
                               class="font-medium text-blue-600 hover:text-blue-800"><?= htmlspecialchars($supplier['email']) ?></a>
                        </div>
                        <?php if ($supplier['phone']): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Phone:</span>
                                <a href="tel:<?= htmlspecialchars($supplier['phone']) ?>" 
                                   class="font-medium text-blue-600 hover:text-blue-800"><?= htmlspecialchars($supplier['phone']) ?></a>
                            </div>
                        <?php endif; ?>
                        <?php if ($supplier['website']): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Website:</span>
                                <a href="<?= htmlspecialchars($supplier['website']) ?>" target="_blank" 
                                   class="font-medium text-blue-600 hover:text-blue-800"><?= htmlspecialchars($supplier['website']) ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-map-marker-alt mr-2 text-red-600"></i>Address
                    </h3>
                    <div class="text-gray-700">
                        <?php if ($supplier['address_line1']): ?>
                            <div><?= htmlspecialchars($supplier['address_line1']) ?></div>
                        <?php endif; ?>
                        <?php if ($supplier['address_line2']): ?>
                            <div><?= htmlspecialchars($supplier['address_line2']) ?></div>
                        <?php endif; ?>
                        <?php if ($supplier['city'] || $supplier['state'] || $supplier['postal_code']): ?>
                            <div>
                                <?= htmlspecialchars($supplier['city']) ?>
                                <?= $supplier['state'] ? ', ' . htmlspecialchars($supplier['state']) : '' ?>
                                <?= $supplier['postal_code'] ? ' ' . htmlspecialchars($supplier['postal_code']) : '' ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($supplier['country_name']): ?>
                            <div><?= htmlspecialchars($supplier['country_name']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Business Terms and Performance -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Business Terms -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-handshake mr-2 text-green-600"></i>Business Terms
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Payment Terms:</span>
                            <span class="font-medium"><?= ucfirst(str_replace('_', ' ', $supplier['payment_terms'])) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Lead Time:</span>
                            <span class="font-medium"><?= $supplier['lead_time_days'] ?> days</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Minimum Order:</span>
                            <span class="font-medium">$<?= number_format($supplier['minimum_order_amount'], 2) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Currency:</span>
                            <span class="font-medium"><?= htmlspecialchars($supplier['currency_code']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-chart-line mr-2 text-purple-600"></i>Performance Metrics
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Quality Rating:</span>
                            <div class="flex items-center">
                                <span class="font-medium mr-2"><?= number_format($supplier['quality_rating'], 1) ?>/5</span>
                                <div class="flex text-yellow-400">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= $supplier['quality_rating'] ? '' : 'text-gray-300' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Delivery Rating:</span>
                            <div class="flex items-center">
                                <span class="font-medium mr-2"><?= number_format($supplier['delivery_rating'], 1) ?>/5</span>
                                <div class="flex text-yellow-400">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= $supplier['delivery_rating'] ? '' : 'text-gray-300' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Preferred Supplier:</span>
                            <span class="font-medium">
                                <?php if ($supplier['preferred_supplier']): ?>
                                    <i class="fas fa-check text-green-600"></i> Yes
                                <?php else: ?>
                                    <i class="fas fa-times text-red-600"></i> No
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Member Since:</span>
                            <span class="font-medium"><?= date('M Y', strtotime($supplier['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-bolt mr-2 text-yellow-600"></i>Quick Actions
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="supplier-products.php?supplier_id=<?= $supplier['id'] ?>" 
                       class="bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg p-4 text-center transition-colors">
                        <i class="fas fa-box text-blue-600 text-2xl mb-2"></i>
                        <div class="font-medium text-blue-800">View Products</div>
                    </a>
                    <a href="purchase-orders.php?supplier_id=<?= $supplier['id'] ?>" 
                       class="bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg p-4 text-center transition-colors">
                        <i class="fas fa-file-invoice text-green-600 text-2xl mb-2"></i>
                        <div class="font-medium text-green-800">View Orders</div>
                    </a>
                    <a href="mailto:<?= htmlspecialchars($supplier['email']) ?>" 
                       class="bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg p-4 text-center transition-colors">
                        <i class="fas fa-envelope text-purple-600 text-2xl mb-2"></i>
                        <div class="font-medium text-purple-800">Send Email</div>
                    </a>
                    <a href="purchase-orders.php" 
                       class="bg-orange-50 hover:bg-orange-100 border border-orange-200 rounded-lg p-4 text-center transition-colors">
                        <i class="fas fa-plus text-orange-600 text-2xl mb-2"></i>
                        <div class="font-medium text-orange-800">New Order</div>
                    </a>
                </div>
            </div>

            <!-- Recent Products and Orders -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Products -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-box mr-2 text-blue-600"></i>Recent Products
                    </h3>
                    <?php if (empty($recentProducts)): ?>
                        <p class="text-gray-500 text-center py-4">No products found</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach (array_slice($recentProducts, 0, 5) as $product): ?>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <div>
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($product['product_name']) ?></div>
                                        <div class="text-sm text-gray-500">SKU: <?= htmlspecialchars($product['supplier_sku']) ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium text-gray-900">$<?= number_format($product['cost_price'], 2) ?></div>
                                        <div class="text-sm text-gray-500"><?= ucfirst($product['status']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 text-center">
                            <a href="supplier-products.php?supplier_id=<?= $supplier['id'] ?>" 
                               class="text-blue-600 hover:text-blue-800 font-medium">View All Products</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Purchase Orders -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-file-invoice mr-2 text-green-600"></i>Recent Purchase Orders
                    </h3>
                    <?php if (empty($recentOrders)): ?>
                        <p class="text-gray-500 text-center py-4">No purchase orders found</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach (array_slice($recentOrders, 0, 5) as $order): ?>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <div>
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($order['po_number']) ?></div>
                                        <div class="text-sm text-gray-500"><?= date('M j, Y', strtotime($order['order_date'])) ?></div>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch($order['status']) {
                                                case 'draft': echo 'bg-gray-100 text-gray-800'; break;
                                                case 'sent': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'confirmed': echo 'bg-purple-100 text-purple-800'; break;
                                                case 'received': echo 'bg-green-100 text-green-800'; break;
                                                case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 text-center">
                            <a href="purchase-orders.php?supplier_id=<?= $supplier['id'] ?>" 
                               class="text-blue-600 hover:text-blue-800 font-medium">View All Orders</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notes Section -->
            <?php if ($supplier['notes']): ?>
                <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-sticky-note mr-2 text-yellow-600"></i>Notes
                    </h3>
                    <div class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($supplier['notes']) ?></div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <i class="fas fa-exclamation-triangle text-4xl text-yellow-500 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Supplier Not Found</h2>
                <p class="text-gray-600 mb-4">The requested supplier could not be found.</p>
                <a href="suppliers.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Suppliers
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Supplier Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Edit Supplier Information</h3>
                </div>

                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="update_supplier">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Information -->
                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Basic Information</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company Name *</label>
                            <input type="text" name="company_name" value="<?= htmlspecialchars($supplier['company_name'] ?? '') ?>" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Person *</label>
                            <input type="text" name="contact_person" value="<?= htmlspecialchars($supplier['contact_person'] ?? '') ?>" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($supplier['email'] ?? '') ?>" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Website</label>
                            <input type="url" name="website" value="<?= htmlspecialchars($supplier['website'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>

                        <!-- Address Information -->
                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4 mt-6">Address Information</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Address Line 1</label>
                            <input type="text" name="address_line1" value="<?= htmlspecialchars($supplier['address_line1'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Address Line 2</label>
                            <input type="text" name="address_line2" value="<?= htmlspecialchars($supplier['address_line2'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">City</label>
                            <input type="text" name="city" value="<?= htmlspecialchars($supplier['city'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">State/Province</label>
                            <input type="text" name="state" value="<?= htmlspecialchars($supplier['state'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Postal Code</label>
                            <input type="text" name="postal_code" value="<?= htmlspecialchars($supplier['postal_code'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                            <select name="country_code" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= $country['code'] ?>" <?= $supplier['country_code'] === $country['code'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($country['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Business Terms -->
                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4 mt-6">Business Terms</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Terms</label>
                            <select name="payment_terms" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                                <option value="net_15" <?= $supplier['payment_terms'] === 'net_15' ? 'selected' : '' ?>>Net 15 Days</option>
                                <option value="net_30" <?= $supplier['payment_terms'] === 'net_30' ? 'selected' : '' ?>>Net 30 Days</option>
                                <option value="net_45" <?= $supplier['payment_terms'] === 'net_45' ? 'selected' : '' ?>>Net 45 Days</option>
                                <option value="net_60" <?= $supplier['payment_terms'] === 'net_60' ? 'selected' : '' ?>>Net 60 Days</option>
                                <option value="cod" <?= $supplier['payment_terms'] === 'cod' ? 'selected' : '' ?>>Cash on Delivery</option>
                                <option value="prepaid" <?= $supplier['payment_terms'] === 'prepaid' ? 'selected' : '' ?>>Prepaid</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Lead Time (Days)</label>
                            <input type="number" name="lead_time_days" value="<?= $supplier['lead_time_days'] ?? 7 ?>" min="1" max="365"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Order Amount</label>
                            <input type="number" name="minimum_order_amount" value="<?= $supplier['minimum_order_amount'] ?? 0 ?>" min="0" step="0.01"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500"><?= htmlspecialchars($supplier['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 mt-6 pt-6 border-t border-gray-200">
                        <button type="button" onclick="closeEditModal()"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                            Update Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEditModal() {
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        });

        // ESC key to close modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>

</body>
</html>