<?php

class ShippingCalculator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Calculate shipping rates for a cart/order
     */
    public function calculateShippingRates($cartItems, $destinationCountry, $destinationState = null, $destinationCity = null) {
        try {
            // Get destination zone
            $zoneId = $this->getShippingZone($destinationCountry);
            if (!$zoneId) {
                throw new Exception("Shipping not available to this destination");
            }
            
            // Calculate total weight and dimensions
            $totalWeight = 0;
            $totalVolume = 0;
            $hasFragile = false;
            $hasHazardous = false;
            $requiresSignature = false;
            $totalValue = 0;
            
            foreach ($cartItems as $item) {
                $dimensions = $this->getProductDimensions($item['product_id']);
                $quantity = $item['quantity'];
                
                $totalWeight += ($dimensions['weight_kg'] ?? 0.5) * $quantity; // Default 0.5kg if not set
                $totalVolume += $this->calculateVolume($dimensions) * $quantity;
                $totalValue += $item['price'] * $quantity;
                
                if ($dimensions['fragile']) $hasFragile = true;
                if ($dimensions['hazardous']) $hasHazardous = true;
                if ($dimensions['requires_signature']) $requiresSignature = true;
            }
            
            // Get available shipping options
            $shippingOptions = $this->getShippingOptions($zoneId, $totalWeight, $totalValue);
            
            // Calculate rates for each option
            $rates = [];
            foreach ($shippingOptions as $option) {
                $rate = $this->calculateRate($option, $totalWeight, $totalVolume, $totalValue, $hasFragile, $hasHazardous);
                if ($rate !== null) {
                    $rates[] = [
                        'provider_id' => $option['provider_id'],
                        'provider_name' => $option['provider_name'],
                        'service_id' => $option['service_id'],
                        'service_name' => $option['service_name'],
                        'service_code' => $option['service_code'],
                        'cost' => $rate,
                        'estimated_days_min' => $option['estimated_days_min'],
                        'estimated_days_max' => $option['estimated_days_max'],
                        'description' => $option['description'],
                        'requires_signature' => $requiresSignature,
                        'has_fragile' => $hasFragile,
                        'total_weight_kg' => $totalWeight
                    ];
                }
            }
            
            // Sort by cost (cheapest first)
            usort($rates, function($a, $b) {
                return $a['cost'] <=> $b['cost'];
            });
            
            return $rates;
            
        } catch (Exception $e) {
            error_log("Shipping calculation error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get shipping zone for a country
     */
    private function getShippingZone($countryCode) {
        $stmt = $this->pdo->prepare("
            SELECT sz.id 
            FROM shipping_zones sz
            JOIN zone_countries zc ON sz.id = zc.zone_id
            JOIN countries c ON zc.country_id = c.id
            WHERE c.code = ? AND sz.is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute([$countryCode]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    /**
     * Get product dimensions
     */
    private function getProductDimensions($productId) {
        $stmt = $this->pdo->prepare("SELECT * FROM product_dimensions WHERE product_id = ?");
        $stmt->execute([$productId]);
        $dimensions = $stmt->fetch();
        
        if (!$dimensions) {
            // Return default dimensions if not set
            return [
                'weight_kg' => 0.5,
                'length_cm' => 20,
                'width_cm' => 15,
                'height_cm' => 10,
                'fragile' => false,
                'hazardous' => false,
                'requires_signature' => false
            ];
        }
        
        return $dimensions;
    }
    
    /**
     * Calculate volume in cubic cm
     */
    private function calculateVolume($dimensions) {
        return ($dimensions['length_cm'] ?? 20) * 
               ($dimensions['width_cm'] ?? 15) * 
               ($dimensions['height_cm'] ?? 10);
    }
    
    /**
     * Get available shipping options for zone and weight
     */
    private function getShippingOptions($zoneId, $totalWeight, $totalValue) {
        $stmt = $this->pdo->prepare("
            SELECT 
                srr.*,
                sp.name as provider_name,
                sp.code as provider_code,
                sp.max_weight_kg,
                ss.name as service_name,
                ss.code as service_code,
                ss.description,
                ss.estimated_days_min,
                ss.estimated_days_max
            FROM shipping_rate_rules srr
            JOIN shipping_providers sp ON srr.provider_id = sp.id
            JOIN shipping_services ss ON srr.service_id = ss.id
            WHERE srr.zone_id = ? 
            AND srr.weight_min_kg <= ? 
            AND srr.weight_max_kg >= ?
            AND srr.is_active = TRUE
            AND sp.is_active = TRUE
            AND ss.is_active = TRUE
            AND (srr.effective_date IS NULL OR srr.effective_date <= CURDATE())
            AND (srr.expiry_date IS NULL OR srr.expiry_date >= CURDATE())
            AND sp.max_weight_kg >= ?
            ORDER BY srr.base_cost ASC
        ");
        $stmt->execute([$zoneId, $totalWeight, $totalWeight, $totalWeight]);
        return $stmt->fetchAll();
    }
    
    /**
     * Calculate shipping rate for a specific option
     */
    private function calculateRate($option, $totalWeight, $totalVolume, $totalValue, $hasFragile, $hasHazardous) {
        $baseCost = $option['base_cost'];
        $costPerKg = $option['cost_per_kg'] ?? 0;
        $costPerCubicCm = $option['cost_per_cubic_cm'] ?? 0;
        $freeShippingThreshold = $option['free_shipping_threshold'];
        
        // Check for free shipping
        if ($freeShippingThreshold && $totalValue >= $freeShippingThreshold) {
            return 0.00;
        }
        
        // Calculate base rate
        $rate = $baseCost;
        
        // Add weight-based cost
        if ($costPerKg > 0) {
            $rate += $totalWeight * $costPerKg;
        }
        
        // Add volume-based cost
        if ($costPerCubicCm > 0) {
            $rate += $totalVolume * $costPerCubicCm;
        }
        
        // Add surcharges
        if ($hasFragile) {
            $rate += 5.00; // Fragile handling fee
        }
        
        if ($hasHazardous) {
            $rate += 15.00; // Hazardous materials fee
        }
        
        // Minimum rate
        $rate = max($rate, 2.99);
        
        return round($rate, 2);
    }
    
    /**
     * Calculate taxes for an order
     */
    public function calculateTaxes($orderTotal, $countryCode, $stateCode = null, $productCategories = []) {
        $taxes = [];
        $totalTaxAmount = 0;
        
        try {
            // Get country tax rate
            $stmt = $this->pdo->prepare("SELECT tax_rate FROM countries WHERE code = ? AND is_active = TRUE");
            $stmt->execute([$countryCode]);
            $country = $stmt->fetch();
            
            if ($country && $country['tax_rate'] > 0) {
                $countryTax = ($orderTotal * $country['tax_rate']) / 100;
                $taxes[] = [
                    'type' => 'country_tax',
                    'name' => 'National Tax',
                    'rate' => $country['tax_rate'],
                    'amount' => $countryTax
                ];
                $totalTaxAmount += $countryTax;
            }
            
            // Get state/province tax rate
            if ($stateCode) {
                $stmt = $this->pdo->prepare("
                    SELECT s.tax_rate, s.name 
                    FROM states s 
                    JOIN countries c ON s.country_id = c.id 
                    WHERE s.code = ? AND c.code = ? AND s.is_active = TRUE
                ");
                $stmt->execute([$stateCode, $countryCode]);
                $state = $stmt->fetch();
                
                if ($state && $state['tax_rate'] > 0) {
                    $stateTax = ($orderTotal * $state['tax_rate']) / 100;
                    $taxes[] = [
                        'type' => 'state_tax',
                        'name' => $state['name'] . ' Tax',
                        'rate' => $state['tax_rate'],
                        'amount' => $stateTax
                    ];
                    $totalTaxAmount += $stateTax;
                }
            }
            
            return [
                'taxes' => $taxes,
                'total_tax_amount' => round($totalTaxAmount, 2)
            ];
            
        } catch (Exception $e) {
            error_log("Tax calculation error: " . $e->getMessage());
            return ['taxes' => [], 'total_tax_amount' => 0];
        }
    }
    
    /**
     * Convert currency
     */
    public function convertCurrency($amount, $fromCurrency, $toCurrency) {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT rate FROM currency_rates 
                WHERE from_currency = ? AND to_currency = ?
            ");
            $stmt->execute([$fromCurrency, $toCurrency]);
            $rate = $stmt->fetchColumn();
            
            if ($rate) {
                return round($amount * $rate, 2);
            }
            
            // If direct rate not found, try reverse
            $stmt = $this->pdo->prepare("
                SELECT rate FROM currency_rates 
                WHERE from_currency = ? AND to_currency = ?
            ");
            $stmt->execute([$toCurrency, $fromCurrency]);
            $reverseRate = $stmt->fetchColumn();
            
            if ($reverseRate) {
                return round($amount / $reverseRate, 2);
            }
            
            return $amount; // Return original if no rate found
            
        } catch (Exception $e) {
            error_log("Currency conversion error: " . $e->getMessage());
            return $amount;
        }
    }
    
    /**
     * Create shipment record
     */
    public function createShipment($orderId, $providerId, $serviceId, $shippingCost, $weight, $dimensions, $originAddress, $destinationAddress) {
        try {
            // Generate tracking number
            $trackingNumber = $this->generateTrackingNumber($providerId);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO shipments (order_id, provider_id, service_id, tracking_number, shipping_cost, weight_kg, dimensions_cm, origin_address, destination_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $orderId,
                $providerId,
                $serviceId,
                $trackingNumber,
                $shippingCost,
                $weight,
                $dimensions,
                $originAddress,
                $destinationAddress
            ]);
            
            $shipmentId = $this->pdo->lastInsertId();
            
            // Add initial tracking event
            $this->addTrackingEvent($shipmentId, 'created', 'Shipment created', 'Origin facility');
            
            return [
                'shipment_id' => $shipmentId,
                'tracking_number' => $trackingNumber
            ];
            
        } catch (Exception $e) {
            error_log("Shipment creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate tracking number
     */
    private function generateTrackingNumber($providerId) {
        $stmt = $this->pdo->prepare("SELECT code FROM shipping_providers WHERE id = ?");
        $stmt->execute([$providerId]);
        $providerCode = $stmt->fetchColumn();
        
        $timestamp = time();
        $random = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        return $providerCode . $timestamp . $random;
    }
    
    /**
     * Add tracking event
     */
    public function addTrackingEvent($shipmentId, $status, $description, $location) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO tracking_events (shipment_id, status, description, location, event_time)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$shipmentId, $status, $description, $location]);

            // Update shipment status
            $stmt = $this->pdo->prepare("UPDATE shipments SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $shipmentId]);

            return true;
        } catch (Exception $e) {
            error_log("Tracking event error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get shipment tracking information
     */
    public function getShipmentTracking($trackingNumber) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, sp.name as provider_name, sp.tracking_url_template,
                       ss.name as service_name, o.id as order_id
                FROM shipments s
                JOIN shipping_providers sp ON s.provider_id = sp.id
                JOIN shipping_services ss ON s.service_id = ss.id
                JOIN orders o ON s.order_id = o.id
                WHERE s.tracking_number = ?
            ");
            $stmt->execute([$trackingNumber]);
            $shipment = $stmt->fetch();

            if (!$shipment) {
                return null;
            }

            // Get tracking events
            $stmt = $this->pdo->prepare("
                SELECT * FROM tracking_events
                WHERE shipment_id = ?
                ORDER BY event_time DESC
            ");
            $stmt->execute([$shipment['id']]);
            $events = $stmt->fetchAll();

            $shipment['tracking_events'] = $events;
            $shipment['tracking_url'] = str_replace('{tracking_number}', $trackingNumber, $shipment['tracking_url_template']);

            return $shipment;
        } catch (Exception $e) {
            error_log("Tracking lookup error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update product dimensions
     */
    public function updateProductDimensions($productId, $dimensions) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO product_dimensions (product_id, weight_kg, length_cm, width_cm, height_cm, fragile, hazardous, requires_signature)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                weight_kg = VALUES(weight_kg),
                length_cm = VALUES(length_cm),
                width_cm = VALUES(width_cm),
                height_cm = VALUES(height_cm),
                fragile = VALUES(fragile),
                hazardous = VALUES(hazardous),
                requires_signature = VALUES(requires_signature)
            ");

            return $stmt->execute([
                $productId,
                $dimensions['weight_kg'] ?? 0.5,
                $dimensions['length_cm'] ?? 20,
                $dimensions['width_cm'] ?? 15,
                $dimensions['height_cm'] ?? 10,
                $dimensions['fragile'] ?? false,
                $dimensions['hazardous'] ?? false,
                $dimensions['requires_signature'] ?? false
            ]);
        } catch (Exception $e) {
            error_log("Product dimensions update error: " . $e->getMessage());
            return false;
        }
    }
}
