// Real-time Inventory Updates
// This script handles real-time inventory synchronization using Server-Sent Events

class RealTimeInventory {
    constructor() {
        this.eventSource = null;
        this.isConnected = false;
        this.updateCallbacks = [];
        this.summaryCallbacks = [];
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
    }
    
    // Connect to the SSE endpoint
    connect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
        
        const url = '../api/inventory-sse.php';
        this.eventSource = new EventSource(url);
        
        this.eventSource.onopen = () => {
            console.log('Connected to real-time inventory updates');
            this.isConnected = true;
            this.reconnectAttempts = 0;
            this.notifyCallbacks('connected', { message: 'Connected to inventory update stream' });
        };
        
        this.eventSource.onerror = (event) => {
            console.error('Error with real-time inventory connection:', event);
            this.isConnected = false;
            
            // Attempt to reconnect with exponential backoff
            if (this.reconnectAttempts < this.maxReconnectAttempts) {
                this.reconnectAttempts++;
                const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
                console.log(`Attempting to reconnect in ${delay}ms...`);
                setTimeout(() => this.connect(), delay);
            } else {
                console.error('Max reconnect attempts reached. Giving up.');
                this.notifyCallbacks('error', { message: 'Failed to connect to inventory updates' });
            }
        };
        
        // Handle inventory updates
        this.eventSource.addEventListener('update', (event) => {
            const data = JSON.parse(event.data);
            console.log('Inventory update received:', data);
            this.notifyCallbacks('update', data);
        });
        
        // Handle inventory summaries
        this.eventSource.addEventListener('summary', (event) => {
            const data = JSON.parse(event.data);
            console.log('Inventory summary received:', data);
            this.notifyCallbacks('summary', data);
        });
        
        // Handle connection messages
        this.eventSource.addEventListener('connected', (event) => {
            const data = JSON.parse(event.data);
            console.log('Inventory stream connected:', data);
            this.notifyCallbacks('connected', data);
        });
    }
    
    // Disconnect from the SSE endpoint
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
            this.isConnected = false;
        }
    }
    
    // Add callback for inventory updates
    onUpdate(callback) {
        this.updateCallbacks.push(callback);
    }
    
    // Add callback for inventory summaries
    onSummary(callback) {
        this.summaryCallbacks.push(callback);
    }
    
    // Notify all callbacks of an event
    notifyCallbacks(type, data) {
        switch (type) {
            case 'update':
                this.updateCallbacks.forEach(callback => callback(data));
                break;
            case 'summary':
                this.summaryCallbacks.forEach(callback => callback(data));
                break;
            case 'connected':
            case 'error':
                // Notify all callbacks of connection events
                [...this.updateCallbacks, ...this.summaryCallbacks].forEach(callback => {
                    if (typeof callback === 'function') {
                        callback({ type, ...data });
                    }
                });
                break;
        }
    }
    
    // Send inventory update (for manual updates)
    sendInventoryUpdate(productId, locationId, quantity, movementType = 'adjustment') {
        // In a real implementation, this would send to a backend API
        console.log('Sending inventory update:', { productId, locationId, quantity, movementType });
        
        // For now, we'll just simulate the update
        setTimeout(() => {
            this.notifyCallbacks('update', {
                type: 'inventory_movement',
                data: {
                    product_id: productId,
                    location_id: locationId,
                    movement_type: movementType,
                    quantity: quantity,
                    product_name: 'Sample Product',
                    location_name: 'Sample Location',
                    created_at: new Date().toISOString()
                },
                timestamp: new Date().toISOString()
            });
        }, 100);
    }
}

// Create global instance
const realTimeInventory = new RealTimeInventory();

// Auto-connect when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    realTimeInventory.connect();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RealTimeInventory;
}