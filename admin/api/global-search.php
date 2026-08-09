<?php
/**
 * Global admin search: one query across orders, products, customers,
 * suppliers, POs, and engineering tickets. GET ?q=...
 */
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json');
if (!isLoggedIn() || !isStaff()) { http_response_code(403); echo '{"error":"Staff only"}'; exit; }

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo '{"groups":[]}'; exit; }

$like = '%' . $q . '%';
$num = (int) preg_replace('/\D/', '', $q);   // "VD-000123" -> 123
$groups = [];

// Orders (by id / VD ref / promo code)
$s = $pdo->prepare("SELECT o.id, o.total, o.status, o.payment_status, u.email
                    FROM orders o LEFT JOIN users u ON o.user_id=u.id
                    WHERE o.id = ? OR o.promo_code LIKE ? ORDER BY o.id DESC LIMIT 5");
$s->execute([$num, $like]);
if ($rows = $s->fetchAll(PDO::FETCH_ASSOC)) {
    $groups[] = ['label' => 'Orders', 'items' => array_map(fn($r) => [
        'title' => '#VD-' . str_pad($r['id'], 6, '0', STR_PAD_LEFT) . ' — $' . number_format($r['total'], 2),
        'sub' => ($r['email'] ?? '') . ' · ' . $r['status'] . '/' . $r['payment_status'],
        'url' => 'orders.php?search=' . $r['id']], $rows)];
}

// Products (name / sku / barcode / id)
$s = $pdo->prepare("SELECT id, name, sku, price, inventory FROM products
                    WHERE name LIKE ? OR sku LIKE ? OR barcode LIKE ? OR id = ? LIMIT 6");
$s->execute([$like, $like, $like, $num]);
if ($rows = $s->fetchAll(PDO::FETCH_ASSOC)) {
    $groups[] = ['label' => 'Products', 'items' => array_map(fn($r) => [
        'title' => $r['name'],
        'sub' => $r['sku'] . ' · $' . number_format($r['price'], 2) . ' · stock ' . $r['inventory'],
        'url' => 'catalog-dashboard.php?product_id=' . $r['id']], $rows)];
}

// Customers / users
$s = $pdo->prepare("SELECT id, email, role FROM users WHERE email LIKE ? LIMIT 5");
$s->execute([$like]);
if ($rows = $s->fetchAll(PDO::FETCH_ASSOC)) {
    $groups[] = ['label' => 'Users', 'items' => array_map(fn($r) => [
        'title' => $r['email'], 'sub' => $r['role'] . ' · #' . $r['id'],
        'url' => 'users.php?search=' . urlencode($r['email'])], $rows)];
}

// Suppliers + POs
$s = $pdo->prepare("SELECT id, company_name, supplier_code FROM suppliers WHERE company_name LIKE ? OR supplier_code LIKE ? LIMIT 4");
$s->execute([$like, $like]);
if ($rows = $s->fetchAll(PDO::FETCH_ASSOC)) {
    $groups[] = ['label' => 'Suppliers', 'items' => array_map(fn($r) => [
        'title' => $r['company_name'], 'sub' => $r['supplier_code'],
        'url' => 'supplier-scorecard.php'], $rows)];
}
$s = $pdo->prepare("SELECT id, po_number, total_amount, status FROM purchase_orders WHERE po_number LIKE ? OR id = ? LIMIT 4");
$s->execute([$like, $num]);
if ($rows = $s->fetchAll(PDO::FETCH_ASSOC)) {
    $groups[] = ['label' => 'Purchase Orders', 'items' => array_map(fn($r) => [
        'title' => $r['po_number'], 'sub' => '$' . number_format($r['total_amount'], 2) . ' · ' . $r['status'],
        'url' => 'po-manage.php'], $rows)];
}

// Engineering tickets
$s = $pdo->prepare("SELECT id, product_type, status FROM quotes WHERE product_type LIKE ? OR id = ? LIMIT 4");
$s->execute([$like, $num]);
if ($rows = $s->fetchAll(PDO::FETCH_ASSOC)) {
    $groups[] = ['label' => 'Engineering', 'items' => array_map(fn($r) => [
        'title' => '#' . $r['id'] . ' ' . $r['product_type'], 'sub' => $r['status'],
        'url' => 'work-order.php?quote=' . $r['id']], $rows)];
}

echo json_encode(['groups' => $groups]);
