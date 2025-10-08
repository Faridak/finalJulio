<?php
require_once 'config/database.php';
require_once 'includes/ProductReviews.php';

// Initialize reviews system
$reviewsSystem = new ProductReviews($pdo);

// Get product ID from URL
$productId = intval($_GET['id'] ?? 0);

if ($productId <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch product from DB (with prepared statement)
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit;
}

// Fetch merchant info
$merchantStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$merchantStmt->execute([$product['merchant_id']]);
$merchant = $merchantStmt->fetch();

// Handle review submission
$reviewError = '';
$reviewSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    requireCSRF();
    requireLogin();
    
    $rules = [
        'rating' => ['required' => true, 'type' => 'integer', 'min_value' => 1, 'max_value' => 5],
        'title' => ['required' => true, 'max_length' => 255],
        'review_text' => ['required' => true, 'max_length' => 2000]
    ];
    
    $reviewData = Security::sanitizeArray($_POST, [
        'rating' => 'int',
        'title' => 'string',
        'review_text' => 'string'
    ]);
    
    $errors = Security::validateInput($reviewData, $rules);
    
    if (empty($errors)) {
        try {
            $canReview = $reviewsSystem->canUserReviewProduct($_SESSION['user_id'], $productId);
            
            if ($canReview['can_review']) {
                $reviewId = $reviewsSystem->addReview(
                    $productId,
                    $_SESSION['user_id'],
                    $reviewData['rating'],
                    $reviewData['title'],
                    $reviewData['review_text'],
                    $canReview['order_id'] ?? null
                );
                
                if ($reviewId) {
                    $reviewSuccess = 'Your review has been submitted successfully!';
                    // Refresh product data to show updated rating
                    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch();
                }
            } else {
                $reviewError = match($canReview['reason']) {
                    'already_reviewed' => 'You have already reviewed this product.',
                    'no_purchase' => 'You can only review products you have purchased.',
                    default => 'Unable to submit review at this time.'
                };
            }
        } catch (Exception $e) {
            $reviewError = $e->getMessage();
        }
    } else {
        $reviewError = implode(' ', $errors);
    }
}

// Get product reviews
$page = max(1, intval($_GET['review_page'] ?? 1));
$sortBy = $_GET['sort'] ?? 'newest';
$reviewsData = $reviewsSystem->getProductReviews($productId, $page, 5, $sortBy);
$ratingSummary = $reviewsSystem->getProductRatingSummary($productId);

// Check if user can review
$canUserReview = false;
$userReviewStatus = null;
if (isLoggedIn()) {
    $userReviewStatus = $reviewsSystem->canUserReviewProduct($_SESSION['user_id'], $productId);
    $canUserReview = $userReviewStatus['can_review'];
}

// SEO Meta Tags
$metaTitle = !empty($product['meta_title']) ? $product['meta_title'] : $product['name'] . ' - VentDepot';
$metaDescription = !empty($product['meta_description']) ? $product['meta_description'] : substr($product['description'], 0, 160);
$metaKeywords = !empty($product['meta_keywords']) ? $product['meta_keywords'] : $product['category'] . ', ' . $product['name'];

// Open Graph Tags
$ogTitle = !empty($product['og_title']) ? $product['og_title'] : $product['name'];
$ogDescription = !empty($product['og_description']) ? $product['og_description'] : substr($product['description'], 0, 300);
$ogImage = !empty($product['og_image']) ? $product['og_image'] : (!empty($product['image_url']) ? $product['image_url'] : 'https://ventdepot.com/images/default-product.jpg');
$ogUrl = 'https://ventdepot.com/product.php?id=' . $product['id'];

