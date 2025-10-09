<?php
// Engineering Module Helper Functions

/**
 * Check if the current user is an engineer
 * Engineers are identified by having 'engineer' in their email address
 * 
 * @param PDO $pdo Database connection
 * @return bool True if user is an engineer
 */
function isEngineer($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && strpos($user['email'], 'engineer') !== false) {
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get engineer ID by user ID
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return int|null Engineer ID or null if not found
 */
function getEngineerId($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND email LIKE '%engineer%'");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        return $result ? $result['id'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get all engineers
 * 
 * @param PDO $pdo Database connection
 * @return array Array of engineers
 */
function getAllEngineers($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email LIKE '%engineer%' ORDER BY email");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get task status badge class
 * 
 * @param string $status Task status
 * @return string CSS class for badge
 */
function getTaskStatusBadgeClass($status) {
    switch ($status) {
        case 'new':
            return 'bg-gray-100 text-gray-800';
        case 'assigned':
            return 'bg-blue-100 text-blue-800';
        case 'in_progress':
            return 'bg-yellow-100 text-yellow-800';
        case 'review':
            return 'bg-purple-100 text-purple-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

/**
 * Format hours for display
 * 
 * @param float $hours Hours
 * @return string Formatted hours
 */
function formatHours($hours) {
    return number_format($hours, 1) . 'h';
}

/**
 * Calculate task progress percentage
 * 
 * @param float $actual_hours Actual hours worked
 * @param float $estimated_hours Estimated hours
 * @return int Progress percentage
 */
function calculateTaskProgress($actual_hours, $estimated_hours) {
    if ($estimated_hours <= 0) {
        return 0;
    }
    
    return min(100, round(($actual_hours / $estimated_hours) * 100));
}
?>