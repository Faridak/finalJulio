#!/usr/bin/env bash
# VentDepot — first push of code (+ optional database) to a fresh Linode.
# Run FROM your machine in Git Bash. Needs only ssh + git (no rsync).
#   SERVER=deploy@1.2.3.4 bash deploy/02-deploy.sh             # code only
#   SERVER=deploy@1.2.3.4 WITH_DB=1 bash deploy/02-deploy.sh   # code + database
# The server user needs passwordless sudo (01-provision-server.sh sets this up).
# On Windows with a passphrase key in the Windows ssh-agent, use Windows OpenSSH:
#   SSH=/c/Windows/System32/OpenSSH/ssh.exe SCP=/c/Windows/System32/OpenSSH/scp.exe SERVER=ventdepot ...
#
# Images are NOT copied to the server: they go straight from this PC to Linode
# Object Storage with  php scripts/migrate-images-to-object-storage.php
# (see deploy/README.md). Later code updates: deploy/pull-deploy.sh on the server.
set -euo pipefail

SERVER="${SERVER:?set SERVER=deploy@your.linode.ip}"
SSH="${SSH:-ssh}"
SCP="${SCP:-scp}"
APP_DIR="${APP_DIR:-/var/www/ventdepot}"
LOCAL_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DB_NAME=ventdepot
cd "$LOCAL_DIR"
# ASSUME_YES=1 answers the confirmations (for unattended runs); otherwise asks on the terminal.
confirm() { [ "${ASSUME_YES:-0}" = "1" ] && { echo "$1 [y] (ASSUME_YES)"; return 0; }; read -r -p "$1 [y/N] " a < /dev/tty; [ "$a" = "y" ]; }

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  echo "Uncommitted changes — the server gets the last COMMIT only. Commit first, or Ctrl-C."
  confirm "Continue with $(git log --oneline -1)?" || exit 1
fi

echo "==> 1/3 Code: $(git rev-parse --abbrev-ref HEAD) @ $(git log --oneline -1)"
# git archive = exactly the committed tree (no .env, logs, uploads, node_modules).
git archive --format=tar HEAD | "$SSH" "$SERVER" "sudo mkdir -p $APP_DIR && sudo tar -x --no-same-owner -C $APP_DIR"
git rev-parse HEAD | "$SSH" "$SERVER" "sudo tee $APP_DIR/REVISION >/dev/null"
"$SSH" -n "$SERVER" "cd $APP_DIR \
  && sudo mkdir -p uploads/thumbs uploads/stores uploads/avatars uploads/images logs storage/cache \
  && sudo chown -R www-data:www-data $APP_DIR \
  && sudo find $APP_DIR -type d -exec chmod 755 {} + && sudo find $APP_DIR -type f -exec chmod 644 {} + \
  && sudo chmod -R 775 uploads logs storage"
# .env holds secrets: readable by Apache, not world-readable (only if already created)
"$SSH" -n "$SERVER" "if sudo test -f $APP_DIR/.env; then sudo chown root:www-data $APP_DIR/.env && sudo chmod 640 $APP_DIR/.env; fi"

if [ "${WITH_DB:-0}" = "1" ]; then
  echo "==> 2/3 Database (schema + data from local XAMPP '$DB_NAME')"
  echo "    Production should start clean: run  php scripts/clean-demo-data.php --dry-run / --confirm  locally first."
  confirm "    Local DB is ready to become PRODUCTION data?" || exit 1
  TMP=$(mktemp -d)
  "/c/xampp/mysql/bin/mysqldump.exe" -u root --single-transaction --routines "$DB_NAME" | gzip > "$TMP/db.sql.gz"
  echo "    dump: $(du -h "$TMP/db.sql.gz" | cut -f1)"
  "$SCP" -q "$TMP/db.sql.gz" "$SERVER:/tmp/db.sql.gz"
  "$SSH" -n "$SERVER" "set -o pipefail; gunzip -c /tmp/db.sql.gz | sudo mariadb $DB_NAME && rm /tmp/db.sql.gz \
    && cd $APP_DIR && sudo -u www-data php migrate.php --baseline && sudo -u www-data php migrate.php --status | tail -1"
  rm -rf "$TMP"
else
  echo "==> 2/3 Database skipped (WITH_DB=1 to include)"
fi

echo "==> 3/3 Verify on the server"
"$SSH" -n "$SERVER" "cd $APP_DIR && php -v | head -1 && sudo -u www-data php deploy/preflight.php | tail -3"
echo "Done. Next: .env, certbot, crontab — see deploy/README.md"