// Twitter Card Tags
$twitterTitle = !empty($product['twitter_title']) ? $product['twitter_title'] : $product['name'];
$twitterDescription = !empty($product['twitter_description']) ? $product['twitter_description'] : substr($product['description'], 0, 200);
$twitterImage = !empty($product['twitter_image']) ? $product['twitter_image'] : (!empty($product['image_url']) ? $product['image_url'] : 'https://ventdepot.com/images/default-product.jpg');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($metaTitle) ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($ogUrl) ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="product">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($ogUrl) ?>">
    <meta property="og:site_name" content="VentDepot">
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($twitterTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($twitterDescription) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($twitterImage) ?>">
    
    <!-- Schema.org for Google -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "Product",
        "name": "<?= htmlspecialchars($product['name']) ?>",
        "image": "<?= htmlspecialchars($product['image_url']) ?>",
        "description": "<?= htmlspecialchars(substr($product['description'], 0, 300)) ?>",
        "sku": "<?= $product['id'] ?>",
        "offers": {
            "@type": "Offer",
            "url": "<?= htmlspecialchars($ogUrl) ?>",
            "priceCurrency": "USD",
            "price": "<?= $product['price'] ?>",
            "availability": "<?= $product['stock'] > 0 ? 'InStock' : 'OutOfStock' ?>"
        }
        <?php if ($ratingSummary['average_rating'] > 0): ?>
        ,"aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "<?= $ratingSummary['average_rating'] ?>",
            "bestRating": "5",
            "worstRating": "1",
            "ratingCount": "<?= $ratingSummary['total_reviews'] ?>"
        }
        <?php endif; ?>
    }
    </script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8">
  <!-- Product Images -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="flex flex-col gap-4">
      <?php foreach (explode(',', $product['image_urls']) as $img): ?>
        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="rounded-lg w-full h-80 object-cover">
      <?php endforeach; ?>
    </div>

    <!-- Product Info -->
    <div class="space-y-6">
      <h1 class="text-3xl font-bold"><?= htmlspecialchars($product['name']) ?></h1>
      
      <!-- Color Swatches -->
      <div class="flex gap-2">
        <?php foreach (['#ff0000', '#00ff00', '#0000ff'] as $color): ?>
          <button 
            class="w-8 h-8 rounded-full border-2 border-gray-300 focus:ring-2 focus:ring-blue-500"
            style="background-color: <?= $color ?>">
          </button>
        <?php endforeach; ?>
      </div>

      <p class="text-2xl font-bold">$<?= number_format($product['price'], 2) ?></p>
      
      <!-- Add to Cart Button (with animation) -->
      <button 
        id="addToCart"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-lg transition duration-200"
        data-product-id="<?= $product['id'] ?>">
        Add to Cart
      </button>
      
      <!-- Contact Seller Button -->
      <?php include 'includes/contact-seller-modal.php'; ?>
    </div>
  </div>
</div>

<!-- Product Description -->
<div class="mt-12">
  <h2 class="text-2xl font-semibold mb-4">Product Description</h2>
  <div class="bg-white rounded-lg p-6 shadow-md">
    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <h3 class="font-semibold text-gray-900 mb-2">Product Details</h3>
        <ul class="space-y-1 text-gray-600">
          <li><strong>Category:</strong> <?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?></li>
          <li><strong>Stock:</strong> <?= $product['stock'] ?> available</li>
          <li><strong>Sold by:</strong> <?= htmlspecialchars($merchant['email'] ?? 'Unknown') ?></li>
          <?php if ($ratingSummary['average_rating'] > 0): ?>
            <li><strong>Rating:</strong> 
              <span class="inline-flex items-center">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <i class="fas fa-star <?= $i <= round($ratingSummary['average_rating']) ? 'text-yellow-400' : 'text-gray-300' ?> text-sm"></i>
                <?php endfor; ?>
                <span class="ml-1 text-sm"><?= number_format($ratingSummary['average_rating'], 1) ?> (<?= $ratingSummary['total_reviews'] ?> reviews)</span>
              </span>
            </li>
          <?php endif; ?>
        </ul>
      </div>
      <div>
        <h3 class="font-semibold text-gray-900 mb-2">Shipping & Returns</h3>
        <ul class="space-y-1 text-gray-600">
          <li>✓ Free shipping on orders over $50</li>
          <li>✓ 30-day return policy</li>
          <li>✓ Secure payment processing</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Reviews Section -->
