<?php
require_once 'config/database.php';

$success = '';
$error = '';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $department = $_POST['department'] ?? 'general';
    
    if ($name && $email && $subject && $message) {
        $userId = $_SESSION['user_id'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO contact_messages (user_id, name, email, phone, subject, message, department) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$userId, $name, $email, $phone, $subject, $message, $department])) {
            $success = 'Thank you for contacting us! We will respond within 24 hours.';
        } else {
            $error = 'Failed to send message. Please try again.';
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Get contact information from settings
$contactInfo = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'contact_%' OR setting_key = 'business_hours'");
while ($row = $stmt->fetch()) {
    $contactInfo[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - VentDepot</title>
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

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Contact Us</h1>
            <p class="text-xl text-gray-600">We're here to help! Get in touch with our team.</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 max-w-2xl mx-auto">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 max-w-2xl mx-auto">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Contact Form -->
            <div class="bg-white rounded-lg shadow-md p-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-6">Send us a Message</h2>
                
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                            <input type="text" name="name" id="name" required
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                            <input type="email" name="email" id="email" required
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" name="phone" id="phone"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="department" class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                            <select name="department" id="department"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="general" <?= ($_POST['department'] ?? '') === 'general' ? 'selected' : '' ?>>General Inquiry</option>
                                <option value="support" <?= ($_POST['department'] ?? '') === 'support' ? 'selected' : '' ?>>Customer Support</option>
                                <option value="sales" <?= ($_POST['department'] ?? '') === 'sales' ? 'selected' : '' ?>>Sales</option>
                                <option value="billing" <?= ($_POST['department'] ?? '') === 'billing' ? 'selected' : '' ?>>Billing</option>
                                <option value="technical" <?= ($_POST['department'] ?? '') === 'technical' ? 'selected' : '' ?>>Technical Support</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject *</label>
                        <input type="text" name="subject" id="subject" required
                               value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
                        <textarea name="message" id="message" rows="6" required
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-3 px-6 rounded-md hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-paper-plane mr-2"></i>Send Message
                    </button>
                </form>
            </div>

            <!-- Contact Information -->
            <div class="space-y-8">
                <!-- Contact Details -->
                <div class="bg-white rounded-lg shadow-md p-8">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6">Get in Touch</h2>
                    
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-phone text-blue-600 text-xl mt-1"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-medium text-gray-900">Phone</h3>
                                <p class="text-gray-600"><?= htmlspecialchars($contactInfo['contact_phone'] ?? '1-800-VENTDEPOT') ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-envelope text-blue-600 text-xl mt-1"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-medium text-gray-900">Email</h3>
                                <p class="text-gray-600"><?= htmlspecialchars($contactInfo['contact_email'] ?? 'info@ventdepot.com') ?></p>
                                <p class="text-gray-600 text-sm">Support: <?= htmlspecialchars($contactInfo['support_email'] ?? 'support@ventdepot.com') ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-blue-600 text-xl mt-1"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-medium text-gray-900">Address</h3>
                                <p class="text-gray-600 whitespace-pre-line"><?= htmlspecialchars($contactInfo['contact_address'] ?? "123 Business Street\nLos Angeles, CA 90210\nUnited States") ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-clock text-blue-600 text-xl mt-1"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-medium text-gray-900">Business Hours</h3>
                                <p class="text-gray-600 whitespace-pre-line"><?= htmlspecialchars($contactInfo['business_hours'] ?? "Monday - Friday: 9:00 AM - 6:00 PM PST\nSaturday: 10:00 AM - 4:00 PM PST\nSunday: Closed") ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="bg-white rounded-lg shadow-md p-8">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6">Quick Help</h2>
                    
                    <div class="space-y-4">
                        <a href="faq.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                            <i class="fas fa-question-circle text-blue-600 text-xl mr-4"></i>
                            <div>
                                <h3 class="font-medium text-gray-900">FAQ</h3>
                                <p class="text-sm text-gray-600">Find answers to common questions</p>
                            </div>
                        </a>
                        
                        <a href="shipping-info.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                            <i class="fas fa-shipping-fast text-blue-600 text-xl mr-4"></i>
                            <div>
                                <h3 class="font-medium text-gray-900">Shipping Information</h3>
                                <p class="text-sm text-gray-600">Learn about our shipping policies</p>
                            </div>
                        </a>
                        
                        <a href="returns.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                            <i class="fas fa-undo text-blue-600 text-xl mr-4"></i>
                            <div>
                                <h3 class="font-medium text-gray-900">Returns & Refunds</h3>
                                <p class="text-sm text-gray-600">Information about returns and refunds</p>
                            </div>
                        </a>
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
