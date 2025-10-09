-- Fixed Enhanced VentDepot Database Schema
-- Complete marketplace features implementation with corrected TIMESTAMP fields

-- =====================================================
-- SECURITY TABLES
-- =====================================================

-- User Sessions Table for enhanced session management
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_sessions_user (user_id),
    INDEX idx_user_sessions_active (is_active, expires_at)
);

-- Two Factor Authentication
CREATE TABLE IF NOT EXISTS user_2fa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    secret_key VARCHAR(255) NOT NULL,
    is_enabled BOOLEAN DEFAULT FALSE,
    backup_codes JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enabled_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Password Reset Tokens
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reset_tokens_token (token),
    INDEX idx_reset_tokens_expires (expires_at)
);

-- Email Verification Tokens
CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email_tokens_token (token)
);

-- =====================================================
-- REVIEWS AND RATINGS SYSTEM
-- =====================================================

-- Product Reviews
CREATE TABLE IF NOT EXISTS product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT NULL, -- Link to verified purchase
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255),
    review_text TEXT,
    is_verified_purchase BOOLEAN DEFAULT FALSE,
    is_approved BOOLEAN DEFAULT TRUE,
    helpful_count INT DEFAULT 0,
    unhelpful_count INT DEFAULT 0,
    admin_response TEXT NULL,
    admin_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_product_review (user_id, product_id),
    INDEX idx_reviews_product (product_id),
    INDEX idx_reviews_rating (rating),
    INDEX idx_reviews_approved (is_approved)
);

-- Review Helpfulness Votes
CREATE TABLE IF NOT EXISTS review_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    user_id INT NOT NULL,
    vote_type ENUM('helpful', 'unhelpful') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES product_reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_review_vote (user_id, review_id)
);

-- Merchant Ratings (separate from product reviews)
CREATE TABLE IF NOT EXISTS merchant_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    customer_id INT NOT NULL,
    order_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    communication_rating TINYINT CHECK (communication_rating >= 1 AND communication_rating <= 5),
    shipping_speed_rating TINYINT CHECK (shipping_speed_rating >= 1 AND shipping_speed_rating <= 5),
    item_description_rating TINYINT CHECK (item_description_rating >= 1 AND item_description_rating <= 5),
    feedback_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    UNIQUE KEY unique_customer_merchant_order (customer_id, merchant_id, order_id)
);

-- =====================================================
-- MESSAGING SYSTEM
-- =====================================================

-- Conversations
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('buyer_seller', 'support', 'dispute') NOT NULL,
    subject VARCHAR(255),
    status ENUM('active', 'closed', 'archived') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conversations_type (type),
    INDEX idx_conversations_status (status)
);

-- Conversation Participants
CREATE TABLE IF NOT EXISTS conversation_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('buyer', 'seller', 'admin', 'support') NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_conversation_user (conversation_id, user_id)
);

-- Messages
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    message_text TEXT NOT NULL,
    message_type ENUM('text', 'image', 'file', 'system') DEFAULT 'text',
    attachment_url VARCHAR(500) NULL,
    attachment_type VARCHAR(50) NULL,
    attachment_size INT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_messages_conversation (conversation_id),
    INDEX idx_messages_sender (sender_id),
    INDEX idx_messages_created (created_at)
);

-- =====================================================
-- ENHANCED PRODUCT SYSTEM
-- =====================================================

-- Product Categories with hierarchical structure
CREATE TABLE IF NOT EXISTS product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    parent_id INT NULL,
    image_url VARCHAR(500),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    meta_title VARCHAR(255),
    meta_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES product_categories(id) ON DELETE SET NULL,
    INDEX idx_categories_parent (parent_id),
    INDEX idx_categories_slug (slug),
    INDEX idx_categories_active (is_active)
);

-- Product Variants (size, color, etc.)
CREATE TABLE IF NOT EXISTS product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    compare_price DECIMAL(10,2) NULL,
    cost_price DECIMAL(10,2) NULL,
    stock_quantity INT DEFAULT 0,
    weight_kg DECIMAL(8,3),
    dimensions_cm VARCHAR(50), -- "L x W x H"
    variant_options JSON, -- {"color": "red", "size": "large"}
    image_urls JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_variants_product (product_id),
    INDEX idx_variants_sku (sku),
    INDEX idx_variants_active (is_active)
);

-- Product Images
CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    variant_id INT NULL,
    image_url VARCHAR(500) NOT NULL,
    alt_text VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    INDEX idx_images_product (product_id),
    INDEX idx_images_variant (variant_id)
);

