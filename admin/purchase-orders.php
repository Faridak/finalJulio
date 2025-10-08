<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';
$setupNeeded = false;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_po') {
        try {
            $supplierId = intval($_POST['supplier_id']);
            $locationId = intval($_POST['location_id'] ?? 1);
            $expectedDeliveryDate = $_POST['expected_delivery_date'] ?? null;
            $notes = $_POST['notes'] ?? '';
            
            // Generate PO number
            $poNumber = 'PO-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("
                INSERT INTO purchase_orders 
                (po_number, supplier_id, location_id, order_date, expected_delivery_date, 
                 notes, status, created_by) 
                VALUES (?, ?, ?, CURDATE(), ?, ?, 'draft', ?)
            ");
            $stmt->execute([$poNumber, $supplierId, $locationId, $expectedDeliveryDate, $notes, $_SESSION['user_id']]);
            $success = "Purchase order $poNumber created successfully.";
            
        } catch (PDOException $e) {
            $error = "Error creating purchase order: " . $e->getMessage();
        }
    } elseif ($action === 'update_status') {
        try {
            $poId = intval($_POST['po_id']);
            $newStatus = $_POST['status'];
            $validStatuses = ['draft', 'sent', 'confirmed', 'partial_received', 'received', 'cancelled'];
            
            if (in_array($newStatus, $validStatuses)) {
                // If status is being set to 'received', automatically allocate to inventory
                if ($newStatus === 'received') {
                    // Call the API to allocate items
                    $data = json_encode([
                        'action' => 'update_po_status',
                        'po_id' => $poId,
                        'status' => $newStatus
                    ]);
                    
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'POST',
                            'header' => 'Content-Type: application/json',
                            'content' => $data
                        ]
                    ]);
                    
                    $result = file_get_contents('http://localhost/finalJulio/admin/api/po-inventory-allocation.php', false, $context);
                    $response = json_decode($result, true);
                    
                    if (!$response['success']) {
                        throw new Exception($response['message']);
                    }
                } else {
                    // Just update the status
                    $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $poId]);
                }
                
                $success = "Purchase order status updated successfully.";
            }
        } catch (PDOException $e) {
            $error = "Error updating status: " . $e->getMessage();
        } catch (Exception $e) {
            $error = "Error allocating inventory: " . $e->getMessage();
        }
    }
}

// Get purchase orders with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$whereConditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(po.po_number LIKE ? OR s.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($statusFilter)) {
    $whereConditions[] = "po.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $whereConditions);

try {
    // Get total count
    $countQuery = "
        SELECT COUNT(*) 
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        WHERE $whereClause
    ";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalOrders = $stmt->fetchColumn();
    
    // Get purchase orders
    $ordersQuery = "
        SELECT po.*, s.company_name, s.email as supplier_email,
               il.location_name, il.location_code,
               u.email as created_by_email,
               COUNT(poi.id) as item_count,
               COALESCE(SUM(poi.total_cost), 0) as calculated_total
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN inventory_locations il ON po.location_id = il.id
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
        WHERE $whereClause
        GROUP BY po.id
        ORDER BY po.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($ordersQuery);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Get statistics
    $stats = [
        'total_orders' => $pdo->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn(),
        'draft_orders' => $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'draft'")->fetchColumn(),
        'pending_orders' => $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('sent', 'confirmed')")->fetchColumn(),
        'total_value' => $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_orders WHERE status != 'cancelled'")->fetchColumn()
    ];
    
    // Get suppliers for dropdown
    $suppliers = $pdo->query("SELECT id, company_name FROM suppliers WHERE status = 'active' ORDER BY company_name")->fetchAll();
    
    // Get locations for dropdown
    $locations = $pdo->query("SELECT id, location_name, location_code FROM inventory_locations WHERE status = 'active' ORDER BY location_name")->fetchAll();
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $error = "Purchase order tables are missing. Please import the supplier inventory module schema first.";
        $setupNeeded = true;
        $orders = [];
        $stats = ['total_orders' => 0, 'draft_orders' => 0, 'pending_orders' => 0, 'total_value' => 0];
        $suppliers = [];
        $locations = [];
    } else {
        throw $e;
    }
}

