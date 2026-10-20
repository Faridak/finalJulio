#!/usr/bin/env bash
# VentDepot — first push of code (+ optional database) to a fresh Linode.
# Run FROM your machine in Git Bash. Needs only ssh + git (no rsync).
#   SERVER=root@1.2.3.4 bash deploy/02-deploy.sh             # code only
#   SERVER=root@1.2.3.4 WITH_DB=1 bash deploy/02-deploy.sh   # code + database
#
# Images are NOT copied to the server: they go straight from this PC to Linode
# Object Storage with  php scripts/migrate-images-to-object-storage.php
# (see deploy/README.md). Later code updates: deploy/pull-deploy.sh on the server.
set -euo pipefail

SERVER="${SERVER:?set SERVER=root@your.linode.ip}"
APP_DIR="${APP_DIR:-/var/www/ventdepot}"
LOCAL_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DB_NAME=ventdepot
cd "$LOCAL_DIR"

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  echo "Uncommitted changes — the server gets the last COMMIT only. Commit first, or Ctrl-C."
  read -r -p "Continue with $(git log --oneline -1)? [y/N] " a; [ "$a" = "y" ] || exit 1
fi

echo "==> 1/3 Code: $(git rev-parse --abbrev-ref HEAD) @ $(git log --oneline -1)"
# git archive = exactly the committed tree (no .env, logs, uploads, node_modules).
git archive --format=tar HEAD | ssh "$SERVER" "mkdir -p $APP_DIR && tar -x -C $APP_DIR"
git rev-parse HEAD | ssh "$SERVER" "cat > $APP_DIR/REVISION"
ssh "$SERVER" "cd $APP_DIR \
  && mkdir -p uploads/thumbs uploads/stores uploads/avatars uploads/images logs storage/cache \
  && chown -R www-data:www-data $APP_DIR \
  && find $APP_DIR -type d -exec chmod 755 {} + && find $APP_DIR -type f -exec chmod 644 {} + \
  && chmod -R 775 uploads logs storage"
# .env holds secrets: readable by Apache, not world-readable (only if already created)
ssh "$SERVER" "if [ -f $APP_DIR/.env ]; then chown root:www-data $APP_DIR/.env && chmod 640 $APP_DIR/.env; fi"

if [ "${WITH_DB:-0}" = "1" ]; then
  echo "==> 2/3 Database (schema + data from local XAMPP '$DB_NAME')"
  echo "    Production should start clean: run  php scripts/clean-demo-data.php --dry-run / --confirm  locally first."
  read -r -p "    Local DB is ready to become PRODUCTION data? [y/N] " a; [ "$a" = "y" ] || exit 1
  TMP=$(mktemp -d)
  "/c/xampp/mysql/bin/mysqldump.exe" -u root --single-transaction --routines "$DB_NAME" | gzip > "$TMP/db.sql.gz"
  echo "    dump: $(du -h "$TMP/db.sql.gz" | cut -f1)"
  scp -q "$TMP/db.sql.gz" "$SERVER:/tmp/db.sql.gz"
  ssh "$SERVER" "set -o pipefail; gunzip -c /tmp/db.sql.gz | mysql $DB_NAME && rm /tmp/db.sql.gz \
    && cd $APP_DIR && php migrate.php --baseline && php migrate.php --status | tail -1"
  rm -rf "$TMP"
else
  echo "==> 2/3 Database skipped (WITH_DB=1 to include)"
fi

echo "==> 3/3 Verify on the server"
ssh "$SERVER" "cd $APP_DIR && php -v | head -1 && php deploy/preflight.php | tail -3"
echo "Done. Next: .env, certbot, crontab — see deploy/README.md"
