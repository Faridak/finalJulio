<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

// Allow CORS for API access
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_product_seo':
            $productId = intval($_GET['product_id'] ?? 0);
            if (!$productId) {
                throw new Exception("Product ID is required");
            }
            
            // Get SEO data for product
            $stmt = $pdo->prepare("
                SELECT id, meta_title, meta_description, meta_keywords,
                       og_title, og_description, og_image,
                       twitter_title, twitter_description, twitter_image
                FROM products 
                WHERE id = ?
            ");
            $stmt->execute([$productId]);
            $seoData = $stmt->fetch();
            
            if (!$seoData) {
                throw new Exception("Product not found");
            }
            
            echo json_encode([
                'success' => true,
                'data' => $seoData
            ]);
            break;
            
        case 'update_product_seo':
            $productId = intval($_POST['product_id'] ?? 0);
            if (!$productId) {
                throw new Exception("Product ID is required");
            }
            
            // SEO fields
            $metaTitle = trim($_POST['meta_title'] ?? '');
            $metaDescription = trim($_POST['meta_description'] ?? '');
            $metaKeywords = trim($_POST['meta_keywords'] ?? '');
            $ogTitle = trim($_POST['og_title'] ?? '');
            $ogDescription = trim($_POST['og_description'] ?? '');
            $ogImage = trim($_POST['og_image'] ?? '');
            $twitterTitle = trim($_POST['twitter_title'] ?? '');
            $twitterDescription = trim($_POST['twitter_description'] ?? '');
            $twitterImage = trim($_POST['twitter_image'] ?? '');
            
            // Update SEO data
            $stmt = $pdo->prepare("
                UPDATE products 
                SET meta_title = ?, meta_description = ?, meta_keywords = ?,
                    og_title = ?, og_description = ?, og_image = ?,
                    twitter_title = ?, twitter_description = ?, twitter_image = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $metaTitle, $metaDescription, $metaKeywords,
                $ogTitle, $ogDescription, $ogImage,
                $twitterTitle, $twitterDescription, $twitterImage,
                $productId
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'SEO settings updated successfully'
            ]);
            break;
            
        case 'get_seo_statistics':
            // Get SEO statistics
            $totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            $completeSeo = $pdo->query("SELECT COUNT(*) FROM products WHERE meta_title IS NOT NULL AND meta_title != ''")->fetchColumn();
            $missingSeo = $pdo->query("SELECT COUNT(*) FROM products WHERE meta_title IS NULL OR meta_title = ''")->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_products' => intval($totalProducts),
                    'complete_seo' => intval($completeSeo),
                    'missing_seo' => intval($missingSeo),
                    'completion_rate' => $totalProducts > 0 ? round(($completeSeo/$totalProducts)*100, 1) : 0
                ]
            ]);
            break;
            
        default:
            throw new Exception("Invalid action specified");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>