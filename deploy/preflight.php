<?php
/**
 * Deployment preflight — run LOCALLY before deploying, and again ON THE SERVER
 * after deploying. Verifies environment, config, data and code readiness.
 *
 *   php deploy/preflight.php
 *
 * Exit 0 = ready; 1 = blockers found.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../config/database.php';

$ok = 0; $warn = 0; $bad = 0;
function pass($m) { global $ok; $ok++; echo "  ✔ $m\n"; }
function warning($m) { global $warn; $warn++; echo "  ⚠ $m\n"; }
function fail($m) { global $bad; $bad++; echo "  ✘ $m\n"; }
$env = fn($k) => $_ENV[$k] ?? '';
$isPlaceholder = fn($v) => $v === '' || str_starts_with($v, 'your_') || str_starts_with($v, 'CHANGE_ME');

echo "== Environment ==\n";
version_compare(PHP_VERSION, '8.0', '>=') ? pass('PHP ' . PHP_VERSION) : fail('PHP ' . PHP_VERSION . ' — need 8.0+');
foreach (['pdo_mysql' => 1, 'gd' => 1, 'curl' => 1, 'mbstring' => 1, 'fileinfo' => 1, 'openssl' => 1] as $ext => $_) {
    extension_loaded($ext) ? pass("ext $ext") : fail("missing PHP extension: $ext");
}
(int) ini_get('memory_limit') >= 256 || ini_get('memory_limit') == -1
    ? pass('memory_limit ' . ini_get('memory_limit')) : warning('memory_limit ' . ini_get('memory_limit') . ' — 256M+ recommended');

echo "== Clocks (a mismatch silently breaks discounts) ==\n";
$dbNow = strtotime($pdo->query('SELECT NOW()')->fetchColumn());
abs($dbNow - time()) <= 120 ? pass('PHP and MySQL clocks agree')
    : warning('PHP/MySQL clocks differ by ' . round(abs($dbNow - time()) / 60) . ' min — set the same timezone');

echo "== Configuration (.env) ==\n";
$env('APP_ENV') === 'production' ? pass('APP_ENV=production (mock payment disabled)')
    : warning("APP_ENV='" . $env('APP_ENV') . "' — set to 'production' on the server");
$isPlaceholder($env('APP_URL')) ? warning('APP_URL not set — email links will be wrong') : pass('APP_URL ' . $env('APP_URL'));

$payLive = !$isPlaceholder($env('PAYPAL_US_CLIENT_ID')) || !$isPlaceholder($env('PAYPAL_MX_CLIENT_ID'));
$payLive ? pass('PayPal credentials present (' . $env('PAYPAL_MODE') . ')')
         : warning('PayPal keys missing — checkout runs in demo mode');
$isPlaceholder($env('MAIL_USERNAME')) ? warning('SMTP not configured — email stays queued, never sends')
                                      : pass('SMTP configured (' . $env('MAIL_HOST') . ')');
$isPlaceholder($env('GOOGLE_MAPS_API_KEY')) ? warning('No Google Maps key — shipping uses per-country estimates')
                                            : pass('Google Maps key present');
$isPlaceholder($env('OXXO_WEBHOOK_SECRET')) ? warning('OXXO webhook secret unset — confirm cash manually in admin')
                                            : pass('OXXO webhook secret set');

echo "== Database ==\n";
$tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
$tables >= 110 ? pass("$tables tables") : fail("only $tables tables — schema incomplete");
$pending = 0;
try {
    $applied = $pdo->query("SELECT COUNT(*) FROM schema_migrations")->fetchColumn();
    $files = count(array_filter(glob(__DIR__ . '/../migration/[0-9][0-9][0-9]_*.sql'), fn($f) => basename($f) !== '000_full_schema.sql'));
    $pending = max(0, $files - $applied);
    $pending === 0 ? pass("migrations up to date ($applied applied)") : fail("$pending pending migration(s) — run php migrate.php");
} catch (PDOException $e) { fail('schema_migrations missing — run php migrate.php --baseline'); }

$products = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$products > 100 ? pass("$products products") : fail("only $products products — catalog not loaded");
$withImg = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE thumb_url IS NOT NULL AND thumb_url <> ''")->fetchColumn();
$withImg > 0 ? pass("$withImg products with thumbnails") : warning('no thumbnails — run scripts/generate-thumbnails.php');

$ledger = (float) $pdo->query("SELECT COALESCE(ROUND(SUM(debit_amount)-SUM(credit_amount),2),0) FROM general_ledger")->fetchColumn();
abs($ledger) < 0.01 ? pass('ledger balanced') : fail("ledger off by $ledger");

$accounts = (int) $pdo->query("SELECT COUNT(*) FROM chart_of_accounts")->fetchColumn();
$accounts >= 15 ? pass("$accounts ledger accounts") : fail("only $accounts accounts — run scripts/recover-database.php");
$rules = (int) $pdo->query("SELECT COUNT(*) FROM shipping_rate_rules WHERE is_active=1")->fetchColumn();
$rules > 0 ? pass("$rules active shipping rate rule(s)") : fail('no shipping tariff — checkout will use fallback pricing');

echo "== Security ==\n";
$weak = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','staff')")->fetchColumn();
warning("$weak staff/admin account(s) — confirm every password was changed from the seeded default");
is_file(__DIR__ . '/../.htaccess') ? pass('.htaccess present') : fail('.htaccess missing — sensitive files would be public');
foreach (['migration', 'scripts', 'tests', 'cron', 'backups'] as $d) {
    is_file(__DIR__ . "/../$d/.htaccess") ? pass("$d/ blocked from web") : warning("$d/ has no .htaccess guard");
}
is_file(__DIR__ . '/../uploads/.htaccess') ? pass('uploads/ script execution blocked') : fail('uploads/.htaccess missing');

echo "== Writable paths ==\n";
foreach (['uploads', 'uploads/thumbs', 'uploads/stores', 'uploads/avatars'] as $d) {
    $p = __DIR__ . '/../' . $d;
    if (!is_dir($p)) { @mkdir($p, 0775, true); }
    is_writable($p) ? pass("$d writable") : fail("$d NOT writable — uploads will fail");
}

echo "\n=== " . ($bad === 0 ? "READY TO DEPLOY" : "BLOCKERS FOUND") . " — $ok ok, $warn warning(s), $bad blocker(s) ===\n";
if ($warn) echo "Warnings are acceptable for a staging run; resolve them before taking real orders.\n";
exit($bad === 0 ? 0 : 1);
