<?php
// Mega-menu data: top categories by product count, with their busiest
// subcategories. One cheap query, shared by every page that includes the nav.
$navMegaTop = $pdo->query("
    SELECT c.id, c.name,
           (SELECT COUNT(*) FROM products p LEFT JOIN categories s2 ON p.category_id = s2.id
             WHERE p.inventory > 0 AND (p.category_id = c.id OR s2.parent_id = c.id)) AS n
    FROM categories c
    WHERE c.parent_id IS NULL
    HAVING n > 0
    ORDER BY n DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
$navMegaSubs = [];
if ($navMegaTop) {
    $ids = implode(',', array_map(fn($c) => (int) $c['id'], $navMegaTop));
    foreach ($pdo->query("
        SELECT c.parent_id, c.id, c.name, COUNT(p.id) n
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id AND p.inventory > 0
        WHERE c.parent_id IN ($ids)
        GROUP BY c.parent_id, c.id, c.name
        HAVING n > 0
        ORDER BY n DESC
    ") as $s) {
        if (count($navMegaSubs[$s['parent_id']] ?? []) < 6) $navMegaSubs[$s['parent_id']][] = $s;
    }
}
?>
<!-- Navigation -->
<nav class="bg-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center py-4">
            <div class="flex items-center space-x-2 sm:space-x-4">
                <a href="index.php" class="text-xl sm:text-2xl font-bold text-blue-600">VentDepot</a>
                <!-- Categories mega-menu -->
                <div class="relative" id="megaMenuWrap">
                    <button id="megaMenuBtn" class="flex items-center gap-1 text-gray-700 hover:text-blue-600 font-medium px-2 py-1">
                        <i class="fas fa-bars text-sm"></i> <span class="hidden sm:inline">Categories</span> <i class="fas fa-chevron-down text-xs hidden sm:inline"></i>
                    </button>
                    <div id="megaMenuPanel" class="hidden absolute left-0 top-full mt-2 w-[44rem] max-w-[90vw] bg-white rounded-lg shadow-2xl border border-gray-100 p-5 z-50">
                        <div class="grid grid-cols-2 gap-x-8 gap-y-4 max-h-[28rem] overflow-y-auto">
                            <?php foreach ($navMegaTop as $mc): ?>
                                <div>
                                    <a href="browse.php?category_id=<?= (int)$mc['id'] ?>" class="font-semibold text-gray-900 hover:text-blue-600 text-sm flex justify-between">
                                        <span class="truncate"><?= htmlspecialchars($mc['name']) ?></span>
                                        <span class="text-gray-300 font-normal ml-2"><?= (int)$mc['n'] ?></span>
                                    </a>
                                    <?php foreach ($navMegaSubs[$mc['id']] ?? [] as $ms): ?>
                                        <a href="browse.php?category_id=<?= (int)$ms['id'] ?>" class="block text-sm text-gray-500 hover:text-blue-600 truncate pl-2 mt-0.5"><?= htmlspecialchars($ms['name']) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="browse.php" class="block mt-4 pt-3 border-t text-sm text-blue-600 hover:underline">Browse all products →</a>
                    </div>
                </div>
            </div>
            
            <!-- Search Bar (hidden on phones — icon link instead) -->
            <div class="flex-1 max-w-lg mx-8 hidden md:block">
                <form action="browse.php" method="GET" class="relative">
                    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" 
                           placeholder="Search products..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <button type="submit" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            
            <!-- User Menu -->
            <div class="flex items-center space-x-2 sm:space-x-4">
                <a href="browse.php" class="md:hidden text-gray-600 hover:text-blue-600" aria-label="Search">
                    <i class="fas fa-search text-xl"></i>
                </a>
                <a href="cart.php" class="relative text-gray-600 hover:text-blue-600">
                    <i class="fas fa-shopping-cart text-xl"></i>
                    <?php if (getCartCount() > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?= getCartCount() ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <?php if (isLoggedIn()): ?>
                    <div class="relative">
                        <button id="userMenuButton" class="flex items-center space-x-2 text-gray-600 hover:text-blue-600 focus:outline-none">
                            <i class="fas fa-user"></i>
                            <span class="hidden sm:inline"><?= htmlspecialchars($_SESSION['user_email'] ?? 'User') ?></span>
                            <i class="fas fa-chevron-down text-sm"></i>
                        </button>
                        <div id="userMenuDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                            <a href="orders.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Orders</a>
                            <a href="wishlist.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Wishlist</a>
                            <a href="messages.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Messages</a>
                            <a href="disputes.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Disputes</a>
                            <?php if (getUserRole() === 'merchant'): ?>
                                <a href="merchant/dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Merchant Dashboard</a>
                            <?php endif; ?>
                            <?php if (getUserRole() === 'admin'): ?>
                                <a href="admin/dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Admin Dashboard</a>
                                <a href="sales-dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Engineering Sales</a>
                                <a href="engineering-dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Engineering Tasks</a>
                            <?php endif; ?>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="text-gray-600 hover:text-blue-600">Login</a>
                    <a href="register.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Categories mega-menu
    const megaBtn = document.getElementById('megaMenuBtn');
    const megaPanel = document.getElementById('megaMenuPanel');
    if (megaBtn && megaPanel) {
        megaBtn.addEventListener('click', function(e) { e.stopPropagation(); megaPanel.classList.toggle('hidden'); });
        document.addEventListener('click', function(e) {
            if (!megaPanel.contains(e.target) && !megaBtn.contains(e.target)) megaPanel.classList.add('hidden');
        });
    }

    const userMenuButton = document.getElementById('userMenuButton');
    const userMenuDropdown = document.getElementById('userMenuDropdown');
    
    if (userMenuButton && userMenuDropdown) {
        userMenuButton.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenuDropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenuButton.contains(e.target) && !userMenuDropdown.contains(e.target)) {
                userMenuDropdown.classList.add('hidden');
            }
        });
    }
});
</script>
<!-- Motion (motion.dev) animation library, sitewide: window.Motion.animate / inView / scroll.
     Vendored from npm (`npm install` copies it here) — no CDN. -->
<script src="public/vendor/js/motion.min.js" defer></script>