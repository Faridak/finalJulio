<?php
echo "=== SEO Module Verification ===\n\n";

// Check required files
$requiredFiles = [
    'SEO Management Page' => 'c:\xampp\htdocs\finalJulio\admin\seo-management.php',
    'SEO API' => 'c:\xampp\htdocs\finalJulio\admin\api\seo-api.php',
    'Setup Script' => 'c:\xampp\htdocs\finalJulio\admin\setup-seo-module.php',
    'Documentation' => 'c:\xampp\htdocs\finalJulio\admin\seo-module-documentation.md'
];

echo "Checking required files:\n";
$allFilesExist = true;
foreach ($requiredFiles as $name => $path) {
    if (file_exists($path)) {
        echo "  ✓ $name\n";
    } else {
        echo "  ✗ $name (MISSING)\n";
        $allFilesExist = false;
    }
}

echo "\nChecking database structure:\n";
require_once 'c:\xampp\htdocs\finalJulio\config\database.php';

// Check if SEO columns exist in products table
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'meta_title'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "  ✓ SEO columns added to products table\n";
    } else {
        echo "  ✗ SEO columns missing from products table\n";
        $allFilesExist = false;
    }
} catch (Exception $e) {
    echo "  ✗ Error checking products table: " . $e->getMessage() . "\n";
    $allFilesExist = false;
}

// Check if product_seo table exists
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute(['product_seo']);
    $result = $stmt->fetch();
    
    if ($result) {
        echo "  ✓ product_seo table exists\n";
    } else {
        echo "  ✗ product_seo table missing\n";
        $allFilesExist = false;
    }
} catch (Exception $e) {
    echo "  ✗ Error checking product_seo table: " . $e->getMessage() . "\n";
    $allFilesExist = false;
}

// Check if merchant product pages have been updated
echo "\nChecking merchant product pages:\n";
$merchantFiles = [
    'c:\xampp\htdocs\finalJulio\merchant\add-product.php',
    'c:\xampp\htdocs\finalJulio\merchant\edit-product.php'
];

foreach ($merchantFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'meta_title') !== false && strpos($content, 'og_title') !== false) {
            echo "  ✓ " . basename($file) . " updated with SEO fields\n";
        } else {
            echo "  ✗ " . basename($file) . " missing SEO fields\n";
            $allFilesExist = false;
        }
    } else {
        echo "  ✗ " . basename($file) . " missing\n";
        $allFilesExist = false;
    }
}

// Check if product page has SEO meta tags
echo "\nChecking frontend product page:\n";
$productFile = 'c:\xampp\htdocs\finalJulio\product.php';
if (file_exists($productFile)) {
    $content = file_get_contents($productFile);
    if (strpos($content, 'meta name="description"') !== false && 
        strpos($content, 'og:title') !== false && 
        strpos($content, 'twitter:card') !== false) {
        echo "  ✓ product.php updated with SEO meta tags\n";
    } else {
        echo "  ✗ product.php missing SEO meta tags\n";
        $allFilesExist = false;
    }
} else {
    echo "  ✗ product.php missing\n";
    $allFilesExist = false;
}

echo "\n=== Verification Summary ===\n";
if ($allFilesExist) {
    echo "✓ ALL CHECKS PASSED - SEO module is ready for use!\n";
    echo "\nAccess the module at: http://localhost/finalJulio/admin/seo-management.php\n";
    echo "API endpoint: http://localhost/finalJulio/admin/api/seo-api.php\n";
    echo "Documentation: http://localhost/finalJulio/admin/seo-module-documentation.md\n";
} else {
    echo "✗ SOME CHECKS FAILED - Please review the errors above\n";
}

echo "\n=== Module Features ===\n";
echo "1. Merchant SEO Controls - Add/edit SEO settings when creating products\n";
echo "2. Admin SEO Management - Centralized SEO management for all products\n";
echo "3. Social Media Optimization - Open Graph and Twitter Card support\n";
echo "4. Structured Data - Schema.org markup for rich snippets\n";
echo "5. Bulk Operations - Update SEO settings for multiple products\n";
echo "6. SEO Status Monitoring - Track SEO completeness across products\n";
?>