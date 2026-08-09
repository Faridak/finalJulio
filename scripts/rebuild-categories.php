<?php
/**
 * Rebuild the category tree from products.category strings ("Parent: Child")
 * and link products.category_id. Idempotent.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../config/database.php';

$rows = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> ''")->fetchAll(PDO::FETCH_COLUMN);
echo count($rows) . " distinct category strings\n";

$getParent = $pdo->prepare("SELECT id FROM categories WHERE parent_id IS NULL AND name = ?");
$insParent = $pdo->prepare("INSERT INTO categories (parent_id, name) VALUES (NULL, ?)");
$getChild  = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ? AND name = ?");
$insChild  = $pdo->prepare("INSERT INTO categories (parent_id, name) VALUES (?, ?)");
$link      = $pdo->prepare("UPDATE products SET category_id = ? WHERE category = ?");

$made = 0;
foreach ($rows as $cat) {
    $parts = array_map('trim', explode(':', $cat, 2));
    $topName = mb_substr($parts[0] ?: 'Uncategorized', 0, 190);
    $subName = isset($parts[1]) && $parts[1] !== '' ? mb_substr($parts[1], 0, 190) : null;

    $getParent->execute([$topName]);
    $pid = (int) $getParent->fetchColumn();
    if (!$pid) { $insParent->execute([$topName]); $pid = (int) $pdo->lastInsertId(); $made++; }

    $leaf = $pid;
    if ($subName !== null) {
        $getChild->execute([$pid, $subName]);
        $cid = (int) $getChild->fetchColumn();
        if (!$cid) { $insChild->execute([$pid, $subName]); $cid = (int) $pdo->lastInsertId(); $made++; }
        $leaf = $cid;
    }
    $link->execute([$leaf, $cat]);
}
// products with no category string
$pdo->exec("INSERT INTO categories (parent_id, name) SELECT NULL, 'Uncategorized'
            FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name='Uncategorized' AND parent_id IS NULL)");
$unc = $pdo->query("SELECT id FROM categories WHERE name='Uncategorized' AND parent_id IS NULL")->fetchColumn();
$pdo->exec("UPDATE products SET category_id = $unc WHERE category_id IS NULL OR category_id = 0");

echo "created $made node(s); tree: "
   . $pdo->query("SELECT COUNT(*) FROM categories WHERE parent_id IS NULL")->fetchColumn() . " top / "
   . $pdo->query("SELECT COUNT(*) FROM categories WHERE parent_id IS NOT NULL")->fetchColumn() . " sub; "
   . $pdo->query("SELECT COUNT(*) FROM products WHERE category_id IS NOT NULL")->fetchColumn() . " products linked\n";
