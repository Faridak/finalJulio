<?php
// Setup script for commission tier update triggers
// This script creates database triggers for automatic commission tier updates

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create trigger to automatically update commission tier when sales commissions are inserted or updated
    $sql = "
    DELIMITER //

    CREATE TRIGGER update_commission_tier_after_insert
    AFTER INSERT ON sales_commissions
    FOR EACH ROW
    BEGIN
        DECLARE applicable_tier VARCHAR(50);
        DECLARE new_commission_rate DECIMAL(5,4);
        
        -- Determine applicable tier based on total sales
        SELECT tier_name, commission_rate 
        INTO applicable_tier, new_commission_rate
        FROM commission_tiers 
        WHERE NEW.total_sales >= min_sales_threshold 
        ORDER BY min_sales_threshold DESC 
        LIMIT 1;
        
        -- Update the commission record with the applicable tier and rate
        IF applicable_tier IS NOT NULL THEN
            UPDATE sales_commissions 
            SET tier_level = applicable_tier, commission_rate = new_commission_rate
            WHERE id = NEW.id;
        END IF;
    END//

    CREATE TRIGGER update_commission_tier_after_update
    AFTER UPDATE ON sales_commissions
    FOR EACH ROW
    BEGIN
        DECLARE applicable_tier VARCHAR(50);
        DECLARE new_commission_rate DECIMAL(5,4);
        
        -- Only update if total_sales changed
        IF OLD.total_sales != NEW.total_sales THEN
            -- Determine applicable tier based on total sales
            SELECT tier_name, commission_rate 
            INTO applicable_tier, new_commission_rate
            FROM commission_tiers 
            WHERE NEW.total_sales >= min_sales_threshold 
            ORDER BY min_sales_threshold DESC 
            LIMIT 1;
            
            -- Update the commission record with the applicable tier and rate
            IF applicable_tier IS NOT NULL THEN
                UPDATE sales_commissions 
                SET tier_level = applicable_tier, commission_rate = new_commission_rate
                WHERE id = NEW.id;
            END IF;
        END IF;
    END//

    DELIMITER ;
    ";
    
    // Since we can't execute DELIMITER in PDO, we'll create the triggers separately
    $trigger1 = "
    CREATE TRIGGER update_commission_tier_after_insert
    AFTER INSERT ON sales_commissions
    FOR EACH ROW
    BEGIN
        DECLARE applicable_tier VARCHAR(50);
        DECLARE new_commission_rate DECIMAL(5,4);
        
        -- Determine applicable tier based on total sales
        SELECT tier_name, commission_rate 
        INTO applicable_tier, new_commission_rate
        FROM commission_tiers 
        WHERE NEW.total_sales >= min_sales_threshold 
        ORDER BY min_sales_threshold DESC 
        LIMIT 1;
        
        -- Update the commission record with the applicable tier and rate
        IF applicable_tier IS NOT NULL THEN
            UPDATE sales_commissions 
            SET tier_level = applicable_tier, commission_rate = new_commission_rate
            WHERE id = NEW.id;
        END IF;
    END;
    ";
    
    $trigger2 = "
    CREATE TRIGGER update_commission_tier_after_update
    AFTER UPDATE ON sales_commissions
    FOR EACH ROW
    BEGIN
        DECLARE applicable_tier VARCHAR(50);
        DECLARE new_commission_rate DECIMAL(5,4);
        
        -- Only update if total_sales changed
        IF OLD.total_sales != NEW.total_sales THEN
            -- Determine applicable tier based on total sales
            SELECT tier_name, commission_rate 
            INTO applicable_tier, new_commission_rate
            FROM commission_tiers 
            WHERE NEW.total_sales >= min_sales_threshold 
            ORDER BY min_sales_threshold DESC 
            LIMIT 1;
            
            -- Update the commission record with the applicable tier and rate
            IF applicable_tier IS NOT NULL THEN
                UPDATE sales_commissions 
                SET tier_level = applicable_tier, commission_rate = new_commission_rate
                WHERE id = NEW.id;
            END IF;
        END IF;
    END;
    ";
    
    // Drop existing triggers if they exist
    try {
        $pdo->exec("DROP TRIGGER IF EXISTS update_commission_tier_after_insert");
        $pdo->exec("DROP TRIGGER IF EXISTS update_commission_tier_after_update");
    } catch (Exception $e) {
        // Ignore errors if triggers don't exist
    }
    
    // Create the triggers
    $pdo->exec($trigger1);
    echo "Commission tier update trigger (INSERT) created successfully\n";
    
    $pdo->exec($trigger2);
    echo "Commission tier update trigger (UPDATE) created successfully\n";
    
    // Create trigger for automatic commission calculation
    $commissionTrigger = "
    CREATE TRIGGER calculate_commission_before_insert
    BEFORE INSERT ON sales_commissions
    FOR EACH ROW
    BEGIN
        -- Calculate commission amount if not provided
        IF NEW.commission_amount = 0 OR NEW.commission_amount IS NULL THEN
            SET NEW.commission_amount = NEW.total_sales * NEW.commission_rate;
        END IF;
    END;
    ";
    
    try {
        $pdo->exec("DROP TRIGGER IF EXISTS calculate_commission_before_insert");
    } catch (Exception $e) {
        // Ignore errors if trigger doesn't exist
    }
    
    $pdo->exec($commissionTrigger);
    echo "Commission calculation trigger created successfully\n";
    
    // Create trigger for automatic financial report cache invalidation
    $cacheTrigger = "
    CREATE TRIGGER invalidate_financial_cache_after_ledger_change
    AFTER INSERT ON general_ledger
    FOR EACH ROW
    BEGIN
        -- This would typically notify an external cache system to invalidate financial reports
        -- For now, we'll just log the change
        INSERT INTO system_logs (log_level, message, created_at) 
        VALUES ('INFO', CONCAT('General ledger changed, account_id: ', NEW.account_id), NOW())
        ON DUPLICATE KEY UPDATE message = VALUES(message), created_at = VALUES(created_at);
    END;
    ";
    
    try {
        $pdo->exec("DROP TRIGGER IF EXISTS invalidate_financial_cache_after_ledger_change");
    } catch (Exception $e) {
        // Ignore errors if trigger doesn't exist
    }
    
    // Create system logs table if it doesn't exist
    $logTable = "
    CREATE TABLE IF NOT EXISTS system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        log_level ENUM('INFO', 'WARNING', 'ERROR') NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_log_level (log_level),
        INDEX idx_log_date (created_at)
    );
    ";
    $pdo->exec($logTable);
    
    $pdo->exec($cacheTrigger);
    echo "Financial cache invalidation trigger created successfully\n";
    
    echo "All commission triggers and related database objects created successfully!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

?>