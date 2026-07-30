"""
Central data loading utilities for the E-Commerce AI project.

Fixes vs. the original version:
- Paths are resolved relative to this file (not the current working
  directory), so `load_data()` works the same whether it's called from a
  notebook in `notebooks/`, a service in `services/`, or the Streamlit app
  in `dashboard/`.
- File names now match what's actually in `ecommerce_dataset/`
  (`users.csv`, not `customers.csv`).
- Date columns are parsed once, here, so every page/service works with
  real `datetime64` columns instead of re-parsing strings everywhere.
"""

from pathlib import Path
import pandas as pd

# ecommerce_dataset/ lives one level up from this file (src/ -> project root)
PROJECT_ROOT = Path(__file__).resolve().parent.parent
DATA_DIR = PROJECT_ROOT / "ecommerce_dataset"

DATE_COLUMNS = {
    "orders": ["order_date"],
    "reviews": ["review_date"],
    "events": ["event_timestamp"],
    "customers": ["signup_date"],
}


def load_data(data_dir: str | Path | None = None) -> dict[str, pd.DataFrame]:
    """Load every raw table used across the project.

    Returns a dict keyed by table name so callers can do
    ``data["orders"]`` instead of relying on positional unpacking order.
    """
    data_dir = Path(data_dir) if data_dir else DATA_DIR

    tables = {
        "products": pd.read_csv(data_dir / "products.csv"),
        "customers": pd.read_csv(data_dir / "users.csv"),
        "orders": pd.read_csv(data_dir / "orders.csv"),
        "order_items": pd.read_csv(data_dir / "order_items.csv"),
        "reviews": pd.read_csv(data_dir / "reviews.csv"),
        "events": pd.read_csv(data_dir / "events.csv"),
    }

    for name, cols in DATE_COLUMNS.items():
        for col in cols:
            tables[name][col] = pd.to_datetime(tables[name][col])

    return tables
