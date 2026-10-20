#!/usr/bin/env bash
# Copy the catalog's legacy product photos straight from Hostek (old site, FTP)
# into the Linode Object Storage bucket. Run ON THE LINODE, after the database
# is imported and .env has the S3_* keys:
#
#   sudo bash deploy/hostek-images-to-bucket.sh
#   FTP_USER=vd-migrate SRC_DIR=wwwroot/photogallery sudo -E bash deploy/hostek-images-to-bucket.sh
#
# - Only files referenced by products.image_url (uploads/legacy/...) are copied,
#   not the whole 1.47M-file gallery. Same names as the DB expects.
# - Hostek password: asked with hidden input, never written to disk or history.
# - Bucket keys: read from the app .env (never printed).
# - Resumable: re-running skips objects already in the bucket.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/ventdepot}"
FTP_HOST="${FTP_HOST:-62.106.80.60}"
FTP_USER="${FTP_USER:-vd-migrate}"
SRC_DIR="${SRC_DIR:-wwwroot/photogallery}"     # path on Hostek, relative to the FTP user's root
FTP_TLS="${FTP_TLS:-explicit}"                 # explicit (FTPS) | implicit | none (plain FTP: password in clear)
PREFIX="uploads/legacy"
WORK=/root/vd-image-migration
mkdir -p "$WORK"

envval() { grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -E 's/[[:space:]]+#.*$//; s/^["'"'"']//; s/["'"'"']$//'; }

echo "==> rclone"
command -v rclone >/dev/null || { apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq rclone >/dev/null; }
rclone version | head -1

echo "==> Bucket settings from $APP_DIR/.env"
S3_ENDPOINT=$(envval S3_ENDPOINT); S3_BUCKET=$(envval S3_BUCKET)
S3_KEY=$(envval S3_KEY); S3_SECRET=$(envval S3_SECRET)
[ -n "$S3_ENDPOINT" ] && [ -n "$S3_BUCKET" ] && [ -n "$S3_KEY" ] && [ -n "$S3_SECRET" ] \
  || { echo "Missing S3_ENDPOINT/S3_BUCKET/S3_KEY/S3_SECRET in .env"; exit 1; }
echo "    $S3_ENDPOINT / $S3_BUCKET"

# Remotes defined only through environment variables: nothing saved to rclone.conf
export RCLONE_CONFIG_BUCKET_TYPE=s3
export RCLONE_CONFIG_BUCKET_PROVIDER=Linode
export RCLONE_CONFIG_BUCKET_ENDPOINT="${S3_ENDPOINT#https://}"
export RCLONE_CONFIG_BUCKET_ACCESS_KEY_ID="$S3_KEY"
export RCLONE_CONFIG_BUCKET_SECRET_ACCESS_KEY="$S3_SECRET"
export RCLONE_CONFIG_BUCKET_ACL=public-read

export RCLONE_CONFIG_HOSTEK_TYPE=ftp
export RCLONE_CONFIG_HOSTEK_HOST="$FTP_HOST"
export RCLONE_CONFIG_HOSTEK_USER="$FTP_USER"
case "$FTP_TLS" in
  explicit) export RCLONE_CONFIG_HOSTEK_EXPLICIT_TLS=true ;;
  implicit) export RCLONE_CONFIG_HOSTEK_TLS=true ;;
  none)     echo "    WARNING: plain FTP — the password crosses the internet unencrypted" ;;
esac
read -r -s -p "Hostek FTP password for $FTP_USER@$FTP_HOST: " FTP_PASS; echo
export RCLONE_CONFIG_HOSTEK_PASS="$(rclone obscure "$FTP_PASS")"
unset FTP_PASS

echo "==> Checking both ends"
rclone lsf "hostek:$SRC_DIR" --max-depth 1 2>/dev/null | head -3 >/dev/null \
  || { echo "Cannot list hostek:$SRC_DIR — check user/password, TLS mode (FTP_TLS=), Hostek IP List"; exit 1; }
rclone lsf "bucket:$S3_BUCKET" --max-depth 1 >/dev/null || { echo "Cannot reach the bucket"; exit 1; }
echo "    Hostek OK, bucket OK"

echo "==> Building the list of catalog images from the database"
cd "$APP_DIR"
php -r '
  require "config/database.php";
  $q = $pdo->query("SELECT image_url FROM products WHERE image_url LIKE \"uploads/legacy/%\"
                    UNION SELECT image_path FROM product_images WHERE image_path LIKE \"uploads/legacy/%\"");
  foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $u) echo substr($u, strlen("uploads/legacy/")), "\n";
' | sort -u > "$WORK/files.txt"
echo "    $(wc -l < "$WORK/files.txt") referenced originals"

echo "==> Copying Hostek → bucket (resumable; log: $WORK/copy.log)"
set +e
rclone copy "hostek:$SRC_DIR" "bucket:$S3_BUCKET/$PREFIX" \
  --files-from "$WORK/files.txt" --no-traverse \
  --transfers 8 --checkers 16 --retries 5 --low-level-retries 10 \
  --header-upload "Cache-Control: public, max-age=31536000, immutable" \
  --stats 30s --stats-one-line --log-file "$WORK/copy.log" --log-level NOTICE --progress
RC=$?
set -e

echo "==> Report"
grep -E "not found|doesn't exist|no such file" "$WORK/copy.log" | sed -E 's/.*: ([^:]+): .*/\1/' | sort -u > "$WORK/missing.txt" || true
echo "    copied/verified : $(( $(wc -l < "$WORK/files.txt") - $(wc -l < "$WORK/missing.txt") ))"
echo "    missing on Hostek: $(wc -l < "$WORK/missing.txt")  (list: $WORK/missing.txt)"
[ "$RC" -eq 0 ] && echo "Done." || echo "rclone exited $RC — re-run to resume; see $WORK/copy.log"
exit "$RC"
