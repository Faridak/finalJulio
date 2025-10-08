<?php
/**
 * CMS Frontend Helper Class
 * Provides methods to retrieve and display CMS content on the frontend
 */

class CMSFrontend {
    private $pdo;
    
    public function __construct($database) {
        $this->pdo = $database;
    }
    
    /**
     * Get active banners by type
     */
    public function getBannersByType($type, $limit = null) {
        $sql = "
            SELECT * FROM frontend_banners 
            WHERE banner_type = ? AND is_active = 1 
            AND (start_date IS NULL OR start_date <= NOW()) 
            AND (end_date IS NULL OR end_date >= NOW())
            ORDER BY sort_order, created_at DESC
        ";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get active carousel items for a banner
     */
    public function getCarouselItems($bannerId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM carousel_items 
            WHERE banner_id = ? AND is_active = 1 
            ORDER BY sort_order, created_at DESC
        ");
        $stmt->execute([$bannerId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get content blocks by section
     */
    public function getContentBlocksBySection($sectionSlug) {
        $stmt = $this->pdo->prepare("
            SELECT cb.* 
            FROM content_blocks cb
            JOIN frontend_sections fs ON cb.section_id = fs.id
            WHERE fs.slug = ? AND cb.is_active = 1
            ORDER BY cb.sort_order, cb.created_at DESC
        ");
        $stmt->execute([$sectionSlug]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get products in a carousel
     */
    public function getProductsInCarousel($carouselName, $limit = null) {
        $sql = "
            SELECT p.*, cp.is_featured, cp.sort_order as carousel_sort
            FROM products p
            JOIN carousel_products cp ON p.id = cp.product_id
            JOIN product_carousels pc ON cp.carousel_id = pc.id
            WHERE pc.name = ? AND pc.is_active = 1
            ORDER BY cp.sort_order, cp.created_at
        ";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$carouselName]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get image details by ID
     */
    public function getImageById($imageId) {
        if (!$imageId) return null;
        
        $stmt = $this->pdo->prepare("SELECT * FROM image_assets WHERE id = ? AND is_active = 1");
        $stmt->execute([$imageId]);
        return $stmt->fetch();
    }
    
    /**
     * Get SEO metadata for a page
     */
    public function getSEOMetadata($pageType, $pageIdentifier = null) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM page_seo_metadata 
            WHERE page_type = ? AND (page_identifier = ? OR page_identifier IS NULL)
            ORDER BY page_identifier DESC
            LIMIT 1
        ");
        $stmt->execute([$pageType, $pageIdentifier]);
        return $stmt->fetch();
    }
    
    /**
     * Get active social posts
     */
    public function getActiveSocialPosts($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM social_posts 
            WHERE status = 'published' 
            ORDER BY published_at DESC 
            LIMIT ?
        ");
        $stmt->execute([intval($limit)]);
        return $stmt->fetchAll();
    }
    
    /**
     * Render banner HTML
     */
    public function renderBanner($banner) {
        $image = $this->getImageById($banner['image_id']);
        $imageUrl = $image ? $image['file_path'] : 'https://via.placeholder.com/1200x400';
        
        $html = '<div class="relative">';
        $html .= '<img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($banner['title']) . '" class="w-full h-auto">';
        
        $html .= '<div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">';
        $html .= '<div class="text-center text-white px-4">';
        
        if ($banner['title']) {
            $html .= '<h2 class="text-4xl font-bold mb-2">' . htmlspecialchars($banner['title']) . '</h2>';
        }
        
        if ($banner['subtitle']) {
            $html .= '<p class="text-xl mb-6">' . htmlspecialchars($banner['subtitle']) . '</p>';
        }
        
        if ($banner['content']) {
            $html .= '<div class="mb-6">' . $banner['content'] . '</div>';
        }
        
        if ($banner['button_text'] && $banner['button_url']) {
            $target = $banner['target'] === '_blank' ? 'target="_blank"' : '';
            $html .= '<a href="' . htmlspecialchars($banner['button_url']) . '" ' . $target . ' class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200">';
            $html .= htmlspecialchars($banner['button_text']);
            $html .= '</a>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render carousel HTML
     */
    public function renderCarousel($carouselId, $items = null) {
        if ($items === null) {
            $items = $this->getCarouselItems($carouselId);
        }
        
        if (empty($items)) {
            return '';
        }
        
        $html = '<div class="carousel-container relative">';
        $html .= '<div class="carousel overflow-hidden">';
        $html .= '<div class="carousel-track flex transition-transform duration-300 ease-in-out">';
        
        foreach ($items as $item) {
            $image = $this->getImageById($item['image_id']);
            $imageUrl = $image ? $image['file_path'] : 'https://via.placeholder.com/800x400';
            
            $html .= '<div class="carousel-slide flex-shrink-0 w-full">';
            $html .= '<div class="relative">';
            $html .= '<img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($item['title'] ?? '') . '" class="w-full h-96 object-cover">';
            
            if ($item['title'] || $item['subtitle'] || $item['content']) {
                $html .= '<div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">';
                $html .= '<div class="text-center text-white px-4">';
                
                if ($item['title']) {
                    $html .= '<h3 class="text-2xl font-bold mb-2">' . htmlspecialchars($item['title']) . '</h3>';
                }
                
                if ($item['subtitle']) {
                    $html .= '<p class="text-lg mb-4">' . htmlspecialchars($item['subtitle']) . '</p>';
                }
                
                if ($item['content']) {
                    $html .= '<div class="mb-4">' . $item['content'] . '</div>';
                }
                
                if ($item['button_text'] && $item['button_url']) {
                    $target = $item['target'] === '_blank' ? 'target="_blank"' : '';
                    $html .= '<a href="' . htmlspecialchars($item['button_url']) . '" ' . $target . ' class="bg-white text-blue-600 hover:bg-gray-100 font-bold py-2 px-4 rounded-lg transition duration-200">';
                    $html .= htmlspecialchars($item['button_text']);
                    $html .= '</a>';
                }
                
                $html .= '</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        // Navigation buttons
        $html .= '<button class="carousel-prev absolute left-4 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-80 hover:bg-opacity-100 rounded-full p-2 shadow-lg">';
        $html .= '<i class="fas fa-chevron-left text-gray-800"></i>';
        $html .= '</button>';
        
        $html .= '<button class="carousel-next absolute right-4 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-80 hover:bg-opacity-100 rounded-full p-2 shadow-lg">';
        $html .= '<i class="fas fa-chevron-right text-gray-800"></i>';
        $html .= '</button>';
        
        // Indicators
        $html .= '<div class="carousel-indicators flex justify-center mt-4 space-x-2">';
        for ($i = 0; $i < count($items); $i++) {
            $activeClass = $i === 0 ? 'bg-white' : 'bg-gray-300';
            $html .= '<button class="indicator w-3 h-3 rounded-full ' . $activeClass . '" data-slide="' . $i . '"></button>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Add JavaScript for carousel functionality
        $html .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const carousels = document.querySelectorAll(".carousel-container");
                carousels.forEach(carousel => {
                    const track = carousel.querySelector(".carousel-track");
                    const slides = carousel.querySelectorAll(".carousel-slide");
                    const prevBtn = carousel.querySelector(".carousel-prev");
                    const nextBtn = carousel.querySelector(".carousel-next");
                    const indicators = carousel.querySelectorAll(".indicator");
                    
                    let currentIndex = 0;
                    const slideWidth = slides[0].offsetWidth;
                    
                    function goToSlide(index) {
                        if (index < 0) index = slides.length - 1;
                        if (index >= slides.length) index = 0;
                        
                        track.style.transform = `translateX(-${index * slideWidth}px)`;
                        currentIndex = index;
                        
                        // Update indicators
                        indicators.forEach((indicator, i) => {
                            indicator.classList.toggle("bg-white", i === currentIndex);
                            indicator.classList.toggle("bg-gray-300", i !== currentIndex);
                        });
                    }
                    
                    if (prevBtn) {
                        prevBtn.addEventListener("click", () => goToSlide(currentIndex - 1));
                    }
                    
                    if (nextBtn) {
                        nextBtn.addEventListener("click", () => goToSlide(currentIndex + 1));
                    }
                    
                    indicators.forEach((indicator, index) => {
                        indicator.addEventListener("click", () => goToSlide(index));
                    });
                    
                    // Auto-rotate every 5 seconds
                    setInterval(() => {
                        goToSlide(currentIndex + 1);
                    }, 5000);
                });
            });
        </script>';
        
        return $html;
    }
    
    /**
     * Render product carousel HTML
     */
    public function renderProductCarousel($products, $title = null, $description = null) {
        if (empty($products)) {
            return '';
        }
        
        $html = '<div class="product-carousel-container">';
        
        if ($title) {
            $html .= '<h2 class="text-2xl font-bold text-center mb-6">' . htmlspecialchars($title) . '</h2>';
        }
        
        if ($description) {
            $html .= '<p class="text-gray-600 text-center mb-8">' . htmlspecialchars($description) . '</p>';
        }
        
        $html .= '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">';
        
        foreach ($products as $product) {
            $html .= '<div class="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-lg transition duration-200">';
            $html .= '<a href="product.php?id=' . $product['id'] . '">';
            $html .= '<img src="' . htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/300x200') . '" 
                         alt="' . htmlspecialchars($product['name']) . '"
                         class="w-full h-48 object-cover">';
            $html .= '</a>';
            $html .= '<div class="p-4">';
            $html .= '<h3 class="font-semibold text-lg mb-2">';
            $html .= '<a href="product.php?id=' . $product['id'] . '" class="hover:text-blue-600">';
            $html .= htmlspecialchars($product['name']);
            $html .= '</a>';
            $html .= '</h3>';
            $html .= '<p class="text-gray-600 text-sm mb-3">' . htmlspecialchars(substr($product['description'], 0, 100)) . '...</p>';
            $html .= '<div class="flex justify-between items-center">';
            $html .= '<span class="text-2xl font-bold text-blue-600">$' . number_format($product['price'], 2) . '</span>';
            $html .= '<button onclick="addToCart(' . $product['id'] . ')" 
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">';
            $html .= 'Add to Cart';
            $html .= '</button>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}