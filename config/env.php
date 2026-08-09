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

            // Strip surrounding quotes.
            if (strlen($value) >= 2 &&
                (($value[0] === '"' && substr($value, -1) === '"') ||
                 ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
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
