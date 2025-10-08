-- =====================================================
-- Contact, FAQ, and Admin Management Tables
-- Add these tables to your finalJulio database
-- =====================================================

USE finalJulio;

-- =====================================================
-- CONTACT MANAGEMENT TABLES
-- =====================================================

-- Contact Messages Table
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'NULL if guest contact',
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'in_progress', 'replied', 'closed') DEFAULT 'pending',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    department ENUM('general', 'support', 'sales', 'billing', 'technical') DEFAULT 'general',
    assigned_to INT NULL COMMENT 'Admin user ID',
    replied_at DATETIME NULL,
    replied_by INT NULL COMMENT 'Admin user ID who replied',
    reply_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_contact_status (status),
    INDEX idx_contact_created (created_at),
    INDEX idx_contact_user (user_id)
);

-- Site Settings Table (for storing various site configurations)
CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'textarea', 'number', 'boolean', 'json') DEFAULT 'text',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE COMMENT 'Whether this setting can be viewed by non-admins',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_settings_key (setting_key),
    INDEX idx_settings_public (is_public)
);

-- =====================================================
-- FAQ MANAGEMENT TABLES
-- =====================================================

-- FAQs Table
CREATE TABLE IF NOT EXISTS faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    view_count INT DEFAULT 0,
    helpful_count INT DEFAULT 0,
    not_helpful_count INT DEFAULT 0,
    created_by INT NULL COMMENT 'Admin user ID who created this FAQ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_faq_category (category),
    INDEX idx_faq_active (is_active),
    INDEX idx_faq_sort (sort_order)
);

-- FAQ Feedback Table (to track if FAQs are helpful)
CREATE TABLE IF NOT EXISTS faq_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faq_id INT NOT NULL,
    user_id INT NULL,
    is_helpful BOOLEAN NOT NULL,
    feedback_text TEXT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faq_id) REFERENCES faqs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_faq_feedback (faq_id, user_id, ip_address),
    INDEX idx_feedback_faq (faq_id)
);

-- =====================================================
-- MERCHANT APPLICATION TABLES
-- =====================================================

-- Merchant Applications Table
CREATE TABLE IF NOT EXISTS merchant_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'NULL if not registered yet',
    business_name VARCHAR(200) NOT NULL,
    business_type ENUM('individual', 'sole_proprietorship', 'partnership', 'llc', 'corporation', 'other') NOT NULL,
    business_description TEXT,
    contact_name VARCHAR(100) NOT NULL,
    contact_email VARCHAR(100) NOT NULL,
    contact_phone VARCHAR(20),
    business_address TEXT,
    business_city VARCHAR(100),
    business_state VARCHAR(50),
    business_postal_code VARCHAR(20),
    business_country VARCHAR(3) DEFAULT 'USA',
    tax_id VARCHAR(50) COMMENT 'EIN, SSN, or other tax identifier',
    website_url VARCHAR(255),
    estimated_monthly_sales DECIMAL(12,2),
    product_categories TEXT COMMENT 'JSON array of product categories',
    business_license_number VARCHAR(100),
    years_in_business INT,
    previous_ecommerce_experience TEXT,
    marketing_plan TEXT,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'requires_info') DEFAULT 'pending',
    reviewed_by INT NULL COMMENT 'Admin user ID who reviewed',
    reviewed_at DATETIME NULL,
    review_notes TEXT,
    rejection_reason TEXT,
    documents_uploaded BOOLEAN DEFAULT FALSE,
    agreement_accepted BOOLEAN DEFAULT FALSE,
    agreement_accepted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_merchant_status (status),
    INDEX idx_merchant_created (created_at),
    INDEX idx_merchant_user (user_id)
);

-- Merchant Application Documents Table
CREATE TABLE IF NOT EXISTS merchant_application_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_type ENUM('business_license', 'tax_document', 'bank_statement', 'identity_proof', 'other') NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES merchant_applications(id) ON DELETE CASCADE,
    INDEX idx_docs_application (application_id)
);

-- =====================================================
-- NOTIFICATION SYSTEM TABLES
-- =====================================================

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('order', 'payment', 'shipping', 'merchant', 'system', 'promotion') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(500) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_important BOOLEAN DEFAULT FALSE,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_unread (user_id, is_read),
    INDEX idx_notifications_type (type),
    INDEX idx_notifications_created (created_at)
);