-- Product Attributes (specifications)
CREATE TABLE IF NOT EXISTS product_attributes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    attribute_name VARCHAR(100) NOT NULL,
    attribute_value TEXT NOT NULL,
    attribute_type ENUM('text', 'number', 'boolean', 'list') DEFAULT 'text',
    display_order INT DEFAULT 0,
    is_filterable BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_attributes_product (product_id),
    INDEX idx_attributes_filterable (is_filterable)
);

-- Wishlist
CREATE TABLE IF NOT EXISTS wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product_wishlist (user_id, product_id, variant_id)
);

-- Product Views/Analytics
CREATE TABLE IF NOT EXISTS product_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer VARCHAR(500),
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_views_product (product_id),
    INDEX idx_views_date (viewed_at)
);

-- =====================================================
-- ENHANCED ORDER SYSTEM
-- =====================================================

-- Order Status History
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') NOT NULL,
    notes TEXT,
    changed_by INT NULL, -- admin/merchant who changed status
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status_history_order (order_id)
);

-- Shipping Tracking
CREATE TABLE IF NOT EXISTS order_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    carrier VARCHAR(100),
    tracking_number VARCHAR(255),
    tracking_url VARCHAR(500),
    estimated_delivery DATE,
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    tracking_data JSON, -- Store API responses
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_tracking_order (order_id),
    INDEX idx_tracking_number (tracking_number)
);

-- Return/Refund Requests
CREATE TABLE IF NOT EXISTS return_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    reason ENUM('defective', 'wrong_item', 'not_as_described', 'changed_mind', 'other') NOT NULL,
    description TEXT,
    status ENUM('pending', 'approved', 'rejected', 'processing', 'completed') DEFAULT 'pending',
    refund_amount DECIMAL(10,2),
    admin_notes TEXT,
    processed_by INT NULL,
    images JSON, -- Array of image URLs
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_returns_order (order_id),
    INDEX idx_returns_status (status)
);

-- =====================================================
-- PAYMENT SYSTEM
-- =====================================================

-- Payment Transactions
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    transaction_id VARCHAR(255) UNIQUE NOT NULL,
    payment_method ENUM('stripe', 'paypal', 'bank_transfer', 'crypto', 'wallet') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    gateway_response JSON,
    fee_amount DECIMAL(10,2) DEFAULT 0,
    net_amount DECIMAL(10,2) NOT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_transactions_order (order_id),
    INDEX idx_transactions_status (status),
    INDEX idx_transactions_method (payment_method)
);

-- Escrow System
CREATE TABLE IF NOT EXISTS escrow_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNIQUE NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('held', 'released_to_seller', 'refunded_to_buyer', 'disputed') DEFAULT 'held',
    hold_until TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    release_conditions JSON,
    released_at TIMESTAMP NULL,
    dispute_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_escrow_status (status),
    INDEX idx_escrow_release (hold_until)
);

-- Merchant Payouts
CREATE TABLE IF NOT EXISTS merchant_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    commission_amount DECIMAL(10,2) NOT NULL,
    net_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    payout_method ENUM('bank_transfer', 'paypal', 'stripe') NOT NULL,
    account_details JSON,
    orders_included JSON, -- Array of order IDs
    processed_at TIMESTAMP NULL,
    failure_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_payouts_merchant (merchant_id),
    INDEX idx_payouts_status (status)
);

-- =====================================================
-- NOTIFICATION SYSTEM
-- =====================================================

-- Enhanced Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('order', 'payment', 'shipping', 'merchant', 'system', 'promotion', 'review', 'message') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(500) NULL,
    action_text VARCHAR(100) NULL,
    data JSON NULL, -- Additional data for the notification
    is_read BOOLEAN DEFAULT FALSE,
    is_important BOOLEAN DEFAULT FALSE,
    channel ENUM('web', 'email', 'sms', 'push') DEFAULT 'web',
    sent_at TIMESTAMP NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_unread (user_id, is_read),
    INDEX idx_notifications_type (type),
    INDEX idx_notifications_created (created_at)
);

-- Email Queue
CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    body_text TEXT,
    body_html TEXT,
    template_name VARCHAR(100),
    template_data JSON,
    status ENUM('queued', 'sending', 'sent', 'failed') DEFAULT 'queued',
    priority TINYINT DEFAULT 5, -- 1 = highest, 10 = lowest
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT NULL,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_status (status),
    INDEX idx_email_scheduled (scheduled_at),
    INDEX idx_email_priority (priority)
);

