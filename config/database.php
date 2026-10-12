<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Load environment variables
require_once __DIR__ . '/env.php';

// One clock for the whole app. PHP and MySQL MUST agree: discount windows,
// voucher expiry and reservation TTLs are compared in SQL but often written
// from PHP. APP_TIMEZONE keeps dev machines and the server aligned.
date_default_timezone_set(env('APP_TIMEZONE', 'America/Mexico_City'));

// Database Configuration (from environment; safe local defaults)
$host = env('DB_HOST', '127.0.0.1');
$port = env('DB_PORT', '3306');
$dbname = env('DB_DATABASE', 'ventdepot');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    // Align the DB session clock with PHP's (offset form works on any host).
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
} catch(PDOException $e) {
    // Never leak connection details to the client.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Service temporarily unavailable. Please try again later.');
}

// Include engineering helpers
require_once __DIR__ . '/../includes/engineering_helpers.php';
// Media URL resolver (local disk vs object storage / CDN)
require_once __DIR__ . '/../includes/asset.php';

// Include other helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? '';
}

/**
 * Staff scope within the admin role — 'all' (full) or a single section:
 * finance, warehouse, catalog, orders, sellers. Lazy-loaded from the DB the
 * first time it's needed (so existing sessions keep working after deploy).
 */
function getUserScope() {
    if (!isset($_SESSION['staff_scope']) && isLoggedIn()) {
        global $pdo;
        try {
            $s = $pdo->prepare("SELECT staff_scope FROM users WHERE id = ?");
            $s->execute([$_SESSION['user_id']]);
            $_SESSION['staff_scope'] = $s->fetchColumn() ?: 'all';
        } catch (\Throwable $e) { $_SESSION['staff_scope'] = 'all'; }
    }
    return $_SESSION['staff_scope'] ?? 'all';
}

function requireRole($role) {
    $userRole = getUserRole();
    // 'staff' members share the admin AREA; requireScope() + the nav filter
    // limit them to their own section (warehouse, sales, marketing…).
    $ok = $userRole === $role || ($role === 'admin' && $userRole === 'staff');
    if (!isLoggedIn() || !$ok) {
        header('Location: ../login.php');
        exit;
    }
}

/** True for both full admins and scoped staff (the whole back-office team). */
function isStaff() {
    return in_array(getUserRole(), ['admin', 'staff'], true);
}

/**
 * Gate an admin page to a section. Full admins ('all' scope) pass everything;
 * scoped staff only pass their own section. Call AFTER requireRole('admin').
 *   requireScope('finance');
 */
function requireScope($section) {
    $scope = getUserScope();
    if ($scope === 'all' || $scope === $section) return;
    http_response_code(403);
    echo '<!DOCTYPE html><meta charset="utf-8"><div style="font-family:sans-serif;max-width:32rem;margin:4rem auto;text-align:center">'
       . '<h1 style="font-size:1.5rem">Access restricted</h1>'
       . '<p style="color:#666">Your staff account is scoped to <b>' . htmlspecialchars($scope) . '</b> and cannot open the <b>'
       . htmlspecialchars($section) . '</b> section. Ask an administrator if you need access.</p>'
       . '<p><a href="dashboard.php" style="color:#2563eb">← Back to dashboard</a></p></div>';
    exit;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getCartCount() {
    return array_sum($_SESSION['cart'] ?? []);
}
?>
