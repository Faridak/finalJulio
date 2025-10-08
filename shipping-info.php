<?php
require_once 'config/database.php';

// Get shipping information from settings
$shippingInfo = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'shipping_%'");
while ($row = $stmt->fetch()) {
    $shippingInfo[$row['setting_key']] = $row['setting_value'];
}

// Get shipping providers and zones for display
$providers = $pdo->query("SELECT * FROM shipping_providers WHERE is_active = TRUE ORDER BY name")->fetchAll();
$zones = $pdo->query("SELECT * FROM shipping_zones WHERE is_active = TRUE ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Information - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                </div>
                
                <div class="flex items-center space-x-6">
                    <a href="index.php" class="text-gray-600 hover:text-blue-600">Home</a>
                    <a href="products.php" class="text-gray-600 hover:text-blue-600">Products</a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="account.php" class="text-gray-600 hover:text-blue-600">My Account</a>
                        <a href="logout.php" class="text-gray-600 hover:text-blue-600">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-600 hover:text-blue-600">Login</a>
                        <a href="register.php" class="text-gray-600 hover:text-blue-600">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Shipping Information</h1>
            <p class="text-xl text-gray-600">Everything you need to know about our shipping policies and options</p>
        </div>

        <!-- Processing Time -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-clock text-blue-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Processing Time</h2>
            </div>
            <div class="prose max-w-none">
                <p class="text-gray-700 leading-relaxed">
                    <?= nl2br(htmlspecialchars($shippingInfo['shipping_processing_time'] ?? 'Orders are typically processed within 1-2 business days. Orders placed before 2:00 PM PST on business days are usually processed the same day.')) ?>
                </p>
            </div>
        </div>

        <!-- Domestic Shipping -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-truck text-blue-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Domestic Shipping</h2>
            </div>
            <div class="prose max-w-none">
                <p class="text-gray-700 leading-relaxed mb-6">
                    <?= nl2br(htmlspecialchars($shippingInfo['shipping_domestic_info'] ?? 'We offer fast and reliable domestic shipping throughout the United States. Standard shipping typically takes 3-7 business days, while express options are available for faster delivery.')) ?>
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 mb-2">Standard Shipping</h3>
                        <p class="text-gray-600 text-sm mb-2">3-7 business days</p>
                        <p class="text-blue-600 font-medium">$5.99 - $12.99</p>
                        <p class="text-xs text-gray-500 mt-2">Free on orders over $50</p>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 mb-2">Express Shipping</h3>
                        <p class="text-gray-600 text-sm mb-2">2-3 business days</p>
                        <p class="text-blue-600 font-medium">$12.99 - $19.99</p>
                        <p class="text-xs text-gray-500 mt-2">Available for most items</p>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 mb-2">Overnight Shipping</h3>
                        <p class="text-gray-600 text-sm mb-2">1 business day</p>
                        <p class="text-blue-600 font-medium">$24.99 - $39.99</p>
                        <p class="text-xs text-gray-500 mt-2">Order by 2 PM PST</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- International Shipping -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-globe text-blue-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">International Shipping</h2>
            </div>
            <div class="prose max-w-none">
                <p class="text-gray-700 leading-relaxed mb-6">
                    <?= nl2br(htmlspecialchars($shippingInfo['shipping_international_info'] ?? 'International shipping is available to most countries worldwide. Delivery times vary by destination and may take 7-21 business days. Customs fees and import duties may apply.')) ?>
                </p>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">International Shipping Coverage</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <p>We ship to 50+ countries worldwide including:</p>
                                <ul class="list-disc list-inside mt-1 space-y-1">
                                    <li><strong>Europe:</strong> UK, Germany, France, Italy, Spain, Netherlands, and more</li>
                                    <li><strong>Asia Pacific:</strong> Japan, Australia, Singapore, South Korea, and more</li>
                                    <li><strong>Americas:</strong> Canada, Mexico, Brazil, and more</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 mb-2">International Standard</h3>
                        <p class="text-gray-600 text-sm mb-2">7-21 business days</p>
                        <p class="text-blue-600 font-medium">$15.99 - $49.99</p>
                        <p class="text-xs text-gray-500 mt-2">Tracking included</p>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 mb-2">International Express</h3>
                        <p class="text-gray-600 text-sm mb-2">3-7 business days</p>
                        <p class="text-blue-600 font-medium">$39.99 - $89.99</p>
                        <p class="text-xs text-gray-500 mt-2">Priority handling</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shipping Rates -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-calculator text-blue-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">How Shipping Rates Are Calculated</h2>
            </div>
            <div class="prose max-w-none">
                <p class="text-gray-700 leading-relaxed mb-6">
                    <?= nl2br(htmlspecialchars($shippingInfo['shipping_rates_info'] ?? 'Shipping rates are calculated based on package weight, dimensions, destination, and selected shipping method. Free shipping is available on orders over $50 for domestic shipments.')) ?>
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Rate Factors</h3>
                        <ul class="space-y-2 text-gray-700">
                            <li class="flex items-center"><i class="fas fa-weight text-blue-600 mr-2"></i> Package weight</li>
                            <li class="flex items-center"><i class="fas fa-cube text-blue-600 mr-2"></i> Package dimensions</li>
                            <li class="flex items-center"><i class="fas fa-map-marker-alt text-blue-600 mr-2"></i> Destination distance</li>
                            <li class="flex items-center"><i class="fas fa-shipping-fast text-blue-600 mr-2"></i> Shipping speed</li>
                            <li class="flex items-center"><i class="fas fa-shield-alt text-blue-600 mr-2"></i> Insurance (optional)</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Free Shipping</h3>
                        <ul class="space-y-2 text-gray-700">
                            <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i> Orders over $50 (US)</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i> Orders over $100 (Canada)</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i> Orders over $150 (International)</li>
                            <li class="flex items-center"><i class="fas fa-star text-yellow-600 mr-2"></i> Premium members always</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tracking -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-search-location text-blue-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Package Tracking</h2>
            </div>
            <div class="prose max-w-none">
                <p class="text-gray-700 leading-relaxed mb-6">
                    <?= nl2br(htmlspecialchars($shippingInfo['shipping_tracking_info'] ?? 'All shipments include tracking information. You will receive a tracking number via email once your order ships. Track your package on our website or the carrier\'s website.')) ?>
                </p>
                
                <div class="bg-gray-50 rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Track Your Order</h3>
                    <div class="flex space-x-4">
                        <input type="text" placeholder="Enter tracking number" 
                               class="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <button class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                            Track Package
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Restrictions -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Shipping Restrictions</h2>
            </div>
            <div class="prose max-w-none">
                <p class="text-gray-700 leading-relaxed mb-6">
                    <?= nl2br(htmlspecialchars($shippingInfo['shipping_restrictions'] ?? 'Some items may have shipping restrictions due to size, weight, or regulatory requirements. Hazardous materials and certain electronics may require special handling.')) ?>
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Restricted Items</h3>
                        <ul class="space-y-2 text-gray-700 text-sm">
                            <li class="flex items-center"><i class="fas fa-times text-red-600 mr-2"></i> Hazardous materials</li>
                            <li class="flex items-center"><i class="fas fa-times text-red-600 mr-2"></i> Flammable liquids</li>
                            <li class="flex items-center"><i class="fas fa-times text-red-600 mr-2"></i> Perishable food items</li>
                            <li class="flex items-center"><i class="fas fa-times text-red-600 mr-2"></i> Live animals</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Special Handling</h3>
                        <ul class="space-y-2 text-gray-700 text-sm">
                            <li class="flex items-center"><i class="fas fa-exclamation text-yellow-600 mr-2"></i> Fragile items (+$5 fee)</li>
                            <li class="flex items-center"><i class="fas fa-exclamation text-yellow-600 mr-2"></i> Oversized packages</li>
                            <li class="flex items-center"><i class="fas fa-exclamation text-yellow-600 mr-2"></i> High-value items</li>
                            <li class="flex items-center"><i class="fas fa-exclamation text-yellow-600 mr-2"></i> Temperature-sensitive</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-question-circle text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Need Help with Shipping?</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>Have questions about shipping options or need assistance with your order?</p>
                        <div class="mt-3 space-x-4">
                            <a href="contact.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm">
                                Contact Support
                            </a>
                            <a href="faq.php" class="text-blue-600 hover:text-blue-800 text-sm">
                                View FAQ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12 mt-16">
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
</body>
</html>
