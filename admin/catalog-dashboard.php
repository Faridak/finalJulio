<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/PricingService.php';
require_once '../services/CategoryService.php';
require_once '../services/RelationshipService.php';
require_once '../includes/AuditLog.php';

requireRole('admin');
requireScope('catalog');

$pricing = new PricingService($pdo);
$categorySvc = new CategoryService($pdo);
$relSvc = new RelationshipService($pdo);

$notice = ''; $error = '';

// ---- Actions --------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(403); die('CSRF mismatch'); }
    $pid = (int) ($_POST['product_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'set_price') {
            $oldPrice = $pdo->query("SELECT price FROM products WHERE id=".(int)$pid)->fetchColumn();
            $newPrice = round((float) $_POST['price'], 2);
            $pdo->prepare("UPDATE products SET price=?, updated_at=NOW() WHERE id=?")
                ->execute([$newPrice, $pid]);
            $notice = 'Price updated.';
            AuditLog::record($pdo, 'price.update', 'product', $pid,
                "Price changed \$".number_format((float)$oldPrice,2)." → \$".number_format($newPrice,2),
                ['old'=>(float)$oldPrice, 'new'=>$newPrice]);
        } elseif ($action === 'add_discount') {
            $pricing->createDiscount($pid, $_POST['discount_type'] ?? 'percentage', (float) $_POST['value'],
                $_POST['start'] ?: null, $_POST['end'] ?: null,
                (int) ($_POST['min_qty'] ?: 1), $_POST['max_qty'] !== '' ? (int) $_POST['max_qty'] : null);
            $notice = 'Discount added.';
        } elseif ($action === 'delete_discount') {
            $pdo->prepare("DELETE FROM product_discounts WHERE id=?")->execute([(int) $_POST['discount_id']]);
            $notice = 'Discount removed.';
        } elseif ($action === 'assign_category') {
            $categorySvc->assign($pid, (int) $_POST['category_id']);
            $notice = 'Category assigned.';
        } elseif ($action === 'add_relationship') {
            $code = trim($_POST['related_code'] ?? '');
            $rel = $pdo->prepare("SELECT id FROM products WHERE sku=? OR barcode=? LIMIT 1");
            $rel->execute([$code, $code]);
            $relId = (int) $rel->fetchColumn();
            if (!$relId) $error = "No product found for \"$code\".";
            elseif ($relId === $pid) $error = 'A product cannot relate to itself.';
            else { $relSvc->link($pid, $relId, $_POST['rel_type'] ?? 'accessory', !empty($_POST['required']), $_POST['note'] ?: null);
                   $notice = 'Related product added.'; }
        } elseif ($action === 'remove_relationship') {
            $pdo->prepare("DELETE FROM product_relationships WHERE id=?")->execute([(int) $_POST['rel_id']]);
            $notice = 'Relationship removed.';
        } elseif ($action === 'bulk_price') {
            $n = $pricing->bulkUpdate(['category_id' => (int) $_POST['bulk_category']], $_POST['bulk_method'], (float) $_POST['bulk_value']);
            $notice = "Bulk update applied to $n product(s).";
            AuditLog::record($pdo, 'price.bulk_update', 'category', (int) $_POST['bulk_category'],
                "Bulk price {$_POST['bulk_method']} {$_POST['bulk_value']} on $n product(s)",
                ['method'=>$_POST['bulk_method'], 'value'=>(float)$_POST['bulk_value'], 'affected'=>$n]);
        }
    } catch (Exception $e) { $error = $e->getMessage(); }

    if ($pid && !$error) { header('Location: catalog-dashboard.php?product=' . $pid . '&ok=' . urlencode($notice)); exit; }
}
if (isset($_GET['ok'])) $notice = $_GET['ok'];

// ---- Load selected product ------------------------------------------------
$q = trim($_GET['q'] ?? '');
$productId = (int) ($_GET['product'] ?? 0);
$product = null; $results = [];

if ($productId) {
    $s = $pdo->prepare("SELECT id, sku, name, price, cost_price, category_id FROM products WHERE id=? LIMIT 1");
    $s->execute([$productId]);
    $product = $s->fetch(PDO::FETCH_ASSOC);
} elseif ($q !== '') {
    $s = $pdo->prepare("SELECT id, sku, name, price FROM products WHERE ownership='own' AND (sku LIKE ? OR name LIKE ? OR barcode LIKE ?) ORDER BY name LIMIT 40");
    $like = "%$q%"; $s->execute([$like, $like, $like]);
    $results = $s->fetchAll(PDO::FETCH_ASSOC);
}

