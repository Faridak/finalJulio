<?php
/**
 * One-time (resumable) migration: push local media to object storage.
 *
 * Two sources, uploaded under the SAME key they already have on disk so the DB
 * paths keep working through asset_url() with no data changes:
 *
 *   1. Small real folders  — uploads/thumbs, uploads/legacy-imagenes,
 *      uploads/stores, uploads/avatars, uploads/images (walked recursively).
 *
 *   2. DB-referenced originals — every products.image_url / thumb_url that
 *      points at a local uploads/ path. This is how the uploads/legacy/*
 *      originals get uploaded: that folder is a SYMLINK to a 1.47M-file photo
 *      gallery, so we must NOT crawl it — we upload only the ~2,700 files the
 *      catalog actually references (resolved through the symlink).
 *
 * Safe to re-run: objects already in the bucket are skipped (HEAD), so an
 * interrupted run just resumes. Nothing local is ever deleted.
 *
 *   php scripts/migrate-images-to-object-storage.php            # everything
 *   php scripts/migrate-images-to-object-storage.php --force    # re-upload all
 *   php scripts/migrate-images-to-object-storage.php --dirs-only # skip DB pass
 *   php scripts/migrate-images-to-object-storage.php --db-only   # skip folders
 *
 * Requires STORAGE_DRIVER=s3 + S3_* in .env.
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/ObjectStorage.php';

$s3 = ObjectStorage::fromEnv();
if (!$s3) {
    fwrite(STDERR, "Object storage not configured. Set STORAGE_DRIVER=s3 and S3_* in .env.\n");
    exit(1);
}

$args    = array_slice($argv, 1);
$force   = in_array('--force', $args, true);
$dirsOnly = in_array('--dirs-only', $args, true);
$dbOnly  = in_array('--db-only', $args, true);

$root = realpath(__DIR__ . '/..');
$up = 0; $skip = 0; $fail = 0; $miss = 0; $bytes = 0;

$send = function (string $key, string $absPath) use ($s3, $force, &$up, &$skip, &$fail, &$bytes) {
    $key = str_replace('\\', '/', ltrim($key, '/'));
    if (!$force && $s3->exists($key)) { $skip++; return; }
    if ($s3->putFile($key, $absPath)) {
        $up++; $bytes += @filesize($absPath) ?: 0;
        if ($up % 200 === 0) echo "  ...uploaded $up (" . round($bytes / 1048576) . " MB)\n";
    } else {
        $fail++; fwrite(STDERR, "  FAIL: $key\n");
    }
};

// ---- 1. Small real folders (NEVER the legacy symlink) --------------------
if (!$dbOnly) {
    $dirs = ['uploads/thumbs', 'uploads/legacy-imagenes', 'uploads/stores', 'uploads/avatars', 'uploads/images'];
    foreach ($dirs as $rel) {
        $abs = realpath($root . '/' . $rel);
        if ($abs === false || !is_dir($abs)) continue;
        echo "== folder: $rel ==\n";
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            $name = $file->getFilename();
            if ($name === '.htaccess' || str_starts_with($name, '.')) continue;
            $key = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $send($key, $file->getPathname());
        }
    }
}

// ---- 2. DB-referenced images (covers uploads/legacy/* originals) ---------
if (!$dirsOnly) {
    echo "== DB-referenced product images ==\n";
    $seen = [];
    $sql = "SELECT image_url FROM products WHERE image_url LIKE 'uploads/%'
            UNION SELECT thumb_url FROM products WHERE thumb_url LIKE 'uploads/%'";
    foreach ($pdo->query($sql) as $r) {
        $key = $r['image_url'];
        if ($key === null || isset($seen[$key])) continue;
        $seen[$key] = true;
        $abs = $root . '/' . $key;            // is_file() follows the legacy symlink
        if (!is_file($abs)) { $miss++; continue; }
        $send($key, $abs);
    }
}

echo "\nDone: $up uploaded (" . round($bytes / 1048576, 1) . " MB), $skip already present, "
   . "$miss referenced-but-missing on disk, $fail failed.\n";
if ($miss) echo "Note: $miss product images have no local file (broken links) — expected for the partial legacy set.\n";
exit($fail === 0 ? 0 : 1);
