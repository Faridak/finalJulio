<?php
require_once 'config/database.php';
require_once 'classes/ShippingCalculator.php';

$shippingCalc = new ShippingCalculator($pdo);
$trackingNumber = trim($_GET['tracking'] ?? $_POST['tracking'] ?? '');
$shipment = null;
$error = '';

if ($trackingNumber) {
    $shipment = $shippingCalc->getShipmentTracking($trackingNumber);
    if (!$shipment) {
        $error = 'Tracking number not found. Please check your tracking number and try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Shipment - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Track Your Shipment</h1>
            <p class="text-gray-600 mt-2">Enter your tracking number to see the latest updates</p>
        </div>

        <!-- Tracking Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <label for="tracking" class="block text-sm font-medium text-gray-700 mb-2">Tracking Number</label>
                    <input type="text" name="tracking" id="tracking" 
                           value="<?= htmlspecialchars($trackingNumber) ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter your tracking number">
                </div>
                <div class="sm:mt-6">
                    <button type="submit" class="w-full sm:w-auto bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-search mr-2"></i>Track Package
                    </button>
                </div>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($shipment): ?>
            <!-- Shipment Information -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <!-- Header -->
                <div class="bg-blue-50 px-6 py-4 border-b border-blue-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Shipment Details</h2>
                            <p class="text-sm text-gray-600">Tracking Number: <?= htmlspecialchars($shipment['tracking_number']) ?></p>
                        </div>
                        <div class="mt-2 sm:mt-0">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                <?php
                                switch($shipment['status']) {
                                    case 'created': echo 'bg-gray-100 text-gray-800'; break;
                                    case 'picked_up': echo 'bg-blue-100 text-blue-800'; break;
                                    case 'in_transit': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'out_for_delivery': echo 'bg-orange-100 text-orange-800'; break;
                                    case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                    case 'exception': echo 'bg-red-100 text-red-800'; break;
                                    case 'returned': echo 'bg-purple-100 text-purple-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <i class="fas fa-circle mr-2 text-xs"></i>
                                <?= ucfirst(str_replace('_', ' ', $shipment['status'])) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Shipment Info Grid -->
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Carrier</h3>
                            <p class="mt-1 text-lg font-semibold text-gray-900"><?= htmlspecialchars($shipment['provider_name']) ?></p>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($shipment['service_name']) ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Order</h3>
                            <p class="mt-1 text-lg font-semibold text-gray-900">#<?= str_pad($shipment['order_id'], 6, '0', STR_PAD_LEFT) ?></p>
                            <p class="text-sm text-gray-600">VentDepot Order</p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Weight</h3>
                            <p class="mt-1 text-lg font-semibold text-gray-900"><?= number_format($shipment['weight_kg'], 2) ?> kg</p>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($shipment['dimensions_cm']) ?> cm</p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Shipping Cost</h3>
                            <p class="mt-1 text-lg font-semibold text-gray-900">$<?= number_format($shipment['shipping_cost'], 2) ?></p>
                            <?php if ($shipment['estimated_delivery']): ?>
                                <p class="text-sm text-gray-600">Est: <?= date('M j, Y', strtotime($shipment['estimated_delivery'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Addresses -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">Ship From</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <pre class="text-sm text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($shipment['origin_address']) ?></pre>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">Ship To</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <pre class="text-sm text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($shipment['destination_address']) ?></pre>
                            </div>
                        </div>
                    </div>

                    <!-- External Tracking Link -->
                    <?php if ($shipment['tracking_url']): ?>
                        <div class="border-t border-gray-200 pt-4">
                            <a href="<?= htmlspecialchars($shipment['tracking_url']) ?>" target="_blank"
                               class="inline-flex items-center text-blue-600 hover:text-blue-800">
                                <i class="fas fa-external-link-alt mr-2"></i>
                                Track on <?= htmlspecialchars($shipment['provider_name']) ?> Website
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tracking Timeline -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900">Tracking History</h2>
                </div>
                
                <div class="p-6">
                    <?php if (!empty($shipment['tracking_events'])): ?>
                        <div class="flow-root">
                            <ul class="-mb-8">
                                <?php foreach ($shipment['tracking_events'] as $index => $event): ?>
                                    <li>
                                        <div class="relative pb-8">
                                            <?php if ($index < count($shipment['tracking_events']) - 1): ?>
                                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                            <?php endif; ?>
                                            
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white
                                                        <?php
                                                        switch($event['status']) {
                                                            case 'created': echo 'bg-gray-400'; break;
                                                            case 'picked_up': echo 'bg-blue-500'; break;
                                                            case 'in_transit': echo 'bg-yellow-500'; break;
                                                            case 'out_for_delivery': echo 'bg-orange-500'; break;
                                                            case 'delivered': echo 'bg-green-500'; break;
                                                            case 'exception': echo 'bg-red-500'; break;
                                                            default: echo 'bg-gray-400';
                                                        }
                                                        ?>">
                                                        <i class="fas fa-circle text-white text-xs"></i>
                                                    </span>
                                                </div>
                                                
                                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?= ucfirst(str_replace('_', ' ', $event['status'])) ?>
                                                        </p>
                                                        <?php if ($event['description']): ?>
                                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($event['description']) ?></p>
                                                        <?php endif; ?>
                                                        <?php if ($event['location']): ?>
                                                            <p class="text-xs text-gray-500">
                                                                <i class="fas fa-map-marker-alt mr-1"></i>
                                                                <?= htmlspecialchars($event['location']) ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                        <time datetime="<?= $event['event_time'] ?>">
                                                            <?= date('M j, Y', strtotime($event['event_time'])) ?><br>
                                                            <?= date('g:i A', strtotime($event['event_time'])) ?>
                                                        </time>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-clock text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No tracking events available yet.</p>
                            <p class="text-sm text-gray-400">Check back later for updates.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Delivery Status -->
            <?php if ($shipment['status'] === 'delivered' && $shipment['actual_delivery']): ?>
                <div class="mt-8 bg-green-50 border border-green-200 rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400 text-2xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-green-800">Package Delivered!</h3>
                            <p class="text-green-700">
                                Your package was delivered on <?= date('F j, Y \a\t g:i A', strtotime($shipment['actual_delivery'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($trackingNumber): ?>
            <!-- No Results -->
            <div class="text-center py-12">
                <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-900 mb-2">Tracking Number Not Found</h3>
                <p class="text-gray-600 mb-6">
                    We couldn't find any shipment with tracking number "<?= htmlspecialchars($trackingNumber) ?>"
                </p>
                <div class="text-sm text-gray-500">
                    <p>Please check that you've entered the correct tracking number.</p>
                    <p>Tracking information may take up to 24 hours to appear after shipment.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Instructions -->
            <div class="bg-white rounded-lg shadow-md p-8">
                <div class="text-center">
                    <i class="fas fa-shipping-fast text-6xl text-blue-600 mb-6"></i>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Track Your Package</h2>
                    <p class="text-gray-600 mb-6">
                        Enter your tracking number above to see real-time updates on your shipment's progress.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-box text-blue-600"></i>
                        </div>
                        <h3 class="font-medium text-gray-900 mb-2">Order Confirmation</h3>
                        <p class="text-sm text-gray-600">You'll receive a tracking number via email once your order ships.</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-truck text-blue-600"></i>
                        </div>
                        <h3 class="font-medium text-gray-900 mb-2">Real-time Updates</h3>
                        <p class="text-sm text-gray-600">Track your package's journey from our warehouse to your door.</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-home text-blue-600"></i>
                        </div>
                        <h3 class="font-medium text-gray-900 mb-2">Delivery Confirmation</h3>
                        <p class="text-sm text-gray-600">Get notified when your package is delivered safely.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Help Section -->
        <div class="mt-8 bg-gray-100 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Need Help?</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Common Questions</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Tracking updates may take 24-48 hours to appear</li>
                        <li>• Weekend deliveries depend on your shipping method</li>
                        <li>• Signature may be required for high-value items</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Contact Support</h4>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p><i class="fas fa-envelope mr-2"></i>support@ventdepot.com</p>
                        <p><i class="fas fa-phone mr-2"></i>1-800-VENTDEPOT</p>
                        <p><i class="fas fa-clock mr-2"></i>Mon-Fri 9AM-6PM EST</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh tracking data every 5 minutes if tracking number is present
        <?php if ($shipment): ?>
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 300000); // 5 minutes
        <?php endif; ?>
    </script>
</body>
</html>
