<?php
// Simple database setup script for supplier module
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Supplier Module Setup - VentDepot</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
</head>
<body class='bg-gray-50'>
    <div class='max-w-4xl mx-auto px-4 py-8'>
        <div class='bg-white rounded-lg shadow-md p-8'>
            <h1 class='text-3xl font-bold text-gray-900 mb-6'>Supplier Module Database Setup</h1>";

try {
    echo "<div class='space-y-4'>";
    
    // Check if supplier_inventory_module.sql exists
    $sqlFile = 'supplier_inventory_module.sql';
    if (!file_exists($sqlFile)) {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded'>
                <h4 class='font-semibold'>Error: SQL file not found</h4>
                <p>The file <code>supplier_inventory_module.sql</code> was not found in the root directory.</p>
              </div>";
        exit;
    }
    
    echo "<div class='bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded'>
            <h4 class='font-semibold'><i class='fas fa-info-circle mr-2'></i>Starting Database Setup</h4>
            <p>Reading and executing SQL statements from supplier_inventory_module.sql...</p>
          </div>";
    
    // Read SQL file
    $sql = file_get_contents($sqlFile);
    
    // Remove comments and split by semicolon
    $sql = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove multi-line comments
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $warningCount = 0;
    $errors = [];
    
    echo "<div class='bg-gray-50 border rounded p-4 max-h-64 overflow-y-auto'>";
    echo "<h5 class='font-semibold mb-2'>Execution Log:</h5>";
    
    foreach ($statements as $statement) {
        if (empty($statement) || strlen($statement) < 10) continue;
        
        try {
            $pdo->exec($statement);
            $successCount++;
            
            // Try to extract table name from CREATE TABLE statements
            if (preg_match('/CREATE TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?(\w+)`?/i', $statement, $matches)) {
                echo "<div class='text-green-600'><i class='fas fa-check mr-2'></i>Created/verified table: {$matches[1]}</div>";
            } elseif (preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?/i', $statement, $matches)) {
                echo "<div class='text-blue-600'><i class='fas fa-plus mr-2'></i>Inserted data into: {$matches[1]}</div>";
            } elseif (preg_match('/CREATE\s+INDEX/i', $statement)) {
                echo "<div class='text-purple-600'><i class='fas fa-index mr-2'></i>Created index</div>";
            }
            
        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();
            
            // Ignore certain "errors" that are actually OK
            if (strpos($errorMsg, 'already exists') !== false || 
                strpos($errorMsg, 'Duplicate key') !== false ||
                strpos($errorMsg, 'Duplicate entry') !== false) {
                $warningCount++;
                echo "<div class='text-yellow-600'><i class='fas fa-exclamation-triangle mr-2'></i>Warning: " . htmlspecialchars($errorMsg) . "</div>";
            } else {
                $errors[] = $errorMsg;
                echo "<div class='text-red-600'><i class='fas fa-times mr-2'></i>Error: " . htmlspecialchars($errorMsg) . "</div>";
            }
        }
    }
    
    echo "</div>";
    
    if (count($errors) == 0) {
        echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mt-4'>
                <h4 class='font-semibold'><i class='fas fa-check-circle mr-2'></i>Setup Completed Successfully!</h4>
                <p>Executed $successCount SQL statements with $warningCount warnings.</p>
              </div>";
              
        // Verify tables exist
        echo "<div class='mt-6'>";
        echo "<h4 class='font-semibold mb-3'>Verifying Tables:</h4>";
        echo "<div class='grid grid-cols-2 gap-4'>";
        
        $requiredTables = ['suppliers', 'supplier_products', 'purchase_orders', 'inventory_locations'];
        foreach ($requiredTables as $table) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table");
                $stmt->execute();
                $count = $stmt->fetchColumn();
                echo "<div class='bg-green-50 border border-green-200 text-green-700 px-3 py-2 rounded'>
                        <i class='fas fa-table mr-2'></i>$table: $count records
                      </div>";
            } catch (PDOException $e) {
                echo "<div class='bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded'>
                        <i class='fas fa-times mr-2'></i>$table: Missing
                      </div>";
            }
        }
        echo "</div>";
        echo "</div>";
              
        echo "<div class='mt-6 text-center'>
                <a href='admin/suppliers.php' class='bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200'>
                    <i class='fas fa-truck mr-2'></i>Go to Supplier Management
                </a>
                <button onclick='window.close()' class='ml-4 bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition duration-200'>
                    <i class='fas fa-times mr-2'></i>Close
                </button>
              </div>";
    } else {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-4'>
                <h4 class='font-semibold'><i class='fas fa-exclamation-triangle mr-2'></i>Setup completed with errors</h4>
                <p>Some errors occurred during setup. Please check the log above and ensure your database connection is working.</p>
              </div>";
    }
    
} catch (Exception $e) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded'>
            <h4 class='font-semibold'>Critical Error</h4>
            <p>Error during setup: " . htmlspecialchars($e->getMessage()) . "</p>
          </div>";
}

echo "    </div>
        </div>
    </div>
</body>
</html>";
?>