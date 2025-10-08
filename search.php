<?php
require_once 'config/database.php';
require_once 'includes/ProductSearch.php';

$productSearch = new ProductSearch($pdo);

// Get search parameters
$searchParams = [
    'query' => trim($_GET['q'] ?? ''),
    'category' => $_GET['category'] ?? '',
    'min_price' => floatval($_GET['min_price'] ?? 0),
    'max_price' => floatval($_GET['max_price'] ?? 999999),
    'rating' => intval($_GET['rating'] ?? 0),
    'merchant_id' => intval($_GET['merchant_id'] ?? 0),
    'in_stock' => isset($_GET['in_stock']),
    'sort_by' => $_GET['sort'] ?? 'relevance',
    'sort_order' => $_GET['order'] ?? 'desc',
    'page' => max(1, intval($_GET['page'] ?? 1)),
    'limit' => min(48, max(12, intval($_GET['limit'] ?? 24))),
    'filters' => [
        'has_images' => isset($_GET['has_images']),
        'free_shipping' => isset($_GET['free_shipping']),
        'new_arrivals' => isset($_GET['new_arrivals']),
        'on_sale' => isset($_GET['on_sale'])
    ]
];

// Perform search
$searchResults = $productSearch->searchProducts($searchParams);

// Track search if there's a query
if (!empty($searchParams['query'])) {
    $userId = $_SESSION['user_id'] ?? null;
    $productSearch->trackSearch($searchParams['query'], $userId, $searchResults['total_results']);
}

// Get filter options
$filterOptions = $productSearch->getFilterOptions();

