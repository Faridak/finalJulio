<?php
/**
 * Run C-Level Financial Reporting Migration
 */

require_once 'config/database.php';

// Check if user is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access');
}

try {
    $pdo = getDBConnection();
    
    // Read the migration file
    $migrationFile = 'migrations/c_level_reporting_schema.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Split the SQL into individual statements
    $statements = explode(';', $sql);
    
    $pdo->beginTransaction();
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
        } catch (PDOException $e) {
            // Ignore duplicate key errors for INSERT IGNORE statements
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                echo "Error executing statement: " . $e->getMessage() . "\n";
                echo "Statement: " . substr($statement, 0, 100) . "...\n\n";
                $errorCount++;
            } else {
                $successCount++;
            }
        }
    }
    
    $pdo->commit();
    
    echo "Migration completed successfully!\n";
    echo "Statements executed: $successCount\n";
    echo "Errors: $errorCount\n";
    
    if ($errorCount === 0) {
        echo "\nC-Level Financial Reporting schema has been successfully installed.\n";
        echo "You can now access the executive dashboard at admin/c-level-dashboard.php\n";
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>