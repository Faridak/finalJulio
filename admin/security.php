<?php
/**
 * Security Management Admin Interface
 */

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/AdvancedSecurity.php';

// Check if user is admin
Security::requireAdmin();

$advancedSecurity = new AdvancedSecurity($pdo);

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_role':
            // In a real implementation, you would add role creation logic here
            $message = 'Role creation would be implemented here';
            $messageType = 'info';
            break;
            
        case 'assign_permission':
            // In a real implementation, you would add permission assignment logic here
            $message = 'Permission assignment would be implemented here';
            $messageType = 'info';
            break;
            
        case 'create_api_token':
            $userId = $_POST['user_id'] ?? $_SESSION['user_id'];
            $token = $advancedSecurity->createApiToken($userId, 30); // 30 days expiry
            
            if ($token) {
                $message = 'API token created successfully';
                $messageType = 'success';
                $newToken = $token;
            } else {
                $message = 'Failed to create API token';
                $messageType = 'error';
            }
            break;
            
        case 'revoke_api_token':
            $token = $_POST['token'] ?? '';
            $result = $advancedSecurity->revokeApiToken($token);
            
            if ($result) {
                $message = 'API token revoked successfully';
                $messageType = 'success';
            } else {
                $message = 'Failed to revoke API token';
                $messageType = 'error';
            }
            break;
            
        case 'check_suspicious':
            $alerts = $advancedSecurity->checkForSuspiciousActivities();
            
            if (!empty($alerts)) {
                $message = 'Found ' . count($alerts) . ' suspicious activities';
                $messageType = 'warning';
                $suspiciousAlerts = $alerts;
            } else {
                $message = 'No suspicious activities found';
                $messageType = 'success';
            }
            break;
    }
}

// Get roles
$stmt = $pdo->query("SELECT * FROM user_roles ORDER BY role_name");
$roles = $stmt->fetchAll();

// Get permissions
$stmt = $pdo->query("SELECT * FROM permissions ORDER BY category, permission_name");
$permissions = $stmt->fetchAll();

// Get recent security logs
$securityLogs = $advancedSecurity->getSecurityLogs(50);

// Get API tokens for current user
$stmt = $pdo->prepare("
    SELECT * FROM api_tokens 
    WHERE user_id = ? AND expires_at > NOW()
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$apiTokens = $stmt->fetchAll();

// Group permissions by category
$permissionsByCategory = [];
foreach ($permissions as $permission) {
    $category = $permission['category'] ?: 'uncategorized';
    if (!isset($permissionsByCategory[$category])) {
        $permissionsByCategory[$category] = [];
    }
    $permissionsByCategory[$category][] = $permission;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Security Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                            <i class="bi bi-plus-circle"></i> Create Role
                        </button>
                        <form method="POST" class="ms-2">
                            <input type="hidden" name="action" value="check_suspicious">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-shield-exclamation"></i> Check Suspicious Activity
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

                <?php if (isset($newToken)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>New API Token:</strong> <?php echo $newToken; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- API Tokens -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Your API Tokens</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="mb-3">
                            <input type="hidden" name="action" value="create_api_token">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Create New API Token
                            </button>
                        </form>
                        
                        <?php if (!empty($apiTokens)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Token</th>
                                        <th>Created</th>
                                        <th>Expires</th>
                                        <th>Last Used</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($apiTokens as $token): ?>
                                    <tr>
                                        <td><?php echo substr($token['token'], 0, 20); ?>...</td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($token['created_at'])); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($token['expires_at'])); ?></td>
                                        <td><?php echo $token['last_used_at'] ? date('Y-m-d H:i:s', strtotime($token['last_used_at'])) : 'Never'; ?></td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to revoke this token?')">
                                                <input type="hidden" name="action" value="revoke_api_token">
                                                <input type="hidden" name="token" value="<?php echo $token['token']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i> Revoke
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p>No active API tokens found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Roles and Permissions -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">User Roles</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Role</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($roles as $role): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($role['role_name']); ?></td>
                                                <td><?php echo htmlspecialchars($role['description'] ?? ''); ?></td>
                                                <td>
                                                    <?php if ($role['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Permissions by Category</h5>
                            </div>
                            <div class="card-body">
                                <div class="accordion" id="permissionsAccordion">
                                    <?php foreach ($permissionsByCategory as $category => $perms): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading<?php echo ucfirst($category); ?>">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo ucfirst($category); ?>">
                                                <?php echo ucfirst($category); ?>
                                            </button>
                                        </h2>
                                        <div id="collapse<?php echo ucfirst($category); ?>" class="accordion-collapse collapse" data-bs-parent="#permissionsAccordion">
                                            <div class="accordion-body">
                                                <ul class="list-unstyled">
                                                    <?php foreach ($perms as $permission): ?>
                                                    <li class="mb-1">
                                                        <strong><?php echo htmlspecialchars($permission['permission_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($permission['description'] ?? ''); ?></small>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Logs -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Security Logs</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Severity</th>
                                        <th>IP Address</th>
                                        <th>User Agent</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($securityLogs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['event_type']); ?></td>
                                        <td>
                                            <?php
                                            $severityClass = '';
                                            switch ($log['severity']) {
                                                case 'info':
                                                    $severityClass = 'text-info';
                                                    break;
                                                case 'warning':
                                                    $severityClass = 'text-warning';
                                                    break;
                                                case 'error':
                                                    $severityClass = 'text-danger';
                                                    break;
                                                case 'critical':
                                                    $severityClass = 'text-danger fw-bold';
                                                    break;
                                            }
                                            ?>
                                            <span class="<?php echo $severityClass; ?>">
                                                <?php echo ucfirst($log['severity']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(substr($log['user_agent'] ?? 'N/A', 0, 50)) . (strlen($log['user_agent'] ?? '') > 50 ? '...' : ''); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
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

    <!-- Create Role Modal -->
    <div class="modal fade" id="createRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_role">
                        
                        <div class="mb-3">
                            <label class="form-label">Role Name</label>
                            <input type="text" class="form-control" name="role_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>