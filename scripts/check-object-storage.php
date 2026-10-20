<?php
/**
 * Object storage self-test: upload a tiny public object, fetch it anonymously
 * through the public URL(s), then delete it. Prints no credentials.
 *
 *   php scripts/check-object-storage.php
 *
 * Needs STORAGE_DRIVER=s3 + S3_* in .env. Exit 0 = ready for the image migration.
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../services/ObjectStorage.php';

$ok = true;
$say = function ($pass, $msg) use (&$ok) { echo ($pass ? '  ✔ ' : '  ✘ ') . $msg . "\n"; if (!$pass) $ok = false; };

$s3 = ObjectStorage::fromEnv();
if (!$s3) {
    echo "  ✘ not configured: need STORAGE_DRIVER=s3, S3_ENDPOINT, S3_BUCKET, S3_KEY, S3_SECRET in .env\n";
    exit(1);
}
$endpoint = rtrim(env('S3_ENDPOINT', ''), '/');
$bucket   = env('S3_BUCKET', '');
$host     = parse_url($endpoint, PHP_URL_HOST);
echo "Endpoint $endpoint · bucket $bucket · region " . env('S3_REGION', '(default)') . "\n";

$key  = 'healthcheck/' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.txt';
$body = 'ventdepot storage check ' . gmdate('c');

$say($s3->put($key, $body, 'text/plain'), "signed upload (PUT $key)");
$say($s3->exists($key), 'signed HEAD finds the object');

$fetch = function ($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false]);
    $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$c, $b];
};
// Browsers will load images from ASSET_BASE_URL (virtual-hosted style).
$vhost = "https://$bucket.$host/$key";
[$c1, $b1] = $fetch($vhost);
$say($c1 === 200 && $b1 === $body, "anonymous GET, virtual-hosted URL → HTTP $c1  ($vhost)");
[$c2, $b2] = $fetch("$endpoint/$bucket/$key");
$say($c2 === 200 && $b2 === $body, "anonymous GET, path-style URL → HTTP $c2");

$base = rtrim(env('ASSET_BASE_URL', ''), '/');
if ($base !== '') {
    [$c3] = $fetch("$base/$key");
    $say($c3 === 200, "anonymous GET via ASSET_BASE_URL → HTTP $c3");
} else {
    echo "  · ASSET_BASE_URL empty (fine until the migration is done); expected value: https://$bucket.$host\n";
}

$say($s3->delete($key), 'cleanup (DELETE)');
echo $ok ? "READY — run scripts/migrate-images-to-object-storage.php\n" : "NOT READY — see ✘ above\n";
exit($ok ? 0 : 1);
