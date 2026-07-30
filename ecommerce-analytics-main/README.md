# E-Commerce AI Dashboard

A multi-tenant Streamlit dashboard for the Marketplace API: each vendor signs
in with their marketplace account and sees analytics for **their own store
only** — revenue, catalog, funnel — plus three trained models: sales
forecasting (Prophet), customer segmentation (KMeans), and product
recommendations (item-based collaborative filtering).

## Two modes

| Mode | Data source | When |
|---|---|---|
| **Live (SaaS)** | The Marketplace API, scoped to the signed-in vendor | Normal use |
| **Demo** | The bundled `ecommerce_dataset/*.csv` sample data | Presentations, or working on the ML pages without a running API |

The login screen offers both. Live mode requires a marketplace account with
the `vendor` role and an **approved** store.

## Quickstart

```bash
python -m venv .venv && source .venv/bin/activate   # optional but recommended
pip install -r requirements.txt
streamlit run dashboard/app.py
```

Run from the project root so the app can find `ecommerce_dataset/` and
`models/`.

### Connecting to the marketplace

Start the API (in the `marketplace-api-main` checkout):

```bash
php artisan migrate --seed
php artisan db:seed --class="Database\Seeders\AnalyticsDemoSeeder"  # 12 months of demo trading history
php artisan serve
```

Then point the dashboard at it — either set the base URL once:

```bash
export MARKETPLACE_API_URL=http://localhost:8000/api
```

or type it into **API settings** on the login screen. Sign in with a seeded
vendor (`vendor-a@example.com` / `password`).

### Customer chat widget

The same assistant that powers the dashboard's AI Chat page is exposed over
HTTP for the storefront's floating chat widget:

```bash
uvicorn api.chat_api:app --reload --port 8090
```

`POST /chat` takes `{"message": "..."}` and returns `{"reply": "..."}`;
`GET /health` reports whether `GROK_API_KEY` is set. Browser origins are
allowed via `CHAT_CORS_ORIGINS` (comma-separated, defaults to the Vite dev
server). The storefront finds the service through `VITE_CHAT_API_URL`.

No vendor catalog is injected here — a shopper browses the whole marketplace,
so the tools use the shared catalog rather than one vendor's slice.

## How tenancy works

The dashboard never decides which vendor's data to show — the API does.
Login returns a Sanctum token; `GET /api/vendor/analytics/dataset` resolves
the vendor from that token and returns only their rows. There is no vendor id
in the request for a client to tamper with.

Two consequences worth knowing:

* **Revenue is the vendor's share, not the basket.** A marketplace order can
  span several vendors, so `orders.total_amount` is recomputed server-side as
  the sum of *that vendor's* line items. Using the raw order total would show
  a vendor other people's money.
* **Caches are keyed by tenant.** Streamlit doesn't hash arguments beginning
  with `_`, so every cached loader and model in `dashboard/data.py` takes an
  explicit `tenant` key. Without it, a model fit for one vendor would be
  served to the next.

The saved `models/sales_forecast_model.pkl` was trained on the sample dataset,
so it is used in demo mode only; vendors always get a model fit on their own
sales.

## Pages

| Page | What it shows |
|---|---|
| **Overview** | Revenue, orders, customers, AOV, cancellation/return rate, monthly revenue trend, revenue by category, order status mix, top sellers. Has a date-range filter. |
| **Products** | Catalog explorer — filter by category/brand/price, price & rating distributions, price-vs-rating scatter, top rated products, brand performance. |
| **Peer Analysis** | Pick 2-6 products, brands, or categories and compare them side by side: metric cards with revenue growth and a sparkline, grouped bar comparisons, a normalized radar chart, and a full metrics table. This is the "stock peer analysis" pattern applied to your catalog. |
| **Sales Forecast** | Prophet forecast of monthly revenue: pick a 1–12 month horizon, see actual vs. forecast with confidence bands, the trend component, and a downloadable forecast table. Demo mode loads `models/sales_forecast_model.pkl`; vendors get a model fit on their own sales. Needs at least 4 months of history. |
| **Segmentation** | Your KMeans customer segmentation, with an elbow chart to help pick *k*, adjustable features, a cluster scatter plot, segment profiles with plain-language names (Champions, Loyal, etc.), and a per-customer lookup. |
| **Recommendations** | Your item-based collaborative filtering recommender (cosine similarity on co-purchase patterns, re-ranked by category/brand match) as a product picker with recommendation cards. |
| **Customer Behavior** | A view → cart → purchase funnel with conversion rates, events over time, and a "high interest, low conversion" product table. In live mode this is built from the marketplace's `events` table, recorded as shoppers browse, add to cart and check out. |
| **AI Chat** | Assistant over the catalog and the support FAQ. Its product tools are pointed at whichever catalog is on screen, so a vendor gets answers about their own products. Needs `GROK_API_KEY` in `.env`. |

