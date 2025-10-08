<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    try {
        $pdo->beginTransaction();
        
        // Create sample users if they don't exist
        $sampleUsers = [
            ['email' => 'customer1@demo.com', 'role' => 'customer'],
            ['email' => 'customer2@demo.com', 'role' => 'customer'],
            ['email' => 'merchant1@demo.com', 'role' => 'merchant'],
            ['email' => 'merchant2@demo.com', 'role' => 'merchant']
        ];
        
        $userIds = [];
        foreach ($sampleUsers as $user) {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$user['email']]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                $userIds[$user['email']] = $existingUser['id'];
            } else {
                // Create user
                $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$user['email'], password_hash('demo123', PASSWORD_DEFAULT), $user['role']]);
                $userIds[$user['email']] = $pdo->lastInsertId();
                
                // Create user profile
                $stmt = $pdo->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)");
                $firstName = ucfirst(explode('@', $user['email'])[0]);
                $stmt->execute([$userIds[$user['email']], $firstName, 'Demo', '+1234567890']);
            }
        }
        
        // Create sample products if they don't exist
        $sampleProducts = [
            ['name' => 'Wireless Headphones', 'price' => 89.99, 'merchant_email' => 'merchant1@demo.com'],
            ['name' => 'Bluetooth Speaker', 'price' => 45.50, 'merchant_email' => 'merchant1@demo.com'],
            ['name' => 'Phone Case', 'price' => 19.99, 'merchant_email' => 'merchant2@demo.com'],
            ['name' => 'USB Cable', 'price' => 12.99, 'merchant_email' => 'merchant2@demo.com']
        ];
        
        $productIds = [];
        foreach ($sampleProducts as $product) {
            // Check if product exists
            $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND merchant_id = ?");
            $stmt->execute([$product['name'], $userIds[$product['merchant_email']]]);
            $existingProduct = $stmt->fetch();
            
            if ($existingProduct) {
                $productIds[] = $existingProduct['id'];
            } else {
                // Create product
                $stmt = $pdo->prepare("
                    INSERT INTO products (name, description, price, stock, merchant_id, category)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $product['name'],
                    'Demo product for testing',
                    $product['price'],
                    100,
                    $userIds[$product['merchant_email']],
                    'Electronics'
                ]);
                $productIds[] = $pdo->lastInsertId();
            }
        }
        
        // Create sample orders for the last 7 days
        $customerEmails = ['customer1@demo.com', 'customer2@demo.com'];
        
        for ($i = 6; $i >= 0; $i--) {
            $orderDate = date('Y-m-d H:i:s', strtotime("-$i days"));
            $numOrders = rand(1, 3); // 1-3 orders per day
            
            for ($j = 0; $j < $numOrders; $j++) {
                $customerEmail = $customerEmails[array_rand($customerEmails)];
                $customerId = $userIds[$customerEmail];
                
                // Random order total between $20 and $200
                $orderTotal = rand(2000, 20000) / 100;
                
                // Create order
                $stmt = $pdo->prepare("
                    INSERT INTO orders (user_id, total, status, shipping_address, payment_method, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $statuses = ['pending', 'processing', 'shipped', 'delivered'];
                $status = $statuses[array_rand($statuses)];
                
                $stmt->execute([
                    $customerId,
                    $orderTotal,
                    $status,
                    "123 Demo Street\nDemo City, DC 12345\nUnited States",
                    'credit_card',
                    $orderDate
                ]);
                
                $orderId = $pdo->lastInsertId();
                
                // Add 1-3 items to each order
                $numItems = rand(1, 3);
                $orderItemsTotal = 0;
                
                for ($k = 0; $k < $numItems; $k++) {
                    $productId = $productIds[array_rand($productIds)];
                    $quantity = rand(1, 2);
                    
                    // Get product price
                    $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $productPrice = $stmt->fetchColumn();
                    
                    // Create order item
                    $stmt = $pdo->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, price) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$orderId, $productId, $quantity, $productPrice]);
                    
                    $orderItemsTotal += $productPrice * $quantity;
                }
                
                // Update order total to match items
                $stmt = $pdo->prepare("UPDATE orders SET total = ? WHERE id = ?");
                $stmt->execute([$orderItemsTotal, $orderId]);
            }
        }
        
        $pdo->commit();
        $success = 'Sample data added successfully! The dashboard chart should now display revenue data.';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to add sample data: ' . $e->getMessage();
    }
}

// Get current data counts
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'recent_orders' => $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Sample Data - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Add Sample Data</h1>
                <p class="text-gray-600 mt-2">Generate sample data to test the dashboard and charts</p>
            </div>
            <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
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

        <!-- Current Data Status -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Current Data Status</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 border border-gray-200 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600"><?= number_format($stats['users']) ?></div>
                    <div class="text-sm text-gray-600">Total Users</div>
                </div>
                <div class="text-center p-4 border border-gray-200 rounded-lg">
                    <div class="text-2xl font-bold text-green-600"><?= number_format($stats['products']) ?></div>
                    <div class="text-sm text-gray-600">Total Products</div>
                </div>
                <div class="text-center p-4 border border-gray-200 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600"><?= number_format($stats['orders']) ?></div>
                    <div class="text-sm text-gray-600">Total Orders</div>
                </div>
                <div class="text-center p-4 border border-gray-200 rounded-lg">
                    <div class="text-2xl font-bold text-orange-600"><?= number_format($stats['recent_orders']) ?></div>
                    <div class="text-sm text-gray-600">Recent Orders (7 days)</div>
                </div>
            </div>
        </div>

        <!-- Add Sample Data -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Generate Sample Data</h2>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">What this will create:</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>4 sample users (2 customers, 2 merchants)</li>
                                <li>4 sample products in Electronics category</li>
                                <li>7-21 sample orders spread over the last 7 days</li>
                                <li>Order items linking products to orders</li>
                                <li>Realistic revenue data for chart testing</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="POST">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Ready to add sample data?</h3>
                        <p class="text-sm text-gray-600">This will help you test the dashboard charts and functionality.</p>
                    </div>
                    <button type="submit" name="add_sample_data" value="1" 
                            class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700"
                            onclick="return confirm('Are you sure you want to add sample data? This will create test users, products, and orders.')">
                        <i class="fas fa-plus mr-2"></i>Add Sample Data
                    </button>
                </div>
            </form>
        </div>

        <!-- Instructions -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">After Adding Sample Data</h2>
            <div class="space-y-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-chart-line text-blue-600 mt-1"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="font-medium text-gray-900">Dashboard Chart</h3>
                        <p class="text-sm text-gray-600">The revenue chart will display daily sales data for the past 7 days</p>
                    </div>
                </div>
                
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-activity text-green-600 mt-1"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="font-medium text-gray-900">Recent Activities</h3>
                        <p class="text-sm text-gray-600">The activities section will show recent orders, customer registrations, and product additions</p>
                    </div>
                </div>
                
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-users text-purple-600 mt-1"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="font-medium text-gray-900">User Management</h3>
                        <p class="text-sm text-gray-600">Test users will appear in the Users section for management testing</p>
                    </div>
                </div>
                
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shopping-cart text-orange-600 mt-1"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="font-medium text-gray-900">Order Management</h3>
                        <p class="text-sm text-gray-600">Sample orders will be available for testing order management features</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
