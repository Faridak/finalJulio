<?php

class GeographicalManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all active countries
     */
    public function getCountries() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM countries 
            WHERE is_active = TRUE 
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get states/provinces for a country
     */
    public function getStates($countryCode) {
        $stmt = $this->pdo->prepare("
            SELECT s.* 
            FROM states s
            JOIN countries c ON s.country_id = c.id
            WHERE c.code = ? AND s.is_active = TRUE
            ORDER BY s.name ASC
        ");
        $stmt->execute([$countryCode]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get cities for a state
     */
    public function getCities($stateId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM cities 
            WHERE state_id = ? AND is_active = TRUE
            ORDER BY name ASC
        ");
        $stmt->execute([$stateId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Validate address format
     */
    public function validateAddress($countryCode, $stateCode, $postalCode) {
        $errors = [];
        
        // Check if country exists
        $stmt = $this->pdo->prepare("SELECT id FROM countries WHERE code = ? AND is_active = TRUE");
        $stmt->execute([$countryCode]);
        $country = $stmt->fetch();
        
        if (!$country) {
            $errors[] = "Invalid country code";
            return $errors;
        }
        
        // Check if state exists for country
        if ($stateCode) {
            $stmt = $this->pdo->prepare("
                SELECT s.id, s.name 
                FROM states s
                JOIN countries c ON s.country_id = c.id
                WHERE c.code = ? AND s.code = ? AND s.is_active = TRUE
            ");
            $stmt->execute([$countryCode, $stateCode]);
            $state = $stmt->fetch();
            
            if (!$state) {
                $errors[] = "Invalid state/province code for this country";
            }
        }
        
        // Validate postal code format based on country
        if ($postalCode) {
            $postalCodePattern = $this->getPostalCodePattern($countryCode);
            if ($postalCodePattern && !preg_match($postalCodePattern, $postalCode)) {
                $errors[] = "Invalid postal code format for " . $countryCode;
            }
        }
        
        return $errors;
    }
    
    /**
     * Get postal code pattern for country
     */
    private function getPostalCodePattern($countryCode) {
        $patterns = [
            'USA' => '/^\d{5}(-\d{4})?$/', // 12345 or 12345-6789
            'CAN' => '/^[A-Z]\d[A-Z] \d[A-Z]\d$/', // A1A 1A1
            'GBR' => '/^[A-Z]{1,2}\d[A-Z\d]? \d[A-Z]{2}$/i', // SW1A 1AA
            'DEU' => '/^\d{5}$/', // 12345
            'FRA' => '/^\d{5}$/', // 12345
            'JPN' => '/^\d{3}-\d{4}$/', // 123-4567
            'AUS' => '/^\d{4}$/', // 1234
        ];
        
        return $patterns[$countryCode] ?? null;
    }
    
    /**
     * Get country information by code
     */
    public function getCountryInfo($countryCode) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM countries 
            WHERE code = ? AND is_active = TRUE
        ");
        $stmt->execute([$countryCode]);
        return $stmt->fetch();
    }
    
    /**
     * Get state information
     */
    public function getStateInfo($countryCode, $stateCode) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, c.name as country_name, c.currency_code, c.currency_symbol
            FROM states s
            JOIN countries c ON s.country_id = c.id
            WHERE c.code = ? AND s.code = ? AND s.is_active = TRUE
        ");
        $stmt->execute([$countryCode, $stateCode]);
        return $stmt->fetch();
    }
    
    /**
     * Add new country
     */
    public function addCountry($code, $name, $currencyCode, $currencySymbol, $taxRate = 0) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO countries (code, name, currency_code, currency_symbol, tax_rate)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$code, $name, $currencyCode, $currencySymbol, $taxRate]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Error adding country: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add new state/province
     */
    public function addState($countryId, $code, $name, $taxRate = 0) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO states (country_id, code, name, tax_rate)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$countryId, $code, $name, $taxRate]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Error adding state: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update country information
     */
    public function updateCountry($id, $data) {
        try {
            $fields = [];
            $values = [];
            
            foreach ($data as $field => $value) {
                if (in_array($field, ['name', 'currency_code', 'currency_symbol', 'tax_rate', 'is_active'])) {
                    $fields[] = "$field = ?";
                    $values[] = $value;
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $values[] = $id;
            $sql = "UPDATE countries SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Error updating country: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update state information
     */
    public function updateState($id, $data) {
        try {
            $fields = [];
            $values = [];
            
            foreach ($data as $field => $value) {
                if (in_array($field, ['name', 'tax_rate', 'is_active'])) {
                    $fields[] = "$field = ?";
                    $values[] = $value;
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $values[] = $id;
            $sql = "UPDATE states SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Error updating state: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get shipping zones
     */
    public function getShippingZones() {
        $stmt = $this->pdo->prepare("
            SELECT sz.*, 
                   GROUP_CONCAT(c.name ORDER BY c.name) as countries
            FROM shipping_zones sz
            LEFT JOIN zone_countries zc ON sz.id = zc.zone_id
            LEFT JOIN countries c ON zc.country_id = c.id
            WHERE sz.is_active = TRUE
            GROUP BY sz.id
            ORDER BY sz.name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Add shipping zone
     */
    public function addShippingZone($name, $description, $countryIds = []) {
        try {
            $this->pdo->beginTransaction();
            
            // Insert zone
            $stmt = $this->pdo->prepare("
                INSERT INTO shipping_zones (name, description)
                VALUES (?, ?)
            ");
            $stmt->execute([$name, $description]);
            $zoneId = $this->pdo->lastInsertId();
            
            // Add countries to zone
            if (!empty($countryIds)) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO zone_countries (zone_id, country_id)
                    VALUES (?, ?)
                ");
                
                foreach ($countryIds as $countryId) {
                    $stmt->execute([$zoneId, $countryId]);
                }
            }
            
            $this->pdo->commit();
            return $zoneId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error adding shipping zone: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update currency exchange rates
     */
    public function updateCurrencyRates($rates) {
        try {
            $this->pdo->beginTransaction();
            
            foreach ($rates as $rate) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO currency_rates (from_currency, to_currency, rate)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE rate = VALUES(rate), updated_at = NOW()
                ");
                $stmt->execute([$rate['from'], $rate['to'], $rate['rate']]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error updating currency rates: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current currency rates
     */
    public function getCurrencyRates($baseCurrency = 'USD') {
        $stmt = $this->pdo->prepare("
            SELECT * FROM currency_rates 
            WHERE from_currency = ? 
            ORDER BY to_currency
        ");
        $stmt->execute([$baseCurrency]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get distance between two locations (simplified)
     */
    public function calculateDistance($fromCountry, $fromState, $toCountry, $toState) {
        // This is a simplified distance calculation
        // In a real application, you would use actual coordinates and distance APIs
        
        if ($fromCountry === $toCountry) {
            if ($fromState === $toState) {
                return 50; // Same state/province
            }
            return 500; // Same country, different state
        }
        
        // Different countries - simplified continental distances
        $continentalDistances = [
            'USA-CAN' => 1000,
            'USA-MEX' => 1500,
            'USA-GBR' => 5000,
            'USA-EUR' => 6000,
            'USA-JPN' => 8000,
            'USA-AUS' => 12000,
        ];
        
        $key = $fromCountry . '-' . $toCountry;
        $reverseKey = $toCountry . '-' . $fromCountry;
        
        return $continentalDistances[$key] ?? $continentalDistances[$reverseKey] ?? 8000;
    }
    
    /**
     * Format address for display
     */
    public function formatAddress($address) {
        $formatted = [];
        
        if (!empty($address['address_line1'])) {
            $formatted[] = $address['address_line1'];
        }
        
        if (!empty($address['address_line2'])) {
            $formatted[] = $address['address_line2'];
        }
        
        $cityStateZip = [];
        if (!empty($address['city'])) {
            $cityStateZip[] = $address['city'];
        }
        
        if (!empty($address['state'])) {
            $cityStateZip[] = $address['state'];
        }
        
        if (!empty($address['postal_code'])) {
            $cityStateZip[] = $address['postal_code'];
        }
        
        if (!empty($cityStateZip)) {
            $formatted[] = implode(', ', $cityStateZip);
        }
        
        if (!empty($address['country'])) {
            $formatted[] = $address['country'];
        }
        
        return implode("\n", $formatted);
    }
}
?>
