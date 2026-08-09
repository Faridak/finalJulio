<?php
session_start();
require_once 'config/database.php';
require_once 'services/PricingService.php';
require_once 'includes/SiteSettings.php';

$pricing = new PricingService($pdo);

// ---- Marketing-controlled front end ----------------------------------------
$S = fn($k, $d = null) => SiteSettings::get($pdo, $k, $d);
$theme = SiteSettings::themes()[$S('theme', 'default')] ?? SiteSettings::themes()['default'];
$heroHeadline = $S('hero_headline', 'Everything for ventilation & electrical construction');
$heroSub = $S('hero_subtext');
$heroCtaText = $S('hero_cta_text');
$heroCtaUrl = $S('hero_cta_url', 'browse.php');
$heroImage = $S('hero_image');            // decorative (e.g. Catrina for Día de Muertos)
$popupOn = $S('popup_enabled') === '1';

// A/B test: 50/50 by session, count views; CTA clicks counted via api/ab-click.php
$abVariant = null; $abTest = null;
$abTest = $pdo->query("SELECT * FROM ab_tests WHERE status='running' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($abTest) {
    if (!isset($_SESSION['ab_variant'])) $_SESSION['ab_variant'] = (random_int(0, 1) === 0) ? 'a' : 'b';
    $abVariant = $_SESSION['ab_variant'];
    $heroHeadline = $abVariant === 'a' ? $abTest['headline_a'] : $abTest['headline_b'];
    if (empty($_SESSION['ab_viewed_' . $abTest['id']])) {
        $pdo->exec("UPDATE ab_tests SET views_$abVariant = views_$abVariant + 1 WHERE id = " . (int) $abTest['id']);
        $_SESSION['ab_viewed_' . $abTest['id']] = 1;
    }
}

// ---- Real homepage data ----------------------------------------------------

