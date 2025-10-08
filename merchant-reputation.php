<?php
require_once 'config/database.php';
require_once 'includes/MerchantReputation.php';

// Get merchant ID from URL
$merchantId = intval($_GET['id'] ?? 0);

if ($merchantId <= 0) {
    header('Location: index.php');
    exit;
}

// Verify merchant exists
$stmt = $pdo->prepare("SELECT id, email, created_at FROM users WHERE id = ? AND role = 'merchant'");
$stmt->execute([$merchantId]);
$merchant = $stmt->fetch();

if (!$merchant) {
    header('Location: index.php');
    exit;
}

// Get merchant profile
$stmt = $pdo->prepare("
    SELECT up.*, u.email, u.created_at as member_since
    FROM user_profiles up
    RIGHT JOIN users u ON up.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$merchantId]);
$profile = $stmt->fetch();

// Initialize reputation system
$reputationSystem = new MerchantReputation($pdo);

// Get reputation data
$reputation = $reputationSystem->getMerchantReputation($merchantId);
$metrics = $reputationSystem->getMerchantMetrics($merchantId);
$trustIndicators = $reputationSystem->getMerchantTrustIndicators($merchantId);

// Get ratings with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$sortBy = $_GET['sort'] ?? 'newest';
$ratingsData = $reputationSystem->getMerchantRatings($merchantId, $page, 10, $sortBy);

