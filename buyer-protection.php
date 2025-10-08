<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/EscrowSystem.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$escrowSystem = new EscrowSystem($pdo);
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'customer';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRF();
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];
    
    switch ($action) {
        case 'confirm_receipt':
            $escrowId = intval($_POST['escrow_id']);
            $rating = intval($_POST['rating'] ?? 0);
            $feedback = Security::sanitizeInput($_POST['feedback'] ?? '', 'string');
            
            $result = $escrowSystem->confirmReceipt($escrowId, $userId, $rating, $feedback);
            $response = $result;
            break;
            
        case 'mark_shipped':
            if ($userRole === 'merchant') {
                $escrowId = intval($_POST['escrow_id']);
                $trackingNumber = Security::sanitizeInput($_POST['tracking_number'] ?? '', 'string');
                $carrier = Security::sanitizeInput($_POST['carrier'] ?? '', 'string');
                
                $result = $escrowSystem->markAsShipped($escrowId, $userId, $trackingNumber, $carrier);
                $response = $result;
            }
            break;
            
        case 'initiate_dispute':
            $escrowId = intval($_POST['escrow_id']);
            $reason = Security::sanitizeInput($_POST['reason'], 'string');
            $description = Security::sanitizeInput($_POST['description'], 'string');
            
            $result = $escrowSystem->initiateDispute($escrowId, $userId, $reason, $description);
            $response = $result;
            break;
    }
    
    // Return JSON response for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Redirect with message for regular form submissions
    $messageType = $response['success'] ? 'success' : 'error';
    $message = urlencode($response['message'] ?? $response['error'] ?? 'Action completed');
    header("Location: buyer-protection.php?{$messageType}=" . $message);
    exit;
}

// Get user's escrow transactions
if ($userRole === 'merchant') {
    $escrows = $escrowSystem->getSellerEscrows($userId);
    $pageTitle = "Seller Protection Dashboard";
} else {
    $escrows = $escrowSystem->getBuyerEscrows($userId);
    $pageTitle = "Buyer Protection Dashboard";
}

