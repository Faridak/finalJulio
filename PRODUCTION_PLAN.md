# VentDepot — Production Plan (Amazon / Mercado Libre / Alibaba parity)

Created 2026-10-12. Companion to `BUILD_ROADMAP.md` (what's built) and
`PROJECT_STATUS.md`. Branch with all preflight work: `release/production-preflight`.

**Already done (not in this plan):** messaging, 2FA, disputes, refund accounting,
object storage, security/deploy preflight, Composer cleanup (0 vulnerabilities).
**Done on the server by the owner:** off-server backups, SPF/DKIM/DMARC, uptime monitoring.

Size: **S** ≈ 1 session · **M** ≈ 2–3 sessions · **L** ≈ 4–6 sessions (a session = one focused build + test pass).
Every item ships with: a migration, regression checks in `tests/regression.php`,
a preflight check where it has config, and a Spanish + English UI.

---

## Principles (how we build all of it)

1. **No new Composer packages unless unavoidable.** Payments, invoicing, search and
   shipping APIs are plain REST over curl, the same as `ObjectStorage` and PayPal. This keeps
   Dependabot at zero and avoids the PHP-version lock-in we just removed.
2. **Money moves through one path.** New payment methods plug into `PaymentGateway` +
   `OrderFulfillmentOrchestrator`; refunds always go through `ReturnService`;
   everything posts balanced journals via `AccountingService`.
3. **Webhooks are verified and idempotent** (signature + "already processed" guard), and
   every money event is audit-logged.
4. **Mexico first.** Spanish default, MXN prices, CFDI-ready customer data, MX payment habits
   (cards with meses sin intereses, OXXO, SPEI).

---

## Milestone 1 — Take real money, legally (launch blockers)

| # | Item | Size | Needs from you |
|---|------|------|----------------|
| 1.1 | Legal pages + cookie consent | S | Lawyer review of the texts |
| 1.2 | Error monitoring | S | Sentry account (free tier) or pick another |
| 1.3 | Mercado Pago: cards + MSI + SPEI + OXXO | L | Mercado Pago business account, test + live keys |
| 1.4 | Spanish interface (storefront + seller) | L | Glossary review (trade terms) |
| 1.5 | Fraud & payout risk controls | M | Your risk appetite: hold period, limits |
| 1.6 | CFDI 4.0 + platform tax withholding | L | Facturama (or SW Sapien) account, CSD certificates, **accountant answers** |

### 1.1 Legal pages + cookie consent — S
- Pages: Aviso de Privacidad (integral + simplificado, LFPDPPP), Términos y Condiciones
  (buyers), Términos para Vendedores, Política de Devoluciones (reuse return-window rules),
  Política de Cookies. Footer links on every page; links in register + checkout.
- Cookie banner: essential-only by default; analytics/marketing tags (GA4, Meta Pixel from
  `admin/marketing.php`) load **only after consent**. Consent stored per visitor.
- Checkout and registration record acceptance (version + timestamp) in a `legal_acceptances` table.
- ARCO rights: "Download my data" and "Delete my account" requests in profile → admin queue.
- **Done when:** tags don't fire before consent; every order and account has an acceptance record.

### 1.2 Error monitoring — S
- Sentry via its HTTP envelope API (curl, no SDK) from a global error/exception handler in
  `config/database.php`; tag release (git sha), user id, URL. PII scrubbed.
- Alerts: new issue, error spike, and **any exception on checkout/payment/webhook paths**.
- Preflight check: DSN set in production.
- **Done when:** a forced test error appears in Sentry with stack trace, no customer PII.

### 1.3 Mercado Pago — L  *(recommended primary processor for Mexico)*
Why Mercado Pago over Stripe MX: one integration covers cards, **meses sin intereses**,
SPEI, OXXO and the MP wallet, with the checkout Mexican buyers already trust. Keep PayPal for
US buyers. Stripe stays simulated (remove from checkout).
- `services/MercadoPagoService.php` (REST, curl): create payment with Checkout API or Bricks
  (card form tokenizes in the browser; card data never touches our server: PCI SAQ-A).
- MSI: offer 3/6/9/12 months above configurable minimums; show "x MSI de $y" on product pages.
  Decide who absorbs the MSI fee (store, or pass it to the buyer).
- SPEI: CLABE reference shown at checkout; order stays `pending` until the webhook confirms.
- OXXO via MP replaces the manual OXXO confirmation flow (keep the current one as fallback).
- Webhook `api/mercadopago-webhook.php`: verify `x-signature` (HMAC), re-fetch the payment
  from MP (server-authoritative, like PayPal), idempotent, then run the orchestrator.
- Refunds: `ReturnService` → MP refund API (full/partial), recorded against the transaction.
- Fees: post MP fees to an expense account so payouts and margins are right.
- **Done when:** sandbox card, MSI, SPEI and OXXO orders each pay → orchestrate → refund, ledger
  balanced; replayed webhook is a no-op; forged webhook rejected.

### 1.4 Spanish interface — L
- `lang/es.php`, `lang/en.php` + `t('key')` helper; locale from user setting → cookie →
  `Accept-Language`; **default `es` for Mexico**. Language switch in the nav; `<html lang>` set.
- Order: storefront (56 pages) → seller center (15) → emails → admin (103, later, English OK at launch).
- Money/date formatting per locale (`$1,234.56 MXN`, `12 oct 2026`).
- Product content: add `name_es`/`description_es` (fallback to current text) + seller bulk upload columns.
- **Done when:** a Spanish-speaking tester completes browse → checkout → dispute without English text.

### 1.5 Fraud & payout risk controls — M
- Order risk score at checkout (rules, no ML yet): new account + high value, billing/shipping
  country mismatch, many cards/failed payments, velocity (orders/IP/hour), disposable emails,
  high-risk categories. Score ≥ threshold → `on_hold` + admin review queue.
- Use Mercado Pago's own antifraud result as an input.
- **Seller payouts:** pay only for sub-orders delivered + N days (default 7) with no open
  dispute; rolling reserve for new sellers (e.g., 10% for 30 days); payout account changes
  require 2FA + 48h cooling-off.
- **Done when:** a scripted risky order lands in review; payouts skip undelivered/disputed sales.

### 1.6 CFDI 4.0 + platform withholding — L  *(needs your accountant first)*
- Customer fiscal profile: RFC, razón social, régimen fiscal, CP fiscal, uso CFDI (profile +
  optional at checkout, "Necesito factura").
- `services/CfdiService.php` → Facturama API: **ingreso** CFDI per paid order on request;
  **factura global** (público en general) for the rest per period; **egreso** (credit
  note) on refunds; cancel with SAT reason codes. PDF + XML emailed and in "My orders".
- Marketplace: CFDI for the commission charged to each seller; seller monthly statement.
- **Platform withholding (LISR 113-A / LIVA 18-J):** withhold ISR/IVA from individual sellers'
  payouts at the rates your accountant confirms; post to liability accounts; monthly report export.
- Seller onboarding collects RFC + régimen (ties into 3.6 KYC).
- **Questions for the accountant:** who issues the buyer CFDI for marketplace items (seller or
  VentDepot as agent); withholding rates per seller regime; factura global periodicity.
- **Done when:** sandbox (Facturama test) issues ingreso, global, egreso and cancellation, and XML validates.

---

## Milestone 2 — Amazon / Mercado Libre shopping experience

| # | Item | Size |
|---|------|------|
| 2.1 | Product gallery + zoom + multi-image upload | S |
| 2.2 | Real variants (size / voltage / color) | M |
| 2.3 | Product questions & answers | M |
| 2.4 | Shipping notifications + carrier labels | M |
| 2.5 | Recommendations + recently viewed | M |
| 2.6 | SEO foundation | S |
| 2.7 | Typo-tolerant search (Meilisearch) | M |
| 2.8 | Mobile audit + installable web app (PWA) | M |
| 2.9 | Gift cards + store credit | M |

- **2.1 Gallery (S):** `product_images` already exists (image_path, sort_order, is_primary).
  Product page gallery with thumbnails, zoom, swipe on mobile; seller uploads up to 10 images
  (MIME-validated, pushed to object storage); thumbnails generated per image.
- **2.2 Variants (M):** current `product_variants` (name/value/price_modifier) can't express
  "size × voltage". New `product_options` → `product_option_values` → `product_skus`
  (own SKU, barcode, price, stock, image). Variant picker on the product page; cart and
  `order_items.variant_id` (already exists) carry the SKU; inventory and reservations per SKU.
- **2.3 Q&A (M):** `product_questions` / `product_answers`; buyers ask, the seller (or
  verified buyers) answer; moderation + PII/contact-info filter (block phone/email to keep
  sales on-platform); seller SLA shown; questions indexed for search and SEO.
- **2.4 Shipping (M):** emails via `EmailService` on sub-order status change: confirmed →
  shipped (tracking link) → out for delivery → delivered → "rate your purchase".
  **Envia.com** (or Skydropx) REST: rates at checkout as an alternative to own tariff,
  label PDF from the seller center, tracking webhooks → `tracking_events` (exists).
- **2.5 Recommendations (M):** "Frecuentemente comprados juntos" from co-purchase counts
  in `order_items` (nightly cron); "Quienes vieron esto también vieron" from a `product_views`
  log; recently viewed (cookie + DB for logged-in users); blend with existing accessories.
- **2.6 SEO (S):** `sitemap.xml` (products, categories, stores) + `robots.txt`; JSON-LD
  Product/Offer/AggregateRating/BreadcrumbList; canonical URLs; use `page_seo_metadata` (exists);
  slug URLs (`/p/extractor-industrial-12-1234`) via rewrite.
- **2.7 Search (M):** Meilisearch on the Linode box (or a small separate one); Spanish
  synonyms ("extractor/ventilador/inyector"), typo tolerance, facets; sync worker; automatic
  fallback to the current MySQL FULLTEXT if Meilisearch is down.
- **2.8 Mobile + PWA (M):** audit the top 15 pages at 375px (finish the pending roadmap item);
  `manifest.json`, service worker for an offline shell + "add to home screen"; web push later.
- **2.9 Gift cards + store credit (M):** `customer_wallet` ledger; refund-to-credit option in
  `ReturnService`; gift card codes (hashed); accounting as a liability (unearned revenue)
  until redeemed.

---

## Milestone 3 — Alibaba-style B2B

| # | Item | Size |
|---|------|------|
| 3.1 | Business accounts (company, RFC, multiple buyers) | M |
| 3.2 | Net-30 terms on credit | M |
| 3.3 | Minimum order quantity + order multiples | S |
| 3.4 | RFQ marketplace (buyers post, suppliers bid) | L |
| 3.5 | Sample orders | S |
| 3.6 | Verified suppliers (KYC) + badge | M |
| 3.7 | Multi-currency price display | S |

- **3.1 Business accounts (M):** `companies` + `company_members` (roles: admin, buyer,
  approver); spending limits + approval for orders above a threshold; company fiscal data
  feeds CFDI (1.6).
- **3.2 Net-30 (M):** `classes/CreditManager.php` exists but **5 of its 6 tables don't**
  (`customer_credit_limits`, `credit_applications`, `credit_risk_scores`, `collections`,
  `aging_reports`) → migration; credit application flow; "Pay on terms" at checkout posts to
  `accounts_receivable` (exists); reminders; overdue → collections; marketplace sellers are
  still paid on schedule (VentDepot carries the credit risk), so set limits conservatively.
- **3.3 MOQ (S):** `min_order_qty`, `order_multiple` on products/SKUs; enforced in cart API,
  checkout and `OrderProcessor`; shown next to the existing bulk price tiers.
- **3.4 RFQ (L):** `rfqs` (spec, qty, target price, deadline, attachments in private
  storage) → matched sellers notified → `rfq_bids` (price, lead time, MOQ, validity) → buyer
  compares and accepts → converts to an order at the agreed price. Kept separate from the
  engineering `quotes` workflow.
- **3.5 Samples (S):** per-product sample price + limit 1 per buyer/company; sample orders are
  flagged and their cost can be credited against a later bulk order.
- **3.6 KYC (M):** seller documents (RFC constancia, ID, comprobante de domicilio, bank
  statement for CLABE) uploaded to **private** storage (never the public bucket); admin
  review queue; "Proveedor verificado" badge; feeds the credibility tier and the payout
  reserve (1.5) and the withholding regime (1.6).
- **3.7 Currency display (S):** `currency_rates` + `classes/CurrencyConverter.php` exist;
  daily rate update from Banxico (FIX); show prices in MXN/USD per visitor; always charge in
  the processor's currency, labelled "approximate" otherwise.

---

## Milestone 4 — Premium marketplace (after launch, revenue + loyalty)

| # | Item | Size |
|---|------|------|
| 4.1 | Membership "VentDepot Pro" (free/fast shipping) | M |
| 4.2 | Sponsored products + seller ads | L |
| 4.3 | Loyalty points | M |
| 4.4 | Subscribe & save (filters, consumables) | M |
| 4.5 | Product comparison + rich content (video, spec tables) | M |
| 4.6 | Fulfilled by VentDepot | L |
| 4.7 | Performance: CDN, cache, load test | M |

- **4.1** Mercado Pago subscriptions (preapproval); member rules plug into
  `ShippingCalculatorService` (free shipping threshold / fast lane); member-only prices.
- **4.2** Seller ad campaigns (CPC, daily budget, keyword/category targeting), "Patrocinado"
  slots in search/browse/product pages, billing from seller balance, click-fraud filtering,
  reports. Biggest new revenue line after commission.
- **4.3** Points ledger (earn per $; burn at checkout), expiry, liability accounting; tie-in
  with membership.
- **4.4** Recurring orders with saved MP card tokens; reminder before each run; skip/cancel.
- **4.5** `product_attributes` for comparable spec tables; compare up to 4; video and
  "A+"-style content blocks for verified sellers.
- **4.6** Sellers ship inventory into the VentDepot warehouse (warehouse/slotting already
  built); storage + pick/pack fees (supplier billing exists); "Enviado por VentDepot" badge
  and faster delivery promise.
- **4.7** CDN in front of object storage; Redis for sessions/cache once traffic warrants it
  (install Predis ≥ 3.3 or the phpredis extension; code already falls back without it);
  k6 load test of browse/search/checkout; slow-query review.

---

## Legacy cleanup (do alongside Milestone 1)

- Remove or rebuild: `admin/homepage-manager.php` + `api/homepage/` (broken; homepage control
  lives in `admin/marketing.php`), `public/index.php` + `src/` framework (unused, now blocked),
  `analytics-cron.php` / `escrow-cron.php` (target tables that don't exist, now blocked),
  legacy root `deploy.sh/.ps1/.bat` (wrong target dir; superseded by `deploy/`).
- Decide the public REST API (`api/router.php`, off by default): finish it for the mobile app/
  partners, or delete it.
- Drop the stale `C:\Users\farid\Documents\finalJulio` clone to avoid editing the wrong copy.
- **MariaDB strict mode:** production runs the same non-strict `sql_mode` as development. Turn on
  `STRICT_TRANS_TABLES` after a full test pass on staging (the app never ran strict; e.g. writes to
  generated columns were silently ignored until 2026-10-19).

---

## Recommended order

1. **Now:** merge `release/production-preflight` → master, deploy, change admin passwords + 2FA.
2. **Milestone 1:** 1.1 legal + 1.2 monitoring (quick wins) → 1.3 Mercado Pago → 1.4 Spanish
   → 1.5 risk controls → 1.6 CFDI (in parallel once the accountant answers).
   **Soft launch** (invite-only sellers, real orders) after 1.1–1.5.
3. **Milestone 2:** 2.1 gallery → 2.4 shipping notifications → 2.2 variants → 2.6 SEO →
   2.3 Q&A → 2.5 recommendations → 2.7 search → 2.8 PWA → 2.9 gift cards.
4. **Milestone 3:** 3.3 MOQ → 3.6 KYC → 3.1 business accounts → 3.2 net-30 → 3.7 currency → 3.5 samples → 3.4 RFQ.
5. **Milestone 4:** 4.2 sponsored products → 4.1 membership → 4.6 FBV → the rest, by revenue impact.

## What I need from you to start

- [ ] Merge + deploy the release branch
- [ ] Mercado Pago business account (sandbox credentials first)
- [ ] Sentry account (or tell me the tool you prefer)
- [ ] Lawyer for the legal texts (I draft, they review)
- [ ] Accountant answers for 1.6 (questions listed above) + Facturama account
- [ ] Envia.com or Skydropx account (for 2.4)
- [ ] Decisions: MSI fee absorption, payout hold days, new-seller reserve %
