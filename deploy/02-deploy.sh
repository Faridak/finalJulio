#!/usr/bin/env bash
# VentDepot — push code + data to Linode. Run FROM your machine (Git Bash).
#   SERVER=root@1.2.3.4 bash deploy/02-deploy.sh            # code only
#   SERVER=root@1.2.3.4 WITH_DB=1 WITH_IMAGES=1 bash deploy/02-deploy.sh
set -euo pipefail

SERVER="${SERVER:?set SERVER=root@your.linode.ip}"
APP_DIR="${APP_DIR:-/var/www/ventdepot}"
LOCAL_DIR="$(cd "$(dirname "$0")/.." && pwd)"
IMAGE_SRC="${IMAGE_SRC:-/c/Users/farid/photogallery/}"
DB_NAME=ventdepot

echo "==> 1/4 Code"
rsync -az --delete \
  --exclude '.git' --exclude 'backups/' --exclude 'uploads/legacy' \
  --exclude 'node_modules' --exclude '*.log' --exclude '.env' \
  --exclude 'migration/legacy/' \
  "$LOCAL_DIR/" "$SERVER:$APP_DIR/"
ssh "$SERVER" "chown -R www-data:www-data $APP_DIR && chmod -R 755 $APP_DIR && chmod -R 775 $APP_DIR/uploads"

if [ "${WITH_DB:-0}" = "1" ]; then
  echo "==> 2/4 Database (schema + data)"
  TMP=$(mktemp -d)
  "/c/xampp/mysql/bin/mysqldump.exe" -u root --single-transaction --routines "$DB_NAME" | gzip > "$TMP/db.sql.gz"
  scp "$TMP/db.sql.gz" "$SERVER:/tmp/db.sql.gz"
  ssh "$SERVER" "gunzip -c /tmp/db.sql.gz | mysql $DB_NAME && rm /tmp/db.sql.gz && cd $APP_DIR && php migrate.php --baseline && php migrate.php --status | tail -1"
  rm -rf "$TMP"
else
  echo "==> 2/4 Database skipped (WITH_DB=1 to include)"
fi

if [ "${WITH_IMAGES:-0}" = "1" ]; then
  echo "==> 3/4 Product images (this is the slow one — resumable, safe to re-run)"
  ssh "$SERVER" "mkdir -p $APP_DIR/uploads/legacy $APP_DIR/uploads/thumbs"
  # thumbnails first: small, and they're what the storefront grids use
  rsync -az --info=progress2 "$LOCAL_DIR/uploads/thumbs/" "$SERVER:$APP_DIR/uploads/thumbs/"
  # then the originals actually referenced by the catalog (not all 1.47M files)
  "/c/xampp/php/php.exe" -r '
    $p = new PDO("mysql:host=127.0.0.1;dbname=ventdepot","root","");
    foreach ($p->query("SELECT DISTINCT image_url FROM products WHERE image_url LIKE \"uploads/legacy/%\"") as $r)
        if (is_file($r[0])) echo substr($r[0], strlen("uploads/legacy/")) . "\n";
  ' > /tmp/vd-images.txt
  echo "    $(wc -l < /tmp/vd-images.txt) referenced originals"
  rsync -az --info=progress2 --files-from=/tmp/vd-images.txt "$IMAGE_SRC" "$SERVER:$APP_DIR/uploads/legacy/"
  ssh "$SERVER" "chown -R www-data:www-data $APP_DIR/uploads"
else
  echo "==> 3/4 Images skipped (WITH_IMAGES=1 to include)"
fi

echo "==> 4/4 Verify"
ssh "$SERVER" "cd $APP_DIR && php tests/regression.php | tail -3"
echo "Done."
