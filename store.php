<?php
require_once 'config/database.php';
require_once 'services/PricingService.php';

$pricing = new PricingService($pdo);

// Resolve the store by slug or merchant id.
$slug = trim($_GET['seller'] ?? '');
$merchantId = (int) ($_GET['merchant'] ?? 0);
if ($slug !== '') {
    $s = $pdo->prepare("SELECT * FROM supplier_store WHERE store_slug = ? LIMIT 1");
    $s->execute([$slug]);
} else {
    $s = $pdo->prepare("SELECT * FROM supplier_store WHERE merchant_id = ? LIMIT 1");
    $s->execute([$merchantId]);
}
$store = $s->fetch(PDO::FETCH_ASSOC);
if (!$store) { http_response_code(404); echo 'Store not found.'; exit; }
$merchantId = (int) $store['merchant_id'];

// Owner preview even if unpublished.
$isOwner = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $merchantId;
if (!$store['is_published'] && !$isOwner) { http_response_code(404); echo 'This store is not open yet.'; exit; }

$theme = htmlspecialchars($store['theme_color'] ?: '#2563eb');

// Products (merchant's own catalog), featured first, then store order.
$prodStmt = $pdo->prepare("
    SELECT id, sku, name, price, image_url, is_featured
    FROM products WHERE merchant_id = ?
    ORDER BY is_featured DESC, store_sort ASC, name ASC LIMIT 60
");
$prodStmt->execute([$merchantId]);
$products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($products as &$p) { $p['eff'] = $pricing->effectivePrice($p['id'])['unit_effective']; }
unset($p);
$featured = array_values(array_filter($products, fn($p) => $p['is_featured']));

$sections = array_map('trim', explode(',', $store['section_order'] ?: 'jumbotron,banner,featured,products'));
// Stores saved before the banner feature: show the banner right after the hero.
if (!empty($store['banner_image']) && !in_array('banner', $sections, true)) {
    $pos = array_search('jumbotron', $sections, true);
    array_splice($sections, $pos === false ? 0 : $pos + 1, 0, 'banner');
}

// Promo popup active?
$popupActive = $store['popup_enabled']
    && (!$store['popup_starts'] || $store['popup_starts'] <= date('Y-m-d'))
    && (!$store['popup_ends'] || $store['popup_ends'] >= date('Y-m-d'));

function card($p) {
    $img = htmlspecialchars($p['image_url'] ?: 'assets/images/placeholder.jpg');
    echo '<a href="product.php?id=' . (int) $p['id'] . '" class="bg-white rounded-lg shadow hover:shadow-md transition overflow-hidden block">'
        . '<img src="' . $img . '" class="w-full h-40 object-cover" alt="">'
        . '<div class="p-3"><div class="text-sm font-medium text-gray-800 line-clamp-2">' . htmlspecialchars($p['name']) . '</div>'
        . '<div class="font-bold text-gray-900 mt-1">$' . number_format((float) $p['eff'], 2) . '</div></div></a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($store['store_name'] ?: 'Store') ?> — VentDepot</title>
    <link rel="stylesheet" href="public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="index.php" class="text-xl font-bold" style="color:<?= $theme ?>">VentDepot</a>
            <span class="text-gray-500 text-sm"><?= htmlspecialchars($store['store_name'] ?: 'Store') ?></span>
        </div>
    </nav>

    <?php if (!$store['is_published']): ?>
        <div class="bg-amber-100 text-amber-800 text-sm text-center py-2">Preview — this store is not published yet.</div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto px-4 py-6 space-y-10">
        <?php foreach ($sections as $sec): ?>
            <?php if ($sec === 'jumbotron' && ($store['jumbotron_headline'] || $store['jumbotron_image'])): ?>
                <section class="rounded-2xl overflow-hidden text-white p-10 relative" style="background:<?= $theme ?>">
                    <?php if ($store['jumbotron_image']): ?><img src="<?= htmlspecialchars(asset_url($store['jumbotron_image'])) ?>" class="absolute inset-0 w-full h-full object-cover opacity-30" alt=""><?php endif; ?>
                    <div class="relative">
                        <h1 class="text-3xl md:text-4xl font-extrabold"><?= htmlspecialchars($store['jumbotron_headline'] ?: '') ?></h1>
                        <p class="mt-2 text-lg opacity-90"><?= htmlspecialchars($store['jumbotron_subtext'] ?: '') ?></p>
                        <?php if ($store['jumbotron_cta_text']): ?>
                            <a href="<?= htmlspecialchars($store['jumbotron_cta_url'] ?: '#') ?>" class="inline-block mt-4 bg-white text-gray-900 px-5 py-2 rounded-lg font-semibold"><?= htmlspecialchars($store['jumbotron_cta_text']) ?></a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php elseif ($sec === 'banner' && !empty($store['banner_image'])): ?>
                <?php $bimg = '<img src="' . htmlspecialchars($store['banner_image']) . '" class="w-full max-h-64 object-cover rounded-2xl shadow" alt="Store banner">'; ?>
                <section>
                    <?= !empty($store['banner_link'])
                        ? '<a href="' . htmlspecialchars($store['banner_link']) . '">' . $bimg . '</a>'
                        : $bimg ?>
                </section>
            <?php elseif ($sec === 'featured' && $featured): ?>
                <section>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Featured</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4"><?php foreach (array_slice($featured, 0, 8) as $p) card($p); ?></div>
                </section>
            <?php elseif ($sec === 'products'): ?>
                <section>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">All products</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4"><?php foreach ($products as $p) card($p); ?></div>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php if ($popupActive): ?>
    <div id="promo" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 max-w-sm text-center relative">
            <button onclick="document.getElementById('promo').remove()" class="absolute top-2 right-3 text-gray-400 text-xl">&times;</button>
            <h3 class="text-xl font-bold" style="color:<?= $theme ?>"><?= htmlspecialchars($store['popup_title'] ?: 'Special offer') ?></h3>
            <p class="text-gray-600 mt-2"><?= htmlspecialchars($store['popup_text'] ?: '') ?></p>
            <?php if ($store['popup_coupon']): ?>
                <div class="mt-4 border-2 border-dashed rounded-lg py-2 font-mono font-bold text-lg" style="border-color:<?= $theme ?>;color:<?= $theme ?>"><?= htmlspecialchars($store['popup_coupon']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
<script src="public/vendor/js/motion.min.js" defer></script>
</body>
</html>
