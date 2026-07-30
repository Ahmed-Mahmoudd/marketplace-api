"""
Product recommendation service.

Same idea as `notebooks/Product_Recommendation.ipynb` (item-based
collaborative filtering: cosine similarity between products based on which
users bought them, then re-ranked by category/brand match and rating), but
built on a sparse user-product matrix instead of a dense pivot table -
2,000 products x 10,000 users is ~1.6B cells dense; sparse keeps it to just
the ~40k real interactions.
"""

from __future__ import annotations

import pandas as pd
from scipy.sparse import csr_matrix
from sklearn.metrics.pairwise import cosine_similarity


def build_similarity_matrix(order_items: pd.DataFrame) -> pd.DataFrame:
    """Product x product cosine similarity based on shared buyers.

    Returns an empty frame when there's nothing to learn from — a new store
    with no sales yet has no co-purchase signal.
    """
    if order_items.empty:
        return pd.DataFrame()

    interactions = (
        order_items.groupby(["product_id", "user_id"])["quantity"]
        .sum()
        .reset_index()
    )

    if interactions.empty:
        return pd.DataFrame()

    product_cat = interactions["product_id"].astype("category")
    user_cat = interactions["user_id"].astype("category")

    matrix = csr_matrix(
        (interactions["quantity"], (product_cat.cat.codes, user_cat.cat.codes)),
        shape=(len(product_cat.cat.categories), len(user_cat.cat.categories)),
    )

    similarity = cosine_similarity(matrix)
    return pd.DataFrame(
        similarity,
        index=product_cat.cat.categories,
        columns=product_cat.cat.categories,
    )


def recommend_similar(sim_df: pd.DataFrame, product_id: str, top_n: int = 5) -> pd.Series:
    """Raw top-N most similar products by purchase-pattern similarity."""
    if product_id not in sim_df.columns:
        return pd.Series(dtype=float)
    scores = sim_df[product_id].drop(product_id).sort_values(ascending=False)
    return scores.head(top_n)


def recommend_products(
    sim_df: pd.DataFrame,
    products: pd.DataFrame,
    product_id: str,
    top_n: int = 5,
    candidate_pool: int = 50,
) -> pd.DataFrame:
    """Similar products, re-ranked to prefer same category + same brand.

    Mirrors the notebook's `recommend_product_names`: pull a larger pool of
    similar items, keep only same-category ones, then sort by brand match,
    similarity, and rating.
    """
    original = products.loc[products["product_id"] == product_id]
    if original.empty:
        return pd.DataFrame()

    original_category = original["category"].values[0]
    original_brand = original["brand"].values[0]

    candidates = recommend_similar(sim_df, product_id, top_n=candidate_pool)
    if candidates.empty:
        return pd.DataFrame()

    result = candidates.reset_index()
    result.columns = ["product_id", "similarity"]
    result = result.merge(products, on="product_id", how="left")
    result = result[result["category"] == original_category].copy()
    result["brand_match"] = (result["brand"] == original_brand).astype(int)
    result = result.sort_values(
        by=["brand_match", "similarity", "rating"],
        ascending=[False, False, False],
    )
    return result.head(top_n)
