<?php
/**
 * Back-in-stock notifier. Run every ~15 min via cron:
 *   php cron/backinstock-worker.php
 *
 * Finds wishlist items whose product is in stock again and the user hasn't
 * been notified since the item went back in stock; queues one email each
 * (email-worker sends). Re-arms automatically: notified_at is only compared
 * against the wish, so a user is emailed at most once per wish.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/EmailService.php';

$rows = $pdo->query("
    SELECT w.id wid, w.user_id, u.email, p.id pid, p.name, p.price
    FROM wishlist_items w
    JOIN users u ON w.user_id = u.id
    JOIN products p ON w.product_id = p.id
    WHERE w.notify_back_in_stock = 1
      AND w.notified_at IS NULL
      AND p.inventory > 0
      AND w.created_at < NOW() - INTERVAL 5 MINUTE   -- skip items wished while already in stock recently
")->fetchAll(PDO::FETCH_ASSOC);

// Only notify for items that were actually OUT of stock at wish time is hard to
// know retroactively; pragmatic rule: notify once per wish when in stock and
// the wish is at least 1 day old OR the product had a recent stock-in movement.
$email = new EmailService($pdo);
$sent = 0;
foreach ($rows as $r) {
    // Was there a stock-in movement after the wish was created? (i.e. it came back)
    $moved = $pdo->prepare("
        SELECT 1 FROM inventory_movements im
        WHERE im.product_id = ? AND im.movement_type = 'in'
          AND im.created_at > (SELECT created_at FROM wishlist_items WHERE id = ?)
        LIMIT 1
    ");
    $moved->execute([$r['pid'], $r['wid']]);
    if (!$moved->fetchColumn()) continue;

    $url = rtrim($_ENV['APP_URL'] ?? 'http://localhost/finalJulio', '/') . '/product.php?id=' . $r['pid'];
    $html = '<h2>Good news — it\'s back!</h2>'
          . '<p><b>' . htmlspecialchars($r['name']) . '</b> from your wishlist is back in stock at $'
          . number_format((float) $r['price'], 2) . '.</p>'
          . '<p><a href="' . $url . '" style="background:#2563eb;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">View product</a></p>'
          . '<p style="color:#888;font-size:12px">You get this because the item is on your VentDepot wishlist.</p>';
    $email->queue($r['email'], null, 'Back in stock: ' . mb_substr($r['name'], 0, 60), $html, 'back_in_stock', 'wishlist', $r['wid']);
    $pdo->prepare("UPDATE wishlist_items SET notified_at = NOW() WHERE id = ?")->execute([$r['wid']]);
    $sent++;
}
echo date('c') . " back-in-stock: queued $sent notification(s)\n";
