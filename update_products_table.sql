-- Add review-related columns to existing products table
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS review_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_products_rating ON products(average_rating);
CREATE INDEX IF NOT EXISTS idx_products_reviews ON products(review_count);

-- Update existing products with default values
UPDATE products SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL;