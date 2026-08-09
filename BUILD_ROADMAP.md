# VentDepot — Build Roadmap (to launch)

Ordered plan for the remaining recommendations. Each phase is independently
shippable; **Linode migration is deliberately last**. Companion docs:
`PROJECT_STATUS.md` (what's done), `PROJECT_MAP.md` (architecture).

Legend: ✅ done · 🔜 next up · ⬜ planned

---

## Phase 0 — Payments foundation ✅ (done)
- ✅ PayPal Orders v2, server-authoritative (server creates order, captures, verifies amount)
- ✅ **Dual-account routing**: shipping country MX → PayPal Mexico, everyone else → PayPal USA
  (`orders.shipping_country` + checkout country selector + per-order SDK client-id)
- ✅ Env keys: `PAYPAL_US_CLIENT_ID/SECRET`, `PAYPAL_MX_CLIENT_ID/SECRET`,
  `PAYPAL_MX_CURRENCY` (set `MXN` to charge pesos), `PAYPAL_MODE`
- Remaining inside this phase (small): PayPal webhook reconciliation hardening;
  test both accounts in sandbox once real sandbox keys are added.

## Phase 1 — Admin usability ✅
1. ✅ **Unified admin shell** — `admin/nav.php` grouped top-nav (Catalog, Inventory,
   Purchasing, Orders, Sellers, Finance, Site) injected into 79 admin pages;
   active-group highlight, print-hidden, JS-free dropdowns. Tailwind rebuilt.
2. ✅ **Admin audit log** — `audit_log` table (migration 029) + `AuditLog::record()`
   helper wired into price changes, bulk price, seller payouts, credibility
   recompute, write-off approve/reject, PO approve/pay. Viewer `admin/audit-log.php`
   (filter by action/user/date, color-coded, CSV export, append-only).
3. ✅ **Role granularity** — `users.staff_scope` (migration 030; 'all' default =
   full admin, or finance/warehouse/catalog/purchasing/orders/sellers). `requireScope()`
   gates 27 section pages; nav hides out-of-scope groups for scoped staff.

## Phase 2 — Customer conversion & trust ✅
1. ✅ **Guest cart + merge on login** — guests could already cart (session);
   added `cart_items` table (migration 031) + `CartService`: merge on login
   (qty = max, never doubles), mirror on every cart API mutation, clear on
   order. Survives browser close / device switch.
2. ✅ **Verified-purchase reviews** — created the missing `product_reviews` /
   `review_votes` tables + `products.average_rating/total_reviews` (migration
   031); existing `ProductReviews` class self-activated (delivered-order gate,
   verified badge, one review per buyer). Ratings (★ + count) now on
   browse/search cards via SearchService.
3. ✅ **"Buy again"** — `buy-again.php` (whole order or single item, stock-capped,
   persists to DB cart) with buttons on orders list + order details; **order timeline**
   already existed, added live **shipment tracking events** (per-seller) to order details.
4. ✅ **Wishlist + back-in-stock** — `wishlist_items` (migration 033), `api/wishlist.php`
   toggle (♥ on product page wired), `wishlist.php` page, nav link, and
   `cron/backinstock-worker.php`: emails once per wish when a stock-in movement
   follows the wish (idempotent).
5. ✅ **Promo pricing in the money path** — OrderProcessor, checkout totals, cart page,
   and cart API all charge/display `effectivePrice` (timed discounts, bulk tiers, promos)
   with SALE badge + strike-through in the cart. Verified: 20%-off product ordered at
   the sale price end-to-end.

## Phase 3 — Frontend polish
1. ✅ **Legacy product images connected** — junction `uploads/legacy` →
   C:\Usersarid\photogallery (1.47M files indexed); 1,847/2,745 products (67%)
   now show their real photo; 483 repointed to subfolder copies by filename index;
   placeholder.jpg created (was a 404). Remaining 898 filenames not in this folder
   (likely the separate `imagenes/` folder). For Linode: rsync the folder instead
   of the junction. Later: generate thumbnails (legacy photos are up to 2000px).
2. ✅ **Homepage on real data** — replaced the fake "ShopNow" template: hero w/ live
   product count + search, top categories (tree, live counts), 🔥 On sale (active
   discounts, SALE badges), best sellers (by units sold), featured, new arrivals,
   become-a-seller CTA, real footer. Fixed a real bug found on the way: PricingService
   used the PHP clock while queries compare against MySQL NOW() (8h apart) — new
   discounts were invisible for hours; now everything uses the DB clock.
3. ✅ **Category mega-menu + breadcrumbs** — "Categories" button in the shared nav
   opens a panel of the top 10 categories (live counts) with their busiest
   subcategories → faceted browse; `includes/breadcrumbs.php` (Home / parent /
   child / product) on browse + product pages (replaced the dead href="#" crumb);
   browse.php now uses the shared nav; nav search points at faceted browse.
4. ✅ **Search speed (migration 043)** — FULLTEXT(name,description,sku) index:
   keyword search switched from LIKE full-scan to indexed MATCH..AGAINST boolean
   prefix (26× faster, scales with catalog growth); automatic LIKE fallback when
   FULLTEXT finds nothing (substring edge cases); price/inventory filter indexes.
   Category "containerization" achieved logically via the category_id index.
5. Mobile audit pass across storefront pages.

## Phase 4 — Seller experience ✅
1. ✅ **Seller earnings dashboard** — `merchant/earnings.php`: gross/commission/net/
   pending/paid-out cards, monthly net bar chart, tier standing (score + the fees the
   seller pays), per-order commission lines w/ paid/pending badges, payout history.
   Linked from the seller dashboard menu + seller top-nav.
2. ✅ **Seller notifications** — `SellerNotificationService` queued via the standard
   email pipeline: new-order (fires in MultiVendorService.splitOrder for marketplace
   sellers, with net earnings), payout-sent (fires in runPayout after commit),
   tier-change (fires in credibility score() when the tier moves, with new fee).
   All best-effort — a mail failure never breaks the money flow.
3. ✅ **Store-builder polish** — real image UPLOAD for jumbotron background + new
   per-store BANNER (wide image + optional click-through link, migration 034):
   MIME-validated (Security::validateFileUpload), server-generated filenames under
   uploads/stores/{seller}/, uploads/.htaccess blocks script execution; banner
   renders on the public store (auto-inserted after hero for older stores).

## Phase 5 — Supplier & procurement automation
1. ✅ **Auto-draft POs from reorder alerts** — `cron/auto-po-worker.php` (nightly):
   groups alert items by supplier per location, drafts ONE pending-approval PO each
   (qty = suggested cover), audit-logged; idempotent because drafted qty counts as
   on-order in the alert math. Verified: low-stock scenario drafted qty-58 PO, second
   run drafted nothing.
2. ✅ **Supplier scorecard** — `SupplierScorecardService` + `admin/supplier-scorecard.php`:
   on-time % and avg delay from received POs (promised vs actual), mapped to a 0–5
   rating persisted in suppliers.delivery_rating; unscored suppliers listed for context.
3. (Later) supplier portal: confirm POs / ship dates themselves.

## Phase 6 — Shipping reality
1. ✅ **Own shipping calculation (DHL-style + Google Maps distance)** —
   `ShippingCalculatorService`: chargeable weight = max(actual, L×W×H÷5000) from
   product_dimensions; `DistanceService`: Google Distance Matrix (GOOGLE_MAPS_API_KEY,
   cached per route, per-country estimate fallback) from SHIPPING_ORIGIN_ADDRESS;
   price = base + $/kg + $/km + fuel% from OUR editable tariff (7 zone rules seeded,
   MX free > $150). Wired into OrderProcessor + checkout estimate; rate editor +
   live tester at `admin/shipping-rates.php`. Envia to provide labels later — pricing
   stays ours.
2. "Order shipped" email with tracking link (email pipeline).
3. Carrier API (EasyPost/Shippo → FedEx/UPS/DHL) for labels + live tracking.

## Phase 6.5 — HR module ✅ (added by request)
- ✅ **Employees** (salary/hourly, departments, withholding %, terminate) — `admin/hr-dashboard.php`
- ✅ **Hiring pipeline**: openings → applicants → interview → offer → hire (auto-creates employee)
- ✅ **Overtime**: log → approve (×1.5/×2) → auto-paid in the next payroll run
- ✅ **Leave**: vacation/sick/personal/unpaid, weekday counting, yearly balance enforcement,
  unpaid leave deducts pay — `admin/hr-leave.php`
- ✅ **Payroll**: draft → approve → pay per period; posts Dr 6000 Payroll Expense /
  Cr 2300 Payroll Taxes Payable / Cr 1000 Cash — `admin/hr-payroll.php` + `HRService` (migration 032)
- ✅ HR nav group + `hr` staff scope; hire/terminate/approve/pay all audit-logged
- Later: payslip PDFs, employee self-service portal, remit 2300 tax balance

## Phase 6.75 — Internal team chat ✅ (added by request)
- ✅ `admin/chat.php` + `admin/api/chat.php` (migration 036): group channels
  (auto-join all staff, # General default, create new), 1:1 direct messages
  (unique per pair), unread badges via per-member read cursors, 3s polling —
  no extra server needed on LAMP. Staff-only (admin role), CSRF-protected,
  XSS-safe rendering.
- ✅ Slack-style profiles: nickname + avatar upload (migration 037, uploads/avatars,
  MIME-validated); department channels seeded from HR departments (# Marketing,
  # Engineering, # Sales, # Warehouse, # Finance); nick + avatar shown on every
  message and in the DM directory. Later: attach files, @mentions, employee
  (non-admin) logins via HR employees.user_id.

## Phase 7 — Engineering / Type-2 (custom work) ✅
0. ✅ **PM Board (Kanban + Gantt)** — `admin/engineering-board.php` + API (migration 038)
   over the existing quotes/engineering_tasks schema: TICKET kanban (submitted→
   reviewed→assigned→in progress→completed, drag to move, per-ticket task counts +
   avg progress bar), TASK kanban (Backlog→To Do→In Progress→Review→Done, SortableJS
   drag persisted, inline reassign + progress slider, priority chips, avatars),
   CSS GANTT from the same tasks (status colors, progress fill, today marker),
   quick-create ticket (optionally from an order #) + task; all moves audit-logged;
   Engineering nav group + scope.
1. ✅ **Work order BOM** — `WorkOrderService.addMaterial`: consumes stock (movement
   type `work_order`), posts Dr 5000 COGS / Cr 1200 Inventory at cost, rolls into
   quotes.material_cost. `admin/work-order.php` (💲 from ticket cards): BOM table +
   consume-by-SKU, costing card.
2. ✅ **Quote pricing** — labor = task actual_hours × hourly rate; final = (materials
   + labor + overhead) × (1 + markup%); editable rate/markup/overhead, frozen on quote.
3. ✅ **Invoice → A/R → payment** — Dr 1100 A/R / Cr 4400 Engineering Revenue (new
   account), then Dr 1000 Cash / Cr 1100; double-invoice + double-payment guarded;
   audit-logged. Verified: $702.03 flow, A/R back to $0, all journals net to zero.

## Phase 7.5 — Marketing department ✅ (added by request)
- ✅ **Homepage control** (migration 040 + SiteSettings): jumbotron headline/subtext/
  CTA/decorative image, promo popup + coupon — all live-editable; **seasonal themes**
  (default / 🎄 Xmas red / 🍀 St. Patrick green / 💀 Día de Muertos purple w/ Catrina
  image slot / ☀️ Summer) applied via gradient + accent.
- ✅ **Tracking tags**: GA4/Google Tag + Meta Pixel + custom head HTML injected sitewide.
- ✅ **Campaign generator**: channel + product + % → creates the live product discount
  (auto-applied price, so users "find it on the product mentioned"), optional promo
  code, UTM link + ready-to-post social text.
- ✅ **Promo codes at checkout** (PromoCodeService + orders.promo_code/discount_amount):
  sitewide / product-specific / **personal** (locked to one customer), max uses, dates;
  discount reduces taxable subtotal; usage counted; invalid/foreign codes rejected.
- ✅ **A/B testing**: 50/50 session split of the jumbotron headline, view + CTA click
  counters, stop-and-apply-winner.
- ✅ **Newsletter**: segments (loyal 2+ orders / recent 90d / all) via the email queue.
- ✅ **Customer tickets**: complaints/comments logged by marketing, replies, statuses.
- ✅ Marketing hub `admin/marketing.php`, nav group + `marketing` staff scope, audit-logged.

## Phase 8 — Hardening (pre-deploy) 🟡
1. ✅ **Migration runner** — `migrate.php` (run/--status/--baseline, schema_migrations
   tracking) + `migration/000_full_schema.sql` (115 tables, proven on a scratch DB).
   Fresh deploy = import 000 + --baseline; future changes = new NNN file + run.
2. ✅ **Demo-data wipe script** — `scripts/clean-demo-data.php` (--dry-run/--confirm):
   removes sample products, test orders, dev ledger/financials, demo HR/engineering,
   chat, queues; keeps catalog/categories/suppliers. Run on LAUNCH DAY only.
3. ✅ **Regression suite** — `tests/regression.php` (20 checks, ALL GREEN): ledger
   global+per-journal balance, orphan accounts, negative stock, discount math,
   DHL volumetric + free-shipping, personal promo codes, and a full self-cleaning
   order→OXXO-pay→orchestration round-trip. Run before every deploy.
4. ✅ **SMTP transport** (dependency-free SmtpMailer, SSL/STARTTLS) + **password reset**
   (hashed single-use 1h tokens, anti-enumeration, rate-limited) + **DEPLOY.md**
   (server setup, .env checklist, full crontab incl. nightly backup, launch-day order).
5. Data cleanups: 1 zero-price product, 80 "Uncategorized", uploaded-image dirs.

## Phase 9 — LINODE MIGRATION 🚀 (kit ready, awaiting server)
**Everything is scripted — see `deploy/README.md`.**
- ✅ `deploy/01-provision-server.sh` — one-shot Ubuntu setup (Apache/PHP/MariaDB/certbot,
  ufw, DB + read-only backup user, vhost, PHP tuning, MX timezone)
- ✅ `deploy/02-deploy.sh` — rsync code, optional DB dump+import (+auto baseline),
  optional images (thumbs + only catalog-referenced originals), ends with regression
- ✅ `deploy/preflight.php` — 34 readiness checks; currently READY (0 blockers)
- ✅ `deploy/crontab.txt` · `env.production.template` · `03-go-live-checklist.md` (T-7 → launch → rollback)
- ✅ Hardening: tool dirs (migration/scripts/tests/cron/backups) blocked from the web;
  APP_TIMEZONE + per-connection DB timezone so PHP and MySQL never disagree again

### Original outline
1. Provision Linode: LAMP (or LEMP) + MySQL, PHP 8.x, SSL (Let's Encrypt).
2. Deploy code; run the migration runner; import production data + image folders.
3. `.env` with live keys: DB, **both PayPal accounts (PAYPAL_MODE=live)**, SMTP.
4. Crontab entries; `.htaccess`/nginx hardening; firewall.
5. Smoke test: browse → cart → checkout → PayPal sandbox→live → order → email →
   seller sub-order → payout → ledger tie-out.
6. Go-live checklist: backups (DB + images), monitoring/uptime, error logging,
   final legacy-data delta sync, DNS cutover.

---

### Suggested pacing
- Phases 1–2 are the highest ROI per hour and unblock real user testing.
- Phase 3.1 (images) can run any time — it only needs the legacy folders.
- Phases 4–7 can interleave based on business urgency.
- Phase 8 → 9 run back-to-back as the launch sprint.
