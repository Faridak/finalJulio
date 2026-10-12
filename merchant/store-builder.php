<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
requireRole('merchant');

$merchantId = (int) $_SESSION['user_id'];
$notice = ''; $error = '';

function slugify($s) { $s = strtolower(trim($s)); $s = preg_replace('/[^a-z0-9]+/', '-', $s); return trim($s, '-'); }

// Ensure a store row exists.
$pdo->prepare("INSERT IGNORE INTO supplier_store (merchant_id, store_name) VALUES (?, ?)")
    ->execute([$merchantId, 'My Store #' . $merchantId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(403); die('CSRF mismatch'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'save_store') {
        $slug = slugify($_POST['store_slug'] ?: $_POST['store_name'] ?: ('store-' . $merchantId));

        // Uploaded images (jumbotron background / banner) beat the URL fields.
        $imageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $saveUpload = function ($field) use ($imageTypes, $merchantId, &$error) {
            if (empty($_FILES[$field]['tmp_name'])) return null;
            $errs = Security::validateFileUpload($_FILES[$field], $imageTypes, 4 * 1024 * 1024);
            if ($errs) { $error = ucfirst(str_replace('_', ' ', $field)) . ': ' . implode(' ', $errs); return null; }
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][mime_content_type($_FILES[$field]['tmp_name'])];
            $dir = __DIR__ . '/../uploads/stores/' . $merchantId;
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $name = $field . '-' . time() . '.' . $ext;   // safe generated name, never the client's
            if (!move_uploaded_file($_FILES[$field]['tmp_name'], "$dir/$name")) { $error = 'Could not save the uploaded image.'; return null; }
            $key = 'uploads/stores/' . $merchantId . '/' . $name;
            store_media_file($key, "$dir/$name");   // mirror to object storage when configured
            return $key;
        };
        $jumboImg  = $saveUpload('jumbotron_image_file') ?: ($_POST['jumbotron_image'] ?: null);
        $bannerImg = $saveUpload('banner_image_file') ?: ($_POST['banner_image_current'] ?: null);
        if (!empty($_POST['banner_remove'])) $bannerImg = null;

        try {
            if (!$error) $pdo->prepare("
                UPDATE supplier_store SET store_name=?, store_slug=?, theme_color=?,
                    jumbotron_headline=?, jumbotron_subtext=?, jumbotron_image=?, jumbotron_cta_text=?, jumbotron_cta_url=?,
                    banner_image=?, banner_link=?,
                    popup_enabled=?, popup_title=?, popup_text=?, popup_coupon=?, popup_starts=?, popup_ends=?,
                    section_order=?, is_published=?
                WHERE merchant_id=?
            ")->execute([
                $_POST['store_name'] ?: null, $slug, $_POST['theme_color'] ?: '#2563eb',
                $_POST['jumbotron_headline'] ?: null, $_POST['jumbotron_subtext'] ?: null, $jumboImg,
                $_POST['jumbotron_cta_text'] ?: null, $_POST['jumbotron_cta_url'] ?: null,
                $bannerImg, $_POST['banner_link'] ?: null,
                isset($_POST['popup_enabled']) ? 1 : 0, $_POST['popup_title'] ?: null, $_POST['popup_text'] ?: null,
                $_POST['popup_coupon'] ?: null, $_POST['popup_starts'] ?: null, $_POST['popup_ends'] ?: null,
                $_POST['section_order'] ?: 'jumbotron,banner,featured,products', isset($_POST['is_published']) ? 1 : 0,
                $merchantId,
            ]);
            if (!$error) $notice = 'Store saved.';
        } catch (Exception $e) { $error = (stripos($e->getMessage(), 'uq_store_slug') !== false) ? 'That store URL is taken.' : $e->getMessage(); }
    } elseif ($action === 'add_featured') {
        $code = trim($_POST['sku'] ?? '');
        $r = $pdo->prepare("SELECT id FROM products WHERE merchant_id=? AND (sku=? OR barcode=?) LIMIT 1");
        $r->execute([$merchantId, $code, $code]);
        $pid = (int) $r->fetchColumn();
        if ($pid) { $pdo->prepare("UPDATE products SET is_featured=1 WHERE id=? AND merchant_id=?")->execute([$pid, $merchantId]); $notice = 'Added to featured.'; }
        else $error = "No product “$code” in your catalog.";
    } elseif ($action === 'remove_featured') {
        $pdo->prepare("UPDATE products SET is_featured=0 WHERE id=? AND merchant_id=?")->execute([(int) $_POST['pid'], $merchantId]);
        $notice = 'Removed from featured.';
    } elseif ($action === 'save_order') {
        $ids = array_filter(array_map('intval', explode(',', $_POST['order'] ?? '')));
        $upd = $pdo->prepare("UPDATE products SET store_sort=? WHERE id=? AND merchant_id=?");
        foreach ($ids as $i => $pid) $upd->execute([$i, $pid, $merchantId]);
        $notice = 'Order saved.';
    }
    if (!$error) { header('Location: store-builder.php?ok=' . urlencode($notice)); exit; }
}
if (isset($_GET['ok'])) $notice = $_GET['ok'];

