<?php
/**
 * Webhook Management Admin Interface
 */

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/WebhookManager.php';
require_once '../includes/WebhookMonitoring.php';

// Check if user is admin
Security::requireAdmin();

$webhookManager = new WebhookManager($pdo);
$webhookMonitoring = new WebhookMonitoring($pdo);

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'register_webhook':
            $result = $webhookManager->registerWebhook(
                $_POST['source'],
                $_POST['webhook_url'],
                $_POST['secret_key'] ?? '',
                $_POST['verification_method'] ?? 'none',
                $_POST['config_data'] ?? null
            );
            
            if ($result['success']) {
                $message = 'Webhook registered successfully';
                $messageType = 'success';
            } else {
                $message = 'Failed to register webhook: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'update_webhook':
            $result = $webhookManager->updateWebhook(
                $_POST['config_id'],
                $_POST['webhook_url'] ?? null,
                $_POST['secret_key'] ?? null,
                $_POST['verification_method'] ?? null,
                isset($_POST['is_active']) ? (bool)$_POST['is_active'] : null,
                $_POST['config_data'] ?? null
            );
            
            if ($result['success']) {
                $message = 'Webhook updated successfully';
                $messageType = 'success';
            } else {
                $message = 'Failed to update webhook: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'delete_webhook':
            $result = $webhookManager->deleteWebhook($_POST['config_id']);
            
            if ($result['success']) {
                $message = 'Webhook deleted successfully';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete webhook: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'retry_event':
            $result = $webhookManager->retryEvent($_POST['event_id']);
            
            if ($result['success']) {
                $message = 'Webhook event retry initiated';
                $messageType = 'success';
            } else {
                $message = 'Failed to retry webhook event: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'check_issues':
            $result = $webhookMonitoring->checkForIssues();
            
            if ($result['success']) {
                if ($result['issues_found'] > 0) {
                    $message = "Found {$result['issues_found']} webhook issues";
                    $messageType = 'warning';
                } else {
                    $message = 'No webhook issues found';
                    $messageType = 'success';
                }
            } else {
                $message = 'Failed to check for issues: ' . $result['error'];
                $messageType = 'error';
            }
            break;
    }
}

// Get webhook configurations
$stmt = $pdo->query("SELECT * FROM webhook_configs ORDER BY source");
$webhookConfigs = $stmt->fetchAll();

// Get webhook stats
$webhookStats = $webhookManager->getWebhookStats();

// Get health status
$healthStatus = $webhookMonitoring->getHealthStatus();

// Get recent events
$recentEvents = $webhookManager->getRecentEvents(50);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Webhook Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#registerWebhookModal">
                            <i class="bi bi-plus-circle"></i> Register Webhook
                        </button>
                        <form method="POST" class="ms-2">
                            <input type="hidden" name="action" value="check_issues">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-search"></i> Check Issues
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Events</h5>
                                <p class="card-text display-6"><?php echo $webhookStats['total_events'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Delivered</h5>
                                <p class="card-text display-6 text-success"><?php echo $webhookStats['delivered_events'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Failed</h5>
                                <p class="card-text display-6 text-danger"><?php echo $webhookStats['failed_events'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Pending</h5>
                                <p class="card-text display-6 text-warning"><?php echo $webhookStats['pending_events'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Health Status -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Webhook Health Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Active</th>
                                        <th>24h Success</th>
                                        <th>24h Failed</th>
                                        <th>Pending</th>
                                        <th>Success Rate</th>
                                        <th>Last Event</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($healthStatus as $health): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($health['source']); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            switch ($health['status']) {
                                                case 'healthy':
                                                    $statusClass = 'text-success';
                                                    $statusText = 'Healthy';
                                                    break;
                                                case 'degraded':
                                                    $statusClass = 'text-warning';
                                                    $statusText = 'Degraded';
                                                    break;
                                                case 'warning':
                                                    $statusClass = 'text-warning';
                                                    $statusText = 'Warning';
                                                    break;
                                                case 'disabled':
                                                    $statusClass = 'text-secondary';
                                                    $statusText = 'Disabled';
                                                    break;
                                            }
                                            ?>
                                            <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($health['is_active']): ?>
                                                <i class="bi bi-check-circle-fill text-success"></i>
                                            <?php else: ?>
                                                <i class="bi bi-x-circle-fill text-danger"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $health['successful_24h']; ?></td>
                                        <td><?php echo $health['failed_24h']; ?></td>
                                        <td><?php echo $health['pending']; ?></td>
                                        <td><?php echo $health['success_rate']; ?>%</td>
                                        <td><?php echo $health['last_event'] ? date('Y-m-d H:i:s', strtotime($health['last_event'])) : 'Never'; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editWebhook(<?php echo $health['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Webhook Configurations -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Webhook Configurations</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th>Webhook URL</th>
                                        <th>Verification</th>
                                        <th>Active</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($webhookConfigs as $config): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($config['source']); ?></td>
                                        <td><?php echo htmlspecialchars($config['webhook_url']); ?></td>
                                        <td><?php echo htmlspecialchars($config['verification_method']); ?></td>
                                        <td>
                                            <?php if ($config['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($config['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editWebhook(<?php echo $config['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this webhook?')">
                                                <input type="hidden" name="action" value="delete_webhook">
                                                <input type="hidden" name="config_id" value="<?php echo $config['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Events -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Webhook Events</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Event Type</th>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Attempts</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentEvents as $event): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                                        <td><?php echo htmlspecialchars($event['source']); ?></td>
                                        <td>
                                            <?php
                                            switch ($event['delivery_status']) {
                                                case 'delivered':
                                                    echo '<span class="badge bg-success">Delivered</span>';
                                                    break;
                                                case 'failed':
                                                    echo '<span class="badge bg-danger">Failed</span>';
                                                    break;
                                                case 'pending':
                                                    echo '<span class="badge bg-warning">Pending</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $event['delivery_attempts']; ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($event['created_at'])); ?></td>
                                        <td>
                                            <?php if ($event['delivery_status'] === 'failed'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="retry_event">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-arrow-repeat"></i> Retry
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-secondary" 
                                                    onclick="viewEventDetails(<?php echo $event['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Register Webhook Modal -->
    <div class="modal fade" id="registerWebhookModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Register New Webhook</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="register_webhook">
                        
                        <div class="mb-3">
                            <label class="form-label">Source</label>
                            <input type="text" class="form-control" name="source" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Webhook URL</label>
                            <input type="url" class="form-control" name="webhook_url" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Secret Key (for HMAC verification)</label>
                            <input type="text" class="form-control" name="secret_key">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Verification Method</label>
                            <select class="form-control" name="verification_method">
                                <option value="none">None</option>
                                <option value="hmac">HMAC Signature</option>
                                <option value="basic_auth">Basic Authentication</option>
                                <option value="oauth">OAuth</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Configuration Data (JSON)</label>
                            <textarea class="form-control" name="config_data" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Register Webhook</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Webhook Modal -->
    <div class="modal fade" id="editWebhookModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Webhook</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_webhook">
                        <input type="hidden" name="config_id" id="editConfigId">
                        
                        <div class="mb-3">
                            <label class="form-label">Source</label>
                            <input type="text" class="form-control" id="editSource" name="source" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Webhook URL</label>
                            <input type="url" class="form-control" id="editWebhookUrl" name="webhook_url" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Secret Key (for HMAC verification)</label>
                            <input type="text" class="form-control" id="editSecretKey" name="secret_key">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Verification Method</label>
                            <select class="form-control" id="editVerificationMethod" name="verification_method">
                                <option value="none">None</option>
                                <option value="hmac">HMAC Signature</option>
                                <option value="basic_auth">Basic Authentication</option>
                                <option value="oauth">OAuth</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Configuration Data (JSON)</label>
                            <textarea class="form-control" id="editConfigData" name="config_data" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="editIsActive" name="is_active" value="1">
                            <label class="form-check-label" for="editIsActive">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Webhook</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editWebhook(configId) {
            // In a real implementation, you would fetch the webhook details via AJAX
            // and populate the modal form
            alert('Edit functionality would be implemented here');
        }
        
        function viewEventDetails(eventId) {
            // In a real implementation, you would fetch the event details via AJAX
            // and display them in a modal
            alert('Event details would be displayed here');
        }
    </script>
</body>
</html>