<div class="mt-12">
  <div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-semibold">Customer Reviews</h2>
    <?php if ($canUserReview): ?>
      <button onclick="document.getElementById('review-form').scrollIntoView()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
        Write a Review
      </button>
    <?php endif; ?>
  </div>

  <?php if ($ratingSummary['total_reviews'] > 0): ?>
    <!-- Rating Summary -->
    <div class="bg-white rounded-lg p-6 shadow-md mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="text-center">
          <div class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($ratingSummary['average_rating'], 1) ?></div>
          <div class="flex justify-center mb-2">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <i class="fas fa-star <?= $i <= round($ratingSummary['average_rating']) ? 'text-yellow-400' : 'text-gray-300' ?> text-xl"></i>
            <?php endfor; ?>
          </div>
          <div class="text-gray-600"><?= $ratingSummary['total_reviews'] ?> review<?= $ratingSummary['total_reviews'] != 1 ? 's' : '' ?></div>
          <?php if ($ratingSummary['verified_purchases'] > 0): ?>
            <div class="text-sm text-green-600 mt-1"><?= $ratingSummary['verified_purchases'] ?> verified purchases</div>
          <?php endif; ?>
        </div>
        
        <div>
          <?php 
          $starCounts = [
            5 => $ratingSummary['five_star'],
            4 => $ratingSummary['four_star'], 
            3 => $ratingSummary['three_star'],
            2 => $ratingSummary['two_star'],
            1 => $ratingSummary['one_star']
          ];
          ?>
          <?php foreach ($starCounts as $stars => $count): ?>
            <div class="flex items-center mb-2">
              <span class="text-sm w-8"><?= $stars ?></span>
              <i class="fas fa-star text-yellow-400 text-sm mr-2"></i>
              <div class="flex-1 bg-gray-200 rounded-full h-2 mr-3">
                <div class="bg-yellow-400 h-2 rounded-full" style="width: <?= $ratingSummary['total_reviews'] > 0 ? ($count / $ratingSummary['total_reviews'] * 100) : 0 ?>%"></div>
              </div>
              <span class="text-sm text-gray-600 w-8"><?= $count ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Review Form -->
  <?php if ($canUserReview): ?>
    <div id="review-form" class="bg-white rounded-lg p-6 shadow-md mb-6">
      <h3 class="text-lg font-semibold mb-4">Write Your Review</h3>
      
      <?php if ($reviewError): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <?= htmlspecialchars($reviewError) ?>
        </div>
      <?php endif; ?>
      
      <?php if ($reviewSuccess): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
          <?= htmlspecialchars($reviewSuccess) ?>
        </div>
      <?php endif; ?>
      
      <form method="POST" class="space-y-4">
        <?= Security::getCSRFInput() ?>
        
        <!-- Rating -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Rating *</label>
          <div class="flex space-x-1" x-data="{ rating: 0, hoverRating: 0 }">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <button type="button" 
                      @click="rating = <?= $i ?>" 
                      @mouseover="hoverRating = <?= $i ?>" 
                      @mouseleave="hoverRating = 0"
                      class="text-2xl focus:outline-none transition-colors duration-200"
                      :class="(hoverRating >= <?= $i ?> || (hoverRating === 0 && rating >= <?= $i ?>)) ? 'text-yellow-400' : 'text-gray-300'">
                <i class="fas fa-star"></i>
              </button>
            <?php endfor; ?>
            <input type="hidden" name="rating" :value="rating" required>
          </div>
        </div>
        
        <!-- Title -->
        <div>
          <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Review Title *</label>
          <input type="text" name="title" id="title" required maxlength="255"
                 class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                 placeholder="Summarize your experience">
        </div>
        
        <!-- Review Text -->
        <div>
          <label for="review_text" class="block text-sm font-medium text-gray-700 mb-2">Your Review *</label>
          <textarea name="review_text" id="review_text" rows="4" required maxlength="2000"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                    placeholder="Share your thoughts about this product..."></textarea>
          <div class="text-sm text-gray-500 mt-1">Maximum 2000 characters</div>
        </div>
        
        <button type="submit" name="submit_review" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
          Submit Review
        </button>
      </form>
    </div>
  <?php elseif (isLoggedIn() && !$canUserReview): ?>
    <div class="bg-gray-100 rounded-lg p-6 mb-6">
      <div class="text-center text-gray-600">
        <?php if ($userReviewStatus['reason'] === 'already_reviewed'): ?>
          <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
          <p>You have already reviewed this product.</p>
        <?php else: ?>
          <i class="fas fa-shopping-cart text-gray-400 text-2xl mb-2"></i>
          <p>Purchase this product to leave a review.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php elseif (!isLoggedIn()): ?>
    <div class="bg-gray-100 rounded-lg p-6 mb-6">
      <div class="text-center text-gray-600">
        <i class="fas fa-user text-gray-400 text-2xl mb-2"></i>
        <p><a href="login.php" class="text-blue-600 hover:text-blue-800">Login</a> to write a review.</p>
      </div>
    </div>
  <?php endif; ?>

  <!-- Reviews List -->
  <?php if (!empty($reviewsData['reviews'])): ?>
    <div class="bg-white rounded-lg shadow-md">
      <!-- Sort Options -->
      <div class="p-4 border-b">
        <div class="flex flex-wrap items-center gap-4">
          <span class="text-sm font-medium text-gray-700">Sort by:</span>
          <select onchange="window.location.href='?id=<?= $productId ?>&sort=' + this.value" class="text-sm border border-gray-300 rounded px-2 py-1">
            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest</option>
            <option value="highest_rated" <?= $sortBy === 'highest_rated' ? 'selected' : '' ?>>Highest Rated</option>
            <option value="lowest_rated" <?= $sortBy === 'lowest_rated' ? 'selected' : '' ?>>Lowest Rated</option>
            <option value="most_helpful" <?= $sortBy === 'most_helpful' ? 'selected' : '' ?>>Most Helpful</option>
          </select>
        </div>
      </div>
      
      <!-- Reviews -->
      <div class="divide-y divide-gray-200">
        <?php foreach ($reviewsData['reviews'] as $review): ?>
          <div class="p-6">
            <div class="flex items-start justify-between mb-3">
              <div>
                <div class="flex items-center mb-1">
                  <span class="font-medium text-gray-900">
                    <?= htmlspecialchars(trim($review['first_name'] . ' ' . $review['last_name']) ?: 'Anonymous') ?>
                  </span>
                  <?php if ($review['is_verified_purchase']): ?>
                    <span class="ml-2 bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Verified Purchase</span>
                  <?php endif; ?>
                </div>
                <div class="flex items-center">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300' ?> text-sm"></i>
                  <?php endfor; ?>
                  <span class="ml-2 text-sm text-gray-600"><?= date('M j, Y', strtotime($review['created_at'])) ?></span>
                </div>
              </div>
            </div>
            
            <?php if ($review['title']): ?>
              <h4 class="font-medium text-gray-900 mb-2"><?= htmlspecialchars($review['title']) ?></h4>
            <?php endif; ?>
            
            <p class="text-gray-700 mb-3"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
            
            <?php if ($review['admin_response']): ?>
              <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-3">
                <div class="text-sm font-medium text-blue-800 mb-1">Response from VentDepot:</div>
                <div class="text-sm text-blue-700"><?= nl2br(htmlspecialchars($review['admin_response'])) ?></div>
              </div>
            <?php endif; ?>
            
            <!-- Review Actions -->
            <?php if (isLoggedIn()): ?>
              <div class="flex items-center space-x-4 text-sm">
                <span class="text-gray-600">Was this helpful?</span>
                <button onclick="voteOnReview(<?= $review['id'] ?>, 'helpful')" class="text-blue-600 hover:text-blue-800">
                  <i class="fas fa-thumbs-up mr-1"></i>Yes (<?= $review['helpful_votes'] ?>)
                </button>
                <button onclick="voteOnReview(<?= $review['id'] ?>, 'unhelpful')" class="text-blue-600 hover:text-blue-800">
                  <i class="fas fa-thumbs-down mr-1"></i>No (<?= $review['unhelpful_votes'] ?>)
                </button>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      
      <!-- Pagination -->
      <?php if ($reviewsData['total_pages'] > 1): ?>
        <div class="p-4 border-t">
          <div class="flex justify-center space-x-2">
            <?php for ($i = 1; $i <= $reviewsData['total_pages']; $i++): ?>
              <a href="?id=<?= $productId ?>&review_page=<?= $i ?>&sort=<?= urlencode($sortBy) ?>" 
                 class="px-3 py-2 text-sm <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> rounded">
                <?= $i ?>
              </a>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-lg p-8 shadow-md text-center">
      <i class="fas fa-star text-gray-300 text-4xl mb-4"></i>
      <h3 class="text-lg font-medium text-gray-900 mb-2">No Reviews Yet</h3>
      <p class="text-gray-600">Be the first to review this product!</p>
    </div>
  <?php endif; ?>
