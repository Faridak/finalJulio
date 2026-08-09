<?php
/**
 * Legacy product ETL — loads the extracted slice into the new marketplace schema.
 *
 *   Source : migration/legacy/products.clean.json  (3,413 products)
 *            migration/legacy/photos.clean.json    (12,475 photos)
 *   Target : ventdepot.products (+ product_inventory, product_dimensions, product_images)
 *
 * Idempotent: upserts keyed on products.legacy_id, so re-running updates rather
 * than duplicating. Preserves legacy image FILENAMES verbatim (never renamed);
 * only a single directory prefix is applied, adjustable later in one UPDATE.
 *
 * Usage:  php migration/run_etl.php
 */

$DB   = ['host' => '127.0.0.1', 'name' => 'ventdepot', 'user' => 'root', 'pass' => ''];
$DIR  = __DIR__ . '/legacy';
$LOCATION_ID = 1;                 // Main Warehouse (WH001)
$IMAGE_PREFIX = 'uploads/legacy/'; // legacy filenames kept as-is under this prefix
$HOUSE_MERCHANT_EMAIL = 'catalog@ventdepot.com';

$pdo = new PDO("mysql:host={$DB['host']};dbname={$DB['name']};charset=utf8mb4", $DB['user'], $DB['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function loadJson($file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing $file\n");
        exit(1);
    }
    return json_decode(file_get_contents($file), true);
}

/** Turn legacy spec HTML into clean, readable plain text. */
function cleanDescription($html) {
    if ($html === null || $html === '') return null;
    $t = preg_replace('#<\s*br\s*/?>#i', "\n", $html);
    $t = preg_replace('#</\s*(p|div|li|tr)\s*>#i', "\n", $t);
    $t = strip_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t]+/", ' ', $t);
    $t = preg_replace("/\n{3,}/", "\n\n", $t);
    return trim($t);
}

$products = loadJson("$DIR/products.clean.json");
$photos   = loadJson("$DIR/photos.clean.json");
echo "Loaded " . count($products) . " products, " . count($photos) . " photos from extract.\n";

