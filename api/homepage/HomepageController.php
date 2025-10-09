<?php

require_once __DIR__ . '/../includes/Controller.php';

class HomepageController extends Controller {
    
    public function getHomepageData() {
        try {
            // Get active banners
            $banners = $this->getActiveBanners();
            
            // Get featured products
            $featuredProducts = $this->getFeaturedProducts();
            
            // Get active popups
            $popups = $this->getActivePopups();
            
            // Get active CTA buttons
            $ctaButtons = $this->getActiveCtaButtons();
            
            // Get homepage layout
            $layout = $this->getHomepageLayout();
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => [
                    'banners' => $banners,
                    'featured_products' => $featuredProducts,
                    'popups' => $popups,
                    'cta_buttons' => $ctaButtons,
                    'layout' => $layout
                ]
            ]);
        } catch (Exception $e) {
            $this->sendErrorResponse('Failed to fetch homepage data: ' . $e->getMessage());
        }
    }
    
    public function getBanners() {
        if (!$this->isAdmin()) {
            $this->sendErrorResponse('Unauthorized access', 403);
        }
        
        try {
            $banners = $this->getAllBanners();
            $this->sendJsonResponse([
                'success' => true,
                'data' => $banners
            ]);
        } catch (Exception $e) {
            $this->sendErrorResponse('Failed to fetch banners: ' . $e->getMessage());
        }
    }
    
    public function createBanner($data) {
        if (!$this->isAdmin()) {
            $this->sendErrorResponse('Unauthorized access', 403);
        }
        
        if (!$this->validateRequiredFields($data, ['title', 'image_url'])) {
            $this->sendErrorResponse('Title and image URL are required');
        }
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO homepage_banners 
                (title, image_url, text_overlay, button_text, button_link, is_active, start_date, end_date, sort_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['title'],
                $data['image_url'],
                $data['text_overlay'] ?? null,
                $data['button_text'] ?? null,
                $data['button_link'] ?? null,
                $data['is_active'] ?? true,
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['sort_order'] ?? 0
            ]);
            
            $bannerId = $this->db->lastInsertId();
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => [
                    'id' => $bannerId,
                    'message' => 'Banner created successfully'
                ]
            ], 201);
        } catch (Exception $e) {
            $this->sendErrorResponse('Failed to create banner: ' . $e->getMessage());
        }
    }
    
    public function updateBanner($id, $data) {
        if (!$this->isAdmin()) {
            $this->sendErrorResponse('Unauthorized access', 403);
        }
        
        try {
            $fields = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, ['title', 'image_url', 'text_overlay', 'button_text', 'button_link', 'is_active', 'start_date', 'end_date', 'sort_order'])) {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }
            
            if (empty($fields)) {
                $this->sendErrorResponse('No valid fields to update');
            }
            
            $values[] = $id;
            
            $sql = "UPDATE homepage_banners SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => ['message' => 'Banner updated successfully']
            ]);
        } catch (Exception $e) {
            $this->sendErrorResponse('Failed to update banner: ' . $e->getMessage());
        }
    }
    
    public function deleteBanner($id) {
        if (!$this->isAdmin()) {
            $this->sendErrorResponse('Unauthorized access', 403);
        }
        
        try {
            $stmt = $this->db->prepare("DELETE FROM homepage_banners WHERE id = ?");
            $stmt->execute([$id]);
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => ['message' => 'Banner deleted successfully']
            ]);
        } catch (Exception $e) {
            $this->sendErrorResponse('Failed to delete banner: ' . $e->getMessage());
        }
    }
    
    // Similar methods for featured products, popups, CTA buttons, and layout management
    // For brevity, I'll include just a few more key methods
    
    public function getFeaturedProducts() {
        if (!$this->isAdmin()) {
            $this->sendErrorResponse('Unauthorized access', 403);
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT fp.*, p.name as product_name, p.price as product_price, p.image as product_image
                FROM homepage_featured_products fp
                LEFT JOIN products p ON fp.product_id = p.id
                ORDER BY fp.sort_order ASC
            ");
            $stmt->execute();
            $products = $stmt->fetchAll();
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => $products
            ]);
        } catch (Exception $e) {
            $this->sendErrorResponse('Failed to fetch featured products: ' . $e->getMessage());
        }
    }
    
    public function updateLayout($data) {
        if (!$this->isAdmin()) {
            $this->sendErrorResponse('Unauthorized access', 403);
        }
        
        try {
            // First, clear existing layout
            $this->db->prepare("DELETE FROM homepage_layout")->execute();
            
            // Insert new layout
            $stmt = $this->db->prepare("INSERT INTO homepage_layout (section_id, position, is_visible) VALUES (?, ?, ?)");
            
            foreach ($data as $item) {
                $stmt->execute([
                    $item['section_id'],
                    $item['position'],
                    $item['is_visible'] ?? true
                ]);
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => ['message' => 'Layout updated successfully']
            ]);
        } catch (Exception $e) {
            $this->sendErrorResponse('Failed to update layout: ' . $e->getMessage());
        }
    }
    
    // Private methods for internal use
    private function getActiveBanners() {
        $stmt = $this->db->prepare("
            SELECT * FROM homepage_banners 
            WHERE is_active = 1 
            AND (start_date IS NULL OR start_date <= NOW()) 
            AND (end_date IS NULL OR end_date >= NOW())
            ORDER BY sort_order ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    private function getAllBanners() {
        $stmt = $this->db->prepare("SELECT * FROM homepage_banners ORDER BY sort_order ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    private function getFeaturedProductsForHomepage() {
        $stmt = $this->db->prepare("
            SELECT fp.*, p.name as product_name, p.price as product_price, p.image as product_image
            FROM homepage_featured_products fp
            LEFT JOIN products p ON fp.product_id = p.id
            WHERE fp.is_active = 1 
            AND (fp.start_date IS NULL OR fp.start_date <= NOW()) 
            AND (fp.end_date IS NULL OR fp.end_date >= NOW())
            ORDER BY fp.sort_order ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    private function getActivePopups() {
        $stmt = $this->db->prepare("
            SELECT * FROM homepage_popups 
            WHERE is_active = 1 
            AND (start_date IS NULL OR start_date <= NOW()) 
            AND (end_date IS NULL OR end_date >= NOW())
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    private function getActiveCtaButtons() {
        $stmt = $this->db->prepare("
            SELECT * FROM homepage_cta_buttons 
            WHERE is_active = 1 
            AND (start_date IS NULL OR start_date <= NOW()) 
            AND (end_date IS NULL OR end_date >= NOW())
            ORDER BY sort_order ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    private function getHomepageLayout() {
        $stmt = $this->db->prepare("
            SELECT hs.*, hl.position, hl.is_visible
            FROM homepage_sections hs
            JOIN homepage_layout hl ON hs.id = hl.section_id
            WHERE hl.is_visible = 1
            ORDER BY hl.position ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}