-- =====================================================
-- SYSTEM LOGS TABLE
-- =====================================================

-- System Activity Logs Table
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL COMMENT 'product, order, user, etc.',
    entity_id INT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    additional_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_logs_user (user_id),
    INDEX idx_logs_action (action),
    INDEX idx_logs_entity (entity_type, entity_id),
    INDEX idx_logs_created (created_at)
);

-- =====================================================
-- INSERT DEFAULT SITE SETTINGS
-- =====================================================

-- Insert default contact information
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('contact_phone', '1-800-VENTDEPOT', 'text', 'Main contact phone number', TRUE),
('contact_email', 'info@ventdepot.com', 'text', 'General contact email', TRUE),
('support_email', 'support@ventdepot.com', 'text', 'Customer support email', TRUE),
('sales_email', 'sales@ventdepot.com', 'text', 'Sales inquiries email', TRUE),
('contact_address', '123 Business Street\nLos Angeles, CA 90210\nUnited States', 'textarea', 'Business address', TRUE),
('business_hours', 'Monday - Friday: 9:00 AM - 6:00 PM PST\nSaturday: 10:00 AM - 4:00 PM PST\nSunday: Closed', 'textarea', 'Business operating hours', TRUE),

-- Shipping information
('shipping_domestic_info', 'We offer fast and reliable domestic shipping throughout the United States. Standard shipping typically takes 3-7 business days, while express options are available for faster delivery.', 'textarea', 'Domestic shipping information', TRUE),
('shipping_international_info', 'International shipping is available to most countries worldwide. Delivery times vary by destination and may take 7-21 business days. Customs fees and import duties may apply.', 'textarea', 'International shipping information', TRUE),
('shipping_processing_time', 'Orders are typically processed within 1-2 business days. Orders placed before 2:00 PM PST on business days are usually processed the same day.', 'textarea', 'Order processing time information', TRUE),
('shipping_rates_info', 'Shipping rates are calculated based on package weight, dimensions, destination, and selected shipping method. Free shipping is available on orders over $50 for domestic shipments.', 'textarea', 'Shipping rates information', TRUE),
('shipping_restrictions', 'Some items may have shipping restrictions due to size, weight, or regulatory requirements. Hazardous materials and certain electronics may require special handling.', 'textarea', 'Shipping restrictions information', TRUE),
('shipping_tracking_info', 'All shipments include tracking information. You will receive a tracking number via email once your order ships. Track your package on our website or the carrier\'s website.', 'textarea', 'Package tracking information', TRUE),

-- Returns and refunds policy
('returns_policy', 'We accept returns within 30 days of delivery for most items in original condition. Items must be unused, in original packaging, and include all accessories and documentation.', 'textarea', 'Returns policy', TRUE),
('refund_policy', 'Refunds are processed within 5-7 business days after we receive your returned item. Refunds will be issued to the original payment method. Shipping costs are non-refundable unless the return is due to our error.', 'textarea', 'Refund policy', TRUE),
('exchange_policy', 'We offer exchanges for different sizes or colors when available. Exchanges are processed as returns and new orders to ensure fastest delivery of your preferred item.', 'textarea', 'Exchange policy', TRUE),
('return_process', '1. Contact our customer service to initiate a return\n2. Print the prepaid return label we provide\n3. Package the item securely in original packaging\n4. Attach the return label and drop off at any authorized location\n5. Track your return and refund status online', 'textarea', 'Return process steps', TRUE),

-- Site configuration
('site_name', 'VentDepot', 'text', 'Website name', TRUE),
('site_tagline', 'Your Premier Destination for Quality Products', 'text', 'Website tagline', TRUE),
('maintenance_mode', '0', 'boolean', 'Enable maintenance mode', FALSE),
('allow_guest_checkout', '1', 'boolean', 'Allow guest checkout', FALSE),
('require_email_verification', '1', 'boolean', 'Require email verification for new accounts', FALSE),
('max_login_attempts', '5', 'number', 'Maximum login attempts before lockout', FALSE),
('session_timeout', '3600', 'number', 'Session timeout in seconds', FALSE);

-- =====================================================
-- INSERT DEFAULT FAQs
-- =====================================================

