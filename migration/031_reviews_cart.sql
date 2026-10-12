-- Migration 031: verified-purchase reviews + persistent cart.

-- Reviews (matches includes/ProductReviews.php expectations)
CREATE TABLE IF NOT EXISTS product_reviews (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    product_id           INT NOT NULL,
    user_id              INT NOT NULL,
    order_id             INT NULL,
    rating               TINYINT NOT NULL,
    title                VARCHAR(150) NULL,
    review_text          TEXT NULL,
    is_verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
    is_approved          TINYINT(1) NOT NULL DEFAULT 1,
    helpful_count        INT NOT NULL DEFAULT 0,
    unhelpful_count      INT NOT NULL DEFAULT 0,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_user_product (user_id, product_id),
    INDEX idx_pr_product (product_id, is_approved),
    CONSTRAINT fk_pr_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS review_votes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    review_id  INT NOT NULL,
    user_id    INT NOT NULL,
    vote_type  ENUM('helpful','unhelpful') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vote (review_id, user_id),
    CONSTRAINT fk_rv_review FOREIGN KEY (review_id) REFERENCES product_reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Persistent cart for logged-in users (session cart mirrors into this).
CREATE TABLE IF NOT EXISTS cart_items (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    product_id INT NOT NULL,
    quantity   INT NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cart_user_product (user_id, product_id),
    CONSTRAINT fk_ci_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Denormalized rating columns on products (kept fresh by ProductReviews).
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3,2) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS total_reviews INT NOT NULL DEFAULT 0;
