<?php
// Real-time Inventory WebSocket Server
// This script provides real-time inventory updates using WebSockets

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class InventoryWebSocketServer implements MessageComponentInterface {
    protected $clients;
    protected $subscriptions;
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->subscriptions = [];
    }
    
    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data) {
            return;
        }
        
        switch ($data['action']) {
            case 'subscribe':
                $this->handleSubscription($from, $data);
                break;
            case 'unsubscribe':
                $this->handleUnsubscription($from, $data);
                break;
            case 'inventory_update':
                $this->handleInventoryUpdate($data);
                break;
            case 'get_current_state':
                $this->sendCurrentState($from, $data);
                break;
        }
    }
    
    public function onClose(ConnectionInterface $conn) {
        // Remove the connection when closed
        $this->clients->detach($conn);
        
        // Remove from all subscriptions
        foreach ($this->subscriptions as $productId => $subscribers) {
            if (isset($this->subscriptions[$productId][$conn->resourceId])) {
                unset($this->subscriptions[$productId][$conn->resourceId]);
            }
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
    
    private function handleSubscription(ConnectionInterface $conn, $data) {
        $productId = $data['product_id'] ?? null;
        $locationId = $data['location_id'] ?? null;
        
        if ($productId) {
            if (!isset($this->subscriptions[$productId])) {
                $this->subscriptions[$productId] = [];
            }
            $this->subscriptions[$productId][$conn->resourceId] = $conn;
            
            // Send confirmation
            $conn->send(json_encode([
                'action' => 'subscribed',
                'product_id' => $productId,
                'message' => 'Successfully subscribed to product updates'
            ]));
        }
    }
    
    private function handleUnsubscription(ConnectionInterface $conn, $data) {
        $productId = $data['product_id'] ?? null;
        
        if ($productId && isset($this->subscriptions[$productId][$conn->resourceId])) {
            unset($this->subscriptions[$productId][$conn->resourceId]);
            
            // Send confirmation
            $conn->send(json_encode([
                'action' => 'unsubscribed',
                'product_id' => $productId,
                'message' => 'Successfully unsubscribed from product updates'
            ]));
        }
    }
    
    private function handleInventoryUpdate($data) {
        $productId = $data['product_id'] ?? null;
        $locationId = $data['location_id'] ?? null;
        $newQuantity = $data['quantity'] ?? null;
        
        if (!$productId || !$newQuantity) {
            return;
        }
        
        // Broadcast to all subscribers
        $this->broadcastInventoryUpdate($productId, $data);
        
        // Also broadcast to global subscribers (those subscribed to all products)
        if (isset($this->subscriptions['all'])) {
            foreach ($this->subscriptions['all'] as $conn) {
                $conn->send(json_encode([
                    'action' => 'inventory_update',
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'quantity' => $newQuantity,
                    'timestamp' => time()
                ]));
            }
        }
    }
    
    private function broadcastInventoryUpdate($productId, $data) {
        if (isset($this->subscriptions[$productId])) {
            foreach ($this->subscriptions[$productId] as $conn) {
                $conn->send(json_encode([
                    'action' => 'inventory_update',
                    'product_id' => $productId,
                    'data' => $data,
                    'timestamp' => time()
                ]));
            }
        }
    }
    
    private function sendCurrentState(ConnectionInterface $conn, $data) {
        try {
            global $pdo;
            
            $productId = $data['product_id'] ?? null;
            $locationId = $data['location_id'] ?? null;
            
            if ($productId) {
                // Get current inventory state for specific product
                $stmt = $pdo->prepare("
                    SELECT pi.*, p.name as product_name, il.location_name
                    FROM product_inventory pi
                    JOIN products p ON pi.product_id = p.id
                    JOIN inventory_locations il ON pi.location_id = il.id
                    WHERE pi.product_id = ?
                ");
                
                if ($locationId) {
                    $stmt->execute([$productId, $locationId]);
                } else {
                    $stmt->execute([$productId]);
                }
                
                $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $conn->send(json_encode([
                    'action' => 'current_state',
                    'product_id' => $productId,
                    'inventory' => $inventory,
                    'timestamp' => time()
                ]));
            } else {
                // Get overall inventory summary
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(DISTINCT product_id) as total_products,
                        SUM(quantity_on_hand) as total_items,
                        COUNT(CASE WHEN quantity_on_hand <= reorder_point THEN 1 END) as low_stock_items
                    FROM product_inventory
                ");
                
                $summary = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $conn->send(json_encode([
                    'action' => 'current_state',
                    'summary' => $summary,
                    'timestamp' => time()
                ]));
            }
        } catch (Exception $e) {
            $conn->send(json_encode([
                'action' => 'error',
                'message' => 'Failed to retrieve current state: ' . $e->getMessage()
            ]));
        }
    }
}

// Set up the WebSocket server
try {
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new InventoryWebSocketServer()
            )
        ),
        8080 // Port for WebSocket server
    );
    
    echo "Inventory WebSocket server started on port 8080\n";
    $server->run();
} catch (Exception $e) {
    echo "Failed to start WebSocket server: " . $e->getMessage() . "\n";
}

?>