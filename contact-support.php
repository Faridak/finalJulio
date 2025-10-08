<?php
require_once 'config/database.php';

// Require login
requireLogin();

$userId = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $category = $_POST['category'] ?? 'other';
    $priority = $_POST['priority'] ?? 'medium';
    
    if (empty($subject) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Generate unique ticket ID
        $ticketId = 'TKT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insert support ticket
        $stmt = $pdo->prepare("
            INSERT INTO support_messages (user_id, ticket_id, subject, message, category, priority) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$userId, $ticketId, $subject, $message, $category, $priority])) {
            $success = "Support ticket created successfully! Your ticket ID is: <strong>$ticketId</strong><br>We'll respond within 24 hours.";
            // Clear form
            $_POST = [];
        } else {
            $error = 'Failed to create support ticket. Please try again.';
        }
    }
}

// Get user's recent tickets
$ticketsStmt = $pdo->prepare("
    SELECT * FROM support_messages 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$ticketsStmt->execute([$userId]);
$recentTickets = $ticketsStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Support - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Contact Support</h1>
            <p class="text-gray-600 mt-2">We're here to help! Get in touch with our support team.</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Support Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Submit a Support Request</h2>
                    
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                                <select name="category" id="category" required
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="order" <?= ($_POST['category'] ?? '') === 'order' ? 'selected' : '' ?>>Order Issues</option>
                                    <option value="product" <?= ($_POST['category'] ?? '') === 'product' ? 'selected' : '' ?>>Product Questions</option>
                                    <option value="payment" <?= ($_POST['category'] ?? '') === 'payment' ? 'selected' : '' ?>>Payment & Billing</option>
                                    <option value="shipping" <?= ($_POST['category'] ?? '') === 'shipping' ? 'selected' : '' ?>>Shipping & Delivery</option>
                                    <option value="account" <?= ($_POST['category'] ?? '') === 'account' ? 'selected' : '' ?>>Account Issues</option>
                                    <option value="other" <?= ($_POST['category'] ?? 'other') === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                                <select name="priority" id="priority"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="low" <?= ($_POST['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                                    <option value="medium" <?= ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="high" <?= ($_POST['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                                    <option value="urgent" <?= ($_POST['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject *</label>
                            <input type="text" name="subject" id="subject" required
                                   value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="Brief description of your issue">
                        </div>
                        
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
                            <textarea name="message" id="message" rows="6" required
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                      placeholder="Please provide detailed information about your issue..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition duration-200">
                                <i class="fas fa-paper-plane mr-2"></i>Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Support Info & FAQ -->
            <div class="space-y-6">
                <!-- Contact Information -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Contact Information</h3>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-blue-600 w-5"></i>
                            <span class="ml-3 text-sm text-gray-600">support@ventdepot.com</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-phone text-blue-600 w-5"></i>
                            <span class="ml-3 text-sm text-gray-600">1-800-VENTDEPOT</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-clock text-blue-600 w-5"></i>
                            <span class="ml-3 text-sm text-gray-600">Mon-Fri 9AM-6PM EST</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-comments text-blue-600 w-5"></i>
                            <span class="ml-3 text-sm text-gray-600">Live Chat Available</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Help -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Help</h3>
                    <div class="space-y-3">
                        <a href="#" class="block text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-question-circle mr-2"></i>Frequently Asked Questions
                        </a>
                        <a href="#" class="block text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-truck mr-2"></i>Track Your Order
                        </a>
                        <a href="#" class="block text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-undo mr-2"></i>Return & Refund Policy
                        </a>
                        <a href="#" class="block text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-shield-alt mr-2"></i>Privacy & Security
                        </a>
                    </div>
                </div>

                <!-- Response Time -->
                <div class="bg-blue-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-blue-900 mb-2">Response Times</h3>
                    <div class="space-y-2 text-sm text-blue-800">
                        <div class="flex justify-between">
                            <span>Low Priority:</span>
                            <span>48-72 hours</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Medium Priority:</span>
                            <span>24-48 hours</span>
                        </div>
                        <div class="flex justify-between">
                            <span>High Priority:</span>
                            <span>12-24 hours</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Urgent:</span>
                            <span>2-4 hours</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Tickets -->
        <?php if (!empty($recentTickets)): ?>
            <div class="mt-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Your Recent Support Tickets</h2>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($recentTickets as $ticket): ?>
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-lg font-medium text-gray-900"><?= htmlspecialchars($ticket['subject']) ?></h3>
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch($ticket['priority']) {
                                                case 'low': echo 'bg-gray-100 text-gray-800'; break;
                                                case 'medium': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'high': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'urgent': echo 'bg-red-100 text-red-800'; break;
                                            }
                                            ?>">
                                            <?= ucfirst($ticket['priority']) ?>
                                        </span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch($ticket['status']) {
                                                case 'open': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'in_progress': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'resolved': echo 'bg-green-100 text-green-800'; break;
                                                case 'closed': echo 'bg-gray-100 text-gray-800'; break;
                                            }
                                            ?>">
                                            <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-between text-sm text-gray-600 mb-3">
                                    <span>Ticket: <?= htmlspecialchars($ticket['ticket_id']) ?></span>
                                    <span>Category: <?= ucfirst($ticket['category']) ?></span>
                                    <span><?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></span>
                                </div>
                                
                                <p class="text-gray-700 mb-3"><?= htmlspecialchars(substr($ticket['message'], 0, 200)) ?>...</p>
                                
                                <?php if ($ticket['admin_response']): ?>
                                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mt-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-reply text-blue-400"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-blue-700">
                                                    <strong>Support Response:</strong>
                                                </p>
                                                <p class="text-sm text-blue-600 mt-1">
                                                    <?= htmlspecialchars($ticket['admin_response']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Live Chat Widget (Mock) -->
    <div class="fixed bottom-4 right-4 z-50" x-data="{ showChat: false }">
        <button @click="showChat = !showChat" 
                class="bg-blue-600 text-white p-4 rounded-full shadow-lg hover:bg-blue-700 transition duration-200">
            <i class="fas fa-comments text-xl"></i>
        </button>
        
        <div x-show="showChat" x-transition 
             class="absolute bottom-16 right-0 w-80 bg-white rounded-lg shadow-xl border border-gray-200">
            <div class="p-4 bg-blue-600 text-white rounded-t-lg">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold">Live Chat Support</h3>
                    <button @click="showChat = false" class="text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <div class="bg-gray-100 rounded-lg p-3 mb-2">
                        <p class="text-sm text-gray-700">Hi! How can we help you today?</p>
                    </div>
                </div>
                <div class="flex">
                    <input type="text" placeholder="Type your message..." 
                           class="flex-1 border border-gray-300 rounded-l-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <button class="bg-blue-600 text-white px-4 py-2 rounded-r-md hover:bg-blue-700">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
