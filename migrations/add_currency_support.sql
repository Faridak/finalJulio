-- Migration script to add multi-currency support to the database

USE finalJulio;

-- Add currency columns to orders table
ALTER TABLE orders 
ADD COLUMN currency VARCHAR(3) DEFAULT 'USD' AFTER shipping_cost,
ADD COLUMN exchange_rate DECIMAL(12,6) DEFAULT 1.000000 AFTER currency,
ADD COLUMN fx_gain_loss DECIMAL(10,2) DEFAULT 0.00 AFTER exchange_rate;

-- Add currency column to order_items table
ALTER TABLE order_items 
ADD COLUMN currency VARCHAR(3) DEFAULT 'USD' AFTER price;

-- Update existing order items with currency
UPDATE order_items SET currency = 'USD';

-- Update existing orders with currency
UPDATE orders SET currency = 'USD', exchange_rate = 1.000000;

-- Add indexes for better performance
ALTER TABLE orders ADD INDEX idx_orders_currency (currency);
ALTER TABLE order_items ADD INDEX idx_order_items_currency (currency);

-- Create a table for currency preferences per user
CREATE TABLE IF NOT EXISTS user_currency_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preferred_currency VARCHAR(3) DEFAULT 'USD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_currency (user_id)
);

-- Insert default currency preferences for existing users
INSERT IGNORE INTO user_currency_preferences (user_id, preferred_currency)
SELECT id, 'USD' FROM users;

-- Create a table for historical exchange rates
CREATE TABLE IF NOT EXISTS historical_exchange_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_currency VARCHAR(3) NOT NULL,
    to_currency VARCHAR(3) NOT NULL,
    rate DECIMAL(12,6) NOT NULL,
    rate_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_historical_rate (from_currency, to_currency, rate_date)
);

-- Add more comprehensive currency data to currency_rates table
INSERT INTO currency_rates (from_currency, to_currency, rate) VALUES
('USD', 'AED', 3.6725),
('USD', 'ARS', 98.5000),
('USD', 'AUD', 1.4500),
('USD', 'BGN', 1.6600),
('USD', 'BRL', 5.2000),
('USD', 'CAD', 1.3500),
('USD', 'CHF', 0.9200),
('USD', 'CNY', 6.4500),
('USD', 'CZK', 21.5000),
('USD', 'DKK', 6.3500),
('USD', 'EGP', 15.7000),
('USD', 'EUR', 0.8500),
('USD', 'GBP', 0.7300),
('USD', 'HKD', 7.8000),
('USD', 'HRK', 6.4000),
('USD', 'HUF', 295.0000),
('USD', 'IDR', 14250.0000),
('USD', 'ILS', 3.2500),
('USD', 'INR', 74.5000),
('USD', 'JPY', 110.0000),
('USD', 'KES', 108.0000),
('USD', 'KRW', 1180.0000),
('USD', 'MXN', 17.5000),
('USD', 'MYR', 4.1500),
('USD', 'NGN', 410.0000),
('USD', 'NOK', 8.5000),
('USD', 'NZD', 1.4200),
('USD', 'PHP', 50.0000),
('USD', 'PLN', 3.8500),
('USD', 'RON', 4.1500),
('USD', 'RUB', 73.5000),
('USD', 'SAR', 3.7500),
('USD', 'SEK', 8.7500),
('USD', 'SGD', 1.3500),
('USD', 'THB', 33.0000),
('USD', 'TRY', 8.5000),
('USD', 'TWD', 28.5000),
('USD', 'UAH', 27.0000),
('USD', 'VND', 23000.0000),
('USD', 'ZAR', 14.5000)
ON DUPLICATE KEY UPDATE rate = VALUES(rate);

-- Add a stored procedure for currency conversion
DELIMITER //

CREATE PROCEDURE ConvertCurrency(
    IN amount DECIMAL(10,2),
    IN from_currency VARCHAR(3),
    IN to_currency VARCHAR(3),
    OUT converted_amount DECIMAL(10,2)
)
BEGIN
    DECLARE exchange_rate DECIMAL(12,6);
    
    -- If same currency, return original amount
    IF from_currency = to_currency THEN
        SET converted_amount = amount;
    ELSE
        -- Get exchange rate
        SELECT rate INTO exchange_rate
        FROM currency_rates
        WHERE from_currency = from_currency AND to_currency = to_currency
        LIMIT 1;
        
        -- If rate found, convert amount
        IF exchange_rate IS NOT NULL THEN
            SET converted_amount = ROUND(amount * exchange_rate, 2);
        ELSE
            -- Try reverse rate
            SELECT 1/rate INTO exchange_rate
            FROM currency_rates
            WHERE from_currency = to_currency AND to_currency = from_currency
            LIMIT 1;
            
            IF exchange_rate IS NOT NULL THEN
                SET converted_amount = ROUND(amount * exchange_rate, 2);
            ELSE
                -- Return NULL if no rate found
                SET converted_amount = NULL;
            END IF;
        END IF;
    END IF;
END//

DELIMITER ;

-- Add a function to calculate FX gain/loss
DELIMITER //

CREATE FUNCTION CalculateFXGainLoss(
    order_id INT
) RETURNS DECIMAL(10,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE original_rate DECIMAL(12,6);
    DECLARE current_rate DECIMAL(12,6);
    DECLARE order_total DECIMAL(10,2);
    DECLARE original_usd_value DECIMAL(10,2);
    DECLARE current_usd_value DECIMAL(10,2);
    DECLARE fx_gain_loss DECIMAL(10,2);
    
    -- Get order details
    SELECT exchange_rate, total INTO original_rate, order_total
    FROM orders
    WHERE id = order_id;
    
    -- If order not found or already in USD, return 0
    IF original_rate IS NULL OR original_rate = 1.000000 THEN
        RETURN 0.00;
    END IF;
    
    -- Get current exchange rate
    SELECT rate INTO current_rate
    FROM currency_rates
    WHERE from_currency = 'USD' AND to_currency = (
        SELECT currency FROM orders WHERE id = order_id
    )
    LIMIT 1;
    
    -- If current rate not found, return 0
    IF current_rate IS NULL THEN
        RETURN 0.00;
    END IF;
    
    -- Calculate FX gain/loss
    SET original_usd_value = order_total / original_rate;
    SET current_usd_value = order_total / current_rate;
    SET fx_gain_loss = current_usd_value - original_usd_value;
    
    RETURN ROUND(fx_gain_loss, 2);
END//

DELIMITER ;

-- Create a view for orders with currency information
CREATE OR REPLACE VIEW orders_with_currency AS
SELECT 
    o.*,
    cc.formatCurrency AS formatted_total,
    cr.rate as current_exchange_rate,
    CalculateFXGainLoss(o.id) as current_fx_gain_loss
FROM orders o
LEFT JOIN currency_rates cr ON cr.from_currency = 'USD' AND cr.to_currency = o.currency;

SELECT 'Multi-currency support migration completed successfully!' as migration_status;