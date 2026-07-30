"""
Customer segmentation service.

Reproduces the KMeans pipeline from `notebooks/customer_segmentation.ipynb`
(RFM-lite features -> StandardScaler -> KMeans) as reusable functions, plus
a small heuristic that turns anonymous cluster ids (0, 1, 2, 3...) into
human-readable persona names based on how each cluster ranks on spend and
order frequency.
"""

from __future__ import annotations

import pandas as pd
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler

DEFAULT_FEATURES = ["total_spent", "num_orders"]

PERSONA_NAMES = [
    "Champions",
    "Loyal Customers",
    "Potential Loyalists",
    "Low-Engagement",
    "At Risk",
    "New / One-Time",
]


def build_customer_features(orders: pd.DataFrame, customers: pd.DataFrame) -> pd.DataFrame:
    """One row per customer: total_spent, num_orders, avg_order_value, recency."""
    reference_date = orders["order_date"].max()

    agg = orders.groupby("user_id").agg(
        total_spent=("total_amount", "sum"),
        num_orders=("order_id", "count"),
        last_order_date=("order_date", "max"),
    ).reset_index()

    agg["avg_order_value"] = (agg["total_spent"] / agg["num_orders"]).round(2)
    agg["recency_days"] = (reference_date - agg["last_order_date"]).dt.days

    features = agg.merge(
        customers[["user_id", "name", "city", "gender", "signup_date"]],
        on="user_id",
        how="left",
    )
    return features


def elbow_curve(features: pd.DataFrame, feature_cols=DEFAULT_FEATURES, k_range=range(1, 11)):
    """WCSS for k=1..10, for picking the number of clusters."""
    X = features[list(feature_cols)]
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X)

    wcss = []
    for k in k_range:
        km = KMeans(n_clusters=k, random_state=42, n_init=10)
        km.fit(X_scaled)
        wcss.append(km.inertia_)
    return list(k_range), wcss


def run_segmentation(
    features: pd.DataFrame,
    feature_cols=DEFAULT_FEATURES,
    n_clusters: int = 4,
):
    """Scale features, fit KMeans, and attach cluster + persona labels."""
    X = features[list(feature_cols)]
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X)

    km = KMeans(n_clusters=n_clusters, random_state=42, n_init=10)
    labels = km.fit_predict(X_scaled)

    result = features.copy()
    result["cluster"] = labels

    summary = (
        result.groupby("cluster")
        .agg(
            customers=("user_id", "count"),
            avg_total_spent=("total_spent", "mean"),
            avg_num_orders=("num_orders", "mean"),
            avg_order_value=("avg_order_value", "mean"),
            avg_recency_days=("recency_days", "mean"),
        )
        .round(2)
        .reset_index()
    )

    persona_map = _assign_personas(summary)
    summary["persona"] = summary["cluster"].map(persona_map)
    result["persona"] = result["cluster"].map(persona_map)

    return result, summary, km, scaler


def _assign_personas(summary: pd.DataFrame) -> dict:
    """Rank clusters by a simple value score (spend x order count) and
    assign readable persona names. Purely descriptive, not a separate model."""
    score = summary["avg_total_spent"] * summary["avg_num_orders"]
    ranked_clusters = score.sort_values(ascending=False).index.tolist()

    mapping = {}
    for rank, idx in enumerate(ranked_clusters):
        cluster_id = summary.loc[idx, "cluster"]
        name = PERSONA_NAMES[rank] if rank < len(PERSONA_NAMES) else f"Segment {cluster_id}"
        mapping[cluster_id] = name
    return mapping
