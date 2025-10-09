-- User Profiles Table Migration
-- This migration adds the user_profiles table that is required by the application

CREATE TABLE IF NOT EXISTS user_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  first_name VARCHAR(100) DEFAULT NULL,
  last_name VARCHAR(100) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  date_of_birth DATE DEFAULT NULL,
  profile_image VARCHAR(500) DEFAULT NULL,
  bio TEXT DEFAULT NULL,
  preferences LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_id (user_id),
  INDEX idx_user_profiles_user (user_id)
);

-- Insert default data for existing users
INSERT IGNORE INTO user_profiles (user_id, first_name, last_name, phone, created_at) VALUES
(1, 'Admin', 'User', '+1-555-0001', NOW()),
(2, 'John', 'Smith', '+1-555-0102', NOW()),
(3, 'Sarah', 'Johnson', '+1-555-0103', NOW()),
(4, 'Mike', 'Davis', '+1-555-0204', NOW()),
(5, 'Emily', 'Wilson', '+1-555-0205', NOW()),
(6, 'David', 'Brown', '+1-555-0206', NOW()),
(7, 'Tech', 'Store', '+1-555-0307', NOW()),
(8, 'Fashion', 'Hub', '+1-555-0308', NOW()),
(9, 'Home', 'Goods', '+1-555-0309', NOW()),
(10, 'Sports', 'World', '+1-555-0310', NOW()),
(11, 'Uctrl', 'LLC', '9292731531', NOW()),
(12, 'Sales', 'Representative 1', '+1-555-0401', NOW()),
(13, 'Engineer', 'One', '+1-555-0501', NOW()),
(14, 'Engineer', 'Two', '+1-555-0502', NOW());