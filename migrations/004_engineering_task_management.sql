-- Engineering & Task Management Module Schema
-- This migration adds all necessary tables for the engineering task management system

-- =====================================================
-- QUOTE MANAGEMENT TABLES
-- =====================================================

-- Quotes table - stores user quote requests
CREATE TABLE IF NOT EXISTS quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_type VARCHAR(100) NOT NULL,
    specifications TEXT,
    specifications_file VARCHAR(500),
    quantity INT NOT NULL DEFAULT 1,
    preferred_timeline VARCHAR(100),
    status ENUM('submitted', 'reviewed', 'assigned', 'in_progress', 'completed', 'cancelled', 'invoiced') DEFAULT 'submitted',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    sales_rep_id INT NULL,
    assigned_engineer_id INT NULL,
    estimated_hours DECIMAL(6,2) NULL,
    hourly_rate DECIMAL(8,2) DEFAULT 0.00,
    markup_percentage DECIMAL(5,2) DEFAULT 0.00,
    material_cost DECIMAL(10,2) DEFAULT 0.00,
    labor_cost DECIMAL(10,2) DEFAULT 0.00,
    overhead_cost DECIMAL(10,2) DEFAULT 0.00,
    total_cost DECIMAL(10,2) DEFAULT 0.00,
    final_quote_amount DECIMAL(10,2) DEFAULT 0.00,
    commission_percentage DECIMAL(5,2) DEFAULT 0.00,
    commission_amount DECIMAL(10,2) DEFAULT 0.00,
    actual_hours DECIMAL(6,2) DEFAULT 0.00,
    actual_material_cost DECIMAL(10,2) DEFAULT 0.00,
    start_date DATE NULL,
    end_date DATE NULL,
    invoice_id INT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    assigned_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    invoiced_at TIMESTAMP NULL,
    notified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sales_rep_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_engineer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (invoice_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_quotes_user (user_id),
    INDEX idx_quotes_status (status),
    INDEX idx_quotes_sales_rep (sales_rep_id),
    INDEX idx_quotes_engineer (assigned_engineer_id),
    INDEX idx_quotes_created (created_at),
    INDEX idx_quotes_invoice (invoice_id)
);

-- =====================================================
-- TASK MANAGEMENT TABLES
-- =====================================================

-- Engineering tasks table - stores engineering tasks
CREATE TABLE IF NOT EXISTS engineering_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    assigned_to INT NOT NULL,
    status ENUM('new', 'assigned', 'in_progress', 'review', 'completed') DEFAULT 'new',
    estimated_hours DECIMAL(6,2) DEFAULT 0.00,
    actual_hours DECIMAL(6,2) DEFAULT 0.00,
    start_date DATE NULL,
    end_date DATE NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    dependencies TEXT, -- JSON array of task IDs this task depends on
    acceptance_rating TINYINT NULL, -- 1-5 rating for product acceptance
    feedback TEXT, -- Feedback on product acceptance
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_tasks_quote (quote_id),
    INDEX idx_tasks_assigned (assigned_to),
    INDEX idx_tasks_status (status),
    INDEX idx_tasks_dates (start_date, end_date)
);

-- Task comments table - for communication on tasks
CREATE TABLE IF NOT EXISTS task_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES engineering_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_comments_task (task_id),
    INDEX idx_comments_user (user_id)
);

-- =====================================================
-- TIME TRACKING TABLES
-- =====================================================

-- Time logs table - tracks time spent on tasks
CREATE TABLE IF NOT EXISTS time_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    time_spent_minutes INT NOT NULL,
    log_date DATE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES engineering_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_time_logs_task (task_id),
    INDEX idx_time_logs_user (user_id),
    INDEX idx_time_logs_date (log_date)
);

-- Engineer availability table - tracks engineer availability
CREATE TABLE IF NOT EXISTS engineer_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    availability_date DATE NOT NULL,
    available_hours DECIMAL(4,2) DEFAULT 8.00,
    booked_hours DECIMAL(4,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, availability_date),
    INDEX idx_availability_user (user_id),
    INDEX idx_availability_date (availability_date)
);

-- Engineer skills table - tracks engineer skills and expertise
CREATE TABLE IF NOT EXISTS engineer_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    proficiency_level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'intermediate',
    years_experience INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_skills_user (user_id),
    INDEX idx_skills_name (skill_name)
);

-- =====================================================
-- FINANCIAL TRACKING TABLES
-- =====================================================

-- Quote attachments table - stores files related to quotes
CREATE TABLE IF NOT EXISTS quote_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_attachments_quote (quote_id),
    INDEX idx_attachments_user (uploaded_by)
);

-- Commission tracking table - tracks sales commissions
CREATE TABLE IF NOT EXISTS sales_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sales_rep_id INT NOT NULL,
    quote_id INT NOT NULL,
    commission_percentage DECIMAL(5,2) NOT NULL,
    commission_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sales_rep_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    INDEX idx_commissions_sales_rep (sales_rep_id),
    INDEX idx_commissions_quote (quote_id),
    INDEX idx_commissions_status (status)
);

-- =====================================================
-- NOTIFICATIONS TABLES
-- =====================================================

-- Quote notifications table
CREATE TABLE IF NOT EXISTS quote_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    user_id INT NOT NULL,
    notification_type ENUM('quote_submitted', 'quote_reviewed', 'quote_assigned', 'quote_completed', 'quote_finalized', 'quote_invoiced') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_quote (quote_id),
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_read (is_read)
);

-- Task notifications table
CREATE TABLE IF NOT EXISTS task_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    notification_type ENUM('task_assigned', 'task_status_changed', 'task_comment_added', 'task_overdue') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES engineering_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_task_notifications_task (task_id),
    INDEX idx_task_notifications_user (user_id),
    INDEX idx_task_notifications_read (is_read)
);

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Add engineering roles if they don't exist
INSERT IGNORE INTO users (email, password, role, created_at) VALUES
('sales1@ventdepot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW()),
('engineer1@ventdepot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW()),
('engineer2@ventdepot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW());

-- Add default hourly rates to existing users (for demo purposes)
INSERT IGNORE INTO user_profiles (user_id, first_name, last_name, phone, created_at) VALUES
(12, 'Sales', 'Representative 1', '+1-555-0401', NOW()),
(13, 'Engineer', 'One', '+1-555-0501', NOW()),
(14, 'Engineer', 'Two', '+1-555-0502', NOW());