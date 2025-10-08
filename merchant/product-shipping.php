<?php
require_once '../config/database.php';
require_once '../classes/GlobalShippingCalculator.php';

// Require merchant login
requireRole('merchant');

$merchantId = $_SESSION['user_id'];
$globalShipping = new GlobalShippingCalculator($pdo);

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_product_dimensions') {
        $productId = intval($_POST['product_id'] ?? 0);
        $packageTypeId = intval($_POST['package_type_id'] ?? 1);
        $weight = floatval($_POST['weight'] ?? 0.5);
        $length = floatval($_POST['length'] ?? 20);
        $width = floatval($_POST['width'] ?? 15);
        $height = floatval($_POST['height'] ?? 10);
        $fragile = isset($_POST['fragile']);
        $hazardous = isset($_POST['hazardous']);
        $liquid = isset($_POST['liquid']);
        $perishable = isset($_POST['perishable']);
        $requiresSignature = isset($_POST['requires_signature']);
        $requiresAdultSignature = isset($_POST['requires_adult_signature']);
        $declaredValue = floatval($_POST['declared_value'] ?? 0);
        $hsCode = trim($_POST['hs_code'] ?? '');
        
        // Verify product ownership
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND merchant_id = ?");
        $stmt->execute([$productId, $merchantId]);
        if (!$stmt->fetch()) {
            $error = 'Product not found or access denied.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO product_dimensions (
                        product_id, package_type_id, weight_kg, length_cm, width_cm, height_cm,
                        fragile, hazardous, liquid, perishable, requires_signature, requires_adult_signature,
                        declared_value, hs_code
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        package_type_id = VALUES(package_type_id),
                        weight_kg = VALUES(weight_kg),
                        length_cm = VALUES(length_cm),
                        width_cm = VALUES(width_cm),
                        height_cm = VALUES(height_cm),
                        fragile = VALUES(fragile),
                        hazardous = VALUES(hazardous),
                        liquid = VALUES(liquid),
                        perishable = VALUES(perishable),
                        requires_signature = VALUES(requires_signature),
                        requires_adult_signature = VALUES(requires_adult_signature),
                        declared_value = VALUES(declared_value),
                        hs_code = VALUES(hs_code),
                        updated_at = NOW()
                ");
                
                if ($stmt->execute([$productId, $packageTypeId, $weight, $length, $width, $height, 
                                  $fragile, $hazardous, $liquid, $perishable, $requiresSignature, 
                                  $requiresAdultSignature, $declaredValue, $hsCode])) {
                    $success = 'Product shipping information updated successfully!';
                } else {
                    $error = 'Failed to update product shipping information.';
                }
            } catch (Exception $e) {
                $error = 'An error occurred: ' . $e->getMessage();
            }
        }
    }
}

// Get merchant's products with shipping info
$stmt = $pdo->prepare("
    SELECT p.*, pd.*, pt.name as package_type_name, pt.description as package_type_description
    FROM products p
    LEFT JOIN product_dimensions pd ON p.id = pd.product_id
    LEFT JOIN package_types pt ON pd.package_type_id = pt.id
    WHERE p.merchant_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$merchantId]);
$products = $stmt->fetchAll();

// Get package types
$packageTypes = $pdo->query("SELECT * FROM package_types WHERE is_active = TRUE ORDER BY name")->fetchAll();

