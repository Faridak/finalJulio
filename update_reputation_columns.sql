-- Add reputation columns to user_profiles table
ALTER TABLE user_profiles 
ADD COLUMN IF NOT EXISTS reputation_score DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS total_ratings INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3,2) DEFAULT 0.00;

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_user_profiles_reputation ON user_profiles(reputation_score);
CREATE INDEX IF NOT EXISTS idx_user_profiles_rating ON user_profiles(average_rating);

-- Update existing profiles with default values
UPDATE user_profiles SET 
    reputation_score = 0.00, 
    total_ratings = 0, 
    average_rating = 0.00 
WHERE reputation_score IS NULL;