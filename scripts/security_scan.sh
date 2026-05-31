#!/usr/bin/env bash
set -euo pipefail

echo "Running dependency audit..."
if command -v composer >/dev/null 2>&1; then
  composer audit || echo "composer audit exited with non-zero status"
else
  echo "composer not found; skipping composer audit"
fi

echo "Running PHPStan (if installed)..."
if [ -f vendor/bin/phpstan ]; then
  vendor/bin/phpstan analyse --memory-limit=1G || true
else
  echo "phpstan not installed; run 'composer install' to install dev tools"
fi

echo "Running PHPCS (if installed)..."
if [ -f vendor/bin/phpcs ]; then
  vendor/bin/phpcs --standard=PSR12 --extensions=php . || true
else
  echo "phpcs not installed; skipping"
fi

echo "Security scan complete"
