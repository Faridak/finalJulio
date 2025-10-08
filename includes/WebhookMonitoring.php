<?php
/**
 * Webhook Monitoring System
 * Tracks webhook performance, detects issues, and sends alerts
 */

class WebhookMonitoring {
    private $pdo;
    private $notificationSystem;
    private $alertThreshold = 5; // Number of failures before alerting
    private $timeWindow = 3600; // Time window in seconds (1 hour)
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->notificationSystem = new NotificationSystem($pdo);
    }
    
    /**
     * Check for webhook issues and send alerts
     */
    public function checkForIssues() {
        try {
            $issues = [];
            
            // Check for high failure rates
            $failureIssues = $this->checkFailureRates();
            if ($failureIssues) {
                $issues = array_merge($issues, $failureIssues);
            }
            
            // Check for delayed deliveries
            $delayIssues = $this->checkDeliveryDelays();
            if ($delayIssues) {
                $issues = array_merge($issues, $delayIssues);
            }
            
            // Check for unprocessed events
            $unprocessedIssues = $this->checkUnprocessedEvents();
            if ($unprocessedIssues) {
                $issues = array_merge($issues, $unprocessedIssues);
            }
            
            // Send alerts for issues
            if (!empty($issues)) {
                $this->sendAlerts($issues);
            }
            
            return ['success' => true, 'issues_found' => count($issues), 'issues' => $issues];
            
        } catch (Exception $e) {
            error_log("Webhook monitoring check failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Check for high webhook failure rates
     */
    private function checkFailureRates() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    wc.source,
                    COUNT(CASE WHEN we.delivery_status = 'delivered' THEN 1 END) as successful,
                    COUNT(CASE WHEN we.delivery_status = 'failed' THEN 1 END) as failed,
                    COUNT(*) as total
                FROM webhook_events we
                JOIN webhook_configs wc ON we.webhook_config_id = wc.id
                WHERE we.created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
                GROUP BY wc.source
                HAVING failed > 0 AND (failed / total) > 0.1  -- More than 10% failure rate
            ");
            $stmt->execute([$this->timeWindow]);
            $results = $stmt->fetchAll();
            
            $issues = [];
            foreach ($results as $result) {
                $failureRate = $result['failed'] / $result['total'];
                $issues[] = [
                    'type' => 'high_failure_rate',
                    'source' => $result['source'],
                    'successful' => $result['successful'],
                    'failed' => $result['failed'],
                    'total' => $result['total'],
                    'failure_rate' => round($failureRate * 100, 2) . '%'
                ];
            }
            
            return $issues;
            
        } catch (Exception $e) {
            error_log("Checking failure rates failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for delayed webhook deliveries
     */
    private function checkDeliveryDelays() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    wc.source,
                    COUNT(*) as delayed_count,
                    AVG(TIMESTAMPDIFF(SECOND, we.created_at, NOW())) as avg_delay_seconds
                FROM webhook_events we
                JOIN webhook_configs wc ON we.webhook_config_id = wc.id
                WHERE we.delivery_status = 'pending'
                AND we.created_at <= DATE_SUB(NOW(), INTERVAL 300 SECOND)  -- More than 5 minutes old
                GROUP BY wc.source
                HAVING COUNT(*) > 5  -- More than 5 delayed events
            ");
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            $issues = [];
            foreach ($results as $result) {
                $issues[] = [
                    'type' => 'delivery_delays',
                    'source' => $result['source'],
                    'delayed_count' => $result['delayed_count'],
                    'avg_delay_minutes' => round($result['avg_delay_seconds'] / 60, 2)
                ];
            }
            
            return $issues;
            
        } catch (Exception $e) {
            error_log("Checking delivery delays failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for unprocessed webhook events
     */
    private function checkUnprocessedEvents() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    wc.source,
                    COUNT(*) as unprocessed_count
                FROM webhook_events we
                JOIN webhook_configs wc ON we.webhook_config_id = wc.id
                WHERE we.delivery_status = 'pending'
                AND we.next_delivery_attempt IS NULL
                GROUP BY wc.source
                HAVING COUNT(*) > 10  -- More than 10 unprocessed events
            ");
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            $issues = [];
            foreach ($results as $result) {
                $issues[] = [
                    'type' => 'unprocessed_events',
                    'source' => $result['source'],
                    'unprocessed_count' => $result['unprocessed_count']
                ];
            }
            
            return $issues;
            
        } catch (Exception $e) {
            error_log("Checking unprocessed events failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send alerts for webhook issues
     */
    private function sendAlerts($issues) {
        try {
            // Get admin users to notify
            $stmt = $this->pdo->prepare("
                SELECT id, email FROM users 
                WHERE role IN ('admin', 'super_admin') 
                AND status = 'active'
            ");
            $stmt->execute();
            $admins = $stmt->fetchAll();
            
            foreach ($issues as $issue) {
                $message = $this->formatAlertMessage($issue);
                
                foreach ($admins as $admin) {
                    // Create notification for admin
                    $this->notificationSystem->createNotification(
                        $admin['id'],
                        'system',
                        'Webhook Issue Detected',
                        $message,
                        '/admin/webhooks.php',
                        'View Webhooks',
                        ['issue' => $issue],
                        true
                    );
                }
                
                // Log the alert
                error_log("Webhook Alert: " . $message);
            }
            
        } catch (Exception $e) {
            error_log("Sending webhook alerts failed: " . $e->getMessage());
        }
    }
    
    /**
     * Format alert message based on issue type
     */
    private function formatAlertMessage($issue) {
        switch ($issue['type']) {
            case 'high_failure_rate':
                return "High webhook failure rate detected for {$issue['source']}: {$issue['failure_rate']} ({$issue['failed']}/{$issue['total']} failed)";
                
            case 'delivery_delays':
                return "Webhook delivery delays for {$issue['source']}: {$issue['delayed_count']} events delayed, average delay {$issue['avg_delay_minutes']} minutes";
                
            case 'unprocessed_events':
                return "Unprocessed webhook events for {$issue['source']}: {$issue['unprocessed_count']} events not being processed";
                
            default:
                return "Webhook issue detected for {$issue['source']}: " . json_encode($issue);
        }
    }
    
    /**
     * Get webhook performance metrics
     */
    public function getPerformanceMetrics($timeRange = '24h') {
        try {
            // Calculate time range
            $interval = '24 HOUR';
            switch ($timeRange) {
                case '1h':
                    $interval = '1 HOUR';
                    break;
                case '6h':
                    $interval = '6 HOUR';
                    break;
                case '12h':
                    $interval = '12 HOUR';
                    break;
                case '7d':
                    $interval = '7 DAY';
                    break;
                case '30d':
                    $interval = '30 DAY';
                    break;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(we.created_at) as date,
                    HOUR(we.created_at) as hour,
                    wc.source,
                    COUNT(CASE WHEN we.delivery_status = 'delivered' THEN 1 END) as successful,
                    COUNT(CASE WHEN we.delivery_status = 'failed' THEN 1 END) as failed,
                    COUNT(*) as total,
                    AVG(TIMESTAMPDIFF(SECOND, we.created_at, we.last_delivery_attempt)) as avg_delivery_time_seconds
                FROM webhook_events we
                JOIN webhook_configs wc ON we.webhook_config_id = wc.id
                WHERE we.created_at >= DATE_SUB(NOW(), INTERVAL $interval)
                GROUP BY DATE(we.created_at), HOUR(we.created_at), wc.source
                ORDER BY date DESC, hour DESC, wc.source
            ");
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            // Process results for charting
            $metrics = [
                'timeline' => [],
                'sources' => [],
                'summary' => [
                    'total_events' => 0,
                    'successful' => 0,
                    'failed' => 0,
                    'success_rate' => 0,
                    'avg_delivery_time' => 0
                ]
            ];
            
            foreach ($results as $result) {
                $dateKey = $result['date'] . ' ' . str_pad($result['hour'], 2, '0', STR_PAD_LEFT) . ':00';
                
                if (!isset($metrics['timeline'][$dateKey])) {
                    $metrics['timeline'][$dateKey] = [
                        'successful' => 0,
                        'failed' => 0,
                        'total' => 0,
                        'avg_delivery_time' => 0,
                        'sources' => []
                    ];
                }
                
                $metrics['timeline'][$dateKey]['successful'] += $result['successful'];
                $metrics['timeline'][$dateKey]['failed'] += $result['failed'];
                $metrics['timeline'][$dateKey]['total'] += $result['total'];
                $metrics['timeline'][$dateKey]['avg_delivery_time'] += $result['avg_delivery_time_seconds'];
                
                if (!isset($metrics['timeline'][$dateKey]['sources'][$result['source']])) {
                    $metrics['timeline'][$dateKey]['sources'][$result['source']] = [
                        'successful' => 0,
                        'failed' => 0,
                        'total' => 0
                    ];
                }
                
                $metrics['timeline'][$dateKey]['sources'][$result['source']]['successful'] += $result['successful'];
                $metrics['timeline'][$dateKey]['sources'][$result['source']]['failed'] += $result['failed'];
                $metrics['timeline'][$dateKey]['sources'][$result['source']]['total'] += $result['total'];
                
                if (!in_array($result['source'], $metrics['sources'])) {
                    $metrics['sources'][] = $result['source'];
                }
                
                $metrics['summary']['total_events'] += $result['total'];
                $metrics['summary']['successful'] += $result['successful'];
                $metrics['summary']['failed'] += $result['failed'];
                $metrics['summary']['avg_delivery_time'] += $result['avg_delivery_time_seconds'];
            }
            
            // Calculate summary success rate
            if ($metrics['summary']['total_events'] > 0) {
                $metrics['summary']['success_rate'] = round(
                    ($metrics['summary']['successful'] / $metrics['summary']['total_events']) * 100, 2
                );
                
                $metrics['summary']['avg_delivery_time'] = round(
                    $metrics['summary']['avg_delivery_time'] / count($results), 2
                );
            }
            
            return $metrics;
            
        } catch (Exception $e) {
            error_log("Getting performance metrics failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get webhook health status
     */
    public function getHealthStatus() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    wc.source,
                    wc.is_active,
                    COUNT(CASE WHEN we.delivery_status = 'delivered' THEN 1 END) as successful_24h,
                    COUNT(CASE WHEN we.delivery_status = 'failed' THEN 1 END) as failed_24h,
                    COUNT(CASE WHEN we.delivery_status = 'pending' THEN 1 END) as pending,
                    MAX(we.created_at) as last_event
                FROM webhook_configs wc
                LEFT JOIN webhook_events we ON wc.id = we.webhook_config_id 
                    AND we.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY wc.id, wc.source, wc.is_active
            ");
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            $health = [];
            foreach ($results as $result) {
                $total24h = $result['successful_24h'] + $result['failed_24h'];
                $successRate = $total24h > 0 ? round(($result['successful_24h'] / $total24h) * 100, 2) : 100;
                
                // Determine health status
                $status = 'healthy';
                $issues = [];
                
                if (!$result['is_active']) {
                    $status = 'disabled';
                    $issues[] = 'Webhook is disabled';
                } elseif ($successRate < 90) {
                    $status = 'degraded';
                    $issues[] = "Low success rate: {$successRate}%";
                } elseif ($result['pending'] > 50) {
                    $status = 'degraded';
                    $issues[] = "High pending events: {$result['pending']}";
                } elseif (strtotime($result['last_event']) < strtotime('-1 hour')) {
                    $status = 'warning';
                    $issues[] = 'No events in over 1 hour';
                }
                
                $health[] = [
                    'source' => $result['source'],
                    'status' => $status,
                    'is_active' => $result['is_active'],
                    'successful_24h' => $result['successful_24h'],
                    'failed_24h' => $result['failed_24h'],
                    'pending' => $result['pending'],
                    'success_rate' => $successRate,
                    'last_event' => $result['last_event'],
                    'issues' => $issues
                ];
            }
            
            return $health;
            
        } catch (Exception $e) {
            error_log("Getting health status failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get detailed webhook event log
     */
    public function getEventLog($source = null, $limit = 100) {
        try {
            $whereClause = '';
            $params = [];
            
            if ($source) {
                $whereClause = 'WHERE wc.source = ?';
                $params[] = $source;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    we.*,
                    wc.source,
                    wc.webhook_url
                FROM webhook_events we
                JOIN webhook_configs wc ON we.webhook_config_id = wc.id
                $whereClause
                ORDER BY we.created_at DESC
                LIMIT ?
            ");
            
            $params[] = $limit;
            $stmt->execute($params);
            $events = $stmt->fetchAll();
            
            // Process events for display
            foreach ($events as &$event) {
                $event['payload_preview'] = substr($event['payload'], 0, 200) . (strlen($event['payload']) > 200 ? '...' : '');
                $event['payload'] = json_decode($event['payload'], true);
            }
            
            return $events;
            
        } catch (Exception $e) {
            error_log("Getting event log failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old webhook events
     */
    public function cleanupOldEvents($daysOld = 30) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM webhook_events 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$daysOld]);
            
            return ['success' => true, 'deleted_rows' => $stmt->rowCount()];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>