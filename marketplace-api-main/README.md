# Marketplace API

Multi-vendor e-commerce backend (Laravel). See the SRS for full requirements, schema, and phased build plan.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Publish spatie/laravel-permission's migration + config (not auto-loaded like Sanctum's)
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

php artisan migrate
php artisan db:seed   # creates roles + an admin@marketplace.test account
```

`.env` — set `DB_CONNECTION` etc. to a real database for local dev (sqlite is fine to start: `DB_CONNECTION=sqlite`, `touch database/database.sqlite`).

## Tests

```bash
php artisan test
```

## Phase 1 — Auth & Roles (done)

- Sanctum token auth: `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`
- Roles via `spatie/laravel-permission`: `admin`, `vendor`, `customer`. Every new user starts as `customer`.
- Vendor onboarding: `POST /api/vendor/apply` (idempotent — requires `Idempotency-Key` header), `GET /api/vendor/me`
- Admin moderation: `GET /api/admin/vendors`, `POST /api/admin/vendors/{vendor}/approve`, `POST /api/admin/vendors/{vendor}/suspend` — the `vendor` role is granted only on approval, not on application.
- Generic `idempotency` middleware (`app/Http/Middleware/EnsureIdempotency.php`) backed by the `idempotency_keys` table — reused unchanged from Phase 4 (checkout) onward.
- `VendorPolicy` scopes a vendor's own profile to themselves; admin routes are gated separately via `role:admin` middleware.

### Trying it locally

```bash
# Register
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Doda","email":"doda@example.com","password":"password123","password_confirmation":"password123"}'

# Apply as vendor (use the token from register/login)
curl -X POST http://localhost:8000/api/vendor/apply \
  -H "Authorization: Bearer <token>" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{"store_name":"Doda Store"}'

# Approve as admin (login as admin@marketplace.test, then:)
curl -X POST http://localhost:8000/api/admin/vendors/1/approve \
  -H "Authorization: Bearer <admin_token>"
```

## Vendor analytics (powers the Streamlit dashboard)

`GET /api/vendor/analytics/dataset` — auth required, `role:vendor`, store must
be **approved**. Returns that vendor's analytics tables in one payload:
`products`, `customers`, `orders`, `order_items`, `reviews`, `events`.

The vendor is resolved from the bearer token, never from a request parameter,
so a vendor cannot ask for anyone else's data.

Query parameters:

| Param | Meaning |
|---|---|
| `since`, `until` | ISO dates; filter orders, reviews and events by date |
| `tables[]` | Restrict the payload, e.g. `?tables[]=products&tables[]=orders` |

```bash
curl "http://localhost:8000/api/vendor/analytics/dataset?since=2026-01-01" \
  -H "Authorization: Bearer <vendor_token>" -H "Accept: application/json"
```

Two things the queries do deliberately (`app/Services/VendorAnalyticsService.php`):

- **`orders.total_amount` is the vendor's share, not the basket total.** A
  marketplace order can span several vendors, so it is recomputed as
  `sum(order_items.subtotal)` for that vendor. Returning `orders.total` would
  show a vendor other people's revenue.
- **Column names match the dashboard's contract exactly** (`product_id`,
  `item_total`, `order_date`, ...), and `orders`/`order_items` carry no extra
  columns — the dashboard merges those frames together, and any duplicate
  column name would silently become a suffixed `_x`/`_y` pair.

`products.brand` is the vendor's store name (this schema has no brand column)
and `products.rating` is derived from reviews, null until a product has one.

### Behavioural events

The `events` table records the `view → cart → purchase` funnel. Events are
written best-effort by `App\Services\EventRecorder` from `ProductController@show`,
`CartController@storeItem` and `CheckoutController@store` — a failure there is
logged and swallowed, never breaking a customer's request. Clients may pass an
`X-Session-Id` header to group guest activity.

### Demo trading history

The default seeder creates only a handful of orders, all dated today, which
isn't enough for forecasting or segmentation. For a presentable dashboard:

```bash
php artisan db:seed --class="Database\Seeders\AnalyticsDemoSeeder"
```

That generates ~60 customers and twelve months of orders, reviews and funnel
events with a mild growth trend and a Q4 bump. It is intentionally **not**
called by `DatabaseSeeder`, so the test suite's fixtures stay small.

## Presentation-ready demo catalog

The database seeder now creates a curated marketplace catalog using the same 10 categories found in `products.csv`:

- Clothing
- Toys
- Pet Supplies
- Beauty
- Electronics
- Home & Kitchen
- Books
- Automotive
- Groceries
- Sports

It seeds 30 realistic products across Vendor A and Vendor B and stores local product artwork under `database/seeders/assets/products/` so the storefront has working images immediately after a fresh seed.

After a fresh database reset:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
```

The demo images are local seed fixtures for presentation/testing. Real vendor uploads continue to use the existing product-image upload flow.
