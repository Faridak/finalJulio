<?php
/**
 * ObjectStorage — minimal, dependency-free client for S3-compatible storage
 * (Linode Object Storage). Signs requests with AWS Signature V4 using only
 * curl + hash(), so it adds ZERO composer dependencies (keeps our Dependabot
 * surface clean).
 *
 * Why this exists: user-uploaded images must NOT live on the app server.
 *   - Security: an uploaded file can never be executed as code on the web host.
 *   - Resilience: images survive instance rebuilds / a second app node.
 *   - Speed: objects are served directly from the storage edge, not through PHP.
 *
 * Config comes from .env (never hard-coded):
 *   STORAGE_DRIVER=s3            # 's3' to use object storage, 'local' for disk
 *   S3_ENDPOINT=https://us-ord-10.linodeobjects.com
 *   S3_REGION=us-ord
 *   S3_BUCKET=ventdepot-media
 *   S3_KEY=...                   # access key
 *   S3_SECRET=...                # secret key
 *   ASSET_BASE_URL=https://ventdepot-media.us-ord-10.linodeobjects.com
 *
 * Usage:
 *   $s = ObjectStorage::fromEnv();
 *   $s->put('uploads/thumbs/42.jpg', $bytes, 'image/jpeg');
 *   $s->putFile('uploads/legacy/x.jpg', '/abs/path/x.jpg');
 *   $s->exists('uploads/thumbs/42.jpg');
 *   $s->delete('uploads/thumbs/42.jpg');
 */
class ObjectStorage
{
    private string $endpoint;   // host origin, no trailing slash
    private string $host;
    private string $region;
    private string $bucket;
    private string $key;
    private string $secret;
    private string $service = 's3';
    private string $objectAcl;  // '' = rely on the bucket policy (E2/E3 endpoints reject per-object ACLs)

    public function __construct(string $endpoint, string $region, string $bucket, string $key, string $secret, string $objectAcl = '')
    {
        $this->endpoint = rtrim($endpoint, '/');
        $this->host     = parse_url($this->endpoint, PHP_URL_HOST) ?: $this->endpoint;
        $this->region   = $region;
        $this->bucket   = $bucket;
        $this->key      = $key;
        $this->secret   = $secret;
        $this->objectAcl = $objectAcl;
    }

    /** Build from .env; returns null when object storage is not configured. */
    public static function fromEnv(): ?self
    {
        if (function_exists('env')) {
            $g = fn($k, $d = '') => env($k, $d);
        } else {
            $g = fn($k, $d = '') => $_ENV[$k] ?? getenv($k) ?: $d;
        }
        if (strtolower((string) $g('STORAGE_DRIVER', 'local')) !== 's3') return null;
        $endpoint = (string) $g('S3_ENDPOINT');
        $bucket   = (string) $g('S3_BUCKET');
        $key      = (string) $g('S3_KEY');
        $secret   = (string) $g('S3_SECRET');
        if ($endpoint === '' || $bucket === '' || $key === '' || $secret === '') return null;
        $region = (string) $g('S3_REGION') ?: 'us-east-1';
        // Public access comes from the bucket policy (scripts/set-bucket-policy.php).
        // Set S3_OBJECT_ACL=public-read only for legacy (E0/E1) endpoints that support ACLs.
        return new self($endpoint, $region, $bucket, $key, $secret, (string) $g('S3_OBJECT_ACL', ''));
    }

    public static function isEnabled(): bool
    {
        return self::fromEnv() !== null;
    }

