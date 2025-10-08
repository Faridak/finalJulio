<?php
// Simplified database configuration for VentDepot
$host = 'localhost';
$dbname = 'finalJulio';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "Database connection successful\n";
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Setup script for simple warehouse inventory system
try {
    // Create racks table
    $sql = "CREATE TABLE IF NOT EXISTS warehouse_racks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rack_code VARCHAR(10) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "Created warehouse_racks table\n";
    
    // Create shelves table
    $sql = "CREATE TABLE IF NOT EXISTS warehouse_shelves (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rack_id INT NOT NULL,
        shelf_level INT NOT NULL,
        name VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (rack_id) REFERENCES warehouse_racks(id) ON DELETE CASCADE,
        UNIQUE KEY unique_rack_shelf (rack_id, shelf_level)
    )";
    
    $pdo->exec($sql);
    echo "Created warehouse_shelves table\n";
    
    // Create bins table
    $sql = "CREATE TABLE IF NOT EXISTS warehouse_bins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shelf_id INT NOT NULL,
        bin_position INT NOT NULL,
        bin_code VARCHAR(20) NOT NULL UNIQUE,
        status ENUM('empty', 'partial', 'full', 'blocked') DEFAULT 'empty',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (shelf_id) REFERENCES warehouse_shelves(id) ON DELETE CASCADE,
        UNIQUE KEY unique_shelf_bin (shelf_id, bin_position)
    )";
    
    $pdo->exec($sql);
    echo "Created warehouse_bins table\n";
    
    // Create inventory items table
    $sql = "CREATE TABLE IF NOT EXISTS warehouse_inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bin_id INT NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        sku VARCHAR(100),
        quantity INT NOT NULL DEFAULT 1,
        date_arrived DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (bin_id) REFERENCES warehouse_bins(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($sql);
    echo "Created warehouse_inventory table\n";
    
    // Insert sample data
    insertSampleData($pdo);
    
    echo "Simple warehouse inventory system setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error setting up warehouse inventory system: " . $e->getMessage() . "\n";
}

function insertSampleData($pdo) {
    try {
        // Insert sample racks
        $stmt = $pdo->prepare("INSERT IGNORE INTO warehouse_racks (rack_code, name) VALUES (?, ?)");
        for ($i = 1; $i <= 3; $i++) {
            $stmt->execute(["R$i", "Rack $i"]);
        }
        echo "Inserted sample racks\n";
        
        // Insert sample shelves
        $stmt = $pdo->prepare("INSERT IGNORE INTO warehouse_shelves (rack_id, shelf_level, name) VALUES (?, ?, ?)");
        for ($rackId = 1; $rackId <= 3; $rackId++) {
            for ($shelfLevel = 1; $shelfLevel <= 5; $shelfLevel++) {
                $stmt->execute([$rackId, $shelfLevel, "Level $shelfLevel"]);
            }
        }
        echo "Inserted sample shelves\n";
        
        // Insert sample bins
        $stmt = $pdo->prepare("INSERT IGNORE INTO warehouse_bins (shelf_id, bin_position, bin_code, status) VALUES (?, ?, ?, ?)");
        for ($rackId = 1; $rackId <= 3; $rackId++) {
            for ($shelfLevel = 1; $shelfLevel <= 5; $shelfLevel++) {
                // Get shelf ID
                $shelfStmt = $pdo->prepare("SELECT id FROM warehouse_shelves WHERE rack_id = ? AND shelf_level = ?");
                $shelfStmt->execute([$rackId, $shelfLevel]);
                $shelf = $shelfStmt->fetch();
                
                if ($shelf) {
                    for ($binPos = 1; $binPos <= 10; $binPos++) {
                        $binCode = "R{$rackId}-S{$shelfLevel}-B" . str_pad($binPos, 2, '0', STR_PAD_LEFT);
                        $status = (rand(1, 10) <= 3) ? 'partial' : 'empty'; // 30% chance of being partial
                        $stmt->execute([$shelf['id'], $binPos, $binCode, $status]);
                    }
                }
            }
        }
        echo "Inserted sample bins\n";
        
        // Insert sample inventory items
        $stmt = $pdo->prepare("INSERT IGNORE INTO warehouse_inventory (bin_id, item_name, sku, quantity, date_arrived) VALUES (?, ?, ?, ?, ?)");
        
        // Add items to some bins
        $binStmt = $pdo->prepare("SELECT id FROM warehouse_bins WHERE status = 'partial' LIMIT 20");
        $binStmt->execute();
        $bins = $binStmt->fetchAll();
        
        foreach ($bins as $bin) {
            $itemCount = rand(1, 3);
            for ($i = 0; $i < $itemCount; $i++) {
                $itemName = "Product " . chr(65 + rand(0, 25)) . rand(100, 999);
                $sku = "SKU-" . strtoupper(substr(md5(uniqid()), 0, 6));
                $quantity = rand(1, 50);
                $dateArrived = date('Y-m-d', strtotime('-' . rand(1, 60) . ' days'));
                $stmt->execute([$bin['id'], $itemName, $sku, $quantity, $dateArrived]);
            }
        }
        echo "Inserted sample inventory items\n";
        
    } catch (Exception $e) {
        echo "Error inserting sample data: " . $e->getMessage() . "\n";
    }
}
?>