$totalPages = ceil($totalOrders / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders - VentDepot Admin</title>
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
                    <span class="text-gray-400">|</span>
                    <span class="text-lg text-gray-600">Purchase Orders</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="suppliers.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-truck mr-2"></i>Suppliers
                    </a>
                    <a href="inventory.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        <i class="fas fa-boxes mr-2"></i>Inventory
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Purchase Orders</h1>
                <p class="text-gray-600 mt-2">Manage purchase orders and supplier transactions</p>
            </div>
            <?php if (!$setupNeeded): ?>
            <button onclick="openCreatePOModal()" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition duration-200">
                <i class="fas fa-plus mr-2"></i>Create Purchase Order
            </button>
            <?php endif; ?>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
                <?php if ($setupNeeded): ?>
                    <div class="mt-2">
                        <a href="../setup-supplier-module.php" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition-colors duration-200">
                            <i class="fas fa-database mr-2"></i>Setup Database Schema
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$setupNeeded): ?>
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <i class="fas fa-file-invoice text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Orders</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_orders']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-edit text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Draft Orders</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['draft_orders']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-clock text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending Orders</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['pending_orders']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Value</p>
                        <p class="text-2xl font-bold text-gray-900">$<?= number_format($stats['total_value'], 2) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="PO number or supplier..." 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="partial_received" <?= $statusFilter === 'partial_received' ? 'selected' : '' ?>>Partial Received</option>
                        <option value="received" <?= $statusFilter === 'received' ? 'selected' : '' ?>>Received</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition duration-200">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Purchase Orders Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-file-invoice text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-lg font-medium">No Purchase Orders Found</p>
                                    <p class="text-sm">Create your first purchase order to get started</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($order['po_number']) ?></div>
                                        <div class="text-sm text-gray-500">Location: <?= htmlspecialchars($order['location_code'] ?? 'N/A') ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($order['company_name']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($order['supplier_email']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div><?= date('M j, Y', strtotime($order['order_date'])) ?></div>
                                        <?php if ($order['expected_delivery_date']): ?>
                                            <div class="text-xs text-gray-500">Expected: <?= date('M j', strtotime($order['expected_delivery_date'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= number_format($order['item_count']) ?> items
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        $<?= number_format($order['calculated_total'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch($order['status']) {
                                                case 'draft': echo 'bg-gray-100 text-gray-800'; break;
                                                case 'sent': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'confirmed': echo 'bg-purple-100 text-purple-800'; break;
                                                case 'partial_received': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'received': echo 'bg-green-100 text-green-800'; break;
                                                case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewPO(<?= $order['id'] ?>)" class="text-blue-600 hover:text-blue-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'received'): ?>
                                                <button onclick="updateStatus(<?= $order['id'] ?>, '<?= $order['status'] ?>')" class="text-green-600 hover:text-green-900" title="Update Status">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($order['status'] === 'confirmed' || $order['status'] === 'partial_received'): ?>
                                                <button onclick="allocateInventory(<?= $order['id'] ?>)" class="text-purple-600 hover:text-purple-900" title="Allocate to Inventory">
                                                    <i class="fas fa-boxes"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-between items-center mt-6">
                <div class="text-sm text-gray-700">
                    Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $perPage, $totalOrders)) ?> of <?= number_format($totalOrders) ?> results
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm bg-white hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm <?= $i === $page ? 'bg-purple-600 text-white' : 'bg-white hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm bg-white hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Create Purchase Order Modal -->
    <div id="createPOModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-screen overflow-y-auto">
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="create_po">
                    
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Create Purchase Order</h3>
                        <button type="button" onclick="closeCreatePOModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Supplier *</label>
                            <select name="supplier_id" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['company_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Delivery Location</label>
                            <select name="location_id" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= $location['id'] ?>"><?= htmlspecialchars($location['location_name']) ?> (<?= htmlspecialchars($location['location_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Expected Delivery Date</label>
                            <input type="date" name="expected_delivery_date" 
                                   min="<?= date('Y-m-d') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3" 
                                      placeholder="Additional notes or special instructions..."
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" onclick="closeCreatePOModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                            Create Purchase Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="po_id" id="statusPOId">
                    
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Update Status</h3>
                        <button type="button" onclick="closeStatusModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                        <select name="status" id="newStatus" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-purple-500">
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="partial_received">Partial Received</option>
                            <option value="received">Received</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="closeStatusModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                            Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreatePOModal() {
            document.getElementById('createPOModal').classList.remove('hidden');
        }

        function closeCreatePOModal() {
            document.getElementById('createPOModal').classList.add('hidden');
        }

        function updateStatus(poId, currentStatus) {
            document.getElementById('statusPOId').value = poId;
            document.getElementById('newStatus').value = currentStatus;
            document.getElementById('updateStatusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('updateStatusModal').classList.add('hidden');
        }

        function viewPO(poId) {
            // Open purchase order details in new window
            window.open('purchase-order-details.php?id=' + poId, '_blank', 'width=800,height=600');
        }
        
        function allocateInventory(poId) {
            // Open inventory allocation in new window
            window.open('inventory-allocation.php?po_id=' + poId, '_blank', 'width=1000,height=700');
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const createModal = document.getElementById('createPOModal');
            const statusModal = document.getElementById('updateStatusModal');
            
            if (event.target === createModal) {
                closeCreatePOModal();
            }
            if (event.target === statusModal) {
                closeStatusModal();
            }
        });
    </script>
</body>
</html>