$store = $pdo->prepare("SELECT * FROM supplier_store WHERE merchant_id=?");
$store->execute([$merchantId]);
$store = $store->fetch(PDO::FETCH_ASSOC);

$feat = $pdo->prepare("SELECT id, sku, name, image_url FROM products WHERE merchant_id=? AND is_featured=1 ORDER BY store_sort, name");
$feat->execute([$merchantId]);
$featured = $feat->fetchAll(PDO::FETCH_ASSOC);
$v = fn($k) => htmlspecialchars($store[$k] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Builder - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
    <script src="../public/vendor/js/Sortable.min.js" defer></script>
</head>
<body class="bg-gray-50">
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-900">Store Builder</h1>
        <div class="flex gap-2">
            <?php if (!empty($store['store_slug'])): ?><a href="../store.php?seller=<?= urlencode($store['store_slug']) ?>" target="_blank" class="text-blue-600 hover:underline text-sm self-center">Preview store ↗</a><?php endif; ?>
        </div>
    </div>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Store settings -->
    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-5 mb-6 space-y-4">
        <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="save_store">
        <div class="grid md:grid-cols-3 gap-3">
            <div><label class="block text-xs text-gray-500">Store name</label><input name="store_name" value="<?= $v('store_name') ?>" class="w-full px-3 py-2 border rounded"></div>
            <div><label class="block text-xs text-gray-500">Store URL (/store.php?seller=)</label><input name="store_slug" value="<?= $v('store_slug') ?>" class="w-full px-3 py-2 border rounded font-mono text-sm"></div>
            <div><label class="block text-xs text-gray-500">Theme color</label><input type="color" name="theme_color" value="<?= $v('theme_color') ?: '#2563eb' ?>" class="w-full h-10 px-1 py-1 border rounded"></div>
        </div>

        <fieldset class="border rounded p-3"><legend class="text-sm font-semibold px-1">Jumbotron (hero)</legend>
            <div class="grid md:grid-cols-2 gap-3">
                <input name="jumbotron_headline" value="<?= $v('jumbotron_headline') ?>" placeholder="Headline" class="px-3 py-2 border rounded">
                <input name="jumbotron_subtext" value="<?= $v('jumbotron_subtext') ?>" placeholder="Sub-text" class="px-3 py-2 border rounded">
                <div>
                    <input name="jumbotron_image" value="<?= $v('jumbotron_image') ?>" placeholder="Background image URL" class="w-full px-3 py-2 border rounded">
                    <label class="block text-xs text-gray-500 mt-1">…or upload: <input type="file" name="jumbotron_image_file" accept="image/*" class="text-xs"></label>
                    <?php if (!empty($store['jumbotron_image'])): ?><img src="../<?= htmlspecialchars(ltrim($store['jumbotron_image'], '/')) ?>" class="mt-1 h-12 rounded object-cover" onerror="this.style.display='none'"><?php endif; ?>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input name="jumbotron_cta_text" value="<?= $v('jumbotron_cta_text') ?>" placeholder="Button text" class="px-3 py-2 border rounded">
                    <input name="jumbotron_cta_url" value="<?= $v('jumbotron_cta_url') ?>" placeholder="Button link" class="px-3 py-2 border rounded">
                </div>
            </div>
        </fieldset>

        <fieldset class="border rounded p-3"><legend class="text-sm font-semibold px-1">Store banner</legend>
            <div class="grid md:grid-cols-2 gap-3 items-start">
                <div>
                    <label class="block text-xs text-gray-500">Banner image (wide, e.g. 1200×250)
                        <input type="file" name="banner_image_file" accept="image/*" class="block text-xs mt-1"></label>
                    <input type="hidden" name="banner_image_current" value="<?= $v('banner_image') ?>">
                    <?php if (!empty($store['banner_image'])): ?>
                        <img src="../<?= htmlspecialchars(ltrim($store['banner_image'], '/')) ?>" class="mt-2 h-16 rounded object-cover w-full">
                        <label class="text-xs text-red-600 flex items-center gap-1 mt-1"><input type="checkbox" name="banner_remove" value="1"> remove banner</label>
                    <?php endif; ?>
                </div>
                <div><label class="block text-xs text-gray-500">Banner click-through link (optional)</label>
                    <input name="banner_link" value="<?= $v('banner_link') ?>" placeholder="https://… or product.php?id=…" class="w-full px-3 py-2 border rounded"></div>
            </div>
        </fieldset>

        <fieldset class="border rounded p-3"><legend class="text-sm font-semibold px-1">Promo popup</legend>
            <label class="flex items-center gap-2 text-sm mb-2"><input type="checkbox" name="popup_enabled" <?= !empty($store['popup_enabled'])?'checked':'' ?>> Enable popup</label>
            <div class="grid md:grid-cols-2 gap-3">
                <input name="popup_title" value="<?= $v('popup_title') ?>" placeholder="Popup title" class="px-3 py-2 border rounded">
                <input name="popup_coupon" value="<?= $v('popup_coupon') ?>" placeholder="Coupon code" class="px-3 py-2 border rounded font-mono">
                <input name="popup_text" value="<?= $v('popup_text') ?>" placeholder="Message" class="px-3 py-2 border rounded md:col-span-2">
                <div class="grid grid-cols-2 gap-2"><input type="date" name="popup_starts" value="<?= $v('popup_starts') ?>" class="px-3 py-2 border rounded"><input type="date" name="popup_ends" value="<?= $v('popup_ends') ?>" class="px-3 py-2 border rounded"></div>
            </div>
        </fieldset>

        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 text-sm">
                <label>Section order <input name="section_order" value="<?= $v('section_order') ?: 'jumbotron,featured,products' ?>" class="ml-1 px-2 py-1 border rounded font-mono text-xs w-56"></label>
                <label class="flex items-center gap-1"><input type="checkbox" name="is_published" <?= !empty($store['is_published'])?'checked':'' ?>> Publish store</label>
            </div>
            <button class="bg-blue-600 text-white px-5 py-2 rounded">Save store</button>
        </div>
    </form>

    <!-- Featured products (drag to reorder) -->
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold">Featured products <span class="text-xs text-gray-400">(drag to reorder)</span></h2>
            <form method="POST" class="flex gap-2">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="add_featured">
                <input name="sku" placeholder="add by SKU" class="px-3 py-1 border rounded text-sm font-mono">
                <button class="bg-green-600 text-white px-3 rounded text-sm">Add</button>
            </form>
        </div>
        <ul id="featList" class="space-y-2">
            <?php foreach ($featured as $f): ?>
                <li class="flex items-center justify-between border rounded px-3 py-2 bg-gray-50" data-id="<?= (int)$f['id'] ?>">
                    <span class="flex items-center gap-2"><span class="cursor-move text-gray-400">⠿</span>
                        <span class="font-mono text-gray-500 text-sm"><?= htmlspecialchars($f['sku']) ?></span> <?= htmlspecialchars($f['name']) ?></span>
                    <form method="POST"><?= Security::getCSRFInput() ?><input type="hidden" name="action" value="remove_featured"><input type="hidden" name="pid" value="<?= (int)$f['id'] ?>"><button class="text-red-500 text-xs">remove</button></form>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (empty($featured)): ?><p class="text-gray-400 text-sm">No featured products yet — add some by SKU above.</p><?php endif; ?>
        <form method="POST" class="mt-3" id="orderForm">
            <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="save_order"><input type="hidden" name="order" id="orderInput">
            <button class="bg-gray-800 text-white px-4 py-1.5 rounded text-sm">Save order</button>
        </form>
    </div>
</div>

<script>
window.addEventListener('load', function () {
    var list = document.getElementById('featList');
    if (list && window.Sortable) {
        Sortable.create(list, { handle: '.cursor-move', animation: 150 });
        document.getElementById('orderForm').addEventListener('submit', function () {
            document.getElementById('orderInput').value =
                [...list.querySelectorAll('li')].map(li => li.dataset.id).join(',');
        });
    }
});
</script>
<script src="../public/vendor/js/motion.min.js" defer></script>
</body>
</html>
