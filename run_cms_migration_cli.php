<?php
/**
 * Run CMS Frontend Migration (CLI Version)
 */

require_once 'config/database.php';

try {
    // Read the migration file
    $migrationFile = 'migrations/cms_frontend_schema.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Split the SQL into individual statements
    $statements = explode(';', $sql);
    
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
    
    echo "Migration completed successfully!\n";
    echo "Statements executed: $successCount\n";
    echo "Errors: $errorCount\n";
    
    if ($errorCount === 0) {
        echo "\nCMS Frontend schema has been successfully installed.\n";
        echo "You can now access the CMS dashboard at admin/cms-dashboard.php\n";
    }
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>