#!/usr/bin/env bash
# GitHub -> Linode deploy. Run ON THE SERVER:  bash deploy/pull-deploy.sh
# Pulls latest master, applies migrations, fixes perms. Never touches .env,
# uploads/, or the database.
set -euo pipefail
APP=/var/www/ventdepot
cd "$APP"

echo "==> Backup DB before deploy (safety) — abort if it fails; never migrate without one"
BK="/backups/predeploy-$(date +%F-%H%M).sql.gz"
mysqldump --single-transaction --routines ventdepot | gzip > "$BK" || { rm -f "$BK"; echo "Backup failed — deploy aborted."; exit 1; }
echo "  saved $BK ($(du -h "$BK" | cut -f1))"

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
