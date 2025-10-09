<?php
// Chat System for Sales and Engineers
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_email = $_SESSION['user_email'];

// Handle sending messages
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $recipient_id = $_POST['recipient_id'];
    $message = trim($_POST['message']);
    
    if (!empty($message) && !empty($recipient_id)) {
        try {
            // Insert message into database
            $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, recipient_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $recipient_id, $message]);
            
            // Create notification for recipient
            $stmt = $pdo->prepare("INSERT INTO chat_notifications (user_id, sender_id, message_preview) VALUES (?, ?, ?)");
            $stmt->execute([$recipient_id, $user_id, substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')]);
            
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Message and recipient are required']);
        exit;
    }
}

// Fetch chat partners (engineers for sales, sales for engineers)
try {
    if ($user_role === 'admin') {
        // Admins can chat with all users
        $stmt = $pdo->prepare("
            SELECT id, email, role 
            FROM users 
            WHERE id != ? 
            ORDER BY role, email
        ");
        $stmt->execute([$user_id]);
    } elseif (strpos($user_email, 'sales') !== false) {
        // Sales users can chat with engineers
        $stmt = $pdo->prepare("
            SELECT id, email, role 
            FROM users 
            WHERE email LIKE '%engineer%' 
            ORDER BY email
        ");
        $stmt->execute();
    } elseif (strpos($user_email, 'engineer') !== false) {
        // Engineers can chat with sales
        $stmt = $pdo->prepare("
            SELECT id, email, role 
            FROM users 
            WHERE email LIKE '%sales%' 
            ORDER BY email
        ");
        $stmt->execute();
    } else {
        // Other users can chat with admins
        $stmt = $pdo->prepare("
            SELECT id, email, role 
            FROM users 
            WHERE role = 'admin' 
            ORDER BY email
        ");
        $stmt->execute();
    }
    
    $chat_partners = $stmt->fetchAll();
    
    // Fetch recent unread chat notifications
    $stmt = $pdo->prepare("
        SELECT cn.*, u.email as sender_email
        FROM chat_notifications cn
        JOIN users u ON cn.sender_id = u.id
        WHERE cn.user_id = ? AND cn.is_read = 0
        ORDER BY cn.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Error fetching chat data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .chat-container {
            height: calc(100vh - 200px);
        }
        
        .chat-messages {
            height: calc(100% - 100px);
            overflow-y: auto;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 18px;
            margin-bottom: 10px;
        }
        
        .sent-message {
            background-color: #3b82f6;
            color: white;
            margin-left: auto;
        }
        
        .received-message {
            background-color: #e5e7eb;
            color: black;
            margin-right: auto;
        }
        
        .chat-partner {
            transition: all 0.2s ease;
        }
        
        .chat-partner:hover {
            background-color: #f3f4f6;
        }
        
        .chat-partner.active {
            background-color: #dbeafe;
            border-left: 3px solid #3b82f6;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">Team Chat</h1>
            <div class="flex space-x-2">
                <a href="sales-dashboard.php" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md chat-container">
            <div class="flex h-full">
                <!-- Chat Partners Sidebar -->
                <div class="w-full md:w-1/3 border-r border-gray-200">
                    <div class="p-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">Contacts</h2>
                    </div>
                    
                    <div class="overflow-y-auto" style="height: calc(100% - 60px);">
                        <?php if (!empty($chat_partners)): ?>
                            <?php foreach ($chat_partners as $partner): ?>
                                <div class="chat-partner p-4 border-b border-gray-100 cursor-pointer relative" 
                                     onclick="selectChatPartner(<?php echo $partner['id']; ?>, '<?php echo htmlspecialchars($partner['email']); ?>')">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-user text-blue-600"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(explode('@', $partner['email'])[0]); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo ucfirst($partner['role']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <!-- Notification badge would go here in a full implementation -->
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-gray-500">
                                <p>No chat partners available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Chat Area -->
                <div class="hidden md:block w-2/3" id="chatArea">
                    <div class="flex flex-col h-full">
                        <div class="p-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800" id="chatPartnerName">Select a contact to start chatting</h2>
                        </div>
                        
                        <div class="flex-1 chat-messages p-4 bg-gray-50" id="chatMessages">
                            <div class="h-full flex items-center justify-center text-gray-500">
                                <div class="text-center">
                                    <i class="fas fa-comments text-4xl mb-2"></i>
                                    <p>Select a contact to start chatting</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-4 border-t border-gray-200">
                            <div class="flex">
                                <input type="text" id="messageInput" 
                                       class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                       placeholder="Type your message..." 
                                       disabled>
                                <input type="hidden" id="recipientId">
                                <button id="sendMessageBtn" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-r-lg hover:bg-blue-700 disabled:opacity-50" 
                                        disabled>
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Select chat partner
        function selectChatPartner(partnerId, partnerName) {
            // Update UI
            document.getElementById('chatArea').classList.remove('hidden');
            document.getElementById('chatPartnerName').textContent = partnerName;
            document.getElementById('recipientId').value = partnerId;
            document.getElementById('messageInput').disabled = false;
            document.getElementById('sendMessageBtn').disabled = false;
            
            // Highlight selected partner
            document.querySelectorAll('.chat-partner').forEach(partner => {
                partner.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Load chat messages (in a real implementation, this would fetch messages from the server)
            loadChatMessages(partnerId);
        }
        
        // Load chat messages
        function loadChatMessages(partnerId) {
            // In a real implementation, this would fetch messages from the server
            document.getElementById('chatMessages').innerHTML = `
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-comment-slash text-2xl mb-2"></i>
                    <p>No messages yet. Start the conversation!</p>
                </div>
            `;
            
            // Scroll to bottom
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Send message
        document.getElementById('sendMessageBtn').addEventListener('click', sendMessage);
        document.getElementById('messageInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        function sendMessage() {
            const messageInput = document.getElementById('messageInput');
            const recipientId = document.getElementById('recipientId').value;
            const message = messageInput.value.trim();
            
            if (message && recipientId) {
                // Send message via AJAX
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('recipient_id', recipientId);
                formData.append('message', message);
                
                fetch('chat.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Add message to chat
                        addMessageToChat(message, true);
                        messageInput.value = '';
                    } else {
                        alert('Error sending message: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error sending message');
                });
            }
        }
        
        // Add message to chat display
        function addMessageToChat(message, isSent) {
            const chatMessages = document.getElementById('chatMessages');
            
            // Remove placeholder if it exists
            if (chatMessages.querySelector('.text-center')) {
                chatMessages.innerHTML = '';
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message-bubble ${isSent ? 'sent-message' : 'received-message'}`;
            messageDiv.textContent = message;
            
            chatMessages.appendChild(messageDiv);
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Handle real-time updates (in a full implementation, this would use WebSockets)
        function checkForNewMessages() {
            // This would check for new messages periodically
            // In a real implementation, you would use WebSockets for real-time updates
        }
        
        // Check for new messages every 30 seconds
        setInterval(checkForNewMessages, 30000);
    </script>
</body>
</html>