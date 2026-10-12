<?php
/**
 * Retry media uploads that failed to reach object storage (queued by
 * store_media_file() in includes/asset.php). Safe to run every few minutes;
 * a no-op when STORAGE_DRIVER isn't 's3' or the queue is empty.
 *
 *   php cron/media-retry-worker.php
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
require_once __DIR__ . '/../config/database.php';

$queue = media_retry_queue_path();
if (!is_file($queue) || filesize($queue) === 0) exit(0);

// Take the queue atomically so uploads failing right now append to a fresh file.
$work = $queue . '.working';
if (!@rename($queue, $work)) exit(0);

$lines = array_unique(array_filter(array_map('trim', file($work))));
$retry = []; $ok = 0; $gone = 0;
foreach ($lines as $line) {
    [$key, $abs] = array_pad(explode("\t", $line, 2), 2, '');
    if ($key === '' || !is_file($abs)) { $gone++; continue; }   // file deleted since: nothing to push
    if (store_media_file($key, $abs, false)) { $ok++; } else { $retry[] = $line; }
}
if ($retry) {
    file_put_contents($queue, implode("\n", $retry) . "\n", FILE_APPEND | LOCK_EX);
}
@unlink($work);
echo date('c') . " media-retry: $ok uploaded, " . count($retry) . " still failing, $gone missing locally\n";
exit($retry ? 1 : 0);
