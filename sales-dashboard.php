<?php
// Sales Representative Dashboard
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

// CSRF validation temporarily disabled
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
//     die('CSRF token mismatch');
// }

// Check if user is logged in and is admin/sales
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'merchant')) {
    header('Location: login.php');
    exit;
}

$sales_rep_id = $_SESSION['user_id'];

// Handle quote assignment and cost breakdown
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign_quote') {
        $quote_id = $_POST['quote_id'];
        $engineer_id = $_POST['engineer_id'];
        $estimated_hours = $_POST['estimated_hours'];
        $hourly_rate = $_POST['hourly_rate'];
        $markup_percentage = $_POST['markup_percentage'];
        $material_cost = $_POST['material_cost'];
        $overhead_cost = $_POST['overhead_cost'];
        $commission_percentage = $_POST['commission_percentage'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        try {
            // Calculate costs
            $labor_cost = $estimated_hours * $hourly_rate;
            $total_cost = $labor_cost + $material_cost + $overhead_cost;
            $markup_amount = $total_cost * ($markup_percentage / 100);
            $final_amount = $total_cost + $markup_amount;
            $commission_amount = $final_amount * ($commission_percentage / 100);
            
            // Update quote with assignment details and cost breakdown
            $stmt = $pdo->prepare("UPDATE quotes SET sales_rep_id = ?, assigned_engineer_id = ?, estimated_hours = ?, hourly_rate = ?, markup_percentage = ?, material_cost = ?, overhead_cost = ?, labor_cost = ?, total_cost = ?, final_quote_amount = ?, commission_percentage = ?, commission_amount = ?, start_date = ?, end_date = ?, status = 'assigned', reviewed_at = NOW(), assigned_at = NOW() WHERE id = ?");
            $stmt->execute([$sales_rep_id, $engineer_id, $estimated_hours, $hourly_rate, $markup_percentage, $material_cost, $overhead_cost, $labor_cost, $total_cost, $final_amount, $commission_percentage, $commission_amount, $start_date, $end_date, $quote_id]);
            
            // Create engineering task
            $stmt = $pdo->prepare("SELECT product_type, specifications FROM quotes WHERE id = ?");
            $stmt->execute([$quote_id]);
            $quote = $stmt->fetch();
            
            $task_title = "Engineering: " . $quote['product_type'];
            $task_description = $quote['specifications'];
            
            $stmt = $pdo->prepare("INSERT INTO engineering_tasks (quote_id, title, description, assigned_to, estimated_hours, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'assigned')");
            $stmt->execute([$quote_id, $task_title, $task_description, $engineer_id, $estimated_hours, $start_date, $end_date]);
            
            // Create commission record
            $stmt = $pdo->prepare("INSERT INTO sales_commissions (sales_rep_id, quote_id, commission_percentage, commission_amount) VALUES (?, ?, ?, ?)");
            $stmt->execute([$sales_rep_id, $quote_id, $commission_percentage, $commission_amount]);
            
            // Create notification for engineer
            $stmt = $pdo->prepare("INSERT INTO task_notifications (task_id, user_id, notification_type, message) SELECT id, ?, 'task_assigned', ? FROM engineering_tasks WHERE quote_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$engineer_id, "New task assigned: " . $task_title, $quote_id]);
            
            $success_message = "Quote assigned to engineer successfully with cost breakdown!";
        } catch (PDOException $e) {
            $error_message = "Error assigning quote: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'finalize_quote') {
        $quote_id = $_POST['quote_id'];
        $final_amount = $_POST['final_amount'];
        
        try {
            // Update quote with final amount
            $stmt = $pdo->prepare("UPDATE quotes SET final_quote_amount = ?, status = 'completed', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$final_amount, $quote_id]);
            
            // Update commission amount based on final amount
            $stmt = $pdo->prepare("SELECT commission_percentage FROM quotes WHERE id = ?");
            $stmt->execute([$quote_id]);
            $quote = $stmt->fetch();
            
            $commission_amount = $final_amount * ($quote['commission_percentage'] / 100);
            
            $stmt = $pdo->prepare("UPDATE sales_commissions SET commission_amount = ? WHERE quote_id = ?");
            $stmt->execute([$commission_amount, $quote_id]);
            
            // Create notification for user
            $stmt = $pdo->prepare("SELECT user_id FROM quotes WHERE id = ?");
            $stmt->execute([$quote_id]);
            $user_id = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("INSERT INTO quote_notifications (quote_id, user_id, notification_type, message) VALUES (?, ?, 'quote_finalized', ?)");
            $stmt->execute([$quote_id, $user_id, "Your quote has been finalized. Total amount: $" . number_format($final_amount, 2)]);
            
            $success_message = "Quote finalized successfully!";
        } catch (PDOException $e) {
            $error_message = "Error finalizing quote: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'create_invoice') {
        $quote_id = $_POST['quote_id'];
        $final_amount = $_POST['final_amount'];
        
        try {
            // In a real implementation, you would create an actual invoice/order
            // For now, we'll just update the quote status to invoiced
            $stmt = $pdo->prepare("UPDATE quotes SET status = 'invoiced', invoiced_at = NOW() WHERE id = ?");
            $stmt->execute([$quote_id]);
            
            // Mark commission as pending payment
            $stmt = $pdo->prepare("UPDATE sales_commissions SET status = 'pending' WHERE quote_id = ?");
            $stmt->execute([$quote_id]);
            
            $success_message = "Invoice created successfully!";
        } catch (PDOException $e) {
            $error_message = "Error creating invoice: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'send_task_request') {
        $task_id = $_POST['task_id'];
        $engineer_id = $_POST['engineer_id'];
        $message = $_POST['message'];
        $priority = $_POST['priority'];
        
        try {
            // Create notification for engineer
            $stmt = $pdo->prepare("INSERT INTO task_notifications (task_id, user_id, notification_type, message) VALUES (?, ?, 'task_request', ?)");
            $stmt->execute([$task_id, $engineer_id, $message]);
            
            // Update task priority if high
            if ($priority === 'high') {
                $stmt = $pdo->prepare("UPDATE engineering_tasks SET priority = 'urgent' WHERE id = ?");
                $stmt->execute([$task_id]);
                
                // Also send a notification to admin about high priority task
                $stmt = $pdo->prepare("SELECT email FROM users WHERE role = 'admin' AND email LIKE '%admin%' LIMIT 1");
                $stmt->execute();
                $admin = $stmt->fetch();
                
                if ($admin) {
                    $stmt = $pdo->prepare("INSERT INTO task_notifications (task_id, user_id, notification_type, message) SELECT ?, id, 'high_priority_task', ? FROM users WHERE role = 'admin' AND email LIKE '%admin%'");
                    $stmt->execute([$task_id, "High priority task request: " . $message]);
                }
            }
            
            $success_message = "Task request sent to engineer successfully!";
        } catch (PDOException $e) {
            $error_message = "Error sending task request: " . $e->getMessage();
        }
    }
}

// Fetch quotes for sales dashboard
try {
    // Check if user is admin to show all quotes or just assigned quotes
    if ($_SESSION['user_role'] === 'admin') {
        // Admins can see all quotes
        $stmt = $pdo->prepare("SELECT q.*, u.email as user_email, u2.email as engineer_email FROM quotes q LEFT JOIN users u ON q.user_id = u.id LEFT JOIN users u2 ON q.assigned_engineer_id = u2.id ORDER BY q.created_at DESC");
        $stmt->execute();
    } else {
        // Regular sales reps can only see their assigned quotes or unassigned quotes
        $stmt = $pdo->prepare("SELECT q.*, u.email as user_email, u2.email as engineer_email FROM quotes q LEFT JOIN users u ON q.user_id = u.id LEFT JOIN users u2 ON q.assigned_engineer_id = u2.id WHERE q.sales_rep_id = ? OR q.sales_rep_id IS NULL ORDER BY q.created_at DESC");
        $stmt->execute([$sales_rep_id]);
    }
    $quotes = $stmt->fetchAll();
    
    // Fetch engineers with availability information
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, 
               COALESCE(SUM(CASE WHEN et.status != 'completed' THEN et.estimated_hours ELSE 0 END), 0) as booked_hours,
               40 as available_hours
        FROM users u 
        LEFT JOIN engineering_tasks et ON u.id = et.assigned_to
        WHERE u.role = 'admin' AND u.email LIKE '%engineer%'
        GROUP BY u.id, u.email
        ORDER BY u.email
    ");
    $stmt->execute();
    $engineers = $stmt->fetchAll();
    
    // Fetch commission data - show all for admins, specific sales rep for others
    if ($_SESSION['user_role'] === 'admin') {
        // Admins can see overall commission data
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_quotes, SUM(commission_amount) as total_commission, SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as pending_commission FROM sales_commissions");
        $stmt->execute();
    } else {
        // Regular sales reps see their own commission data
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_quotes, SUM(commission_amount) as total_commission, SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as pending_commission FROM sales_commissions WHERE sales_rep_id = ?");
        $stmt->execute([$sales_rep_id]);
    }
    $commission_data = $stmt->fetch();
    
    // Fetch tasks for assignment requests
    $stmt = $pdo->prepare("
        SELECT et.id, et.title, et.priority, q.product_type
        FROM engineering_tasks et
        JOIN quotes q ON et.quote_id = q.id
        WHERE et.status IN ('new', 'assigned')
        ORDER BY et.created_at DESC
    ");
    $stmt->execute();
    $tasks = $stmt->fetchAll();
    
    // Fetch unread high priority notifications for admins
    if ($_SESSION['user_role'] === 'admin') {
        $stmt = $pdo->prepare("
            SELECT tn.*, et.title as task_title, u.email as requester_email
            FROM task_notifications tn
            JOIN engineering_tasks et ON tn.task_id = et.id
            JOIN users u ON tn.user_id = u.id
            WHERE tn.user_id = ? AND tn.is_read = 0 AND tn.notification_type = 'high_priority_task'
            ORDER BY tn.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $high_priority_notifications = $stmt->fetchAll();
    } else {
        $high_priority_notifications = [];
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
    <title>Sales Dashboard - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .image-preview-container {
            position: relative;
            display: inline-block;
        }
        
        .image-preview-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .image-preview-container:hover .image-preview-overlay {
            opacity: 1;
        }
        
        /* Engineer availability styling */
        .engineer-card {
            transition: all 0.2s ease;
        }
        
        .engineer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .availability-high { background-color: #dcfce7; }
        .availability-medium { background-color: #fef3c7; }
        .availability-low { background-color: #fee2e2; }
        
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
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">Sales Dashboard</h1>
            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4 w-full md:w-auto">
                <button id="newQuoteBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 whitespace-nowrap w-full sm:w-auto">
                    <i class="fas fa-plus mr-2"></i>New Quote
                </button>
                <button id="reportsBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 whitespace-nowrap w-full sm:w-auto">
                    <i class="fas fa-chart-line mr-2"></i>Reports
                </button>
                <?php if ($_SESSION['user_role'] === 'admin' && !empty($high_priority_notifications)): ?>
                    <button id="notificationsBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 whitespace-nowrap w-full sm:w-auto relative">
                        <i class="fas fa-exclamation-triangle mr-2"></i>High Priority
                        <span class="notification-badge"><?php echo count($high_priority_notifications); ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($_SESSION['user_role'] === 'admin' && !empty($high_priority_notifications)): ?>
            <!-- High Priority Notifications -->
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">
                            <strong>You have <?php echo count($high_priority_notifications); ?> high priority task(s) pending.</strong>
                            <button onclick="alert('High priority task notifications would be displayed here in a full implementation.')" class="ml-2 underline">View details</button>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Commission Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-file-invoice-dollar text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Quotes</p>
                        <p class="text-2xl font-semibold"><?php echo $commission_data['total_quotes'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Commission</p>
                        <p class="text-2xl font-semibold">$<?php echo number_format($commission_data['total_commission'] ?? 0, 2); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-full">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending Commission</p>
                        <p class="text-2xl font-semibold">$<?php echo number_format($commission_data['pending_commission'] ?? 0, 2); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-chart-bar text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Conversion Rate</p>
                        <p class="text-2xl font-semibold">
                            <?php 
                            $conversion_rate = ($commission_data['total_quotes'] > 0) ? 
                                round(($commission_data['total_commission'] / ($commission_data['total_quotes'] * 1000)) * 100, 1) : 0;
                            echo $conversion_rate; ?>%
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-file-invoice text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Quotes</p>
                        <p class="text-2xl font-semibold"><?php echo count($quotes); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-full">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending Review</p>
                        <p class="text-2xl font-semibold">
                            <?php 
                            $pending = array_filter($quotes, function($q) { return $q['status'] === 'submitted'; });
                            echo count($pending);
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-tasks text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">In Progress</p>
                        <p class="text-2xl font-semibold">
                            <?php 
                            $in_progress = array_filter($quotes, function($q) { return $q['status'] === 'assigned' || $q['status'] === 'in_progress'; });
                            echo count($in_progress);
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Completed</p>
                        <p class="text-2xl font-semibold">
                            <?php 
                            $completed = array_filter($quotes, function($q) { return $q['status'] === 'completed' || $q['status'] === 'invoiced'; });
                            echo count($completed);
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Engineer Availability -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 sm:mb-0">Engineer Availability</h2>
                <div class="text-sm text-gray-500">
                    <span class="inline-block w-3 h-3 bg-green-200 rounded-full mr-1"></span> High Availability (70%+ free)
                    <span class="inline-block w-3 h-3 bg-yellow-200 rounded-full mr-1 ml-3"></span> Medium Availability (30-70% free)
                    <span class="inline-block w-3 h-3 bg-red-200 rounded-full mr-1 ml-3"></span> Low Availability (&lt;30% free)
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($engineers as $engineer): ?>
                    <?php 
                        $booked_hours = $engineer['booked_hours'];
                        $available_hours = $engineer['available_hours'];
                        $utilization = $available_hours > 0 ? ($booked_hours / $available_hours) * 100 : 100;
                        $free_percentage = 100 - $utilization;
                        
                        // Determine availability class
                        if ($free_percentage >= 70) {
                            $availability_class = 'availability-high';
                        } elseif ($free_percentage >= 30) {
                            $availability_class = 'availability-medium';
                        } else {
                            $availability_class = 'availability-low';
                        }
                    ?>
                    <div class="engineer-card border rounded-lg p-4 <?php echo $availability_class; ?>">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars(explode('@', $engineer['email'])[0]); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($engineer['email']); ?></p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $availability_class; ?> border">
                                <?php echo round($free_percentage); ?>% Free
                            </span>
                        </div>
                        
                        <div class="mt-3">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">Workload</span>
                                <span class="font-medium"><?php echo number_format($booked_hours, 1); ?>/<?php echo $available_hours; ?>h</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, $utilization); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mt-3 flex space-x-2">
                            <button onclick="openTaskRequestModal(<?php echo $engineer['id']; ?>)" 
                                    class="flex-1 text-center px-3 py-1 bg-blue-100 text-blue-700 rounded-md text-sm hover:bg-blue-200">
                                <i class="fas fa-paper-plane mr-1"></i>Request Task
                            </button>
                            <button onclick="chatWithEngineer(<?php echo $engineer['id']; ?>)" 
                                    class="flex-1 text-center px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                                <i class="fas fa-comment mr-1"></i>Chat
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Quotes Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Quote Requests</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Engineer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#<?php echo $quote['id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($quote['product_type']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($quote['user_email']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($quote['engineer_email'] ?? 'Not assigned'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    switch ($quote['status']) {
                                        case 'submitted': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'reviewed': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'assigned': echo 'bg-indigo-100 text-indigo-800'; break;
                                        case 'in_progress': echo 'bg-purple-100 text-purple-800'; break;
                                        case 'completed': echo 'bg-green-100 text-green-800'; break;
                                        case 'invoiced': echo 'bg-teal-100 text-teal-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $quote['status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                $<?php echo number_format($quote['final_quote_amount'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                $<?php echo number_format($quote['commission_amount'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M j, Y', strtotime($quote['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewQuote(<?php echo $quote['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3 p-1">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <?php if ($quote['status'] === 'submitted'): ?>
                                    <button onclick="assignQuote(<?php echo $quote['id']; ?>)" class="text-green-600 hover:text-green-900 p-1">
                                        <i class="fas fa-user-check"></i> Assign
                                    </button>
                                <?php elseif ($quote['status'] === 'completed'): ?>
                                    <button onclick="createInvoice(<?php echo $quote['id']; ?>)" class="text-purple-600 hover:text-purple-900 p-1">
                                        <i class="fas fa-file-invoice"></i> Invoice
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Assign Quote Modal -->
    <div id="assignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Assign Quote to Engineer</h3>
                    <button onclick="closeAssignModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="assignForm" method="POST">
                    <input type="hidden" name="action" value="assign_quote">
                    <input type="hidden" name="quote_id" id="assign_quote_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Engineer</label>
                            <select name="engineer_id" id="engineer_id" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Engineer</option>
                                <?php foreach ($engineers as $engineer): ?>
                                    <option value="<?php echo $engineer['id']; ?>">
                                        <?php echo htmlspecialchars($engineer['email']); ?> 
                                        (<?php echo round(100 - ($engineer['booked_hours'] / max(1, $engineer['available_hours'])) * 100); ?>% free)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Hours</label>
                            <input type="number" name="estimated_hours" id="estimated_hours" step="0.5" min="0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate ($)</label>
                            <input type="number" name="hourly_rate" id="hourly_rate" step="0.01" min="0" value="75.00" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Material Cost ($)</label>
                            <input type="number" name="material_cost" id="material_cost" step="0.01" min="0" value="0.00" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Overhead Cost ($)</label>
                            <input type="number" name="overhead_cost" id="overhead_cost" step="0.01" min="0" value="0.00" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Markup Percentage (%)</label>
                            <input type="number" name="markup_percentage" id="markup_percentage" step="0.1" min="0" value="20.0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Commission Percentage (%)</label>
                            <input type="number" name="commission_percentage" id="commission_percentage" step="0.1" min="0" value="5.0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" id="start_date" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" id="end_date" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <div id="costBreakdown" class="bg-gray-50 p-4 rounded-md">
                            <h4 class="font-medium text-gray-700 mb-2">Cost Breakdown</h4>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>Labor Cost:</div>
                                <div id="laborCost">$0.00</div>
                                
                                <div>Material Cost:</div>
                                <div id="materialCost">$0.00</div>
                                
                                <div>Overhead Cost:</div>
                                <div id="overheadCost">$0.00</div>
                                
                                <div class="font-medium border-t pt-1">Total Cost:</div>
                                <div id="totalCost" class="font-medium border-t pt-1">$0.00</div>
                                
                                <div>Markup:</div>
                                <div id="markupAmount">$0.00</div>
                                
                                <div class="font-medium border-t pt-1">Final Amount:</div>
                                <div id="finalAmount" class="font-medium border-t pt-1">$0.00</div>
                                
                                <div>Commission:</div>
                                <div id="commissionAmount">$0.00</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAssignModal()" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Assign Quote
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Task Request Modal -->
    <div id="taskRequestModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Request Task Assignment</h3>
                    <button onclick="closeTaskRequestModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="taskRequestForm" method="POST">
                    <input type="hidden" name="action" value="send_task_request">
                    <input type="hidden" name="engineer_id" id="request_engineer_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Task</label>
                        <select name="task_id" id="task_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Task</option>
                            <?php foreach ($tasks as $task): ?>
                                <option value="<?php echo $task['id']; ?>">
                                    <?php echo htmlspecialchars($task['title']); ?> (<?php echo htmlspecialchars($task['product_type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select name="priority" id="priority" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="normal">Normal</option>
                            <option value="high">High Priority</option>
                        </select>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                        <textarea name="message" id="request_message" rows="3" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Enter your request message..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeTaskRequestModal()" 
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
    
    <!-- Finalize Quote Modal -->
    <div id="finalizeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Finalize Quote</h3>
                    <button onclick="closeFinalizeModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="finalizeForm" method="POST">
                    <input type="hidden" name="action" value="finalize_quote">
                    <input type="hidden" name="quote_id" id="finalize_quote_id">
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Final Quote Amount ($)</label>
                        <input type="number" name="final_amount" id="final_amount" step="0.01" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="mt-1 text-sm text-gray-500">Based on actual costs and work completed</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeFinalizeModal()" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Finalize Quote
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Create Invoice Modal -->
    <div id="invoiceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Create Invoice</h3>
                    <button onclick="closeInvoiceModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="invoiceForm" method="POST">
                    <input type="hidden" name="action" value="create_invoice">
                    <input type="hidden" name="quote_id" id="invoice_quote_id">
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Amount ($)</label>
                        <input type="number" name="final_amount" id="invoice_amount" step="0.01" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="mt-1 text-sm text-gray-500">This will create an invoice for the client</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeInvoiceModal()" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                            Create Invoice
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Quote Modal -->
    <div id="viewQuoteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Quote Details</h3>
                    <button onclick="closeViewQuoteModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Quote ID</label>
                        <div id="view_quote_id" class="mt-1 text-sm text-gray-900"></div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">User ID</label>
                        <div id="view_user_id" class="mt-1 text-sm text-gray-900"></div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Product Type</label>
                        <div id="view_product_type" class="mt-1 text-sm text-gray-900"></div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Submitted At</label>
                        <div id="view_submitted_at" class="mt-1 text-sm text-gray-900"></div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Quantity</label>
                        <div id="view_quantity" class="mt-1 text-sm text-gray-900"></div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <div id="view_status" class="mt-1 text-sm text-gray-900"></div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Preferred Timeline</label>
                        <div id="view_timeline" class="mt-1 text-sm text-gray-900"></div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Specifications</label>
                        <div id="view_specifications" class="mt-1 text-sm text-gray-900 whitespace-pre-wrap"></div>
                    </div>
                    
                    <!-- File Upload Section -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Uploaded Files</label>
                        <div id="view_uploaded_files" class="mt-1">
                            <p class="text-gray-500 text-sm">No files uploaded</p>
                        </div>
                    </div>
                    
                    <!-- Image Preview Section -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Images</label>
                        <div id="view_images_container" class="mt-1 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                            <!-- Image placeholders will be added here dynamically -->
                        </div>
                    </div>
                    
                    <!-- File Upload Form -->
                    <div class="md:col-span-2 mt-4 pt-4 border-t border-gray-200">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Upload Additional Files</label>
                        <div class="flex items-start space-x-2">
                            <input type="file" id="additional_file_upload" multiple accept=".pdf,.doc,.docx,.txt,.jpg,.png,.dwg,.dxf"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button onclick="uploadAdditionalFile()" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Upload
                            </button>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Upload technical drawings, CAD files, or images</p>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button onclick="closeViewQuoteModal()" 
                            class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Update cost breakdown in real-time
        function updateCostBreakdown() {
            const estimatedHours = parseFloat(document.getElementById('estimated_hours').value) || 0;
            const hourlyRate = parseFloat(document.getElementById('hourly_rate').value) || 0;
            const materialCost = parseFloat(document.getElementById('material_cost').value) || 0;
            const overheadCost = parseFloat(document.getElementById('overhead_cost').value) || 0;
            const markupPercentage = parseFloat(document.getElementById('markup_percentage').value) || 0;
            const commissionPercentage = parseFloat(document.getElementById('commission_percentage').value) || 0;
            
            const laborCost = estimatedHours * hourlyRate;
            const totalCost = laborCost + materialCost + overheadCost;
            const markupAmount = totalCost * (markupPercentage / 100);
            const finalAmount = totalCost + markupAmount;
            const commissionAmount = finalAmount * (commissionPercentage / 100);
            
            document.getElementById('laborCost').textContent = '$' + laborCost.toFixed(2);
            document.getElementById('materialCost').textContent = '$' + materialCost.toFixed(2);
            document.getElementById('overheadCost').textContent = '$' + overheadCost.toFixed(2);
            document.getElementById('totalCost').textContent = '$' + totalCost.toFixed(2);
            document.getElementById('markupAmount').textContent = '$' + markupAmount.toFixed(2);
            document.getElementById('finalAmount').textContent = '$' + finalAmount.toFixed(2);
            document.getElementById('commissionAmount').textContent = '$' + commissionAmount.toFixed(2);
        }
        
        // Add event listeners to update cost breakdown
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = ['estimated_hours', 'hourly_rate', 'material_cost', 'overhead_cost', 'markup_percentage', 'commission_percentage'];
            inputs.forEach(id => {
                document.getElementById(id).addEventListener('input', updateCostBreakdown);
            });
            
            // Set today as default start date
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('start_date').value = today;
            
            // Set end date to 2 weeks from today
            const endDate = new Date();
            endDate.setDate(endDate.getDate() + 14);
            document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
        });
        
        function viewQuote(quoteId) {
            // Find the quote data from the quotes array
            const quotes = <?php echo json_encode(array_values($quotes)); ?>;
            const quote = quotes.find(q => parseInt(q.id) === parseInt(quoteId));
            
            if (quote) {
                // Update modal content with quote details
                document.getElementById('view_quote_id').textContent = quote.id;
                document.getElementById('view_user_id').textContent = quote.user_id;
                document.getElementById('view_product_type').textContent = quote.product_type;
                document.getElementById('view_specifications').textContent = quote.specifications || 'No specifications provided';
                document.getElementById('view_quantity').textContent = quote.quantity || 1;
                document.getElementById('view_timeline').textContent = quote.preferred_timeline || 'Not specified';
                document.getElementById('view_submitted_at').textContent = new Date(quote.submitted_at).toLocaleString();
                document.getElementById('view_status').textContent = quote.status;
                
                // Handle file display
                const uploadedFilesContainer = document.getElementById('view_uploaded_files');
                const imagesContainer = document.getElementById('view_images_container');
                
                // Clear previous content
                uploadedFilesContainer.innerHTML = '';
                imagesContainer.innerHTML = '';
                
                if (quote.specifications_file && quote.specifications_file.trim() !== '') {
                    // Display the uploaded file
                    const fileLink = document.createElement('a');
                    fileLink.href = quote.specifications_file;
                    fileLink.textContent = quote.specifications_file.split('/').pop() || 'Download File';
                    fileLink.target = '_blank';
                    fileLink.className = 'text-blue-600 hover:text-blue-800 underline';
                    uploadedFilesContainer.appendChild(fileLink);
                    
                    // Check if it's an image file and display preview
                    const fileName = quote.specifications_file.toLowerCase();
                    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp'];
                    const isImage = imageExtensions.some(ext => fileName.endsWith(ext));
                    
                    if (isImage) {
                        const imgContainer = document.createElement('div');
                        imgContainer.className = 'image-preview-container';
                        
                        const img = document.createElement('img');
                        img.src = quote.specifications_file;
                        img.alt = 'Quote specification image';
                        img.className = 'w-full h-32 object-cover rounded-lg shadow';
                        
                        const overlay = document.createElement('div');
                        overlay.className = 'image-preview-overlay';
                        
                        const viewBtn = document.createElement('button');
                        viewBtn.textContent = 'View';
                        viewBtn.className = 'px-3 py-1 bg-white text-black rounded text-sm';
                        viewBtn.onclick = () => window.open(quote.specifications_file, '_blank');
                        
                        overlay.appendChild(viewBtn);
                        imgContainer.appendChild(img);
                        imgContainer.appendChild(overlay);
                        imagesContainer.appendChild(imgContainer);
                    }
                } else {
                    uploadedFilesContainer.innerHTML = '<p class="text-gray-500 text-sm">No files uploaded</p>';
                    // Add a placeholder image
                    const placeholderContainer = document.createElement('div');
                    placeholderContainer.className = 'col-span-full text-center py-8';
                    placeholderContainer.innerHTML = `
                        <div class="inline-block p-6 bg-gray-100 rounded-lg">
                            <i class="fas fa-file-image text-gray-400 text-4xl mb-2"></i>
                            <p class="text-gray-500">No images available</p>
                        </div>
                    `;
                    imagesContainer.appendChild(placeholderContainer);
                }
                
                // Show the modal
                document.getElementById('viewQuoteModal').classList.remove('hidden');
            } else {
                alert('Quote details not found.');
            }
        }
        
        function assignQuote(quoteId) {
            document.getElementById('assign_quote_id').value = quoteId;
            document.getElementById('assignModal').classList.remove('hidden');
            updateCostBreakdown(); // Initialize cost breakdown
        }
        
        function closeAssignModal() {
            document.getElementById('assignModal').classList.add('hidden');
        }
        
        function openTaskRequestModal(engineerId) {
            document.getElementById('request_engineer_id').value = engineerId;
            document.getElementById('taskRequestModal').classList.remove('hidden');
        }
        
        function closeTaskRequestModal() {
            document.getElementById('taskRequestModal').classList.add('hidden');
        }
        
        function chatWithEngineer(engineerId) {
            alert('Chat functionality would be implemented here. In a full implementation, this would open a chat interface with the selected engineer.');
        }
        
        function finalizeQuote(quoteId) {
            document.getElementById('finalize_quote_id').value = quoteId;
            document.getElementById('finalizeModal').classList.remove('hidden');
        }
        
        function closeFinalizeModal() {
            document.getElementById('finalizeModal').classList.add('hidden');
        }
        
        function createInvoice(quoteId) {
            document.getElementById('invoice_quote_id').value = quoteId;
            document.getElementById('invoiceModal').classList.remove('hidden');
        }
        
        function closeInvoiceModal() {
            document.getElementById('invoiceModal').classList.add('hidden');
        }
        
        function closeViewQuoteModal() {
            document.getElementById('viewQuoteModal').classList.add('hidden');
        }
        
        function uploadAdditionalFile() {
            const fileInput = document.getElementById('additional_file_upload');
            const files = fileInput.files;
            
            if (files.length === 0) {
                alert('Please select a file to upload.');
                return;
            }
            
            // In a real implementation, you would send the file to the server using AJAX
            // For now, we'll just show a message
            alert('File upload functionality would be implemented here. In a full implementation, files would be uploaded to the server and associated with this quote.');
            
            // Reset the file input
            fileInput.value = '';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const assignModal = document.getElementById('assignModal');
            const taskRequestModal = document.getElementById('taskRequestModal');
            const finalizeModal = document.getElementById('finalizeModal');
            const invoiceModal = document.getElementById('invoiceModal');
            const viewQuoteModal = document.getElementById('viewQuoteModal');
            
            if (event.target === assignModal) {
                closeAssignModal();
            }
            
            if (event.target === taskRequestModal) {
                closeTaskRequestModal();
            }
            
            if (event.target === finalizeModal) {
                closeFinalizeModal();
            }
            
            if (event.target === invoiceModal) {
                closeInvoiceModal();
            }
            
            if (event.target === viewQuoteModal) {
                closeViewQuoteModal();
            }
        }
        
        // Handle New Quote button click
        document.addEventListener('DOMContentLoaded', function() {
            const newQuoteBtn = document.getElementById('newQuoteBtn');
            const reportsBtn = document.getElementById('reportsBtn');
            
            if (newQuoteBtn) {
                newQuoteBtn.addEventListener('click', function() {
                    // For now, we'll just show an alert since there's no dedicated new quote page
                    alert('New Quote functionality would be implemented here. In a full implementation, this would redirect to a quote creation page.');
                });
            }
            
            if (reportsBtn) {
                reportsBtn.addEventListener('click', function() {
                    // For now, we'll just show an alert since there's no dedicated reports page
                    alert('Reports functionality would be implemented here. In a full implementation, this would redirect to a reports dashboard.');
                });
            }
        });
    </script>
    
</body>
</html>