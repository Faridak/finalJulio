<?php
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/MessagingSystem.php';

// Require login
requireLogin();

$userId = $_SESSION['user_id'];
$messagingSystem = new MessagingSystem($pdo);

// Create additional tables if they don't exist
createUserBlocksTable($pdo);
createMessageReportsTable($pdo);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // CSRF Protection
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
        exit;
    }
    
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'send_message':
                $conversationId = intval($_POST['conversation_id']);
                $messageText = Security::sanitizeInput($_POST['message_text'], 'string');
                
                if (empty($messageText)) {
                    throw new Exception('Message cannot be empty');
                }
                
                // Rate limiting
                if (!Security::checkRateLimit('send_message', 30, 3600)) { // 30 messages per hour
                    throw new Exception('Message rate limit exceeded. Please slow down.');
                }
                
                $messageId = $messagingSystem->sendMessage($conversationId, $userId, $messageText);
                
                if ($messageId) {
                    echo json_encode(['success' => true, 'message_id' => $messageId]);
                } else {
                    throw new Exception('Failed to send message');
                }
                break;
                
            case 'start_conversation':
                $sellerId = intval($_POST['seller_id']);
                $subject = Security::sanitizeInput($_POST['subject'], 'string');
                $initialMessage = Security::sanitizeInput($_POST['initial_message'], 'string');
                $productId = intval($_POST['product_id'] ?? 0) ?: null;
                
                if (empty($subject) || empty($initialMessage)) {
                    throw new Exception('Subject and message are required');
                }
                
                // Rate limiting
                if (!Security::checkRateLimit('start_conversation', 5, 3600)) { // 5 new conversations per hour
                    throw new Exception('Conversation rate limit exceeded. Please wait.');
                }
                
                $conversationId = $messagingSystem->startConversation($userId, $sellerId, $subject, $productId);
                $messageId = $messagingSystem->sendMessage($conversationId, $userId, $initialMessage);
                
                if ($conversationId && $messageId) {
                    echo json_encode(['success' => true, 'conversation_id' => $conversationId]);
                } else {
                    throw new Exception('Failed to start conversation');
                }
                break;
                
            case 'mark_read':
                $conversationId = intval($_POST['conversation_id']);
                $result = $messagingSystem->markAsRead($conversationId, $userId);
                echo json_encode(['success' => $result]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get current conversation ID if viewing specific conversation
$currentConversationId = intval($_GET['conversation'] ?? 0);
$currentConversation = null;
$messages = [];

if ($currentConversationId) {
    try {
        $currentConversation = $messagingSystem->getConversation($currentConversationId, $userId);
        $messages = $messagingSystem->getConversationMessages($currentConversationId, $userId);
    } catch (Exception $e) {
        $currentConversationId = 0;
        $error = $e->getMessage();
    }
}

// Get user's conversations
$conversations = $messagingSystem->getUserConversations($userId);
$unreadCount = $messagingSystem->getUnreadCount($userId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md overflow-hidden" style="height: 600px;">
            <div class="flex h-full">
                <!-- Conversations Sidebar -->
                <div class="w-1/3 border-r border-gray-200 flex flex-col">
                    <!-- Header -->
                    <div class="p-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900">Messages</h2>
                            <?php if ($unreadCount > 0): ?>
                                <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="p-4 border-b border-gray-200">
                        <div class="relative">
                            <input type="text" placeholder="Search conversations..." 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Conversations List -->
                    <div class="flex-1 overflow-y-auto">
                        <?php if (empty($conversations)): ?>
                            <div class="p-4 text-center text-gray-500">
                                <i class="fas fa-comments text-3xl mb-2"></i>
                                <p>No conversations yet</p>
                                <p class="text-sm">Start a conversation with a seller!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($conversations as $conv): ?>
                                <a href="?conversation=<?= $conv['id'] ?>" 
                                   class="block p-4 border-b border-gray-100 hover:bg-gray-50 <?= $currentConversationId === $conv['id'] ? 'bg-blue-50 border-blue-200' : '' ?>">
                                    <div class="flex items-start space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                            <?= strtoupper(substr($conv['other_first_name'] ?: $conv['other_user_email'], 0, 1)) ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-sm font-medium text-gray-900 truncate">
                                                    <?= htmlspecialchars($conv['other_first_name'] . ' ' . $conv['other_last_name'] ?: $conv['other_user_email']) ?>
                                                </h3>
                                                <?php if ($conv['unread_count'] > 0): ?>
                                                    <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full"><?= $conv['unread_count'] ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-sm text-gray-600 truncate"><?= htmlspecialchars($conv['subject']) ?></p>
                                            <?php if ($conv['last_message']): ?>
                                                <p class="text-xs text-gray-500 truncate mt-1"><?= htmlspecialchars($conv['last_message']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($conv['product']): ?>
                                                <div class="flex items-center mt-2 text-xs text-blue-600">
                                                    <i class="fas fa-box mr-1"></i>
                                                    <span class="truncate"><?= htmlspecialchars($conv['product']['name']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <p class="text-xs text-gray-400 mt-1">
                                                <?= $conv['last_message_at'] ? timeAgo($conv['last_message_at']) : timeAgo($conv['created_at']) ?>
                                            </p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Message Area -->
                <div class="flex-1 flex flex-col">
                    <?php if ($currentConversation): ?>
                        <!-- Conversation Header -->
                        <div class="p-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                        <?= strtoupper(substr($currentConversation['other_first_name'] ?: $currentConversation['other_user_email'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-900">
                                            <?= htmlspecialchars($currentConversation['other_first_name'] . ' ' . $currentConversation['other_last_name'] ?: $currentConversation['other_user_email']) ?>
                                            <span class="text-xs text-gray-500 ml-2">(<?= ucfirst($currentConversation['other_user_role']) ?>)</span>
                                        </h3>
                                        <p class="text-sm text-gray-600"><?= htmlspecialchars($currentConversation['subject']) ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <?php if ($currentConversation['product']): ?>
                                        <a href="product.php?id=<?= $currentConversation['product']['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-sm">
                                            <i class="fas fa-external-link-alt mr-1"></i>
                                            View Product
                                        </a>
                                    <?php endif; ?>
                                    <button class="text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <?php if ($currentConversation['product']): ?>
                                <div class="mt-3 p-3 bg-gray-50 rounded-lg flex items-center space-x-3">
                                    <img src="<?= htmlspecialchars($currentConversation['product']['image_url'] ?: 'https://via.placeholder.com/60x60') ?>" 
                                         alt="<?= htmlspecialchars($currentConversation['product']['name']) ?>"
                                         class="w-12 h-12 object-cover rounded">
                                    <div>
                                        <h4 class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($currentConversation['product']['name']) ?></h4>
                                        <p class="text-sm text-blue-600 font-semibold">$<?= number_format($currentConversation['product']['price'], 2) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Messages -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="messages-container">
                            <?php foreach ($messages as $message): ?>
                                <div class="flex <?= $message['sender_id'] == $userId ? 'justify-end' : 'justify-start' ?>">
                                    <div class="max-w-xs lg:max-w-md">
                                        <?php if ($message['sender_id'] != $userId): ?>
                                            <div class="text-xs text-gray-500 mb-1">
                                                <?= htmlspecialchars($message['sender_first_name'] . ' ' . $message['sender_last_name'] ?: $message['sender_email']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="<?= $message['sender_id'] == $userId ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-900' ?> rounded-lg px-4 py-2">
                                            <p class="text-sm"><?= nl2br(htmlspecialchars($message['message_text'])) ?></p>
                                            <?php if ($message['attachment_url']): ?>
                                                <a href="<?= htmlspecialchars($message['attachment_url']) ?>" 
                                                   class="block mt-2 text-xs underline" target="_blank">
                                                    <i class="fas fa-paperclip mr-1"></i>Attachment
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-1 <?= $message['sender_id'] == $userId ? 'text-right' : 'text-left' ?>">
                                            <?= timeAgo($message['created_at']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Message Input -->
                        <div class="p-4 border-t border-gray-200">
                            <form id="message-form" class="flex space-x-2">
                                <?= Security::getCSRFInput() ?>
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="conversation_id" value="<?= $currentConversationId ?>">
                                <div class="flex-1">
                                    <textarea name="message_text" 
                                              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                              rows="2" 
                                              placeholder="Type your message..."
                                              required></textarea>
                                </div>
                                <button type="submit" 
                                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- No Conversation Selected -->
                        <div class="flex-1 flex items-center justify-center">
                            <div class="text-center text-gray-500">
                                <i class="fas fa-comments text-6xl mb-4"></i>
                                <h3 class="text-lg font-medium mb-2">Select a conversation</h3>
                                <p>Choose a conversation from the sidebar to start messaging</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of messages
        function scrollToBottom() {
            const container = document.getElementById('messages-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        // Send message via AJAX
        document.getElementById('message-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageText = formData.get('message_text').trim();
            
            if (!messageText) return;
            
            // Disable form while sending
            const textarea = this.querySelector('textarea');
            const button = this.querySelector('button');
            textarea.disabled = true;
            button.disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to show new message
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to send message'));
                    textarea.disabled = false;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending message');
                textarea.disabled = false;
                button.disabled = false;
            });
        });

        // Initial scroll to bottom
        window.addEventListener('load', scrollToBottom);

        // Auto-refresh conversations every 30 seconds
        setInterval(() => {
            // Only refresh if not currently typing
            const textarea = document.querySelector('textarea[name="message_text"]');
            if (!textarea || !textarea.value.trim()) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>

<?php
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>