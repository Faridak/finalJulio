<?php
/**
 * 2FA Setup Script - Creates required tables for Two-Factor Authentication
 * Run this only if you want to enable 2FA functionality
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>2FA Setup - finalJulio</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
</head>
<body class='bg-gray-50'>
    <div class='max-w-4xl mx-auto px-4 py-8'>
        <div class='bg-white rounded-lg shadow-md p-8'>
            <h1 class='text-3xl font-bold text-gray-900 mb-6'>
                <i class='fas fa-shield-alt text-blue-600 mr-2'></i>
                Two-Factor Authentication Setup
            </h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_2fa'])) {
    try {
        echo "<div class='space-y-4'>";
        
        // Check if 2FA schema file exists
        $sqlFile = 'two_factor_auth_schema.sql';
        if (!file_exists($sqlFile)) {
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded'>
                    <h4 class='font-semibold'>Error: SQL file not found</h4>
                    <p>The file <code>two_factor_auth_schema.sql</code> was not found.</p>
                  </div>";
            exit;
        }
        
        echo "<div class='bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded'>
                <h4 class='font-semibold'>Setting up 2FA Database Tables...</h4>
              </div>";
        
        // Read and execute SQL file
        $sql = file_get_contents($sqlFile);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $successCount = 0;
        $warningCount = 0;
        $errors = [];
        
        foreach ($statements as $statement) {
            if (empty($statement)) continue;
            
            try {
                $pdo->exec($statement);
                $successCount++;
                
                if (preg_match('/CREATE TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?(\w+)`?/i', $statement, $matches)) {
                    echo "<div class='text-green-600'><i class='fas fa-check mr-2'></i>Created table: {$matches[1]}</div>";
                }
                
            } catch (PDOException $e) {
                $errorMsg = $e->getMessage();
                
                if (strpos($errorMsg, 'already exists') !== false) {
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
                    <h4 class='font-semibold'><i class='fas fa-check-circle mr-2'></i>2FA Setup Completed Successfully!</h4>
                    <p>Executed $successCount SQL statements with $warningCount warnings.</p>
                  </div>";
                  
            // Verify tables exist
            echo "<div class='mt-6'>";
            echo "<h4 class='font-semibold mb-3'>Verifying 2FA Tables:</h4>";
            echo "<div class='grid grid-cols-2 gap-4'>";
            
            $requiredTables = ['user_2fa', 'user_2fa_backup_codes', 'user_trusted_devices'];
            foreach ($requiredTables as $table) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table");
                    $stmt->execute();
                    $count = $stmt->fetchColumn();
                    echo "<div class='bg-green-50 border border-green-200 text-green-700 px-3 py-2 rounded'>
                            <i class='fas fa-table mr-2'></i>$table: Ready
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
                    <div class='bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-4'>
                        <h4 class='font-semibold'>Next Steps:</h4>
                        <ol class='text-left mt-2'>
                            <li>1. Uncomment the 2FA code in login.php</li>
                            <li>2. Test the 2FA functionality</li>
                            <li>3. Configure 2FA settings for users</li>
                        </ol>
                    </div>
                    <a href='login.php' class='bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200'>
                        <i class='fas fa-sign-in-alt mr-2'></i>Go to Login
                    </a>
                    <button onclick='window.close()' class='ml-4 bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition duration-200'>
                        <i class='fas fa-times mr-2'></i>Close
                    </button>
                  </div>";
        } else {
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-4'>
                    <h4 class='font-semibold'><i class='fas fa-exclamation-triangle mr-2'></i>Setup completed with errors</h4>
                    <p>Some errors occurred during setup. Please check the log above.</p>
                  </div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded'>
                <h4 class='font-semibold'>Critical Error</h4>
                <p>Error during setup: " . htmlspecialchars($e->getMessage()) . "</p>
              </div>";
    }
} else {
    // Check if tables already exist
    $tablesExist = true;
    $requiredTables = ['user_2fa', 'user_2fa_backup_codes', 'user_trusted_devices'];
    $existingTables = [];
    
    foreach ($requiredTables as $table) {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->fetch()) {
                $existingTables[] = $table;
            } else {
                $tablesExist = false;
            }
        } catch (PDOException $e) {
            $tablesExist = false;
            break;
        }
    }
    
    if ($tablesExist) {
        echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6'>
                <h4 class='font-semibold'><i class='fas fa-check-circle mr-2'></i>2FA Tables Already Exist</h4>
                <p>All required 2FA tables are already installed in your database.</p>
              </div>";
              
        echo "<div class='grid grid-cols-1 md:grid-cols-3 gap-4 mb-6'>";
        foreach ($existingTables as $table) {
            echo "<div class='bg-white border border-green-200 rounded-lg p-4 text-center'>
                    <i class='fas fa-table text-green-600 text-2xl mb-2'></i>
                    <h4 class='font-semibold text-gray-900'>$table</h4>
                    <p class='text-sm text-green-600'>Ready</p>
                  </div>";
        }
        echo "</div>";
        
        echo "<div class='text-center'>
                <a href='login.php' class='bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200'>
                    <i class='fas fa-sign-in-alt mr-2'></i>Go to Login
                </a>
              </div>";
    } else {
        echo "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-6'>
                <h4 class='font-semibold'><i class='fas fa-exclamation-triangle mr-2'></i>2FA Tables Not Found</h4>
                <p>The required 2FA database tables are not installed. Click below to set them up.</p>
              </div>";
              
        echo "<div class='grid grid-cols-1 md:grid-cols-3 gap-4 mb-6'>";
        foreach ($requiredTables as $table) {
            $exists = in_array($table, $existingTables);
            $iconClass = $exists ? 'fa-check text-green-600' : 'fa-times text-red-600';
            $statusText = $exists ? 'Exists' : 'Missing';
            $bgClass = $exists ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
            
            echo "<div class='$bgClass border rounded-lg p-4 text-center'>
                    <i class='fas $iconClass text-2xl mb-2'></i>
                    <h4 class='font-semibold text-gray-900'>$table</h4>
                    <p class='text-sm'>$statusText</p>
                  </div>";
        }
        echo "</div>";
        
        echo "<form method='POST' class='text-center'>
                <button type='submit' name='setup_2fa' 
                        class='bg-blue-600 text-white px-8 py-4 rounded-lg hover:bg-blue-700 transition-colors text-lg font-semibold'
                        onclick='return confirm(\"This will create 2FA database tables. Continue?\")'>
                    <i class='fas fa-shield-alt mr-2'></i>Install 2FA Tables
                </button>
              </form>";
    }
}

echo "    </div>
        </div>
    </div>
</body>
</html>";
?>