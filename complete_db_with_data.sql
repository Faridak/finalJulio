-- VentDepot Complete Database with Dummy Data
-- Database: finalJulio

CREATE DATABASE IF NOT EXISTS finalJulio;
USE finalJulio;

-- Drop existing tables if they exist
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS payment_methods;
DROP TABLE IF EXISTS shipping_rates;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

-- Create Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- Hashed with password_hash()
    role ENUM('customer', 'merchant', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Products Table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(500),
    stock INT DEFAULT 0,
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES users(id)
);

-- Create Orders Table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT,
    payment_method VARCHAR(50),
    shipping_cost DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create Shipping Rates Table
CREATE TABLE shipping_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origin VARCHAR(100),
    destination VARCHAR(100),
    cost DECIMAL(10,2)
);

-- Create Payment Methods Table
CREATE TABLE payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    provider VARCHAR(20) NOT NULL, -- "Stripe", "PayPal"
    token VARCHAR(255), -- Encrypted token (not stored)
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create Order Items Table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL, -- Price at time of order
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Create User Profiles Table
CREATE TABLE user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    date_of_birth DATE,
    profile_image VARCHAR(500),
    bio TEXT,
    preferences JSON, -- Store customer preferences as JSON
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create Shipping Addresses Table
CREATE TABLE shipping_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address_name VARCHAR(100), -- e.g., "Home", "Work", "Mom's House"
    recipient_name VARCHAR(200),
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'United States',
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create Banking Details Table (encrypted in real app)
CREATE TABLE banking_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_type ENUM('checking', 'savings', 'business') DEFAULT 'checking',
    bank_name VARCHAR(100),
    account_holder_name VARCHAR(200),
    account_number_encrypted VARCHAR(255), -- Last 4 digits only in real app
    routing_number_encrypted VARCHAR(255),
    is_verified BOOLEAN DEFAULT FALSE,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create Customer Support Messages Table
CREATE TABLE support_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ticket_id VARCHAR(50) UNIQUE NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    category ENUM('order', 'product', 'payment', 'shipping', 'account', 'other') DEFAULT 'other',
    admin_response TEXT,
    admin_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id)
);

-- Create Customer Preferences Table
CREATE TABLE customer_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    favorite_categories JSON,
    email_notifications BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT FALSE,
    marketing_emails BOOLEAN DEFAULT TRUE,
    order_updates BOOLEAN DEFAULT TRUE,
    newsletter BOOLEAN DEFAULT FALSE,
    language VARCHAR(10) DEFAULT 'en',
    currency VARCHAR(10) DEFAULT 'USD',
    timezone VARCHAR(50) DEFAULT 'America/New_York',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert Demo Users (password for all: password123)
