-- CMS Frontend Management Schema
-- Tables for managing frontend content, banners, carousels, and social media publishing

-- Frontend Content Sections
CREATE TABLE IF NOT EXISTS frontend_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sections_active (is_active),
    INDEX idx_sections_slug (slug)
);

-- Content Blocks
CREATE TABLE IF NOT EXISTS content_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    content_type ENUM('text', 'html', 'markdown') DEFAULT 'html',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES frontend_sections(id) ON DELETE CASCADE,
    INDEX idx_blocks_section (section_id),
    INDEX idx_blocks_active (is_active)
);

-- Image Assets
CREATE TABLE IF NOT EXISTS image_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    alt_text VARCHAR(255),
    title VARCHAR(255),
    caption TEXT,
    tags JSON,
    is_active BOOLEAN DEFAULT TRUE,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_assets_active (is_active),
    INDEX idx_assets_filename (filename)
);

-- Carousel/Banners
CREATE TABLE IF NOT EXISTS frontend_banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255),
    image_id INT,
    content TEXT,
    button_text VARCHAR(100),
    button_url VARCHAR(500),
    target ENUM('_self', '_blank') DEFAULT '_self',
    banner_type ENUM('hero', 'carousel', 'promotion', 'popup') DEFAULT 'carousel',
    is_active BOOLEAN DEFAULT TRUE,
    start_date TIMESTAMP NULL,
    end_date TIMESTAMP NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (image_id) REFERENCES image_assets(id) ON DELETE SET NULL,
    INDEX idx_banners_active (is_active),
    INDEX idx_banners_type (banner_type),
    INDEX idx_banners_dates (start_date, end_date)
);

-- Carousel Items (for multiple items in a carousel)
CREATE TABLE IF NOT EXISTS carousel_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    banner_id INT NOT NULL,
    image_id INT,
    title VARCHAR(255),
    subtitle VARCHAR(255),
    content TEXT,
    button_text VARCHAR(100),
    button_url VARCHAR(500),
    target ENUM('_self', '_blank') DEFAULT '_self',
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (banner_id) REFERENCES frontend_banners(id) ON DELETE CASCADE,
    FOREIGN KEY (image_id) REFERENCES image_assets(id) ON DELETE SET NULL,
    INDEX idx_carousel_items_banner (banner_id),
    INDEX idx_carousel_items_active (is_active)
);

-- Product Carousels (featured products in carousels)
CREATE TABLE IF NOT EXISTS product_carousels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    title VARCHAR(255),
    description TEXT,
    max_products INT DEFAULT 10,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product_carousels_active (is_active)
);

-- Products in Carousels
CREATE TABLE IF NOT EXISTS carousel_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carousel_id INT NOT NULL,
    product_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (carousel_id) REFERENCES product_carousels(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_carousel_product (carousel_id, product_id),
    INDEX idx_carousel_products_carousel (carousel_id)
);

-- Social Media Posts
CREATE TABLE IF NOT EXISTS social_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    content TEXT NOT NULL,
    image_id INT,
    platform ENUM('facebook', 'twitter', 'instagram', 'linkedin') NOT NULL,
    status ENUM('draft', 'scheduled', 'published', 'failed') DEFAULT 'draft',
    scheduled_at TIMESTAMP NULL,
    published_at TIMESTAMP NULL,
    post_url VARCHAR(500),
    engagement_data JSON,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (image_id) REFERENCES image_assets(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_social_posts_platform (platform),
    INDEX idx_social_posts_status (status)
);

-- SEO Metadata for Pages
CREATE TABLE IF NOT EXISTS page_seo_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_type ENUM('homepage', 'category', 'product', 'cms_page', 'custom') NOT NULL,
    page_identifier VARCHAR(255),
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    og_title VARCHAR(255),
    og_description TEXT,
    og_image_id INT,
    twitter_title VARCHAR(255),
    twitter_description TEXT,
    twitter_image_id INT,
    canonical_url VARCHAR(500),
    robots_txt VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (og_image_id) REFERENCES image_assets(id) ON DELETE SET NULL,
    FOREIGN KEY (twitter_image_id) REFERENCES image_assets(id) ON DELETE SET NULL,
    UNIQUE KEY unique_page_metadata (page_type, page_identifier),
    INDEX idx_page_metadata_type (page_type)
);

-- Insert default sections
INSERT IGNORE INTO frontend_sections (name, slug, description, sort_order) VALUES
('Homepage Hero', 'homepage-hero', 'Main hero section on homepage', 1),
('Homepage Categories', 'homepage-categories', 'Category display section', 2),
('Homepage Featured Products', 'homepage-featured', 'Featured products section', 3),
('Homepage Promotions', 'homepage-promotions', 'Promotional banners section', 4),
('Homepage Testimonials', 'homepage-testimonials', 'Customer testimonials section', 5),
('Footer Content', 'footer-content', 'Footer content sections', 6);

-- Insert default product carousels
INSERT IGNORE INTO product_carousels (name, title, description, max_products, sort_order) VALUES
('Featured Products', 'Featured Products', 'Our most popular products', 8, 1),
('New Arrivals', 'New Arrivals', 'Recently added products', 8, 2),
('Best Sellers', 'Best Selling Products', 'Our top selling products', 8, 3),
('Deals of the Day', 'Special Deals', 'Limited time offers', 6, 4);