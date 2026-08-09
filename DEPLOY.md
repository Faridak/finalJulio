# VentDepot — Deployment Guide (Linode)

## 1. Server
- Ubuntu LTS + Apache (or nginx) + PHP 8.x (`php-mysql php-gd php-curl php-mbstring php-fileinfo`) + MariaDB
- SSL: `certbot --apache` (Let's Encrypt)
- Firewall: allow 80/443/SSH only

## 2. Code + database
```bash
# code
rsync -a ./ user@server:/var/www/ventdepot/   # exclude uploads/legacy junction!

# fresh database
mysql -u root -e "CREATE DATABASE ventdepot CHARACTER SET utf8mb4"
mysql -u root ventdepot < migration/000_full_schema.sql
php migrate.php --baseline          # marks all current migrations applied
php migrate.php --status            # verify: 0 pending

# data (products, categories, suppliers…)
mysqldump -u root ventdepot --no-create-info > data.sql   # on the dev machine, AFTER clean-demo-data
mysql -u root ventdepot < data.sql                        # on the server
```
Future schema changes: drop a new `migration/NNN_*.sql`, run `php migrate.php`.

## 3. Images
```bash
# copy the real photo library (not the junction) — only referenced files if space matters
rsync -a /c/Users/farid/photogallery/ user@server:/var/www/ventdepot/uploads/legacy/
chown -R www-data:www-data uploads/
```

## 4. .env (copy .env.example, fill real values)
| Key | Purpose |
|---|---|
| DB_* | MariaDB credentials |
| APP_ENV=production | disables the mock payment method |
| APP_URL=https://… | links in emails |
| PAYPAL_US_CLIENT_ID/SECRET | PayPal USA business account |
| PAYPAL_MX_CLIENT_ID/SECRET | PayPal Mexico business account |
| PAYPAL_MODE=live | after sandbox testing |
| OXXO_WEBHOOK_SECRET | shared secret for the PSP webhook |
| GOOGLE_MAPS_API_KEY | real driving distance for shipping |
| SHIPPING_ORIGIN_ADDRESS | your warehouse address |
| MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION | SMTP (SmtpMailer built in) |
| MAIL_FROM_ADDRESS/NAME | sender identity |

## 5. Crontab
```cron
* * * * *    php /var/www/ventdepot/cron/email-worker.php >> /var/log/ventdepot/email.log 2>&1
*/5 * * * *  php /var/www/ventdepot/cron/reservation-worker.php >> /var/log/ventdepot/resv.log 2>&1
*/15 * * * * php /var/www/ventdepot/cron/backinstock-worker.php >> /var/log/ventdepot/bis.log 2>&1
0 2 * * *    php /var/www/ventdepot/cron/auto-po-worker.php >> /var/log/ventdepot/autopo.log 2>&1
0 3 * * *    mysqldump -u backup ventdepot | gzip > /backups/ventdepot-$(date +\%F).sql.gz
```

## 6. Launch-day order
1. `mysqldump` backup of the dev DB
2. `php scripts/clean-demo-data.php --dry-run` → review → `--confirm`
3. Seed opening balances (real cash + inventory value → Owner Equity)
4. Export data, import on server (step 2 above)
5. `php tests/regression.php` on the server — must be ALL GREEN
6. PayPal sandbox order end-to-end, then switch `PAYPAL_MODE=live`
7. Real OXXO voucher test (small amount), confirm manually in admin
8. DNS cutover

## 7. Post-launch monitoring
- Uptime check on `/index.php` (UptimeRobot or similar)
- Watch `email_queue` for `failed` rows the first days
- `admin/audit-log.php` for anything unexpected