    /** Upload raw bytes with a long cache lifetime (image keys never change content). */
    public function put(string $objectKey, string $body, string $contentType = 'application/octet-stream'): bool
    {
        $headers = [
            'Content-Type'  => $contentType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ];
        if ($this->objectAcl !== '') {
            $headers['x-amz-acl'] = $this->objectAcl;   // E2/E3 answer 501 NotImplemented to this
        }
        $res = $this->request('PUT', $objectKey, $body, $headers);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    /**
     * Bucket policy that lets anyone READ (GET) objects — how product images are
     * made public on E2/E3 endpoints. Never grants list, write or delete.
     */
    public function setPublicReadPolicy(): array
    {
        $policy = json_encode([
            'Version'   => '2012-10-17',
            'Statement' => [[
                'Sid'       => 'PublicReadObjects',
                'Effect'    => 'Allow',
                'Principal' => '*',
                'Action'    => ['s3:GetObject'],
                'Resource'  => ["arn:aws:s3:::{$this->bucket}/*"],
            ]],
        ], JSON_UNESCAPED_SLASHES);
        return $this->request('PUT', '', $policy, ['Content-Type' => 'application/json'], 'policy');
    }

    /** Current bucket policy JSON (status 404 = none set). */
    public function getBucketPolicy(): array
    {
        return $this->request('GET', '', '', [], 'policy');
    }

    /** Upload a file from disk (streams into memory once; images are small). */
    public function putFile(string $objectKey, string $absPath, ?string $contentType = null): bool
    {
        $body = @file_get_contents($absPath);
        if ($body === false) return false;
        $contentType ??= self::guessType($absPath);
        return $this->put($objectKey, $body, $contentType);
    }

    public function exists(string $objectKey): bool
    {
        return $this->request('HEAD', $objectKey)['status'] === 200;
    }

    public function delete(string $objectKey): bool
    {
        $s = $this->request('DELETE', $objectKey)['status'];
        return $s === 204 || $s === 200 || $s === 404; // already-gone is success
    }

    /** Public URL for an object (used by asset_url()). */
    public function url(string $objectKey): string
    {
        return $this->endpoint . '/' . $this->bucket . '/' . ltrim($objectKey, '/');
    }

    public static function guessType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            'avif'        => 'image/avif',
            'pdf'         => 'application/pdf',
            default       => 'application/octet-stream',
        };
    }

    // ---- AWS Signature V4 (path-style: endpoint/bucket/key) ----------------

    /** $subresource: bucket-level sub-resource with no value, e.g. 'policy' (GET/PUT /bucket?policy). */
    private function request(string $method, string $objectKey, string $body = '', array $headers = [], string $subresource = ''): array
    {
        $objectKey = ltrim($objectKey, '/');
        $canonicalUri = $objectKey === ''
            ? '/' . $this->bucket
            : '/' . $this->bucket . '/' . $this->uriEncode($objectKey, false);
        $canonicalQuery = $subresource === '' ? '' : rawurlencode($subresource) . '=';

        $now      = gmdate('Ymd\THis\Z');
        $date     = substr($now, 0, 8);
        $payloadHash = hash('sha256', $body);

        $headers = array_change_key_case($headers, CASE_LOWER);
        $headers['host']                 = $this->host;
        $headers['x-amz-date']           = $now;
        $headers['x-amz-content-sha256'] = $payloadHash;
        ksort($headers);

        $canonicalHeaders = '';
        $signedHeaders    = [];
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= $k . ':' . trim((string) $v) . "\n";
            $signedHeaders[]   = $k;
        }
        $signedHeaders = implode(';', $signedHeaders);

        $canonicalRequest = implode("\n", [
            $method, $canonicalUri, $canonicalQuery, $canonicalHeaders, $signedHeaders, $payloadHash,
        ]);

        $scope     = "$date/{$this->region}/{$this->service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256', $now, $scope, hash('sha256', $canonicalRequest),
        ]);

        $kDate    = hash_hmac('sha256', $date, 'AWS4' . $this->secret, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $this->service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->key}/$scope, "
            . "SignedHeaders=$signedHeaders, Signature=$signature";

        $curlHeaders = ["Authorization: $authorization"];
        foreach ($headers as $k => $v) $curlHeaders[] = "$k: $v";

        $ch = curl_init($this->endpoint . $canonicalUri . ($subresource === '' ? '' : '?' . rawurlencode($subresource)));
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => $method === 'HEAD',
            CURLOPT_CONNECTTIMEOUT => 5,    // short: runs inside upload requests; failures are queued for retry
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($method !== 'HEAD') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return ['status' => 0, 'body' => '', 'error' => $err];
        }
        return ['status' => (int) $status, 'body' => is_string($resp) ? $resp : '', 'error' => $err];
    }

    /** RFC 3986 encode; keep '/' when encoding an object path. */
    private function uriEncode(string $s, bool $encodeSlash = true): string
    {
        $out = '';
        foreach (str_split($s) as $c) {
            if (ctype_alnum($c) || $c === '-' || $c === '_' || $c === '.' || $c === '~') {
                $out .= $c;
            } elseif ($c === '/') {
                $out .= $encodeSlash ? '%2F' : '/';
            } else {
                $out .= '%' . strtoupper(bin2hex($c));
            }
        }
        return $out;
    }
}
