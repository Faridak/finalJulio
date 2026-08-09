<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireRole('merchant');
$mid = (int) $_SESSION['user_id'];
$report = null; $error = '';

// CSV template download
if (isset($_GET['template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ventdepot-products-template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['name', 'price', 'cost_price', 'stock', 'category', 'description', 'image_url']);
    fputcsv($out, ['Extractor axial 12"', '129.99', '78.50', '25', 'Ventilación: Extractores', 'Extractor industrial 12 pulgadas, 110V', 'https://example.com/foto.jpg']);
    fclose($out); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(419); die('CSRF mismatch'); }
    if (empty($_FILES['csv']['tmp_name'])) {
        $error = 'Choose a CSV file.';
    } else {
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        $header = array_map(fn($h) => strtolower(trim($h)), fgetcsv($fh) ?: []);
        $required = ['name', 'price'];
        if (array_diff($required, $header)) {
            $error = 'CSV must include at least: name, price. Download the template below.';
        } else {
            $ins = $pdo->prepare("
                INSERT INTO products (name, price, cost_price, inventory, category, description, image_url,
                                      merchant_id, ownership, created_at)
                VALUES (?,?,?,?,?,?,?,?, 'marketplace', NOW())");
            $ok = 0; $failed = []; $row = 1;
            while (($line = fgetcsv($fh)) !== false && $row <= 501) {
                $row++;
                $d = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), ''));
                $name = trim($d['name'] ?? '');
                $price = (float) ($d['price'] ?? 0);
                if ($name === '' || mb_strlen($name) > 190) { $failed[] = "Row $row: missing/too-long name"; continue; }
                if ($price <= 0 || $price > 1000000) { $failed[] = "Row $row: invalid price"; continue; }
                $img = trim($d['image_url'] ?? '');
                if ($img !== '' && !preg_match('#^https?://#', $img)) { $failed[] = "Row $row: image_url must be http(s)"; continue; }
                try {
                    $ins->execute([
                        $name, $price,
                        max(0, (float) ($d['cost_price'] ?? 0)),
                        max(0, (int) ($d['stock'] ?? 0)),
                        mb_substr(trim($d['category'] ?? ''), 0, 190) ?: null,
                        trim($d['description'] ?? '') ?: null,
                        $img ?: null,
                        $mid,
                    ]);
                    $ok++;
                } catch (PDOException $e) { $failed[] = "Row $row: " . substr($e->getMessage(), 0, 80); }
            }
            fclose($fh);
            $report = ['ok' => $ok, 'failed' => $failed];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Upload - VentDepot Seller</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<nav class="bg-gray-900 text-white">
    <div class="max-w-4xl mx-auto px-4 h-12 flex items-center gap-4 text-sm">
        <a href="dashboard.php" class="font-bold">VentDepot <span class="text-blue-400">Seller</span></a>
        <a href="products.php" class="hover:text-blue-300">Products</a>
        <a href="bulk-upload.php" class="text-blue-300 font-medium">Bulk Upload</a>
        <a href="earnings.php" class="hover:text-blue-300">Earnings</a>
        <a href="../logout.php" class="ml-auto hover:text-blue-300">Logout</a>
    </div>
</nav>
<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Bulk product upload</h1>
    <p class="text-sm text-gray-500 mb-6">Upload up to <b>500 products</b> in one CSV. Columns: <code>name, price, cost_price, stock, category, description, image_url</code> — only name and price are required. <a class="text-blue-600 hover:underline" href="?template=1">⬇ Download template</a></p>

    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($report): ?>
        <div class="bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded mb-4">
            ✅ <b><?= (int) $report['ok'] ?></b> product(s) created<?= $report['failed'] ? ' · ' . count($report['failed']) . ' row(s) skipped' : '' ?>.
            <a href="products.php" class="underline">Review your products →</a>
        </div>
        <?php if ($report['failed']): ?>
        <div class="bg-amber-50 border border-amber-200 rounded p-4 mb-4 text-sm text-amber-900">
            <b>Skipped rows:</b>
            <ul class="list-disc ml-5 mt-1"><?php foreach (array_slice($report['failed'], 0, 20) as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6">
        <?= Security::getCSRFInput() ?>
        <label class="block border-2 border-dashed border-gray-300 rounded-xl p-10 text-center cursor-pointer hover:border-blue-400">
            <input type="file" name="csv" accept=".csv,text/csv" required class="hidden" onchange="this.form.querySelector('#fname').textContent = this.files[0]?.name || ''">
            <div class="text-4xl mb-2">📄</div>
            <div class="text-gray-600">Click to choose your CSV file</div>
            <div id="fname" class="text-sm text-blue-600 font-mono mt-1"></div>
        </label>
        <button class="mt-4 w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg">Upload products</button>
        <p class="text-xs text-gray-400 mt-2">New products go live immediately under your store. Prices in USD. Images by URL for now (or upload per-product later from Products).</p>
    </form>
</div>
</body>
</html>
