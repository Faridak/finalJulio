<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/InventoryManager.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$inventoryManager = new InventoryManager($pdo);
$userId = $_SESSION['user_id'];

// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'ajax') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($_GET['type'] ?? '') {
        case 'warehouse_structure':
            $locationId = (int)($_GET['location_id'] ?? 1);
            $structure = $inventoryManager->getWarehouseStructure($locationId);
            $response = ['success' => true, 'data' => $structure];
            break;
            
        case 'reorder_suggestions':
            $filters = [];
            if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
            if (isset($_GET['urgency'])) $filters['urgency'] = $_GET['urgency'];
            $suggestions = $inventoryManager->getReorderSuggestions($filters);
            $response = ['success' => true, 'data' => $suggestions];
            break;
            
        case 'dashboard_metrics':
            $locationId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : null;
            $metrics = $inventoryManager->getInventoryDashboard($locationId);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM reorder_suggestions WHERE status = 'pending'");
            $stmt->execute();
            $metrics['pending_reorders'] = $stmt->fetchColumn();
            $response = ['success' => true, 'data' => $metrics];
            break;
            
        case 'check_reorder_triggers':
            $suggestions = $inventoryManager->checkReorderTriggers();
            $response = ['success' => true, 'data' => $suggestions, 'count' => count($suggestions)];
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'log_vendor_communication') {
        $commData = [
            'supplier_id' => (int)($_POST['supplier_id'] ?? 0),
            'reorder_suggestion_id' => (int)($_POST['reorder_suggestion_id'] ?? 0),
            'communication_type' => $_POST['communication_type'] ?? 'email',
            'direction' => 'outgoing',
            'subject' => $_POST['subject'] ?? '',
            'message_content' => $_POST['message_content'] ?? '',
            'initiated_by' => $userId,
            'status' => 'completed',
            'outcome' => $_POST['outcome'] ?? 'successful'
        ];
        $response = $inventoryManager->logVendorCommunication($commData);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Get locations
$stmt = $pdo->prepare("SELECT id, location_name FROM inventory_locations WHERE status = 'active' ORDER BY location_name");
$stmt->execute();
$locations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Inventory Management - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(50px, 1fr)); gap: 3px; }
        .bin-cell { aspect-ratio: 1; border: 2px solid #e5e7eb; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: bold; cursor: pointer; transition: all 0.2s; }
        .bin-empty { background-color: #f3f4f6; }
        .bin-partial { background-color: #fef3c7; border-color: #f59e0b; }
        .bin-full { background-color: #fecaca; border-color: #ef4444; }
        .bin-reserved { background-color: #dbeafe; border-color: #3b82f6; }
    </style>
</head>
<body class="bg-gray-50" x-data="inventoryDashboard()">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Enhanced Inventory Management</h1>
                <p class="text-gray-600 mt-1">Inventory cycles, refills, vendor calling, and location/bin mapping</p>
            </div>
            <div class="flex space-x-3">
                <select x-model="selectedLocation" @change="loadDashboardData()" 
                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location['id'] ?>"><?= htmlspecialchars($location['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button @click="checkReorderTriggers()" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center">
                    <i class="fas fa-sync-alt mr-2"></i>
                    Check Reorders
                </button>
            </div>
        </div>

        <!-- Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Low Stock Alerts</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="metrics.low_stock_count || 0"></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-dollar-sign text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Inventory Value</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="formatCurrency(metrics.total_inventory_value || 0)"></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-shopping-cart text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending Reorders</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="metrics.pending_reorders || 0"></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-warehouse text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Bins</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="warehouseStructure.length || 0"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8">
                <button @click="activeTab = 'warehouse'" 
                        :class="activeTab === 'warehouse' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                        class="py-2 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-warehouse mr-2"></i>Warehouse Map
                </button>
                <button @click="activeTab = 'reorders'" 
                        :class="activeTab === 'reorders' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                        class="py-2 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-sync-alt mr-2"></i>Reorder Management
                </button>
            </nav>
        </div>

        <!-- Warehouse Map Tab -->
        <div x-show="activeTab === 'warehouse'" class="space-y-6">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Warehouse Layout & Bin Locations</h3>
                </div>
                <div class="p-6">
                    <div x-show="warehouseStructure.length === 0" class="text-center py-8 text-gray-500">
                        <i class="fas fa-warehouse text-4xl mb-4"></i>
                        <p>Select a location to view warehouse structure</p>
                    </div>
                    
                    <div x-show="warehouseStructure.length > 0" class="space-y-6">
                        <template x-for="zone in groupByZone(warehouseStructure)" :key="zone.zone_id">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-md font-semibold flex items-center">
                                        <span class="w-4 h-4 bg-blue-500 rounded mr-2"></span>
                                        <span x-text="zone.zone_name"></span>
                                    </h4>
                                    <span class="text-sm bg-blue-100 text-blue-800 px-2 py-1 rounded" x-text="zone.zone_type"></span>
                                </div>
                                
                                <template x-for="rack in zone.racks" :key="rack.rack_id">
                                    <div class="mb-4">
                                        <h5 class="text-sm font-medium text-gray-700 mb-2" x-text="rack.rack_name"></h5>
                                        <div class="bin-grid">
                                            <template x-for="bin in rack.bins" :key="bin.bin_id">
                                                <div :class="getBinClass(bin.occupancy_status)" 
                                                     class="bin-cell"
                                                     :title="getBinTooltip(bin)"
                                                     @click="showBinDetails(bin)">
                                                    <span x-text="bin.bin_code"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reorder Management Tab -->
        <div x-show="activeTab === 'reorders'" class="space-y-6">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Reorder Suggestions & Vendor Communication</h3>
                        <p class="text-sm text-gray-600">Products that need to be reordered - call vendors to refill inventory</p>
                    </div>
                    <button @click="checkReorderTriggers()" 
                            class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        <i class="fas fa-sync-alt mr-2"></i>Check Triggers
                    </button>
                </div>
                <div class="p-6">
                    <div class="mb-4 flex space-x-4">
                        <select x-model="reorderFilters.status" @change="loadReorderSuggestions()"
                                class="px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="ordered">Ordered</option>
                        </select>
                        <select x-model="reorderFilters.urgency" @change="loadReorderSuggestions()"
                                class="px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">All Urgency</option>
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Stock</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reorder Point</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Suggested Qty</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Urgency</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="suggestion in reorderSuggestions" :key="suggestion.id">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900" x-text="suggestion.product_name"></div>
                                            <div class="text-sm text-gray-500" x-text="suggestion.product_sku"></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm" x-text="suggestion.current_stock"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm" x-text="suggestion.reorder_point"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm" x-text="suggestion.suggested_quantity"></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="getUrgencyClass(suggestion.urgency_level)" 
                                                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                  x-text="suggestion.urgency_level"></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm" x-text="suggestion.supplier_name"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button @click="contactVendor(suggestion)" class="text-blue-600 hover:text-blue-900 mr-3" title="Contact Vendor">
                                                <i class="fas fa-phone"></i> Call
                                            </button>
                                            <button @click="approveReorder(suggestion)" class="text-green-600 hover:text-green-900" title="Approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        
                        <div x-show="reorderSuggestions.length === 0" class="text-center py-8 text-gray-500">
                            <i class="fas fa-clipboard-list text-4xl mb-4"></i>
                            <p>No reorder suggestions at this time</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Vendor Modal -->
    <div x-show="showContactVendorModal" 
         x-transition:enter="ease-out duration-300" 
         x-transition:enter-start="opacity-0" 
         x-transition:enter-end="opacity-100"
         class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4" 
             @click.away="showContactVendorModal = false">
            <h3 class="text-lg font-semibold mb-4">Contact Vendor for Reorder</h3>
            <div x-show="selectedSuggestion">
                <div class="mb-4 bg-gray-50 p-4 rounded">
                    <p class="text-sm"><strong>Product:</strong> <span x-text="selectedSuggestion?.product_name"></span></p>
                    <p class="text-sm"><strong>Supplier:</strong> <span x-text="selectedSuggestion?.supplier_name"></span></p>
                    <p class="text-sm"><strong>Quantity Needed:</strong> <span x-text="selectedSuggestion?.suggested_quantity"></span></p>
                    <p class="text-sm"><strong>Urgency:</strong> <span x-text="selectedSuggestion?.urgency_level"></span></p>
                </div>
                
                <form @submit.prevent="logVendorCommunication()">
                    <?= generateCSRFInput() ?>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Communication Type</label>
                            <select x-model="communicationForm.communication_type"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="email">Email</option>
                                <option value="phone">Phone Call</option>
                                <option value="sms">SMS</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                            <input type="text" x-model="communicationForm.subject" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                            <textarea x-model="communicationForm.message_content" rows="4" required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Outcome</label>
                            <select x-model="communicationForm.outcome"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="successful">Successful</option>
                                <option value="no_response">No Response</option>
                                <option value="follow_up_required">Follow-up Required</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="showContactVendorModal = false"
                                class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                        <button type="submit" 
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Log Communication</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function inventoryDashboard() {
            return {
                activeTab: 'warehouse',
                selectedLocation: '',
                metrics: {},
                warehouseStructure: [],
                reorderSuggestions: [],
                reorderFilters: { status: '', urgency: '' },
                showContactVendorModal: false,
                selectedSuggestion: null,
                communicationForm: { communication_type: 'email', subject: '', message_content: '', outcome: 'successful' },

                init() {
                    this.loadDashboardData();
                    this.loadReorderSuggestions();
                },

                async loadDashboardData() {
                    try {
                        const params = new URLSearchParams({ action: 'ajax', type: 'dashboard_metrics' });
                        if (this.selectedLocation) params.append('location_id', this.selectedLocation);
                        
                        const response = await fetch(`?${params}`);
                        const data = await response.json();
                        if (data.success) this.metrics = data.data;
                    } catch (error) {
                        console.error('Failed to load dashboard data:', error);
                    }

                    if (this.selectedLocation) await this.loadWarehouseStructure();
                },

                async loadWarehouseStructure() {
                    if (!this.selectedLocation) return;
                    try {
                        const response = await fetch(`?action=ajax&type=warehouse_structure&location_id=${this.selectedLocation}`);
                        const data = await response.json();
                        if (data.success) this.warehouseStructure = data.data;
                    } catch (error) {
                        console.error('Failed to load warehouse structure:', error);
                    }
                },

                async loadReorderSuggestions() {
                    try {
                        const params = new URLSearchParams({
                            action: 'ajax',
                            type: 'reorder_suggestions',
                            ...this.reorderFilters
                        });
                        const response = await fetch(`?${params}`);
                        const data = await response.json();
                        if (data.success) this.reorderSuggestions = data.data;
                    } catch (error) {
                        console.error('Failed to load reorder suggestions:', error);
                    }
                },

                async checkReorderTriggers() {
                    try {
                        const response = await fetch('?action=ajax&type=check_reorder_triggers');
                        const data = await response.json();
                        if (data.success) {
                            alert(`Found ${data.count} new reorder suggestions`);
                            this.loadReorderSuggestions();
                        }
                    } catch (error) {
                        console.error('Failed to check reorder triggers:', error);
                    }
                },

                contactVendor(suggestion) {
                    this.selectedSuggestion = suggestion;
                    this.communicationForm.subject = `Reorder Request - ${suggestion.product_name}`;
                    this.communicationForm.message_content = `We need to reorder ${suggestion.suggested_quantity} units of ${suggestion.product_name} (SKU: ${suggestion.product_sku}). Please confirm availability and pricing.`;
                    this.showContactVendorModal = true;
                },

                async logVendorCommunication() {
                    if (!this.selectedSuggestion) return;
                    try {
                        const formData = new FormData();
                        formData.append('action', 'log_vendor_communication');
                        formData.append('supplier_id', this.selectedSuggestion.supplier_id);
                        formData.append('reorder_suggestion_id', this.selectedSuggestion.id);
                        formData.append('communication_type', this.communicationForm.communication_type);
                        formData.append('subject', this.communicationForm.subject);
                        formData.append('message_content', this.communicationForm.message_content);
                        formData.append('outcome', this.communicationForm.outcome);
                        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                        
                        const response = await fetch('', { method: 'POST', body: formData });
                        const data = await response.json();
                        if (data.success) {
                            alert('Vendor communication logged successfully');
                            this.showContactVendorModal = false;
                            this.communicationForm = { communication_type: 'email', subject: '', message_content: '', outcome: 'successful' };
                        } else {
                            alert('Failed to log communication: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Failed to log communication:', error);
                    }
                },

                approveReorder(suggestion) {
                    if (confirm(`Approve reorder for ${suggestion.suggested_quantity} units of ${suggestion.product_name}?`)) {
                        alert('Reorder approved - Purchase order will be created');
                    }
                },

                groupByZone(structure) {
                    const zones = {};
                    structure.forEach(item => {
                        if (!zones[item.zone_id]) {
                            zones[item.zone_id] = {
                                zone_id: item.zone_id,
                                zone_code: item.zone_code,
                                zone_name: item.zone_name,
                                zone_type: item.zone_type,
                                racks: {}
                            };
                        }
                        if (item.rack_id && !zones[item.zone_id].racks[item.rack_id]) {
                            zones[item.zone_id].racks[item.rack_id] = {
                                rack_id: item.rack_id,
                                rack_code: item.rack_code,
                                rack_name: item.rack_name,
                                bins: []
                            };
                        }
                        if (item.bin_id) {
                            zones[item.zone_id].racks[item.rack_id].bins.push(item);
                        }
                    });
                    return Object.values(zones).map(zone => ({
                        ...zone,
                        racks: Object.values(zone.racks)
                    }));
                },

                getBinClass(occupancyStatus) {
                    switch (occupancyStatus) {
                        case 'empty': return 'bin-empty';
                        case 'partial': return 'bin-partial';
                        case 'full': return 'bin-full';
                        case 'reserved': return 'bin-reserved';
                        default: return 'bin-empty';
                    }
                },

                getBinTooltip(bin) {
                    return `Bin: ${bin.bin_address}\\nStatus: ${bin.occupancy_status}\\nProduct: ${bin.product_name || 'Empty'}\\nQuantity: ${bin.current_quantity || 0}`;
                },

                showBinDetails(bin) {
                    alert(`Bin Details:\\n\\nAddress: ${bin.bin_address}\\nStatus: ${bin.occupancy_status}\\nProduct: ${bin.product_name || 'Empty'}\\nQuantity: ${bin.current_quantity || 0}`);
                },

                getUrgencyClass(urgency) {
                    switch (urgency) {
                        case 'critical': return 'bg-red-100 text-red-800';
                        case 'high': return 'bg-orange-100 text-orange-800';
                        case 'medium': return 'bg-yellow-100 text-yellow-800';
                        case 'low': return 'bg-green-100 text-green-800';
                        default: return 'bg-gray-100 text-gray-800';
                    }
                },

                formatCurrency(amount) {
                    return new Intl.NumberFormat('en-US', {
                        style: 'currency',
                        currency: 'USD'
                    }).format(amount);
                }
            }
        }
    </script>
</body>
</html>