-- Homepage Components Schema
-- Version: 1.0
-- Date: 2023-06-15

USE ventdepot;

-- Homepage Banner Table
CREATE TABLE homepage_banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    text_overlay TEXT,
    button_text VARCHAR(100),
    button_link VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    start_date DATETIME,
    end_date DATETIME,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Featured Products Table
CREATE TABLE homepage_featured_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    title VARCHAR(255),
    description TEXT,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    start_date DATETIME,
    end_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Promotional Popups Table
CREATE TABLE homepage_popups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    image_url VARCHAR(500),
    product_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    show_once BOOLEAN DEFAULT FALSE,
    start_date DATETIME,
    end_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Call-to-Action Buttons Table
CREATE TABLE homepage_cta_buttons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    text VARCHAR(100) NOT NULL,
    link VARCHAR(500) NOT NULL,
    button_style VARCHAR(50) DEFAULT 'primary',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    start_date DATETIME,
    end_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Homepage Sections Table (for layout arrangement)
CREATE TABLE homepage_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(100) NOT NULL,
    section_type ENUM('banner', 'featured_products', 'popup', 'cta_button', 'custom') NOT NULL,
    title VARCHAR(255),
    content TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Homepage Layout Configuration Table
CREATE TABLE homepage_layout (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    position INT NOT NULL,
    is_visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES homepage_sections(id) ON DELETE CASCADE
);

-- Insert default sections
INSERT INTO homepage_sections (section_name, section_type, title, is_active, sort_order) VALUES
('main_banner', 'banner', 'Main Banner', TRUE, 1),
('featured_products', 'featured_products', 'Featured Products', TRUE, 2),
('promotional_popup', 'popup', 'Promotional Popup', TRUE, 3),
('cta_button', 'cta_button', 'Call to Action', TRUE, 4);

-- Insert default layout
INSERT INTO homepage_layout (section_id, position, is_visible) 
SELECT id, sort_order, TRUE FROM homepage_sections ORDER BY sort_order;

COMMIT;