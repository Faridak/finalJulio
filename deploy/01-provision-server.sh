#!/usr/bin/env bash
# VentDepot — production server provisioning. Run ONCE on a fresh Ubuntu 24.04 LTS
# as root, with your SSH key already in /root/.ssh/authorized_keys:
#   ssh root@YOUR_IP 'DOMAIN=ventdepot.com bash -s' < deploy/01-provision-server.sh
#
# Result: Apache (event MPM) + PHP-FPM, MariaDB, firewall, fail2ban, automatic
# security updates, a key-only `deploy` admin user (root SSH login disabled),
# and /var/www/ventdepot/.env pre-filled with generated DB credentials.
# Secrets are written to files with tight permissions and NEVER printed.
set -euo pipefail

DOMAIN="${DOMAIN:-ventdepot.com}"
HOSTNAME_FQDN="${HOSTNAME_FQDN:-ventdepot-prod}"
ADMIN_USER="${ADMIN_USER:-deploy}"
APP_DIR=/var/www/ventdepot
DB_NAME=ventdepot
DB_USER=ventdepot
TZ_NAME=America/Mexico_City
gen() { openssl rand -base64 36 | tr -d '/+=\n' | head -c 32; }

[ "$(id -u)" -eq 0 ] || { echo "Run as root"; exit 1; }
[ -s /root/.ssh/authorized_keys ] || { echo "No SSH key in /root/.ssh/authorized_keys — refusing (you'd be locked out)"; exit 1; }

echo "==> Host name, time zone"
hostnamectl set-hostname "$HOSTNAME_FQDN"
grep -q "$HOSTNAME_FQDN" /etc/hosts || echo "127.0.1.1 $HOSTNAME_FQDN" >> /etc/hosts
timedatectl set-timezone "$TZ_NAME"

echo "==> System packages (this takes a few minutes)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get -y -qq -o Dpkg::Options::=--force-confold upgrade >/dev/null
apt-get install -y -qq \
  apache2 mariadb-server \
  php-fpm php-cli php-mysql php-gd php-curl php-mbstring php-xml php-zip php-intl php-opcache \
  certbot python3-certbot-apache git unzip rclone fail2ban ufw unattended-upgrades >/dev/null
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
echo "    PHP $PHP_VER, $(mariadb --version | grep -oE '[0-9]+\.[0-9]+\.[0-9]+-MariaDB')"

echo "==> Admin user '$ADMIN_USER' (key-only, sudo)"
id "$ADMIN_USER" >/dev/null 2>&1 || adduser --disabled-password --gecos "" "$ADMIN_USER" >/dev/null
usermod -aG sudo,www-data "$ADMIN_USER"
install -d -m 700 -o "$ADMIN_USER" -g "$ADMIN_USER" "/home/$ADMIN_USER/.ssh"
sort -u /root/.ssh/authorized_keys > "/home/$ADMIN_USER/.ssh/authorized_keys"
sort -u /root/.ssh/authorized_keys -o /root/.ssh/authorized_keys
chown "$ADMIN_USER:$ADMIN_USER" "/home/$ADMIN_USER/.ssh/authorized_keys"
chmod 600 "/home/$ADMIN_USER/.ssh/authorized_keys"
# No password exists for this account (key-only), so sudo cannot ask for one.
echo "$ADMIN_USER ALL=(ALL) NOPASSWD:ALL" > "/etc/sudoers.d/90-$ADMIN_USER"
chmod 440 "/etc/sudoers.d/90-$ADMIN_USER"
visudo -cq

echo "==> SSH hardening: keys only, no root login"
cat > /etc/ssh/sshd_config.d/99-ventdepot.conf <<SSHD
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
AllowUsers $ADMIN_USER
MaxAuthTries 4
LoginGraceTime 30
SSHD
mkdir -p /run/sshd   # 24.04 creates it only when sshd runs as a daemon
sshd -t
systemctl reload ssh 2>/dev/null || systemctl restart ssh   # 24.04: ssh may be socket-activated

echo "==> Firewall (ufw) + fail2ban"
ufw --force reset >/dev/null
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw allow OpenSSH >/dev/null
ufw allow 'Apache Full' >/dev/null
ufw --force enable >/dev/null
cat > /etc/fail2ban/jail.d/ventdepot.local <<'F2B'
[sshd]
enabled  = true
maxretry = 5
findtime = 10m
bantime  = 1h
F2B
systemctl enable --now fail2ban >/dev/null 2>&1
systemctl restart fail2ban

