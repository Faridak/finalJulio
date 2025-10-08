<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';

// Handle log actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'clear_logs') {
        $days = intval($_POST['days'] ?? 30);
        $stmt = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        if ($stmt->execute([$days])) {
            $success = "Logs older than $days days have been cleared.";
        } else {
            $error = 'Failed to clear logs.';
        }
    }
}

// Get filter parameters
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user'] ?? '';
$entity_filter = $_GET['entity'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($action_filter) {
    $whereConditions[] = "action LIKE ?";
    $params[] = "%$action_filter%";
}

if ($user_filter) {
    $whereConditions[] = "user_id = ?";
    $params[] = $user_filter;
}

if ($entity_filter) {
    $whereConditions[] = "entity_type = ?";
    $params[] = $entity_filter;
}

if ($date_filter) {
    $whereConditions[] = "DATE(created_at) = ?";
    $params[] = $date_filter;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get logs with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$logsQuery = "
    SELECT sl.*, u.email as user_email
    FROM system_logs sl
    LEFT JOIN users u ON sl.user_id = u.id
    $whereClause
    ORDER BY sl.created_at DESC
    LIMIT $limit OFFSET $offset
";

$logs = $pdo->prepare($logsQuery);
$logs->execute($params);
$logs = $logs->fetchAll();

// Get total count for pagination
$countQuery = "SELECT COUNT(*) FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalLogs = $countStmt->fetchColumn();
$totalPages = ceil($totalLogs / $limit);

// Get filter options
$actions = $pdo->query("SELECT DISTINCT action FROM system_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$entities = $pdo->query("SELECT DISTINCT entity_type FROM system_logs WHERE entity_type IS NOT NULL ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);
$users = $pdo->query("SELECT id, email FROM users ORDER BY email")->fetchAll();

// Get statistics
$stats = [
    'total_logs' => $pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn(),
    'today_logs' => $pdo->query("SELECT COUNT(*) FROM system_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'week_logs' => $pdo->query("SELECT COUNT(*) FROM system_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'unique_users' => $pdo->query("SELECT COUNT(DISTINCT user_id) FROM system_logs WHERE user_id IS NOT NULL")->fetchColumn()
];

// Get recent activity summary
$recentActivity = $pdo->query("
    SELECT 
        action,
        COUNT(*) as count,
        MAX(created_at) as last_occurrence
    FROM system_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY action
    ORDER BY count DESC
    LIMIT 10
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - VentDepot Admin</title>
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
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">System Logs</h1>
                <p class="text-gray-600 mt-2">Monitor system activity and user actions</p>
            </div>
            <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-list text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Logs</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_logs']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-calendar-day text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Today's Logs</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['today_logs']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-calendar-week text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">This Week</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['week_logs']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-users text-orange-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Users</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['unique_users']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Summary -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Recent Activity (Last 24 Hours)</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <?php foreach ($recentActivity as $activity): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="text-lg font-semibold text-gray-900"><?= number_format($activity['count']) ?></div>
                        <div class="text-sm text-gray-600"><?= htmlspecialchars($activity['action']) ?></div>
                        <div class="text-xs text-gray-500 mt-1">
                            Last: <?= date('H:i', strtotime($activity['last_occurrence'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filters and Actions -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-900">Filter Logs</h2>
                <div class="flex space-x-2">
                    <button onclick="exportLogs()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        <i class="fas fa-download mr-2"></i>Export
                    </button>
                    <button onclick="document.getElementById('clearLogsModal').classList.remove('hidden')"
                            class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                        <i class="fas fa-trash mr-2"></i>Clear Old Logs
                    </button>
                </div>
            </div>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                    <select name="action" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?= htmlspecialchars($action) ?>" <?= $action_filter === $action ? 'selected' : '' ?>>
                                <?= htmlspecialchars($action) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                    <select name="user" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Entity Type</label>
                    <select name="entity" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Entities</option>
                        <?php foreach ($entities as $entity): ?>
                            <option value="<?= htmlspecialchars($entity) ?>" <?= $entity_filter === $entity ? 'selected' : '' ?>>
                                <?= htmlspecialchars($entity) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($date_filter) ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">System Logs (<?= number_format($totalLogs) ?> total)</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No logs found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= date('M j, Y H:i:s', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= $log['user_email'] ? htmlspecialchars($log['user_email']) : 'System' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch(strtolower($log['action'])) {
                                                case 'login': echo 'bg-green-100 text-green-800'; break;
                                                case 'logout': echo 'bg-gray-100 text-gray-800'; break;
                                                case 'create': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'update': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'delete': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-purple-100 text-purple-800';
                                            }
                                            ?>">
                                            <?= htmlspecialchars($log['action']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($log['entity_type']): ?>
                                            <?= htmlspecialchars($log['entity_type']) ?>
                                            <?php if ($log['entity_id']): ?>
                                                <span class="text-gray-500">#<?= $log['entity_id'] ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="max-w-xs truncate" title="<?= htmlspecialchars($log['description']) ?>">
                                            <?= htmlspecialchars($log['description']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($log['ip_address']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                               class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> to 
                                <span class="font-medium"><?= min($page * $limit, $totalLogs) ?></span> of 
                                <span class="font-medium"><?= number_format($totalLogs) ?></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border text-sm font-medium
                                              <?= $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Clear Logs Modal -->
    <div id="clearLogsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Clear Old Logs</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="clear_logs">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Delete logs older than:</label>
                        <select name="days" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <option value="7">7 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="90">90 days</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>
                    <div class="flex justify-center space-x-3">
                        <button type="button" onclick="document.getElementById('clearLogsModal').classList.add('hidden')"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" onclick="return confirm('Are you sure you want to clear old logs? This cannot be undone.')"
                                class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                            Clear Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'export-logs.php?' + params.toString();
        }
    </script>
</body>
</html>
