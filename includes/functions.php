<?php
// Placeholder helper functions required by composer autoload
if (!function_exists('requireCSRF')) {
    function requireCSRF() {
        // Attempt to validate CSRF token using Security helper
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!\Security::validateCSRFToken($token)) {
                http_response_code(403);
                die('CSRF token mismatch or missing');
            }
        }
    }
}

// Small helper to require role - kept as a safe fallback
if (!function_exists('requireRole')) {
    function requireRole($role) {
        if (!isset($_SESSION)) {
            session_start();
        }
        $userRole = $_SESSION['user_role'] ?? null;
        if ($userRole !== $role) {
            header('Location: ../login.php');
            exit;
        }
    }
}

?>