echo "==> Apache: event MPM + PHP-FPM, HTTP/2"
a2dismod -q "php$PHP_VER" mpm_prefork >/dev/null 2>&1 || true
a2enmod -q mpm_event proxy_fcgi setenvif rewrite headers ssl expires http2 >/dev/null
a2enconf -q "php$PHP_VER-fpm" >/dev/null
cat > /etc/apache2/conf-available/ventdepot-security.conf <<'SEC'
ServerTokens Prod
ServerSignature Off
TraceEnable Off
ServerName ventdepot-prod
Protocols h2 http/1.1
SEC
a2enconf -q ventdepot-security >/dev/null

echo "==> PHP-FPM pool (8 GB box: up to 40 workers)"
POOL="/etc/php/$PHP_VER/fpm/pool.d/www.conf"
sed -i -E 's/^;?pm = .*/pm = dynamic/; s/^;?pm.max_children = .*/pm.max_children = 40/;
           s/^;?pm.start_servers = .*/pm.start_servers = 8/; s/^;?pm.min_spare_servers = .*/pm.min_spare_servers = 4/;
           s/^;?pm.max_spare_servers = .*/pm.max_spare_servers = 12/; s/^;?pm.max_requests = .*/pm.max_requests = 500/' "$POOL"

echo "==> PHP settings (FPM + CLI)"
for SAPI in fpm cli; do
  cat > "/etc/php/$PHP_VER/$SAPI/conf.d/99-ventdepot.ini" <<PHPINI
; VentDepot production settings
upload_max_filesize = 20M
post_max_size       = 24M
memory_limit        = 512M
max_execution_time  = 60
expose_php          = Off
display_errors      = Off
log_errors          = On
error_log           = /var/log/ventdepot/php-error.log
date.timezone       = $TZ_NAME

; Session cookies: not readable by JS, HTTPS-only, not sent on cross-site POSTs
session.cookie_httponly  = 1
session.cookie_secure    = 1
session.cookie_samesite  = Lax
session.use_strict_mode  = 1
session.use_only_cookies = 1
session.gc_maxlifetime   = 7200

; Performance
opcache.enable                = 1
opcache.memory_consumption    = 192
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps   = 1
opcache.revalidate_freq       = 60
realpath_cache_size           = 4096K
PHPINI
done

echo "==> MariaDB: tuning, database, users"
cat > /etc/mysql/mariadb.conf.d/90-ventdepot.cnf <<'MYCNF'
[mysqld]
bind-address            = 127.0.0.1
character-set-server    = utf8mb4
collation-server        = utf8mb4_unicode_ci
innodb_buffer_pool_size = 1G
innodb_log_file_size    = 256M
innodb_flush_log_at_trx_commit = 1
max_connections         = 150
slow_query_log          = 1
slow_query_log_file     = /var/log/mysql/slow.log
long_query_time         = 1
# Same sql_mode as development (XAMPP). The app was built and tested non-strict;
# moving to STRICT_TRANS_TABLES is planned after a full test pass (PRODUCTION_PLAN).
sql_mode                = "NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION"
MYCNF
systemctl restart mariadb
# remove anonymous users / test DB (what mysql_secure_installation does; root keeps unix_socket auth)
mariadb -e "DELETE FROM mysql.global_priv WHERE User=''; DROP DATABASE IF EXISTS test; FLUSH PRIVILEGES;"
mariadb -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

mkdir -p /etc/ventdepot "$APP_DIR" /var/log/ventdepot /backups
chmod 750 /etc/ventdepot
if [ ! -f "$APP_DIR/.env" ]; then
  DB_PASS=$(gen)
  mariadb -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
              ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
              GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
  cat > "$APP_DIR/.env" <<ENV
# VentDepot production .env — generated $(date +%F) by 01-provision-server.sh
# Values with spaces must be quoted. Unquoted " # text" is a comment.
APP_ENV=production
APP_TIMEZONE=$TZ_NAME
APP_URL=https://$DOMAIN

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASS

# PayPal (sandbox until a full test order passes)
PAYPAL_US_CLIENT_ID=
PAYPAL_US_SECRET=
PAYPAL_MX_CLIENT_ID=
PAYPAL_MX_SECRET=
PAYPAL_MX_CURRENCY=USD
PAYPAL_MODE=sandbox