-- Insert default FAQs
INSERT IGNORE INTO faqs (question, answer, category, sort_order, is_active) VALUES
-- General FAQs
('What is VentDepot?', 'VentDepot is your premier online destination for quality products. We connect customers with trusted merchants offering a wide variety of items at competitive prices.', 'General', 1, TRUE),
('How do I create an account?', 'Click the "Sign Up" button in the top right corner of any page. Fill out the registration form with your email address and create a secure password. You\'ll receive a confirmation email to verify your account.', 'General', 2, TRUE),
('Is my personal information secure?', 'Yes, we take your privacy and security seriously. We use industry-standard encryption and security measures to protect your personal and payment information.', 'General', 3, TRUE),

-- Shipping FAQs
('How much does shipping cost?', 'Shipping costs are calculated based on the weight, size, and destination of your order. We offer free standard shipping on orders over $50 within the United States.', 'Shipping', 1, TRUE),
('How long does shipping take?', 'Standard shipping typically takes 3-7 business days within the United States. Express shipping options are available for faster delivery. International shipping may take 7-21 business days.', 'Shipping', 2, TRUE),
('Do you ship internationally?', 'Yes, we ship to most countries worldwide. International shipping rates and delivery times vary by destination. Customs fees and import duties may apply and are the responsibility of the customer.', 'Shipping', 3, TRUE),
('How can I track my order?', 'Once your order ships, you\'ll receive a tracking number via email. You can track your package on our website or directly on the carrier\'s website using this tracking number.', 'Shipping', 4, TRUE),

-- Returns FAQs
('What is your return policy?', 'We accept returns within 30 days of delivery for most items in original condition. Items must be unused, in original packaging, and include all accessories.', 'Returns', 1, TRUE),
('How do I return an item?', 'Contact our customer service team to initiate a return. We\'ll provide you with a prepaid return label and instructions. Package the item securely and drop it off at any authorized location.', 'Returns', 2, TRUE),
('How long does it take to process a refund?', 'Refunds are typically processed within 5-7 business days after we receive your returned item. The refund will be issued to your original payment method.', 'Returns', 3, TRUE),

-- Payment FAQs
('What payment methods do you accept?', 'We accept all major credit cards (Visa, MasterCard, American Express, Discover), PayPal, and other secure payment methods. All transactions are processed securely.', 'Payment', 1, TRUE),
('Is it safe to enter my credit card information?', 'Yes, our checkout process uses SSL encryption and industry-standard security measures to protect your payment information. We never store your complete credit card details.', 'Payment', 2, TRUE),
('Can I save my payment information for future orders?', 'Yes, you can securely save your payment methods in your account for faster checkout. This information is encrypted and stored securely.', 'Payment', 3, TRUE),

-- Account FAQs
('How do I reset my password?', 'Click "Forgot Password" on the login page and enter your email address. We\'ll send you a secure link to reset your password.', 'Account', 1, TRUE),
('How do I update my account information?', 'Log into your account and go to "My Profile" to update your personal information, addresses, and preferences.', 'Account', 2, TRUE),
('Can I change my email address?', 'Yes, you can update your email address in your account settings. You\'ll need to verify the new email address before the change takes effect.', 'Account', 3, TRUE);

-- =====================================================
-- COMPLETION MESSAGE
-- =====================================================

SELECT 'Contact and Admin Management Tables Created Successfully!' as Status,
       'Tables Created: 8' as Tables_Created,
       'Default Settings: 20+' as Settings_Added,
       'Default FAQs: 15' as FAQs_Added;

-- Show table counts
SELECT 
    'contact_messages' as Table_Name, COUNT(*) as Records FROM contact_messages
UNION ALL SELECT 
    'site_settings' as Table_Name, COUNT(*) as Records FROM site_settings
UNION ALL SELECT 
    'faqs' as Table_Name, COUNT(*) as Records FROM faqs
UNION ALL SELECT 
    'faq_feedback' as Table_Name, COUNT(*) as Records FROM faq_feedback
UNION ALL SELECT 
    'merchant_applications' as Table_Name, COUNT(*) as Records FROM merchant_applications
UNION ALL SELECT 
    'merchant_application_documents' as Table_Name, COUNT(*) as Records FROM merchant_application_documents
UNION ALL SELECT 
    'notifications' as Table_Name, COUNT(*) as Records FROM notifications
UNION ALL SELECT 
    'system_logs' as Table_Name, COUNT(*) as Records FROM system_logs;
