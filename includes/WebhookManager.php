<?php
/**
 * Webhook Manager
 * Handles sending outgoing webhooks to external systems
 */

class WebhookManager {
    private $pdo;
    private $maxRetries = 3;
    private $retryDelay = 60; // seconds
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Send a webhook to a specific endpoint
     */
    public function sendWebhook($configId, $eventType, $payload) {
        try {
            // Get webhook configuration
            $config = $this->getWebhookConfig($configId);
            
            if (!$config || !$config['is_active']) {
                return ['success' => false, 'error' => 'Webhook configuration not found or inactive'];
            }
            
            // Create webhook event record
            $eventId = $this->createWebhookEvent($configId, $eventType, $payload);
            
            // Send the webhook
            $result = $this->sendWebhookRequest($config, $eventType, $payload, $eventId);
            
            // Update event status
            $this->updateWebhookEventStatus($eventId, $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Webhook sending failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send webhook with retry logic
     */
    public function sendWebhookWithRetry($configId, $eventType, $payload) {
        $attempt = 0;
        $result = null;
        
        do {
            $result = $this->sendWebhook($configId, $eventType, $payload);
            
            if ($result['success']) {
                return $result;
            }
            
            $attempt++;
            
            if ($attempt < $this->maxRetries) {
                // Wait before retrying
                sleep($this->retryDelay);
            }
            
        } while ($attempt < $this->maxRetries);
        
        return $result;
    }
    
    /**
     * Send webhook to all subscribers for an event type
     */
    public function sendWebhookToSubscribers($eventType, $payload) {
        try {
            // Get all active webhook configurations
            $configs = $this->getActiveWebhookConfigs();
            
            $results = [];
            
            foreach ($configs as $config) {
                // Check if this config subscribes to this event type
                if ($this->subscribesToEvent($config['id'], $eventType)) {
                    $result = $this->sendWebhookWithRetry($config['id'], $eventType, $payload);
                    $results[$config['source']] = $result;
                }
            }
            
            return ['success' => true, 'results' => $results];
            
        } catch (Exception $e) {
            error_log("Sending webhooks to subscribers failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process webhook events that are pending or failed
     */
    public function processPendingEvents() {
        try {
            // Get pending or failed events that are due for retry
            $stmt = $this->pdo->prepare("
                SELECT * FROM webhook_events 
                WHERE delivery_status IN ('pending', 'failed')
                AND (next_delivery_attempt IS NULL OR next_delivery_attempt <= NOW())
                ORDER BY created_at ASC
                LIMIT 50
            ");
            $stmt->execute();
            $events = $stmt->fetchAll();
            
            $results = [];
            
            foreach ($events as $event) {
                // Get webhook configuration
                $config = $this->getWebhookConfig($event['webhook_config_id']);
                
                if (!$config || !$config['is_active']) {
                    // Mark as failed if config is inactive
                    $this->updateWebhookEventStatus($event['id'], [
                        'success' => false,
                        'error' => 'Webhook configuration inactive'
                    ]);
                    continue;
                }
                
                // Send the webhook
                $result = $this->sendWebhookRequest($config, $event['event_type'], json_decode($event['payload'], true), $event['id']);
                
                // Update event status
                $this->updateWebhookEventStatus($event['id'], $result);
                
                $results[$event['id']] = $result;
            }
            
            return ['success' => true, 'processed' => count($results), 'results' => $results];
            
        } catch (Exception $e) {
            error_log("Processing pending webhook events failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get webhook configuration by ID
     */
    private function getWebhookConfig($configId) {
        $stmt = $this->pdo->prepare("SELECT * FROM webhook_configs WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$configId]);
        return $stmt->fetch();
    }
    
    /**
     * Get all active webhook configurations
     */
    private function getActiveWebhookConfigs() {
        $stmt = $this->pdo->prepare("SELECT * FROM webhook_configs WHERE is_active = TRUE");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Check if a webhook configuration subscribes to an event type
     */
    private function subscribesToEvent($configId, $eventType) {
        // For now, we'll assume all active configs subscribe to all events
        // In a more complex system, you might have specific subscription rules
        return true;
    }
    
    /**
     * Create a webhook event record
     */
    private function createWebhookEvent($configId, $eventType, $payload) {
        $stmt = $this->pdo->prepare("
            INSERT INTO webhook_events (webhook_config_id, event_type, payload, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$configId, $eventType, json_encode($payload)]);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Send the actual webhook request
     */
    private function sendWebhookRequest($config, $eventType, $payload, $eventId) {
        try {
            // Prepare the payload
            $payload['event_type'] = $eventType;
            $payload['event_id'] = $eventId;
            $payload['timestamp'] = time();
            
            // Convert payload to JSON
            $jsonPayload = json_encode($payload);
            
            // Initialize cURL
            $ch = curl_init();
            
            // Set cURL options
            curl_setopt($ch, CURLOPT_URL, $config['webhook_url']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonPayload),
                'User-Agent: Marketplace-Webhook-Client/1.0'
            ]);
            
            // Add signature for HMAC verification if configured
            if ($config['verification_method'] === 'hmac' && !empty($config['secret_key'])) {
                $signature = hash_hmac('sha256', $jsonPayload, $config['secret_key']);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(curl_getinfo($ch, CURLINFO_HTTP_CODE), [
                    'X-Signature: ' . $signature
                ]));
            }
            
            // Execute the request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            // Check if request was successful
            if ($error) {
                return ['success' => false, 'error' => 'cURL error: ' . $error];
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'http_code' => $httpCode, 'response' => $response];
            } else {
                return ['success' => false, 'http_code' => $httpCode, 'response' => $response, 'error' => 'HTTP error'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update webhook event status
     */
    private function updateWebhookEventStatus($eventId, $result) {
        if ($result['success']) {
            // Success - update status to delivered
            $stmt = $this->pdo->prepare("
                UPDATE webhook_events 
                SET delivery_status = 'delivered',
                    delivery_attempts = delivery_attempts + 1,
                    last_delivery_attempt = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$eventId]);
        } else {
            // Failed - increment attempts and schedule retry
            $stmt = $this->pdo->prepare("
                UPDATE webhook_events 
                SET delivery_status = 'failed',
                    delivery_attempts = delivery_attempts + 1,
                    last_delivery_attempt = NOW(),
                    next_delivery_attempt = DATE_ADD(NOW(), INTERVAL (delivery_attempts * ?) MINUTE),
                    failure_reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$this->retryDelay, $result['error'] ?? 'Unknown error', $eventId]);
        }
    }
    
    /**
     * Register a new webhook configuration
     */
    public function registerWebhook($source, $webhookUrl, $secretKey = null, $verificationMethod = 'none', $configData = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO webhook_configs (source, webhook_url, secret_key, verification_method, config_data, is_active)
                VALUES (?, ?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([
                $source,
                $webhookUrl,
                $secretKey,
                $verificationMethod,
                $configData ? json_encode($configData) : null
            ]);
            
            return ['success' => true, 'config_id' => $this->pdo->lastInsertId()];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update an existing webhook configuration
     */
    public function updateWebhook($configId, $webhookUrl = null, $secretKey = null, $verificationMethod = null, $isActive = null, $configData = null) {
        try {
            $updates = [];
            $params = [];
            
            if ($webhookUrl !== null) {
                $updates[] = "webhook_url = ?";
                $params[] = $webhookUrl;
            }
            
            if ($secretKey !== null) {
                $updates[] = "secret_key = ?";
                $params[] = $secretKey;
            }
            
            if ($verificationMethod !== null) {
                $updates[] = "verification_method = ?";
                $params[] = $verificationMethod;
            }
            
            if ($isActive !== null) {
                $updates[] = "is_active = ?";
                $params[] = $isActive ? 1 : 0;
            }
            
            if ($configData !== null) {
                $updates[] = "config_data = ?";
                $params[] = json_encode($configData);
            }
            
            if (empty($updates)) {
                return ['success' => true, 'message' => 'No updates provided'];
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $configId;
            
            $sql = "UPDATE webhook_configs SET " . implode(', ', $updates) . " WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'affected_rows' => $stmt->rowCount()];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete a webhook configuration
     */
    public function deleteWebhook($configId) {
        try {
            $this->pdo->beginTransaction();
            
            // Delete related events
            $stmt = $this->pdo->prepare("DELETE FROM webhook_events WHERE webhook_config_id = ?");
            $stmt->execute([$configId]);
            
            // Delete subscriptions
            $stmt = $this->pdo->prepare("DELETE FROM webhook_subscriptions WHERE webhook_config_id = ?");
            $stmt->execute([$configId]);
            
            // Delete config
            $stmt = $this->pdo->prepare("DELETE FROM webhook_configs WHERE id = ?");
            $stmt->execute([$configId]);
            
            $this->pdo->commit();
            
            return ['success' => true, 'deleted_rows' => $stmt->rowCount()];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get webhook event statistics
     */
    public function getWebhookStats() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_events,
                    COUNT(CASE WHEN delivery_status = 'delivered' THEN 1 END) as delivered_events,
                    COUNT(CASE WHEN delivery_status = 'failed' THEN 1 END) as failed_events,
                    COUNT(CASE WHEN delivery_status = 'pending' THEN 1 END) as pending_events,
                    AVG(delivery_attempts) as avg_attempts
                FROM webhook_events
            ");
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Getting webhook stats failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get recent webhook events
     */
    public function getRecentEvents($limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT we.*, wc.source 
                FROM webhook_events we
                JOIN webhook_configs wc ON we.webhook_config_id = wc.id
                ORDER BY we.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Getting recent webhook events failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retry a failed webhook event
     */
    public function retryEvent($eventId) {
        try {
            // Get the event
            $stmt = $this->pdo->prepare("
                SELECT we.*, wc.* 
                FROM webhook_events we
                JOIN webhook_configs wc ON we.webhook_config_id = wc.id
                WHERE we.id = ?
            ");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch();
            
            if (!$event) {
                return ['success' => false, 'error' => 'Event not found'];
            }
            
            // Send the webhook
            $result = $this->sendWebhookRequest($event, $event['event_type'], json_decode($event['payload'], true), $eventId);
            
            // Update event status
            $this->updateWebhookEventStatus($eventId, $result);
            
            return $result;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>