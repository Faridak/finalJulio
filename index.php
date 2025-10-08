<?php
require_once 'config/database.php';
require_once 'classes\CMSFrontend.php';

// Initialize CMS frontend helper
$cms = new CMSFrontend($pdo);

// Fetch featured products from CMS carousel
$featuredProducts = $cms->getProductsInCarousel('Featured Products', 8);

// Fetch categories
$stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get SEO metadata for homepage
$seoMetadata = $cms->getSEOMetadata('homepage');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seoMetadata['meta_title'] ?? 'VentDepot - Your Online Marketplace') ?></title>
    <?php if ($seoMetadata['meta_description']): ?>
        <meta name="description" content="<?= htmlspecialchars($seoMetadata['meta_description']) ?>">
    <?php endif; ?>
    <?php if ($seoMetadata['meta_keywords']): ?>
        <meta name="keywords" content="<?= htmlspecialchars($seoMetadata['meta_keywords']) ?>">
    <?php endif; ?>
    <?php if ($seoMetadata['canonical_url']): ?>
        <link rel="canonical" href="<?= htmlspecialchars($seoMetadata['canonical_url']) ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold text-blue-600">VentDepot</h1>
                </div>
                
                <!-- Search Bar -->
                <div class="flex-1 max-w-lg mx-8">
                    <form action="search.php" method="GET" class="relative">
                        <input type="text" name="q" placeholder="Search products..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <button type="submit" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <a href="cart.php" class="relative text-gray-600 hover:text-blue-600">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <?php if (getCartCount() > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?= getCartCount() ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <?php if (isLoggedIn()): ?>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center space-x-2 text-gray-600 hover:text-blue-600">
                                <i class="fas fa-user"></i>
                                <span><?= htmlspecialchars($_SESSION['user_email'] ?? 'User') ?></span>
                                <i class="fas fa-chevron-down text-sm"></i>
                            </button>
                            <div x-show="open" @click.away="open = false" 
                                 class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                                <a href="orders.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Orders</a>
                                <?php if (getUserRole() === 'merchant'): ?>
                                    <a href="merchant/dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Merchant Dashboard</a>
                                <?php endif; ?>
                                <?php if (getUserRole() === 'admin'): ?>
                                    <a href="admin/dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Admin Dashboard</a>
                                <?php endif; ?>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-600 hover:text-blue-600">Login</a>
                        <a href="register.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Banner from CMS -->
    <?php 
    $heroBanners = $cms->getBannersByType('hero', 1);
    if (!empty($heroBanners)): 
        echo $cms->renderBanner($heroBanners[0]);
    else: ?>
        <!-- Default Hero Section -->
        <section class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-20">
            <div class="max-w-7xl mx-auto px-4 text-center">
                <h1 class="text-5xl font-bold mb-6">Welcome to VentDepot</h1>
                <p class="text-xl mb-8">Discover amazing products from trusted merchants worldwide</p>
                <a href="search.php" class="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-200">
                    Start Shopping
                </a>
            </div>
        </section>
    <?php endif; ?>

    <!-- Promotional Banners from CMS -->
    <?php 
    $promoBanners = $cms->getBannersByType('promotion', 3);
    if (!empty($promoBanners)): ?>
        <section class="py-8 bg-gray-100">
            <div class="max-w-7xl mx-auto px-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($promoBanners as $banner): 
                        $image = $cms->getImageById($banner['image_id']);
                        $imageUrl = $image ? $image['file_path'] : 'https://via.placeholder.com/400x200';
                    ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <img src="<?= htmlspecialchars($imageUrl) ?>" 
                                 alt="<?= htmlspecialchars($banner['title']) ?>"
                                 class="w-full h-48 object-cover">
                            <div class="p-4">
                                <h3 class="font-bold text-lg mb-2"><?= htmlspecialchars($banner['title']) ?></h3>
                                <?php if ($banner['subtitle']): ?>
                                    <p class="text-gray-600 text-sm mb-3"><?= htmlspecialchars($banner['subtitle']) ?></p>
                                <?php endif; ?>
                                <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                    <a href="<?= htmlspecialchars($banner['button_url']) ?>" 
                                       target="<?= $banner['target'] === '_blank' ? '_blank' : '_self' ?>"
                                       class="text-blue-600 hover:text-blue-800 font-medium">
                                        <?= htmlspecialchars($banner['button_text']) ?> →
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Categories -->
    <section class="py-16">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Shop by Category</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6">
                <?php foreach ($categories as $category): ?>
                    <a href="search.php?category=<?= urlencode($category) ?>" 
                       class="bg-white rounded-lg p-6 text-center shadow-md hover:shadow-lg transition duration-200">
                        <div class="text-3xl mb-3">
                            <?php
                            $icons = [
                                'Electronics' => '📱',
                                'Clothing' => '👕',
                                'Home' => '🏠',
                                'Books' => '📚',
                                'Sports' => '⚽',
                                'Beauty' => '💄'
                            ];
                            echo $icons[$category] ?? '🛍️';
                            ?>
                        </div>
                        <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($category) ?></h3>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Products Carousel from CMS -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4">
            <?php if (!empty($featuredProducts)): ?>
                <?= $cms->renderProductCarousel($featuredProducts, 'Featured Products', 'Our most popular items') ?>
            <?php else: ?>
                <h2 class="text-3xl font-bold text-center mb-12">Featured Products</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php 
                    $stmt = $pdo->prepare("SELECT * FROM products ORDER BY created_at DESC LIMIT 8");
                    $stmt->execute();
                    $defaultProducts = $stmt->fetchAll();
                    foreach ($defaultProducts as $product): ?>
                        <div class="bg-gray-50 rounded-lg overflow-hidden shadow-md hover:shadow-lg transition duration-200">
                            <a href="product.php?id=<?= $product['id'] ?>">
                                <img src="<?= htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/300x200') ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     class="w-full h-48 object-cover">
                            </a>
                            <div class="p-4">
                                <h3 class="font-semibold text-lg mb-2">
                                    <a href="product.php?id=<?= $product['id'] ?>" class="hover:text-blue-600">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </a>
                                </h3>
                                <p class="text-gray-600 text-sm mb-3"><?= htmlspecialchars(substr($product['description'], 0, 100)) ?>...</p>
                                <div class="flex justify-between items-center">
                                    <span class="text-2xl font-bold text-blue-600">$<?= number_format($product['price'], 2) ?></span>
                                    <button onclick="addToCart(<?= $product['id'] ?>)" 
                                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Content Blocks from CMS -->
    <?php 
    $contentBlocks = $cms->getContentBlocksBySection('homepage-featured');
    if (!empty($contentBlocks)): ?>
        <section class="py-16 bg-gray-100">
            <div class="max-w-7xl mx-auto px-4">
                <?php foreach ($contentBlocks as $block): ?>
                    <div class="bg-white rounded-lg shadow-md p-8 mb-6">
                        <h2 class="text-2xl font-bold mb-4"><?= htmlspecialchars($block['title']) ?></h2>
                        <?php if ($block['content_type'] === 'html'): ?>
                            <?= $block['content'] ?>
                        <?php else: ?>
                            <p class="text-gray-700"><?= htmlspecialchars($block['content']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">VentDepot</h3>
                    <p class="text-gray-400">Your trusted online marketplace for quality products from verified merchants.</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Customer Service</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="contact.php" class="hover:text-white">Contact Us</a></li>
                        <li><a href="shipping-info.php" class="hover:text-white">Shipping Info</a></li>
                        <li><a href="returns.php" class="hover:text-white">Returns</a></li>
                        <li><a href="faq.php" class="hover:text-white">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">For Merchants</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="merchant/register.php" class="hover:text-white">Become a Seller</a></li>
                        <li><a href="merchant/login.php" class="hover:text-white">Merchant Login</a></li>
                        <li><a href="seller-guide.php" class="hover:text-white">Seller Guide</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Connect</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-facebook text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-twitter text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-instagram text-xl"></i></a>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 VentDepot. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function addToCart(productId) {
            fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId,
                    quantity: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart count in navigation
                    location.reload();
                } else {
                    alert('Error adding to cart: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding to cart');
            });
        }
    </script>
</body>
</html>