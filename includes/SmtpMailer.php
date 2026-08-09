<?php
/**
 * Minimal dependency-free SMTP client (SSL :465 or STARTTLS :587, AUTH LOGIN).
 * Used by EmailService when MAIL_* is configured and PHPMailer is not installed.
 */
class SmtpMailer
{
    private $host; private $port; private $user; private $pass; private $enc;
    private $sock; private $log = [];

    public function __construct($host, $port, $user, $pass, $encryption = 'tls')
    {
        $this->host = $host; $this->port = (int) $port;
        $this->user = $user; $this->pass = $pass;
        $this->enc = strtolower((string) $encryption); // 'ssl' | 'tls' (STARTTLS) | ''
    }

    /** @throws Exception on any SMTP failure */
    public function send($fromEmail, $fromName, $toEmail, $toName, $subject, $html)
    {
        $remote = ($this->enc === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $this->sock = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->sock) throw new Exception("SMTP connect failed: $errstr ($errno)");
        stream_set_timeout($this->sock, 15);

        try {
            $this->expect(220);
            $this->cmd('EHLO ' . gethostname(), 250);

            if ($this->enc === 'tls') {
                $this->cmd('STARTTLS', 220);
                if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('STARTTLS negotiation failed');
                }
                $this->cmd('EHLO ' . gethostname(), 250);
            }

            if ($this->user !== '') {
                $this->cmd('AUTH LOGIN', 334);
                $this->cmd(base64_encode($this->user), 334);
                $this->cmd(base64_encode($this->pass), 235);
            }

            $this->cmd('MAIL FROM:<' . $fromEmail . '>', 250);
            $this->cmd('RCPT TO:<' . $toEmail . '>', 250);
            $this->cmd('DATA', 354);

            $headers = 'From: ' . $this->encodeName($fromName) . ' <' . $fromEmail . ">\r\n"
                     . 'To: ' . $this->encodeName($toName ?: $toEmail) . ' <' . $toEmail . ">\r\n"
                     . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
                     . 'Date: ' . date('r') . "\r\n"
                     . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $this->host . ">\r\n"
                     . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
            $body = chunk_split(base64_encode($html));
            // dot-stuffing per RFC 5321
            $payload = preg_replace('/^\./m', '..', $headers . "\r\n" . $body);
            $this->cmd($payload . "\r\n.", 250, true);
            $this->cmd('QUIT', 221);
            return true;
        } finally {
            if (is_resource($this->sock)) fclose($this->sock);
        }
    }

    private function encodeName($name)
    {
        return preg_match('/[^\x20-\x7e]/', (string) $name) ? '=?UTF-8?B?' . base64_encode($name) . '?=' : $name;
    }

    private function cmd($line, $expectCode, $raw = false)
    {
        fwrite($this->sock, $line . "\r\n");
        $this->log[] = '> ' . ($raw ? '[data]' : substr($line, 0, 60));
        return $this->expect($expectCode);
    }

    private function expect($code)
    {
        $resp = '';
        while (($line = fgets($this->sock, 515)) !== false) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // last line of multi-line reply
        }
        $this->log[] = '< ' . trim(substr($resp, 0, 80));
        if ((int) substr($resp, 0, 3) !== $code) {
            throw new Exception('SMTP expected ' . $code . ' got: ' . trim($resp));
        }
        return $resp;
    }
}
