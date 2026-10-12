#!/usr/bin/env bash
# VentDepot — Linode provisioning (run ONCE on a fresh Ubuntu 22.04/24.04 LTS)
#   ssh root@YOUR_IP  'bash -s' < deploy/01-provision-server.sh
set -euo pipefail

DOMAIN="${DOMAIN:-ventdepot.com}"
APP_DIR=/var/www/ventdepot
DB_NAME=ventdepot
DB_USER=ventdepot
DB_PASS="${DB_PASS:-$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)}"

echo "==> System packages"
apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
  apache2 mariadb-server \
  php php-mysql php-gd php-curl php-mbstring php-xml php-zip php-intl \
  certbot python3-certbot-apache unzip rsync fail2ban ufw

echo "==> Apache modules"
a2enmod rewrite headers ssl expires >/dev/null

echo "==> Firewall"
ufw --force reset >/dev/null
ufw allow OpenSSH
ufw allow 'Apache Full'
ufw --force enable

echo "==> Database"
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
# backup user (read-only, for the nightly dump)
BACKUP_PASS="${BACKUP_PASS:-$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)}"
mysql -e "CREATE USER IF NOT EXISTS 'backup'@'localhost' IDENTIFIED BY '${BACKUP_PASS}';"
mysql -e "GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON ${DB_NAME}.* TO 'backup'@'localhost'; FLUSH PRIVILEGES;"

echo "==> Directories"
mkdir -p "$APP_DIR" /var/log/ventdepot /backups /etc/ventdepot
chown -R www-data:www-data "$APP_DIR" /var/log/ventdepot
# The crontab runs as www-data, so it must own /backups (was root:750 -> every dump failed)
chown www-data:www-data /backups
chmod 750 /backups
# Backup credentials for mysqldump (the crontab referenced an undefined $DB_BACKUP_PASS)
cat > /etc/ventdepot/backup.cnf <<CNF
[mysqldump]
user=backup
password=${BACKUP_PASS}
CNF
chown www-data:www-data /etc/ventdepot/backup.cnf
chmod 600 /etc/ventdepot/backup.cnf

echo "==> PHP settings (web + CLI)"
# A drop-in per SAPI. (The old version sed-edited php_ini_loaded_file() from
# `php -r`, which is the CLI php.ini — Apache's php.ini never got the settings.)
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
for SAPI in apache2 cli; do
  [ -d "/etc/php/${PHP_VER}/${SAPI}/conf.d" ] || continue
  cat > "/etc/php/${PHP_VER}/${SAPI}/conf.d/99-ventdepot.ini" <<'PHPINI'
; VentDepot production settings
upload_max_filesize = 20M
post_max_size       = 24M
memory_limit        = 512M
expose_php          = Off
display_errors      = Off
log_errors          = On
error_log           = /var/log/ventdepot/php-error.log

; Session cookies: not readable by JS, HTTPS-only, not sent on cross-site POSTs
session.cookie_httponly = 1
session.cookie_secure   = 1
session.cookie_samesite = Lax
session.use_strict_mode = 1
session.use_only_cookies = 1
session.gc_maxlifetime  = 7200

; Performance
opcache.enable          = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 60
PHPINI
done
chown www-data:www-data /var/log/ventdepot

echo "==> Automatic security updates + log rotation"
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq unattended-upgrades >/dev/null
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

    ErrorLog  /var/log/ventdepot/apache-error.log
    CustomLog /var/log/ventdepot/apache-access.log combined
</VirtualHost>
APACHE
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite ventdepot >/dev/null
systemctl reload apache2

echo "==> Timezone (Mexico City — keeps PHP and MariaDB clocks aligned)"
timedatectl set-timezone America/Mexico_City || true
systemctl restart mariadb

cat <<SUMMARY

=========================================================
 PROVISIONING COMPLETE
=========================================================
 App directory : ${APP_DIR}
 Database      : ${DB_NAME}
 DB user       : ${DB_USER}
 DB password   : ${DB_PASS}      <-- SAVE THIS, put it in .env

 Next:
   1. Upload the code   (deploy/02-deploy.sh from your machine)
   2. Fill ${APP_DIR}/.env
   3. certbot --apache -d ${DOMAIN} -d www.${DOMAIN}
   4. crontab: deploy/crontab.txt
=========================================================
SUMMARY
