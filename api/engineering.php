<?php
// Engineering API Endpoints
header('Content-Type: application/json');
session_start();

require_once '../config/database.php';
require_once '../includes/security.php';

// CSRF validation temporarily disabled
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
//     http_response_code(403);
//     echo json_encode(['error' => 'CSRF token mismatch']);
//     exit;
// }

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get the request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequests($action, $pdo, $user_id, $user_role);
            break;
        case 'POST':
            handlePostRequests($action, $pdo, $user_id, $user_role);
            break;
        case 'PUT':
            handlePutRequests($action, $pdo, $user_id, $user_role);
            break;
        case 'DELETE':
            handleDeleteRequests($action, $pdo, $user_id, $user_role);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function handleGetRequests($action, $pdo, $user_id, $user_role) {
    switch ($action) {
        case 'tasks':
            getTasks($pdo, $user_id, $user_role);
            break;
        case 'quotes':
            getQuotes($pdo, $user_id, $user_role);
            break;
        case 'engineers':
            getEngineers($pdo);
            break;
        case 'task_details':
            getTaskDetails($pdo, $user_id, $user_role);
            break;
        case 'commission_data':
            getCommissionData($pdo, $user_id, $user_role);
            break;
        case 'performance_metrics':
            getPerformanceMetrics($pdo, $user_id, $user_role);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePostRequests($action, $pdo, $user_id, $user_role) {
    switch ($action) {
        case 'update_task_status':
            updateTaskStatus($pdo, $user_id, $user_role);
            break;
        case 'log_time':
            logTime($pdo, $user_id);
            break;
        case 'assign_quote':
            assignQuote($pdo, $user_id);
            break;
        case 'finalize_quote':
            finalizeQuote($pdo, $user_id);
            break;
        case 'create_invoice':
            createInvoice($pdo, $user_id);
            break;
        case 'complete_task':
            completeTask($pdo, $user_id);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePutRequests($action, $pdo, $user_id, $user_role) {
    switch ($action) {
        case 'update_task':
            updateTask($pdo, $user_id, $user_role);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleDeleteRequests($action, $pdo, $user_id, $user_role) {
    // No delete actions implemented yet
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
}

function getTasks($pdo, $user_id, $user_role) {
    // Build query based on user role
    if ($user_role === 'admin') {
        // Admins (sales/engineers) can see all tasks
        $stmt = $pdo->prepare("
            SELECT et.*, q.product_type, u.email as engineer_name, q.commission_amount, q.sales_rep_id
            FROM engineering_tasks et 
            JOIN quotes q ON et.quote_id = q.id 
            JOIN users u ON et.assigned_to = u.id
            ORDER BY et.created_at DESC
        ");
        $stmt->execute();
    } else {
        // Regular users can only see their own quotes
        $stmt = $pdo->prepare("
            SELECT et.*, q.product_type, u.email as engineer_name, q.commission_amount, q.sales_rep_id
            FROM engineering_tasks et 
            JOIN quotes q ON et.quote_id = q.id 
            JOIN users u ON et.assigned_to = u.id
            WHERE q.user_id = ?
            ORDER BY et.created_at DESC
        ");
        $stmt->execute([$user_id]);
    }
    
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['tasks' => $tasks]);
}

function getQuotes($pdo, $user_id, $user_role) {
    // Build query based on user role
    if ($user_role === 'admin') {
        // Admins can see all quotes
        $stmt = $pdo->prepare("
            SELECT q.*, u.email as user_email, u2.email as engineer_email, sc.commission_amount as commission_earned
            FROM quotes q 
            LEFT JOIN users u ON q.user_id = u.id 
            LEFT JOIN users u2 ON q.assigned_engineer_id = u2.id
            LEFT JOIN sales_commissions sc ON q.id = sc.quote_id
            ORDER BY q.created_at DESC
        ");
        $stmt->execute();
    } else {
        // Regular users can only see their own quotes
        $stmt = $pdo->prepare("
            SELECT q.*, u.email as user_email, u2.email as engineer_email, sc.commission_amount as commission_earned
            FROM quotes q 
            LEFT JOIN users u ON q.user_id = u.id 
            LEFT JOIN users u2 ON q.assigned_engineer_id = u2.id
            LEFT JOIN sales_commissions sc ON q.id = sc.quote_id
            WHERE q.user_id = ?
            ORDER BY q.created_at DESC
        ");
        $stmt->execute([$user_id]);
    }
    
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['quotes' => $quotes]);
}

function getEngineers($pdo) {
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE role = 'admin' AND email LIKE '%engineer%'");
    $stmt->execute();
    $engineers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['engineers' => $engineers]);
}

function getTaskDetails($pdo, $user_id, $user_role) {
    $task_id = $_GET['task_id'] ?? 0;
    
    if (!$task_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        return;
    }
    
    // Build query based on user role
    if ($user_role === 'admin') {
        $stmt = $pdo->prepare("
            SELECT et.*, q.product_type, q.specifications, q.quantity, q.commission_amount,
                   u.email as engineer_name, u2.email as user_email, u3.email as sales_rep_email
            FROM engineering_tasks et 
            JOIN quotes q ON et.quote_id = q.id 
            JOIN users u ON et.assigned_to = u.id
            JOIN users u2 ON q.user_id = u2.id
            LEFT JOIN users u3 ON q.sales_rep_id = u3.id
            WHERE et.id = ?
        ");
        $stmt->execute([$task_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT et.*, q.product_type, q.specifications, q.quantity, q.commission_amount,
                   u.email as engineer_name, u2.email as user_email, u3.email as sales_rep_email
            FROM engineering_tasks et 
            JOIN quotes q ON et.quote_id = q.id 
            JOIN users u ON et.assigned_to = u.id
            JOIN users u2 ON q.user_id = u2.id
            LEFT JOIN users u3 ON q.sales_rep_id = u3.id
            WHERE et.id = ? AND q.user_id = ?
        ");
        $stmt->execute([$task_id, $user_id]);
    }
    
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        return;
    }
    
    // Get time logs for this task
    $stmt = $pdo->prepare("SELECT * FROM time_logs WHERE task_id = ? ORDER BY created_at DESC");
    $stmt->execute([$task_id]);
    $time_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['task' => $task, 'time_logs' => $time_logs]);
}

function getCommissionData($pdo, $user_id, $user_role) {
    if ($user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized to view commission data']);
        return;
    }
    
    // Get commission data for this sales rep
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_quotes,
            SUM(commission_amount) as total_commission,
            SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as pending_commission,
            SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as paid_commission
        FROM sales_commissions 
        WHERE sales_rep_id = ?
    ");
    $stmt->execute([$user_id]);
    $commission_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['commission_data' => $commission_data]);
}

function getPerformanceMetrics($pdo, $user_id, $user_role) {
    // Build query based on user role
    if ($user_role === 'admin') {
        // For engineers, get their performance metrics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                AVG(acceptance_rating) as avg_acceptance_rating,
                SUM(actual_hours) as total_hours_worked
            FROM engineering_tasks 
            WHERE assigned_to = ?
        ");
        $stmt->execute([$user_id]);
    } else {
        // For regular users, get their quote metrics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_quotes,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_quotes,
                AVG(final_quote_amount) as avg_quote_amount
            FROM quotes 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
    }
    
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['metrics' => $metrics]);
}

function updateTaskStatus($pdo, $user_id, $user_role) {
    $task_id = $_POST['task_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    if (!$task_id || !$status) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID and status are required']);
        return;
    }
    
    // Validate status
    $valid_statuses = ['new', 'assigned', 'in_progress', 'review', 'completed'];
    if (!in_array($status, $valid_statuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        return;
    }
    
    // Build query based on user role
    if ($user_role === 'admin') {
        $stmt = $pdo->prepare("UPDATE engineering_tasks SET status = ? WHERE id = ?");
        $stmt->execute([$status, $task_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE engineering_tasks SET status = ? WHERE id = ? AND assigned_to = ?");
        $stmt->execute([$status, $task_id, $user_id]);
    }
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Task status updated']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found or not authorized']);
    }
}

function logTime($pdo, $user_id) {
    $task_id = $_POST['task_id'] ?? 0;
    $time_spent = $_POST['time_spent'] ?? 0;
    $description = $_POST['description'] ?? '';
    
    if (!$task_id || !$time_spent) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID and time spent are required']);
        return;
    }
    
    // Verify the task belongs to the user
    $stmt = $pdo->prepare("SELECT id FROM engineering_tasks WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$task_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized to log time for this task']);
        return;
    }
    
    // Insert time log
    $stmt = $pdo->prepare("INSERT INTO time_logs (task_id, user_id, time_spent_minutes, log_date, description) VALUES (?, ?, ?, CURDATE(), ?)");
    $stmt->execute([$task_id, $user_id, $time_spent, $description]);
    
    // Update task actual hours
    $stmt = $pdo->prepare("UPDATE engineering_tasks SET actual_hours = actual_hours + (? / 60.0) WHERE id = ?");
    $stmt->execute([$time_spent, $task_id]);
    
    echo json_encode(['success' => true, 'message' => 'Time logged successfully']);
}

function assignQuote($pdo, $user_id) {
    // Only admins can assign quotes
    if ($user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized to assign quotes']);
        return;
    }
    
    $quote_id = $_POST['quote_id'] ?? 0;
    $engineer_id = $_POST['engineer_id'] ?? 0;
    $estimated_hours = $_POST['estimated_hours'] ?? 0;
    $hourly_rate = $_POST['hourly_rate'] ?? 0;
    $markup_percentage = $_POST['markup_percentage'] ?? 0;
    $material_cost = $_POST['material_cost'] ?? 0;
    $overhead_cost = $_POST['overhead_cost'] ?? 0;
    $commission_percentage = $_POST['commission_percentage'] ?? 0;
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    
    if (!$quote_id || !$engineer_id || !$estimated_hours || !$hourly_rate) {
        http_response_code(400);
        echo json_encode(['error' => 'All required fields are missing']);
        return;
    }
    
    // Calculate costs
    $labor_cost = $estimated_hours * $hourly_rate;
    $total_cost = $labor_cost + $material_cost + $overhead_cost;
    $markup_amount = $total_cost * ($markup_percentage / 100);
    $final_amount = $total_cost + $markup_amount;
    $commission_amount = $final_amount * ($commission_percentage / 100);
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update quote with assignment details
        $stmt = $pdo->prepare("UPDATE quotes SET sales_rep_id = ?, assigned_engineer_id = ?, estimated_hours = ?, hourly_rate = ?, markup_percentage = ?, material_cost = ?, overhead_cost = ?, labor_cost = ?, total_cost = ?, final_quote_amount = ?, commission_percentage = ?, commission_amount = ?, start_date = ?, end_date = ?, status = 'assigned', reviewed_at = NOW(), assigned_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id, $engineer_id, $estimated_hours, $hourly_rate, $markup_percentage, $material_cost, $overhead_cost, $labor_cost, $total_cost, $final_amount, $commission_percentage, $commission_amount, $start_date, $end_date, $quote_id]);
        
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
        $stmt->execute([$user_id, $quote_id, $commission_percentage, $commission_amount]);
        
        // Create notification for engineer
        $stmt = $pdo->prepare("INSERT INTO task_notifications (task_id, user_id, notification_type, message) SELECT id, ?, 'task_assigned', ? FROM engineering_tasks WHERE quote_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$engineer_id, "New task assigned: " . $task_title, $quote_id]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Quote assigned successfully']);
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Error assigning quote: ' . $e->getMessage()]);
    }
}

function finalizeQuote($pdo, $user_id) {
    // Only admins can finalize quotes
    if ($user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized to finalize quotes']);
        return;
    }
    
    $quote_id = $_POST['quote_id'] ?? 0;
    $final_amount = $_POST['final_amount'] ?? 0;
    
    if (!$quote_id || !$final_amount) {
        http_response_code(400);
        echo json_encode(['error' => 'Quote ID and final amount are required']);
        return;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update quote with final amount
        $stmt = $pdo->prepare("UPDATE quotes SET final_quote_amount = ?, status = 'completed', completed_at = NOW() WHERE id = ?");
        $stmt->execute([$final_amount, $quote_id]);
        
        // Update commission amount based on final amount
        $stmt = $pdo->prepare("SELECT commission_percentage, sales_rep_id FROM quotes WHERE id = ?");
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
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Quote finalized successfully']);
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Error finalizing quote: ' . $e->getMessage()]);
    }
}

function createInvoice($pdo, $user_id) {
    // Only admins can create invoices
    if ($user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized to create invoices']);
        return;
    }
    
    $quote_id = $_POST['quote_id'] ?? 0;
    $final_amount = $_POST['final_amount'] ?? 0;
    
    if (!$quote_id || !$final_amount) {
        http_response_code(400);
        echo json_encode(['error' => 'Quote ID and final amount are required']);
        return;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // In a real implementation, you would create an actual invoice/order
        // For now, we'll just update the quote status to invoiced
        $stmt = $pdo->prepare("UPDATE quotes SET status = 'invoiced', invoiced_at = NOW() WHERE id = ?");
        $stmt->execute([$quote_id]);
        
        // Mark commission as pending payment
        $stmt = $pdo->prepare("UPDATE sales_commissions SET status = 'pending' WHERE quote_id = ?");
        $stmt->execute([$quote_id]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Invoice created successfully']);
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Error creating invoice: ' . $e->getMessage()]);
    }
}

function completeTask($pdo, $user_id) {
    $task_id = $_POST['task_id'] ?? 0;
    $acceptance_rating = $_POST['acceptance_rating'] ?? 0;
    $feedback = $_POST['feedback'] ?? '';
    
    if (!$task_id || !$acceptance_rating) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID and acceptance rating are required']);
        return;
    }
    
    // Verify the task belongs to the user
    $stmt = $pdo->prepare("SELECT id FROM engineering_tasks WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$task_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized to complete this task']);
        return;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update task with acceptance rating and feedback
        $stmt = $pdo->prepare("UPDATE engineering_tasks SET status = 'completed', acceptance_rating = ?, feedback = ? WHERE id = ?");
        $stmt->execute([$acceptance_rating, $feedback, $task_id]);
        
        // Update quote actual hours
        $stmt = $pdo->prepare("SELECT actual_hours FROM engineering_tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();
        
        $stmt = $pdo->prepare("UPDATE quotes q JOIN engineering_tasks et ON q.id = et.quote_id SET q.actual_hours = et.actual_hours WHERE et.id = ?");
        $stmt->execute([$task_id]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Task completed successfully']);
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Error completing task: ' . $e->getMessage()]);
    }
}

function updateTask($pdo, $user_id, $user_role) {
    $task_id = $_POST['task_id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    
    if (!$task_id || !$title) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID and title are required']);
        return;
    }
    
    // Build query based on user role
    if ($user_role === 'admin') {
        $stmt = $pdo->prepare("UPDATE engineering_tasks SET title = ?, description = ?, start_date = ?, end_date = ? WHERE id = ?");
        $stmt->execute([$title, $description, $start_date, $end_date, $task_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE engineering_tasks SET title = ?, description = ?, start_date = ?, end_date = ? WHERE id = ? AND assigned_to = ?");
        $stmt->execute([$title, $description, $start_date, $end_date, $task_id, $user_id]);
    }
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found or not authorized']);
    }
}
?>