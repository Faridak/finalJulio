<?php
/**
 * Currency Converter Class
 * Handles currency conversion, exchange rate updates, and foreign exchange calculations
 */

class CurrencyConverter {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get exchange rate between two currencies
     * 
     * @param string $fromCurrency Base currency code
     * @param string $toCurrency Target currency code
     * @return float Exchange rate or false if not found
     */
    public function getExchangeRate($fromCurrency, $toCurrency) {
        // If same currency, return 1
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }
        
        // Try direct rate first
        $stmt = $this->pdo->prepare("
            SELECT rate FROM currency_rates 
            WHERE from_currency = ? AND to_currency = ?
        ");
        $stmt->execute([$fromCurrency, $toCurrency]);
        $rate = $stmt->fetchColumn();
        
        if ($rate) {
            return $rate;
        }
        
        // Try reverse rate
        $stmt = $this->pdo->prepare("
            SELECT rate FROM currency_rates 
            WHERE from_currency = ? AND to_currency = ?
        ");
        $stmt->execute([$toCurrency, $fromCurrency]);
        $reverseRate = $stmt->fetchColumn();
        
        if ($reverseRate) {
            return 1 / $reverseRate;
        }
        
        // Try through USD as intermediary
        $stmt = $this->pdo->prepare("
            SELECT rate FROM currency_rates 
            WHERE from_currency = 'USD' AND to_currency = ?
        ");
        $stmt->execute([$toCurrency]);
        $toRate = $stmt->fetchColumn();
        
        if ($toRate) {
            $stmt = $this->pdo->prepare("
                SELECT rate FROM currency_rates 
                WHERE from_currency = 'USD' AND to_currency = ?
            ");
            $stmt->execute([$fromCurrency]);
            $fromRate = $stmt->fetchColumn();
            
            if ($fromRate) {
                return $toRate / $fromRate;
            }
        }
        
        return false;
    }
    
    /**
     * Convert amount from one currency to another
     * 
     * @param float $amount Amount to convert
     * @param string $fromCurrency Source currency code
     * @param string $toCurrency Target currency code
     * @return float Converted amount or false if conversion failed
     */
    public function convert($amount, $fromCurrency, $toCurrency) {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }
        
        $rate = $this->getExchangeRate($fromCurrency, $toCurrency);
        
        if ($rate === false) {
            return false;
        }
        
        return round($amount * $rate, 2);
    }
    
    /**
     * Update exchange rates from an external API
     * In a real implementation, this would connect to a service like Fixer.io or Open Exchange Rates
     * 
     * @return bool Success status
     */
    public function updateExchangeRates() {
        // This is a simplified implementation
        // In a real application, you would connect to an external API
        
        try {
            // Example rates - in a real app, these would come from an API
            $rates = [
                ['from' => 'USD', 'to' => 'EUR', 'rate' => 0.85],
                ['from' => 'USD', 'to' => 'GBP', 'rate' => 0.73],
                ['from' => 'USD', 'to' => 'JPY', 'rate' => 110.00],
                ['from' => 'USD', 'to' => 'CAD', 'rate' => 1.25],
                ['from' => 'USD', 'to' => 'AUD', 'rate' => 1.35],
                ['from' => 'USD', 'to' => 'CHF', 'rate' => 0.92],
                ['from' => 'USD', 'to' => 'CNY', 'rate' => 6.45],
                ['from' => 'USD', 'to' => 'SEK', 'rate' => 8.75],
                ['from' => 'USD', 'to' => 'NZD', 'rate' => 1.42],
                ['from' => 'USD', 'to' => 'MXN', 'rate' => 20.00],
                ['from' => 'USD', 'to' => 'SGD', 'rate' => 1.35],
                ['from' => 'USD', 'to' => 'HKD', 'rate' => 7.78],
                ['from' => 'USD', 'to' => 'NOK', 'rate' => 8.50],
                ['from' => 'USD', 'to' => 'KRW', 'rate' => 1180.00],
                ['from' => 'USD', 'to' => 'TRY', 'rate' => 8.50],
                ['from' => 'USD', 'to' => 'RUB', 'rate' => 73.50],
                ['from' => 'USD', 'to' => 'INR', 'rate' => 74.50],
                ['from' => 'USD', 'to' => 'BRL', 'rate' => 5.20],
                ['from' => 'USD', 'to' => 'ZAR', 'rate' => 14.50]
            ];
            
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
            error_log("Error updating exchange rates: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate foreign exchange gain/loss for an order
     * 
     * @param int $orderId Order ID
     * @return float FX gain/loss amount
     */
    public function calculateFXGainLoss($orderId) {
        try {
            // Get order details
            $stmt = $this->pdo->prepare("
                SELECT currency, exchange_rate, total 
                FROM orders 
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if (!$order) {
                return 0;
            }
            
            // Get current exchange rate
            $currentRate = $this->getExchangeRate($order['currency'], 'USD');
            
            if ($currentRate === false) {
                return 0;
            }
            
            // Calculate gain/loss
            $originalUSDValue = $order['total'] / $order['exchange_rate'];
            $currentUSDValue = $order['total'] / $currentRate;
            $fxGainLoss = $currentUSDValue - $originalUSDValue;
            
            // Update order with FX gain/loss
            $stmt = $this->pdo->prepare("
                UPDATE orders 
                SET fx_gain_loss = ?
                WHERE id = ?
            ");
            $stmt->execute([round($fxGainLoss, 2), $orderId]);
            
            return round($fxGainLoss, 2);
        } catch (Exception $e) {
            error_log("Error calculating FX gain/loss: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get all available currencies
     * 
     * @return array List of currency codes
     */
    public function getAvailableCurrencies() {
        $stmt = $this->pdo->query("
            SELECT DISTINCT from_currency as currency FROM currency_rates
            UNION
            SELECT DISTINCT to_currency as currency FROM currency_rates
            ORDER BY currency
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Format currency amount for display
     * 
     * @param float $amount Amount to format
     * @param string $currency Currency code
     * @return string Formatted currency string
     */
    public function formatCurrency($amount, $currency) {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'Fr',
            'CNY' => '¥',
            'SEK' => 'kr',
            'NZD' => 'NZ$',
            'MXN' => '$',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'NOK' => 'kr',
            'KRW' => '₩',
            'TRY' => '₺',
            'RUB' => '₽',
            'INR' => '₹',
            'BRL' => 'R$',
            'ZAR' => 'R'
        ];
        
        $symbol = $symbols[$currency] ?? $currency;
        
        // For JPY, we don't use decimal places
        if ($currency === 'JPY') {
            return $symbol . number_format($amount, 0);
        }
        
        return $symbol . number_format($amount, 2);
    }
}
?>