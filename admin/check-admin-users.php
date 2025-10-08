<?php
require_once 'config/database.php';

try {
    $stmt = $pdo->query("SELECT id, email, role FROM users WHERE role = 'admin' LIMIT 5");
    $admins = $stmt->fetchAll();
    
    echo "Admin users found: " . count($admins) . "\n";
    
    foreach ($admins as $admin) {
        echo "- " . $admin['email'] . " (" . $admin['role'] . ")\n";
    }
    
    if (count($admins) == 0) {
        echo "No admin users found. You'll need to create an admin user or log in as an existing one.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>