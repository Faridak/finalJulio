<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$poId = intval($_GET['id'] ?? 0);

if (!$poId) {
    header('Location: purchase-orders.php');
    exit;
}

try {
    // Get purchase order details
    $poQuery = "
        SELECT po.*, s.company_name, s.email as supplier_email, s.phone as supplier_phone,
               s.address_line1, s.address_line2, s.city, s.state, s.postal_code, s.country_code,
               il.location_name, il.location_code,
               u.email as created_by_email,
               COALESCE(SUM(poi.total_cost), 0) as calculated_total,
               COUNT(poi.id) as item_count
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN inventory_locations il ON po.location_id = il.id
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
        WHERE po.id = ?
        GROUP BY po.id
    ";
    $stmt = $pdo->prepare($poQuery);
    $stmt->execute([$poId]);
    $po = $stmt->fetch();

    if (!$po) {
        header('Location: purchase-orders.php');
        exit;
    }

    // Get purchase order items
    $itemsQuery = "
        SELECT poi.*, p.name as product_name, poi.supplier_sku as sku, p.image_url
        FROM purchase_order_items poi
        JOIN products p ON poi.product_id = p.id
        WHERE poi.purchase_order_id = ?
        ORDER BY poi.id
    ";
    $stmt = $pdo->prepare($itemsQuery);
    $stmt->execute([$poId]);
    $items = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error retrieving purchase order: " . $e->getMessage();
    $po = null;
    $items = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order #<?= htmlspecialchars($po['po_number'] ?? 'N/A') ?> - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Purchase Order #<?= htmlspecialchars($po['po_number'] ?? 'N/A') ?></h1>
                <p class="text-gray-600 mt-2">Created on <?= $po ? date('F j, Y \a\t g:i A', strtotime($po['created_at'])) : 'N/A' ?></p>
            </div>
            <button onclick="window.print()" class="no-print bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors duration-200">
                <i class="fas fa-print mr-2"></i>Print PO
            </button>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($po): ?>
        <!-- PO Status -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Status</h2>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-2
                        <?php
                        switch($po['status']) {
                            case 'draft': echo 'bg-gray-100 text-gray-800'; break;
                            case 'sent': echo 'bg-blue-100 text-blue-800'; break;
                            case 'confirmed': echo 'bg-purple-100 text-purple-800'; break;
                            case 'partial_received': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'received': echo 'bg-green-100 text-green-800'; break;
                            case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                        <?= ucfirst(str_replace('_', ' ', $po['status'])) ?>
                    </span>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-gray-900">$<?= number_format($po['calculated_total'], 2) ?></div>
                    <div class="text-sm text-gray-500">Total Amount</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Supplier Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Supplier Information</h2>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Company</label>
                        <p class="text-gray-900"><?= htmlspecialchars($po['company_name']) ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Email</label>
                        <p class="text-gray-900"><?= htmlspecialchars($po['supplier_email']) ?></p>
                    </div>
                    <?php if ($po['supplier_phone']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Phone</label>
                        <p class="text-gray-900"><?= htmlspecialchars($po['supplier_phone']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($po['address_line1'] || $po['address_line2'] || $po['city'] || $po['state'] || $po['postal_code'] || $po['country_code']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Address</label>
                        <p class="text-gray-900 whitespace-pre-line">
                            <?= htmlspecialchars(
                                implode("\n", array_filter([
                                    $po['address_line1'],
                                    $po['address_line2'],
                                    implode(" ", array_filter([$po['city'], $po['state'], $po['postal_code']])),
                                    $po['country_code']
                                ]))
                            ) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Order Information</h2>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Created By</label>
                        <p class="text-gray-900"><?= htmlspecialchars($po['created_by_email']) ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Order Date</label>
                        <p class="text-gray-900"><?= $po['order_date'] ? date('F j, Y', strtotime($po['order_date'])) : 'N/A' ?></p>
                    </div>
                    <?php if ($po['expected_delivery_date']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Expected Delivery</label>
                        <p class="text-gray-900"><?= date('F j, Y', strtotime($po['expected_delivery_date'])) ?></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Delivery Location</label>
                        <p class="text-gray-900"><?= htmlspecialchars($po['location_name'] ?? 'N/A') ?> (<?= htmlspecialchars($po['location_code'] ?? 'N/A') ?>)</p>
                    </div>
                    <?php if ($po['notes']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Notes</label>
                        <p class="text-gray-900"><?= htmlspecialchars($po['notes']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PO Items -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Order Items (<?= number_format($po['item_count']) ?> items)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No items found for this purchase order
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <?php if ($item['image_url']): ?>
                                                <img class="h-10 w-10 rounded-lg object-cover mr-4" 
                                                     src="<?= htmlspecialchars($item['image_url']) ?>" 
                                                     alt="<?= htmlspecialchars($item['product_name']) ?>">
                                            <?php else: ?>
                                                <div class="h-10 w-10 bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                                                    <i class="fas fa-image text-gray-400"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></div>
                                                <div class="text-sm text-gray-500">ID: <?= $item['product_id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($item['sku']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        $<?= number_format($item['unit_cost'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= number_format($item['quantity']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        $<?= number_format($item['total_cost'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-6 py-3 text-right text-sm font-medium text-gray-900">Total:</td>
                            <td class="px-6 py-3 text-sm font-bold text-gray-900">$<?= number_format($po['calculated_total'], 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="no-print flex justify-end space-x-4 mt-6">
            <button onclick="window.close()" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition duration-200">
                <i class="fas fa-times mr-2"></i>Close
            </button>
            <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                <i class="fas fa-print mr-2"></i>Print PO
            </button>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <p class="text-center text-gray-500">Unable to load purchase order details.</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Close window function
        function closeWindow() {
            if (window.opener) {
                window.close();
            } else {
                window.location.href = 'purchase-orders.php';
            }
        }
    </script>
</body>
</html>