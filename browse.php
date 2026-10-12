<?php
session_start();
require_once 'config/database.php';
require_once 'services/SearchService.php';
require_once 'includes/breadcrumbs.php';

$search = new SearchService($pdo);
$params = [
    'q' => $_GET['q'] ?? '',
    'category_id' => (int) ($_GET['category_id'] ?? 0),
    'merchant_id' => (int) ($_GET['merchant_id'] ?? 0),
    'min_price' => $_GET['min_price'] ?? '',
    'max_price' => $_GET['max_price'] ?? '',
    'in_stock' => !empty($_GET['in_stock']),
    'on_sale' => !empty($_GET['on_sale']),
    'featured' => !empty($_GET['featured']),
    'sort' => $_GET['sort'] ?? 'relevance',
    'page' => (int) ($_GET['page'] ?? 1),
];
$r = $search->search($params);

// URL helper — current filters with overrides ([]=remove).
function urlWith($params, array $over = []) {
    $q = array_filter([
        'q' => $params['q'], 'category_id' => $params['category_id'], 'merchant_id' => $params['merchant_id'],
        'min_price' => $params['min_price'], 'max_price' => $params['max_price'],
        'in_stock' => $params['in_stock'] ? 1 : '', 'on_sale' => $params['on_sale'] ? 1 : '',
        'featured' => $params['featured'] ? 1 : '', 'sort' => $params['sort'],
    ], fn($v) => $v !== '' && $v !== 0);
    foreach ($over as $k => $v) { if ($v === null) unset($q[$k]); else $q[$k] = $v; }
    return 'browse.php?' . http_build_query($q);
}
$activeCat = null;
if ($params['category_id']) { $c = $pdo->prepare("SELECT name FROM categories WHERE id=?"); $c->execute([$params['category_id']]); $activeCat = $c->fetchColumn(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse — VentDepot</title>
    <link rel="stylesheet" href="public/vendor/css/tailwind.min.css">
    <link rel="stylesheet" href="public/vendor/css/fontawesome.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 pt-6">
        <?php
        $crumbs = [['Home', 'index.php']];
        if ($params['category_id']) $crumbs = array_merge($crumbs, categoryTrail($pdo, $params['category_id']));
        else $crumbs[] = [$params['q'] !== '' ? 'Search: "' . $params['q'] . '"' : 'All products', null];
        echo renderBreadcrumbs($crumbs);
        ?>
    </div>

    <div class="max-w-7xl mx-auto px-4 pb-6 grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Facet sidebar -->
        <aside class="space-y-6">
            <?php
            $anyFilter = $params['category_id'] || $params['merchant_id'] || $params['min_price'] !== '' || $params['max_price'] !== '' || $params['in_stock'] || $params['on_sale'] || $params['featured'];
            if ($anyFilter): ?>
                <a href="<?= htmlspecialchars('browse.php?' . http_build_query(array_filter(['q'=>$params['q'],'sort'=>$params['sort']]))) ?>" class="text-sm text-blue-600 hover:underline">✕ Clear all filters</a>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="font-semibold mb-2">Category</h3>
                <?php if ($activeCat): ?><div class="text-sm mb-2"><span class="font-medium"><?= htmlspecialchars($activeCat) ?></span> <a href="<?= htmlspecialchars(urlWith($params, ['category_id'=>null])) ?>" class="text-blue-600 text-xs">clear</a></div><?php endif; ?>
                <ul class="space-y-1 text-sm">
                    <?php foreach ($r['facets']['categories'] as $c): ?>
                        <li><a href="<?= htmlspecialchars(urlWith($params, ['category_id'=>$c['top_id'], 'page'=>null])) ?>" class="flex justify-between hover:text-blue-600 <?= $params['category_id']==$c['top_id']?'font-semibold text-blue-600':'text-gray-700' ?>">
                            <span class="truncate"><?= htmlspecialchars($c['top_name']) ?></span><span class="text-gray-400"><?= (int)$c['n'] ?></span></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="font-semibold mb-2">Price</h3>
                <form method="GET" class="flex gap-2 items-center text-sm">
                    <?php foreach (['q','category_id','merchant_id','sort'] as $k) if ($params[$k]) echo '<input type="hidden" name="'.$k.'" value="'.htmlspecialchars($params[$k]).'">'; ?>
                    <input type="number" name="min_price" value="<?= htmlspecialchars($params['min_price']) ?>" placeholder="min" class="w-20 px-2 py-1 border rounded">
                    <span>–</span>
                    <input type="number" name="max_price" value="<?= htmlspecialchars($params['max_price']) ?>" placeholder="max" class="w-20 px-2 py-1 border rounded">
                    <button class="bg-gray-800 text-white px-3 py-1 rounded">Go</button>
                </form>
                <p class="text-xs text-gray-400 mt-1">range $<?= number_format($r['facets']['price']['min'],0) ?>–$<?= number_format($r['facets']['price']['max'],0) ?></p>
            </div>

            <?php if ($r['facets']['sellers']): ?>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="font-semibold mb-2">Seller</h3>
                <ul class="space-y-1 text-sm">
                    <?php foreach ($r['facets']['sellers'] as $s): ?>
                        <li><a href="<?= htmlspecialchars(urlWith($params, ['merchant_id'=>$s['merchant_id'], 'page'=>null])) ?>" class="flex justify-between hover:text-blue-600 <?= $params['merchant_id']==$s['merchant_id']?'font-semibold text-blue-600':'text-gray-700' ?>">
                            <span class="truncate"><?= htmlspecialchars(explode('@',$s['email'])[0]) ?></span><span class="text-gray-400"><?= (int)$s['n'] ?></span></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow p-4 space-y-2 text-sm">
                <a href="<?= htmlspecialchars(urlWith($params, ['in_stock'=>$params['in_stock']?null:1])) ?>" class="flex items-center gap-2"><span class="w-4 h-4 border rounded <?= $params['in_stock']?'bg-blue-600':'' ?>"></span> In stock</a>
                <a href="<?= htmlspecialchars(urlWith($params, ['on_sale'=>$params['on_sale']?null:1])) ?>" class="flex items-center gap-2"><span class="w-4 h-4 border rounded <?= $params['on_sale']?'bg-blue-600':'' ?>"></span> On sale</a>
                <a href="<?= htmlspecialchars(urlWith($params, ['featured'=>$params['featured']?null:1])) ?>" class="flex items-center gap-2"><span class="w-4 h-4 border rounded <?= $params['featured']?'bg-blue-600':'' ?>"></span> Featured</a>
            </div>
        </aside>

        <!-- Results -->
        <main class="md:col-span-3">
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm text-gray-600"><b><?= number_format($r['total']) ?></b> products<?= $params['q']?' for “'.htmlspecialchars($params['q']).'”':'' ?></p>
                <form method="GET" class="text-sm">
                    <?php foreach (['q','category_id','merchant_id','min_price','max_price'] as $k) if ($params[$k]!=='' && $params[$k]!=0) echo '<input type="hidden" name="'.$k.'" value="'.htmlspecialchars($params[$k]).'">'; ?>
                    <?php if($params['in_stock'])echo '<input type="hidden" name="in_stock" value="1">'; if($params['on_sale'])echo '<input type="hidden" name="on_sale" value="1">'; if($params['featured'])echo '<input type="hidden" name="featured" value="1">'; ?>
                    <label>Sort <select name="sort" onchange="this.form.submit()" class="border rounded px-2 py-1">
                        <?php foreach (['relevance'=>'Relevance','price_asc'=>'Price ↑','price_desc'=>'Price ↓','newest'=>'Newest','name'=>'Name'] as $k=>$lbl): ?>
                            <option value="<?= $k ?>" <?= $params['sort']==$k?'selected':'' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select></label>
                </form>
            </div>

            <?php if (empty($r['products'])): ?>
                <div class="bg-white rounded-lg shadow p-12 text-center text-gray-400">No products match your filters.</div>
            <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($r['products'] as $pr): ?>
                    <a href="product.php?id=<?= (int)$pr['id'] ?>" class="bg-white rounded-lg shadow hover:shadow-md transition overflow-hidden block">
                        <img src="<?= htmlspecialchars(asset_url($pr['image_url'])) ?>" class="w-full h-40 object-cover" alt="">
                        <div class="p-3">
                            <div class="text-sm font-medium text-gray-800 line-clamp-2"><?= htmlspecialchars($pr['name']) ?></div>
                            <?php if (!empty($pr['review_count'])): ?>
                            <div class="text-xs mt-0.5"><span class="text-amber-500"><?= str_repeat('★', (int) round($pr['avg_rating'])) . str_repeat('☆', 5 - (int) round($pr['avg_rating'])) ?></span>
                                <span class="text-gray-400"><?= $pr['avg_rating'] ?> (<?= (int)$pr['review_count'] ?>)</span></div>
                            <?php endif; ?>
                            <div class="mt-1"><span class="font-bold text-gray-900">$<?= number_format((float)$pr['price'],2) ?></span>
                                <?php if ($pr['compare']): ?><span class="text-xs text-gray-400 line-through ml-1">$<?= number_format((float)$pr['compare'],2) ?></span><?php endif; ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($r['pages'] > 1): ?>
            <div class="flex justify-center gap-1 mt-8">
                <?php for ($i = max(1,$r['page']-2); $i <= min($r['pages'],$r['page']+2); $i++): ?>
                    <a href="<?= htmlspecialchars(urlWith($params, ['page'=>$i])) ?>" class="px-3 py-1 rounded <?= $i==$r['page']?'bg-blue-600 text-white':'bg-white border text-gray-700' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
