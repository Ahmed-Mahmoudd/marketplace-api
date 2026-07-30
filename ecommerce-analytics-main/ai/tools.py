"""
Tools the chat assistant can call.

The product catalog is injected rather than read from disk at import time:
when a vendor is signed in, the assistant must answer about *their* catalog,
not the bundled sample CSV. `set_catalog()` is called on every render of the
AI Chat page. The FAQ corpus is shared and stays file-backed.
"""

from pathlib import Path

import pandas as pd
from difflib import get_close_matches

PROJECT_ROOT = Path(__file__).resolve().parent.parent

# Paths are resolved from this file, not the working directory, so the tools
# work no matter where the app or a notebook is launched from.
PRODUCTS_CSV = PROJECT_ROOT / "ecommerce_dataset" / "products.csv"
FAQ_CSV = PROJECT_ROOT / "FAQ_dataset" / "Customer Support Data Set - Sheet1 (1).csv"

faq = pd.read_csv(FAQ_CSV)

_catalog: pd.DataFrame | None = None


def set_catalog(df: pd.DataFrame) -> None:
    """Point the tools at the signed-in vendor's catalog."""
    global _catalog
    _catalog = df


def get_catalog() -> pd.DataFrame:
    """Current catalog, falling back to the bundled sample data."""
    global _catalog

    if _catalog is None:
        _catalog = pd.read_csv(PRODUCTS_CSV)

    return _catalog


def search_products(
    category=None,
    min_price=None,
    max_price=None,
    min_rating=None
):
    result = get_catalog().copy()

    if category:
        result = result[
            result["category"].str.contains(category, case=False, na=False)
        ]

    if min_price is not None:
        result = result[result["price"] >= min_price]

    if max_price is not None:
        result = result[result["price"] <= max_price]

    if min_rating is not None:
        result = result[result["rating"] >= min_rating]

    result = result.sort_values(
        by="rating",
        ascending=False
    )

    return result
def best_products(category=None, top_n=5):

    result = get_catalog().copy()

    if category:
        result = result[
            result["category"].str.contains(category, case=False, na=False)
        ]

    result = result.sort_values(
        by=["rating", "price"],
        ascending=[False, True]
    )

    return result[
        [
            "product_name",
            "category",
            "brand",
            "price",
            "rating"
        ]
    ].head(top_n)
def get_product(product_name):

    products = get_catalog()

    result = products[
        products["product_name"].str.contains(
            product_name,
            case=False,
            na=False
        )
    ]

    return result
def compare_products(product1, product2):

    p1 = get_product(product1)
    p2 = get_product(product2)

    if p1.empty:
        return f"{product1} not found."

    if p2.empty:
        return f"{product2} not found."

    p1 = p1.iloc[0]
    p2 = p2.iloc[0]

    comparison = pd.DataFrame({
        "Feature": [
            "Brand",
            "Category",
            "Price",
            "Rating"
        ],
        p1["product_name"]: [
            p1["brand"],
            p1["category"],
            p1["price"],
            p1["rating"]
        ],
        p2["product_name"]: [
            p2["brand"],
            p2["category"],
            p2["price"],
            p2["rating"]
        ]
    })

    return comparison
def compare_summary(product1, product2):

    p1 = get_product(product1)
    p2 = get_product(product2)

    if p1.empty:
        return f"{product1} not found."

    if p2.empty:
        return f"{product2} not found."

    p1 = p1.iloc[0]
    p2 = p2.iloc[0]

    summary = {}

    summary["higher_rating"] = (
        p1["product_name"]
        if p1["rating"] >= p2["rating"]
        else p2["product_name"]
    )

    summary["lower_price"] = (
        p1["product_name"]
        if p1["price"] <= p2["price"]
        else p2["product_name"]
    )

    summary["same_brand"] = (
        p1["brand"] == p2["brand"]
    )

    summary["same_category"] = (
        p1["category"] == p2["category"]
    )

    return summary
def search_faq(question, top_n=3):

    questions = faq["question"].tolist()

    matches = get_close_matches(
        question,
        questions,
        n=top_n,
        cutoff=0.4
    )

    results = []

    for q in matches:

        row = faq[
            faq["question"] == q
        ].iloc[0]

        results.append({
            "question": row["question"],
            "answer": row["answer"],
            "category": row["category"]
        })

    return results