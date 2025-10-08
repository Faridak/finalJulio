<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_contact_info') {
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $hours = trim($_POST['hours'] ?? '');
        $supportEmail = trim($_POST['support_email'] ?? '');
        $salesEmail = trim($_POST['sales_email'] ?? '');
        
        // Update or insert contact information
        $stmt = $pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value) VALUES 
            ('contact_phone', ?),
            ('contact_email', ?),
            ('contact_address', ?),
            ('business_hours', ?),
            ('support_email', ?),
            ('sales_email', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        if ($stmt->execute([$phone, $email, $address, $hours, $supportEmail, $salesEmail])) {
            $success = 'Contact information updated successfully!';
        } else {
            $error = 'Failed to update contact information.';
        }
    }
}

// Get current contact information
$contactInfo = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('contact_phone', 'contact_email', 'contact_address', 'business_hours', 'support_email', 'sales_email')");
while ($row = $stmt->fetch()) {
    $contactInfo[$row['setting_key']] = $row['setting_value'];
}

// Get recent contact messages
$recentMessages = $pdo->query("
    SELECT cm.*, u.email as user_email 
    FROM contact_messages cm 
    LEFT JOIN users u ON cm.user_id = u.id 
    ORDER BY cm.created_at DESC 
    LIMIT 20
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Management - VentDepot Admin</title>
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
                    <a href="../index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                    <span class="text-gray-400">|</span>
                    <a href="dashboard.php" class="text-lg font-semibold text-red-600 hover:text-red-700">Admin Panel</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Contact Management</h1>
                <p class="text-gray-600 mt-2">Manage contact information and customer messages</p>
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

        <!-- Contact Information Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Contact Information</h2>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="update_contact_info">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                        <input type="tel" name="phone" id="phone" 
                               value="<?= htmlspecialchars($contactInfo['contact_phone'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                               placeholder="1-800-VENTDEPOT">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">General Email</label>
                        <input type="email" name="email" id="email" 
                               value="<?= htmlspecialchars($contactInfo['contact_email'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                               placeholder="info@ventdepot.com">
                    </div>
                    
                    <div>
                        <label for="support_email" class="block text-sm font-medium text-gray-700 mb-2">Support Email</label>
                        <input type="email" name="support_email" id="support_email" 
                               value="<?= htmlspecialchars($contactInfo['support_email'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                               placeholder="support@ventdepot.com">
                    </div>
                    
                    <div>
                        <label for="sales_email" class="block text-sm font-medium text-gray-700 mb-2">Sales Email</label>
                        <input type="email" name="sales_email" id="sales_email" 
                               value="<?= htmlspecialchars($contactInfo['sales_email'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                               placeholder="sales@ventdepot.com">
                    </div>
                </div>
                
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Business Address</label>
                    <textarea name="address" id="address" rows="3"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                              placeholder="123 Business St, City, State 12345"><?= htmlspecialchars($contactInfo['contact_address'] ?? '') ?></textarea>
                </div>
                
                <div>
                    <label for="hours" class="block text-sm font-medium text-gray-700 mb-2">Business Hours</label>
                    <textarea name="hours" id="hours" rows="3"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                              placeholder="Monday - Friday: 9:00 AM - 6:00 PM EST&#10;Saturday: 10:00 AM - 4:00 PM EST&#10;Sunday: Closed"><?= htmlspecialchars($contactInfo['business_hours'] ?? '') ?></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Update Contact Information
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Contact Messages -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Recent Contact Messages</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($recentMessages)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No contact messages yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentMessages as $message): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= date('M j, Y', strtotime($message['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($message['name']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($message['email']) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($message['subject']) ?></div>
                                        <div class="text-sm text-gray-500 truncate max-w-xs">
                                            <?= htmlspecialchars(substr($message['message'], 0, 100)) ?>...
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?= $message['status'] === 'replied' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= ucfirst($message['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
