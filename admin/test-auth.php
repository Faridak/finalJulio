<?php
require_once 'config/database.php';

echo "Session Status: " . session_status() . "\n";
echo "Is Logged In: " . (isLoggedIn() ? 'YES' : 'NO') . "\n";
echo "User Role: " . (getUserRole() ?? 'NONE') . "\n";

if (isset($_SESSION)) {
    echo "Session Data:\n";
    foreach ($_SESSION as $key => $value) {
        if ($key !== 'csrf_token') { // Don't display CSRF token
            echo "  $key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
        }
    }
} else {
    echo "No session data\n";
}
?>