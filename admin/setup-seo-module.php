<?php
require_once 'c:\xampp\htdocs\finalJulio\config\database.php';

try {
    // Add SEO columns to products table
    $alterProductsTable = "
        ALTER TABLE products 
        ADD COLUMN meta_title VARCHAR(255) NULL,
        ADD COLUMN meta_description TEXT NULL,
        ADD COLUMN meta_keywords TEXT NULL,
        ADD COLUMN og_title VARCHAR(255) NULL,
        ADD COLUMN og_description TEXT NULL,
        ADD COLUMN og_image VARCHAR(255) NULL,
        ADD COLUMN og_type VARCHAR(50) DEFAULT 'product',
        ADD COLUMN twitter_title VARCHAR(255) NULL,
        ADD COLUMN twitter_description TEXT NULL,
        ADD COLUMN twitter_image VARCHAR(255) NULL,
        ADD COLUMN canonical_url VARCHAR(255) NULL,
        ADD COLUMN robots VARCHAR(100) DEFAULT 'index,follow'
    ";
    
    $pdo->exec($alterProductsTable);
    
    // Create product_seo table for additional SEO data
    $createSeoTable = "
        CREATE TABLE IF NOT EXISTS product_seo (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            schema_type VARCHAR(50) DEFAULT 'Product',
            schema_data JSON NULL,
            social_media_templates JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            INDEX idx_product_id (product_id)
        )
    ";
    
    $pdo->exec($createSeoTable);
    
    echo "SEO module tables and columns created successfully!\n";
    echo "Added SEO columns to products table:\n";
    echo "  - meta_title\n";
    echo "  - meta_description\n";
    echo "  - meta_keywords\n";
    echo "  - og_title\n";
    echo "  - og_description\n";
    echo "  - og_image\n";
    echo "  - og_type\n";
    echo "  - twitter_title\n";
    echo "  - twitter_description\n";
    echo "  - twitter_image\n";
    echo "  - canonical_url\n";
    echo "  - robots\n";
    echo "\n";
    echo "Created product_seo table with:\n";
    echo "  - schema_type\n";
    echo "  - schema_data (JSON)\n";
    echo "  - social_media_templates (JSON)\n";
    
} catch (PDOException $e) {
    // Check if it's a duplicate column error (in case script is run multiple times)
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "SEO columns already exist in products table.\n";
    } else {
        echo "Error creating SEO module: " . $e->getMessage() . "\n";
    }
    
    // Try to create the product_seo table if it doesn't exist
    try {
        $createSeoTable = "
            CREATE TABLE IF NOT EXISTS product_seo (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                schema_type VARCHAR(50) DEFAULT 'Product',
                schema_data JSON NULL,
                social_media_templates JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                INDEX idx_product_id (product_id)
            )
        ";
        
        $pdo->exec($createSeoTable);
        echo "product_seo table created successfully!\n";
    } catch (PDOException $e2) {
        echo "Error creating product_seo table: " . $e2->getMessage() . "\n";
    }
}
?>