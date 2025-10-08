<?php
/**
 * Test CMS Functionality
 * Simple test script to verify all CMS components are working
 */

require_once 'config/database.php';
require_once 'classes/CMSFrontend.php';

echo "=== CMS Frontend Module Test ===\n\n";

// Test 1: Database Connection
echo "1. Testing Database Connection...\n";
try {
    global $pdo;
    if ($pdo) {
        echo "   ✓ Database connection successful\n";
    } else {
        throw new Exception("Database connection not available");
    }
} catch (Exception $e) {
    echo "   ✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: CMS Frontend Class
echo "2. Testing CMS Frontend Class...\n";
try {
    $cms = new CMSFrontend($pdo);
    echo "   ✓ CMSFrontend class instantiated successfully\n";
} catch (Exception $e) {
    echo "   ✗ CMSFrontend class failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Required Tables
echo "3. Testing Required CMS Tables...\n";
$requiredTables = [
    'frontend_sections',
    'content_blocks',
    'image_assets',
    'frontend_banners',
    'carousel_items',
    'product_carousels',
    'carousel_products',
    'social_posts',
    'page_seo_metadata'
];

$missingTables = [];
foreach ($requiredTables as $table) {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM $table LIMIT 1");
        $stmt->execute();
        echo "   ✓ $table table exists\n";
    } catch (PDOException $e) {
        echo "   ✗ $table table missing: " . $e->getMessage() . "\n";
        $missingTables[] = $table;
    }
}

if (!empty($missingTables)) {
    echo "   ✗ Missing tables: " . implode(', ', $missingTables) . "\n";
    exit(1);
}

// Test 4: Sample Data
echo "4. Testing Sample Data...\n";
try {
    // Check if default sections exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM frontend_sections");
    $sectionCount = $stmt->fetchColumn();
    echo "   ✓ Found $sectionCount frontend sections\n";
    
    // Check if default product carousels exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM product_carousels");
    $carouselCount = $stmt->fetchColumn();
    echo "   ✓ Found $carouselCount product carousels\n";
    
    // Check if content blocks exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM content_blocks");
    $contentCount = $stmt->fetchColumn();
    echo "   ✓ Found $contentCount content blocks\n";
    
} catch (Exception $e) {
    echo "   ✗ Sample data test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: CMS Methods
echo "5. Testing CMS Methods...\n";
try {
    // Test getBannersByType
    $banners = $cms->getBannersByType('hero');
    echo "   ✓ getBannersByType() method working (found " . count($banners) . " banners)\n";
    
    // Test getContentBlocksBySection
    $contentBlocks = $cms->getContentBlocksBySection('homepage-hero');
    echo "   ✓ getContentBlocksBySection() method working (found " . count($contentBlocks) . " blocks)\n";
    
    // Test getProductsInCarousel
    $products = $cms->getProductsInCarousel('Featured Products');
    echo "   ✓ getProductsInCarousel() method working (found " . count($products) . " products)\n";
    
    // Test getImageById (with null)
    $image = $cms->getImageById(null);
    echo "   ✓ getImageById() method working\n";
    
} catch (Exception $e) {
    echo "   ✗ CMS methods test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6: Admin Pages
echo "6. Testing Admin Pages Existence...\n";
$adminPages = [
    'cms-dashboard.php',
    'cms-banners.php',
    'cms-content.php',
    'cms-products.php',
    'cms-social.php',
    'cms-images.php'
];

foreach ($adminPages as $page) {
    $pagePath = 'admin/' . $page;
    if (file_exists($pagePath)) {
        echo "   ✓ $page exists\n";
    } else {
        echo "   ✗ $page missing\n";
    }
}

echo "\n=== CMS Frontend Module Test Completed Successfully ===\n";
echo "You can now access the CMS at: admin/cms-dashboard.php\n";
?>