// Top categories by live product count (top-level of the hierarchy).
$topCategories = $pdo->query("
    SELECT c.id, c.name, COUNT(p.id) AS n
    FROM categories c
    LEFT JOIN categories sub ON sub.parent_id = c.id
    LEFT JOIN products p ON p.category_id IN (c.id, sub.id) AND p.inventory > 0
    WHERE c.parent_id IS NULL
    GROUP BY c.id, c.name
    HAVING n > 0
    ORDER BY n DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// On sale now: products with a currently active discount.
$onSale = $pdo->query("
    SELECT DISTINCT p.id, p.name, p.price, COALESCE(NULLIF(p.thumb_url,''), p.image_url) AS image_url
    FROM products p
    JOIN product_discounts d ON d.product_id = p.id AND d.is_active = 1
        AND (d.start_date IS NULL OR d.start_date <= NOW())
        AND (d.end_date IS NULL OR d.end_date >= NOW())
    WHERE p.inventory > 0
    ORDER BY p.id DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Best sellers: by units sold.
$bestSellers = $pdo->query("
    SELECT p.id, p.name, p.price, COALESCE(NULLIF(p.thumb_url,''), p.image_url) AS image_url, SUM(oi.quantity) AS sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE p.inventory > 0
    GROUP BY p.id, p.name, p.price, p.image_url
    ORDER BY sold DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// New arrivals.
$newArrivals = $pdo->query("
    SELECT id, name, price, COALESCE(NULLIF(thumb_url,''), image_url) AS image_url FROM products
    WHERE inventory > 0 ORDER BY created_at DESC, id DESC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Featured (admin-curated).
$featured = $pdo->query("
    SELECT id, name, price, COALESCE(NULLIF(thumb_url,''), image_url) AS image_url FROM products
    WHERE inventory > 0 AND is_featured = 1 ORDER BY id DESC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Effective pricing + image fallback for all card sets.
function prepCards(&$rows, $pricing) {
    foreach ($rows as &$p) {
        $ep = $pricing->effectivePrice((int) $p['id']);
        $p['eff'] = (float) ($ep['unit_effective'] ?? $p['price']);
        $p['base'] = ($ep['discount'] ?? 0) > 0 ? (float) $p['price'] : null;
        if (empty($p['image_url'])) $p['image_url'] = 'assets/images/placeholder.jpg';
    }
    unset($p);
}
prepCards($onSale, $pricing);
prepCards($bestSellers, $pricing);
prepCards($newArrivals, $pricing);
prepCards($featured, $pricing);

function productCard($p, $badge = null) {
    $name = htmlspecialchars($p['name']);
    $img = htmlspecialchars($p['image_url']);
    $id = (int) $p['id'];
    $price = number_format($p['eff'], 2);
    $compare = !empty($p['base']) ? '<span class="text-xs text-gray-400 line-through ml-1">$' . number_format($p['base'], 2) . '</span>' : '';
    $badgeHtml = $badge ? '<span class="absolute top-2 left-2 bg-red-600 text-white text-xs px-2 py-0.5 rounded">' . $badge . '</span>' : '';
    return '<a href="product.php?id=' . $id . '" class="bg-white rounded-lg shadow hover:shadow-md transition overflow-hidden block relative">'
         . $badgeHtml
         . '<img src="' . $img . '" class="w-full h-40 object-cover" alt="' . $name . '" loading="lazy">'
         . '<div class="p-3"><div class="text-sm font-medium text-gray-800 line-clamp-2 h-10">' . $name . '</div>'
         . '<div class="mt-1"><span class="font-bold text-gray-900">$' . $price . '</span>' . $compare . '</div></div></a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VentDepot — Ventilation &amp; Electrical Construction Marketplace</title>
    <meta name="description" content="VentDepot: thousands of ventilation, electrical and construction products from verified sellers. B2B and B2C, shipped across Mexico and worldwide.">
    <link rel="stylesheet" href="public/vendor/css/tailwind.min.css">
    <link rel="stylesheet" href="public/vendor/css/fontawesome.min.css">
    <?php // Marketing tracking tags (Google Tag / Meta Pixel / custom)
    if ($gtag = $S('gtag_id')): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gtag) ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= htmlspecialchars($gtag) ?>');</script>
    <?php endif; ?>
    <?php if ($px = $S('meta_pixel_id')): ?>
    <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?= htmlspecialchars($px) ?>');fbq('track','PageView');</script>
    <?php endif; ?>
    <?= $S('custom_head_html', '') ?>
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <!-- Hero (marketing-controlled: theme, headline, image, CTA, A/B) -->
    <section class="text-white relative overflow-hidden" style="background:linear-gradient(100deg, <?= $theme['from'] ?>, <?= $theme['to'] ?>)">
        <?php if ($heroImage): ?>
            <img src="<?= htmlspecialchars($heroImage) ?>" class="absolute right-0 bottom-0 h-full max-w-[45%] object-contain opacity-90 pointer-events-none hidden md:block" alt="">
        <?php endif; ?>
        <div class="max-w-7xl mx-auto px-4 py-14 md:py-20 relative">
            <div class="max-w-2xl">
                <h1 class="text-3xl md:text-5xl font-bold leading-tight mb-4"><?= htmlspecialchars($heroHeadline) ?></h1>
                <p class="text-white/80 text-lg mb-6"><?= htmlspecialchars($heroSub ?: number_format((int) $pdo->query("SELECT COUNT(*) FROM products WHERE inventory > 0")->fetchColumn()) . ' products in stock — from extractors and solar equipment to signage and safety gear.') ?></p>
                <form action="browse.php" method="GET" class="flex max-w-xl">
                    <input type="text" name="q" placeholder="Search products, SKUs, categories…"
                           class="flex-1 px-4 py-3 rounded-l-lg text-gray-900 focus:outline-none">
                    <button class="px-6 py-3 rounded-r-lg font-semibold" style="background:<?= $theme['accent'] ?>">
                        <i class="fas fa-search mr-1"></i> Search
                    </button>
                </form>
                <?php if ($heroCtaText): ?>
                    <a href="<?= htmlspecialchars($heroCtaUrl) ?>" <?= $abTest ? 'onclick="fetch(\'api/ab-click.php?t=' . (int) $abTest['id'] . '&v=' . $abVariant . '\')"' : '' ?>
                       class="inline-block mt-5 px-6 py-3 rounded-lg font-bold text-white" style="background:<?= $theme['accent'] ?>"><?= htmlspecialchars($heroCtaText) ?></a>
                <?php endif; ?>
                <div class="flex flex-wrap gap-4 mt-6 text-sm text-white/80">
                    <span><i class="fas fa-truck mr-1"></i> Fast shipping MX &amp; worldwide</span>
                    <span><i class="fas fa-undo mr-1"></i> Easy returns</span>
                    <span><i class="fas fa-shield-alt mr-1"></i> Verified sellers</span>
                </div>
            </div>
        </div>
    </section>

    <?php if ($popupOn && ($S('popup_title') || $S('popup_coupon'))): ?>
    <!-- Marketing promo popup (once per session) -->
    <div id="promoPopup" class="fixed inset-0 bg-black/50 z-[80] hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 text-center relative">
            <button onclick="document.getElementById('promoPopup').style.display='none'" class="absolute top-2 right-3 text-gray-400 text-xl">×</button>
            <div class="text-3xl mb-2">🎁</div>
            <h3 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($S('popup_title', '¡Promoción!')) ?></h3>
            <p class="text-gray-600 text-sm mt-1"><?= htmlspecialchars($S('popup_text', '')) ?></p>
            <?php if ($cp = $S('popup_coupon')): ?>
                <div class="mt-3 border-2 border-dashed rounded-lg py-2 font-mono font-bold text-lg" style="color:<?= $theme['to'] ?>;border-color:<?= $theme['to'] ?>"><?= htmlspecialchars($cp) ?></div>
                <p class="text-xs text-gray-400 mt-1">Use this code at checkout</p>
            <?php endif; ?>
            <a href="browse.php?on_sale=1" class="inline-block mt-4 text-white px-5 py-2 rounded-lg font-semibold" style="background:<?= $theme['accent'] ?>">Shop deals</a>
        </div>
    </div>
    <script>
    if (!sessionStorage.getItem('promoSeen')) {
        setTimeout(() => { const p = document.getElementById('promoPopup'); p.style.display = 'flex'; sessionStorage.setItem('promoSeen', '1'); }, 1200);
    }
    </script>
    <?php endif; ?>

    <main class="max-w-7xl mx-auto px-4 py-10 space-y-12">

        <!-- Categories -->
        <?php if ($topCategories): ?>
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900">Shop by category</h2>
                <a href="browse.php" class="text-blue-600 text-sm hover:underline">All products →</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <?php foreach ($topCategories as $c): ?>
                    <a href="browse.php?category_id=<?= (int)$c['id'] ?>" class="bg-white rounded-lg shadow hover:shadow-md transition p-4 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($c['name']) ?></span>
                        <span class="text-xs text-gray-400 whitespace-nowrap ml-2"><?= (int)$c['n'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- On sale -->
        <?php if ($onSale): ?>
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900">🔥 On sale now</h2>
                <a href="browse.php?on_sale=1" class="text-blue-600 text-sm hover:underline">View all →</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($onSale as $p) echo productCard($p, 'SALE'); ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Best sellers -->
        <?php if ($bestSellers): ?>
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900">Best sellers</h2>
                <a href="browse.php" class="text-blue-600 text-sm hover:underline">View all →</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($bestSellers as $p) echo productCard($p); ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Featured -->
        <?php if ($featured): ?>
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900">Featured products</h2>
                <a href="browse.php?featured=1" class="text-blue-600 text-sm hover:underline">View all →</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($featured as $p) echo productCard($p); ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- New arrivals -->
        <?php if ($newArrivals): ?>
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900">New arrivals</h2>
                <a href="browse.php?sort=newest" class="text-blue-600 text-sm hover:underline">View all →</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($newArrivals as $p) echo productCard($p); ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Sell with us -->
        <section class="bg-white rounded-xl shadow p-8 md:flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 mb-1">Sell on VentDepot</h2>
                <p class="text-gray-500 text-sm">Reach construction buyers across Mexico and beyond. Build your own store, we handle payments, warehousing and shipping.</p>
            </div>
            <a href="register.php?type=merchant" class="inline-block mt-4 md:mt-0 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg whitespace-nowrap">Become a seller</a>
        </section>
    </main>

    <footer class="bg-gray-900 text-gray-400 mt-12">
        <div class="max-w-7xl mx-auto px-4 py-10 grid md:grid-cols-3 gap-8 text-sm">
            <div>
                <div class="text-white font-bold text-lg mb-2">VentDepot</div>
                <p>Ventilation &amp; electrical construction marketplace. B2B &amp; B2C.</p>
            </div>
            <div>
                <div class="text-white font-semibold mb-2">Shop</div>
                <a href="browse.php" class="block hover:text-white">All products</a>
                <a href="browse.php?on_sale=1" class="block hover:text-white">Deals</a>
                <a href="orders.php" class="block hover:text-white">My orders</a>
                <a href="wishlist.php" class="block hover:text-white">Wishlist</a>
            </div>
            <div>
                <div class="text-white font-semibold mb-2">Company</div>
                <a href="register.php?type=merchant" class="block hover:text-white">Sell with us</a>
                <a href="help.php" class="block hover:text-white">Help</a>
                <a href="privacy.php" class="block hover:text-white">Privacy</a>
            </div>
        </div>
        <div class="border-t border-gray-800 py-4 text-center text-xs">© <?= date('Y') ?> VentDepot. All rights reserved.</div>
    </footer>
</body>
</html>
