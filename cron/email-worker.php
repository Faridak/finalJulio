<?php
/**
 * Email queue worker — flushes queued/failed transactional emails.
 * Run from cron every minute:  * * * * * php /path/cron/email-worker.php
 * CLI-only. Real delivery requires MAIL_* configured in .env (SMTP + PHPMailer).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/EmailService.php';

$res = (new EmailService($pdo))->processQueue(100);
echo date('c') . " email-worker: processed {$res['processed']}, sent {$res['sent']}\n";
