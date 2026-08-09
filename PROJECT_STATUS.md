# VentDepot — Project Status & TODO

Marketplace for ventilation & electrical construction items (Home-Depot-style, B2B/B2C),
migrated from a legacy ColdFusion + SQL Server system to PHP + MySQL/MariaDB.

Legend: ✅ done & tested · 🟡 partial · ⬜ not started · ⚠️ needs decision/rework

---

## DONE (functionally built & tested)

### Security & foundation ✅
- ✅ `.env`-driven config (dependency-free loader), removed hardcoded DB creds
- ✅ CSRF re-enabled (login, register, quote, dashboards), rate limiting on login
- ✅ File-upload hardening (whitelist + safe rename), removed debug/backdoor scripts
- ✅ Normalized session role to `user_role`, `.htaccess` hardening

### Legacy data migration ✅
- ✅ Restored 21 GB SQL Server `.bak`; profiled 288 tables
- ✅ ETL of **3,413 products** → `products` (name, price, cost, category, weight, images)
  with UTF-8 fix, legacy-id crosswalk, dedup; image filenames preserved (never renamed)
- ✅ Schema reconciled: migrations 010–021 (integration, returns, warehouse, identity,
  ownership, categories, PO, bank rec)

### Inventory module ✅ (deep)
- ✅ Ledger-as-source-of-truth: `inventory_movements` in/out drives `product_inventory`
- ✅ Physical warehouse: zones → racks/aisles → bins (level × position), 1,068 dummy bins
- ✅ Dimension + weight-aware **slotting/put-away** (best-fit, ergonomics, zone fit)
- ✅ **2D map** + **3D (Three.js)** with partial-fill, capacity heatmap, weight alerts
- ✅ Barcode **scan console** (receive / pick / count) + `ScanService`
- ✅ FEFO batch/expiry tracking; sale ships → picks from bins
- ✅ **Shrinkage control**: reason-coded (theft/damage/loss), costed, ledger-posted,
  approval-gated write-offs + audit trail
- ✅ **Cycle counting**: `last_counted_at`, risk-weighted 🎲 pick, spot-count from 3D,
  cycle-count dashboard + approval screen
- ✅ Capacity math: "how many boxes fit" (min of volume vs weight limit)
- ✅ Stock replenishment (receiving) → inventory + costed lot + accounting + auto-slot

### Product identity & catalog ✅
- ✅ Internal SKU (`MXxxx-NNN`) + barcode generator (own products), `findByBarcode`
- ✅ Printable **item sales tickets** + **bin location labels** (QR + Code128)
- ✅ Ownership model: own vs marketplace; supplier billing (storage rent / subscription)
- ✅ **Categories** hierarchy (376 top / 2,046 sub, 3,420 linked) + `CategoryService`
- ✅ **Product relationships** (component/accessory/alternative) + `RelationshipService`
- ✅ **Pricing**: timed discounts, bulk qty tiers, promotions, bulk update (`PricingService`)
- ✅ **Catalog Management dashboard** (price + category + accessories per product)

### Procurement PO (Type 1) ✅
- ✅ Create PO by **supplier SKU** + barcode auto-fill; maps to our product on receipt
- ✅ **Reorder alerts**: demand × (lead + customs) + safety; stock-out risk flag
- ✅ Lifecycle: Approve → A/P + expected inventory → Pay (bank transfer) → Receive
  (In-Transit → on-hand), balanced accounting, PO management page
- ✅ Supplier completeness (customs days, bank details, delivery terms, insurance)

### Accounting & finance ✅
- ✅ Double-entry ledger (`general_ledger` + `chart_of_accounts`), auto-posted from ops
- ✅ **Financial statements**: P&L, Balance Sheet (balances), Cash Flow, Solvency, COGS
- ✅ **KPIs**: gross/net margin, DSO/DPO, A/R & A/P aging, current/quick ratio, shrinkage
- ✅ **Tax report**: sales tax + estimated federal, CSV export for gov filing
- ✅ **Bank reconciliation**: import bank/CC statement (CSV) → auto-match to ledger
- ✅ CSV export on every statement; PDF via print
- ✅ **Consolidated**: legacy mock dashboards redirect to live pages (one source of truth)

### Order lifecycle ✅
- ✅ Sale → Revenue + COGS + inventory out + bin pick + shipment + ledger (orchestrator)
- ✅ **Returns/RMA**: refund + restock + balanced ledger reversal (`ReturnService`)

---

### HR module ✅
- ✅ Employees + hiring pipeline (applicant → interview → offer → hired)
- ✅ Overtime (approval-gated, paid in payroll), Leave (balances enforced, unpaid deducts pay)
- ✅ Payroll runs draft→approve→pay, ledger-posted (6000/2300/1000), audit-logged

## TODO (not built / partial)

### Type 2 — Custom Work Order → Quote → A/R ⬜
- ⬜ Work order = bill of materials (materials + labor + engineering fee)
- ⬜ Consume materials as inventory `out` / reference `work_order` (real COGS)
- ⬜ Roll up to a customer **Quote** (uses existing `quotes` + engineering/sales dashboards)
- ⬜ Quote approved → Sales Order → **Accounts Receivable** (Dr A/R / Cr Revenue)

