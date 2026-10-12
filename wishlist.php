<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'services/PricingService.php';

if (!isLoggedIn()) { header('Location: login.php?redirect=wishlist.php'); exit; }

$pricing = new PricingService($pdo);
$stmt = $pdo->prepare("
    SELECT w.id wid, w.notify_back_in_stock, p.id, p.name, p.price, COALESCE(NULLIF(p.thumb_url,''), p.image_url) AS image_url, p.inventory
    FROM wishlist_items w JOIN products p ON w.product_id = p.id
    WHERE w.user_id = ? ORDER BY w.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($items as &$it) {
    $ep = $pricing->effectivePrice($it['id']);
    $it['eff'] = $ep['unit_effective']; $it['base'] = $ep['discount'] > 0 ? $ep['base'] : null;
}
unset($it);
$csrf = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - VentDepot</title>
    <link rel="stylesheet" href="public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>
    <main class="container mx-auto px-4 py-8 max-w-5xl">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">My Wishlist</h1>
        <p class="text-sm text-gray-500 mb-6">Saved items — we'll email you when an out-of-stock item is available again.</p>

        <?php if (empty($items)): ?>
            <div class="bg-white rounded-lg shadow p-12 text-center text-gray-400">Your wishlist is empty. <a href="browse.php" class="text-blue-600 hover:underline">Browse products →</a></div>
        <?php else: ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($items as $it): ?>
            <div class="bg-white rounded-lg shadow overflow-hidden" id="wl-<?= (int)$it['id'] ?>">
                <a href="product.php?id=<?= (int)$it['id'] ?>"><img src="<?= htmlspecialchars(asset_url($it['image_url'] ?? '') ?: 'assets/images/placeholder.jpg') ?>" class="w-full h-36 object-cover"></a>
                <div class="p-3">
                    <a href="product.php?id=<?= (int)$it['id'] ?>" class="text-sm font-medium text-gray-800 hover:text-blue-600 line-clamp-2"><?= htmlspecialchars($it['name']) ?></a>
                    <div class="mt-1 text-sm"><b>$<?= number_format((float)$it['eff'],2) ?></b>
                        <?php if ($it['base']): ?><span class="text-xs text-gray-400 line-through">$<?= number_format((float)$it['base'],2) ?></span><?php endif; ?></div>
                    <?php if ($it['inventory'] > 0): ?>
                        <button onclick="addToCart(<?= (int)$it['id'] ?>)" class="mt-2 w-full bg-blue-600 text-white text-sm py-1.5 rounded hover:bg-blue-700">Add to cart</button>
                    <?php else: ?>
                        <div class="mt-2 text-center text-xs text-amber-700 bg-amber-50 rounded py-1.5">Out of stock — we'll email you 📬</div>
                    <?php endif; ?>
                    <button onclick="removeWish(<?= (int)$it['id'] ?>)" class="mt-1 w-full text-xs text-gray-400 hover:text-red-600">remove</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
<script>
const CSRF = <?= json_encode($csrf) ?>;
async function removeWish(pid){
    const r = await fetch('api/wishlist.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'remove',product_id:pid,csrf_token:CSRF})});
    const d = await r.json();
    if (d.success) document.getElementById('wl-'+pid).remove();
}
async function addToCart(pid){
    const r = await fetch('api/cart.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add',product_id:pid,quantity:1})});
    const d = await r.json();
    alert(d.success ? 'Added to cart' : (d.message||'Could not add'));
}
</script>
</body>
</html>
