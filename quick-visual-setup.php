<?php
require_once 'config/database.php';

echo "<h2>Setting up Visual Inventory System with Sample Data</h2>";

try {
    // Check if enhanced inventory tables exist
    $requiredTables = ['warehouse_zones', 'storage_racks', 'inventory_bins'];
    $tablesExist = true;
    
    foreach ($requiredTables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            $tablesExist = false;
            echo "<p style='color: red;'>❌ Table '$table' does not exist</p>";
        } else {
            echo "<p style='color: green;'>✅ Table '$table' exists</p>";
        }
    }
    
    if (!$tablesExist) {
        echo "<p><strong>Please run the enhanced inventory setup first:</strong></p>";
        echo "<p><a href='setup-enhanced-inventory.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Setup Enhanced Inventory</a></p>";
        exit;
    }
    
    // Check if sample data exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM warehouse_zones");
    $stmt->execute();
    $zoneCount = $stmt->fetchColumn();
    
    if ($zoneCount == 0) {
        echo "<h3>Creating Sample Warehouse Data...</h3>";
        
        // Get or create a location
        $stmt = $pdo->prepare("SELECT id FROM inventory_locations WHERE status = 'active' LIMIT 1");
        $stmt->execute();
        $location = $stmt->fetch();
        
        if (!$location) {
            // Create a sample location
            $stmt = $pdo->prepare("INSERT INTO inventory_locations (location_name, location_code, address, status) VALUES (?, ?, ?, 'active')");
            $stmt->execute(['Main Warehouse', 'WH001', '123 Industrial St, City, State 12345']);
            $locationId = $pdo->lastInsertId();
            echo "<p>✅ Created sample warehouse location</p>";
        } else {
            $locationId = $location['id'];
            echo "<p>✅ Using existing warehouse location</p>";
        }
        
        // Create zones
        $zones = [
            ['A', 'Receiving Zone', 'receiving'],
            ['B', 'Main Storage', 'storage'], 
            ['C', 'Picking Zone', 'picking'],
            ['D', 'Shipping Zone', 'shipping']
        ];
        
        foreach ($zones as $zone) {
            $stmt = $pdo->prepare("INSERT INTO warehouse_zones (location_id, zone_code, zone_name, zone_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$locationId, $zone[0], $zone[1], $zone[2]]);
            $zoneId = $pdo->lastInsertId();
            
            // Create racks in each zone
            for ($rack = 1; $rack <= 4; $rack++) {
                $rackCode = sprintf("R%02d", $rack);
                $stmt = $pdo->prepare("INSERT INTO storage_racks (zone_id, rack_code, rack_name, total_levels, total_positions) VALUES (?, ?, ?, 4, 10)");
                $stmt->execute([$zoneId, $rackCode, "Rack $rackCode", 4, 10]);
                $rackId = $pdo->lastInsertId();
                
                // Create bins for each rack (4 levels, 10 positions each)
                for ($level = 1; $level <= 4; $level++) {
                    for ($position = 1; $position <= 10; $position++) {
                        $binCode = sprintf("L%02dP%02d", $level, $position);
                        $binAddress = "{$zone[0]}-{$rackCode}-L{$level}-P{$position}";
                        
                        // Random occupancy for demo
                        $occupancyStatuses = ['empty', 'partial', 'full'];
                        $occupancyStatus = $occupancyStatuses[array_rand($occupancyStatuses)];
                        $utilization = match($occupancyStatus) {
                            'empty' => 0,
                            'partial' => rand(20, 70),
                            'full' => rand(85, 100),
                            default => 0
                        };
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO inventory_bins 
                            (rack_id, bin_code, bin_address, level_number, position_number, 
                             occupancy_status, utilization_percentage, volume_liters, weight_capacity_kg)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 100, 50)
                        ");
                        $stmt->execute([$rackId, $binCode, $binAddress, $level, $position, $occupancyStatus, $utilization]);
                    }
                }
            }
            echo "<p>✅ Created Zone {$zone[0]} with 4 racks and 160 bins</p>";
        }
        
        echo "<h3>Sample Data Creation Complete!</h3>";
        echo "<p><strong>Created:</strong></p>";
        echo "<ul>";
        echo "<li>4 Warehouse Zones (Receiving, Storage, Picking, Shipping)</li>";
        echo "<li>16 Storage Racks (4 per zone)</li>";
        echo "<li>640 Individual Bins (4 levels × 10 positions × 16 racks)</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: green;'>✅ Sample data already exists ($zoneCount zones found)</p>";
    }
    
    // Show current stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM storage_racks");
    $stmt->execute();
    $rackCount = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_bins");
    $stmt->execute();
    $binCount = $stmt->fetchColumn();
    
    echo "<h3>Current System Stats:</h3>";
    echo "<ul>";
    echo "<li><strong>Zones:</strong> $zoneCount</li>";
    echo "<li><strong>Racks:</strong> $rackCount</li>";
    echo "<li><strong>Bins:</strong> $binCount</li>";
    echo "</ul>";
    
    echo "<h3>Ready to Use!</h3>";
    echo "<p>Your visual inventory system is now ready with sample data.</p>";
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='visual-inventory-demo.php' style='background: #059669; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; margin-right: 10px;'>📊 View Demo Page</a>";
    echo "<a href='admin/inventory-visual.php' style='background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px;'>🏭 Open Visual Warehouse</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo "<p><strong>The enhanced inventory schema needs to be installed first.</strong></p>";
        echo "<p><a href='setup-enhanced-inventory.php' style='background: #dc2626; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Install Enhanced Inventory Schema</a></p>";
    }
}
?>