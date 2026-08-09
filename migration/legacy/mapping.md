# Legacy → Target Field Mapping (draft)

Source: `TVVendepot` (SQL Server, 288 tables). Target: `ventdepot` (MariaDB).
Legacy is a Spanish-language ColdFusion B2B commerce/quotation system.
`—` = needs a decision or a lookup.

## Scope triage
- **Migrate:** `Cliente`, `Domicilio`, `Producto`(+`ProdIdioma`+`ListaPrecios`+`saproductstock`),
  `PedidoM`/`PedidoD`, `CotizacionM`/`CotizacionD`, suppliers/`Proveedor`.
- **Skip (logs/temp/backups):** `pagetracking` (51M), `*bitacora*`, `*log`,
  `*_bkp`, `dupliate_cliente`, `*_tm`, `PedidoDomTemp`, `logging_Url_Hits`.

---

## Cliente → `users` + `user_profiles` + crosswalk
| Legacy (Cliente) | Target | Notes |
|---|---|---|
| `IdCliente` (varchar 6) | crosswalk → `users.id` (int) | keep map `legacy_id_cliente → new user_id` |
| `Email` | `users.email` | dedupe; some may collide/blank |
| `Password` (varchar 10) | **NOT migrated** | legacy plaintext/weak → force password reset on first login |
| `RazonSocial` | `user_profiles.company` / name | business name |
| `Paterno`,`Materno` | `user_profiles.last_name` | + `RazonSocial`/`Contacto` → first_name |
| `Tel1` | `user_profiles.phone` | |
| `Active` (bit) | `users.is_active` | |
| `RegFedCont`/`tax_id` | profile tax id | RFC (Mexican tax id) |
| `FechaRegistro` | `users.created_at` | |
| `PaisCountry` | address/profile country | |
| role | `users.role='customer'` | distributors (`IdDistribuidor`) may map to a role |

## Domicilio → `user_addresses`
Map recipient, street, city, state, postal, country, `IdCliente`→new user via crosswalk.

## Producto (+ joins) → `products`
Product data is **normalized** — assemble from several tables:
| Source | Target |
|---|---|
| `Producto.IdProducto` | crosswalk → `products.id` |
| `ProdIdioma` (name/description per language) | `products.name`, `products.description` (pick default language) |
| `ListaPrecios`/`ListaPreciosD` | `products.price` (choose the correct price list) |
| `Producto.IdImpuesto` | tax mapping |
| `Producto.Imagen` / `saproductphotop` | `products.image_url` (blob → file/URL) |
| `saproductstock` | `product_inventory.quantity_on_hand` |
| `Producto.IdProveedor` | supplier link |

## PedidoM/PedidoD → `orders` / `order_items`
| Legacy | Target |
|---|---|
| `PedidoM.IdPedido` | crosswalk → `orders.id` |
| `PedidoM.IdCliente` | `orders.user_id` (via crosswalk) |
| `PedidoM.Fecha` | `orders.created_at` |
| `PedidoM.Total` | `orders.total` |
| `PedidoM.Gastosenvio` | `orders.shipping_cost` |
| `PedidoM.Descuento` | discount |
| `PedidoM.FormaPago` | `orders.payment_method` |
| `PedidoM.IdStatus` | `orders.status` (status-code lookup needed) |
| `PedidoD` lines | `order_items` (product via crosswalk, qty, price) |

## Open decisions (blockers for build)
1. **Historical window** — migrate all 135k orders / 617k quotes, or an active window?
2. **Language** — legacy is multilingual (`ProdIdioma`); pick default (es/en) for `products`.
3. **Price list** — which `ListaPrecios` is the canonical retail price?
4. **Passwords** — confirm force-reset (recommended) vs. attempt hash reuse.
5. **Images/blobs** — extract `Imagen`/photo tables to files, or keep URLs?
6. **Distributors** — treat `IdDistribuidor` clients as a distinct role?
