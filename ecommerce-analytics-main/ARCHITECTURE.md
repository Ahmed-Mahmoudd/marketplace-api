# Architecture

How the three applications in this project fit together.

## The pieces

| App | Stack | Repo / path | Dev port | Role |
|---|---|---|---|---|
| **Marketplace API** | Laravel 12, Sanctum, spatie/permission | `marketplace-api-main/` | 8001 | System of record. Owns the database, auth, and all authorisation. |
| **Storefront** | React 18, Vite, TanStack Query, Tailwind 4 | `marketplace-api-main/frontend/` | 5174 | What customers, vendors and admins use. |
| **Vendor analytics** | Python, Streamlit, pandas, Prophet, scikit-learn | `ecommerce/` | 8501 | Per-vendor analytics and the ML pages. |
| **Shopping assistant** | Python, FastAPI | `ecommerce/api/chat_api.py` | 8090 | HTTP front door for the chat agent in `ai/`. |

Ports are non-default because 5173 and 8000 were already taken on this machine.
See [Configuration](#configuration).

## The one rule everything follows

**The API is the only thing that decides who may see what.** Every client —
the storefront, the dashboard, the assistant — is untrusted. A vendor's identity
is always resolved from their bearer token server-side, never from a parameter
the client supplies. There is no vendor id in any analytics request for a client
to tamper with.

## Integration seams

There are four places these apps touch. Each is a deliberate contract.

### 1. Storefront → API

Plain REST over `VITE_API_URL`, Sanctum bearer token in `Authorization`,
token held in `localStorage`. An axios interceptor clears the token and fires
an `auth:unauthorized` event on any 401, so an expired session drops the user
back to login rather than failing silently.

CORS is restricted to the storefront's origin via `FRONTEND_URL` /
`FRONTEND_URL_ALT` in the API's `.env`, read by `config/cors.php`.

### 2. API → Vendor analytics

One endpoint feeds the whole dashboard:

```
GET /api/vendor/analytics/dataset?since=&until=&tables[]=
    auth:sanctum + role:vendor + store must be approved
```

It returns six tables — `products`, `customers`, `orders`, `order_items`,
`reviews`, `events` — scoped to the vendor behind the token.
Built by `app/Services/VendorAnalyticsService.php`; consumed by
`src/api_client.py`.

Two decisions in that service matter more than the rest:

**Revenue is the vendor's share, not the basket.** A marketplace order can span
several vendors, so `orders.total_amount` is recomputed as
`sum(order_items.subtotal)` *for that vendor*. Returning the raw `orders.total`
would show every vendor the whole basket — other people's money.

**Column names match the CSV loader exactly, with no extras.** The dashboard
merges `order_items` with `products` and `orders` on several pages. A duplicated
column name (`product_name`, `order_status`) would silently become an `_x`/`_y`
suffixed pair and break those charts, so `orders` and `order_items` carry only
the contracted columns.

Two schema gaps are filled rather than left null: `products.brand` is the
vendor's store name (there is no brand column), and `products.rating` is derived
as `AVG(reviews.rating)`, null until a product has a review.

### 3. Storefront → Vendor analytics (single sign-on)

The dashboard is embedded in the storefront's vendor area at `/vendor/analytics`
in an iframe. The vendor is signed in by a one-time code, never a shared token:

```
1. React      POST /api/auth/handoff            (bearer token)
              → { code, expires_in: 60 }
2. Browser    iframe src = :8501/?code=…&embedded=1&embed=true
3. Streamlit  POST /api/auth/handoff/redeem { code }
              → { token, user }      code marked spent
4. Streamlit  strips ?code= from the URL
```

Why a code and not the token: the value travels in a URL, and URLs land in
browser history, referrer headers and proxy logs. A code that dies in 60 seconds
and survives exactly one redemption is worth far less to whoever reads it later.

Hardening, all covered by `tests/Feature/Auth/HandoffTest.php`:

- Only a SHA-256 hash of the code is stored, so a leaked DB row isn't redeemable.
- Redemption runs in a transaction with `lockForUpdate` — a code cannot be spent
  twice, even under a race.
- The redeem endpoint is public by necessity (the caller has no token yet), so
  it is throttled to 10/minute.
- Failures are deliberately vague — wrong, spent and expired codes are
  indistinguishable to a caller.
- The dashboard takes its API URL from configuration, never the query string.
  Accepting one would let a crafted link post the vendor's code to an
  attacker-controlled host.

### 4. Storefront → Shopping assistant

The chat widget posts to a separate FastAPI service (`VITE_CHAT_API_URL`,
default `http://127.0.0.1:8090`) with no auth — shoppers can ask about the
catalog without signing in. Its own CORS allowlist is `CHAT_CORS_ORIGINS`.

The assistant's tools use the shared catalog here. In the *dashboard*, the same
tools are pointed at the signed-in vendor's catalog via `tools.set_catalog()`,
so a vendor gets answers about their own products.

## Multi-tenancy in the dashboard

The dashboard is single-tenant code serving many tenants, which creates one
non-obvious hazard.

**Streamlit does not hash arguments whose name starts with `_`.** The original
`get_forecast_model(_monthly_sales)` had *only* underscore arguments, so its
cache key was the function identity alone — vendor B would have been handed
vendor A's fitted model. Every cached loader and model in `dashboard/data.py`
now takes an explicit `tenant` key (`vendor:{id}` or `demo:csv`), and
`dashboard/auth.py` clears both caches on login, logout and handoff.

Relatedly, `models/sales_forecast_model.pkl` was trained on the sample CSVs, so
it is used in demo mode only; a vendor always gets a model fit on their own sales.

## Behavioural events

`events` is a new table recording the `view → cart → purchase` funnel, written by
`app/Services/EventRecorder.php` from three controllers:

| Event | Written by |
|---|---|
| `view` | `ProductController@show` |
| `cart` | `CartController@storeItem` |
| `purchase` | `CheckoutController@store` (one row per line item) |

`vendor_id` is denormalised onto each row so a funnel can be scoped to one
vendor without joining through products. Every write is best-effort: failures
are logged and swallowed, because analytics must never break a shopper's
checkout. Clients may send `X-Session-Id` to group guest activity.

## Two data sources, one contract

The dashboard runs in either mode, chosen at login:

- **Live** — the API, scoped to the signed-in vendor.
- **Demo** — the bundled `ecommerce_dataset/*.csv`.

`src/api_client.py` returns the same dict of DataFrames, with the same column
names and dtypes, as `src/loader.py`. That is why every view and service works
against either source unchanged. Empty results still produce frames with the
declared columns, so a newly approved vendor with no orders renders empty states
rather than crashing.

## Running it

```bash
# 1. API  (marketplace-api-main/)
php artisan migrate --seed
php artisan db:seed --class="Database\Seeders\AnalyticsDemoSeeder"   # 12 months of history
php artisan serve --port=8001

# 2. Storefront  (marketplace-api-main/frontend/)
npm install && npm run dev -- --port 5174 --strictPort

# 3. Dashboard  (ecommerce/)
MARKETPLACE_API_URL=http://127.0.0.1:8001/api streamlit run dashboard/app.py

# 4. Assistant  (ecommerce/, optional — needs GROK_API_KEY)
uvicorn api.chat_api:app --port 8090
```

Then open **http://localhost:5174** and sign in as `vendor-a@example.com` /
`password`. Seeded accounts: `vendor-a@`, `vendor-b@`, `customer@example.com`,
`admin@marketplace.test` — all `password`.

The default seeder creates only a handful of orders dated today, which is not
enough for forecasting or segmentation. `AnalyticsDemoSeeder` generates ~60
customers and twelve months of orders, reviews and events with a growth trend
and a Q4 bump. It is intentionally not called by `DatabaseSeeder` so the test
suite's fixtures stay small.

## Configuration

| Setting | Where | Value here |
|---|---|---|
| `DB_CONNECTION` | API `.env` | `mysql` on port 3307, database `ecommerce_market_place` |
| `FRONTEND_URL` / `_ALT` | API `.env` | `http://localhost:5174` — CORS allowlist |
| `VITE_API_URL` | `frontend/.env` | `http://127.0.0.1:8001/api` |
| `VITE_ANALYTICS_URL` | `frontend/.env` | `http://localhost:8501` |
| `VITE_CHAT_API_URL` | `frontend/.env` | optional, defaults to `:8090` |
| `MARKETPLACE_API_URL` | dashboard env | `http://127.0.0.1:8001/api` |
| `CHAT_CORS_ORIGINS` | assistant env | defaults to the Vite dev origins |
| `GROK_API_KEY` | `ecommerce/.env` | required for the assistant |

If you free up ports 5173 and 8000, update `FRONTEND_URL`, `VITE_API_URL` and
`MARKETPLACE_API_URL` together — they must agree.

## Known trade-off

Embedding the dashboard cross-origin required disabling Streamlit's CORS and
XSRF protection in `.streamlit/config.toml`. That is acceptable locally: the
dashboard has no state-changing endpoints of its own, it only reads, and every
read is authorised server-side by the vendor's token. **Do not expose it
publicly in this configuration.** For production, serve both apps from one
origin — a reverse proxy putting the dashboard under `/analytics` — and turn
both settings back on.

## Tests

```bash
cd marketplace-api-main && php artisan test     # 152 passing
```

The suite runs on its own in-memory SQLite, so it is unaffected by the MySQL
configuration above. The two suites worth knowing:

- `tests/Feature/Vendor/VendorAnalyticsTest.php` — tenant isolation, the
  vendor-share revenue split, derived ratings, date filters, access control.
- `tests/Feature/Auth/HandoffTest.php` — single use, expiry, hashing, unknown
  codes, deleted users.