// Get merchant products
$stmt = $pdo->prepare("
    SELECT id, name, price, image_url, average_rating, review_count
    FROM products 
    WHERE merchant_id = ? AND status = 'active'
    ORDER BY created_at DESC 
    LIMIT 8
");
$stmt->execute([$merchantId]);
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name'] ?: 'Merchant') ?> - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Merchant Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between">
                <div class="flex items-center space-x-4 mb-4 md:mb-0">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                        <?= strtoupper(substr($profile['first_name'] ?: $profile['email'], 0, 1)) ?>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            <?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name'] ?: 'Anonymous Merchant') ?>
                        </h1>
                        <?php if ($reputation['badge']): ?>
                            <div class="flex items-center mt-1">
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full 
                                    <?= $reputation['badge']['color'] === 'gold' ? 'bg-yellow-100 text-yellow-800' : 
                                        ($reputation['badge']['color'] === 'purple' ? 'bg-purple-100 text-purple-800' :
                                        ($reputation['badge']['color'] === 'green' ? 'bg-green-100 text-green-800' :
                                        ($reputation['badge']['color'] === 'blue' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'))) ?>">
                                    <i class="<?= $reputation['badge']['icon'] ?> mr-1"></i>
                                    <?= $reputation['badge']['name'] ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <p class="text-gray-600 text-sm mt-1">
                            Member since <?= date('M Y', strtotime($profile['member_since'])) ?>
                        </p>
                    </div>
                </div>
                
                <div class="flex flex-col items-end">
                    <?php if ($reputation['total_ratings'] > 0): ?>
                        <div class="flex items-center">
                            <div class="text-2xl font-bold text-gray-900 mr-2">
                                <?= number_format($reputation['average_rating'], 1) ?>
                            </div>
                            <div class="flex">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= $i <= round($reputation['average_rating']) ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <p class="text-gray-600 text-sm">
                            <?= number_format($reputation['total_ratings']) ?> rating<?= $reputation['total_ratings'] != 1 ? 's' : '' ?>
                        </p>
                    <?php else: ?>
                        <div class="text-gray-500">
                            <i class="fas fa-star text-gray-300"></i>
                            <span class="ml-1">No ratings yet</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($profile['bio']): ?>
                <div class="mt-4 pt-4 border-t">
                    <p class="text-gray-700"><?= nl2br(htmlspecialchars($profile['bio'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Reputation Overview -->
                <?php if ($reputation['total_ratings'] > 0): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6">Seller Ratings Breakdown</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Rating Distribution -->
                            <div>
                                <h3 class="font-medium text-gray-900 mb-4">Rating Distribution</h3>
                                <?php 
                                $starCounts = [
                                    5 => $reputation['five_star'],
                                    4 => $reputation['four_star'], 
                                    3 => $reputation['three_star'],
                                    2 => $reputation['two_star'],
                                    1 => $reputation['one_star']
                                ];
                                ?>
                                <?php foreach ($starCounts as $stars => $count): ?>
                                    <div class="flex items-center mb-2">
                                        <span class="text-sm w-4"><?= $stars ?></span>
                                        <i class="fas fa-star text-yellow-400 text-sm mr-2 ml-1"></i>
                                        <div class="flex-1 bg-gray-200 rounded-full h-2 mr-3">
                                            <div class="bg-yellow-400 h-2 rounded-full" style="width: <?= $reputation['total_ratings'] > 0 ? ($count / $reputation['total_ratings'] * 100) : 0 ?>%"></div>
                                        </div>
                                        <span class="text-sm text-gray-600 w-8"><?= $count ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Detailed Ratings -->
                            <div>
                                <h3 class="font-medium text-gray-900 mb-4">Detailed Ratings</h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Communication</span>
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium mr-2"><?= number_format($reputation['avg_communication'], 1) ?></span>
                                            <div class="flex">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star text-xs <?= $i <= round($reputation['avg_communication']) ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Shipping Speed</span>
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium mr-2"><?= number_format($reputation['avg_shipping_speed'], 1) ?></span>
                                            <div class="flex">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star text-xs <?= $i <= round($reputation['avg_shipping_speed']) ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Item Description</span>
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium mr-2"><?= number_format($reputation['avg_item_description'], 1) ?></span>
                                            <div class="flex">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star text-xs <?= $i <= round($reputation['avg_item_description']) ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Customer Reviews -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b">
                        <div class="flex justify-between items-center">
                            <h2 class="text-xl font-semibold text-gray-900">Customer Feedback</h2>
                            <?php if (!empty($ratingsData['ratings'])): ?>
                                <select onchange="window.location.href='?id=<?= $merchantId ?>&sort=' + this.value" class="text-sm border border-gray-300 rounded px-2 py-1">
                                    <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                                    <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                    <option value="highest_rated" <?= $sortBy === 'highest_rated' ? 'selected' : '' ?>>Highest Rated</option>
                                    <option value="lowest_rated" <?= $sortBy === 'lowest_rated' ? 'selected' : '' ?>>Lowest Rated</option>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($ratingsData['ratings'])): ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($ratingsData['ratings'] as $rating): ?>
                                <div class="p-6">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <div class="flex items-center mb-1">
                                                <span class="font-medium text-gray-900">
                                                    <?= htmlspecialchars(trim($rating['first_name'] . ' ' . $rating['last_name']) ?: 'Anonymous') ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $rating['rating'] ? 'text-yellow-400' : 'text-gray-300' ?> text-sm"></i>
                                                <?php endfor; ?>
                                                <span class="ml-2 text-sm text-gray-600"><?= date('M j, Y', strtotime($rating['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($rating['feedback_text']): ?>
                                        <p class="text-gray-700"><?= nl2br(htmlspecialchars($rating['feedback_text'])) ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3 grid grid-cols-3 gap-4 text-xs text-gray-500">
                                        <div>Communication: <?= $rating['communication_rating'] ?>/5</div>
                                        <div>Shipping: <?= $rating['shipping_speed_rating'] ?>/5</div>
                                        <div>Description: <?= $rating['item_description_rating'] ?>/5</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($ratingsData['total_pages'] > 1): ?>
                            <div class="p-4 border-t">
                                <div class="flex justify-center space-x-2">
                                    <?php for ($i = 1; $i <= $ratingsData['total_pages']; $i++): ?>
                                        <a href="?id=<?= $merchantId ?>&page=<?= $i ?>&sort=<?= urlencode($sortBy) ?>" 
                                           class="px-3 py-2 text-sm <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> rounded">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="p-8 text-center">
                            <i class="fas fa-comments text-gray-300 text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Customer Feedback Yet</h3>
                            <p class="text-gray-600">This merchant hasn't received any customer reviews yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Products -->
                <?php if (!empty($products)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6">Products from this Seller</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <?php foreach ($products as $product): ?>
                                <a href="product.php?id=<?= $product['id'] ?>" class="group">
                                    <div class="bg-gray-100 rounded-lg overflow-hidden group-hover:shadow-md transition-shadow">
                                        <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://via.placeholder.com/300x200') ?>" 
                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                             class="w-full h-32 object-cover group-hover:scale-105 transition-transform">
                                        <div class="p-3">
                                            <h3 class="font-medium text-gray-900 text-sm truncate"><?= htmlspecialchars($product['name']) ?></h3>
                                            <p class="text-blue-600 font-semibold text-sm">$<?= number_format($product['price'], 2) ?></p>
                                            <?php if ($product['average_rating'] > 0): ?>
                                                <div class="flex items-center mt-1">
                                                    <div class="flex">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?= $i <= round($product['average_rating']) ? 'text-yellow-400' : 'text-gray-300' ?> text-xs"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <span class="ml-1 text-xs text-gray-600">(<?= $product['review_count'] ?>)</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Trust Indicators -->
                <?php if (!empty($trustIndicators)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Trust Indicators</h3>
                        <div class="space-y-3">
                            <?php foreach ($trustIndicators as $indicator): ?>
                                <div class="flex items-center text-sm">
                                    <i class="<?= $indicator['icon'] ?> text-green-600 mr-3"></i>
                                    <span class="text-gray-700"><?= $indicator['text'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Performance Metrics -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Performance</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Total Orders</span>
                            <span class="text-sm font-medium"><?= number_format($metrics['total_orders']) ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Completion Rate</span>
                            <span class="text-sm font-medium"><?= number_format($metrics['completion_rate'], 1) ?>%</span>
                        </div>
                        
                        <?php if ($metrics['total_revenue'] > 0): ?>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Total Sales</span>
                                <span class="text-sm font-medium">$<?= number_format($metrics['total_revenue'], 2) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($metrics['avg_response_time_hours'])): ?>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Avg Response Time</span>
                                <span class="text-sm font-medium"><?= number_format($metrics['avg_response_time_hours'], 1) ?>h</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contact Seller -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Contact Seller</h3>
                    <?php if (isLoggedIn()): ?>
                        <button class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-envelope mr-2"></i>
                            Send Message
                        </button>
                    <?php else: ?>
                        <p class="text-sm text-gray-600 mb-3">Please log in to contact this seller.</p>
                        <a href="login.php" class="w-full bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700 transition-colors inline-block text-center">
                            Log In
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>