// Filter by status if requested
$statusFilter = $_GET['status'] ?? '';
if ($statusFilter) {
    $escrows = array_filter($escrows, function($escrow) use ($statusFilter) {
        return $escrow['status'] === $statusFilter;
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?= $pageTitle ?></h1>
                    <p class="text-gray-600 mt-1">
                        <?= $userRole === 'merchant' ? 
                            'Manage your sales and shipping status' : 
                            'Track your purchases and manage disputes' ?>
                    </p>
                </div>
                
                <div class="mt-4 md:mt-0">
                    <div class="flex items-center space-x-4 text-sm">
                        <div class="text-center">
                            <div class="text-lg font-semibold text-blue-600">
                                <?= count(array_filter($escrows, fn($e) => $e['status'] === 'active')) ?>
                            </div>
                            <div class="text-gray-600">Active</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-semibold text-green-600">
                                <?= count(array_filter($escrows, fn($e) => $e['status'] === 'released')) ?>
                            </div>
                            <div class="text-gray-600">Completed</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-semibold text-red-600">
                                <?= count(array_filter($escrows, fn($e) => $e['status'] === 'disputed')) ?>
                            </div>
                            <div class="text-gray-600">Disputed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <div class="flex flex-wrap gap-2">
                <a href="buyer-protection.php" 
                   class="px-3 py-2 text-sm rounded-lg <?= !$statusFilter ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                    All
                </a>
                <a href="buyer-protection.php?status=active" 
                   class="px-3 py-2 text-sm rounded-lg <?= $statusFilter === 'active' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                    Active
                </a>
                <a href="buyer-protection.php?status=shipped" 
                   class="px-3 py-2 text-sm rounded-lg <?= $statusFilter === 'shipped' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                    Shipped
                </a>
                <a href="buyer-protection.php?status=disputed" 
                   class="px-3 py-2 text-sm rounded-lg <?= $statusFilter === 'disputed' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                    Disputed
                </a>
                <a href="buyer-protection.php?status=released" 
                   class="px-3 py-2 text-sm rounded-lg <?= $statusFilter === 'released' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                    Completed
                </a>
            </div>
        </div>

        <!-- Escrow Transactions -->
        <div class="space-y-6">
            <?php if (empty($escrows)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-shield-alt text-gray-300 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Protected Transactions</h3>
                    <p class="text-gray-600">
                        <?= $userRole === 'merchant' ? 
                            'Your sales with buyer protection will appear here.' : 
                            'Your purchases with buyer protection will appear here.' ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($escrows as $escrow): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <h3 class="text-lg font-semibold text-gray-900">
                                            Order #<?= $escrow['order_number'] ?>
                                        </h3>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full 
                                            <?= match($escrow['status']) {
                                                'active' => 'bg-blue-100 text-blue-800',
                                                'shipped' => 'bg-yellow-100 text-yellow-800',
                                                'disputed' => 'bg-red-100 text-red-800',
                                                'released' => 'bg-green-100 text-green-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            } ?>">
                                            <i class="fas fa-circle mr-1 text-xs"></i>
                                            <?= ucfirst(str_replace('_', ' ', $escrow['status'])) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                                        <div>
                                            <p><strong>Amount:</strong> $<?= number_format($escrow['amount'], 2) ?></p>
                                            <p><strong>Date:</strong> <?= date('M j, Y', strtotime($escrow['order_date'])) ?></p>
                                        </div>
                                        <div>
                                            <p><strong><?= $userRole === 'merchant' ? 'Buyer' : 'Seller' ?>:</strong> 
                                               <?= htmlspecialchars($userRole === 'merchant' ? $escrow['buyer_name'] : $escrow['seller_name']) ?>
                                            </p>
                                            <p><strong>Protection Until:</strong> <?= date('M j, Y', strtotime($escrow['release_date'])) ?></p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($escrow['tracking_number']): ?>
                                        <div class="mt-3 p-3 bg-blue-50 rounded-lg">
                                            <p class="text-sm text-blue-800">
                                                <i class="fas fa-truck mr-2"></i>
                                                <strong>Tracking:</strong> <?= htmlspecialchars($escrow['tracking_number']) ?>
                                                <?php if ($escrow['carrier']): ?>
                                                    (<?= htmlspecialchars($escrow['carrier']) ?>)
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Actions -->
                                <div class="mt-4 lg:mt-0 lg:ml-6 flex flex-col space-y-2">
                                    <?php if ($userRole === 'customer'): ?>
                                        <!-- Buyer Actions -->
                                        <?php if ($escrow['status'] === 'shipped'): ?>
                                            <button onclick="openConfirmReceiptModal(<?= $escrow['id'] ?>)" 
                                                    class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm">
                                                <i class="fas fa-check mr-1"></i> Confirm Receipt
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($escrow['status'], ['active', 'shipped'])): ?>
                                            <button onclick="openDisputeModal(<?= $escrow['id'] ?>)" 
                                                    class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> Open Dispute
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Seller Actions -->
                                        <?php if ($escrow['status'] === 'active'): ?>
                                            <button onclick="openShippingModal(<?= $escrow['id'] ?>)" 
                                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                                                <i class="fas fa-shipping-fast mr-1"></i> Mark as Shipped
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <a href="order-details.php?id=<?= $escrow['order_number'] ?>" 
                                       class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 text-sm text-center">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Confirm Receipt Modal (for buyers) -->
    <div id="confirmReceiptModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Confirm Receipt</h3>
                
                <form id="confirmReceiptForm" method="POST">
                    <?= generateCSRFInput() ?>
                    <input type="hidden" name="action" value="confirm_receipt">
                    <input type="hidden" name="escrow_id" id="confirmEscrowId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rate the seller (optional)</label>
                        <div class="flex space-x-1" id="ratingStars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <button type="button" onclick="setRating(<?= $i ?>)" 
                                        class="rating-star text-gray-300 hover:text-yellow-400 text-xl">
                                    <i class="fas fa-star"></i>
                                </button>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="selectedRating" value="0">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Feedback (optional)</label>
                        <textarea name="feedback" rows="3" 
                                  class="w-full border border-gray-300 rounded px-3 py-2"
                                  placeholder="Share your experience with this seller..."></textarea>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-green-600 text-white py-2 px-4 rounded hover:bg-green-700">
                            Confirm Receipt & Release Payment
                        </button>
                        <button type="button" onclick="closeModal('confirmReceiptModal')" 
                                class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Shipping Modal (for sellers) -->
    <div id="shippingModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Mark as Shipped</h3>
                
                <form method="POST">
                    <?= generateCSRFInput() ?>
                    <input type="hidden" name="action" value="mark_shipped">
                    <input type="hidden" name="escrow_id" id="shippingEscrowId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tracking Number</label>
                        <input type="text" name="tracking_number" 
                               class="w-full border border-gray-300 rounded px-3 py-2"
                               placeholder="Enter tracking number">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Carrier</label>
                        <select name="carrier" class="w-full border border-gray-300 rounded px-3 py-2">
                            <option value="">Select carrier</option>
                            <option value="UPS">UPS</option>
                            <option value="FedEx">FedEx</option>
                            <option value="USPS">USPS</option>
                            <option value="DHL">DHL</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">
                            Mark as Shipped
                        </button>
                        <button type="button" onclick="closeModal('shippingModal')" 
                                class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dispute Modal (for buyers) -->
    <div id="disputeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Open Dispute</h3>
                
                <form method="POST">
                    <?= generateCSRFInput() ?>
                    <input type="hidden" name="action" value="initiate_dispute">
                    <input type="hidden" name="escrow_id" id="disputeEscrowId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Dispute</label>
                        <select name="reason" required class="w-full border border-gray-300 rounded px-3 py-2">
                            <option value="">Select a reason</option>
                            <option value="not_received">Item not received</option>
                            <option value="not_as_described">Item not as described</option>
                            <option value="damaged">Item arrived damaged</option>
                            <option value="counterfeit">Counterfeit item</option>
                            <option value="seller_unresponsive">Seller unresponsive</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="4" required
                                  class="w-full border border-gray-300 rounded px-3 py-2"
                                  placeholder="Please provide detailed information about the issue..."></textarea>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-red-600 text-white py-2 px-4 rounded hover:bg-red-700">
                            Open Dispute
                        </button>
                        <button type="button" onclick="closeModal('disputeModal')" 
                                class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openConfirmReceiptModal(escrowId) {
            document.getElementById('confirmEscrowId').value = escrowId;
            document.getElementById('confirmReceiptModal').classList.remove('hidden');
        }
        
        function openShippingModal(escrowId) {
            document.getElementById('shippingEscrowId').value = escrowId;
            document.getElementById('shippingModal').classList.remove('hidden');
        }
        
        function openDisputeModal(escrowId) {
            document.getElementById('disputeEscrowId').value = escrowId;
            document.getElementById('disputeModal').classList.remove('hidden');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }
        
        // Rating system
        function setRating(rating) {
            document.getElementById('selectedRating').value = rating;
            const stars = document.querySelectorAll('.rating-star');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.remove('text-gray-300');
                    star.classList.add('text-yellow-400');
                } else {
                    star.classList.remove('text-yellow-400');
                    star.classList.add('text-gray-300');
                }
            });
        }
        
        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('bg-black')) {
                e.target.classList.add('hidden');
            }
        });
    </script>
</body>
</html>