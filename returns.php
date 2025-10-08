<?php
require_once 'config/database.php';

// Get returns information from settings
$returnsInfo = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('returns_policy', 'refund_policy', 'exchange_policy', 'return_process')");
while ($row = $stmt->fetch()) {
    $returnsInfo[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns & Refunds - VentDepot</title>
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
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Returns & Refunds</h1>
            <p class="text-xl text-gray-600">Easy returns and hassle-free refunds for your peace of mind</p>
        </div>

        <!-- Returns Policy -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-undo text-blue-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Returns Policy</h2>
            </div>
            <div class="prose max-w-none">
                <p class="text-gray-700 leading-relaxed mb-6">
                    <?= nl2br(htmlspecialchars($returnsInfo['returns_policy'] ?? 'We accept returns within 30 days of delivery for most items in original condition. Items must be unused, in original packaging, and include all accessories and documentation.')) ?>
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center p-6 border border-gray-200 rounded-lg">
                        <i class="fas fa-calendar-alt text-blue-600 text-3xl mb-4"></i>
                        <h3 class="font-semibold text-gray-900 mb-2">30-Day Window</h3>
                        <p class="text-gray-600 text-sm">Return items within 30 days of delivery</p>
                    </div>
                    
                    <div class="text-center p-6 border border-gray-200 rounded-lg">
                        <i class="fas fa-box text-blue-600 text-3xl mb-4"></i>
                        <h3 class="font-semibold text-gray-900 mb-2">Original Condition</h3>
                        <p class="text-gray-600 text-sm">Items must be unused and in original packaging</p>
                    </div>
                    
                    <div class="text-center p-6 border border-gray-200 rounded-lg">
                        <i class="fas fa-shipping-fast text-blue-600 text-3xl mb-4"></i>
                        <h3 class="font-semibold text-gray-900 mb-2">Free Return Shipping</h3>
                        <p class="text-gray-600 text-sm">We provide prepaid return labels</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Refund Policy -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-money-bill-wave text-green-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Refund Policy</h2>
            </div>
            <div class="prose max-w-none">
                <p class="text-gray-700 leading-relaxed mb-6">
                    <?= nl2br(htmlspecialchars($returnsInfo['refund_policy'] ?? 'Refunds are processed within 5-7 business days after we receive your returned item. Refunds will be issued to the original payment method. Shipping costs are non-refundable unless the return is due to our error.')) ?>
                </p>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                    <h3 class="font-semibold text-green-800 mb-4">Refund Timeline</h3>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                <span class="text-green-600 font-semibold text-sm">1</span>
                            </div>
                            <div>
                                <p class="font-medium text-green-800">Return Received</p>
                                <p class="text-green-600 text-sm">We inspect your returned item</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                <span class="text-green-600 font-semibold text-sm">2</span>
                            </div>
                            <div>
                                <p class="font-medium text-green-800">Refund Processed</p>
                                <p class="text-green-600 text-sm">Within 2-3 business days</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                <span class="text-green-600 font-semibold text-sm">3</span>
                            </div>
                            <div>
                                <p class="font-medium text-green-800">Money Back</p>
                                <p class="text-green-600 text-sm">3-5 business days to your account</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exchange Policy -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-exchange-alt text-purple-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Exchange Policy</h2>
            </div>
            <div class="prose max-w-none">
                <p class="text-gray-700 leading-relaxed mb-6">
                    <?= nl2br(htmlspecialchars($returnsInfo['exchange_policy'] ?? 'We offer exchanges for different sizes or colors when available. Exchanges are processed as returns and new orders to ensure fastest delivery of your preferred item.')) ?>
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 mb-3">Size Exchanges</h3>
                        <ul class="space-y-2 text-gray-700 text-sm">
                            <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i> Same product, different size</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i> No additional shipping cost</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i> Subject to availability</li>
                        </ul>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 mb-3">Color/Style Exchanges</h3>
                        <ul class="space-y-2 text-gray-700 text-sm">
                            <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i> Same product, different color</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i> Price difference may apply</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i> Fast processing</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Return Process -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-list-ol text-orange-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">How to Return an Item</h2>
            </div>
            <div class="prose max-w-none">
                <div class="space-y-6">
                    <?php 
                    $returnSteps = explode("\n", $returnsInfo['return_process'] ?? "1. Contact our customer service to initiate a return\n2. Print the prepaid return label we provide\n3. Package the item securely in original packaging\n4. Attach the return label and drop off at any authorized location\n5. Track your return and refund status online");
                    foreach ($returnSteps as $index => $step): 
                        if (trim($step)):
                    ?>
                        <div class="flex items-start">
                            <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center mr-4 mt-1">
                                <span class="text-orange-600 font-semibold"><?= $index + 1 ?></span>
                            </div>
                            <div class="flex-1">
                                <p class="text-gray-700"><?= htmlspecialchars(trim($step)) ?></p>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                
                <div class="mt-8 text-center">
                    <a href="contact.php" class="bg-orange-600 text-white px-8 py-3 rounded-md hover:bg-orange-700 transition duration-200">
                        <i class="fas fa-headset mr-2"></i>Start a Return
                    </a>
                </div>
            </div>
        </div>

        <!-- Non-Returnable Items -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-times-circle text-red-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Non-Returnable Items</h2>
            </div>
            <div class="prose max-w-none">
                <p class="text-gray-700 leading-relaxed mb-6">
                    For health and safety reasons, some items cannot be returned:
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Health & Safety Items</h3>
                        <ul class="space-y-2 text-gray-700 text-sm">
                            <li class="flex items-center"><i class="fas fa-times text-red-600 mr-2"></i> Personal care items</li>
                            <li class="flex items-center"><i class="fas fa-times text-red-600 mr-2"></i> Intimate apparel</li>
                            <li class="flex items-center"><i class="fas fa-times text-red-600 mr-2"></i> Perishable goods</li>
                            <li class="flex items-center"><i class="fas fa-times text-red-600 mr-2"></i> Custom/personalized items</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Digital & Final Sale</h3>
                        <ul class="space-y-2 text-gray-700 text-sm">
                            <li class="flex items-center"><i class="fas fa-times text-red-600 mr-2"></i> Digital downloads</li>
                            <li class="flex items-center"><i class="fas fa-times text-red-600 mr-2"></i> Gift cards</li>
                            <li class="flex items-center"><i class="fas fa-times text-red-600 mr-2"></i> Final sale items</li>
                            <li class="flex items-center"><i class="fas fa-times text-red-600 mr-2"></i> Items damaged by misuse</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Return Status Checker -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-search text-blue-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Check Return Status</h2>
            </div>
            <div class="prose max-w-none">
                <p class="text-gray-700 leading-relaxed mb-6">
                    Track the status of your return using your order number or return tracking number.
                </p>
                
                <div class="bg-gray-50 rounded-lg p-6">
                    <div class="flex space-x-4">
                        <input type="text" placeholder="Enter order number or return tracking number" 
                               class="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <button class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                            Check Status
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Support -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-question-circle text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Need Help with Returns?</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>Our customer service team is here to help with any questions about returns, refunds, or exchanges.</p>
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
