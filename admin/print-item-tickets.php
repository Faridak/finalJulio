<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireRole('admin');
requireScope('catalog');

// Store contact defaults (editable via site_settings later).
$TEL = '55-5822-1516';
$WEB = 'VentDepot.com';

// Selection: ?q= search, or ?ids=1,2,3
$q = trim($_GET['q'] ?? '');
$ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));

$products = [];
if ($ids) {
    $in = implode(',', $ids);
    $products = $pdo->query("
        SELECT id, sku, barcode, name, short_description, price, ownership
        FROM products WHERE id IN ($in) AND ownership='own'
    ")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($q !== '') {
    $st = $pdo->prepare("
        SELECT id, sku, barcode, name, short_description, price, ownership
        FROM products
        WHERE ownership='own' AND (sku LIKE ? OR name LIKE ? OR barcode LIKE ?)
        ORDER BY name LIMIT 60
    ");
    $like = "%$q%";
    $st->execute([$like, $like, $like]);
    $products = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Item Tickets - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
    <script src="../public/vendor/js/JsBarcode.all.min.js"></script>
    <script src="../public/vendor/js/qrcode.min.js"></script>
    <style>
        @media print { .no-print { display:none } body { background:#fff } .ticket { break-inside: avoid } }
        .ticket { width: 3.2in; }
    </style>
</head>
<body class="bg-gray-100">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-5xl mx-auto px-4 py-6">
    <div class="no-print mb-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Item Sales Tickets</h1>
            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded">🖨️ Print</button>
        </div>
        <form method="GET" class="mt-3 flex gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="search SKU / name / barcode"
                   class="flex-1 px-3 py-2 border rounded">
            <button class="bg-gray-800 text-white px-4 rounded">Load</button>
        </form>
        <p class="text-sm text-gray-500 mt-2"><?= count($products) ?> ticket(s). Price = supplier cost + margin (stored in <code>price</code>).</p>
    </div>

    <div class="flex flex-wrap gap-3">
        <?php foreach ($products as $i => $p):
            $sku = $p['sku'] ?: ('ID' . $p['id']);
            $barcode = $p['barcode'] ?: $sku; ?>
            <div class="ticket bg-white border border-gray-300 rounded-lg p-3 flex gap-3">
                <div class="flex-1 min-w-0">
                    <div class="text-2xl font-extrabold tracking-tight"><?= htmlspecialchars($sku) ?></div>
                    <div class="text-base font-semibold text-gray-800 truncate"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="text-[11px] text-gray-600 leading-snug mt-1 line-clamp-2">
                        <?= htmlspecialchars($p['short_description'] ?? '') ?>
                    </div>
                    <div class="text-[11px] text-gray-700 mt-2">Teléfono: <?= htmlspecialchars($TEL) ?></div>
                    <div class="text-[11px] text-gray-700">Web: <?= htmlspecialchars($WEB) ?></div>
                    <div class="mt-1 flex items-end justify-between">
                        <div class="text-lg font-bold text-green-700">$<?= number_format((float)$p['price'], 2) ?></div>
                        <div class="text-[10px] text-gray-400">JDP</div>
                    </div>
                    <svg class="barcode mt-1" data-code="<?= htmlspecialchars($barcode) ?>"></svg>
                </div>
                <div class="qr shrink-0" data-code="<?= htmlspecialchars($sku) ?>"></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($products)): ?>
        <p class="text-gray-400 text-center py-12">Search above to load item tickets.</p>
    <?php endif; ?>
</div>

<script>
    // Render QR codes
    document.querySelectorAll('.qr').forEach(el => {
        new QRCode(el, { text: el.dataset.code, width: 90, height: 90, correctLevel: QRCode.CorrectLevel.M });
    });
    // Render Code128 barcodes (for POS/inventory scanners)
    document.querySelectorAll('.barcode').forEach(el => {
        try { JsBarcode(el, el.dataset.code, { format:'CODE128', width:1.3, height:34, fontSize:11, margin:0 }); }
        catch(e){ el.remove(); }
    });
</script>
</body>
</html>
