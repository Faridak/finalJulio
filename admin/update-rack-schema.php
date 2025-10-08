<?php
require_once '../config/database.php';

header('Content-Type: text/html; charset=utf-8');

try {
    // Create PDO connection
    $host = 'localhost';
    $dbname = 'finalJulio';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Updating Storage Racks Table Schema</h2>";
    
    // Check if levels column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM storage_racks LIKE 'levels'");
    if ($stmt->rowCount() == 0) {
        echo "<p>Adding 'levels' column...</p>";
        $pdo->exec("ALTER TABLE storage_racks ADD COLUMN levels INT DEFAULT 5 AFTER rack_type");
        echo "<p style='color: green;'>✓ Added 'levels' column</p>";
    } else {
        echo "<p style='color: blue;'>✓ 'levels' column already exists</p>";
    }
    
    // Check if positions column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM storage_racks LIKE 'positions'");
    if ($stmt->rowCount() == 0) {
        echo "<p>Adding 'positions' column...</p>";
        $pdo->exec("ALTER TABLE storage_racks ADD COLUMN positions INT DEFAULT 10 AFTER levels");
        echo "<p style='color: green;'>✓ Added 'positions' column</p>";
    } else {
        echo "<p style='color: blue;'>✓ 'positions' column already exists</p>";
    }
    
    // Update existing racks with default values if needed
    echo "<p>Updating existing racks with proper levels and positions...</p>";
    
    // Calculate actual levels and positions from existing bins
    $stmt = $pdo->query("
        UPDATE storage_racks sr
        SET 
            levels = COALESCE((
                SELECT MAX(level_number) 
                FROM inventory_bins ib 
                WHERE ib.rack_id = sr.id AND ib.status = 'active'
            ), 5),
            positions = COALESCE((
                SELECT MAX(position_number) 
                FROM inventory_bins ib 
                WHERE ib.rack_id = sr.id AND ib.status = 'active'
            ), 10)
        WHERE sr.status = 'active'
    ");
    
    $updatedRacks = $stmt->rowCount();
    echo "<p style='color: green;'>✓ Updated {$updatedRacks} racks with calculated levels and positions</p>";
    
    // Show current rack status
    $stmt = $pdo->query("
        SELECT rack_code, rack_name, levels, positions, 
               (SELECT COUNT(*) FROM inventory_bins WHERE rack_id = sr.id AND status = 'active') as bin_count
        FROM storage_racks sr 
        WHERE status = 'active' 
        ORDER BY rack_code
    ");
    
    $racks = $stmt->fetchAll();
    
    echo "<h3 style='margin-top: 20px;'>Current Rack Configuration:</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr><th>Rack Code</th><th>Rack Name</th><th>Levels</th><th>Positions</th><th>Total Bins</th></tr>";
    
    foreach ($racks as $rack) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($rack['rack_code']) . "</td>";
        echo "<td>" . htmlspecialchars($rack['rack_name']) . "</td>";
        echo "<td>" . $rack['levels'] . "</td>";
        echo "<td>" . $rack['positions'] . "</td>";
        echo "<td>" . $rack['bin_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><p style='color: green; font-weight: bold;'>✅ Schema update completed successfully!</p>";
    echo "<p><a href='warehouse-config.php' style='color: blue;'>← Back to Warehouse Configuration</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>