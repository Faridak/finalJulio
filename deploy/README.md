# Deploying VentDepot to Linode

Four commands, in order. Full detail in `03-go-live-checklist.md`.

| Step | Where | Command |
|---|---|---|
| 0. Preflight | your PC | `php deploy/preflight.php` — must say READY |
| 1. Provision | Linode (once) | `ssh root@IP 'bash -s' < deploy/01-provision-server.sh` |
| 2. Configure | Linode | copy `env.production.template` → `/var/www/ventdepot/.env`, fill it |
| 3. Deploy | your PC | `SERVER=root@IP WITH_DB=1 WITH_IMAGES=1 bash deploy/02-deploy.sh` |
| 4. SSL + cron | Linode | `certbot --apache -d ventdepot.com` · `sudo -u www-data crontab deploy/crontab.txt` |
| 5. Verify | Linode | `php deploy/preflight.php && php tests/regression.php` |

## What each file does
- **01-provision-server.sh** — Apache/PHP/MariaDB/certbot, firewall, DB + backup user, vhost, PHP tuning, timezone. Prints the generated DB password (save it).
- **02-deploy.sh** — rsyncs code; `WITH_DB=1` also dumps and imports the database (then baselines migrations); `WITH_IMAGES=1` copies thumbnails plus **only the originals the catalog references** (~2,700 files, not the full 1.47M library). Ends by running the regression suite on the server.
- **preflight.php** — 34 checks: PHP version/extensions, PHP↔MySQL clock agreement, .env completeness, schema/migration state, catalog + ledger sanity, .htaccess guards, writable upload dirs. Run it before *and* after deploying.
- **crontab.txt** — the 6 scheduled jobs (email, reservations/OXXO expiry, back-in-stock, nightly auto-PO, nightly backup, log trim).
- **env.production.template** — every key with a one-line explanation.
- **03-go-live-checklist.md** — T-7 dry run → T-1 real data → launch day → week-1 monitoring → rollback.

## Sizing
A **Linode 2GB shared** ($12/mo) comfortably runs this. Storage: code ~50MB, DB ~15MB, thumbnails ~150MB, referenced originals ~2–4GB → the 50GB disk is plenty.

## Notes
- Deploy the DB **after** `scripts/clean-demo-data.php --confirm`, so production starts with clean books.
- `migration/000_full_schema.sql` is for **empty databases only** — the runner refuses to execute it (it contains DROP TABLE).
- Keep `PAYPAL_MODE=sandbox` until a full test order passes on the server.
