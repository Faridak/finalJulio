<?php
require_once 'config/database.php';

// Get FAQs from database
$faqs = $pdo->query("
    SELECT * FROM faqs 
    WHERE is_active = TRUE 
    ORDER BY category, sort_order, id
")->fetchAll();

// Group FAQs by category
$faqsByCategory = [];
foreach ($faqs as $faq) {
    $category = $faq['category'] ?: 'General';
    $faqsByCategory[$category][] = $faq;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frequently Asked Questions - VentDepot</title>
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
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Frequently Asked Questions</h1>
            <p class="text-xl text-gray-600">Find answers to common questions about VentDepot</p>
        </div>

        <!-- Search Box -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center space-x-4">
                <i class="fas fa-search text-gray-400 text-xl"></i>
                <input type="text" id="faqSearch" placeholder="Search FAQs..." 
                       class="flex-1 border-0 focus:ring-0 text-lg"
                       onkeyup="searchFAQs()">
            </div>
        </div>

        <?php if (empty($faqsByCategory)): ?>
            <!-- No FAQs Available -->
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <i class="fas fa-question-circle text-gray-400 text-6xl mb-4"></i>
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">No FAQs Available</h2>
                <p class="text-gray-600 mb-6">We're working on adding frequently asked questions. In the meantime, feel free to contact us directly.</p>
                <a href="contact.php" class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700">
                    Contact Support
                </a>
            </div>
        <?php else: ?>
            <!-- FAQ Categories -->
            <div class="space-y-8">
                <?php foreach ($faqsByCategory as $category => $categoryFaqs): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                                <i class="<?php
                                    switch(strtolower($category)) {
                                        case 'shipping': echo 'fas fa-shipping-fast text-blue-600'; break;
                                        case 'returns': echo 'fas fa-undo text-green-600'; break;
                                        case 'payment': echo 'fas fa-credit-card text-purple-600'; break;
                                        case 'account': echo 'fas fa-user text-orange-600'; break;
                                        case 'products': echo 'fas fa-box text-yellow-600'; break;
                                        default: echo 'fas fa-question-circle text-gray-600';
                                    }
                                ?> mr-3"></i>
                                <?= htmlspecialchars($category) ?>
                            </h2>
                        </div>
                        
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($categoryFaqs as $faq): ?>
                                <div class="faq-item" x-data="{ open: false }">
                                    <button @click="open = !open" 
                                            class="w-full px-6 py-4 text-left hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition duration-200">
                                        <div class="flex justify-between items-center">
                                            <h3 class="font-medium text-gray-900 faq-question"><?= htmlspecialchars($faq['question']) ?></h3>
                                            <i class="fas fa-chevron-down text-gray-400 transform transition-transform duration-200"
                                               :class="{ 'rotate-180': open }"></i>
                                        </div>
                                    </button>
                                    
                                    <div x-show="open" x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95"
                                         class="px-6 pb-4">
                                        <div class="prose max-w-none">
                                            <p class="text-gray-700 faq-answer"><?= nl2br(htmlspecialchars($faq['answer'])) ?></p>
                                        </div>
                                        
                                        <!-- Helpful buttons -->
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <p class="text-sm text-gray-600 mb-2">Was this helpful?</p>
                                            <div class="flex space-x-2">
                                                <button onclick="markHelpful(<?= $faq['id'] ?>, true)" 
                                                        class="text-sm bg-green-100 text-green-700 px-3 py-1 rounded hover:bg-green-200">
                                                    <i class="fas fa-thumbs-up mr-1"></i>Yes
                                                </button>
                                                <button onclick="markHelpful(<?= $faq['id'] ?>, false)" 
                                                        class="text-sm bg-red-100 text-red-700 px-3 py-1 rounded hover:bg-red-200">
                                                    <i class="fas fa-thumbs-down mr-1"></i>No
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Still Need Help -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-12">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-headset text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Still Need Help?</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>Can't find what you're looking for? Our customer support team is here to help!</p>
                        <div class="mt-3 space-x-4">
                            <a href="contact.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm">
                                Contact Support
                            </a>
                            <a href="mailto:support@ventdepot.com" class="text-blue-600 hover:text-blue-800 text-sm">
                                Email Us
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Topics -->
        <div class="bg-white rounded-lg shadow-md p-8 mt-8">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">Popular Help Topics</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="shipping-info.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                    <i class="fas fa-shipping-fast text-blue-600 text-xl mr-4"></i>
                    <div>
                        <h3 class="font-medium text-gray-900">Shipping Information</h3>
                        <p class="text-sm text-gray-600">Rates, times, and policies</p>
                    </div>
                </a>
                
                <a href="returns.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                    <i class="fas fa-undo text-green-600 text-xl mr-4"></i>
                    <div>
                        <h3 class="font-medium text-gray-900">Returns & Refunds</h3>
                        <p class="text-sm text-gray-600">How to return items</p>
                    </div>
                </a>
                
                <a href="contact.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                    <i class="fas fa-user-circle text-purple-600 text-xl mr-4"></i>
                    <div>
                        <h3 class="font-medium text-gray-900">Account Help</h3>
                        <p class="text-sm text-gray-600">Login and account issues</p>
                    </div>
                </a>
                
                <a href="merchant/register.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                    <i class="fas fa-store text-orange-600 text-xl mr-4"></i>
                    <div>
                        <h3 class="font-medium text-gray-900">Become a Seller</h3>
                        <p class="text-sm text-gray-600">Start selling on VentDepot</p>
                    </div>
                </a>
                
                <a href="contact.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                    <i class="fas fa-credit-card text-red-600 text-xl mr-4"></i>
                    <div>
                        <h3 class="font-medium text-gray-900">Payment Issues</h3>
                        <p class="text-sm text-gray-600">Billing and payment help</p>
                    </div>
                </a>
                
                <a href="contact.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                    <i class="fas fa-cog text-gray-600 text-xl mr-4"></i>
                    <div>
                        <h3 class="font-medium text-gray-900">Technical Support</h3>
                        <p class="text-sm text-gray-600">Website and app issues</p>
                    </div>
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
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-instagram text-xl"></a>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 VentDepot. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function searchFAQs() {
            const searchTerm = document.getElementById('faqSearch').value.toLowerCase();
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = searchTerm === '' ? 'block' : 'none';
                }
            });
        }
        
        function markHelpful(faqId, isHelpful) {
            fetch('api/faq-feedback.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    faq_id: faqId,
                    is_helpful: isHelpful
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show thank you message
                    alert('Thank you for your feedback!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>
