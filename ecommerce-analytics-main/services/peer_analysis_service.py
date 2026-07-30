"""
Peer analysis service.

Powers the "Peer Analysis Gallery" page, which applies the classic
stock-market "peer comparison" pattern (pick a handful of tickers, compare
them side by side on the same metrics, look at relative growth) to this
project's products / brands / categories instead of stocks.

For a chosen group of peers (e.g. a handful of products), we compute a
common set of metrics - revenue, units sold, orders, average price, average
rating, review count, and month-over-month growth - so they can be shown as
comparison cards, a grouped bar chart, and a normalized radar chart.
"""

from __future__ import annotations

import pandas as pd

LEVEL_KEY = {
    "Product": "product_id",
    "Brand": "brand",
    "Category": "category",
}

LEVEL_LABEL_COL = {
    "Product": "product_name",
    "Brand": "brand",
    "Category": "category",
}


def _sales_with_attributes(order_items: pd.DataFrame, orders: pd.DataFrame, products: pd.DataFrame) -> pd.DataFrame:
    sales = order_items.merge(products, on="product_id", how="left")
    sales = sales.merge(
        orders[["order_id", "user_id", "order_date"]],
        on=["order_id", "user_id"],
        how="left",
    )
    return sales


def list_options(products: pd.DataFrame, level: str) -> list[str]:
    key = LEVEL_KEY[level]
    if level == "Product":
        # show product_id + product_name so peers are identifiable in a picker.
        # product_id is a string in the sample CSVs but an integer from the
        # marketplace API, so cast before concatenating.
        opts = (
            products["product_id"].astype(str) + " - " + products["product_name"].astype(str)
        ).sort_values()
        return opts.tolist()
    return sorted(products[key].dropna().unique().tolist())


def _resolve_ids(level: str, selected: list[str], products: pd.DataFrame | None = None) -> list:
    if level != "Product":
        return selected

    # selected items look like "P000049 - NeoTech Help" -> take the id
    prefixes = [s.split(" - ")[0] for s in selected]

    if products is None:
        return prefixes

    # Map the string label back to the id's real dtype, so the comparisons
    # below still match when ids are integers.
    lookup = {str(pid): pid for pid in products["product_id"]}
    return [lookup.get(prefix, prefix) for prefix in prefixes]


def compute_peer_metrics(
    data: dict,
    level: str,
    selected: list[str],
) -> tuple[pd.DataFrame, dict[str, pd.DataFrame]]:
    """Compute comparison metrics + monthly revenue trend for each peer.

    Returns (metrics_df, trends) where trends[label] is a DataFrame with
    columns ['month', 'revenue'] for that peer's monthly revenue history.
    """
    key = LEVEL_KEY[level]
    label_col = LEVEL_LABEL_COL[level]
    ids = _resolve_ids(level, selected, data["products"])

    sales = _sales_with_attributes(data["order_items"], data["orders"], data["products"])
    reviews = data["reviews"].merge(
        data["products"][["product_id", key]] if level != "Product" else data["products"][["product_id"]],
        on="product_id",
        how="left",
    )

    rows = []
    trends = {}

    for entity_id, label in zip(ids, selected):
        subset = sales[sales[key] == entity_id]
        rev_subset = reviews[reviews[key if level != "Product" else "product_id"] == entity_id]

        revenue = subset["item_total"].sum()
        units_sold = subset["quantity"].sum()
        order_count = subset["order_id"].nunique()
        avg_price = subset["price"].mean() if not subset.empty else 0
        avg_rating = subset["rating"].mean() if not subset.empty else 0
        review_count = len(rev_subset)

        monthly = (
            subset.dropna(subset=["order_date"])
            .groupby(subset["order_date"].dt.to_period("M"))["item_total"]
            .sum()
            .reset_index()
        )
        monthly.columns = ["month", "revenue"]
        monthly["month"] = monthly["month"].astype(str)
        trends[label] = monthly

        # growth: last 3 months of revenue vs the 3 months before that
        growth_pct = None
        if len(monthly) >= 6:
            recent = monthly["revenue"].tail(3).sum()
            prior = monthly["revenue"].tail(6).head(3).sum()
            if prior > 0:
                growth_pct = ((recent - prior) / prior) * 100

        rows.append(
            {
                "label": label,
                "id": entity_id,
                "revenue": revenue,
                "units_sold": units_sold,
                "orders": order_count,
                "avg_price": avg_price,
                "avg_rating": avg_rating,
                "review_count": review_count,
                "growth_pct": growth_pct,
            }
        )

    metrics = pd.DataFrame(rows)
    if not metrics.empty and metrics["revenue"].sum() > 0:
        metrics["revenue_share_pct"] = metrics["revenue"] / metrics["revenue"].sum() * 100
    else:
        metrics["revenue_share_pct"] = 0.0

    return metrics, trends


def normalize_for_radar(metrics: pd.DataFrame, cols: list[str]) -> pd.DataFrame:
    """Min-max scale each metric to 0-100 so differently-scaled metrics
    (revenue in the thousands, rating out of 5) can share one radar chart."""
    normalized = metrics[["label"]].copy()
    for col in cols:
        col_min, col_max = metrics[col].min(), metrics[col].max()
        if col_max - col_min == 0:
            normalized[col] = 50.0
        else:
            normalized[col] = (metrics[col] - col_min) / (col_max - col_min) * 100
    return normalized
