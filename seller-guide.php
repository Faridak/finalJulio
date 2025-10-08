<?php
require_once 'config/database.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Guide - VentDepot</title>
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
                    <a href="merchant/register.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Become a Seller</a>
                    <a href="merchant/login.php" class="text-gray-600 hover:text-blue-600">Merchant Login</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Seller Guide</h1>
            <p class="text-xl text-gray-600">Everything you need to know about selling on VentDepot</p>
        </div>

        <!-- Getting Started -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-rocket text-blue-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Getting Started</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-6 border border-gray-200 rounded-lg">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-blue-600 font-bold text-xl">1</span>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Apply to Sell</h3>
                    <p class="text-gray-600 text-sm">Submit your application with business information and required documents</p>
                </div>
                
                <div class="text-center p-6 border border-gray-200 rounded-lg">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-green-600 font-bold text-xl">2</span>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Get Approved</h3>
                    <p class="text-gray-600 text-sm">Our team reviews your application within 2-3 business days</p>
                </div>
                
                <div class="text-center p-6 border border-gray-200 rounded-lg">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-purple-600 font-bold text-xl">3</span>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Start Selling</h3>
                    <p class="text-gray-600 text-sm">List your products and start reaching customers worldwide</p>
                </div>
            </div>
            
            <div class="text-center mt-8">
                <a href="merchant/register.php" class="bg-blue-600 text-white px-8 py-3 rounded-md hover:bg-blue-700 text-lg">
                    <i class="fas fa-store mr-2"></i>Start Your Application
                </a>
            </div>
        </div>

        <!-- Requirements -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-clipboard-check text-green-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Seller Requirements</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="font-semibold text-gray-900 mb-4">Basic Requirements</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                            <span class="text-gray-700">Valid business license or registration</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                            <span class="text-gray-700">Tax identification number (EIN or SSN)</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                            <span class="text-gray-700">Bank account for payments</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                            <span class="text-gray-700">Professional email address</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                            <span class="text-gray-700">Ability to fulfill orders promptly</span>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="font-semibold text-gray-900 mb-4">Product Standards</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                            <span class="text-gray-700">High-quality product images</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                            <span class="text-gray-700">Accurate product descriptions</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                            <span class="text-gray-700">Competitive pricing</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                            <span class="text-gray-700">Authentic, new products only</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                            <span class="text-gray-700">Compliance with safety regulations</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Fees and Pricing -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-dollar-sign text-purple-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Fees and Pricing</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Listing Fees</h3>
                    <div class="text-3xl font-bold text-green-600 mb-2">FREE</div>
                    <p class="text-gray-600 text-sm">No upfront costs to list your products</p>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Transaction Fee</h3>
                    <div class="text-3xl font-bold text-blue-600 mb-2">3.5%</div>
                    <p class="text-gray-600 text-sm">Only pay when you make a sale</p>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Payment Processing</h3>
                    <div class="text-3xl font-bold text-purple-600 mb-2">2.9%</div>
                    <p class="text-gray-600 text-sm">Standard payment processing fee</p>
                </div>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Total Cost Example</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>For a $100 sale: Transaction fee ($3.50) + Payment processing ($2.90) = $6.40 total fees</p>
                            <p class="mt-1"><strong>You keep: $93.60</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Best Practices -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-star text-yellow-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Best Practices for Success</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="font-semibold text-gray-900 mb-4">Product Optimization</h3>
                    <ul class="space-y-3 text-gray-700">
                        <li class="flex items-start">
                            <i class="fas fa-camera text-blue-600 mr-3 mt-1"></i>
                            <div>
                                <strong>High-Quality Photos:</strong> Use multiple angles, good lighting, and high resolution
                            </div>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-edit text-blue-600 mr-3 mt-1"></i>
                            <div>
                                <strong>Detailed Descriptions:</strong> Include specifications, materials, dimensions, and benefits
                            </div>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-tags text-blue-600 mr-3 mt-1"></i>
                            <div>
                                <strong>Competitive Pricing:</strong> Research market prices and offer value to customers
                            </div>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-search text-blue-600 mr-3 mt-1"></i>
                            <div>
                                <strong>SEO Keywords:</strong> Use relevant keywords in titles and descriptions
                            </div>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="font-semibold text-gray-900 mb-4">Customer Service</h3>
                    <ul class="space-y-3 text-gray-700">
                        <li class="flex items-start">
                            <i class="fas fa-clock text-green-600 mr-3 mt-1"></i>
                            <div>
                                <strong>Fast Response:</strong> Reply to customer inquiries within 24 hours
                            </div>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-shipping-fast text-green-600 mr-3 mt-1"></i>
                            <div>
                                <strong>Quick Shipping:</strong> Process orders within 1-2 business days
                            </div>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-shield-alt text-green-600 mr-3 mt-1"></i>
                            <div>
                                <strong>Secure Packaging:</strong> Protect items during shipping to prevent damage
                            </div>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-smile text-green-600 mr-3 mt-1"></i>
                            <div>
                                <strong>Professional Service:</strong> Be courteous and helpful in all interactions
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Seller Tools -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-tools text-orange-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Seller Tools & Features</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="border border-gray-200 rounded-lg p-6">
                    <i class="fas fa-chart-bar text-blue-600 text-2xl mb-4"></i>
                    <h3 class="font-semibold text-gray-900 mb-2">Analytics Dashboard</h3>
                    <p class="text-gray-600 text-sm">Track sales, views, and performance metrics</p>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-6">
                    <i class="fas fa-boxes text-green-600 text-2xl mb-4"></i>
                    <h3 class="font-semibold text-gray-900 mb-2">Inventory Management</h3>
                    <p class="text-gray-600 text-sm">Manage stock levels and product variations</p>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-6">
                    <i class="fas fa-shipping-fast text-purple-600 text-2xl mb-4"></i>
                    <h3 class="font-semibold text-gray-900 mb-2">Global Shipping</h3>
                    <p class="text-gray-600 text-sm">Reach customers worldwide with our shipping network</p>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-6">
                    <i class="fas fa-credit-card text-red-600 text-2xl mb-4"></i>
                    <h3 class="font-semibold text-gray-900 mb-2">Payment Processing</h3>
                    <p class="text-gray-600 text-sm">Secure payments with automatic deposits</p>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-6">
                    <i class="fas fa-headset text-yellow-600 text-2xl mb-4"></i>
                    <h3 class="font-semibold text-gray-900 mb-2">Seller Support</h3>
                    <p class="text-gray-600 text-sm">Dedicated support team for merchants</p>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-6">
                    <i class="fas fa-mobile-alt text-indigo-600 text-2xl mb-4"></i>
                    <h3 class="font-semibold text-gray-900 mb-2">Mobile App</h3>
                    <p class="text-gray-600 text-sm">Manage your store on the go</p>
                </div>
            </div>
        </div>

        <!-- FAQ for Sellers -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-question-circle text-gray-600 text-2xl mr-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900">Frequently Asked Questions</h2>
            </div>
            
            <div class="space-y-4">
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2">How long does the approval process take?</h3>
                    <p class="text-gray-700">Most applications are reviewed within 2-3 business days. Complex applications may take up to 5 business days.</p>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2">When do I get paid?</h3>
                    <p class="text-gray-700">Payments are processed weekly on Fridays for all sales from the previous week. Funds typically arrive in your account within 2-3 business days.</p>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2">Can I sell internationally?</h3>
                    <p class="text-gray-700">Yes! Our global shipping network allows you to reach customers in 50+ countries worldwide.</p>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2">What products are prohibited?</h3>
                    <p class="text-gray-700">We prohibit illegal items, counterfeit goods, hazardous materials, and adult content. See our full policy for details.</p>
                </div>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-8 text-center text-white">
            <h2 class="text-3xl font-bold mb-4">Ready to Start Selling?</h2>
            <p class="text-xl mb-6">Join thousands of successful merchants on VentDepot</p>
            <div class="space-x-4">
                <a href="merchant/register.php" class="bg-white text-blue-600 px-8 py-3 rounded-md hover:bg-gray-100 font-semibold">
                    Apply Now
                </a>
                <a href="contact.php" class="border border-white text-white px-8 py-3 rounded-md hover:bg-white hover:text-blue-600 font-semibold">
                    Contact Sales
                </a>
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