// ---------------------------------------------------------------------------
// 1. Ensure a house merchant owns the migrated catalog.
// ---------------------------------------------------------------------------
$sel = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$sel->execute([$HOUSE_MERCHANT_EMAIL]);
$merchantId = $sel->fetchColumn();
if (!$merchantId) {
    $pdo->prepare("INSERT INTO users (email, password, role, created_at) VALUES (?, ?, 'merchant', NOW())")
        ->execute([$HOUSE_MERCHANT_EMAIL, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $merchantId = $pdo->lastInsertId();
    echo "Created house merchant #$merchantId ($HOUSE_MERCHANT_EMAIL)\n";
} else {
    echo "House merchant #$merchantId ($HOUSE_MERCHANT_EMAIL)\n";
}

// Group photos by legacy_id (already ordered by sort_order in the extract).
$photosByProduct = [];
foreach ($photos as $ph) {
    $photosByProduct[$ph['legacy_id']][] = $ph;
}

// ---------------------------------------------------------------------------
// Prepared statements.
// ---------------------------------------------------------------------------
$upProduct = $pdo->prepare("
    INSERT INTO products
        (legacy_id, merchant_id, name, sku, description, short_description,
         price, cost_price, category, subcategory, image_url, legacy_image_slug, inventory, created_at, updated_at)
    VALUES (:legacy_id, :merchant_id, :name, :sku, :description, :short_description,
            :price, :cost_price, :category, :subcategory, :image_url, :legacy_image_slug, :inventory, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        name=VALUES(name), sku=VALUES(sku), description=VALUES(description),
        short_description=VALUES(short_description), price=VALUES(price), cost_price=VALUES(cost_price),
        category=VALUES(category), subcategory=VALUES(subcategory), image_url=VALUES(image_url),
        legacy_image_slug=VALUES(legacy_image_slug), inventory=VALUES(inventory), updated_at=NOW()
");

$upInventory = $pdo->prepare("
    INSERT INTO product_inventory
        (product_id, location_id, quantity_on_hand, quantity_reserved, quantity_available,
         reorder_point, reorder_quantity, max_stock_level, average_cost, last_cost, last_movement_at, created_at)
    VALUES (:pid, :loc, :qty, 0, :qty, :rop, :roq, :maxs, :cost, :cost, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        quantity_on_hand=VALUES(quantity_on_hand), quantity_available=VALUES(quantity_available),
        reorder_point=VALUES(reorder_point), max_stock_level=VALUES(max_stock_level),
        average_cost=VALUES(average_cost), last_cost=VALUES(last_cost), last_movement_at=NOW()
");

$upDim = $pdo->prepare("
    INSERT INTO product_dimensions (product_id, weight_kg, created_at)
    VALUES (:pid, :wt, NOW())
    ON DUPLICATE KEY UPDATE weight_kg=VALUES(weight_kg)
");

$upImage = $pdo->prepare("
    INSERT INTO product_images (product_id, image_path, legacy_filename, sort_order, is_primary, created_at)
    VALUES (:pid, :path, :fn, :sort, :prim, NOW())
    ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order), is_primary=VALUES(is_primary), legacy_filename=VALUES(legacy_filename)
");

$findId = $pdo->prepare("SELECT id FROM products WHERE legacy_id = ? LIMIT 1");

// ---------------------------------------------------------------------------
// 2. Load.
// ---------------------------------------------------------------------------
$n = 0; $withImg = 0; $withDim = 0; $imgRows = 0;
$pdo->beginTransaction();

foreach ($products as $p) {
    $legacyId = $p['legacy_id'];
    $stock    = (int) ($p['stock'] ?? 0);

    // Primary image path (preserve filename verbatim).
    $imageUrl = null;
    $primaryFilename = null;
    if (!empty($photosByProduct[$legacyId])) {
        $primaryFilename = $photosByProduct[$legacyId][0]['filename'];
        $imageUrl = $IMAGE_PREFIX . $primaryFilename;
    }

    $upProduct->execute([
        ':legacy_id'         => $legacyId,
        ':merchant_id'       => $merchantId,
        ':name'              => trim($p['name'] ?? '') ?: ('Product ' . $legacyId),
        ':sku'               => $p['sku'] ?? $legacyId,
        ':description'       => cleanDescription($p['description'] ?? ''),
        ':short_description' => mb_substr(trim((string) ($p['short_description'] ?? '')), 0, 500) ?: null,
        ':price'             => is_numeric($p['price'] ?? null) ? round((float) $p['price'], 2) : 0,
        ':cost_price'        => is_numeric($p['cost_price'] ?? null) ? round((float) $p['cost_price'], 2) : 0,
        ':category'          => mb_substr(trim((string) ($p['category'] ?? '')), 0, 100) ?: 'Uncategorized',
        ':subcategory'       => mb_substr(trim((string) ($p['subcategory'] ?? '')), 0, 100) ?: null,
        ':image_url'         => $imageUrl,
        ':legacy_image_slug' => $p['image_slug'] ?? null,
        ':inventory'         => $stock,
    ]);

    $findId->execute([$legacyId]);
    $pid = (int) $findId->fetchColumn();

    // Inventory detail.
    $upInventory->execute([
        ':pid'  => $pid, ':loc' => $LOCATION_ID, ':qty' => $stock,
        ':rop'  => (int) ($p['reorder_point'] ?? 0),
        ':roq'  => max(1, (int) ($p['min_stock'] ?? 0)),
        ':maxs' => (int) ($p['max_stock'] ?? 0),
        ':cost' => is_numeric($p['cost_price'] ?? null) ? round((float) $p['cost_price'], 2) : 0,
    ]);

    // Weight → dimensions.
    if (is_numeric($p['weight'] ?? null) && (float) $p['weight'] > 0) {
        $upDim->execute([':pid' => $pid, ':wt' => round((float) $p['weight'], 3)]);
        $withDim++;
    }

    // Images (preserve filenames; first = primary).
    if (!empty($photosByProduct[$legacyId])) {
        $withImg++;
        $i = 0;
        foreach ($photosByProduct[$legacyId] as $ph) {
            $upImage->execute([
                ':pid'  => $pid,
                ':path' => $IMAGE_PREFIX . $ph['filename'],
                ':fn'   => $ph['filename'],
                ':sort' => (int) ($ph['sort_order'] ?? $i),
                ':prim' => $i === 0 ? 1 : 0,
            ]);
            $imgRows++; $i++;
        }
    }

    $n++;
    if ($n % 1000 === 0) { $pdo->commit(); $pdo->beginTransaction(); echo "  ...$n products\n"; }
}

$pdo->commit();

echo "\n=== ETL complete ===\n";
echo "Products upserted : $n\n";
echo "With weight/dims  : $withDim\n";
echo "With images       : $withImg products, $imgRows image rows\n";
