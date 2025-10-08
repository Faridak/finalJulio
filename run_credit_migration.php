<?php
// Run Credit Management Database Migration
require_once 'config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Running Credit Management Database Migration...\n";
    
    // Read the migration file
    $migrationSql = file_get_contents('migrations/credit_management_schema.sql');
    
    // Split the SQL into individual statements
    $statements = explode(';', $migrationSql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                echo "Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (PDOException $e) {
                // Ignore duplicate key errors, but show other errors
                if (strpos($e->getMessage(), 'duplicate') === false) {
                    echo "Warning: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "Credit Management Database Migration completed successfully!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>