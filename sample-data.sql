-- Sample data for VentDepot

-- Insert demo users
INSERT INTO users (email, password, role) VALUES
('customer@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer'), -- password123
('merchant@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant'), -- password123
('admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'); -- password123

-- Insert sample products
INSERT INTO products (merchant_id, name, description, price, image_url, stock, category) VALUES
(2, 'Wireless Bluetooth Headphones', 'High-quality wireless headphones with noise cancellation and 30-hour battery life. Perfect for music lovers and professionals.', 89.99, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400', 25, 'Electronics'),
(2, 'Smart Fitness Watch', 'Advanced fitness tracker with heart rate monitoring, GPS, and smartphone connectivity. Track your workouts and health metrics.', 199.99, 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400', 15, 'Electronics'),
(2, 'Organic Cotton T-Shirt', 'Comfortable and sustainable organic cotton t-shirt available in multiple colors. Perfect for casual wear.', 24.99, 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400', 50, 'Clothing'),
(2, 'Leather Laptop Bag', 'Premium leather laptop bag with multiple compartments. Professional and durable for business use.', 129.99, 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400', 12, 'Accessories'),
(2, 'Ceramic Coffee Mug Set', 'Set of 4 handcrafted ceramic coffee mugs. Perfect for your morning coffee or tea routine.', 39.99, 'https://images.unsplash.com/photo-1514228742587-6b1558fcf93a?w=400', 30, 'Home'),
(2, 'Yoga Mat Premium', 'Non-slip premium yoga mat with extra cushioning. Ideal for yoga, pilates, and home workouts.', 49.99, 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=400', 20, 'Sports'),
(2, 'Wireless Phone Charger', 'Fast wireless charging pad compatible with all Qi-enabled devices. Sleek and efficient design.', 34.99, 'https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=400', 40, 'Electronics'),
(2, 'Stainless Steel Water Bottle', 'Insulated stainless steel water bottle that keeps drinks cold for 24 hours or hot for 12 hours.', 29.99, 'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=400', 35, 'Sports'),
(2, 'Scented Candle Collection', 'Set of 3 premium scented candles with relaxing fragrances. Perfect for creating a cozy atmosphere.', 45.99, 'https://images.unsplash.com/photo-1602874801006-e26d3d17d0a5?w=400', 18, 'Home'),
(2, 'Bluetooth Portable Speaker', 'Compact Bluetooth speaker with powerful sound and waterproof design. Perfect for outdoor activities.', 79.99, 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=400', 22, 'Electronics'),
(2, 'Denim Jacket Classic', 'Timeless denim jacket made from high-quality cotton. A wardrobe essential for any season.', 69.99, 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=400', 28, 'Clothing'),
(2, 'Essential Oil Diffuser', 'Ultrasonic essential oil diffuser with LED lights and timer settings. Create a relaxing environment at home.', 54.99, 'https://images.unsplash.com/photo-1608571423902-eed4a5ad8108?w=400', 16, 'Home'),
(2, 'Running Shoes Pro', 'Professional running shoes with advanced cushioning and breathable mesh upper. Perfect for serious runners.', 149.99, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400', 24, 'Sports'),
(2, 'Wireless Gaming Mouse', 'High-precision wireless gaming mouse with customizable RGB lighting and programmable buttons.', 89.99, 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=400', 19, 'Electronics'),
(2, 'Silk Scarf Luxury', 'Elegant silk scarf with beautiful patterns. Perfect accessory for any outfit.', 79.99, 'https://images.unsplash.com/photo-1590736969955-71cc94901144?w=400', 14, 'Accessories'),
(2, 'Plant-Based Protein Powder', 'Organic plant-based protein powder with vanilla flavor. Perfect for post-workout nutrition.', 39.99, 'https://images.unsplash.com/photo-1593095948071-474c5cc2989d?w=400', 32, 'Health'),
(2, 'Bamboo Cutting Board Set', 'Set of 3 bamboo cutting boards in different sizes. Eco-friendly and durable for kitchen use.', 34.99, 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=400', 26, 'Home'),
(2, 'Wireless Earbuds Pro', 'True wireless earbuds with active noise cancellation and premium sound quality.', 159.99, 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=400', 21, 'Electronics'),
(2, 'Hiking Backpack 40L', 'Durable hiking backpack with multiple compartments and hydration system compatibility.', 119.99, 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400', 13, 'Sports'),
(2, 'Skincare Gift Set', 'Complete skincare gift set with cleanser, moisturizer, and serum. Perfect for daily skincare routine.', 89.99, 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=400', 17, 'Beauty');

-- Insert sample shipping rates
INSERT INTO shipping_rates (origin, destination, cost) VALUES
('Warehouse A', 'Zone 1', 5.99),
('Warehouse A', 'Zone 2', 8.99),
('Warehouse A', 'Zone 3', 12.99),
('Warehouse B', 'Zone 1', 6.99),
('Warehouse B', 'Zone 2', 9.99),
('Warehouse B', 'Zone 3', 13.99);

-- Insert sample payment methods
INSERT INTO payment_methods (user_id, provider, token) VALUES
(1, 'Stripe', 'tok_visa_encrypted'),
(1, 'PayPal', 'paypal_token_encrypted');

-- Insert sample orders
INSERT INTO orders (user_id, total, status, shipping_address, payment_method, shipping_cost) VALUES
(1, 119.98, 'delivered', '123 Main St\nAnytown, ST 12345\nUnited States', 'Stripe', 5.99),
(1, 89.99, 'shipped', '123 Main St\nAnytown, ST 12345\nUnited States', 'PayPal', 5.99),
(1, 249.97, 'pending', '123 Main St\nAnytown, ST 12345\nUnited States', 'Stripe', 8.99);
