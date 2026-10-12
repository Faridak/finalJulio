<?php
/**
 * Lightweight .env loader (no external dependencies).
 * Populates $_ENV / $_SERVER / getenv() from the project .env file once per request.
 */
if (!function_exists('loadEnv')) {
    function loadEnv($path = null) {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $path = $path ?: __DIR__ . '/../.env';
        if (!is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                // Quoted: take what's inside the quotes; anything after the
                // closing quote (e.g. a "# comment") is ignored.
                $end = strpos($value, $value[0], 1);
                $value = $end === false ? substr($value, 1) : substr($value, 1, $end - 1);
            } else {
                // Unquoted: " # ..." starts a comment (dotenv convention). Without
                // this, `OXXO_WEBHOOK_SECRET=   # shared secret` made the comment
                // text — public in the repo — the live webhook secret.
                $value = trim(preg_replace('/\s+#.*$/', '', $value));
                if ($value !== '' && $value[0] === '#') {
                    $value = '';
                }
            }

            if ($name === '') {
                continue;
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv("$name=$value");
        }
    }
}

if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        switch (strtolower((string) $value)) {
            case 'true':  return true;
            case 'false': return false;
            case 'null':  return null;
            case 'empty': return '';
        }
        return $value;
    }
}

loadEnv();
