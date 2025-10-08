# VentDepot Deployment Guide for Linode

This guide provides step-by-step instructions for deploying the VentDepot e-commerce platform to a Linode server.

## Prerequisites

- A Linode account
- A registered domain name (optional but recommended)
- SSH access to your Linode server
- Basic knowledge of Linux command line

## Server Provisioning

1. Log in to your Linode account
2. Create a new Linode:
   - Select a distribution (Ubuntu 20.04 LTS recommended)
   - Choose a region
   - Select a plan (at least 2GB RAM recommended)
   - Add SSH keys for secure access
   - Create the Linode

## Initial Server Setup

1. SSH into your server:
   ```bash
   ssh root@your_server_ip
   ```

2. Update the system:
   ```bash
   apt update && apt upgrade -y
   ```

3. Create a non-root user (replace 'ventdepot' with your preferred username):
   ```bash
   adduser ventdepot
   usermod -aG sudo ventdepot
   ```

4. Add SSH key for the new user (if needed):
   ```bash
   mkdir -p /home/ventdepot/.ssh
   cp /root/.ssh/authorized_keys /home/ventdepot/.ssh/
   chown -R ventdepot:ventdepot /home/ventdepot/.ssh
   chmod 700 /home/ventdepot/.ssh
   chmod 600 /home/ventdepot/.ssh/authorized_keys
   ```

5. Configure firewall:
   ```bash
   ufw allow OpenSSH
   ufw enable
   ```

## Installing Required Software

1. Install LAMP stack:
   ```bash
   # Apache
   apt install apache2 -y
   
   # MySQL
   apt install mysql-server -y
   
   # PHP and extensions
   apt install php php-cli php-fpm php-json php-common php-mysql php-zip php-gd php-mbstring php-curl php-xml php-pear php-bcmath -y
   ```

2. Install Redis:
   ```bash
   apt install redis-server -y
   ```

3. Install Composer:
   ```bash
   curl -sS https://getcomposer.org/installer | php
   mv composer.phar /usr/local/bin/composer
   ```

4. Install Git:
   ```bash
   apt install git -y
   ```

## Configuring Apache

1. Create a virtual host configuration:
   ```bash
   nano /etc/apache2/sites-available/ventdepot.conf
   ```

2. Add the following configuration (adjust domain as needed):
   ```apache
   <VirtualHost *:80>
       ServerName yourdomain.com
       ServerAlias www.yourdomain.com
       DocumentRoot /var/www/ventdepot/public

       <Directory /var/www/ventdepot/public>
           AllowOverride All
           Require all granted
       </Directory>

       ErrorLog ${APACHE_LOG_DIR}/ventdepot_error.log
       CustomLog ${APACHE_LOG_DIR}/ventdepot_access.log combined
   </VirtualHost>
   ```

3. Enable the site and required modules:
   ```bash
   a2ensite ventdepot.conf
   a2enmod rewrite
   systemctl restart apache2
   ```

## Setting Up SSL Certificate (Let's Encrypt)

1. Install Certbot:
   ```bash
   apt install certbot python3-certbot-apache -y
   ```

2. Obtain SSL certificate:
   ```bash
   certbot --apache -d yourdomain.com -d www.yourdomain.com
   ```

## Database Setup

1. Secure MySQL installation:
   ```bash
   mysql_secure_installation
   ```

2. Log into MySQL:
   ```bash
   mysql -u root -p
   ```

3. Create database and user:
   ```sql
   CREATE DATABASE ventdepot;
   CREATE USER 'ventdepot_user'@'localhost' IDENTIFIED BY 'strong_password';
   GRANT ALL PRIVILEGES ON ventdepot.* TO 'ventdepot_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

4. Import the database schema:
   ```bash
   mysql -u ventdepot_user -p ventdepot < migrations/001_initial_schema.sql
   ```

## Deploying Application Code

1. Clone the repository (or upload your code):
   ```bash
   cd /var/www
   git clone https://github.com/Faridak/finalJulio.git
   chown -R ventdepot:www-data ventdepot
   ```

2. Install PHP dependencies:
   ```bash
   cd /var/www/finalJulio
   composer install --no-dev --optimize-autoloader
   ```

3. Set proper permissions:
   ```bash
   chmod -R 755 storage/
   chmod -R 755 public/uploads/
   chown -R www-data:www-data storage/
   chown -R www-data:www-data public/uploads/
   ```

## Configuring Environment Variables

1. Create the .env file:
   ```bash
   cp .env.example .env
   ```

2. Edit the .env file with your configuration:
   ```bash
   nano .env
   ```

3. Update the following variables:
   - Database connection details
   - Payment gateway credentials
   - Email configuration
   - Redis connection details
   - Application URL

4. Generate application key (if needed):
   ```bash
   php artisan key:generate
   ```

## Setting Up Cron Jobs

1. Edit crontab:
   ```bash
   crontab -e
   ```

2. Add the following jobs:
   ```bash
   # Daily cleanup
   0 2 * * * cd /var/www/finalJulio && php cleanup.php >> /var/log/ventdepot_cleanup.log 2>&1
   
   # Hourly cache refresh
   0 * * * * cd /var/www/finalJulio && php refresh_cache.php >> /var/log/ventdepot_cache.log 2>&1
   ```

## Configuring Firewall

1. Allow HTTP and HTTPS traffic:
   ```bash
   ufw allow 'Apache Full'
   ```

2. Check firewall status:
   ```bash
   ufw status
   ```

## Final Testing

1. Restart services:
   ```bash
   systemctl restart apache2
   systemctl restart mysql
   systemctl restart redis
   ```

2. Test the application by visiting your domain

3. Check Apache error logs if needed:
   ```bash
   tail -f /var/log/apache2/ventdepot_error.log
   ```

## Troubleshooting

### Common Issues

1. **Permission denied errors**:
   - Ensure proper ownership: `chown -R www-data:www-data /var/www/finalJulio`
   - Check directory permissions: `chmod -R 755 /var/www/finalJulio`

2. **Database connection errors**:
   - Verify database credentials in .env file
   - Ensure MySQL is running: `systemctl status mysql`

3. **500 Internal Server Error**:
   - Check Apache error logs: `tail -f /var/log/apache2/ventdepot_error.log`
   - Verify .htaccess files are properly configured

4. **PHP modules missing**:
   - Install required extensions: `apt install php-extension-name`

### Useful Commands

- Check Apache status: `systemctl status apache2`
- Check MySQL status: `systemctl status mysql`
- Check Redis status: `systemctl status redis`
- View application logs: `tail -f storage/logs/app.log`
- Restart all services: `systemctl restart apache2 mysql redis`

## Migration Plan

When you're ready to migrate your database:

1. Export your current database:
   ```bash
   mysqldump -u current_user -p current_database > ventdepot_data.sql
   ```

2. Transfer the dump to your Linode server:
   ```bash
   scp ventdepot_data.sql user@your_server_ip:/tmp/
   ```

3. Import the data on Linode:
   ```bash
   mysql -u ventdepot_user -p ventdepot < /tmp/ventdepot_data.sql
   ```

4. Update any domain references in the database if needed

## Backup Strategy

1. Set up automated database backups:
   ```bash
   # Add to crontab
   0 3 * * 0 mysqldump -u ventdepot_user -p'db_password' ventdepot > /home/ventdepot/backups/ventdepot_$(date +\%Y\%m\%d).sql
   ```

2. Set up file backups using rsync or similar tools

3. Consider using Linode's backup service for full server snapshots