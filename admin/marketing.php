<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/SiteSettings.php';
require_once '../includes/AuditLog.php';
require_once '../services/PricingService.php';
require_once '../services/EmailService.php';

requireRole('admin');
requireScope('marketing');

$uid = (int) $_SESSION['user_id'];
$notice = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(419); die('CSRF mismatch'); }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'homepage') {
            foreach (['theme','hero_headline','hero_subtext','hero_cta_text','hero_cta_url','hero_image',
                      'popup_title','popup_text','popup_coupon'] as $k) {
                SiteSettings::set($pdo, $k, trim($_POST[$k] ?? ''), $uid);
            }
            SiteSettings::set($pdo, 'popup_enabled', isset($_POST['popup_enabled']) ? '1' : '0', $uid);
            $notice = 'Homepage & theme saved — live immediately.';
            AuditLog::record($pdo, 'mkt.homepage', 'settings', null, 'Homepage/theme updated (theme: ' . ($_POST['theme'] ?? '') . ')');
        } elseif ($action === 'tags') {
            foreach (['gtag_id','meta_pixel_id','custom_head_html'] as $k) SiteSettings::set($pdo, $k, trim($_POST[$k] ?? ''), $uid);
            $notice = 'Tracking tags saved.';
            AuditLog::record($pdo, 'mkt.tags', 'settings', null, 'Tracking tags updated');
        } elseif ($action === 'campaign') {
            // resolve product
            $code = trim($_POST['product'] ?? '');
            $pid = $code === '' ? null : (int) $pdo->query("SELECT id FROM products WHERE sku=" . $pdo->quote($code) . " OR barcode=" . $pdo->quote($code) . " OR id=" . (int) $code . " LIMIT 1")->fetchColumn();
            if ($code !== '' && !$pid) throw new Exception("No product found for \"$code\".");
            $pct = max(1, min(90, (float) $_POST['discount_pct']));
            $ends = $_POST['ends'] ?: date('Y-m-d', strtotime('+14 days'));

            $discountId = null;
            if ($pid) { // auto-applied price cut on the mentioned product
                $discountId = (new PricingService($pdo))->createDiscount($pid, 'percentage', $pct, null, $ends . ' 23:59:59');
            }
            $promo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['promo_code'] ?? '')) ?: null;
            if ($promo) {
                $pdo->prepare("INSERT INTO promo_codes (code, discount_type, value, product_id, ends, created_by) VALUES (?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE value=VALUES(value), ends=VALUES(ends), is_active=1")
                    ->execute([$promo, 'percentage', $pct, $pid, $ends, $uid]);
            }
            $utm = 'https://ventdepot.com/' . ($pid ? "product.php?id=$pid" : 'browse.php?on_sale=1')
                 . '&utm_source=' . strtolower($_POST['channel']) . '&utm_medium=social&utm_campaign=' . urlencode(strtolower(str_replace(' ', '-', $_POST['name'])));
            $pname = $pid ? $pdo->query("SELECT name FROM products WHERE id=$pid")->fetchColumn() : 'toda la tienda';
            $post = "🔥 ¡{$pct}% de descuento en " . $pname . "! "
                  . ($promo ? "Usa el código $promo al pagar. " : "Precio ya rebajado, sin código. ")
                  . "Válido hasta $ends. 👉 $utm";

            $pdo->prepare("INSERT INTO marketing_campaigns (name, channel, product_id, discount_pct, discount_id, promo_code, utm_url, post_text, ends, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([trim($_POST['name']), $_POST['channel'], $pid, $pct, $discountId, $promo, $utm, $post, $ends, $uid]);
            $notice = 'Campaign created — discount live' . ($promo ? ", code $promo active" : '') . '. Post text ready below.';
            AuditLog::record($pdo, 'mkt.campaign', 'campaign', $pdo->lastInsertId(), "Campaign \"{$_POST['name']}\" — {$pct}% off " . ($pid ? "product #$pid" : 'storewide'));
        } elseif ($action === 'promo') {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['code']));
            $userId = null;
            if (trim($_POST['customer_email'] ?? '') !== '') {
                $userId = $pdo->query("SELECT id FROM users WHERE email=" . $pdo->quote(trim($_POST['customer_email'])))->fetchColumn();
                if (!$userId) throw new Exception('No customer with that email.');
            }
            $pdo->prepare("INSERT INTO promo_codes (code, discount_type, value, user_id, max_uses, ends, created_by) VALUES (?,?,?,?,?,?,?)")
                ->execute([$code, $_POST['discount_type'], (float) $_POST['value'], $userId ?: null,
                           (int) $_POST['max_uses'], $_POST['ends'] ?: null, $uid]);
            $notice = "Code $code created" . ($userId ? ' (personal — only that customer can use it)' : '') . '.';
            AuditLog::record($pdo, 'mkt.promo_code', 'promo', $code, "Promo $code created" . ($userId ? " (personal, user #$userId)" : ''));
        } elseif ($action === 'newsletter') {
            $segment = $_POST['segment'];
            $where = "role='customer' OR role=''";
            if ($segment === 'loyal') $where = "id IN (SELECT user_id FROM orders GROUP BY user_id HAVING COUNT(*) >= 2)";
            elseif ($segment === 'recent') $where = "id IN (SELECT user_id FROM orders WHERE created_at >= NOW() - INTERVAL 90 DAY)";
            $recipients = $pdo->query("SELECT id, email FROM users WHERE role NOT IN ('admin','merchant') AND ($where)")->fetchAll(PDO::FETCH_ASSOC);
            $email = new EmailService($pdo);
            foreach ($recipients as $r) $email->queue($r['email'], null, trim($_POST['subject']), $_POST['body_html'], 'newsletter', 'newsletter', 0);
            $pdo->prepare("INSERT INTO newsletters (subject, body_html, segment, recipients, sent_by) VALUES (?,?,?,?,?)")
                ->execute([trim($_POST['subject']), $_POST['body_html'], $segment, count($recipients), $uid]);
            $notice = 'Newsletter queued to ' . count($recipients) . ' recipient(s).';
            AuditLog::record($pdo, 'mkt.newsletter', 'newsletter', $pdo->lastInsertId(), "Newsletter \"{$_POST['subject']}\" to $segment (" . count($recipients) . ')');
        } elseif ($action === 'ticket') {
            $userId = null;
            if (trim($_POST['customer_email'] ?? '') !== '') $userId = $pdo->query("SELECT id FROM users WHERE email=" . $pdo->quote(trim($_POST['customer_email'])))->fetchColumn() ?: null;
            $pdo->prepare("INSERT INTO support_tickets (user_id, customer_email, subject, kind, opened_by) VALUES (?,?,?,?,?)")
                ->execute([$userId, trim($_POST['customer_email']) ?: null, trim($_POST['subject']), $_POST['kind'], $uid]);
            $tid = (int) $pdo->lastInsertId();
            if (trim($_POST['body'] ?? '') !== '') $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, body) VALUES (?,?,?)")->execute([$tid, $uid, trim($_POST['body'])]);
            $notice = "Ticket #$tid opened.";
        } elseif ($action === 'ticket_reply') {
            $tid = (int) $_POST['ticket_id'];
            if (trim($_POST['body'] ?? '') !== '') $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, body) VALUES (?,?,?)")->execute([$tid, $uid, trim($_POST['body'])]);
            if (!empty($_POST['status'])) $pdo->prepare("UPDATE support_tickets SET status=? WHERE id=?")->execute([$_POST['status'], $tid]);
            $notice = "Ticket #$tid updated.";
        } elseif ($action === 'ab_start') {
            $pdo->exec("UPDATE ab_tests SET status='stopped' WHERE status='running'");
            $pdo->prepare("INSERT INTO ab_tests (name, headline_a, headline_b) VALUES (?,?,?)")
                ->execute([trim($_POST['name']), trim($_POST['headline_a']), trim($_POST['headline_b'])]);
            $notice = 'A/B test running — the homepage now splits visitors 50/50.';
        } elseif ($action === 'ab_stop') {
            $win = $_POST['winner'] ?? '';
            $t = $pdo->query("SELECT * FROM ab_tests WHERE id=" . (int) $_POST['test_id'])->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("UPDATE ab_tests SET status='stopped' WHERE id=?")->execute([(int) $_POST['test_id']]);
            if ($t && in_array($win, ['a','b'], true)) {
                SiteSettings::set($pdo, 'hero_headline', $t["headline_$win"], $uid);
                $notice = 'Test stopped — winner ' . strtoupper($win) . ' applied to the homepage.';
            } else $notice = 'Test stopped.';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$S = fn($k, $d = '') => SiteSettings::get($pdo, $k, $d);
$themes = SiteSettings::themes();
$campaigns = $pdo->query("SELECT c.*, p.name pname FROM marketing_campaigns c LEFT JOIN products p ON c.product_id=p.id ORDER BY c.id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
$promos = $pdo->query("SELECT pc.*, u.email FROM promo_codes pc LEFT JOIN users u ON pc.user_id=u.id ORDER BY pc.id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
$tickets = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM ticket_replies r WHERE r.ticket_id=t.id) replies FROM support_tickets t WHERE t.status IN ('open','pending') ORDER BY t.id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
$abRunning = $pdo->query("SELECT * FROM ab_tests WHERE status='running' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$abPast = $pdo->query("SELECT * FROM ab_tests WHERE status='stopped' ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$newsletters = $pdo->query("SELECT * FROM newsletters ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Marketing</h1>
    <p class="text-sm text-gray-500 mb-5">Homepage &amp; seasonal theme · campaigns &amp; promo codes · tracking tags · A/B testing · newsletter · customer tickets. <a href="../index.php" target="_blank" class="text-blue-600 hover:underline">View live site ↗</a></p>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="grid md:grid-cols-2 gap-6">
        <!-- Homepage & theme -->
        <form method="POST" class="bg-white rounded-lg shadow p-5 space-y-3">
            <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="homepage">
            <h2 class="font-semibold text-gray-800">Homepage &amp; seasonal theme</h2>
            <label class="block text-xs text-gray-500">Theme
                <select name="theme" class="w-full px-2 py-1.5 border rounded">
                    <?php foreach ($themes as $key => $t): ?><option value="<?= $key ?>" <?= $S('theme','default')===$key?'selected':'' ?>><?= $t['label'] ?></option><?php endforeach; ?>
                </select></label>
            <label class="block text-xs text-gray-500">Jumbotron headline<input name="hero_headline" value="<?= htmlspecialchars($S('hero_headline')) ?>" class="w-full px-2 py-1.5 border rounded"></label>
            <label class="block text-xs text-gray-500">Sub-text<input name="hero_subtext" value="<?= htmlspecialchars($S('hero_subtext')) ?>" class="w-full px-2 py-1.5 border rounded"></label>
            <div class="grid grid-cols-2 gap-2">
                <label class="block text-xs text-gray-500">CTA button text<input name="hero_cta_text" value="<?= htmlspecialchars($S('hero_cta_text')) ?>" class="w-full px-2 py-1.5 border rounded"></label>
                <label class="block text-xs text-gray-500">CTA link<input name="hero_cta_url" value="<?= htmlspecialchars($S('hero_cta_url')) ?>" class="w-full px-2 py-1.5 border rounded"></label>
            </div>
            <label class="block text-xs text-gray-500">Decorative image URL (e.g. Catrina for Día de Muertos)<input name="hero_image" value="<?= htmlspecialchars($S('hero_image')) ?>" class="w-full px-2 py-1.5 border rounded"></label>
            <div class="border-t pt-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="popup_enabled" <?= $S('popup_enabled')==='1'?'checked':'' ?>> Promo popup enabled</label>
                <div class="grid grid-cols-2 gap-2 mt-1">
                    <input name="popup_title" value="<?= htmlspecialchars($S('popup_title')) ?>" placeholder="Popup title" class="px-2 py-1.5 border rounded">
                    <input name="popup_coupon" value="<?= htmlspecialchars($S('popup_coupon')) ?>" placeholder="Coupon code" class="px-2 py-1.5 border rounded font-mono">
                    <input name="popup_text" value="<?= htmlspecialchars($S('popup_text')) ?>" placeholder="Message" class="px-2 py-1.5 border rounded col-span-2">
                </div>
            </div>
            <button class="w-full bg-blue-600 text-white py-2 rounded">Save homepage</button>
        </form>

        <!-- Campaign generator -->
        <div class="space-y-6">
            <form method="POST" class="bg-white rounded-lg shadow p-5 space-y-3">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="campaign">
                <h2 class="font-semibold text-gray-800">Social campaign generator</h2>
                <div class="grid grid-cols-2 gap-2">
                    <input name="name" required placeholder="Campaign name" class="px-2 py-1.5 border rounded">
                    <select name="channel" class="px-2 py-1.5 border rounded"><option>instagram</option><option>facebook</option><option>tiktok</option><option>x</option><option>whatsapp</option><option>email</option></select>
                    <input name="product" placeholder="Product SKU/id (empty = storewide)" class="px-2 py-1.5 border rounded font-mono">
                    <input name="discount_pct" type="number" value="15" min="1" max="90" class="px-2 py-1.5 border rounded" title="% off">
                    <input name="promo_code" placeholder="Promo code (optional)" class="px-2 py-1.5 border rounded font-mono uppercase">
                    <input name="ends" type="date" class="px-2 py-1.5 border rounded">
                </div>
                <button class="w-full bg-indigo-600 text-white py-2 rounded">Launch campaign</button>
                <p class="text-xs text-gray-400">Creates the discount on the product (price applies automatically on the site) + the ready-to-post text with UTM link.</p>
            </form>
            <?php if ($campaigns): ?>
            <div class="bg-white rounded-lg shadow p-4 text-sm">
                <h3 class="font-semibold text-gray-700 mb-2">Recent campaigns</h3>
                <?php foreach (array_slice($campaigns, 0, 4) as $cp): ?>
                <div class="border-b py-2">
                    <b><?= htmlspecialchars($cp['name']) ?></b> · <?= $cp['channel'] ?> · <?= $cp['discount_pct'] + 0 ?>%<?= $cp['pname'] ? ' · ' . htmlspecialchars(mb_substr($cp['pname'],0,30)) : ' · storewide' ?>
                    <textarea readonly onclick="this.select()" class="w-full text-xs bg-gray-50 border rounded p-1 mt-1" rows="2"><?= htmlspecialchars($cp['post_text']) ?></textarea>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Promo codes incl. personal -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Promo codes <span class="text-xs text-gray-400">(personal = tied to one customer)</span></h2>
            <form method="POST" class="grid grid-cols-2 gap-2 text-sm mb-3">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="promo">
                <input name="code" required placeholder="CODE" class="px-2 py-1.5 border rounded font-mono uppercase">
                <div class="flex gap-1"><select name="discount_type" class="px-2 py-1.5 border rounded flex-1"><option value="percentage">%</option><option value="fixed">$ fixed</option></select>
                    <input name="value" type="number" step="0.01" required placeholder="15" class="px-2 py-1.5 border rounded w-20"></div>
                <input name="customer_email" placeholder="Customer email (empty = anyone)" class="px-2 py-1.5 border rounded col-span-2">
                <input name="max_uses" type="number" value="0" title="0 = unlimited" class="px-2 py-1.5 border rounded">
                <input name="ends" type="date" class="px-2 py-1.5 border rounded">
                <button class="col-span-2 bg-gray-800 text-white py-2 rounded">Create code</button>
            </form>
            <table class="w-full text-xs">
                <thead><tr class="text-left text-gray-500 border-b"><th class="py-1">Code</th><th>Value</th><th>Who</th><th class="text-right">Used</th></tr></thead>
                <tbody><?php foreach ($promos as $pc): ?>
                    <tr class="border-b"><td class="py-1 font-mono font-bold"><?= htmlspecialchars($pc['code']) ?></td>
                        <td><?= $pc['discount_type']==='percentage' ? ($pc['value']+0).'%' : '$'.number_format($pc['value'],2) ?></td>
                        <td><?= $pc['email'] ? '👤 '.htmlspecialchars($pc['email']) : 'anyone' ?></td>
                        <td class="text-right"><?= (int)$pc['used_count'] ?><?= $pc['max_uses']>0 ? '/'.(int)$pc['max_uses'] : '' ?></td></tr>
                <?php endforeach; if (empty($promos)) echo '<tr><td colspan="4" class="py-3 text-center text-gray-400">No codes yet.</td></tr>'; ?></tbody>
            </table>
        </div>

        <!-- Tracking + A/B -->
        <div class="space-y-6">
            <form method="POST" class="bg-white rounded-lg shadow p-5 space-y-2 text-sm">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="tags">
                <h2 class="font-semibold text-gray-800">Tracking tags</h2>
                <label class="block text-xs text-gray-500">Google Tag / GA4 ID (G-XXXX)<input name="gtag_id" value="<?= htmlspecialchars($S('gtag_id')) ?>" class="w-full px-2 py-1.5 border rounded font-mono"></label>
                <label class="block text-xs text-gray-500">Meta Pixel ID<input name="meta_pixel_id" value="<?= htmlspecialchars($S('meta_pixel_id')) ?>" class="w-full px-2 py-1.5 border rounded font-mono"></label>
                <label class="block text-xs text-gray-500">Custom &lt;head&gt; HTML (other pixels)<textarea name="custom_head_html" rows="2" class="w-full px-2 py-1.5 border rounded font-mono text-xs"><?= htmlspecialchars($S('custom_head_html')) ?></textarea></label>
                <button class="w-full bg-gray-800 text-white py-2 rounded">Save tags</button>
            </form>

            <div class="bg-white rounded-lg shadow p-5 text-sm">
                <h2 class="font-semibold text-gray-800 mb-2">A/B test (jumbotron headline)</h2>
                <?php if ($abRunning): $ra = $abRunning['views_a'] ? round($abRunning['clicks_a']/$abRunning['views_a']*100,1) : 0; $rb = $abRunning['views_b'] ? round($abRunning['clicks_b']/$abRunning['views_b']*100,1) : 0; ?>
                    <p class="mb-2"><b><?= htmlspecialchars($abRunning['name']) ?></b> — running</p>
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <div class="border rounded p-2 <?= $ra>=$rb?'border-green-400 bg-green-50':'' ?>"><b>A</b>: “<?= htmlspecialchars($abRunning['headline_a']) ?>”<br><?= $abRunning['views_a'] ?> views · <?= $abRunning['clicks_a'] ?> clicks · <b><?= $ra ?>%</b></div>
                        <div class="border rounded p-2 <?= $rb>$ra?'border-green-400 bg-green-50':'' ?>"><b>B</b>: “<?= htmlspecialchars($abRunning['headline_b']) ?>”<br><?= $abRunning['views_b'] ?> views · <?= $abRunning['clicks_b'] ?> clicks · <b><?= $rb ?>%</b></div>
                    </div>
                    <form method="POST" class="flex gap-2">
                        <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="ab_stop"><input type="hidden" name="test_id" value="<?= (int)$abRunning['id'] ?>">
                        <button name="winner" value="a" class="flex-1 bg-green-600 text-white py-1.5 rounded text-xs">Stop — apply A</button>
                        <button name="winner" value="b" class="flex-1 bg-green-600 text-white py-1.5 rounded text-xs">Stop — apply B</button>
                        <button name="winner" value="" class="bg-gray-400 text-white py-1.5 px-2 rounded text-xs">Stop</button>
                    </form>
                <?php else: ?>
                    <form method="POST" class="space-y-2">
                        <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="ab_start">
                        <input name="name" required placeholder="Test name" class="w-full px-2 py-1.5 border rounded">
                        <input name="headline_a" required placeholder="Headline A" class="w-full px-2 py-1.5 border rounded">
                        <input name="headline_b" required placeholder="Headline B" class="w-full px-2 py-1.5 border rounded">
                        <button class="w-full bg-purple-600 text-white py-2 rounded">Start 50/50 test</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Newsletter -->
        <form method="POST" class="bg-white rounded-lg shadow p-5 space-y-2 text-sm">
            <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="newsletter">
            <h2 class="font-semibold text-gray-800">Newsletter</h2>
            <select name="segment" class="w-full px-2 py-1.5 border rounded">
                <option value="loyal">Loyal customers (2+ orders)</option>
                <option value="recent">Recent buyers (90 days)</option>
                <option value="all">All customers</option>
            </select>
            <input name="subject" required placeholder="Subject" class="w-full px-2 py-1.5 border rounded">
            <textarea name="body_html" required rows="5" placeholder="HTML body — include your promo code…" class="w-full px-2 py-1.5 border rounded font-mono text-xs"></textarea>
            <button class="w-full bg-blue-600 text-white py-2 rounded" onclick="return confirm('Queue this newsletter?')">Queue newsletter</button>
            <?php foreach ($newsletters as $nl): ?><div class="text-xs text-gray-400">✉ “<?= htmlspecialchars($nl['subject']) ?>” → <?= $nl['segment'] ?> (<?= $nl['recipients'] ?>) <?= date('M j', strtotime($nl['sent_at'])) ?></div><?php endforeach; ?>
        </form>

        <!-- Tickets -->
        <div class="bg-white rounded-lg shadow p-5 text-sm">
            <h2 class="font-semibold text-gray-800 mb-2">Customer tickets (comments &amp; complaints)</h2>
            <form method="POST" class="grid grid-cols-2 gap-2 mb-3">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="ticket">
                <input name="customer_email" placeholder="Customer email" class="px-2 py-1.5 border rounded">
                <select name="kind" class="px-2 py-1.5 border rounded"><option>complaint</option><option>comment</option><option>question</option><option>return</option><option>other</option></select>
                <input name="subject" required placeholder="Subject" class="px-2 py-1.5 border rounded col-span-2">
                <input name="body" placeholder="Notes (what the customer said)" class="px-2 py-1.5 border rounded col-span-2">
                <button class="col-span-2 bg-gray-800 text-white py-1.5 rounded">Open ticket</button>
            </form>
            <?php foreach ($tickets as $t): ?>
            <div class="border-b py-2">
                <div class="flex justify-between items-center">
                    <span><b>#<?= (int)$t['id'] ?></b> <?= htmlspecialchars($t['subject']) ?>
                        <span class="px-1.5 py-0.5 rounded text-[10px] <?= $t['kind']==='complaint'?'bg-red-100 text-red-700':'bg-gray-100 text-gray-600' ?>"><?= $t['kind'] ?></span></span>
                    <span class="text-xs text-gray-400"><?= htmlspecialchars($t['customer_email'] ?? '') ?> · <?= (int)$t['replies'] ?> note(s)</span>
                </div>
                <form method="POST" class="flex gap-1 mt-1">
                    <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="ticket_reply"><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                    <input name="body" placeholder="Add note / resolution…" class="flex-1 px-2 py-1 border rounded text-xs">
                    <select name="status" class="border rounded text-xs px-1"><option value="">keep <?= $t['status'] ?></option><option value="pending">pending</option><option value="resolved">resolved</option><option value="closed">closed</option></select>
                    <button class="bg-blue-600 text-white text-xs px-2 rounded">Save</button>
                </form>
            </div>
            <?php endforeach; if (empty($tickets)) echo '<p class="text-gray-400 text-sm">No open tickets. ✅</p>'; ?>
        </div>
    </div>
</div>
</body>
</html>