// Build URL for filters
function buildFilterUrl($params, $overrides = []) {
    $merged = array_merge($params, $overrides);
    $query = [];
    
    foreach ($merged as $key => $value) {
        if ($key === 'filters' && is_array($value)) {
            foreach ($value as $filterKey => $filterValue) {
                if ($filterValue) {
                    $query[$filterKey] = '1';
                }
            }
        } elseif ($value && $key !== 'filters') {
            $query[$key] = $value;
        }
    }
    
    return 'search.php?' . http_build_query($query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !empty($searchParams['query']) ? 'Search Results for "' . htmlspecialchars($searchParams['query']) . '"' : 'Search Products' ?> - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Search Header -->
        <div class="mb-8">
            <?php if (!empty($searchParams['query'])): ?>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    Search Results for "<?= htmlspecialchars($searchParams['query']) ?>"
                </h1>
                <p class="text-gray-600">
                    Found <?= number_format($searchResults['total_results']) ?> product<?= $searchResults['total_results'] != 1 ? 's' : '' ?>
                </p>
            <?php else: ?>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">All Products</h1>
                <p class="text-gray-600">
                    Showing <?= number_format($searchResults['total_results']) ?> product<?= $searchResults['total_results'] != 1 ? 's' : '' ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Applied Filters -->
        <?php if (!empty($searchResults['filters_applied'])): ?>
            <div class="mb-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-gray-700">Active filters:</span>
                    <?php foreach ($searchResults['filters_applied'] as $key => $label): ?>
                        <span class="inline-flex items-center px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-full">
                            <?= htmlspecialchars($label) ?>
                            <a href="<?= buildFilterUrl($searchParams, [$key => '']) ?>" 
                               class="ml-2 text-blue-600 hover:text-blue-800">
                                <i class="fas fa-times text-xs"></i>
                            </a>
                        </span>
                    <?php endforeach; ?>
                    <a href="search.php<?= !empty($searchParams['query']) ? '?q=' . urlencode($searchParams['query']) : '' ?>" 
                       class="text-sm text-red-600 hover:text-red-800 font-medium">
                        Clear all filters
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Filters Sidebar -->
            <div class="lg:w-1/4">
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>

                    <!-- Advanced Search Form -->
                    <form method="GET" class="space-y-6">
                        <input type="hidden" name="q" value="<?= htmlspecialchars($searchParams['query']) ?>">
                        
                        <!-- Categories -->
                        <?php if (!empty($filterOptions['categories'])): ?>
                            <div>
                                <h4 class="font-medium text-gray-900 mb-3">Categories</h4>
                                <div class="space-y-2 max-h-48 overflow-y-auto">
                                    <label class="flex items-center">
                                        <input type="radio" name="category" value="" 
                                               <?= empty($searchParams['category']) ? 'checked' : '' ?>
                                               class="text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">All Categories</span>
                                    </label>
                                    <?php foreach ($filterOptions['categories'] as $category): ?>
                                        <label class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <input type="radio" name="category" value="<?= htmlspecialchars($category['category']) ?>"
                                                       <?= $searchParams['category'] === $category['category'] ? 'checked' : '' ?>
                                                       class="text-blue-600 focus:ring-blue-500">
                                                <span class="ml-2 text-sm text-gray-700"><?= htmlspecialchars($category['category']) ?></span>
                                            </div>
                                            <span class="text-xs text-gray-500">(<?= $category['count'] ?>)</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Price Range -->
                        <div>
                            <h4 class="font-medium text-gray-900 mb-3">Price Range</h4>
                            <div class="space-y-2">
                                <?php foreach ($filterOptions['price_ranges'] as $range): ?>
                                    <label class="flex items-center">
                                        <input type="radio" name="price_range" 
                                               value="<?= $range['min'] ?>-<?= $range['max'] ?>"
                                               <?= ($searchParams['min_price'] == $range['min'] && $searchParams['max_price'] == $range['max']) ? 'checked' : '' ?>
                                               onchange="updatePriceRange(<?= $range['min'] ?>, <?= $range['max'] ?>)"
                                               class="text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700"><?= $range['label'] ?></span>
                                    </label>
                                <?php endforeach; ?>
                                <div class="pt-2">
                                    <label class="flex items-center mb-2">
                                        <input type="radio" name="price_range" value="custom" 
                                               class="text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Custom Range</span>
                                    </label>
                                    <div class="flex space-x-2">
                                        <input type="number" name="min_price" placeholder="Min" 
                                               value="<?= $searchParams['min_price'] > 0 ? $searchParams['min_price'] : '' ?>"
                                               class="w-full text-sm border border-gray-300 rounded px-2 py-1">
                                        <input type="number" name="max_price" placeholder="Max"
                                               value="<?= $searchParams['max_price'] < 999999 ? $searchParams['max_price'] : '' ?>"
                                               class="w-full text-sm border border-gray-300 rounded px-2 py-1">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rating -->
                        <div>
                            <h4 class="font-medium text-gray-900 mb-3">Customer Rating</h4>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" name="rating" value="0" 
                                           <?= $searchParams['rating'] == 0 ? 'checked' : '' ?>
                                           class="text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">Any Rating</span>
                                </label>
                                <?php foreach ($filterOptions['ratings'] as $rating): ?>
                                    <label class="flex items-center">
                                        <input type="radio" name="rating" value="<?= $rating['value'] ?>"
                                               <?= $searchParams['rating'] == $rating['value'] ? 'checked' : '' ?>
                                               class="text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700"><?= $rating['label'] ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Additional Filters -->
                        <div>
                            <h4 class="font-medium text-gray-900 mb-3">Additional Filters</h4>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="checkbox" name="in_stock" value="1"
                                           <?= $searchParams['in_stock'] ? 'checked' : '' ?>
                                           class="text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">In Stock Only</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="has_images" value="1"
                                           <?= $searchParams['filters']['has_images'] ? 'checked' : '' ?>
                                           class="text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">With Images</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="free_shipping" value="1"
                                           <?= $searchParams['filters']['free_shipping'] ? 'checked' : '' ?>
                                           class="text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">Free Shipping</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="new_arrivals" value="1"
                                           <?= $searchParams['filters']['new_arrivals'] ? 'checked' : '' ?>
                                           class="text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">New Arrivals (30 days)</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="on_sale" value="1"
                                           <?= $searchParams['filters']['on_sale'] ? 'checked' : '' ?>
                                           class="text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">On Sale</span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" 
                                class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 font-medium">
                            Apply Filters
                        </button>
                    </form>
                </div>
            </div>

            <!-- Results Area -->
            <div class="lg:w-3/4">
                <!-- Sort and View Options -->
                <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                        <!-- Results Info -->
                        <div class="text-sm text-gray-600">
                            Showing <?= number_format(($searchResults['page'] - 1) * $searchResults['limit'] + 1) ?>-<?= number_format(min($searchResults['page'] * $searchResults['limit'], $searchResults['total_results'])) ?> 
                            of <?= number_format($searchResults['total_results']) ?> results
                        </div>

                        <!-- Sort Options -->
                        <div class="flex items-center space-x-4">
                            <label class="text-sm font-medium text-gray-700">Sort by:</label>
                            <select onchange="updateSort(this.value)" 
                                    class="text-sm border border-gray-300 rounded px-3 py-1 focus:ring-2 focus:ring-blue-500">
                                <option value="relevance" <?= $searchParams['sort_by'] === 'relevance' ? 'selected' : '' ?>>Relevance</option>
                                <option value="price_low" <?= $searchParams['sort_by'] === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="price_high" <?= $searchParams['sort_by'] === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                                <option value="rating" <?= $searchParams['sort_by'] === 'rating' ? 'selected' : '' ?>>Customer Rating</option>
                                <option value="newest" <?= $searchParams['sort_by'] === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="popularity" <?= $searchParams['sort_by'] === 'popularity' ? 'selected' : '' ?>>Most Popular</option>
                            </select>

                            <select onchange="updateLimit(this.value)" 
                                    class="text-sm border border-gray-300 rounded px-3 py-1 focus:ring-2 focus:ring-blue-500">
                                <option value="12" <?= $searchParams['limit'] === 12 ? 'selected' : '' ?>>12 per page</option>
                                <option value="24" <?= $searchParams['limit'] === 24 ? 'selected' : '' ?>>24 per page</option>
                                <option value="48" <?= $searchParams['limit'] === 48 ? 'selected' : '' ?>>48 per page</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Products Grid -->
                <?php if (empty($searchResults['products'])): ?>
                    <div class="bg-white rounded-lg shadow-md p-12 text-center">
                        <i class="fas fa-search text-gray-300 text-6xl mb-4"></i>
                        <h3 class="text-xl font-medium text-gray-900 mb-2">No products found</h3>
                        <p class="text-gray-600 mb-6">
                            <?php if (!empty($searchParams['query'])): ?>
                                Try adjusting your search terms or filters to find what you're looking for.
                            <?php else: ?>
                                No products match your current filters. Try adjusting your criteria.
                            <?php endif; ?>
                        </p>
                        <a href="search.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            Clear All Filters
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach ($searchResults['products'] as $product): ?>
                            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                                <a href="product.php?id=<?= $product['id'] ?>" class="block">
                                    <div class="aspect-w-1 aspect-h-1 bg-gray-200">
                                        <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://via.placeholder.com/300x300') ?>" 
                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                             class="w-full h-48 object-cover">
                                    </div>
                                    <div class="p-4">
                                        <h3 class="font-medium text-gray-900 text-sm mb-2 line-clamp-2">
                                            <?= htmlspecialchars($product['name']) ?>
                                        </h3>
                                        
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-lg font-bold text-blue-600">
                                                $<?= number_format($product['price'], 2) ?>
                                            </span>
                                            <?php if ($product['average_rating'] > 0): ?>
                                                <div class="flex items-center">
                                                    <div class="flex">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?= $i <= round($product['average_rating']) ? 'text-yellow-400' : 'text-gray-300' ?> text-xs"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <span class="ml-1 text-xs text-gray-600">(<?= $product['review_count'] ?>)</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="text-xs text-gray-500 mb-3">
                                            by <?= htmlspecialchars($product['merchant_name'] ?: $product['merchant_email']) ?>
                                        </div>
                                        
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-gray-600"><?= htmlspecialchars($product['category']) ?></span>
                                            <?php if ($product['stock'] > 0): ?>
                                                <span class="text-green-600">In Stock</span>
                                            <?php else: ?>
                                                <span class="text-red-600">Out of Stock</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($searchResults['total_pages'] > 1): ?>
                        <div class="mt-8 flex justify-center">
                            <nav class="flex space-x-2">
                                <?php if ($searchResults['page'] > 1): ?>
                                    <a href="<?= buildFilterUrl($searchParams, ['page' => $searchResults['page'] - 1]) ?>" 
                                       class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $searchResults['page'] - 2); $i <= min($searchResults['total_pages'], $searchResults['page'] + 2); $i++): ?>
                                    <a href="<?= buildFilterUrl($searchParams, ['page' => $i]) ?>" 
                                       class="px-3 py-2 text-sm <?= $i === $searchResults['page'] ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50' ?> rounded-lg">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($searchResults['page'] < $searchResults['total_pages']): ?>
                                    <a href="<?= buildFilterUrl($searchParams, ['page' => $searchResults['page'] + 1]) ?>" 
                                       class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function updateSort(sortBy) {
            const url = new URL(window.location);
            url.searchParams.set('sort', sortBy);
            url.searchParams.delete('page'); // Reset to page 1
            window.location.href = url.toString();
        }
        
        function updateLimit(limit) {
            const url = new URL(window.location);
            url.searchParams.set('limit', limit);
            url.searchParams.delete('page'); // Reset to page 1
            window.location.href = url.toString();
        }
        
        function updatePriceRange(min, max) {
            const url = new URL(window.location);
            url.searchParams.set('min_price', min);
            url.searchParams.set('max_price', max);
            url.searchParams.delete('page'); // Reset to page 1
        }
    </script>
</body>
</html>