$discounts = $relationships = $breadcrumb = [];
$eff = null;
if ($product) {
    $d = $pdo->prepare("SELECT * FROM product_discounts WHERE product_id=? ORDER BY is_active DESC, id DESC");
    $d->execute([$productId]); $discounts = $d->fetchAll(PDO::FETCH_ASSOC);
    $relationships = $relSvc->related($productId, null, true);
    if ($product['category_id']) $breadcrumb = $categorySvc->breadcrumb($product['category_id']);
    $eff = $pricing->effectivePrice($productId, 1);
}

// categories for the picker (subcategories labelled "Parent › Sub")
$catOptions = $pdo->query("
    SELECT c.id, CONCAT(COALESCE(pcat.name,''), CASE WHEN pcat.name IS NULL THEN '' ELSE ' › ' END, c.name) AS label
    FROM categories c LEFT JOIN categories pcat ON c.parent_id=pcat.id
    ORDER BY label LIMIT 4000
")->fetchAll(PDO::FETCH_ASSOC);
$topCats = $pdo->query("SELECT id, name FROM categories WHERE level=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$relTypes = ['component'=>'Component (fundamental)','accessory'=>'Accessory (add-on)','alternative'=>'Alternative (material/variant)','compatible'=>'Compatible','substitute'=>'Substitute','bundle'=>'Bundle'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalog Management - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Catalog Management</h1>
    <p class="text-sm text-gray-500 mb-4">Pricing, categories, and accessories/components — per product.</p>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Search -->
    <form method="GET" class="flex gap-2 mb-6">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="search product by SKU / name / barcode" class="flex-1 px-3 py-2 border rounded">
        <button class="bg-gray-800 text-white px-5 rounded">Search</button>
    </form>

    <?php if ($results): ?>
        <div class="bg-white rounded-lg shadow divide-y mb-6">
            <?php foreach ($results as $r): ?>
                <a href="?product=<?= (int)$r['id'] ?>" class="flex justify-between px-4 py-2 hover:bg-gray-50">
                    <span><span class="font-mono text-gray-500"><?= htmlspecialchars($r['sku']) ?></span> <?= htmlspecialchars($r['name']) ?></span>
                    <span class="font-semibold">$<?= number_format($r['price'],2) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($product): ?>
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <div class="text-lg font-bold"><?= htmlspecialchars($product['name']) ?></div>
                <div class="font-mono text-sm text-gray-500"><?= htmlspecialchars($product['sku']) ?></div>
                <div class="text-xs text-gray-400 mt-1"><?= $breadcrumb ? implode(' › ', array_column($breadcrumb,'name')) : 'No category' ?></div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold">$<?= number_format($eff['unit_effective'],2) ?></div>
                <?php if ($eff['applied']): ?><div class="text-xs text-green-700">was $<?= number_format($eff['base'],2) ?> · <?= htmlspecialchars($eff['applied']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <!-- Pricing -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold mb-3">💲 Pricing</h2>
            <form method="POST" class="flex items-end gap-2 mb-4">
                <?= Security::getCSRFInput() ?><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="action" value="set_price">
                <div><label class="block text-xs text-gray-500">Base price</label><input type="number" step="0.01" name="price" value="<?= $product['price'] ?>" class="px-3 py-2 border rounded w-32"></div>
                <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Save price</button>
            </form>
            <div class="text-sm font-medium text-gray-600 mb-1">Discounts &amp; promotions</div>
            <?php foreach ($discounts as $d): ?>
                <div class="flex items-center justify-between text-sm border-b py-1">
                    <span><?= $d['discount_type']==='percentage' ? $d['discount_value'].'%' : '$'.$d['discount_value'] ?> off
                        <?php if ($d['min_quantity']>1): ?><span class="text-gray-400">(qty <?= $d['min_quantity'] ?>+)</span><?php endif; ?>
                        <span class="text-gray-400 text-xs"><?= htmlspecialchars(date('M j', strtotime($d['start_date']))) ?>–<?= htmlspecialchars(date('M j', strtotime($d['end_date']))) ?></span>
                    </span>
                    <form method="POST"><?= Security::getCSRFInput() ?><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="action" value="delete_discount"><input type="hidden" name="discount_id" value="<?= $d['id'] ?>"><button class="text-red-500 text-xs">remove</button></form>
                </div>
            <?php endforeach; ?>
            <form method="POST" class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <?= Security::getCSRFInput() ?><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="action" value="add_discount">
                <select name="discount_type" class="px-2 py-1 border rounded"><option value="percentage">% off</option><option value="fixed_amount">$ off</option></select>
                <input type="number" step="0.01" name="value" placeholder="value" class="px-2 py-1 border rounded" required>
                <input type="date" name="start" class="px-2 py-1 border rounded" title="start (optional)">
                <input type="date" name="end" class="px-2 py-1 border rounded" title="end (optional)">
                <input type="number" name="min_qty" placeholder="min qty (bulk)" class="px-2 py-1 border rounded">
                <input type="number" name="max_qty" placeholder="max qty" class="px-2 py-1 border rounded">
                <button class="col-span-2 bg-green-600 text-white py-1.5 rounded">Add timed / bulk discount</button>
            </form>
        </div>

        <!-- Category + Accessories -->
        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow p-5">
                <h2 class="font-semibold mb-3">🗂️ Category</h2>
                <form method="POST" class="flex gap-2">
                    <?= Security::getCSRFInput() ?><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="action" value="assign_category">
                    <select name="category_id" class="flex-1 px-3 py-2 border rounded text-sm">
                        <?php foreach ($catOptions as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $product['category_id']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="bg-blue-600 text-white px-4 rounded text-sm">Set</button>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow p-5">
                <h2 class="font-semibold mb-3">🔧 Accessories &amp; components</h2>
                <?php if ($relationships): foreach ($relationships as $r): ?>
                    <div class="flex items-center justify-between text-sm border-b py-1">
                        <span>
                            <span class="px-1.5 py-0.5 rounded text-xs <?= $r['is_required']?'bg-red-100 text-red-700':'bg-gray-100 text-gray-600' ?>"><?= $r['relationship_type'] ?><?= $r['is_required']?' *':'' ?></span>
                            <span class="font-mono text-gray-500"><?= htmlspecialchars($r['sku']) ?></span> <?= htmlspecialchars($r['name']) ?>
                            <span class="text-gray-400">$<?= number_format($r['effective_price'],2) ?></span>
                        </span>
                        <form method="POST"><?= Security::getCSRFInput() ?><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="action" value="remove_relationship"><input type="hidden" name="rel_id" value="<?= $r['id'] ?>"><button class="text-red-500 text-xs">remove</button></form>
                    </div>
                <?php endforeach; else: ?><p class="text-gray-400 text-sm mb-2">No related products yet.</p><?php endif; ?>
                <form method="POST" class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <?= Security::getCSRFInput() ?><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="action" value="add_relationship">
                    <input type="text" name="related_code" placeholder="related SKU / barcode" class="px-2 py-1 border rounded" required>
                    <select name="rel_type" class="px-2 py-1 border rounded"><?php foreach ($relTypes as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
                    <input type="text" name="note" placeholder="note (e.g. copper)" class="px-2 py-1 border rounded">
                    <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="required"> fundamental (required)</label>
                    <button class="col-span-2 bg-green-600 text-white py-1.5 rounded">Add related product</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bulk price tool -->
    <div class="bg-white rounded-lg shadow p-5 mt-6">
        <h2 class="font-semibold mb-3">⚡ Bulk price update (by category)</h2>
        <form method="POST" class="flex flex-wrap items-end gap-2 text-sm">
            <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="bulk_price">
            <select name="bulk_category" class="px-3 py-2 border rounded"><?php foreach ($topCats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
            <select name="bulk_method" class="px-3 py-2 border rounded"><option value="percent">% change</option><option value="fixed">$ change</option><option value="set">set to</option></select>
            <input type="number" step="0.01" name="bulk_value" placeholder="value (e.g. -10)" class="px-3 py-2 border rounded w-32" required>
            <button class="bg-amber-600 text-white px-4 py-2 rounded" onclick="return confirm('Apply bulk price change to this category?')">Apply</button>
        </form>
    </div>
</div>
</body>
</html>