// Get shipping statistics for merchant
$stats = [
    'total_products' => count($products),
    'products_with_dimensions' => count(array_filter($products, function($p) { return !empty($p['weight_kg']); })),
    'fragile_products' => count(array_filter($products, function($p) { return $p['fragile']; })),
    'hazardous_products' => count(array_filter($products, function($p) { return $p['hazardous']; }))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Shipping Management - VentDepot</title>
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
                <h1 class="text-3xl font-bold text-gray-900">Product Shipping Management</h1>
                <p class="text-gray-600 mt-2">Manage shipping dimensions and package types for your products</p>
            </div>
            <a href="products.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2"></i>Back to Products
            </a>
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
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['total_products'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-ruler-combined text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">With Dimensions</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['products_with_dimensions'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Fragile Items</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['fragile_products'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-radiation text-red-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Hazardous Items</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['hazardous_products'] ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products List -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Your Products</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dimensions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Special Handling</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($products as $product): ?>
                            <tr>
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
                                            <div class="text-sm text-gray-500">$<?= number_format($product['price'], 2) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php if ($product['weight_kg']): ?>
                                        <div><?= $product['weight_kg'] ?>kg</div>
                                        <div class="text-xs text-gray-500">
                                            <?= $product['length_cm'] ?>×<?= $product['width_cm'] ?>×<?= $product['height_cm'] ?>cm
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= number_format($product['volume_cm3']) ?>cm³
                                        </div>
                                    <?php else: ?>
                                        <span class="text-red-500">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= $product['package_type_name'] ?? 'Standard Box' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        <?php if ($product['fragile']): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>Fragile
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($product['hazardous']): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-radiation mr-1"></i>Hazardous
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($product['liquid']): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-tint mr-1"></i>Liquid
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($product['perishable']): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-leaf mr-1"></i>Perishable
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($product['requires_signature']): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                <i class="fas fa-signature mr-1"></i>Signature
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="editShipping(<?= $product['id'] ?>)" 
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit mr-1"></i>Edit Shipping
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Shipping Modal -->
    <div id="editShippingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Product Shipping Information</h3>
                <form method="POST" id="shippingForm">
                    <input type="hidden" name="action" value="update_product_dimensions">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Left Column - Dimensions -->
                        <div class="space-y-4">
                            <h4 class="font-medium text-gray-900">Package Dimensions</h4>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Package Type</label>
                                <select name="package_type_id" id="edit_package_type_id" required
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <?php foreach ($packageTypes as $type): ?>
                                        <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Weight (kg)</label>
                                    <input type="number" name="weight" id="edit_weight" step="0.001" min="0" required
                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Declared Value ($)</label>
                                    <input type="number" name="declared_value" id="edit_declared_value" step="0.01" min="0"
                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Length (cm)</label>
                                    <input type="number" name="length" id="edit_length" step="0.1" min="0" required
                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Width (cm)</label>
                                    <input type="number" name="width" id="edit_width" step="0.1" min="0" required
                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Height (cm)</label>
                                    <input type="number" name="height" id="edit_height" step="0.1" min="0" required
                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">HS Code (for customs)</label>
                                <input type="text" name="hs_code" id="edit_hs_code"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                       placeholder="e.g., 8471.30.01">
                                <p class="text-xs text-gray-500 mt-1">Harmonized System code for international shipping</p>
                            </div>
                        </div>
                        
                        <!-- Right Column - Special Handling -->
                        <div class="space-y-4">
                            <h4 class="font-medium text-gray-900">Special Handling Requirements</h4>
                            
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <input type="checkbox" name="fragile" id="edit_fragile"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="edit_fragile" class="ml-2 text-sm text-gray-700">
                                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-1"></i>
                                        Fragile item (additional $5.00 handling fee)
                                    </label>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" name="hazardous" id="edit_hazardous"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="edit_hazardous" class="ml-2 text-sm text-gray-700">
                                        <i class="fas fa-radiation text-red-500 mr-1"></i>
                                        Hazardous material (additional $25.00 fee, restrictions apply)
                                    </label>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" name="liquid" id="edit_liquid"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="edit_liquid" class="ml-2 text-sm text-gray-700">
                                        <i class="fas fa-tint text-blue-500 mr-1"></i>
                                        Contains liquid (additional $10.00 handling fee)
                                    </label>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" name="perishable" id="edit_perishable"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="edit_perishable" class="ml-2 text-sm text-gray-700">
                                        <i class="fas fa-leaf text-green-500 mr-1"></i>
                                        Perishable item (expedited shipping, additional $15.00 fee)
                                    </label>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" name="requires_signature" id="edit_requires_signature"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="edit_requires_signature" class="ml-2 text-sm text-gray-700">
                                        <i class="fas fa-signature text-purple-500 mr-1"></i>
                                        Requires signature on delivery
                                    </label>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" name="requires_adult_signature" id="edit_requires_adult_signature"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="edit_requires_adult_signature" class="ml-2 text-sm text-gray-700">
                                        <i class="fas fa-id-card text-orange-500 mr-1"></i>
                                        Requires adult signature (21+)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">Shipping Cost Impact</h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p>These settings affect shipping costs and delivery options:</p>
                                            <ul class="list-disc list-inside mt-1 space-y-1">
                                                <li>Accurate dimensions ensure proper shipping rates</li>
                                                <li>Special handling adds fees but ensures safe delivery</li>
                                                <li>HS codes are required for international shipping</li>
                                                <li>Higher declared values may require insurance</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                        <button type="button" onclick="closeShippingModal()"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                            <i class="fas fa-save mr-2"></i>Update Shipping Info
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const products = <?= json_encode($products) ?>;
        
        function editShipping(productId) {
            const product = products.find(p => p.id == productId);
            if (!product) return;
            
            // Populate form
            document.getElementById('edit_product_id').value = productId;
            document.getElementById('edit_package_type_id').value = product.package_type_id || 1;
            document.getElementById('edit_weight').value = product.weight_kg || 0.5;
            document.getElementById('edit_length').value = product.length_cm || 20;
            document.getElementById('edit_width').value = product.width_cm || 15;
            document.getElementById('edit_height').value = product.height_cm || 10;
            document.getElementById('edit_declared_value').value = product.declared_value || 0;
            document.getElementById('edit_hs_code').value = product.hs_code || '';
            
            // Set checkboxes
            document.getElementById('edit_fragile').checked = product.fragile == 1;
            document.getElementById('edit_hazardous').checked = product.hazardous == 1;
            document.getElementById('edit_liquid').checked = product.liquid == 1;
            document.getElementById('edit_perishable').checked = product.perishable == 1;
            document.getElementById('edit_requires_signature').checked = product.requires_signature == 1;
            document.getElementById('edit_requires_adult_signature').checked = product.requires_adult_signature == 1;
            
            // Show modal
            document.getElementById('editShippingModal').classList.remove('hidden');
        }
        
        function closeShippingModal() {
            document.getElementById('editShippingModal').classList.add('hidden');
        }
        
        // Calculate volume in real-time
        function updateVolume() {
            const length = parseFloat(document.getElementById('edit_length').value) || 0;
            const width = parseFloat(document.getElementById('edit_width').value) || 0;
            const height = parseFloat(document.getElementById('edit_height').value) || 0;
            const volume = length * width * height;
            
            // You could display this somewhere if needed
            console.log('Volume:', volume, 'cm³');
        }
        
        // Add event listeners for real-time volume calculation
        document.addEventListener('DOMContentLoaded', function() {
            ['edit_length', 'edit_width', 'edit_height'].forEach(id => {
                document.getElementById(id).addEventListener('input', updateVolume);
            });
        });
    </script>
</body>
</html>
