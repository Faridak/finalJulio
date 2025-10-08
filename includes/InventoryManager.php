<?php
/**
 * Enhanced Inventory Management System for VentDepot
 * Handles bin locations, cycle counting, automated reorders, and vendor communication
 */

require_once 'security.php';

class InventoryManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // =====================================================
    // BIN LOCATION MANAGEMENT
    // =====================================================
    
    /**
     * Get complete warehouse structure with zones, racks, and bins
     */
    public function getWarehouseStructure($locationId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                wz.id as zone_id, wz.zone_code, wz.zone_name, wz.zone_type,
                sr.id as rack_id, sr.rack_code, sr.rack_name, sr.rack_type,
                ib.id as bin_id, ib.bin_code, ib.bin_address, ib.occupancy_status,
                ib.current_product_id, ib.current_quantity, ib.utilization_percentage,
                p.name as product_name, CONCAT('PROD-', p.id) as product_sku
            FROM warehouse_zones wz
            LEFT JOIN storage_racks sr ON wz.id = sr.zone_id
            LEFT JOIN inventory_bins ib ON sr.id = ib.rack_id
            LEFT JOIN products p ON ib.current_product_id = p.id
            WHERE wz.location_id = ? AND wz.status = 'active'
            ORDER BY wz.zone_code, sr.rack_code, ib.level_number, ib.position_number
        ");
        $stmt->execute([$locationId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Find optimal bin for product placement
     */
    public function findOptimalBin($productId, $quantity, $locationId, $zoneType = 'storage') {
        $stmt = $this->pdo->prepare("
            SELECT ib.*, sr.rack_code, wz.zone_code, wz.zone_name,
                   (ib.weight_capacity_kg - (ib.current_quantity * 0.1)) as remaining_capacity
            FROM inventory_bins ib
            JOIN storage_racks sr ON ib.rack_id = sr.id
            JOIN warehouse_zones wz ON sr.zone_id = wz.id
            WHERE wz.location_id = ? 
            AND wz.zone_type = ?
            AND ib.status = 'active'
            AND (ib.occupancy_status IN ('empty', 'partial') OR ib.current_product_id = ?)
            ORDER BY 
                CASE WHEN ib.current_product_id = ? THEN 1 ELSE 2 END,
                ib.utilization_percentage ASC,
                ib.bin_address ASC
            LIMIT 5
        ");
        $stmt->execute([$locationId, $zoneType, $productId, $productId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Assign product to specific bin
     */
    public function assignProductToBin($productId, $binId, $quantity, $assignmentType = 'primary', $userId = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Check if assignment already exists
            $stmt = $this->pdo->prepare("
                SELECT id, quantity FROM product_bin_assignments 
                WHERE product_id = ? AND bin_id = ? AND assignment_type = ?
            ");
            $stmt->execute([$productId, $binId, $assignmentType]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing assignment
                $newQuantity = $existing['quantity'] + $quantity;
                $stmt = $this->pdo->prepare("
                    UPDATE product_bin_assignments 
                    SET quantity = ?, last_movement_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newQuantity, $existing['id']]);
            } else {
                // Create new assignment
                $stmt = $this->pdo->prepare("
                    INSERT INTO product_bin_assignments 
                    (product_id, bin_id, assignment_type, quantity, assigned_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$productId, $binId, $assignmentType, $quantity, $userId]);
            }
            
            // Update bin status
            $this->updateBinStatus($binId);
            
            // Log movement
            $this->logInventoryMovement($productId, $binId, 'in', $quantity, 'bin_assignment', null, $userId);
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Product assigned to bin successfully'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update bin occupancy status and utilization
     */
    private function updateBinStatus($binId) {
        $stmt = $this->pdo->prepare("
            UPDATE inventory_bins 
            SET 
                current_quantity = (
                    SELECT COALESCE(SUM(quantity), 0) 
                    FROM product_bin_assignments 
                    WHERE bin_id = ? AND status = 'active'
                ),
                current_product_id = (
                    SELECT product_id 
                    FROM product_bin_assignments 
                    WHERE bin_id = ? AND status = 'active' 
                    ORDER BY quantity DESC LIMIT 1
                ),
                occupancy_status = CASE 
                    WHEN (SELECT COALESCE(SUM(quantity), 0) FROM product_bin_assignments WHERE bin_id = ? AND status = 'active') = 0 THEN 'empty'
                    ELSE 'partial'
                END,
                utilization_percentage = CASE 
                    WHEN volume_liters > 0 THEN 
                        LEAST(100, ((SELECT COALESCE(SUM(quantity), 0) FROM product_bin_assignments WHERE bin_id = ? AND status = 'active') / volume_liters) * 100)
                    ELSE 50.0
                END
            WHERE id = ?
        ");
        $stmt->execute([$binId, $binId, $binId, $binId, $binId]);
    }
    
    /**
     * Generate bin barcode/QR code
     */
    public function generateBinCode($binId, $type = 'barcode') {
        $stmt = $this->pdo->prepare("
            SELECT ib.bin_address, sr.rack_code, wz.zone_code, il.location_code
            FROM inventory_bins ib
            JOIN storage_racks sr ON ib.rack_id = sr.id
            JOIN warehouse_zones wz ON sr.zone_id = wz.id
            JOIN inventory_locations il ON wz.location_id = il.id
            WHERE ib.id = ?
        ");
        $stmt->execute([$binId]);
        $bin = $stmt->fetch();
        
        if (!$bin) return null;
        
        $code = $bin['location_code'] . '-' . $bin['bin_address'];
        
        if ($type === 'qr') {
            $qrData = json_encode([
                'type' => 'bin_location',
                'bin_id' => $binId,
                'address' => $bin['bin_address'],
                'location' => $bin['location_code'],
                'timestamp' => time()
            ]);
            return $qrData;
        }
        
        return $code;
    }
    
    // =====================================================
    // CYCLE COUNTING
    // =====================================================
    
    /**
     * Create cycle count plan
     */
    public function createCycleCountPlan($data, $userId) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO cycle_count_plans 
                (plan_name, location_id, plan_type, frequency_type, frequency_value,
                 start_date, assigned_to, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)
            ");
            $stmt->execute([
                $data['plan_name'],
                $data['location_id'],
                $data['plan_type'],
                $data['frequency_type'],
                $data['frequency_value'],
                $data['start_date'],
                $data['assigned_to'],
                $userId
            ]);
            
            $planId = $this->pdo->lastInsertId();
            
            // Schedule first session
            $this->scheduleNextCycleCount($planId);
            
            return ['success' => true, 'plan_id' => $planId];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Schedule next cycle count session
     */
    private function scheduleNextCycleCount($planId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM cycle_count_plans WHERE id = ?
        ");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        
        if (!$plan) return false;
        
        $nextDate = $this->calculateNextCountDate($plan);
        
        // Create session
        $stmt = $this->pdo->prepare("
            INSERT INTO cycle_count_sessions 
            (plan_id, session_name, scheduled_date, counter_id, status)
            VALUES (?, ?, ?, ?, 'scheduled')
        ");
        $sessionName = $plan['plan_name'] . ' - ' . $nextDate;
        $stmt->execute([$planId, $sessionName, $nextDate, $plan['assigned_to']]);
        
        // Update plan next scheduled date
        $stmt = $this->pdo->prepare("
            UPDATE cycle_count_plans 
            SET next_scheduled_date = ? 
            WHERE id = ?
        ");
        $stmt->execute([$nextDate, $planId]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Record cycle count
     */
    public function recordCycleCount($sessionId, $productId, $binId, $countedQuantity, $userId) {
        try {
            // Get expected quantity
            $stmt = $this->pdo->prepare("
                SELECT quantity FROM product_bin_assignments 
                WHERE product_id = ? AND bin_id = ? AND status = 'active'
            ");
            $stmt->execute([$productId, $binId]);
            $expectedQuantity = $stmt->fetchColumn() ?: 0;
            
            $variance = $countedQuantity - $expectedQuantity;
            $variancePercentage = $expectedQuantity > 0 ? ($variance / $expectedQuantity) * 100 : 0;
            
            // Insert count detail
            $stmt = $this->pdo->prepare("
                INSERT INTO cycle_count_details 
                (session_id, product_id, bin_id, expected_quantity, counted_quantity, 
                 variance_quantity, variance_percentage, counted_by, counted_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $status = abs($variance) > 0 ? 'variance' : 'counted';
            $stmt->execute([
                $sessionId, $productId, $binId, $expectedQuantity, 
                $countedQuantity, $variance, $variancePercentage, $userId, $status
            ]);
            
            return ['success' => true, 'variance' => $variance];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // =====================================================
    // AUTOMATED REORDER MANAGEMENT
    // =====================================================
    
    /**
     * Create reorder rule
     */
    public function createReorderRule($data, $userId) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO reorder_rules 
                (product_id, location_id, supplier_id, reorder_point, reorder_quantity,
                 max_stock_level, safety_stock, lead_time_days, auto_generate_po, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['product_id'], $data['location_id'], $data['supplier_id'],
                $data['reorder_point'], $data['reorder_quantity'], $data['max_stock_level'],
                $data['safety_stock'], $data['lead_time_days'], 
                $data['auto_generate_po'] ?? false, $userId
            ]);
            
            return ['success' => true, 'rule_id' => $this->pdo->lastInsertId()];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Check for reorder triggers and generate suggestions
     */
    public function checkReorderTriggers() {
        $stmt = $this->pdo->prepare("
            SELECT rr.*, p.name as product_name, s.company_name as supplier_name,
                   il.location_name, pi.quantity_on_hand, pi.quantity_reserved
            FROM reorder_rules rr
            JOIN products p ON rr.product_id = p.id
            JOIN suppliers s ON rr.supplier_id = s.id
            JOIN inventory_locations il ON rr.location_id = il.id
            LEFT JOIN product_inventory pi ON rr.product_id = pi.product_id 
                AND rr.location_id = pi.location_id
            WHERE rr.status = 'active'
            AND COALESCE(pi.quantity_on_hand, 0) <= rr.reorder_point
            AND NOT EXISTS (
                SELECT 1 FROM reorder_suggestions rs 
                WHERE rs.rule_id = rr.id 
                AND rs.status IN ('pending', 'approved', 'ordered')
            )
        ");
        $stmt->execute();
        $triggers = $stmt->fetchAll();
        
        $suggestions = [];
        foreach ($triggers as $trigger) {
            $suggestion = $this->createReorderSuggestion($trigger);
            if ($suggestion['success']) {
                $suggestions[] = $suggestion;
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get reorder suggestions with filters
     */
    public function getReorderSuggestions($filters = []) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (isset($filters['status'])) {
            $whereClause .= " AND rs.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['urgency'])) {
            $whereClause .= " AND rs.urgency_level = ?";
            $params[] = $filters['urgency'];
        }
        
        if (isset($filters['location_id'])) {
            $whereClause .= " AND rs.location_id = ?";
            $params[] = $filters['location_id'];
        }
        
        $stmt = $this->pdo->prepare("
            SELECT rs.*, p.name as product_name, CONCAT('PROD-', p.id) as product_sku,
                   s.company_name as supplier_name, s.email as supplier_email,
                   s.phone as supplier_phone, il.location_name,
                   vc.first_name, vc.last_name, vc.email as contact_email, vc.phone as contact_phone
            FROM reorder_suggestions rs
            JOIN products p ON rs.product_id = p.id
            JOIN suppliers s ON rs.supplier_id = s.id
            JOIN inventory_locations il ON rs.location_id = il.id
            LEFT JOIN vendor_contacts vc ON s.id = vc.supplier_id AND vc.is_primary = 1
            $whereClause
            ORDER BY 
                CASE rs.urgency_level 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    ELSE 4 
                END,
                rs.created_at ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Create reorder suggestion
     */
    private function createReorderSuggestion($rule) {
        try {
            $currentStock = $rule['quantity_on_hand'] ?: 0;
            $suggestedQuantity = $rule['reorder_quantity'];
            
            // Calculate urgency level
            $stockRatio = $currentStock / max($rule['reorder_point'], 1);
            $urgencyLevel = 'medium';
            if ($stockRatio <= 0.25) $urgencyLevel = 'critical';
            elseif ($stockRatio <= 0.5) $urgencyLevel = 'high';
            elseif ($stockRatio <= 0.75) $urgencyLevel = 'medium';
            else $urgencyLevel = 'low';
            
            // Calculate dates
            $orderDate = date('Y-m-d');
            $deliveryDate = date('Y-m-d', strtotime("+{$rule['lead_time_days']} days"));
            
            // Estimate cost
            $estimatedCost = $suggestedQuantity * ($rule['last_purchase_cost'] ?: 0);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO reorder_suggestions 
                (rule_id, product_id, supplier_id, location_id, current_stock,
                 reorder_point, suggested_quantity, urgency_level, estimated_cost,
                 suggested_order_date, expected_delivery_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $rule['id'], $rule['product_id'], $rule['supplier_id'], $rule['location_id'],
                $currentStock, $rule['reorder_point'], $suggestedQuantity, $urgencyLevel,
                $estimatedCost, $orderDate, $deliveryDate
            ]);
            
            return ['success' => true, 'suggestion_id' => $this->pdo->lastInsertId()];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // =====================================================
    // VENDOR COMMUNICATION
    // =====================================================
    
    /**
     * Add vendor contact
     */
    public function addVendorContact($supplierId, $contactData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO vendor_contacts 
                (supplier_id, contact_type, first_name, last_name, title, email, phone, 
                 preferred_contact_method, is_primary, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $supplierId, $contactData['contact_type'], $contactData['first_name'],
                $contactData['last_name'], $contactData['title'], $contactData['email'],
                $contactData['phone'], $contactData['preferred_contact_method'],
                $contactData['is_primary'] ?? false, 'active'
            ]);
            
            return ['success' => true, 'contact_id' => $this->pdo->lastInsertId()];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Log vendor communication
     */
    public function logVendorCommunication($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO vendor_communication_log 
                (supplier_id, contact_id, reorder_suggestion_id, communication_type,
                 direction, subject, message_content, initiated_by, status, outcome,
                 is_automated, completed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $data['supplier_id'], $data['contact_id'], $data['reorder_suggestion_id'],
                $data['communication_type'], $data['direction'], $data['subject'],
                $data['message_content'], $data['initiated_by'], $data['status'],
                $data['outcome'], $data['is_automated'] ?? false
            ]);
            
            return ['success' => true, 'log_id' => $this->pdo->lastInsertId()];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // =====================================================
    // INVENTORY MOVEMENTS & TRACKING
    // =====================================================
    
    /**
     * Log inventory movement
     */
    public function logInventoryMovement($productId, $locationId, $movementType, $quantity, 
                                       $referenceType = null, $referenceId = null, $userId = null, 
                                       $supplierId = null, $reason = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory_movements 
                (product_id, location_id, movement_type, quantity, reference_type,
                 reference_id, supplier_id, reason, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $productId, $locationId, $movementType, $quantity, $referenceType,
                $referenceId, $supplierId, $reason, $userId
            ]);
            
            return ['success' => true, 'movement_id' => $this->pdo->lastInsertId()];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get inventory dashboard data
     */
    public function getInventoryDashboard($locationId = null) {
        $whereClause = $locationId ? "WHERE il.id = ?" : "";
        $params = $locationId ? [$locationId] : [];
        
        // Low stock items
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM product_inventory pi
            JOIN inventory_locations il ON pi.location_id = il.id
            WHERE pi.quantity_on_hand <= pi.reorder_point
            $whereClause
        ");
        $stmt->execute($params);
        $lowStockCount = $stmt->fetchColumn();
        
        // Total inventory value
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(pi.quantity_on_hand * pi.average_cost), 0)
            FROM product_inventory pi
            JOIN inventory_locations il ON pi.location_id = il.id
            $whereClause
        ");
        $stmt->execute($params);
        $totalValue = $stmt->fetchColumn();
        
        return [
            'low_stock_count' => $lowStockCount,
            'total_inventory_value' => $totalValue
        ];
    }
    
    /**
     * Calculate next count date based on frequency
     */
    private function calculateNextCountDate($plan) {
        $startDate = new DateTime($plan['start_date']);
        $frequency = $plan['frequency_value'];
        
        switch ($plan['frequency_type']) {
            case 'daily':
                $startDate->add(new DateInterval("P{$frequency}D"));
                break;
            case 'weekly':
                $weeks = $frequency * 7;
                $startDate->add(new DateInterval("P{$weeks}D"));
                break;
            case 'monthly':
                $startDate->add(new DateInterval("P{$frequency}M"));
                break;
            case 'quarterly':
                $months = $frequency * 3;
                $startDate->add(new DateInterval("P{$months}M"));
                break;
        }
        
        return $startDate->format('Y-m-d');
    }
}
?>