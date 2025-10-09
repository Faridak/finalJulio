<?php
// Engineering Dashboard with Kanban Board
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

// CSRF validation temporarily disabled
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
//     die('CSRF token mismatch');
// }

// Check if user is logged in and is admin/engineer
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'merchant')) {
    header('Location: login.php');
    exit;
}

$engineer_id = $_SESSION['user_id'];

// Handle task status updates and acceptance rating
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_task_status') {
        $task_id = $_POST['task_id'];
        $status = $_POST['status'];
        
        try {
            // Update task status - allow all users (admin/engineer) to update task status
            // Not just the assigned engineer for drag and drop functionality
            if ($_SESSION['user_role'] === 'admin') {
                // Admins can update any task
                $stmt = $pdo->prepare("UPDATE engineering_tasks SET status = ? WHERE id = ?");
                $stmt->execute([$status, $task_id]);
            } else {
                // Regular users can update any task they have access to
                $stmt = $pdo->prepare("UPDATE engineering_tasks SET status = ? WHERE id = ?");
                $stmt->execute([$status, $task_id]);
            }
            
            // Log time if moving to completed
            if ($status === 'completed') {
                // In a real implementation, you might want to calculate actual hours from time logs
                $stmt = $pdo->prepare("UPDATE engineering_tasks SET actual_hours = estimated_hours WHERE id = ?");
                $stmt->execute([$task_id]);
            }
            
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    } elseif ($_POST['action'] === 'log_time') {
        $task_id = $_POST['task_id'];
        $time_spent = $_POST['time_spent'];
        $description = $_POST['description'];
        
        try {
            // Insert time log
            $stmt = $pdo->prepare("INSERT INTO time_logs (task_id, user_id, time_spent_minutes, log_date, description) VALUES (?, ?, ?, CURDATE(), ?)");
            $stmt->execute([$task_id, $engineer_id, $time_spent, $description]);
            
            // Update task actual hours
            $stmt = $pdo->prepare("UPDATE engineering_tasks SET actual_hours = actual_hours + (? / 60.0) WHERE id = ?");
            $stmt->execute([$time_spent, $task_id]);
            
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    } elseif ($_POST['action'] === 'complete_task') {
        $task_id = $_POST['task_id'];
        $acceptance_rating = $_POST['acceptance_rating'];
        $feedback = $_POST['feedback'];
        
        try {
            // Update task with acceptance rating and feedback
            $stmt = $pdo->prepare("UPDATE engineering_tasks SET status = 'completed', acceptance_rating = ?, feedback = ? WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$acceptance_rating, $feedback, $task_id, $engineer_id]);
            
            // Update quote actual hours
            $stmt = $pdo->prepare("SELECT actual_hours FROM engineering_tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();
            
            $stmt = $pdo->prepare("UPDATE quotes q JOIN engineering_tasks et ON q.id = et.quote_id SET q.actual_hours = et.actual_hours WHERE et.id = ?");
            $stmt->execute([$task_id]);
            
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    } elseif ($_POST['action'] === 'accept_task_request') {
        $task_id = $_POST['task_id'];
        $notification_id = $_POST['notification_id'];
        
        try {
            // Update task assignment
            $stmt = $pdo->prepare("UPDATE engineering_tasks SET assigned_to = ?, status = 'assigned' WHERE id = ?");
            $stmt->execute([$engineer_id, $task_id]);
            
            // Update notification as read
            $stmt = $pdo->prepare("UPDATE task_notifications SET is_read = 1 WHERE id = ?");
            $stmt->execute([$notification_id]);
            
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    } elseif ($_POST['action'] === 'decline_task_request') {
        $notification_id = $_POST['notification_id'];
        
        try {
            // Update notification as read and declined
            $stmt = $pdo->prepare("UPDATE task_notifications SET is_read = 1, message = CONCAT(message, ' (Declined)') WHERE id = ?");
            $stmt->execute([$notification_id]);
            
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    } elseif ($_POST['action'] === 'request_task_assignment') {
        $task_id = $_POST['task_id'];
        $message = $_POST['message'];
        
        try {
            // Get the task details
            $stmt = $pdo->prepare("SELECT et.title, q.sales_rep_id FROM engineering_tasks et JOIN quotes q ON et.quote_id = q.id WHERE et.id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();
            
            if ($task) {
                // Create notification for sales rep
                $stmt = $pdo->prepare("INSERT INTO task_notifications (task_id, user_id, notification_type, message) VALUES (?, ?, 'task_request', ?)");
                $stmt->execute([$task_id, $task['sales_rep_id'], "Engineer request for task: " . $task['title'] . " - " . $message]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Task not found']);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// Fetch tasks for engineer
try {
    // Check if user is admin to show all tasks or just assigned tasks
    if ($_SESSION['user_role'] === 'admin') {
        // Admins can see all tasks with engineer details
        $stmt = $pdo->prepare("
            SELECT et.*, q.product_type, q.status as quote_status, q.commission_amount, q.sales_rep_id, u.email as engineer_email, u.id as engineer_id
            FROM engineering_tasks et 
            JOIN quotes q ON et.quote_id = q.id 
            JOIN users u ON et.assigned_to = u.id
            ORDER BY et.created_at DESC
        ");
        $stmt->execute();
    } else {
        // Regular users (engineers) can only see their assigned tasks with engineer details
        $stmt = $pdo->prepare("
            SELECT et.*, q.product_type, q.status as quote_status, q.commission_amount, q.sales_rep_id, u.email as engineer_email, u.id as engineer_id
            FROM engineering_tasks et 
            JOIN quotes q ON et.quote_id = q.id 
            JOIN users u ON et.assigned_to = u.id
            WHERE et.assigned_to = ? 
            ORDER BY et.created_at DESC
        ");
        $stmt->execute([$engineer_id]);
    }
    $tasks = $stmt->fetchAll();
    
    // Group tasks by status for Kanban board
    $kanban_tasks = [
        'new' => [],
        'assigned' => [],
        'in_progress' => [],
        'review' => [],
        'completed' => []
    ];
    
    foreach ($tasks as $task) {
        $kanban_tasks[$task['status']][] = $task;
    }
    
    // Fetch recent time logs
    if ($_SESSION['user_role'] === 'admin') {
        // Admins can see all time logs
        $stmt = $pdo->prepare("
            SELECT tl.*, et.title as task_title, u.email as user_email
            FROM time_logs tl 
            JOIN engineering_tasks et ON tl.task_id = et.id 
            JOIN users u ON tl.user_id = u.id
            ORDER BY tl.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute();
    } else {
        // Regular users can only see their own time logs
        $stmt = $pdo->prepare("
            SELECT tl.*, et.title as task_title 
            FROM time_logs tl 
            JOIN engineering_tasks et ON tl.task_id = et.id 
            WHERE tl.user_id = ? 
            ORDER BY tl.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$engineer_id]);
    }
    $time_logs = $stmt->fetchAll();
    
    // Fetch engineer performance metrics
    if ($_SESSION['user_role'] === 'admin') {
        // For admins, show overall platform metrics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                AVG(acceptance_rating) as avg_acceptance_rating,
                SUM(actual_hours) as total_hours_worked
            FROM engineering_tasks
        ");
        $stmt->execute();
    } else {
        // For regular users, show their own metrics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                AVG(acceptance_rating) as avg_acceptance_rating,
                SUM(actual_hours) as total_hours_worked
            FROM engineering_tasks 
            WHERE assigned_to = ?
        ");
        $stmt->execute([$engineer_id]);
    }
    $performance_metrics = $stmt->fetch();
    
    // Fetch sales rep information for commission tracking
    if ($_SESSION['user_role'] === 'admin') {
        // Admins can see all commission info
        $stmt = $pdo->prepare("
            SELECT u.email as sales_rep_email, q.commission_amount, u2.email as engineer_email
            FROM quotes q
            JOIN users u ON q.sales_rep_id = u.id
            JOIN users u2 ON q.assigned_engineer_id = u2.id
            WHERE q.status = 'completed'
            ORDER BY q.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
    } else {
        // Regular users can only see their own commission info
        $stmt = $pdo->prepare("
            SELECT u.email as sales_rep_email, q.commission_amount
            FROM quotes q
            JOIN users u ON q.sales_rep_id = u.id
            WHERE q.assigned_engineer_id = ? AND q.status = 'completed'
            LIMIT 5
        ");
        $stmt->execute([$engineer_id]);
    }
    $commission_info = $stmt->fetchAll();
    
    // Fetch unread task notifications for engineers
    if ($_SESSION['user_role'] !== 'admin') {
        $stmt = $pdo->prepare("
            SELECT tn.*, et.title as task_title, u.email as requester_email
            FROM task_notifications tn
            JOIN engineering_tasks et ON tn.task_id = et.id
            JOIN users u ON tn.user_id = u.id
            WHERE tn.user_id = ? AND tn.is_read = 0 AND tn.notification_type = 'task_request'
            ORDER BY tn.created_at DESC
        ");
        $stmt->execute([$engineer_id]);
        $notifications = $stmt->fetchAll();
    } else {
        $notifications = [];
    }
    
    // Fetch all available tasks for engineers to request assignment
    if ($_SESSION['user_role'] !== 'admin') {
        $stmt = $pdo->prepare("
            SELECT et.id, et.title, et.priority, q.product_type, u.email as assigned_engineer
            FROM engineering_tasks et
            JOIN quotes q ON et.quote_id = q.id
            LEFT JOIN users u ON et.assigned_to = u.id
            WHERE et.status IN ('new') AND (et.assigned_to IS NULL OR et.assigned_to = 0)
            ORDER BY et.created_at DESC
        ");
        $stmt->execute();
        $available_tasks = $stmt->fetchAll();
    } else {
        $available_tasks = [];
    }
    
} catch (PDOException $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineering Dashboard - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .task-item {
            cursor: move;
            transition: all 0.2s ease;
            min-height: 100px;
        }
        
        .task-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .task-item.dragging {
            opacity: 0.8;
            transform: rotate(3deg);
        }
        
        .task-column {
            min-height: 300px;
            transition: background-color 0.2s ease;
        }
        
        .task-column.sortable-chosen {
            background-color: #f3f4f6;
        }
        
        .task-column.sortable-ghost {
            background-color: #dbeafe;
        }
        
        /* Increase the max width for better task visibility */
        @media (min-width: 1200px) {
            .max-w-7xl {
                max-width: 95rem;
            }
        }
        
        @media (min-width: 1400px) {
            .max-w-7xl {
                max-width: 110rem;
            }
        }
        
        /* Compact task item styling */
        .compact-task {
            padding: 0.75rem;
        }
        
        .compact-task .task-title {
            font-size: 0.875rem;
            line-height: 1.25;
        }
        
        .compact-task .task-meta {
            font-size: 0.75rem;
        }
        
        /* Improve column spacing */
        .task-board-grid {
            gap: 1rem;
        }
        
        /* Better column styling */
        .task-column-container {
            padding: 1rem;
        }
        
        /* Engineer tag styling */
        .engineer-tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-top: 4px;
        }
        .engineer-id-1 { background-color: #dbeafe; color: #1e40af; }
        .engineer-id-2 { background-color: #dcfce7; color: #166534; }
        .engineer-id-3 { background-color: #fef3c7; color: #92400e; }
        .engineer-id-4 { background-color: #fce7f3; color: #9d174d; }
        .engineer-id-5 { background-color: #e0e7ff; color: #4338ca; }
        
        /* Notification badge */
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
        
        /* Available tasks styling */
        .available-task {
            transition: all 0.2s ease;
        }
        
        .available-task:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">Engineering Dashboard</h1>
            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4 w-full md:w-auto">
                <button id="ganttViewBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 whitespace-nowrap w-full sm:w-auto">
                    <i class="fas fa-calendar-alt mr-2"></i>Gantt View
                </button>
                <button id="reportsBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 whitespace-nowrap w-full sm:w-auto">
                    <i class="fas fa-chart-bar mr-2"></i>Reports
                </button>
                <?php if ($_SESSION['user_role'] !== 'admin'): ?>
                    <button id="notificationsBtn" class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 whitespace-nowrap w-full sm:w-auto relative">
                        <i class="fas fa-bell mr-2"></i>Notifications
                        <?php if (count($notifications) > 0): ?>
                            <span class="notification-badge"><?php echo count($notifications); ?></span>
                        <?php endif; ?>
                    </button>
                    <button id="availableTasksBtn" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 whitespace-nowrap w-full sm:w-auto">
                        <i class="fas fa-tasks mr-2"></i>Available Tasks
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Performance Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-tasks text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Tasks</p>
                        <p class="text-2xl font-semibold"><?php echo $performance_metrics['total_tasks'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Completed Tasks</p>
                        <p class="text-2xl font-semibold"><?php echo $performance_metrics['completed_tasks'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-full">
                        <i class="fas fa-star text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Avg Rating</p>
                        <p class="text-2xl font-semibold">
                            <?php echo number_format($performance_metrics['avg_acceptance_rating'] ?? 0, 1); ?>/5
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-clock text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Hours Worked</p>
                        <p class="text-2xl font-semibold">
                            <?php echo number_format($performance_metrics['total_hours_worked'] ?? 0, 1); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($_SESSION['user_role'] !== 'admin' && !empty($notifications)): ?>
            <!-- Task Requests Notification -->
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-yellow-400 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            <strong>You have <?php echo count($notifications); ?> task request(s) pending.</strong>
                            <button onclick="openNotificationsPanel()" class="ml-2 underline">View details</button>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Commission Information -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Commission Tracking</h2>
            <?php if (!empty($commission_info)): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($commission_info as $commission): ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-600">Sales Rep</span>
                                <span class="text-sm text-gray-900"><?php echo htmlspecialchars($commission['sales_rep_email']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-sm font-medium text-gray-600">Commission</span>
                                <span class="text-sm font-semibold text-green-600">$<?php echo number_format($commission['commission_amount'], 2); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No commission information available yet.</p>
            <?php endif; ?>
        </div>
        
        <!-- Kanban Board -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 space-y-4 sm:space-y-0">
                <h2 class="text-xl font-semibold text-gray-800">Task Board</h2>
                <div class="flex flex-wrap gap-2">
                    <button id="filterBtn" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200 whitespace-nowrap">
                        <i class="fas fa-filter mr-1"></i>Filter
                    </button>
                    <button id="sortBtn" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200 whitespace-nowrap">
                        <i class="fas fa-sort mr-1"></i>Sort
                    </button>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-5 task-board-grid">
                <!-- New Column -->
                <div class="bg-gray-50 rounded-lg task-column-container">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-medium text-gray-700">New</h3>
                        <span class="bg-gray-200 text-gray-700 text-xs font-medium px-2 py-1 rounded-full">
                            <?php echo count($kanban_tasks['new']); ?>
                        </span>
                    </div>
                    <div id="new-tasks" class="min-h-40 space-y-3 task-column" data-status="new">
                        <?php foreach ($kanban_tasks['new'] as $task): ?>
                            <div class="bg-white rounded-lg shadow-sm p-3 task-item compact-task" data-task-id="<?php echo $task['id']; ?>">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-medium text-gray-900 task-title">Task #<?php echo $task['id']; ?></h4>
                                    <span class="text-xs text-gray-500"><?php echo date('M j', strtotime($task['created_at'])); ?></span>
                                </div>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars(substr($task['title'], 0, 40)) . (strlen($task['title']) > 40 ? '...' : ''); ?></p>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-xs text-gray-500 task-meta">
                                        <i class="fas fa-clock mr-1"></i><?php echo number_format($task['estimated_hours'], 1); ?>h
                                    </span>
                                    <div class="flex space-x-1">
                                        <button onclick="openTimeLogModal(<?php echo $task['id']; ?>)" class="text-gray-400 hover:text-blue-600 p-1">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                        <button onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'in_progress')" class="text-gray-400 hover:text-green-600 p-1">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Engineer Tag -->
                                <div class="mt-2">
                                    <span class="engineer-tag engineer-id-<?php echo ($task['engineer_id'] % 5) + 1; ?>">
                                        <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars(explode('@', $task['engineer_email'])[0]); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Assigned Column -->
                <div class="bg-blue-50 rounded-lg task-column-container">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-medium text-blue-700">Assigned</h3>
                        <span class="bg-blue-200 text-blue-700 text-xs font-medium px-2 py-1 rounded-full">
                            <?php echo count($kanban_tasks['assigned']); ?>
                        </span>
                    </div>
                    <div id="assigned-tasks" class="min-h-40 space-y-3 task-column" data-status="assigned">
                        <?php foreach ($kanban_tasks['assigned'] as $task): ?>
                            <div class="bg-white rounded-lg shadow-sm p-3 task-item compact-task" data-task-id="<?php echo $task['id']; ?>">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-medium text-gray-900 task-title">Task #<?php echo $task['id']; ?></h4>
                                    <span class="text-xs text-gray-500"><?php echo date('M j', strtotime($task['created_at'])); ?></span>
                                </div>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars(substr($task['title'], 0, 40)) . (strlen($task['title']) > 40 ? '...' : ''); ?></p>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-xs text-gray-500 task-meta">
                                        <i class="fas fa-clock mr-1"></i><?php echo number_format($task['estimated_hours'], 1); ?>h
                                    </span>
                                    <div class="flex space-x-1">
                                        <button onclick="openTimeLogModal(<?php echo $task['id']; ?>)" class="text-gray-400 hover:text-blue-600 p-1">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                        <button onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'in_progress')" class="text-gray-400 hover:text-green-600 p-1">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Engineer Tag -->
                                <div class="mt-2">
                                    <span class="engineer-tag engineer-id-<?php echo ($task['engineer_id'] % 5) + 1; ?>">
                                        <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars(explode('@', $task['engineer_email'])[0]); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- In Progress Column -->
                <div class="bg-yellow-50 rounded-lg task-column-container">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-medium text-yellow-700">In Progress</h3>
                        <span class="bg-yellow-200 text-yellow-700 text-xs font-medium px-2 py-1 rounded-full">
                            <?php echo count($kanban_tasks['in_progress']); ?>
                        </span>
                    </div>
                    <div id="in-progress-tasks" class="min-h-40 space-y-3 task-column" data-status="in_progress">
                        <?php foreach ($kanban_tasks['in_progress'] as $task): ?>
                            <div class="bg-white rounded-lg shadow-sm p-3 task-item compact-task" data-task-id="<?php echo $task['id']; ?>">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-medium text-gray-900 task-title">Task #<?php echo $task['id']; ?></h4>
                                    <span class="text-xs text-gray-500"><?php echo date('M j', strtotime($task['created_at'])); ?></span>
                                </div>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars(substr($task['title'], 0, 40)) . (strlen($task['title']) > 40 ? '...' : ''); ?></p>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-xs text-gray-500 task-meta">
                                        <i class="fas fa-clock mr-1"></i><?php echo number_format($task['actual_hours'], 1); ?>/<?php echo number_format($task['estimated_hours'], 1); ?>h
                                    </span>
                                    <div class="flex space-x-1">
                                        <button onclick="openTimeLogModal(<?php echo $task['id']; ?>)" class="text-gray-400 hover:text-blue-600 p-1">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                        <button onclick="openCompleteTaskModal(<?php echo $task['id']; ?>)" class="text-gray-400 hover:text-purple-600 p-1">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Engineer Tag -->
                                <div class="mt-2">
                                    <span class="engineer-tag engineer-id-<?php echo ($task['engineer_id'] % 5) + 1; ?>">
                                        <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars(explode('@', $task['engineer_email'])[0]); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Review Column -->
                <div class="bg-purple-50 rounded-lg task-column-container">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-medium text-purple-700">Review</h3>
                        <span class="bg-purple-200 text-purple-700 text-xs font-medium px-2 py-1 rounded-full">
                            <?php echo count($kanban_tasks['review']); ?>
                        </span>
                    </div>
                    <div id="review-tasks" class="min-h-40 space-y-3 task-column" data-status="review">
                        <?php foreach ($kanban_tasks['review'] as $task): ?>
                            <div class="bg-white rounded-lg shadow-sm p-3 task-item compact-task" data-task-id="<?php echo $task['id']; ?>">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-medium text-gray-900 task-title">Task #<?php echo $task['id']; ?></h4>
                                    <span class="text-xs text-gray-500"><?php echo date('M j', strtotime($task['created_at'])); ?></span>
                                </div>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars(substr($task['title'], 0, 40)) . (strlen($task['title']) > 40 ? '...' : ''); ?></p>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-xs text-gray-500 task-meta">
                                        <i class="fas fa-clock mr-1"></i><?php echo number_format($task['actual_hours'], 1); ?>/<?php echo number_format($task['estimated_hours'], 1); ?>h
                                    </span>
                                    <div class="flex space-x-1">
                                        <button onclick="openTimeLogModal(<?php echo $task['id']; ?>)" class="text-gray-400 hover:text-blue-600 p-1">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                        <button onclick="openCompleteTaskModal(<?php echo $task['id']; ?>)" class="text-gray-400 hover:text-green-600 p-1">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Engineer Tag -->
                                <div class="mt-2">
                                    <span class="engineer-tag engineer-id-<?php echo ($task['engineer_id'] % 5) + 1; ?>">
                                        <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars(explode('@', $task['engineer_email'])[0]); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Completed Column -->
                <div class="bg-green-50 rounded-lg task-column-container">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-medium text-green-700">Completed</h3>
                        <span class="bg-green-200 text-green-700 text-xs font-medium px-2 py-1 rounded-full">
                            <?php echo count($kanban_tasks['completed']); ?>
                        </span>
                    </div>
                    <div id="completed-tasks" class="min-h-40 space-y-3 task-column" data-status="completed">
                        <?php foreach ($kanban_tasks['completed'] as $task): ?>
                            <div class="bg-white rounded-lg shadow-sm p-3 task-item compact-task" data-task-id="<?php echo $task['id']; ?>">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-medium text-gray-900 task-title">Task #<?php echo $task['id']; ?></h4>
                                    <span class="text-xs text-gray-500"><?php echo date('M j', strtotime($task['created_at'])); ?></span>
                                </div>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars(substr($task['title'], 0, 40)) . (strlen($task['title']) > 40 ? '...' : ''); ?></p>
                                <?php if ($task['acceptance_rating']): ?>
                                    <div class="flex items-center mt-1">
                                        <div class="flex text-yellow-400 text-xs">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $task['acceptance_rating'] ? 'text-yellow-400' : 'text-gray-300'; ?> text-xs"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-xs text-gray-500 ml-1"><?php echo $task['acceptance_rating']; ?>/5</span>
                                    </div>
                                <?php endif; ?>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-xs text-gray-500 task-meta">
                                        <i class="fas fa-clock mr-1"></i><?php echo number_format($task['actual_hours'], 1); ?>/<?php echo number_format($task['estimated_hours'], 1); ?>h
                                    </span>
                                    <div class="flex space-x-1">
                                        <button onclick="openTimeLogModal(<?php echo $task['id']; ?>)" class="text-gray-400 hover:text-blue-600 p-1">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                        <span class="text-green-600">
                                            <i class="fas fa-check-circle"></i>
                                        </span>
                                    </div>
                                </div>
                                <!-- Engineer Tag -->
                                <div class="mt-2">
                                    <span class="engineer-tag engineer-id-<?php echo ($task['engineer_id'] % 5) + 1; ?>">
                                        <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars(explode('@', $task['engineer_email'])[0]); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Time Logs -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 space-y-4 sm:space-y-0">
                <h2 class="text-xl font-semibold text-gray-800">Recent Time Logs</h2>
                <button onclick="openTimeLogModal()" class="px-3 py-1 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700 whitespace-nowrap w-full sm:w-auto">
                    <i class="fas fa-plus mr-1"></i>Log Time
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Spent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($time_logs as $log): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($log['task_title']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M j, Y', strtotime($log['log_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo number_format($log['time_spent_minutes']); ?> min
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($log['description']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($time_logs)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                No time logs found
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Time Log Modal -->
    <div id="timeLogModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Log Time</h3>
                    <button onclick="closeTimeLogModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="timeLogForm">
                    <input type="hidden" name="action" value="log_time">
                    <input type="hidden" name="task_id" id="log_task_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Task</label>
                        <select id="log_task_select" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Task</option>
                            <?php foreach ($tasks as $task): ?>
                                <option value="<?php echo $task['id']; ?>">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Time Spent (minutes)</label>
                        <input type="number" id="time_spent" min="1" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="log_description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="What did you work on?"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeTimeLogModal()" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" onclick="logTime()" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Log Time
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Complete Task Modal -->
    <div id="completeTaskModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Complete Task</h3>
                    <button onclick="closeCompleteTaskModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="completeTaskForm">
                    <input type="hidden" name="action" value="complete_task">
                    <input type="hidden" name="task_id" id="complete_task_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Acceptance Rating</label>
                        <div class="flex items-center">
                            <div class="flex text-2xl text-gray-300" id="ratingStars">
                                <i class="fas fa-star cursor-pointer" data-rating="1"></i>
                                <i class="fas fa-star cursor-pointer ml-1" data-rating="2"></i>
                                <i class="fas fa-star cursor-pointer ml-1" data-rating="3"></i>
                                <i class="fas fa-star cursor-pointer ml-1" data-rating="4"></i>
                                <i class="fas fa-star cursor-pointer ml-1" data-rating="5"></i>
                            </div>
                            <input type="hidden" id="acceptance_rating" name="acceptance_rating" required>
                            <span class="ml-2 text-sm text-gray-500" id="ratingText">Select rating</span>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Feedback</label>
                        <textarea id="feedback" name="feedback" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Any feedback on the completed work?"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeCompleteTaskModal()" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" onclick="completeTask()" 
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Complete Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Notifications Panel -->
    <div id="notificationsPanel" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Task Requests</h3>
                    <button onclick="closeNotificationsPanel()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <?php if (!empty($notifications)): ?>
                    <div class="space-y-4">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between">
                                    <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($notification['task_title']); ?></h4>
                                    <span class="text-xs text-gray-500"><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></span>
                                </div>
                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <p class="text-xs text-gray-500 mt-1">From: <?php echo htmlspecialchars($notification['requester_email']); ?></p>
                                
                                <div class="flex justify-end space-x-2 mt-3">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="decline_task_request">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" class="px-3 py-1 bg-red-100 text-red-700 rounded-md text-sm hover:bg-red-200">
                                            Decline
                                        </button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="accept_task_request">
                                        <input type="hidden" name="task_id" value="<?php echo $notification['task_id']; ?>">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" class="px-3 py-1 bg-green-100 text-green-700 rounded-md text-sm hover:bg-green-200">
                                            Accept
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4">No pending task requests</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Available Tasks Panel -->
    <div id="availableTasksPanel" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Available Tasks</h3>
                    <button onclick="closeAvailableTasksPanel()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <?php if (!empty($available_tasks)): ?>
                    <div class="space-y-4">
                        <?php foreach ($available_tasks as $task): ?>
                            <div class="available-task border rounded-lg p-4">
                                <div class="flex justify-between">
                                    <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($task['title']); ?></h4>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?php echo htmlspecialchars($task['product_type']); ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mt-1">
                                    <i class="fas fa-clock mr-1"></i><?php echo number_format($task['priority']); ?> priority
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Currently assigned to: <?php echo $task['assigned_engineer'] ? htmlspecialchars($task['assigned_engineer']) : 'Unassigned'; ?>
                                </p>
                                
                                <div class="flex justify-end mt-3">
                                    <button onclick="openRequestAssignmentModal(<?php echo $task['id']; ?>)" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md text-sm hover:bg-blue-200">
                                        Request Assignment
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4">No available tasks at this time</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Request Assignment Modal -->
    <div id="requestAssignmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Request Task Assignment</h3>
                    <button onclick="closeRequestAssignmentModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="requestAssignmentForm" method="POST">
                    <input type="hidden" name="action" value="request_task_assignment">
                    <input type="hidden" name="task_id" id="request_task_id">
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message to Sales Rep</label>
                        <textarea name="message" id="request_message" rows="3" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Why are you requesting this task assignment?"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeRequestAssignmentModal()" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Send Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize SortableJS for Kanban board
        document.addEventListener('DOMContentLoaded', function() {
            // Make sure SortableJS is loaded
            if (typeof Sortable !== 'undefined') {
                document.querySelectorAll('.task-column').forEach(column => {
                    new Sortable(column, {
                        group: 'tasks',
                        animation: 150,
                        ghostClass: 'bg-blue-100',
                        dragClass: 'shadow-lg',
                        filter: 'button, input, textarea, select, option',
                        preventOnFilter: false,
                        onEnd: function (evt) {
                            const taskId = evt.item.dataset.taskId;
                            const newStatus = evt.to.dataset.status;
                            
                            // Only update if task was moved to a different column
                            if (evt.from !== evt.to && taskId && newStatus) {
                                // Update task status via AJAX
                                updateTaskStatus(taskId, newStatus);
                            }
                        }
                    });
                });
            } else {
                console.error('SortableJS not loaded');
            }
        });
        
        function updateTaskStatus(taskId, status) {
            const formData = new FormData();
            formData.append('action', 'update_task_status');
            formData.append('task_id', taskId);
            formData.append('status', status);
            
            fetch('engineering-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page to reflect changes
                    location.reload();
                } else {
                    alert('Error updating task status: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating task status');
            });
        }
        
        function openTimeLogModal(taskId = null) {
            if (taskId) {
                document.getElementById('log_task_id').value = taskId;
                document.getElementById('log_task_select').value = taskId;
            }
            document.getElementById('timeLogModal').classList.remove('hidden');
        }
        
        function closeTimeLogModal() {
            document.getElementById('timeLogModal').classList.add('hidden');
            document.getElementById('timeLogForm').reset();
            document.getElementById('log_task_id').value = '';
        }
        
        function logTime() {
            const taskId = document.getElementById('log_task_id').value || document.getElementById('log_task_select').value;
            const timeSpent = document.getElementById('time_spent').value;
            const description = document.getElementById('log_description').value;
            
            if (!taskId || !timeSpent) {
                alert('Please select a task and enter time spent');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'log_time');
            formData.append('task_id', taskId);
            formData.append('time_spent', timeSpent);
            formData.append('description', description);
            
            fetch('engineering-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeTimeLogModal();
                    location.reload();
                } else {
                    alert('Error logging time: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error logging time');
            });
        }
        
        function openCompleteTaskModal(taskId) {
            document.getElementById('complete_task_id').value = taskId;
            document.getElementById('completeTaskModal').classList.remove('hidden');
        }
        
        function closeCompleteTaskModal() {
            document.getElementById('completeTaskModal').classList.add('hidden');
            document.getElementById('completeTaskForm').reset();
            document.getElementById('acceptance_rating').value = '';
            document.getElementById('ratingText').textContent = 'Select rating';
        }
        
        function completeTask() {
            const taskId = document.getElementById('complete_task_id').value;
            const acceptanceRating = document.getElementById('acceptance_rating').value;
            const feedback = document.getElementById('feedback').value;
            
            if (!taskId || !acceptanceRating) {
                alert('Please select a task and provide an acceptance rating');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'complete_task');
            formData.append('task_id', taskId);
            formData.append('acceptance_rating', acceptanceRating);
            formData.append('feedback', feedback);
            
            fetch('engineering-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeCompleteTaskModal();
                    location.reload();
                } else {
                    alert('Error completing task: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error completing task');
            });
        }
        
        function openNotificationsPanel() {
            document.getElementById('notificationsPanel').classList.remove('hidden');
        }
        
        function closeNotificationsPanel() {
            document.getElementById('notificationsPanel').classList.add('hidden');
        }
        
        function openAvailableTasksPanel() {
            document.getElementById('availableTasksPanel').classList.remove('hidden');
        }
        
        function closeAvailableTasksPanel() {
            document.getElementById('availableTasksPanel').classList.add('hidden');
        }
        
        function openRequestAssignmentModal(taskId) {
            document.getElementById('request_task_id').value = taskId;
            document.getElementById('requestAssignmentModal').classList.remove('hidden');
        }
        
        function closeRequestAssignmentModal() {
            document.getElementById('requestAssignmentModal').classList.add('hidden');
        }
        
        // Rating stars functionality
        document.querySelectorAll('#ratingStars i').forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.dataset.rating;
                document.getElementById('acceptance_rating').value = rating;
                document.getElementById('ratingText').textContent = rating + ' star' + (rating > 1 ? 's' : '');
                
                // Update star colors
                document.querySelectorAll('#ratingStars i').forEach((s, index) => {
                    if (index < rating) {
                        s.classList.remove('text-gray-300');
                        s.classList.add('text-yellow-400');
                    } else {
                        s.classList.remove('text-yellow-400');
                        s.classList.add('text-gray-300');
                    }
                });
            });
        });
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const timeLogModal = document.getElementById('timeLogModal');
            const completeTaskModal = document.getElementById('completeTaskModal');
            const notificationsPanel = document.getElementById('notificationsPanel');
            const availableTasksPanel = document.getElementById('availableTasksPanel');
            const requestAssignmentModal = document.getElementById('requestAssignmentModal');
            
            if (event.target === timeLogModal) {
                closeTimeLogModal();
            }
            
            if (event.target === completeTaskModal) {
                closeCompleteTaskModal();
            }
            
            if (event.target === notificationsPanel) {
                closeNotificationsPanel();
            }
            
            if (event.target === availableTasksPanel) {
                closeAvailableTasksPanel();
            }
            
            if (event.target === requestAssignmentModal) {
                closeRequestAssignmentModal();
            }
        }
        
        // Handle button clicks
        document.addEventListener('DOMContentLoaded', function() {
            // Gantt View button
            const ganttViewBtn = document.getElementById('ganttViewBtn');
            if (ganttViewBtn) {
                ganttViewBtn.addEventListener('click', function() {
                    window.location.href = 'gantt-view.php';
                });
            }
            
            // Reports button
            const reportsBtn = document.getElementById('reportsBtn');
            if (reportsBtn) {
                reportsBtn.addEventListener('click', function() {
                    // For now, we'll just show an alert since there's no dedicated reports page
                    alert('Reports functionality would be implemented here. In a full implementation, this would redirect to a reports dashboard or open a reports modal.');
                });
            }
            
            // Filter button
            const filterBtn = document.getElementById('filterBtn');
            if (filterBtn) {
                filterBtn.addEventListener('click', function() {
                    // For now, we'll just show an alert since there's no filter implementation
                    alert('Filter functionality would be implemented here. In a full implementation, this would open a filter modal or dropdown to filter tasks by various criteria.');
                });
            }
            
            // Sort button
            const sortBtn = document.getElementById('sortBtn');
            if (sortBtn) {
                sortBtn.addEventListener('click', function() {
                    // For now, we'll just show an alert since there's no sort implementation
                    alert('Sort functionality would be implemented here. In a full implementation, this would open a sort modal or dropdown to sort tasks by various criteria.');
                });
            }
            
            // Available Tasks button
            const availableTasksBtn = document.getElementById('availableTasksBtn');
            if (availableTasksBtn) {
                availableTasksBtn.addEventListener('click', function() {
                    openAvailableTasksPanel();
                });
            }
        });
    </script>
    
</body>
</html>