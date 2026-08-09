# VentDepot Marketplace — Build Roadmap

Status legend: ✅ done · 🟡 in progress · ⬜ planned

This roadmap turns the current codebase (rich back-office, thin transaction spine)
into a working multi-vendor marketplace. Phases are ordered by dependency — later
phases assume earlier ones are complete.

---

## Phase 0 — Schema truth & safety  ⬜ (partially ✅)

The `migrations/` folder is **stale** and does not describe the live database.
The live DB (68 tables) is far richer and the application code targets it, not the
migrations. Before anything else, the schema must become the single source of truth.

- ⬜ Dump the live schema and regenerate `migrations/` from it (`mysqldump --no-data`).
- ⬜ Delete or archive the misleading `001`–`006` migrations once regenerated.
- ✅ `.env`-driven DB config, CSRF, uploads, secrets (completed in the hardening pass).
- ⬜ Introduce a real migration runner (phinx or a simple versioned runner) so future
  schema changes are tracked instead of applied by ad-hoc `create_*.php` scripts.

---

## Phase 1 — Connect the core: inventory ↔ payment ↔ shipping ↔ accounting  🟡

**Goal:** when an order is paid, stock, the general ledger, and shipping all update
atomically. This is the backbone the user asked for.

- ✅ `services/AccountingService.php` — double-entry posting into `general_ledger`
  against the existing `chart_of_accounts`.
- ✅ `services/InventoryService.php` — commit sale: write `inventory_movements`
  (out / sale_order), sync `product_inventory`, compute COGS from costed inventory.
- ✅ `services/ShippingService.php` — create a `shipments` record for a paid order.
- ✅ `services/OrderFulfillmentOrchestrator.php` — the connective tissue; on payment
  completion it runs inventory commit → accounting journal → shipment, idempotently.
- ✅ `migrations/010_marketplace_integration.sql` — adds `products.cost_price`,
  merchant-payable / commission-revenue accounts, and idempotency flags on `orders`.
- ✅ Wired the orchestrator into `PaymentGateway::processPayment` success path.
- ⬜ Backfill: run a one-off to post ledger entries for historical paid orders.

**Accounting model implemented (retail / merchant-of-record view):**
- On sale: `Dr Cash` (total) · `Cr Sales Revenue` (subtotal+shipping) · `Cr Sales Tax Payable` (tax)
- COGS:     `Dr Cost of Goods Sold` · `Cr Inventory` (sum of item cost)

---

## Phase 2 — Real payments & seller payouts  ⬜

Payments are currently simulated (no `\Stripe\` SDK usage despite the dependency).

- ⬜ Integrate the real Stripe SDK (and/or PayPal) behind the existing
  `PaymentGateway` interface; keep `mock` for non-production only (already gated).
- ⬜ **Stripe Connect** (or equivalent) for split payments so each seller is paid
  their share automatically; platform keeps commission.
- ⬜ Create `merchant_payouts` + wire `MerchantPayoutSystem` to run from
  `merchant_commissions` (status `pending_payout` → `paid_out`), posting
  `Dr Merchant Payable / Cr Cash` to the ledger.
- ⬜ Webhook reconciliation: `webhooks/payment-webhook.php` already verifies
  signatures — connect it to order/ledger state.

---

## Phase 3 — Escrow & buyer protection  ⬜

`EscrowSystem` exists but targets the **stale** schema (`orders.total_amount`,
`customer_id`, `escrow_id`, status `paid`/`in_escrow`) — none present in the live
`orders` table. It must be reconciled before use.

- ⬜ Create `escrow_transactions`, `escrow_logs`, `escrow_disputes` tables.
- ⬜ Rewrite `EscrowSystem` to the live schema (`orders.total`, `user_id`), add the
  `in_escrow`/`refunded` statuses and an `escrow_id` column.
- ⬜ Hook escrow into the orchestrator (create on payment, release on delivery
  confirmation, dispute path → refund + ledger reversal).

---

## Phase 4 — Multi-vendor orders  ⬜

Today `OrderProcessor` assigns the whole order to the first item's merchant.

- ⬜ Add `order_items.merchant_id` (populated at creation).
- ⬜ Split a multi-seller cart into per-seller sub-orders / shipments, each with its
  own fulfillment status, commission and payout.
- ⬜ Per-merchant order views in the merchant dashboard.

---

## Phase 4.5 — Stock replenishment (supplier receiving) + expiry  ✅

Refilling stock is a supplier receipt that flows into inventory and accounting.

- ✅ `migrations/012_stock_replenishment.sql` — `inventory_batches` (costed lots
  with `expiry_date` + `quantity_remaining`).
- ✅ `services/StockReplenishmentService.php` — `receiveStock()` (movement +
  costed lot + weighted-average `product_inventory` + catalog stock +
  `Dr Inventory / Cr Accounts Payable` + AP sub-ledger under the supplier);
  `receivePurchaseOrder()` (receive against a PO and advance its status);
  `consumeFEFO()` first-expiry-first-out draw-down.
- ✅ `InventoryService::commitSaleForOrder()` now draws lots down FEFO on sale.
- ✅ Admin UI `admin/receive-stock.php` (supplier-connected refill form) and
  `admin/inventory-batches.php` (expiry + product description + inventory table).

---

## Phase 5 — Persistent cart, shipping, tax, promotions  ⬜

- ⬜ Move cart from `$_SESSION['cart']` to the `product_inventory`-aware DB cart with
  stock reservation (tables exist; wire them).
- ⬜ Real shipping quotes via `shipping_rate_rules` / `shipping_zones` /
  `product_dimensions` instead of the hardcoded `$10 + $5/item`.
- ⬜ Real tax via `TaxManager` / `tax_rules` instead of the flat 8%.
- ⬜ Wire the existing `product_discounts` / `product_promotions` tables into checkout
  (coupon codes, `orders.discount_amount`).

---

## Phase 6 — Marketplace experience  🟡

- ✅ Returns/RMA → refund + ledger reversal + stock `return` movement.
  - `migrations/011_returns_rma.sql` (`return_requests`, `return_request_items`, `payment_refunds`).
  - `services/ReturnService.php` — create request, admin approve/reject, and
    complete (refund + restock + balanced ledger reversal), all idempotent.
  - `InventoryService::restockReturn()` reuses the original sale cost basis.
  - Customer UI `request-return.php`; admin UI `admin/returns.php`;
    "Request Return" link added to `orders.php`.
- ⬜ Wishlist, product variants/options wired into product & cart pages.
- ⬜ Verified-purchase gating on `reviews`; seller rating into search ranking.
- ⬜ Search facets/filters/ranking; related-product recommendations.
- ⬜ Buyer↔seller messaging surfaced in the buy flow.
- ⬜ Transactional email delivery — `NotificationSystem` queues to `email_queue`
  but nothing sends it; add a PHPMailer-based queue worker (dependency already present).

---

## How to run Phase 1

```bash
# 1. Apply the integration migration (MariaDB supports IF NOT EXISTS on ALTER)
mysql -u root ventdepot < migrations/010_marketplace_integration.sql

# 2. (recommended) set product cost prices so COGS is accurate
#    UPDATE products SET cost_price = ... ;  or rely on product_inventory.average_cost

# 3. Place a test order through checkout; then verify:
#    - inventory_movements has an 'out' / 'sale_order' row for the order
#    - general_ledger has balanced debit/credit rows (reference_type='order')
#    - shipments has a 'created' row for the order
```
