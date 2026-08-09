# Go-Live Checklist

Tick these in order. Anything failing = stop and fix before continuing.

## T-7 days — dry run
- [ ] `bash deploy/01-provision-server.sh` on a fresh Linode (save the DB password)
- [ ] `SERVER=root@IP WITH_DB=1 WITH_IMAGES=1 bash deploy/02-deploy.sh`
- [ ] `.env` filled from `deploy/env.production.template` (PayPal **sandbox** first)
- [ ] `certbot --apache -d ventdepot.com -d www.ventdepot.com` → HTTPS green
- [ ] `sudo -u www-data crontab deploy/crontab.txt` → `crontab -l` shows 6 jobs
- [ ] `php tests/regression.php` on the server → **ALL GREEN**
- [ ] Smoke test: browse → add to cart → register → checkout → PayPal sandbox → order confirmation email arrives
- [ ] Smoke test: OXXO voucher prints, confirm it in admin → order completes
- [ ] Admin: executive dashboard, warehouse 3D, accounting statements all load

## T-1 day — real data
- [ ] `php scripts/backup-db.php` (local safety copy)
- [ ] `php scripts/clean-demo-data.php --dry-run` → review the counts
- [ ] `php scripts/clean-demo-data.php --confirm`
- [ ] Post opening balances: Dr Cash + Dr Inventory / Cr Owner Equity (real figures)
- [ ] Re-deploy the cleaned DB: `WITH_DB=1 bash deploy/02-deploy.sh`
- [ ] Change every seeded password (admin + staff); temp password `Ventdepot#2026` must be gone
- [ ] Marketing: set the homepage jumbotron + theme for launch

## Launch day
- [ ] `PAYPAL_MODE=live` + real PayPal credentials in `.env`
- [ ] One **real** low-value order end-to-end (card via PayPal) → refund it
- [ ] Point PayPal webhooks (both apps) at `https://ventdepot.com/api/paypal-webhook.php`, event `PAYMENT.CAPTURE.COMPLETED`
- [ ] DNS A record → Linode IP (TTL 300 beforehand so you can roll back fast)
- [ ] Watch `/var/log/ventdepot/apache-error.log` and `email_queue` for the first hour

## Week 1 monitoring
- [ ] Uptime monitor on `https://ventdepot.com` (UptimeRobot free tier)
- [ ] Daily: `SELECT COUNT(*) FROM email_queue WHERE status='failed'` → should stay 0
- [ ] Daily: admin → Audit Log for anything unexpected
- [ ] Confirm the nightly backup file appears in `/backups/`
- [ ] `php tests/regression.php` after any change

## Rollback (if needed)
1. DNS back to the old host (TTL 300 = ~5 min)
2. `gunzip -c /backups/ventdepot-YYYY-MM-DD.sql.gz | mysql ventdepot`
3. Investigate on the server with the site pointed away
