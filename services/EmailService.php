<?php
/**
 * EmailService — transactional email with a durable queue/log.
 *
 * Every message is logged to `email_queue` (audit + retry). Delivery is attempted
 * immediately: via PHPMailer+SMTP if installed & configured (MAIL_* in .env),
 * otherwise via PHP mail(). If neither is available (typical in local dev), the
 * row stays 'queued' and a worker (processQueue) can retry once SMTP is set up.
 *
 * So the flow always works; real sending switches on when MAIL_* is configured.
 */
class EmailService
{
    private $pdo;
    private $from;
    private $fromName;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->from = function_exists('env') ? env('MAIL_FROM_ADDRESS', 'noreply@ventdepot.com') : 'noreply@ventdepot.com';
        $this->fromName = function_exists('env') ? env('MAIL_FROM_NAME', 'VentDepot') : 'VentDepot';
    }

    /** Queue + attempt to send an email. Returns the queue row id. */
    public function queue($toEmail, $toName, $subject, $html, $template = null, $refType = null, $refId = null)
    {
        $this->pdo->prepare("
            INSERT INTO email_queue (to_email, to_name, subject, body_html, template, reference_type, reference_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'queued', NOW())
        ")->execute([$toEmail, $toName, $subject, $html, $template, $refType, $refId]);
        $id = (int) $this->pdo->lastInsertId();
        $this->attempt($id, $toEmail, $toName, $subject, $html);
        return $id;
    }

    /** Retry queued/failed messages (call from a cron/worker). */
    public function processQueue($limit = 50)
    {
        $rows = $this->pdo->query("SELECT * FROM email_queue WHERE status IN ('queued','failed') AND attempts < 5 ORDER BY id LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
        $sent = 0;
        foreach ($rows as $r) {
            if ($this->attempt((int) $r['id'], $r['to_email'], $r['to_name'], $r['subject'], $r['body_html'])) $sent++;
        }
        return ['processed' => count($rows), 'sent' => $sent];
    }

    private function attempt($id, $to, $name, $subject, $html)
    {
        try {
            $ok = $this->deliver($to, $name, $subject, $html);
            if ($ok) {
                $this->pdo->prepare("UPDATE email_queue SET status='sent', sent_at=NOW(), attempts=attempts+1 WHERE id=?")->execute([$id]);
                return true;
            }
            $this->pdo->prepare("UPDATE email_queue SET status='queued', attempts=attempts+1, error='No transport / not configured' WHERE id=?")->execute([$id]);
        } catch (Exception $e) {
            $this->pdo->prepare("UPDATE email_queue SET status='failed', attempts=attempts+1, error=? WHERE id=?")->execute([substr($e->getMessage(), 0, 500), $id]);
        }
        return false;
    }

    /** Try PHPMailer+SMTP, then PHP mail(); return false if neither is usable. */
    private function deliver($to, $name, $subject, $html)
    {
        $host = function_exists('env') ? env('MAIL_HOST', '') : '';

        // PHPMailer path (if the library is installed and SMTP is configured)
        if ($host && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $m = new \PHPMailer\PHPMailer\PHPMailer(true);
            $m->isSMTP();
            $m->Host = $host;
            $m->Port = (int) env('MAIL_PORT', 587);
            $m->SMTPAuth = true;
            $m->Username = env('MAIL_USERNAME', '');
            $m->Password = env('MAIL_PASSWORD', '');
            $enc = env('MAIL_ENCRYPTION', 'tls');
            if ($enc) $m->SMTPSecure = $enc;
            $m->setFrom($this->from, $this->fromName);
            $m->addAddress($to, $name ?: $to);
            $m->isHTML(true);
            $m->Subject = $subject;
            $m->Body = $html;
            $m->AltBody = trim(strip_tags($html));
            return $m->send();
        }

        // Built-in SMTP transport (no dependencies) when MAIL_* is configured
        // with real credentials (placeholder values from .env.example are ignored).
        $smtpUser = env('MAIL_USERNAME', '');
        if ($host && $smtpUser !== '' && strpos($smtpUser, 'your_') !== 0) {
            require_once __DIR__ . '/../includes/SmtpMailer.php';
            $smtp = new SmtpMailer($host, env('MAIL_PORT', 587), $smtpUser, env('MAIL_PASSWORD', ''), env('MAIL_ENCRYPTION', 'tls'));
            return $smtp->send($this->from, $this->fromName, $to, $name, $subject, $html);
        }

        // PHP mail() fallback (needs a local MTA; often unavailable in dev)
        if (function_exists('mail') && ini_get('SMTP') || stripos(PHP_OS, 'WIN') === false) {
            $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
            $headers .= 'From: ' . $this->fromName . ' <' . $this->from . ">\r\n";
            return @mail($to, $subject, $html, $headers);
        }
        return false; // no transport available -> stays queued
    }

    // ---- templates --------------------------------------------------------

    public function orderConfirmation(array $order, array $items, $toEmail, $toName)
    {
        $rows = '';
        foreach ($items as $it) {
            $rows .= '<tr><td style="padding:6px 0">' . htmlspecialchars($it['name'] ?? ('Product #' . $it['product_id']))
                   . ' × ' . (int) $it['quantity'] . '</td><td style="text-align:right">$'
                   . number_format((float) $it['total'], 2) . '</td></tr>';
        }
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto">'
            . '<h2 style="color:#2563eb">Thank you for your order!</h2>'
            . '<p>Hi ' . htmlspecialchars($toName ?: 'there') . ', your order <b>#' . (int) $order['id'] . '</b> is confirmed.</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:12px 0">' . $rows
            . '<tr><td style="border-top:1px solid #ddd;padding-top:8px">Shipping</td><td style="text-align:right;border-top:1px solid #ddd;padding-top:8px">$' . number_format((float) ($order['shipping_cost'] ?? 0), 2) . '</td></tr>'
            . '<tr><td>Tax</td><td style="text-align:right">$' . number_format((float) ($order['tax'] ?? 0), 2) . '</td></tr>'
            . '<tr><td style="font-weight:bold">Total</td><td style="text-align:right;font-weight:bold">$' . number_format((float) ($order['total'] ?? 0), 2) . '</td></tr>'
            . '</table>'
            . '<p>You can track your order any time from your <a href="' . (function_exists('env') ? env('APP_URL', '') : '') . '/orders.php">order history</a>.</p>'
            . '<p style="color:#888;font-size:12px">VentDepot — ventilation &amp; electrical construction supply.</p></div>';

        return $this->queue($toEmail, $toName, 'Order #' . (int) $order['id'] . ' confirmed — VentDepot', $html, 'order_confirmation', 'order', (int) $order['id']);
    }
}