Every page degrades to an explanatory message rather than an error when a
store has no data yet — a newly approved vendor with no products or orders
can sign in safely.

## Project structure

```
E-Commerce Ai/
├── dashboard/
│   ├── app.py              # entry point: auth gate, sidebar nav, routing
│   ├── auth.py              # vendor login, session/tenant state, demo toggle
│   ├── data.py              # streamlit-cached, tenant-keyed data + model access
│   ├── utils.py             # shared formatting + chart styling helpers
│   └── views/                # one file per page, each exposing render(data)
├── services/                 # business logic, framework-agnostic (no `import streamlit`)
│   ├── forecast_service.py
│   ├── segmentation_service.py
│   ├── recommendation_service.py
│   └── peer_analysis_service.py
├── src/
│   ├── loader.py             # loads + parses the six sample CSVs
│   └── api_client.py         # Marketplace API client (auth + analytics dataset)
├── models/
│   └── sales_forecast_model.pkl
├── ecommerce_dataset/
├── notebooks/                 # your original analysis, unchanged
├── .streamlit/config.toml     # theme
└── requirements.txt
```

`services/` and `src/` never import `streamlit`, so the same functions work
from a notebook or a script, not just the dashboard — the dashboard's
`dashboard/data.py` is the only place that adds Streamlit caching on top.
That includes `src/api_client.py`, so you can pull a vendor's dataset from a
notebook:

```python
from src.api_client import MarketplaceClient

client = MarketplaceClient("http://localhost:8000/api")
client.login("vendor-a@example.com", "password")
data = client.fetch_dataset()   # same dict of DataFrames as load_data()
```

`fetch_dataset()` returns the same six tables with the same column names as
the CSV loader, which is why every view and service works against either
source unchanged.

## Bugs fixed along the way

- **`src/loader.py`** pointed at `../data/customers.csv`, a path/filename
  that doesn't exist in this project (`ecommerce_dataset/users.csv` does).
  Paths are now resolved relative to the file itself, so it works
  regardless of the current working directory.
- **Prophet forecasting** called `make_future_dataframe(freq="M")`, which
  raises on current pandas (`'M'` was removed in favor of `'ME'`). Fixed to
  `'ME'`, and forecasted values are clipped at 0 (revenue can't go negative).
- **The recommendation notebook's dense pivot table** (`user_product_matrix`,
  10,000 × 2,000 cells) was rebuilt as a sparse matrix in
  `recommendation_service.py` — same math, far less memory, and it
  reproduces the notebook's example output exactly.
- **`dashboard/app.py`** itself was unfinished: almost the entire file sat
  outside the `Overview` `if` block due to inconsistent indentation, so it
  would either crash or silently render the wrong thing regardless of which
  sidebar item was selected. It's been rebuilt as one `render(data)`
  function per page.

## Notes on the models

- **Forecast**: trained on ~23 months of aggregated monthly revenue.
  Prophet didn't detect a reliable yearly seasonal pattern (that needs 2+
  years of history), so what you're seeing is trend-driven, not seasonal —
  the dashboard says this explicitly rather than showing a seasonality
  chart that doesn't reflect what the model actually learned.
- **Segmentation**: defaults to your original two features (total spent,
  order count) and *k*=4 clusters, but the page lets you add average order
  value / recency and change *k* to explore further. Segment names are a
  heuristic ranking by spend × order count, not a separate model.
- **Recommendations**: same category/brand-boosted re-ranking as your
  notebook. If a product has too little co-purchase overlap within its own
  category, the page says so instead of showing an empty or misleading list.
