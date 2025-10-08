<?php
// Accounting Queue Worker
// This script processes background jobs from the accounting queue

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/queue-system.php';

// Check if script is being run from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Create and start the queue worker
$worker = new AccountingQueueWorker($pdo);

// Handle shutdown gracefully
declare(ticks = 1);
pcntl_signal(SIGTERM, function() use ($worker) {
    echo "Received SIGTERM, shutting down gracefully...\n";
    $worker->stop();
});

pcntl_signal(SIGINT, function() use ($worker) {
    echo "Received SIGINT, shutting down gracefully...\n";
    $worker->stop();
});

// Start processing jobs
$worker->start('accounting');

?>