<?php

class GlobalShippingCalculator {
    private $pdo;
    private $baseLatitude = 34.0522;  // Los Angeles, California
    private $baseLongitude = -118.2437;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Calculate global shipping rates with advanced features
     */
    public function calculateGlobalShippingRates($cartItems, $destinationAddress, $options = []) {
        try {
            // Get destination coordinates
            $destCoords = $this->getDestinationCoordinates($destinationAddress);
            if (!$destCoords) {
                throw new Exception("Unable to determine destination coordinates");
            }
            
            // Calculate distance from base (California)
            $distance = $this->calculateDistance(
                $this->baseLatitude, $this->baseLongitude,
                $destCoords['latitude'], $destCoords['longitude']
            );
            
            // Get shipping zone
            $zoneId = $this->getShippingZoneByCountry($destinationAddress['country_code']);
            if (!$zoneId) {
                throw new Exception("Shipping not available to this destination");
            }
            
            // Calculate package details
            $packageDetails = $this->calculatePackageDetails($cartItems);
            
            // Get available shipping options
            $shippingOptions = $this->getGlobalShippingOptions(
                $zoneId, 
                $packageDetails['total_weight'], 
                $packageDetails['total_volume'],
                $distance,
                $packageDetails['total_value']
            );
            
            // Calculate rates for each option
            $rates = [];
            foreach ($shippingOptions as $option) {
                $rate = $this->calculateAdvancedRate(
                    $option, 
                    $packageDetails, 
                    $distance, 
                    $destinationAddress,
                    $options
                );
                
                if ($rate !== null) {
                    $rates[] = $rate;
                }
            }
            
            // Sort by total cost
            usort($rates, function($a, $b) {
                return $a['total_cost'] <=> $b['total_cost'];
            });
            
            return [
                'success' => true,
                'rates' => $rates,
                'package_details' => $packageDetails,
                'distance_km' => $distance,
                'destination_coordinates' => $destCoords
            ];
            
        } catch (Exception $e) {
            error_log("Global shipping calculation error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'rates' => []
            ];
        }
    }
    
