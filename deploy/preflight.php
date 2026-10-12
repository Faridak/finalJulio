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
$noTfa = $pdo->query("SELECT COUNT(*) FROM users u LEFT JOIN user_2fa t ON t.user_id = u.id AND t.is_enabled = 1
                      WHERE u.role IN ('admin','staff') AND t.user_id IS NULL")->fetchColumn();
$noTfa == 0 ? pass('all staff/admin accounts use 2FA')
            : warning("$noTfa staff/admin account(s) without 2FA — enable it in profile → Two-Factor Authentication");

$prod = $env('APP_ENV') === 'production';
// Old template keys were never read by config/database.php (app fell back to root/no password)
if ($env('DB_PASS') !== '' && $env('DB_PASSWORD') === '') {
    fail('.env uses DB_NAME/DB_USER/DB_PASS — rename to DB_DATABASE/DB_USERNAME/DB_PASSWORD');
}
// Public REST API: forged tokens if enabled with a weak/default secret
if (filter_var($env('API_ENABLED'), FILTER_VALIDATE_BOOLEAN)) {
    strlen($env('JWT_SECRET')) >= 32 ? pass('REST API on, JWT_SECRET set')
        : fail('API_ENABLED=true but JWT_SECRET is missing/short (need 32+ chars)');
} else {
    pass('public REST API disabled (API_ENABLED=false)');
}
// Object storage cut-over order
if ($env('ASSET_BASE_URL') !== '' && strtolower($env('STORAGE_DRIVER')) !== 's3') {
    fail('ASSET_BASE_URL is set but STORAGE_DRIVER is not s3 — new uploads would 404');
}
$q = __DIR__ . '/../logs/media-retry.queue';
(is_file($q) && filesize($q) > 0)
    ? warning(count(file($q)) . ' media upload(s) waiting to reach object storage (cron/media-retry-worker.php)')
    : pass('no pending media uploads');
// PHP runtime hardening (01-provision-server.sh installs 99-ventdepot.ini for CLI + Apache)
if ($prod) {
    ini_get('display_errors') && strtolower(ini_get('display_errors')) !== 'off'
        ? fail('display_errors is ON — errors (paths, SQL) would be shown to visitors') : pass('display_errors off');
    (ini_get('session.cookie_httponly') && ini_get('session.cookie_secure') && ini_get('session.use_strict_mode'))
        ? pass('session cookies httponly + secure + strict')
        : warning('session cookie hardening missing — install deploy 99-ventdepot.ini');
}
// Backups actually landing (the old crontab had no password and wrote nowhere)
if (is_dir('/backups')) {
    $latest = glob('/backups/ventdepot-*.sql.gz') ?: [];
    usort($latest, fn($a, $b) => filemtime($b) <=> filemtime($a));
    if (!$latest) {
        warning('no nightly backup in /backups yet (first one runs at 03:00)');
    } else {
        $age = (time() - filemtime($latest[0])) / 3600;
        ($age < 26 && filesize($latest[0]) > 10240)
            ? pass('latest backup ' . basename($latest[0]) . ' (' . round(filesize($latest[0]) / 1048576, 1) . ' MB)')
            : fail('latest backup is ' . round($age) . 'h old or empty — check /var/log/ventdepot/backup.log');
    }
}
// Live HTTP check: sensitive paths must not be served
if (!$isPlaceholder($env('APP_URL')) && function_exists('curl_init')) {
    foreach (['.env', 'deploy.sh', 'migrate.php', 'config/database.php', 'node_modules/', 'composer.json', 'logs/media-retry.queue'] as $p) {
        $ch = curl_init(rtrim($env('APP_URL'), '/') . '/' . $p);
        curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 0) { warning("could not reach " . $env('APP_URL') . " for HTTP checks"); break; }
        in_array($code, [403, 404], true) ? pass("/$p not served ($code)") : fail("/$p is publicly reachable (HTTP $code)");
    }
}

echo "== Writable paths ==\n";
foreach (['uploads', 'uploads/thumbs', 'uploads/stores', 'uploads/avatars'] as $d) {
    $p = __DIR__ . '/../' . $d;
    if (!is_dir($p)) { @mkdir($p, 0775, true); }
    is_writable($p) ? pass("$d writable") : fail("$d NOT writable — uploads will fail");
}

echo "\n=== " . ($bad === 0 ? "READY TO DEPLOY" : "BLOCKERS FOUND") . " — $ok ok, $warn warning(s), $bad blocker(s) ===\n";
if ($warn) echo "Warnings are acceptable for a staging run; resolve them before taking real orders.\n";
exit($bad === 0 ? 0 : 1);
