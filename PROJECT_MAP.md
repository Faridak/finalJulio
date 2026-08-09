# VentDepot — Project Map

How the system fits together: modules, key files, data flow, and the account model.
Stack: PHP (procedural + service classes) · MySQL/MariaDB · vanilla JS + Tailwind (CDN) · Three.js.

---

## Directory layout

```
finalJulio/
├── config/            env loader + PDO ($pdo), auth helpers (requireRole, isLoggedIn)
├── includes/          legacy helper classes (Security, InventoryManager, PaymentGateway…)
├── services/          NEW business logic (see below) — the real engine
├── admin/             admin UIs (dashboards, scan, PO, accounting, warehouse 2D/3D)
│   └── _legacy_backup/ original mock accounting pages (retired, kept for reference)
├── migration/         schema migrations 010–021 + seeds + legacy ETL (run_etl, seed_*)
│   └── legacy/         SQL Server extract (JSON) + mapping docs
├── api/               storefront + admin JSON endpoints
├── webhooks/          payment/general webhooks
└── (root)             storefront pages: index, product, cart, checkout, orders, returns…
```

---

## Services layer (the engine — `services/`)

| Service | Responsibility |
|---|---|
| `AccountingService` | Double-entry posting to `general_ledger` (balanced), account balances |
| `InventoryService` | Sale → movement out, product_inventory sync, FEFO, bin pick, COGS |
| `StockReplenishmentService` | Receiving: movement in + costed lot + accounting + auto-slot |
| `SlottingService` | Dimension/weight put-away, bin capacity math, pick FEFO |
| `ScanService` | Barcode receive / pick / count console backend |
| `StockAdjustmentService` | Reason-coded, costed, approval-gated shrinkage → ledger |
| `SkuBarcodeService` | Internal SKU + barcode generation, findByBarcode |
| `PricingService` | Effective price (timed/bulk/promo), bulk price update |
| `CategoryService` | Hierarchical categories, browse, breadcrumb |
| `RelationshipService` | Components / accessories / alternatives, forOrder() |
| `PurchaseOrderService` | Create PO (supplier SKU), approve → A/P, mark paid |
| `ReorderService` | Demand-based reorder point (lead + customs), on-order |
| `ReturnService` | RMA: refund + restock + ledger reversal |
| `BankReconciliationService` | Import statement, auto-match to ledger cash |
| `FinancialMetricsService` | P&L, balance sheet, cash flow, solvency, aging, tax, KPIs |
| `OrderFulfillmentOrchestrator` | On payment: inventory + accounting + shipping together |

---

## Core data flow

```
                         ┌──────────────── general_ledger (source of truth) ───────────────┐
                         │  every money event posts a balanced double-entry journal here    │
                         └──▲───────────▲───────────▲───────────▲────────────▲──────────────┘
                            │           │           │           │            │
   RECEIVE (PO/scan) ───────┘           │           │           │            │
   Dr Inventory / Cr A-P or In-Transit  │           │           │            │
                                        │           │           │            │
   SALE / SHIP ─────────────────────────┘           │           │            │
   Dr Cash / Cr Revenue+Tax ; Dr COGS / Cr Inventory│           │            │
   + inventory OUT + bin pick + shipment            │           │            │
                                                    │           │            │
   RETURN ──────────────────────────────────────────┘           │            │
   Dr Revenue+Tax / Cr Cash ; Dr Inventory / Cr COGS + restock  │            │
                                                                │            │
   SHRINKAGE (count/theft/damage) ──────────────────────────────┘            │
   Dr Shrinkage / Cr Inventory (approval-gated)                              │
                                                                             │
   PO PAY (bank transfer) ───────────────────────────────────────────────────┘
   Dr A-P / Cr Cash

   ↓ read side (never keyed by hand)
   FinancialMetricsService → P&L · Balance Sheet · Cash Flow · Solvency · Tax · KPIs
   BankReconciliationService → match bank statement lines to the cash ledger
```

---

## Module → key files

- **Inventory / warehouse**: `services/{Inventory,Slotting,Scan,StockAdjustment,StockReplenishment}Service.php`
  · `admin/{warehouse-map,warehouse-3d,scan,cycle-count-dashboard,receive-stock,inventory-batches}.php`
- **Catalog / pricing**: `services/{Pricing,Category,Relationship,SkuBarcode}Service.php`
  · `admin/catalog-dashboard.php` · `admin/print-item-tickets.php` · `admin/print-bin-labels.php`
- **Procurement PO**: `services/{PurchaseOrder,Reorder}Service.php`
  · `admin/{create-po,po-manage,reorder-alerts}.php` · `admin/api/po-item-lookup.php`
- **Accounting**: `services/{Accounting,FinancialMetrics,BankReconciliation}Service.php`
  · `admin/{accounting-hub,financial-statements,financial-kpis,tax-report,bank-reconciliation}.php`
- **Orders / returns**: `services/{OrderFulfillmentOrchestrator,Return}Service.php` · `checkout.php` · `returns.php`

---

## Chart of accounts (ledger backbone)

```
1000 Cash            1100 A/R          1200 Inventory      1250 Inventory In-Transit
2000 A/P             2100 Sales Tax    2200 Merchant Pay.  3000 Owner Equity
4000 Sales Rev.      4100 Commission   4200 Shipping Rev.  4300 Inv. Adj. Gain
5000 COGS            5100 Shipping Exp 5200 Shrinkage & Loss
```
Retained earnings = revenue − expense (all-time). Balance sheet: Assets = Liabilities + Equity.

---

## Migrations (apply in order)

`010` integration · `011` returns · `012` replenishment/batches · `013` legacy bridge + images ·
`014` sku/short-desc · `015` warehouse structure · `016` ownership/identity · `017` shrinkage ·
`018` cycle-count/supplier links · `019` procurement PO · `020` categories/relationships ·
`021` bank reconciliation · seeds: `seed_warehouse.php`, `run_etl.php`, `run_putaway.php`, `seed_financials.php`

---

## Two "PO" documents (don't confuse)

- **Type 1 — Procurement PO** → supplier → we pay → **Accounts Payable** (built ✅)
- **Type 2 — Custom Work Order → Quote** → customer → they pay → **Accounts Receivable** (TODO)
  Lives in the existing quote/engineering module, not a purchase order.