-- =====================================================
-- ANALYTICS TABLES
-- =====================================================

-- User Activity Tracking
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    session_id VARCHAR(128),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50), -- 'product', 'order', 'user', etc.
    entity_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer VARCHAR(500),
    data JSON, -- Additional context data
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_activity_user (user_id),
    INDEX idx_activity_action (action),
    INDEX idx_activity_entity (entity_type, entity_id),
    INDEX idx_activity_date (created_at)
);

-- Business Metrics
CREATE TABLE IF NOT EXISTS business_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,4) NOT NULL,
    metric_date DATE NOT NULL,
    dimensions JSON, -- Additional breakdown dimensions
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_metric_date (metric_name, metric_date),
    INDEX idx_metrics_name_date (metric_name, metric_date)
);

-- =====================================================
-- CONTENT MANAGEMENT
-- =====================================================

-- CMS Pages
CREATE TABLE IF NOT EXISTS cms_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content TEXT,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    featured_image VARCHAR(500),
    author_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at TIMESTAMP NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pages_slug (slug),
    INDEX idx_pages_status (status)
);

-- FAQ System
CREATE TABLE IF NOT EXISTS faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100) NOT NULL,
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    helpful_count INT DEFAULT 0,
    unhelpful_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_faqs_category (category),
    INDEX idx_faqs_featured (is_featured),
    INDEX idx_faqs_active (is_active)
);

-- =====================================================
-- SYSTEM CONFIGURATION
-- =====================================================

-- Site Settings
CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'float', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE, -- Can be accessed via API
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_settings_key (setting_key),
    INDEX idx_settings_public (is_public)
);

-- =====================================================
-- INITIAL DATA INSERTS
-- =====================================================

-- Insert default site settings
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'VentDepot', 'string', 'Site name displayed in headers', true),
('site_description', 'Your trusted online marketplace', 'string', 'Site description for SEO', true),
('default_currency', 'USD', 'string', 'Default currency code', true),
('commission_rate', '3.5', 'float', 'Platform commission rate percentage', false),
('max_file_upload_size', '5242880', 'integer', 'Maximum file upload size in bytes', false),
('enable_reviews', '1', 'boolean', 'Enable product reviews system', true),
('enable_messaging', '1', 'boolean', 'Enable buyer-seller messaging', true),
('enable_escrow', '1', 'boolean', 'Enable escrow protection', true),
('min_payout_amount', '25.00', 'float', 'Minimum amount for merchant payouts', false);

-- Insert default categories
INSERT IGNORE INTO product_categories (name, slug, description, sort_order) VALUES
('Electronics', 'electronics', 'Electronic devices and accessories', 1),
('Clothing & Fashion', 'clothing-fashion', 'Apparel and fashion accessories', 2),
('Home & Garden', 'home-garden', 'Home improvement and garden supplies', 3),
('Sports & Outdoors', 'sports-outdoors', 'Sports equipment and outdoor gear', 4),
('Books & Media', 'books-media', 'Books, movies, music and digital media', 5),
('Beauty & Health', 'beauty-health', 'Beauty products and health supplements', 6),
('Automotive', 'automotive', 'Car parts and automotive accessories', 7),
('Business & Industrial', 'business-industrial', 'Business equipment and industrial supplies', 8);

-- Insert sample FAQs
INSERT IGNORE INTO faqs (category, question, answer, sort_order, is_featured) VALUES
('General', 'How do I create an account?', 'Click the "Sign Up" button in the top right corner and fill out the registration form with your email and password.', 1, true),
('General', 'Is my personal information secure?', 'Yes, we use industry-standard encryption and security measures to protect your personal and financial information.', 2, true),
('Orders', 'How can I track my order?', 'Once your order ships, you will receive a tracking number via email. You can also check your order status in your account dashboard.', 1, true),
('Orders', 'What is your return policy?', 'We offer a 30-day return policy for most items. Items must be in original condition with all packaging.', 2, true),
('Payments', 'What payment methods do you accept?', 'We accept all major credit cards, PayPal, and bank transfers. All payments are processed securely.', 1, true),
('Sellers', 'How do I become a seller?', 'Click "Become a Seller" and complete the merchant application. We will review your application within 2-3 business days.', 1, true),
('Sellers', 'What are the selling fees?', 'We charge a 3.5% commission on successful sales plus payment processing fees. There are no listing fees.', 2, true);