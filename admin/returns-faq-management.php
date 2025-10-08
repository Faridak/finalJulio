<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_returns_policy') {
        $returnsPolicy = trim($_POST['returns_policy'] ?? '');
        $refundPolicy = trim($_POST['refund_policy'] ?? '');
        $exchangePolicy = trim($_POST['exchange_policy'] ?? '');
        $returnProcess = trim($_POST['return_process'] ?? '');
        
        // Update returns information
        $stmt = $pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value) VALUES 
            ('returns_policy', ?),
            ('refund_policy', ?),
            ('exchange_policy', ?),
            ('return_process', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        if ($stmt->execute([$returnsPolicy, $refundPolicy, $exchangePolicy, $returnProcess])) {
            $success = 'Returns policy updated successfully!';
        } else {
            $error = 'Failed to update returns policy.';
        }
    } elseif ($action === 'add_faq') {
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        
        if ($question && $answer) {
            $stmt = $pdo->prepare("
                INSERT INTO faqs (question, answer, category, sort_order, is_active) 
                VALUES (?, ?, ?, ?, TRUE)
            ");
            if ($stmt->execute([$question, $answer, $category, $sortOrder])) {
                $success = 'FAQ added successfully!';
            } else {
                $error = 'Failed to add FAQ.';
            }
        }
    } elseif ($action === 'update_faq') {
        $faqId = intval($_POST['faq_id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']);
        
        if ($faqId && $question && $answer) {
            $stmt = $pdo->prepare("
                UPDATE faqs 
                SET question = ?, answer = ?, category = ?, sort_order = ?, is_active = ?
                WHERE id = ?
            ");
            if ($stmt->execute([$question, $answer, $category, $sortOrder, $isActive, $faqId])) {
                $success = 'FAQ updated successfully!';
            } else {
                $error = 'Failed to update FAQ.';
            }
        }
    } elseif ($action === 'delete_faq') {
        $faqId = intval($_POST['faq_id'] ?? 0);
        if ($faqId) {
            $stmt = $pdo->prepare("DELETE FROM faqs WHERE id = ?");
            if ($stmt->execute([$faqId])) {
                $success = 'FAQ deleted successfully!';
            } else {
                $error = 'Failed to delete FAQ.';
            }
        }
    }
}

// Get current returns information
$returnsInfo = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('returns_policy', 'refund_policy', 'exchange_policy', 'return_process')");
while ($row = $stmt->fetch()) {
    $returnsInfo[$row['setting_key']] = $row['setting_value'];
}

// Get FAQs
$faqs = $pdo->query("SELECT * FROM faqs ORDER BY category, sort_order, id")->fetchAll();

// Get FAQ categories
$categories = $pdo->query("SELECT DISTINCT category FROM faqs WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns & FAQ Management - VentDepot Admin</title>
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
                <h1 class="text-3xl font-bold text-gray-900">Returns & FAQ Management</h1>
                <p class="text-gray-600 mt-2">Manage return policies and frequently asked questions</p>
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

        <!-- Tabs -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden" x-data="{ activeTab: 'returns' }">
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6">
                    <button @click="activeTab = 'returns'" 
                            :class="activeTab === 'returns' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-undo mr-2"></i>Returns Policy
                    </button>
                    <button @click="activeTab = 'faq'" 
                            :class="activeTab === 'faq' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-question-circle mr-2"></i>FAQ Management
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Returns Policy Tab -->
                <div x-show="activeTab === 'returns'" class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900">Returns & Refunds Policy</h3>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_returns_policy">
                        
                        <div>
                            <label for="returns_policy" class="block text-sm font-medium text-gray-700 mb-2">Returns Policy</label>
                            <textarea name="returns_policy" id="returns_policy" rows="5"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                      placeholder="Describe your returns policy, time limits, conditions, etc..."><?= htmlspecialchars($returnsInfo['returns_policy'] ?? '') ?></textarea>
                        </div>
                        
                        <div>
                            <label for="refund_policy" class="block text-sm font-medium text-gray-700 mb-2">Refund Policy</label>
                            <textarea name="refund_policy" id="refund_policy" rows="5"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                      placeholder="Describe your refund policy, processing times, refund methods, etc..."><?= htmlspecialchars($returnsInfo['refund_policy'] ?? '') ?></textarea>
                        </div>
                        
                        <div>
                            <label for="exchange_policy" class="block text-sm font-medium text-gray-700 mb-2">Exchange Policy</label>
                            <textarea name="exchange_policy" id="exchange_policy" rows="4"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                      placeholder="Describe your exchange policy, size exchanges, defective items, etc..."><?= htmlspecialchars($returnsInfo['exchange_policy'] ?? '') ?></textarea>
                        </div>
                        
                        <div>
                            <label for="return_process" class="block text-sm font-medium text-gray-700 mb-2">Return Process</label>
                            <textarea name="return_process" id="return_process" rows="6"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                      placeholder="Step-by-step instructions for customers on how to return items..."><?= htmlspecialchars($returnsInfo['return_process'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                                <i class="fas fa-save mr-2"></i>Update Returns Policy
                            </button>
                        </div>
                    </form>
                </div>

                <!-- FAQ Management Tab -->
                <div x-show="activeTab === 'faq'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">FAQ Management</h3>
                        <button onclick="document.getElementById('addFaqModal').classList.remove('hidden')"
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add FAQ
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <?php if (empty($faqs)): ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-question-circle text-4xl mb-4"></i>
                                <p>No FAQs yet. Add your first FAQ to get started.</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            $currentCategory = '';
                            foreach ($faqs as $faq): 
                                if ($faq['category'] !== $currentCategory):
                                    $currentCategory = $faq['category'];
                            ?>
                                <h4 class="text-md font-medium text-gray-900 mt-6 mb-3 border-b border-gray-200 pb-2">
                                    <?= htmlspecialchars($currentCategory ?: 'General') ?>
                                </h4>
                            <?php endif; ?>
                            
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h5 class="font-medium text-gray-900 mb-2"><?= htmlspecialchars($faq['question']) ?></h5>
                                        <p class="text-gray-600 text-sm"><?= nl2br(htmlspecialchars($faq['answer'])) ?></p>
                                        <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500">
                                            <span>Order: <?= $faq['sort_order'] ?></span>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                <?= $faq['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= $faq['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex space-x-2">
                                        <button onclick="editFaq(<?= htmlspecialchars(json_encode($faq)) ?>)" 
                                                class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this FAQ?')">
                                            <input type="hidden" name="action" value="delete_faq">
                                            <input type="hidden" name="faq_id" value="<?= $faq['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add FAQ Modal -->
    <div id="addFaqModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add New FAQ</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_faq">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <select name="category" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="">General</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                <?php endforeach; ?>
                                <option value="Shipping">Shipping</option>
                                <option value="Returns">Returns</option>
                                <option value="Payment">Payment</option>
                                <option value="Account">Account</option>
                                <option value="Products">Products</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Question</label>
                            <input type="text" name="question" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Answer</label>
                            <textarea name="answer" rows="4" required
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                            <input type="number" name="sort_order" value="0"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="document.getElementById('addFaqModal').classList.add('hidden')"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            Add FAQ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit FAQ Modal -->
    <div id="editFaqModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit FAQ</h3>
                <form method="POST" id="editFaqForm">
                    <input type="hidden" name="action" value="update_faq">
                    <input type="hidden" name="faq_id" id="edit_faq_id">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <select name="category" id="edit_category" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="">General</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                <?php endforeach; ?>
                                <option value="Shipping">Shipping</option>
                                <option value="Returns">Returns</option>
                                <option value="Payment">Payment</option>
                                <option value="Account">Account</option>
                                <option value="Products">Products</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Question</label>
                            <input type="text" name="question" id="edit_question" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Answer</label>
                            <textarea name="answer" id="edit_answer" rows="4" required
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                            <input type="number" name="sort_order" id="edit_sort_order"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="edit_is_active"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="edit_is_active" class="ml-2 text-sm text-gray-700">
                                Active (visible to customers)
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="document.getElementById('editFaqModal').classList.add('hidden')"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            Update FAQ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editFaq(faq) {
            document.getElementById('edit_faq_id').value = faq.id;
            document.getElementById('edit_category').value = faq.category || '';
            document.getElementById('edit_question').value = faq.question;
            document.getElementById('edit_answer').value = faq.answer;
            document.getElementById('edit_sort_order').value = faq.sort_order;
            document.getElementById('edit_is_active').checked = faq.is_active == 1;
            document.getElementById('editFaqModal').classList.remove('hidden');
        }
    </script>
</body>
</html>
