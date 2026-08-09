#!/usr/bin/env bash
# GitHub -> Linode deploy. Run ON THE SERVER:  bash deploy/pull-deploy.sh
# Pulls latest master, applies migrations, fixes perms. Never touches .env,
# uploads/, or the database.
set -euo pipefail
APP=/var/www/ventdepot
cd "$APP"

echo "==> Backup DB before deploy (safety)"
mysqldump ventdepot | gzip > "/backups/predeploy-$(date +%F-%H%M).sql.gz" 2>/dev/null || echo "  (skipped)"

echo "==> Fetch + reset to origin/master (keeps .env, uploads/, DB untouched — they're gitignored)"
git fetch --quiet origin
git reset --hard origin/master

echo "==> Apply any new migrations"
php migrate.php

echo "==> Fix ownership/permissions"
chown -R www-data:www-data "$APP"
chmod -R 775 "$APP/uploads"

echo "==> Reload Apache"
systemctl reload apache2

echo "==> Verify"
php deploy/preflight.php | tail -1
echo "Deploy complete: $(git log --oneline -1)"
