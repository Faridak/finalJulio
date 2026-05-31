<?php
// Minimal helper functions used across the app - placeholder implementations
if (!function_exists('env')) {
    function env($key, $default = null) {
        if (array_key_exists($key, $_ENV)) return $_ENV[$key];
        if (getenv($key) !== false) return getenv($key);
        return $default;
    }
}

if (!function_exists('requireCSRF')) {
    // Forward to functions.php implementation if not already defined
    require_once __DIR__ . '/functions.php';
}

?>