</div>

<!-- Shipping Calculator (Mock) -->
<div class="mt-12 bg-gray-50 p-6 rounded-lg">
  <h2 class="text-xl font-semibold mb-4">Shipping Options</h2>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <?php foreach (['Standard ($5.99)', 'Express ($12.99)', 'Free Over $50'] as $option): ?>
      <div class="border rounded p-4 hover:border-blue-500 cursor-pointer">
        <p><?= $option ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</div>

</div>

<script>
function addToCart(productId) {
    fetch('api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add',
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            const button = document.getElementById('addToCart');
            const originalText = button.textContent;
            button.textContent = 'Added to Cart!';
            button.classList.add('bg-green-600');
            button.classList.remove('bg-blue-600');

            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('bg-green-600');
                button.classList.add('bg-blue-600');
            }, 2000);

            // Update cart count in navigation if needed
            location.reload();
        } else {
            alert('Error adding to cart: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding to cart');
    });
}

// Add click event to the add to cart button
document.getElementById('addToCart').addEventListener('click', function() {
    const productId = this.getAttribute('data-product-id');
    addToCart(productId);
});

// Review voting function
function voteOnReview(reviewId, voteType) {
    fetch('api/reviews.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'vote',
            review_id: reviewId,
            vote_type: voteType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload page to show updated vote counts
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to record vote'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error recording vote');
    });
}
</script>

</body>
</html>
