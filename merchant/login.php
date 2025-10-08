<?php
require_once '../config/database.php';

$error = '';

// Redirect if already logged in as merchant
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'merchant') {
    header('Location: dashboard.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'merchant'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password, or account is not a merchant account.';
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Login - VentDepot</title>
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
                </div>
                
                <div class="flex items-center space-x-6">
                    <a href="../index.php" class="text-gray-600 hover:text-blue-600">Home</a>
                    <a href="../seller-guide.php" class="text-gray-600 hover:text-blue-600">Seller Guide</a>
                    <a href="register.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Become a Seller</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <i class="fas fa-store text-blue-600 text-6xl mb-4"></i>
                <h2 class="text-3xl font-bold text-gray-900">Merchant Login</h2>
                <p class="mt-2 text-gray-600">Access your seller dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <div class="bg-white rounded-lg shadow-md p-8">
                <form method="POST" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                        </label>
                        <input type="email" name="email" id="email" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your email">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <input type="password" name="password" id="password" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your password">
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input type="checkbox" id="remember_me" name="remember_me"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember_me" class="ml-2 text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>

                        <div class="text-sm">
                            <a href="../forgot-password.php" class="text-blue-600 hover:text-blue-800">
                                Forgot your password?
                            </a>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                        <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                    </button>
                </form>

                <!-- Divider -->
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">New to VentDepot?</span>
                        </div>
                    </div>
                </div>

                <!-- Register Link -->
                <div class="mt-6 text-center">
                    <a href="register.php" 
                       class="w-full bg-gray-100 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-200 transition duration-200 inline-block">
                        <i class="fas fa-user-plus mr-2"></i>Apply to Become a Seller
                    </a>
                </div>
            </div>

            <!-- Help Section -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-question-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Need Help?</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>Having trouble accessing your merchant account?</p>
                            <div class="mt-3 space-x-4">
                                <a href="../contact.php" class="text-blue-600 hover:text-blue-800">
                                    Contact Support
                                </a>
                                <a href="../seller-guide.php" class="text-blue-600 hover:text-blue-800">
                                    Seller Guide
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Merchant Dashboard Features</h3>
                <div class="space-y-3">
                    <div class="flex items-center">
                        <i class="fas fa-chart-bar text-blue-600 mr-3"></i>
                        <span class="text-gray-700">Sales Analytics & Reports</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-boxes text-green-600 mr-3"></i>
                        <span class="text-gray-700">Inventory Management</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-shopping-cart text-purple-600 mr-3"></i>
                        <span class="text-gray-700">Order Processing</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-shipping-fast text-orange-600 mr-3"></i>
                        <span class="text-gray-700">Global Shipping Tools</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-credit-card text-red-600 mr-3"></i>
                        <span class="text-gray-700">Payment Management</span>
                    </div>
                </div>
            </div>

            <!-- Customer Login Link -->
            <div class="text-center">
                <p class="text-sm text-gray-600">
                    Looking for customer login? 
                    <a href="../login.php" class="text-blue-600 hover:text-blue-800">
                        Customer Login
                    </a>
                </p>
            </div>
        </div>
    </div>

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
                        <li><a href="../contact.php" class="hover:text-white">Contact Us</a></li>
                        <li><a href="../shipping-info.php" class="hover:text-white">Shipping Info</a></li>
                        <li><a href="../returns.php" class="hover:text-white">Returns</a></li>
                        <li><a href="../faq.php" class="hover:text-white">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">For Merchants</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="register.php" class="hover:text-white">Become a Seller</a></li>
                        <li><a href="login.php" class="hover:text-white">Merchant Login</a></li>
                        <li><a href="../seller-guide.php" class="hover:text-white">Seller Guide</a></li>
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
