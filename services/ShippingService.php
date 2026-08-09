<?php
/**
 * ShippingService
 *
 * Creates a shipment record for a paid order so fulfilment and tracking can
 * proceed. Uses the order's shipping cost/address; carrier selection and real
 * rate quoting are Phase 5 (shipping_rate_rules / shipping_zones).
 */
class ShippingService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a 'created' shipment for the order if one does not already exist.
     * Idempotent.
     *
     * @return int|null shipment id (existing or new)
     */
    public function createShipmentForOrder($orderId, $merchantId = null)
    {
        // One shipment per (order, seller). NULL merchant = single-vendor order.
        if ($merchantId === null) {
            $existing = $this->pdo->prepare("SELECT id FROM shipments WHERE order_id = ? LIMIT 1");
            $existing->execute([$orderId]);
        } else {
            $existing = $this->pdo->prepare("SELECT id FROM shipments WHERE order_id = ? AND merchant_id = ? LIMIT 1");
            $existing->execute([$orderId, $merchantId]);
        }
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $orderStmt = $this->pdo->prepare("
            SELECT shipping_cost, shipping_address FROM orders WHERE id = ? LIMIT 1
        ");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return null;
        }

        // provider_id and service_id are required FKs. Resolve defaults;
        // real carrier selection / rate quoting is a later phase.
        [$providerId, $serviceId] = $this->resolveCarrier();
        if ($providerId === null || $serviceId === null) {
            error_log("ShippingService: no shipping provider/service configured; skipping shipment for order $orderId");
            return null;
        }

        $shippingCost = (float) ($order['shipping_cost'] ?? 0);
        $trackingNumber = 'VD' . str_pad((string) $orderId, 8, '0', STR_PAD_LEFT)
            . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        // shipping_type_id must reference a real type (the column default can
        // point at a deleted id after reseeds) — take it from the service.
        $typeId = (int) ($this->pdo->query("SELECT shipping_type_id FROM shipping_services WHERE id = " . (int) $serviceId)->fetchColumn()
            ?: $this->pdo->query("SELECT id FROM shipping_types ORDER BY id LIMIT 1")->fetchColumn());

        $stmt = $this->pdo->prepare("
            INSERT INTO shipments
                (order_id, merchant_id, provider_id, service_id, shipping_type_id, tracking_number, status, shipping_cost, total_cost,
                 destination_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'created', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $orderId, $merchantId,
            $providerId,
            $serviceId,
            $typeId,
            $trackingNumber,
            $shippingCost,
            $shippingCost,
            $order['shipping_address'] ?? null,
        ]);

        $shipmentId = (int) $this->pdo->lastInsertId();

        // Seed the first tracking event.
        $ev = $this->pdo->prepare("
            INSERT INTO tracking_events (shipment_id, status, description, event_time, created_at)
            VALUES (?, 'created', 'Shipment created and awaiting pickup', NOW(), NOW())
        ");
        $ev->execute([$shipmentId]);

        return $shipmentId;
    }

    /**
     * Resolve a default carrier. Prefers a service linked to its own provider
     * (consistent pair); falls back to the first provider + first service.
     *
     * @return array{0:?int,1:?int} [providerId, serviceId]
     */
    private function resolveCarrier()
    {
        // Try a service that carries its own provider_id for a consistent pair.
        try {
            $row = $this->pdo->query("
                SELECT id AS service_id, provider_id
                FROM shipping_services
                WHERE provider_id IS NOT NULL
                ORDER BY id LIMIT 1
            ")->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['provider_id']) {
                return [(int) $row['provider_id'], (int) $row['service_id']];
            }
        } catch (Exception $e) {
            // shipping_services may not have a provider_id column; fall through.
        }

        $providerId = $this->pdo->query("SELECT id FROM shipping_providers ORDER BY id LIMIT 1")->fetchColumn();
        $serviceId  = $this->pdo->query("SELECT id FROM shipping_services ORDER BY id LIMIT 1")->fetchColumn();

        return [
            $providerId !== false ? (int) $providerId : null,
            $serviceId !== false ? (int) $serviceId : null,
        ];
    }
}
