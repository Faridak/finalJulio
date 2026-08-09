# Legacy Migration Plan — ColdFusion/SQL Server → PHP/MariaDB

**Source:** `TVVendepot` (SQL Server 2008-level, ~21 GB) — restored from
`Backup20251021.bak`. Legacy stack: ColdFusion + SQL Server.
**Target:** `ventdepot` (MariaDB) — this PHP application.
**Goal:** a seamless, verifiable production cutover.

---

## Strategy: staged ETL with reconciliation (not a raw dump)

The two schemas differ (engine, table/column names, int vs SQL Server types,
collation `SQL_Latin1_General_CP1_CI_AS`). A raw restore/dump won't work — we map
table-by-table, transform, load, then reconcile counts. Steps:

### Phase A — Restore & discover  🟡
- ✅ Restore `TVVendepot` into the local SQL Server instance.
- ⬜ Run `legacy/discover.sql` → dump tables, row counts, columns, keys.
- ⬜ Identify the entities we actually need (customers, products, orders, …) and
  ignore legacy cruft (logs, session tables, large binary/image blobs unless
  required).

### Phase B — Field mapping  ⬜
For each target table in `ventdepot`, document the source→target column map,
type coercions, default/derived values, and any lookups. Draft lives in
`legacy/mapping.md` (created after discovery). Key entities to map first:
`users`, `user_profiles`, `user_addresses`, `products`, `orders`, `order_items`,
`suppliers`, `product_inventory`.

### Phase C — Extract  ⬜
Export mapped source tables from SQL Server to UTF-8 CSV (or NDJSON) via `bcp` or
`sqlcmd`. Normalise encoding to `utf8mb4`, fix date formats, NULL handling.

### Phase D — Transform & load  ⬜
A PHP ETL runner (`migration/run_etl.php`) reads the extracts and inserts into
MariaDB with:
- deterministic ID mapping (legacy PK → new PK crosswalk tables) so foreign keys
  survive the move;
- idempotent upserts (safe to re-run);
- batching for the large tables.

### Phase E — Reconcile & validate  ⬜
- Row-count parity per table (source vs target).
- Spot-check financial totals (order sums, inventory quantities).
- Referential integrity checks (no orphaned order_items, etc.).
- A sample of records compared field-by-field.

### Phase F — Cutover rehearsal  ⬜
- Full dry-run into a throwaway copy of `ventdepot`, timed.
- Freeze window plan + rollback (keep the `.bak` and a pre-migration MariaDB dump).
- Delta/last-mile sync if the legacy system keeps running until go-live.

---

## Confirmed scope & decisions (products-first)
- **Scope:** ~3,418 products where `saproductstock.currentstock >= 10` (warehouse 1).
  Orders/customers/quotes are a later phase.
- **Language:** Spanish (`ProdIdioma.IdIdioma = 1`) primary, English (2) fallback.
- **Price:** `ListaPreciosD.IdListadPrecios = 1` ("VentDepot.com", active list).
- **Images — PRESERVE, never rename:**
  - Legacy files live under the old site `wwwroot/`: `imagenes/` and
    `photogallery/{original,small,medium,big,1200x1200px,...}`.
  - Primary image per product from `saproductphotop` (ordered by `idproductphoto`);
    ~80% of the slice has photos; fallback = `Producto.Imagen` slug / placeholder.
  - New schema stores the **relative path verbatim** (migration 013:
    `product_images.image_path`, `products.image_url`), and we mirror the legacy
    image folders 1:1 onto the new server so every URL resolves unchanged.
  - **Needed from source:** a copy of the `imagenes/` + `photogallery/` folders and
    the legacy base URL (e.g. `https://ventdepot.com/imagenes/...`).
- **Legacy passwords** (customers phase): `varchar(10)` plaintext → force reset.

Schema prepared: `migration/013_legacy_bridge_and_images.sql` (applied) adds
`products.legacy_id`, `products.legacy_image_slug`, and the `product_images` table.

## Safety
- The 21 GB `.bak` is the rollback source of record — keep it untouched.
- All loads run against a **copy** of `ventdepot` first; production load only
  after reconciliation passes.