INSERT INTO users (email, password, role) VALUES
('admin@ventdepot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('merchant1@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant'),
('merchant2@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant'),
('customer1@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer'),
('customer2@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer'),
('customer3@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer'),
('techstore@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant'),
('fashionhub@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant'),
('homegoods@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant'),
('sportsworld@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant');

-- Insert Sample Products
INSERT INTO products (merchant_id, name, description, price, image_url, stock, category) VALUES
-- Electronics (Merchant 2 - merchant1@demo.com)
(2, 'Wireless Bluetooth Headphones', 'Premium noise-cancelling wireless headphones with 30-hour battery life. Perfect for music lovers and professionals who demand high-quality audio.', 89.99, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400', 25, 'Electronics'),
(2, 'Smart Fitness Watch', 'Advanced fitness tracker with heart rate monitoring, GPS tracking, and smartphone connectivity. Monitor your health and fitness goals.', 199.99, 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400', 15, 'Electronics'),
(2, 'Wireless Phone Charger', 'Fast wireless charging pad compatible with all Qi-enabled devices. Sleek design with LED indicators.', 34.99, 'https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=400', 40, 'Electronics'),
(7, 'Bluetooth Portable Speaker', 'Compact Bluetooth speaker with powerful sound and waterproof design. Perfect for outdoor activities and travel.', 79.99, 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=400', 22, 'Electronics'),
(7, 'Wireless Gaming Mouse', 'High-precision wireless gaming mouse with customizable RGB lighting and programmable buttons for serious gamers.', 89.99, 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=400', 19, 'Electronics'),
(7, 'Wireless Earbuds Pro', 'True wireless earbuds with active noise cancellation and premium sound quality. Includes charging case.', 159.99, 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=400', 21, 'Electronics'),

-- Clothing (Merchant 3 - merchant2@demo.com)
(3, 'Organic Cotton T-Shirt', 'Comfortable and sustainable organic cotton t-shirt available in multiple colors. Perfect for casual wear and everyday comfort.', 24.99, 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400', 50, 'Clothing'),
(3, 'Denim Jacket Classic', 'Timeless denim jacket made from high-quality cotton. A wardrobe essential that never goes out of style.', 69.99, 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=400', 28, 'Clothing'),
(8, 'Silk Scarf Luxury', 'Elegant silk scarf with beautiful patterns. Perfect accessory to elevate any outfit for special occasions.', 79.99, 'https://images.unsplash.com/photo-1590736969955-71cc94901144?w=400', 14, 'Clothing'),
(8, 'Winter Wool Coat', 'Premium wool coat for cold weather. Stylish and warm with classic design that suits any professional setting.', 199.99, 'https://images.unsplash.com/photo-1544966503-7cc5ac882d5f?w=400', 12, 'Clothing'),

-- Home & Garden (Merchant 9 - homegoods@demo.com)
(9, 'Ceramic Coffee Mug Set', 'Set of 4 handcrafted ceramic coffee mugs. Perfect for your morning coffee routine or tea time with friends.', 39.99, 'https://images.unsplash.com/photo-1514228742587-6b1558fcf93a?w=400', 30, 'Home'),
(9, 'Scented Candle Collection', 'Set of 3 premium scented candles with relaxing fragrances. Create a cozy atmosphere in any room.', 45.99, 'https://images.unsplash.com/photo-1602874801006-e26d3d17d0a5?w=400', 18, 'Home'),
(9, 'Essential Oil Diffuser', 'Ultrasonic essential oil diffuser with LED lights and timer settings. Transform your space into a relaxing sanctuary.', 54.99, 'https://images.unsplash.com/photo-1608571423902-eed4a5ad8108?w=400', 16, 'Home'),
(9, 'Bamboo Cutting Board Set', 'Set of 3 bamboo cutting boards in different sizes. Eco-friendly and durable for all your kitchen needs.', 34.99, 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=400', 26, 'Home'),

-- Sports & Fitness (Merchant 10 - sportsworld@demo.com)
(10, 'Yoga Mat Premium', 'Non-slip premium yoga mat with extra cushioning. Ideal for yoga, pilates, and home workout routines.', 49.99, 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=400', 20, 'Sports'),
(10, 'Stainless Steel Water Bottle', 'Insulated stainless steel water bottle that keeps drinks cold for 24 hours or hot for 12 hours.', 29.99, 'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=400', 35, 'Sports'),
(10, 'Running Shoes Pro', 'Professional running shoes with advanced cushioning and breathable mesh upper. Perfect for serious runners.', 149.99, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400', 24, 'Sports'),
(10, 'Hiking Backpack 40L', 'Durable hiking backpack with multiple compartments and hydration system compatibility for outdoor adventures.', 119.99, 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400', 13, 'Sports'),

-- Accessories
(2, 'Leather Laptop Bag', 'Premium leather laptop bag with multiple compartments. Professional and durable for business use.', 129.99, 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400', 12, 'Accessories'),

-- Health & Beauty
(8, 'Skincare Gift Set', 'Complete skincare gift set with cleanser, moisturizer, and serum. Perfect for daily skincare routine.', 89.99, 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=400', 17, 'Beauty'),
(10, 'Plant-Based Protein Powder', 'Organic plant-based protein powder with vanilla flavor. Perfect for post-workout nutrition and muscle recovery.', 39.99, 'https://images.unsplash.com/photo-1593095948071-474c5cc2989d?w=400', 32, 'Health');

-- Insert Sample Orders
INSERT INTO orders (user_id, total, status, shipping_address, payment_method, shipping_cost, created_at) VALUES
(4, 119.98, 'delivered', '123 Main Street\nAnytown, CA 90210\nUnited States', 'Stripe', 5.99, '2024-01-15 10:30:00'),
(4, 89.99, 'shipped', '123 Main Street\nAnytown, CA 90210\nUnited States', 'PayPal', 5.99, '2024-01-20 14:15:00'),
(5, 249.97, 'pending', '456 Oak Avenue\nSpringfield, IL 62701\nUnited States', 'Stripe', 8.99, '2024-01-25 09:45:00'),
(5, 159.99, 'delivered', '456 Oak Avenue\nSpringfield, IL 62701\nUnited States', 'Cash', 0.00, '2024-01-18 16:20:00'),
(6, 79.99, 'shipped', '789 Pine Road\nAustin, TX 73301\nUnited States', 'PayPal', 6.99, '2024-01-22 11:10:00'),
(6, 199.98, 'delivered', '789 Pine Road\nAustin, TX 73301\nUnited States', 'Stripe', 7.99, '2024-01-12 13:45:00'),
(4, 54.99, 'pending', '123 Main Street\nAnytown, CA 90210\nUnited States', 'Stripe', 4.99, '2024-01-26 08:30:00'),
(5, 129.99, 'cancelled', '456 Oak Avenue\nSpringfield, IL 62701\nUnited States', 'PayPal', 5.99, '2024-01-14 15:20:00');

-- Insert Order Items
INSERT INTO order_items (order_id, product_id, quantity, price) VALUES
-- Order 1 items
(1, 1, 1, 89.99),
(1, 11, 1, 24.99),
-- Order 2 items  
(2, 1, 1, 89.99),
-- Order 3 items
(3, 2, 1, 199.99),
(3, 3, 1, 34.99),
(3, 15, 1, 49.99),
-- Order 4 items
(4, 6, 1, 159.99),
-- Order 5 items
(5, 4, 1, 79.99),
-- Order 6 items
(6, 2, 1, 199.99),
-- Order 7 items
(7, 13, 1, 54.99),
-- Order 8 items (cancelled)
(8, 19, 1, 129.99);

-- Insert Shipping Rates
INSERT INTO shipping_rates (origin, destination, cost) VALUES
('Warehouse A', 'Zone 1', 5.99),
('Warehouse A', 'Zone 2', 8.99),
('Warehouse A', 'Zone 3', 12.99),
('Warehouse B', 'Zone 1', 6.99),
('Warehouse B', 'Zone 2', 9.99),
('Warehouse B', 'Zone 3', 13.99),
('Local Pickup', 'Same City', 0.00),
('Express Shipping', 'Nationwide', 19.99);

-- Insert Payment Methods
INSERT INTO payment_methods (user_id, provider, token) VALUES
(4, 'Stripe', 'tok_visa_encrypted_demo'),
(4, 'PayPal', 'paypal_token_encrypted_demo'),
(5, 'Stripe', 'tok_mastercard_encrypted_demo'),
(6, 'PayPal', 'paypal_business_token_demo');

-- Insert User Profiles
INSERT INTO user_profiles (user_id, first_name, last_name, phone, date_of_birth, bio, preferences) VALUES
(1, 'Admin', 'User', '+1-555-0001', '1985-01-15', 'Platform administrator with full access to all systems.', '{"theme": "dark", "dashboard_layout": "compact"}'),
(2, 'John', 'Smith', '+1-555-0102', '1988-03-22', 'Electronics enthusiast and tech merchant. Specializing in cutting-edge gadgets and accessories.', '{"business_hours": "9AM-6PM", "auto_respond": true}'),
(3, 'Sarah', 'Johnson', '+1-555-0103', '1990-07-18', 'Fashion lover and clothing merchant. Curating sustainable and stylish apparel.', '{"business_hours": "10AM-8PM", "specialty": "sustainable_fashion"}'),
(4, 'Mike', 'Davis', '+1-555-0204', '1992-11-05', 'Tech professional and avid online shopper. Love discovering new gadgets and tools.', '{"favorite_categories": ["Electronics", "Sports"], "newsletter": true}'),
(5, 'Emily', 'Wilson', '+1-555-0205', '1987-09-12', 'Home decor enthusiast and fitness lover. Always looking for quality products.', '{"favorite_categories": ["Home", "Sports", "Beauty"], "budget_alerts": true}'),
(6, 'David', 'Brown', '+1-555-0206', '1995-04-30', 'Outdoor adventure seeker and sports equipment collector.', '{"favorite_categories": ["Sports", "Electronics"], "deal_notifications": true}'),
(7, 'Tech', 'Store', '+1-555-0307', '1985-12-01', 'Premium technology retailer with 10+ years experience in consumer electronics.', '{"business_type": "electronics", "warranty_offered": true}'),
(8, 'Fashion', 'Hub', '+1-555-0308', '1989-06-15', 'Trendy fashion boutique offering the latest styles and accessories.', '{"business_type": "fashion", "size_guide": true}'),
(9, 'Home', 'Goods', '+1-555-0309', '1983-08-20', 'Quality home goods and decor specialist. Making houses into homes.', '{"business_type": "home_decor", "custom_orders": true}'),
(10, 'Sports', 'World', '+1-555-0310', '1991-02-28', 'Athletic equipment and fitness gear expert. Helping customers achieve their fitness goals.', '{"business_type": "sports", "expert_advice": true}');

-- Insert Shipping Addresses
INSERT INTO shipping_addresses (user_id, address_name, recipient_name, address_line1, address_line2, city, state, postal_code, country, is_default) VALUES
(4, 'Home', 'Mike Davis', '123 Main Street', 'Apt 4B', 'Anytown', 'CA', '90210', 'United States', TRUE),
(4, 'Work', 'Mike Davis', '456 Business Blvd', 'Suite 200', 'Anytown', 'CA', '90211', 'United States', FALSE),
(5, 'Home', 'Emily Wilson', '456 Oak Avenue', '', 'Springfield', 'IL', '62701', 'United States', TRUE),
(5, 'Parents House', 'Emily Wilson c/o Wilson Family', '789 Family Lane', '', 'Springfield', 'IL', '62702', 'United States', FALSE),
(6, 'Home', 'David Brown', '789 Pine Road', 'Unit 12', 'Austin', 'TX', '73301', 'United States', TRUE),
(6, 'Office', 'David Brown', '321 Corporate Dr', 'Floor 5', 'Austin', 'TX', '73302', 'United States', FALSE),
(2, 'Business Address', 'John Smith - Tech Merchant', '100 Commerce St', 'Warehouse A', 'San Jose', 'CA', '95110', 'United States', TRUE),
(3, 'Store Location', 'Sarah Johnson Fashion', '200 Fashion Ave', '', 'New York', 'NY', '10001', 'United States', TRUE);

-- Insert Banking Details (Demo - encrypted in real app)
INSERT INTO banking_details (user_id, account_type, bank_name, account_holder_name, account_number_encrypted, routing_number_encrypted, is_verified, is_default) VALUES
(2, 'business', 'Chase Business Bank', 'John Smith', '****1234', '****5678', TRUE, TRUE),
(3, 'business', 'Bank of America Business', 'Sarah Johnson', '****5678', '****9012', TRUE, TRUE),
(4, 'checking', 'Wells Fargo', 'Mike Davis', '****9876', '****3456', TRUE, TRUE),
(5, 'savings', 'Capital One', 'Emily Wilson', '****5432', '****7890', TRUE, TRUE),
(6, 'checking', 'Chase Bank', 'David Brown', '****1111', '****2222', TRUE, TRUE),
(7, 'business', 'Silicon Valley Bank', 'Tech Store LLC', '****3333', '****4444', TRUE, TRUE),
(8, 'business', 'Fashion Credit Union', 'Fashion Hub Inc', '****5555', '****6666', TRUE, TRUE);

-- Insert Customer Support Messages
INSERT INTO support_messages (user_id, ticket_id, subject, message, status, priority, category, admin_response, admin_id) VALUES
(4, 'TKT-2024-001', 'Order Delivery Issue', 'My order #000001 was supposed to arrive yesterday but I haven\'t received it yet. Can you please check the tracking status?', 'resolved', 'medium', 'shipping', 'Hi Mike, I checked with our shipping partner and your package was delivered to your building\'s front desk. Please check with your building management. If you still can\'t locate it, we\'ll send a replacement immediately.', 1),
(5, 'TKT-2024-002', 'Product Quality Concern', 'The yoga mat I received has a strong chemical smell and seems different from what was advertised. I\'d like to return it.', 'in_progress', 'high', 'product', 'Hi Emily, I\'m sorry to hear about the quality issue. We\'re arranging a return label for you and will process a full refund once we receive the item. We\'re also investigating this with our supplier.', 1),
(6, 'TKT-2024-003', 'Payment Processing Error', 'I was charged twice for my recent order. My credit card shows two transactions but I only placed one order.', 'resolved', 'high', 'payment', 'Hi David, I found the duplicate charge and have processed a refund for the extra amount. You should see it back on your card within 3-5 business days. Sorry for the inconvenience!', 1),
(4, 'TKT-2024-004', 'Account Login Problems', 'I can\'t seem to reset my password. The reset email isn\'t coming through to my inbox.', 'open', 'medium', 'account', NULL, NULL),
(5, 'TKT-2024-005', 'Product Recommendation Request', 'I\'m looking for a good wireless speaker for outdoor use. Can you recommend something from your electronics section?', 'open', 'low', 'product', NULL, NULL);

-- Insert Customer Preferences
INSERT INTO customer_preferences (user_id, favorite_categories, email_notifications, sms_notifications, marketing_emails, order_updates, newsletter, language, currency, timezone) VALUES
(4, '["Electronics", "Sports", "Accessories"]', TRUE, FALSE, TRUE, TRUE, TRUE, 'en', 'USD', 'America/Los_Angeles'),
(5, '["Home", "Beauty", "Sports"]', TRUE, TRUE, FALSE, TRUE, FALSE, 'en', 'USD', 'America/Chicago'),
(6, '["Sports", "Electronics", "Accessories"]', TRUE, FALSE, TRUE, TRUE, TRUE, 'en', 'USD', 'America/Chicago'),
(2, '["Electronics"]', TRUE, TRUE, TRUE, TRUE, FALSE, 'en', 'USD', 'America/Los_Angeles'),
(3, '["Clothing", "Beauty", "Accessories"]', TRUE, FALSE, TRUE, TRUE, TRUE, 'en', 'USD', 'America/New_York');

-- Create some indexes for better performance
CREATE INDEX idx_products_merchant ON products(merchant_id);
CREATE INDEX idx_products_category ON products(category);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created ON orders(created_at);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_order_items_product ON order_items(product_id);
CREATE INDEX idx_user_profiles_user ON user_profiles(user_id);
CREATE INDEX idx_shipping_addresses_user ON shipping_addresses(user_id);
CREATE INDEX idx_banking_details_user ON banking_details(user_id);
CREATE INDEX idx_support_messages_user ON support_messages(user_id);
CREATE INDEX idx_support_messages_status ON support_messages(status);
CREATE INDEX idx_customer_preferences_user ON customer_preferences(user_id);

-- Display summary of inserted data
SELECT 'Database Setup Complete!' as Status;
SELECT 
    'Users' as Table_Name,
    COUNT(*) as Record_Count,
    GROUP_CONCAT(DISTINCT role) as Roles
FROM users
UNION ALL
SELECT 
    'Products' as Table_Name,
    COUNT(*) as Record_Count,
    GROUP_CONCAT(DISTINCT category) as Categories
FROM products
UNION ALL
SELECT 
    'Orders' as Table_Name,
    COUNT(*) as Record_Count,
    GROUP_CONCAT(DISTINCT status) as Statuses
FROM orders;

-- Show login credentials for testing
SELECT 
    'LOGIN CREDENTIALS FOR TESTING' as Info,
    '' as Email,
    '' as Password,
    '' as Role
UNION ALL
SELECT 
    '================================' as Info,
    '' as Email,
    '' as Password,
    '' as Role
UNION ALL
SELECT 
    'Admin Access:' as Info,
    'admin@ventdepot.com' as Email,
    'password123' as Password,
    'admin' as Role
UNION ALL
SELECT 
    'Merchant Access:' as Info,
    'merchant1@demo.com' as Email,
    'password123' as Password,
    'merchant' as Role
UNION ALL
SELECT 
    'Customer Access:' as Info,
    'customer1@demo.com' as Email,
    'password123' as Password,
    'customer' as Role;