OXXO_WEBHOOK_SECRET=$(openssl rand -hex 32)

API_ENABLED=false
JWT_SECRET=

GOOGLE_MAPS_API_KEY=
SHIPPING_ORIGIN_ADDRESS="Mexico City, CDMX, Mexico"

MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@$DOMAIN
MAIL_FROM_NAME="VentDepot"

# Object storage (bucket keys added separately; ASSET_BASE_URL only after the image migration)
STORAGE_DRIVER=s3
S3_ENDPOINT=https://us-ord-10.linodeobjects.com
S3_REGION=us-ord
S3_BUCKET=ventdepot-media
S3_KEY=
S3_SECRET=
ASSET_BASE_URL=
ENV
  unset DB_PASS
  echo "    .env created with generated DB password (not shown)"
else
  echo "    .env exists — DB user/password left unchanged"
fi

BACKUP_PASS=$(gen)
mariadb -e "CREATE USER IF NOT EXISTS 'backup'@'localhost' IDENTIFIED BY '${BACKUP_PASS}';
            ALTER USER 'backup'@'localhost' IDENTIFIED BY '${BACKUP_PASS}';
            GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON ${DB_NAME}.* TO 'backup'@'localhost'; FLUSH PRIVILEGES;"
printf '[mysqldump]\nuser=backup\npassword=%s\n' "$BACKUP_PASS" > /etc/ventdepot/backup.cnf
unset BACKUP_PASS

echo "==> Ownership + permissions"
chown -R www-data:www-data "$APP_DIR" /var/log/ventdepot /backups
chown root:www-data "$APP_DIR/.env" /etc/ventdepot /etc/ventdepot/backup.cnf
chmod 640 "$APP_DIR/.env" /etc/ventdepot/backup.cnf
chmod 750 /backups
chmod 2775 "$APP_DIR"          # group www-data; deploy user is in it

echo "==> Automatic security updates + log rotation"
dpkg-reconfigure -f noninteractive unattended-upgrades >/dev/null
cat > /etc/logrotate.d/ventdepot <<'ROTATE'
/var/log/ventdepot/*.log {
    weekly
    rotate 12
    compress
    missingok
    notifempty
    copytruncate
}
ROTATE

echo "==> Apache vhost"
cat > /etc/apache2/sites-available/ventdepot.conf <<APACHE
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ServerAlias www.${DOMAIN}
    DocumentRoot ${APP_DIR}

    <Directory ${APP_DIR}>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # Object storage: after the image migration, uncomment and set to the same
    # value as ASSET_BASE_URL in .env (.htaccess redirects missing uploads there).
    #SetEnv ASSET_ORIGIN https://ventdepot-media.us-ord-10.linodeobjects.com

    ErrorLog  /var/log/ventdepot/apache-error.log
    CustomLog /var/log/ventdepot/apache-access.log combined
</VirtualHost>
APACHE
a2dissite -q 000-default >/dev/null 2>&1 || true
a2ensite -q ventdepot >/dev/null
apache2ctl configtest 2>&1 | grep -v "^Syntax OK" || true
systemctl restart "php$PHP_VER-fpm" apache2
systemctl enable "php$PHP_VER-fpm" apache2 mariadb >/dev/null 2>&1

echo "==> Status"
for s in apache2 "php$PHP_VER-fpm" mariadb fail2ban ssh; do printf "    %-14s %s\n" "$s" "$(systemctl is-active "$s")"; done
printf "    %-14s %s\n" "apache mpm" "$(apache2ctl -M 2>/dev/null | grep -oE 'mpm_[a-z]+')"
printf "    %-14s %s\n" "ufw" "$(ufw status | head -1)"

cat <<SUMMARY

=========================================================
 PROVISIONING COMPLETE — $(hostname), $(lsb_release -ds)
=========================================================
 SSH from now on : ssh ${ADMIN_USER}@<this IP>   (root login disabled)
 App directory   : ${APP_DIR}
 Secrets         : ${APP_DIR}/.env (DB password generated, never shown)
                   /etc/ventdepot/backup.cnf (backup DB user)
 Still to fill in .env: S3_KEY / S3_SECRET, PayPal, MAIL_*, Google Maps
 Next: deploy code (02-deploy.sh) → import DB → certbot → crontab
=========================================================
SUMMARY