    /**
     * Get destination coordinates from address
     */
    private function getDestinationCoordinates($address) {
        // First try to get from user_addresses if address_id provided
        if (isset($address['address_id'])) {
            $stmt = $this->pdo->prepare("
                SELECT latitude, longitude, country_code, state_code 
                FROM user_addresses 
                WHERE id = ?
            ");
            $stmt->execute([$address['address_id']]);
            $result = $stmt->fetch();
            if ($result && $result['latitude'] && $result['longitude']) {
                return $result;
            }
        }
        
        // Try to get from cities table
        if (isset($address['city']) && isset($address['state_code'])) {
            $stmt = $this->pdo->prepare("
                SELECT c.latitude, c.longitude, s.country_id, co.code as country_code
                FROM cities c
                JOIN states s ON c.state_id = s.id
                JOIN countries co ON s.country_id = co.id
                WHERE c.name LIKE ? AND s.code = ? AND co.code = ?
                LIMIT 1
            ");
            $stmt->execute([
                '%' . $address['city'] . '%',
                $address['state_code'],
                $address['country_code']
            ]);
            $result = $stmt->fetch();
            if ($result) {
                return [
                    'latitude' => $result['latitude'],
                    'longitude' => $result['longitude'],
                    'country_code' => $result['country_code']
                ];
            }
        }
        
        // Fall back to country center
        $stmt = $this->pdo->prepare("
            SELECT latitude, longitude, code as country_code 
            FROM countries 
            WHERE code = ?
        ");
        $stmt->execute([$address['country_code']]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Calculate distance between two points using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return round($earthRadius * $c);
    }
    
    /**
     * Calculate package details from cart items
     */
    private function calculatePackageDetails($cartItems) {
        $totalWeight = 0;
        $totalVolume = 0;
        $totalValue = 0;
        $hasFragile = false;
        $hasHazardous = false;
        $hasLiquid = false;
        $hasPerishable = false;
        $requiresSignature = false;
        $requiresAdultSignature = false;
        $maxDeclaredValue = 0;
        $packageTypes = [];
        
        foreach ($cartItems as $item) {
            $dimensions = $this->getProductDimensions($item['product_id']);
            $quantity = $item['quantity'];
            
            $itemWeight = ($dimensions['weight_kg'] ?? 0.5) * $quantity;
            $itemVolume = ($dimensions['volume_cm3'] ?? 3000) * $quantity;
            $itemValue = $item['price'] * $quantity;
            
            $totalWeight += $itemWeight;
            $totalVolume += $itemVolume;
            $totalValue += $itemValue;
            
            if ($dimensions['fragile']) $hasFragile = true;
            if ($dimensions['hazardous']) $hasHazardous = true;
            if ($dimensions['liquid']) $hasLiquid = true;
            if ($dimensions['perishable']) $hasPerishable = true;
            if ($dimensions['requires_signature']) $requiresSignature = true;
            if ($dimensions['requires_adult_signature']) $requiresAdultSignature = true;
            
            $maxDeclaredValue = max($maxDeclaredValue, $dimensions['declared_value'] ?? $itemValue);
            
            $packageTypeId = $dimensions['package_type_id'] ?? 1;
            $packageTypes[$packageTypeId] = ($packageTypes[$packageTypeId] ?? 0) + $quantity;
        }
        
        return [
            'total_weight' => $totalWeight,
            'total_volume' => $totalVolume,
            'total_value' => $totalValue,
            'has_fragile' => $hasFragile,
            'has_hazardous' => $hasHazardous,
            'has_liquid' => $hasLiquid,
            'has_perishable' => $hasPerishable,
            'requires_signature' => $requiresSignature,
            'requires_adult_signature' => $requiresAdultSignature,
            'max_declared_value' => $maxDeclaredValue,
            'package_types' => $packageTypes,
            'estimated_packages' => max(1, ceil($totalVolume / 50000)) // Estimate packages needed
        ];
    }
    
    /**
     * Get product dimensions with package type
     */
    private function getProductDimensions($productId) {
        $stmt = $this->pdo->prepare("
            SELECT pd.*, pt.volume_multiplier, pt.fragile_surcharge, pt.handling_fee
            FROM product_dimensions pd
            LEFT JOIN package_types pt ON pd.package_type_id = pt.id
            WHERE pd.product_id = ?
        ");
        $stmt->execute([$productId]);
        $dimensions = $stmt->fetch();
        
        if (!$dimensions) {
            // Return default dimensions
            return [
                'weight_kg' => 0.5,
                'length_cm' => 20,
                'width_cm' => 15,
                'height_cm' => 10,
                'volume_cm3' => 3000,
                'fragile' => false,
                'hazardous' => false,
                'liquid' => false,
                'perishable' => false,
                'requires_signature' => false,
                'requires_adult_signature' => false,
                'declared_value' => 0,
                'package_type_id' => 1,
                'volume_multiplier' => 1.0,
                'fragile_surcharge' => 0,
                'handling_fee' => 0
            ];
        }
        
        return $dimensions;
    }
    
    /**
     * Get shipping zone by country
     */
    private function getShippingZoneByCountry($countryCode) {
        $stmt = $this->pdo->prepare("
            SELECT sz.id 
            FROM shipping_zones sz
            JOIN zone_countries zc ON sz.id = zc.zone_id
            JOIN countries c ON zc.country_id = c.id
            WHERE c.code = ? AND sz.is_active = TRUE
            ORDER BY sz.base_distance_km ASC
            LIMIT 1
        ");
        $stmt->execute([$countryCode]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    /**
     * Get available global shipping options
     */
    private function getGlobalShippingOptions($zoneId, $weight, $volume, $distance, $value) {
        $stmt = $this->pdo->prepare("
            SELECT 
                srr.*,
                sp.name as provider_name,
                sp.code as provider_code,
                sp.supports_insurance,
                sp.supports_signature,
                ss.name as service_name,
                ss.code as service_code,
                ss.description,
                ss.estimated_days_min,
                ss.estimated_days_max,
                ss.is_express,
                ss.is_overnight,
                ss.supports_insurance as service_supports_insurance,
                ss.max_insurance_value,
                st.name as shipping_type_name,
                st.base_multiplier,
                st.requires_signature as type_requires_signature,
                st.insurance_included,
                sz.customs_required,
                sz.max_processing_days
            FROM shipping_rate_rules srr
            JOIN shipping_providers sp ON srr.provider_id = sp.id
            JOIN shipping_services ss ON srr.service_id = ss.id
            JOIN shipping_types st ON srr.shipping_type_id = st.id
            JOIN shipping_zones sz ON srr.zone_id = sz.id
            WHERE srr.zone_id = ? 
            AND srr.weight_min_kg <= ? 
            AND srr.weight_max_kg >= ?
            AND srr.volume_min_cm3 <= ?
            AND srr.volume_max_cm3 >= ?
            AND srr.distance_min_km <= ?
            AND srr.distance_max_km >= ?
            AND srr.is_active = TRUE
            AND sp.is_active = TRUE
            AND ss.is_active = TRUE
            AND st.is_active = TRUE
            AND (srr.effective_date IS NULL OR srr.effective_date <= CURDATE())
            AND (srr.expiry_date IS NULL OR srr.expiry_date >= CURDATE())
            ORDER BY srr.base_cost ASC
        ");
        
        $stmt->execute([
            $zoneId, $weight, $weight, 
            $volume, $volume, 
            $distance, $distance
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Calculate advanced shipping rate with all factors
     */
    private function calculateAdvancedRate($option, $packageDetails, $distance, $destinationAddress, $userOptions = []) {
        $baseCost = $option['base_cost'];
        $weightCost = $packageDetails['total_weight'] * ($option['cost_per_kg'] ?? 0);
        $volumeCost = $packageDetails['total_volume'] * ($option['cost_per_cm3'] ?? 0);
        $distanceCost = $distance * ($option['cost_per_km'] ?? 0);
        
        // Apply shipping type multiplier
        $typeMultiplier = $option['base_multiplier'] ?? 1.0;
        $shippingCost = ($baseCost + $weightCost + $volumeCost + $distanceCost) * $typeMultiplier;
        
        // Add surcharges
        $surcharges = 0;
        
        // Fragile handling
        if ($packageDetails['has_fragile']) {
            $surcharges += 5.00 * $packageDetails['estimated_packages'];
        }
        
        // Hazardous materials
        if ($packageDetails['has_hazardous']) {
            $surcharges += 25.00 * $packageDetails['estimated_packages'];
        }
        
        // Liquid handling
        if ($packageDetails['has_liquid']) {
            $surcharges += 10.00 * $packageDetails['estimated_packages'];
        }
        
        // Perishable handling
        if ($packageDetails['has_perishable']) {
            $surcharges += 15.00 * $packageDetails['estimated_packages'];
        }
        
        // Fuel surcharge
        $fuelSurcharge = $shippingCost * ($option['fuel_surcharge_rate'] ?? 0) / 100;
        
        // Customs fee
        $customsFee = $option['customs_fee'] ?? 0;
        
        // Calculate insurance cost
        $insuranceCost = 0;
        $insuranceOptions = [];
        
        if ($userOptions['include_insurance'] ?? false) {
            $insuranceValue = min(
                $packageDetails['total_value'],
                $option['max_insurance_value'] ?? 25000
            );
            
            if ($insuranceValue > 0) {
                $insuranceRate = $option['insurance_rate'] ?? 0.005;
                $insuranceCost = max(2.00, $insuranceValue * $insuranceRate);
                
                $insuranceOptions = $this->getInsuranceOptions($insuranceValue);
            }
        }
        
        // Check for free shipping
        $totalBeforeDiscount = $shippingCost + $surcharges + $fuelSurcharge + $customsFee + $insuranceCost;
        
        if ($option['free_shipping_threshold'] && 
            $packageDetails['total_value'] >= $option['free_shipping_threshold']) {
            $shippingCost = 0;
            $totalBeforeDiscount = $surcharges + $fuelSurcharge + $customsFee + $insuranceCost;
        }
        
        $totalCost = max(2.99, $totalBeforeDiscount); // Minimum shipping cost
        
        // Calculate estimated delivery
        $estimatedDelivery = $this->calculateEstimatedDelivery(
            $option['estimated_days_min'],
            $option['estimated_days_max'],
            $option['max_processing_days'] ?? 0,
            $packageDetails['has_perishable']
        );
        
        return [
            'provider_id' => $option['provider_id'],
            'provider_name' => $option['provider_name'],
            'provider_code' => $option['provider_code'],
            'service_id' => $option['service_id'],
            'service_name' => $option['service_name'],
            'service_code' => $option['service_code'],
            'shipping_type_id' => $option['shipping_type_id'],
            'shipping_type_name' => $option['shipping_type_name'],
            'description' => $option['description'],
            'base_cost' => round($shippingCost, 2),
            'surcharges' => round($surcharges, 2),
            'fuel_surcharge' => round($fuelSurcharge, 2),
            'customs_fee' => round($customsFee, 2),
            'insurance_cost' => round($insuranceCost, 2),
            'total_cost' => round($totalCost, 2),
            'estimated_delivery' => $estimatedDelivery,
            'is_express' => $option['is_express'],
            'is_overnight' => $option['is_overnight'],
            'customs_required' => $option['customs_required'],
            'requires_signature' => $packageDetails['requires_signature'] || $option['type_requires_signature'],
            'requires_adult_signature' => $packageDetails['requires_adult_signature'],
            'supports_insurance' => $option['supports_insurance'],
            'insurance_options' => $insuranceOptions,
            'distance_km' => $distance,
            'estimated_packages' => $packageDetails['estimated_packages'],
            'special_handling' => [
                'fragile' => $packageDetails['has_fragile'],
                'hazardous' => $packageDetails['has_hazardous'],
                'liquid' => $packageDetails['has_liquid'],
                'perishable' => $packageDetails['has_perishable']
            ]
        ];
    }
    
    /**
     * Get insurance options for a given value
     */
    private function getInsuranceOptions($value) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM shipping_insurance 
            WHERE max_coverage_amount >= ? AND is_active = TRUE
            ORDER BY rate_percentage ASC
        ");
        $stmt->execute([$value]);
        
        $options = [];
        while ($insurance = $stmt->fetch()) {
            $cost = max(
                $insurance['minimum_fee'],
                min(
                    $insurance['maximum_fee'],
                    $value * $insurance['rate_percentage']
                )
            );
            
            $options[] = [
                'id' => $insurance['id'],
                'name' => $insurance['name'],
                'description' => $insurance['description'],
                'coverage_amount' => min($value, $insurance['max_coverage_amount']),
                'cost' => round($cost, 2),
                'deductible' => $insurance['deductible_amount']
            ];
        }
        
        return $options;
    }
    
    /**
     * Calculate estimated delivery date
     */
    private function calculateEstimatedDelivery($minDays, $maxDays, $processingDays, $isPerishable) {
        $totalMinDays = $minDays + $processingDays;
        $totalMaxDays = $maxDays + $processingDays;

        // Rush perishable items
        if ($isPerishable) {
            $totalMinDays = max(1, $totalMinDays - 1);
            $totalMaxDays = max(2, $totalMaxDays - 1);
        }

        $minDate = date('Y-m-d', strtotime("+{$totalMinDays} days"));
        $maxDate = date('Y-m-d', strtotime("+{$totalMaxDays} days"));

        return [
            'min_date' => $minDate,
            'max_date' => $maxDate,
            'min_days' => $totalMinDays,
            'max_days' => $totalMaxDays,
            'display' => $totalMinDays === $totalMaxDays ?
                "{$totalMinDays} business days" :
                "{$totalMinDays}-{$totalMaxDays} business days"
        ];
    }

    /**
     * Update shipping status for a shipment
     */
    public function updateShippingStatus($shipmentId, $status, $description = '', $location = '', $coordinates = null) {
        try {
            $this->pdo->beginTransaction();

            // Update shipment status
            $stmt = $this->pdo->prepare("
                UPDATE shipments
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $shipmentId]);

            // Add tracking event
            $eventStmt = $this->pdo->prepare("
                INSERT INTO tracking_events (shipment_id, status, description, location, latitude, longitude, event_time)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $latitude = $coordinates['latitude'] ?? null;
            $longitude = $coordinates['longitude'] ?? null;

            $eventStmt->execute([
                $shipmentId, $status, $description, $location, $latitude, $longitude
            ]);

            // Update delivery date if delivered
            if ($status === 'delivered') {
                $deliveryStmt = $this->pdo->prepare("
                    UPDATE shipments
                    SET actual_delivery = NOW()
                    WHERE id = ?
                ");
                $deliveryStmt->execute([$shipmentId]);
            }

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Shipping status update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create shipment with enhanced tracking
     */
    public function createGlobalShipment($orderId, $shippingOption, $packageDetails, $originAddress, $destinationAddress, $options = []) {
        try {
            $this->pdo->beginTransaction();

            // Generate tracking number
            $trackingNumber = $this->generateTrackingNumber($shippingOption['provider_code']);

            // Get coordinates
            $originCoords = $this->getAddressCoordinates($originAddress);
            $destCoords = $this->getAddressCoordinates($destinationAddress);

            // Calculate distance
            $distance = $this->calculateDistance(
                $originCoords['latitude'], $originCoords['longitude'],
                $destCoords['latitude'], $destCoords['longitude']
            );

            // Insert shipment
            $stmt = $this->pdo->prepare("
                INSERT INTO shipments (
                    order_id, provider_id, service_id, shipping_type_id, insurance_id,
                    tracking_number, shipping_cost, insurance_cost, fuel_surcharge,
                    customs_fee, total_cost, weight_kg, volume_cm3, distance_km,
                    declared_value, origin_latitude, origin_longitude, origin_address,
                    destination_latitude, destination_longitude, destination_address,
                    signature_required, adult_signature_required, special_instructions
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $orderId,
                $shippingOption['provider_id'],
                $shippingOption['service_id'],
                $shippingOption['shipping_type_id'],
                $options['insurance_id'] ?? null,
                $trackingNumber,
                $shippingOption['base_cost'],
                $shippingOption['insurance_cost'],
                $shippingOption['fuel_surcharge'],
                $shippingOption['customs_fee'],
                $shippingOption['total_cost'],
                $packageDetails['total_weight'],
                $packageDetails['total_volume'],
                $distance,
                $packageDetails['total_value'],
                $originCoords['latitude'],
                $originCoords['longitude'],
                $this->formatAddress($originAddress),
                $destCoords['latitude'],
                $destCoords['longitude'],
                $this->formatAddress($destinationAddress),
                $shippingOption['requires_signature'],
                $shippingOption['requires_adult_signature'],
                $options['special_instructions'] ?? ''
            ]);

            $shipmentId = $this->pdo->lastInsertId();

            // Add initial tracking event
            $this->updateShippingStatus(
                $shipmentId,
                'created',
                'Shipment created and label generated',
                $originAddress['city'] ?? 'Origin facility',
                $originCoords
            );

            $this->pdo->commit();

            return [
                'shipment_id' => $shipmentId,
                'tracking_number' => $trackingNumber,
                'estimated_delivery' => $shippingOption['estimated_delivery']
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Global shipment creation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate tracking number with provider prefix
     */
    private function generateTrackingNumber($providerCode) {
        $timestamp = time();
        $random = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        return $providerCode . $timestamp . $random;
    }

    /**
     * Get coordinates for an address
     */
    private function getAddressCoordinates($address) {
        // This would integrate with a geocoding service in production
        // For now, return default coordinates based on country/state

        if (isset($address['latitude']) && isset($address['longitude'])) {
            return [
                'latitude' => $address['latitude'],
                'longitude' => $address['longitude']
            ];
        }

        // Default to country center
        $stmt = $this->pdo->prepare("
            SELECT latitude, longitude
            FROM countries
            WHERE code = ?
        ");
        $stmt->execute([$address['country_code'] ?? 'USA']);
        $result = $stmt->fetch();

        return $result ?: [
            'latitude' => $this->baseLatitude,
            'longitude' => $this->baseLongitude
        ];
    }

    /**
     * Format address for storage
     */
    private function formatAddress($address) {
        $parts = [];

        if (!empty($address['company_name'])) {
            $parts[] = $address['company_name'];
        }

        if (!empty($address['recipient_name'])) {
            $parts[] = $address['recipient_name'];
        }

        if (!empty($address['address_line1'])) {
            $parts[] = $address['address_line1'];
        }

        if (!empty($address['address_line2'])) {
            $parts[] = $address['address_line2'];
        }

        $cityStateZip = [];
        if (!empty($address['city'])) {
            $cityStateZip[] = $address['city'];
        }
        if (!empty($address['state_code'])) {
            $cityStateZip[] = $address['state_code'];
        }
        if (!empty($address['postal_code'])) {
            $cityStateZip[] = $address['postal_code'];
        }

        if (!empty($cityStateZip)) {
            $parts[] = implode(', ', $cityStateZip);
        }

        if (!empty($address['country_code'])) {
            $parts[] = $address['country_code'];
        }

        return implode("\n", $parts);
    }
}
