<?php
/**
 * Make the media bucket's objects publicly READABLE via a bucket policy —
 * required on Linode E2/E3 endpoints, which reject per-object ACLs.
 * Grants s3:GetObject only (no listing, writing or deleting).
 *
 *   php scripts/set-bucket-policy.php          # show current policy
 *   php scripts/set-bucket-policy.php --apply  # set public-read policy
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../services/ObjectStorage.php';

// Setting a policy needs an UNLIMITED key; the app's Limited key can't. Put a
// temporary key in S3_ADMIN_KEY / S3_ADMIN_SECRET, run --apply, then delete
// those lines AND the key in Cloud Manager.
if (env('S3_ADMIN_KEY', '') !== '' && env('S3_ADMIN_SECRET', '') !== '') {
    $s3 = new ObjectStorage(env('S3_ENDPOINT', ''), env('S3_REGION', 'us-east-1'), env('S3_BUCKET', ''),
                            env('S3_ADMIN_KEY'), env('S3_ADMIN_SECRET'));
    echo "Using the temporary admin key (S3_ADMIN_KEY)\n";
} else {
    $s3 = ObjectStorage::fromEnv();
}
if (!$s3) exit("Object storage not configured (.env: STORAGE_DRIVER=s3 + S3_*)\n");
$code = fn(array $r) => preg_match('#<Code>([^<]+)</Code>#', $r['body'], $m) ? $m[1] : '';

if (in_array('--apply', $argv, true)) {
    $r = $s3->setPublicReadPolicy();
    echo "PUT policy → HTTP {$r['status']} " . $code($r) . "\n";
    if ($r['status'] >= 300) {
        echo $r['status'] === 403
            ? "Access denied: a Limited (bucket-only) key may not be allowed to set policies.\n"
              . "Create a temporary UNLIMITED key in Cloud Manager, run this once with it, then delete that key.\n"
            : "Failed — see the error code above.\n";
        exit(1);
    }
}
$g = $s3->getBucketPolicy();
echo "Current policy → HTTP {$g['status']} " . ($g['status'] === 200 ? $g['body'] : $code($g)) . "\n";
