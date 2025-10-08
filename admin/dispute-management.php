<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/EscrowSystem.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$escrowSystem = new EscrowSystem($pdo);

// Handle dispute resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRF();
    
    if ($_POST['action'] === 'resolve_dispute') {
        $disputeId = intval($_POST['dispute_id']);
        $resolution = Security::sanitizeInput($_POST['resolution'], 'string');
        $awardToBuyer = floatval($_POST['award_to_buyer_percentage'] ?? 0);
        
        $result = $escrowSystem->resolveDispute($disputeId, $_SESSION['user_id'], $resolution, $awardToBuyer);
        
        if ($result['success']) {
            $successMessage = "Dispute resolved successfully.";
        } else {
            $errorMessage = $result['error'];
        }
    }
}

// Get dispute statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_disputes,
        COUNT(CASE WHEN status = 'open' THEN 1 END) as open_disputes,
        COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review,
        COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_disputes,
        AVG(CASE WHEN resolved_at IS NOT NULL 
            THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) as avg_resolution_hours
    FROM escrow_disputes 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();
$disputeStats = $stmt->fetch();

// Get pending disputes
$stmt = $pdo->prepare("
    SELECT 
        ed.*,
        et.amount as escrow_amount,
        et.order_id,
        buyer.email as buyer_email,
        seller.email as seller_email,
        CONCAT(COALESCE(buyer_profile.first_name, ''), ' ', COALESCE(buyer_profile.last_name, '')) as buyer_name,
        CONCAT(COALESCE(seller_profile.first_name, ''), ' ', COALESCE(seller_profile.last_name, '')) as seller_name
    FROM escrow_disputes ed
    JOIN escrow_transactions et ON ed.escrow_id = et.id
    JOIN users buyer ON et.buyer_id = buyer.id
    JOIN users seller ON et.seller_id = seller.id
    LEFT JOIN user_profiles buyer_profile ON buyer.id = buyer_profile.user_id
    LEFT JOIN user_profiles seller_profile ON seller.id = seller_profile.user_id
    WHERE ed.status IN ('open', 'under_review')
    ORDER BY 
        CASE ed.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
        END,
        ed.created_at ASC
");
$stmt->execute();
$pendingDisputes = $stmt->fetchAll();

// Get escrow statistics
$escrowStats = $escrowSystem->getEscrowStatistics('30_days');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispute Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Dispute Management Center</h1>
            <p class="text-gray-600">Monitor and resolve buyer-seller disputes</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($successMessage)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Dispute Stats -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-red-100 rounded-full">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900"><?= number_format($disputeStats['open_disputes']) ?></h3>
                        <p class="text-gray-600 text-sm">Open Disputes</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-full">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900"><?= number_format($disputeStats['under_review']) ?></h3>
                        <p class="text-gray-600 text-sm">Under Review</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900"><?= number_format($disputeStats['resolved_disputes']) ?></h3>
                        <p class="text-gray-600 text-sm">Resolved (30 days)</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-hourglass-half text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900"><?= number_format($disputeStats['avg_resolution_hours'] ?? 0, 1) ?>h</h3>
                        <p class="text-gray-600 text-sm">Avg Resolution Time</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Escrow Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Escrow Overview (30 days)</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Escrows</span>
                        <span class="font-medium"><?= number_format($escrowStats['total_escrows']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Active Amount</span>
                        <span class="font-medium">$<?= number_format($escrowStats['held_amount'], 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Avg Holding Period</span>
                        <span class="font-medium"><?= number_format($escrowStats['avg_holding_days'] ?? 0, 1) ?> days</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Escrow Status</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Active</span>
                        <span class="font-medium"><?= number_format($escrowStats['active_escrows']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Shipped</span>
                        <span class="font-medium"><?= number_format($escrowStats['shipped_escrows']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Disputed</span>
                        <span class="font-medium text-red-600"><?= number_format($escrowStats['disputed_escrows']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Released</span>
                        <span class="font-medium text-green-600"><?= number_format($escrowStats['released_escrows']) ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Financial Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Volume</span>
                        <span class="font-medium">$<?= number_format($escrowStats['total_amount'], 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Fees Collected</span>
                        <span class="font-medium">$<?= number_format($escrowStats['total_fees_collected'], 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Currently Held</span>
                        <span class="font-medium text-blue-600">$<?= number_format($escrowStats['held_amount'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Disputes -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold text-gray-900">Pending Disputes</h2>
            </div>

            <?php if (empty($pendingDisputes)): ?>
                <div class="p-8 text-center">
                    <i class="fas fa-peace text-gray-300 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Pending Disputes</h3>
                    <p class="text-gray-600">All disputes have been resolved. Great job!</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dispute</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parties</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($pendingDisputes as $dispute): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                Order #<?= $dispute['order_id'] ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                <?= ucfirst(str_replace('_', ' ', $dispute['dispute_reason'])) ?>
                                            </div>
                                            <div class="flex items-center mt-1">
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full 
                                                    <?= match($dispute['priority']) {
                                                        'urgent' => 'bg-red-100 text-red-800',
                                                        'high' => 'bg-orange-100 text-orange-800',
                                                        'medium' => 'bg-yellow-100 text-yellow-800',
                                                        'low' => 'bg-green-100 text-green-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    } ?>">
                                                    <?= ucfirst($dispute['priority']) ?> Priority
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <div><strong>Buyer:</strong> <?= htmlspecialchars($dispute['buyer_name'] ?: $dispute['buyer_email']) ?></div>
                                            <div><strong>Seller:</strong> <?= htmlspecialchars($dispute['seller_name'] ?: $dispute['seller_email']) ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            $<?= number_format($dispute['escrow_amount'], 2) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full 
                                            <?= $dispute['status'] === 'open' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= ucfirst(str_replace('_', ' ', $dispute['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?= date('M j, Y g:i A', strtotime($dispute['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <button onclick="openDisputeModal(<?= $dispute['id'] ?>, '<?= addslashes($dispute['description']) ?>', <?= $dispute['escrow_amount'] ?>)" 
                                                class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                            Review & Resolve
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dispute Resolution Modal -->
    <div id="disputeResolutionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full p-6 max-h-screen overflow-y-auto">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Resolve Dispute</h3>
                
                <div id="disputeDetails" class="bg-gray-50 p-4 rounded-lg mb-4">
                    <!-- Dispute details will be populated here -->
                </div>
                
                <form method="POST">
                    <?= generateCSRFInput() ?>
                    <input type="hidden" name="action" value="resolve_dispute">
                    <input type="hidden" name="dispute_id" id="modalDisputeId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Award to Buyer (%)</label>
                        <div class="flex items-center space-x-4">
                            <input type="range" name="award_to_buyer_percentage" id="awardSlider" 
                                   min="0" max="100" value="0" 
                                   class="flex-1"
                                   oninput="updateAwardDisplay()">
                            <span id="awardDisplay" class="text-sm font-medium w-20 text-center">0% ($0.00)</span>
                        </div>
                        <div class="mt-2 text-xs text-gray-600">
                            <div class="flex justify-between">
                                <span>0% = Full refund to seller</span>
                                <span>100% = Full refund to buyer</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Resolution Notes</label>
                        <textarea name="resolution" rows="4" required
                                  class="w-full border border-gray-300 rounded px-3 py-2"
                                  placeholder="Provide detailed explanation of the resolution decision..."></textarea>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-green-600 text-white py-2 px-4 rounded hover:bg-green-700">
                            Resolve Dispute
                        </button>
                        <button type="button" onclick="closeModal()" 
                                class="px-6 py-2 border border-gray-300 rounded hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentDisputeAmount = 0;
        
        function openDisputeModal(disputeId, description, escrowAmount) {
            currentDisputeAmount = escrowAmount;
            document.getElementById('modalDisputeId').value = disputeId;
            document.getElementById('disputeDetails').innerHTML = `
                <h4 class="font-medium text-gray-900 mb-2">Dispute Description:</h4>
                <p class="text-gray-700">${description}</p>
                <p class="text-sm text-gray-600 mt-2">Escrow Amount: $${escrowAmount.toFixed(2)}</p>
            `;
            document.getElementById('disputeResolutionModal').classList.remove('hidden');
            updateAwardDisplay();
        }
        
        function closeModal() {
            document.getElementById('disputeResolutionModal').classList.add('hidden');
        }
        
        function updateAwardDisplay() {
            const slider = document.getElementById('awardSlider');
            const display = document.getElementById('awardDisplay');
            const percentage = slider.value;
            const amount = (currentDisputeAmount * percentage / 100).toFixed(2);
            display.textContent = `${percentage}% ($${amount})`;
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('bg-black')) {
                closeModal();
            }
        });
    </script>
</body>
</html>