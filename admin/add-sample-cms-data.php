<?php
/**
 * Add Sample CMS Data for Testing
 * This script adds sample data to test the CMS functionality
 */

require_once '../config/database.php';

// Require admin login
requireRole('admin');

echo "Adding sample CMS data...\n";

try {
    // Add sample banners
    $stmt = $pdo->prepare("
        INSERT INTO frontend_banners (title, subtitle, content, button_text, button_url, banner_type, is_active, sort_order) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $banners = [
        ['Summer Sale', 'Up to 50% off on selected items', '<p>Don\'t miss our biggest sale of the year!</p>', 'Shop Now', '/search.php', 'hero', 1, 1],
        ['New Arrivals', 'Check out our latest products', '<p>Fresh products just for you</p>', 'View Collection', '/search.php?category=Electronics', 'promotion', 1, 1],
        ['Free Shipping', 'On orders over $50', '<p>Free shipping on all orders</p>', 'Start Shopping', '/search.php', 'promotion', 1, 2],
        ['Limited Time Offer', '24 hours only', '<p>Special discounts for a limited time</p>', 'Grab Deal', '/search.php', 'popup', 1, 3]
    ];
    
    foreach ($banners as $banner) {
        $stmt->execute($banner);
    }
    
    echo "✓ Added " . count($banners) . " sample banners\n";
    
    // Add sample content blocks
    $stmt = $pdo->prepare("
        INSERT INTO content_blocks (section_id, title, content, content_type, is_active, sort_order) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $contentBlocks = [
        [1, 'Welcome to Our Store', '<p>Welcome to VentDepot, your one-stop shop for all your needs. We offer a wide range of quality products at competitive prices.</p>', 'html', 1, 1],
        [3, 'Why Choose Us?', '<ul><li>Fast and free shipping</li><li>30-day return policy</li><li>24/7 customer support</li></ul>', 'html', 1, 1],
        [6, 'About Our Company', '<p>We are committed to providing the best shopping experience with quality products and excellent customer service.</p>', 'html', 1, 1]
    ];
    
    foreach ($contentBlocks as $block) {
        $stmt->execute($block);
    }
    
    echo "✓ Added " . count($contentBlocks) . " sample content blocks\n";
    
    // Add sample social posts
    $stmt = $pdo->prepare("
        INSERT INTO social_posts (title, content, platform, status, created_by) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $socialPosts = [
        ['New Product Launch', 'Excited to announce our new product line! Check it out now.', 'facebook', 'published', $_SESSION['user_id']],
        ['Summer Sale', 'Huge discounts on summer collection. Don\'t miss out!', 'twitter', 'published', $_SESSION['user_id']],
        ['Customer Testimonial', 'Happy customers sharing their experience with our products.', 'instagram', 'published', $_SESSION['user_id']],
        ['Behind the Scenes', 'Take a look at how we prepare your orders with care.', 'linkedin', 'published', $_SESSION['user_id']]
    ];
    
    foreach ($socialPosts as $post) {
        $stmt->execute($post);
    }
    
    echo "✓ Added " . count($socialPosts) . " sample social posts\n";
    
    // Add sample SEO metadata
    $stmt = $pdo->prepare("
        INSERT INTO page_seo_metadata (page_type, meta_title, meta_description, meta_keywords) 
        VALUES (?, ?, ?, ?)
    ");
    
    $seoData = [
        ['homepage', 'VentDepot - Your Online Marketplace', 'Shop the best products at competitive prices with fast shipping and excellent customer service.', 'shopping, ecommerce, products, online store'],
        ['category', 'Electronics - VentDepot', 'Find the latest electronics at great prices with fast shipping and warranty.', 'electronics, gadgets, technology, phones, laptops'],
        ['product', null, 'High-quality product with warranty and fast shipping.', 'product, shopping, quality, warranty']
    ];
    
    foreach ($seoData as $seo) {
        $stmt->execute($seo);
    }
    
    echo "✓ Added " . count($seoData) . " sample SEO metadata entries\n";
    
    echo "\nSample CMS data added successfully!\n";
    echo "You can now test the CMS functionality in the admin panel.\n";
    
} catch (Exception $e) {
    echo "Error adding sample data: " . $e->getMessage() . "\n";
    exit(1);
}
?>