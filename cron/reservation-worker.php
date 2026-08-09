<?php
/**
 * Reservation expiry worker — restores stock from abandoned (unpaid) orders and
 * cancels them. Run from cron every few minutes:
 *   *\/5 * * * * php /path/cron/reservation-worker.php
 * CLI-only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/ReservationService.php';

$res = (new ReservationService($pdo))->expireStale(500);
echo date('c') . " reservation-worker: expired {$res['expired']} hold(s)\n";

// Also expire overdue OXXO cash vouchers (their reservations release above).
require_once __DIR__ . '/../services/OxxoPaymentService.php';
$expired = (new OxxoPaymentService($pdo))->expireOverdue();
if ($expired) echo date('c') . " oxxo: expired $expired voucher(s)\n";
