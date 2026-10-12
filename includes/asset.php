<?php
/**
 * asset_url() — resolve a stored media path to a servable URL.
 *
 * Media paths are stored in the DB as relative keys (e.g. "uploads/thumbs/42.jpg").
 * Those double as the object-storage key AND the local disk path, so nothing in
 * the database changes when we move to object storage — only how they're served.
 *
 *   - If ASSET_BASE_URL is set (object storage / CDN origin), prepend it so the
 *     browser fetches straight from the storage edge (fast, off the app server).
 *   - If it's empty (local dev, or storage not yet configured), return the path
 *     unchanged so Apache serves it from disk exactly as before.
 *   - Absolute URLs (http/https) and data: URIs are returned untouched.
 *
 * This makes the cutover a single env flip with zero code churn per template.
 */
/**
 * Write-through a freshly-saved local upload to object storage.
 *
 * Upload handlers keep their existing move_uploaded_file()/imagejpeg() call
 * (so local dev is unchanged), then call this once with the same relative key
 * and absolute path. When object storage is configured the file is pushed to
 * the bucket under the identical key so asset_url() serves it from the edge.
 * A no-op when storage is 'local'. Never throws — a storage hiccup must not
 * break the user's upload. A failed push is queued and retried by
 * cron/media-retry-worker.php: once ASSET_BASE_URL points at the bucket,
 * browsers fetch from there directly, so a missing object would otherwise be
 * a permanently broken image.
 *
 *   store_media_file('uploads/stores/7/logo-123.jpg', "$dir/$name");
 */
function store_media_file(string $relKey, string $absPath, bool $queueOnFailure = true): bool
{
    static $s3 = null, $loaded = false;
    if (!$loaded) {
        require_once __DIR__ . '/../services/ObjectStorage.php';
        try { $s3 = ObjectStorage::fromEnv(); } catch (\Throwable $e) { $s3 = null; }
        $loaded = true;
    }
    if ($s3 === null) return false;
    $key = str_replace('\\', '/', ltrim($relKey, '/'));
    try {
        $ok = $s3->putFile($key, $absPath);
        $why = $ok ? '' : 'non-2xx response or unreadable file';
    } catch (\Throwable $e) {
        $ok = false;
        $why = $e->getMessage();
    }
    if (!$ok) {
        error_log("store_media_file failed for $key: $why");
        if ($queueOnFailure) {
            @file_put_contents(media_retry_queue_path(), $key . "\t" . $absPath . "\n", FILE_APPEND | LOCK_EX);
        }
    }
    return $ok;
}

/** Uploads that failed to reach object storage, one "key<TAB>abs path" per line. */
function media_retry_queue_path(): string
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/media-retry.queue';
}

function asset_url(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') return '';

    // Already absolute (external image, or a path already rewritten): leave it.
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
        return $path;
    }

    // Only user-uploaded media moves to object storage. App static assets
    // (assets/, images/, css/…) ship with the code and stay app-served.
    $rel = ltrim($path, '/');
    if (!str_starts_with($rel, 'uploads/')) {
        return $path;
    }

    $base = function_exists('env') ? (string) env('ASSET_BASE_URL', '') : (string) ($_ENV['ASSET_BASE_URL'] ?? '');
    if ($base === '') {
        return $path; // serve from local disk (unchanged behaviour)
    }
    return rtrim($base, '/') . '/' . $rel;
}