### Storefront (customer-facing) 🟡
- ✅ Product page shows real products + **"goes-with-it" (fundamental parts / accessories / material chooser)** + live effective pricing
- ✅ Purchase **confirmation email** + enforced **refund window**
- ✅ **Faceted search/discovery** (`browse.php` + `SearchService`): keyword + category-tree facets w/ counts, price/seller facets, in-stock/on-sale/featured, sort, pagination, effective pricing
- ✅ **Stock reservation on checkout** — holds deducted stock, commits on payment, auto-restores + cancels on abandonment (`ReservationService` + `cron/reservation-worker.php`). Oversell already prevented by OrderProcessor FOR UPDATE.
- ⬜ Persistent DB cart + guest cart merge
- ⬜ Wire pricing (timed/promo) into cart/checkout display

### Supplier/seller module 🟡
- ✅ **Customer PII redacted** in the seller order view (masked customer id + region-only ship-to; no email/name/phone/address)
- ✅ **Seller credibility rating** (SupplierCredibilityService) — score/tier from sales, refunds, ratings → adjusts subscription + insurance fee; admin `merchant-credibility.php`
- ✅ **Store builder**: supplier storefront (`store.php`) + builder (`merchant/store-builder.php`) — theme, jumbotron, promo popup+coupon, featured products, drag-and-drop ordering (SortableJS), publish
- ⬜ Store builder polish: full drag-drop section canvas, per-store banners/carousels, image upload

### Marketplace money loop 🟡
- ✅ **Commission split** on sale — marketplace (seller) lines credit Merchant Payable (seller net) + Commission Revenue (platform cut); own products stay full retail. Ownership-aware in the orchestrator.
- ✅ **Seller payouts** — `MarketplacePayoutService` + admin `merchant-payouts.php` (Dr Merchant Payable / Cr Cash), `merchant_commissions`→paid_out, `merchant_payouts` record.
- ✅ Multi-vendor **order splitting** — per-seller sub-orders (`merchant_suborders`), one shipment per seller, independent fulfilment status + parent roll-up (`MultiVendorService`); sellers update only their portion

### Payments (gateway) 🟡
- ✅ **PayPal (Orders v2) wired server-authoritatively** — server creates the order (amount from DB), buyer approves, server captures + verifies amount/currency; idempotent capture; demo (sb) mode until real keys in .env (`PAYPAL_CLIENT_ID`, `PAYPAL_SECRET`, `PAYPAL_MODE=live`). Fixed orders.status='paid' enum bug (now payment_status=paid + status=processing).
- ⬜ Stripe integration (still simulated); PayPal webhook reconciliation hardening
- ⚠️ Escrow: `EscrowSystem` targets stale schema — needs rewrite before use

### Accounting extras ⬜
- ⬜ EBITDA, inventory turnover / days-on-hand, journal drill-down
- ⬜ Cash-flow **forecast** (future), budget-vs-actual (needs a budget table)
- ⬜ Native `.xlsx` + server-side `.pdf` export (needs PhpSpreadsheet + Dompdf)
- ⬜ Gov portal **API** auto-submit (upload file works today)
- ⬜ Reference-level bank matching (invoice #, not just amount+date)

### Notifications 🟡
- ✅ Transactional **order-confirmation email** (EmailService + queue + worker `cron/email-worker.php`); wired into payment success, idempotent. Real send needs MAIL_* (SMTP) in .env.
- ⬜ Other emails (shipping update, refund, password reset) — same pipeline

### PRODUCTION HARDENING (do before Linode) 🟡
1. ✅ **Stock reservation** on checkout — abandonment recovery (migration 023 + ReservationService + cron)
2. ✅ **Vendored CDN assets locally** — `public/vendor/` (Tailwind built to 52 KB via
   `npx tailwindcss` scanning all PHP; Three.js, OrbitControls, JsBarcode, QRCode,
   Font Awesome + webfonts). 116 pages swapped to relative local paths. Offline-safe.
   Rebuild Tailwind after adding new classes: `npx tailwindcss -c tailwind.build.config.js -i tailwind.src.css -o public/vendor/css/tailwind.min.css --minify`
3. ⬜ **Consolidate migrations** into a tracked runner; regenerate base schema from live DB
4. ⬜ **Replace all dummy/seed data** with real (warehouse layout, product dimensions,
   supplier links, financial history)
5. ⬜ Automated tests (regression suite for the ledger + inventory)
6. ⬜ Provision Linode MySQL, deploy, smoke-test

### Data cleanups ⬜
- ⬜ 1 product with price 0, 80 "Uncategorized" products (from ETL)
- ⬜ Image files: copy legacy `imagenes/` + `photogallery/` folders; set one path prefix

---

## Notes
- All warehouse/financial figures currently run on **seed/dummy data** so features are
  demonstrable — replace with real data during hardening (item 4 above).
- Preview screenshots were taken via an isolated PHP server; admin pages require an
  authenticated admin session.
