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
mysql -e "CREATE USER IF NOT EXISTS 'backup'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON ${DB_NAME}.* TO 'backup'@'localhost'; FLUSH PRIVILEGES;"

echo "==> Directories"
mkdir -p "$APP_DIR" /var/log/ventdepot /backups
chown -R www-data:www-data "$APP_DIR" /var/log/ventdepot
chmod 750 /backups

echo "==> PHP tuning (uploads + memory for the ETL/thumbnails)"
PHP_INI=$(php -r 'echo php_ini_loaded_file();')
sed -i 's/^upload_max_filesize.*/upload_max_filesize = 20M/; s/^post_max_size.*/post_max_size = 24M/; s/^memory_limit.*/memory_limit = 512M/; s/^expose_php.*/expose_php = Off/' "$PHP_INI"

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
