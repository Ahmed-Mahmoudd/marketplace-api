"""
Sales forecasting service.

Wraps the Prophet model trained in `notebooks/Predictive_Models.ipynb.ipynb`
and saved to `models/sales_forecast_model.pkl`. If the pickled model can't
be loaded (missing file, version mismatch, prophet not installed the same
way it was trained), we transparently retrain it from the raw data instead
of crashing the dashboard.
"""

from pathlib import Path
import pandas as pd
import joblib

PROJECT_ROOT = Path(__file__).resolve().parent.parent
MODEL_PATH = PROJECT_ROOT / "models" / "sales_forecast_model.pkl"


def build_monthly_sales(order_items: pd.DataFrame, orders: pd.DataFrame) -> pd.DataFrame:
    """Reproduce the monthly revenue series the model was trained on."""
    sales = order_items.merge(
        orders[["order_id", "user_id", "order_date"]],
        on=["order_id", "user_id"],
        how="left",
    )
    monthly = (
        sales.groupby(sales["order_date"].dt.to_period("M"))["item_total"]
        .sum()
        .reset_index()
    )
    monthly.columns = ["ds", "y"]
    monthly["ds"] = monthly["ds"].dt.to_timestamp()
    return monthly


def load_trained_model():
    """Load the pre-trained Prophet model. Raises if unavailable."""
    return joblib.load(MODEL_PATH)


def train_model(monthly_sales: pd.DataFrame):
    """Fallback: fit a fresh Prophet model on the current data."""
    from prophet import Prophet

    model = Prophet()
    model.fit(monthly_sales)
    return model


def get_model(monthly_sales: pd.DataFrame, allow_saved: bool = True):
    """Load the saved model if possible, otherwise train one on the fly.

    `allow_saved=False` is what vendor dashboards pass: the pickled model was
    trained on the bundled sample dataset, so reusing it for a real vendor
    would forecast someone else's revenue curve. Vendors always get a model
    fit on their own sales.
    """
    if allow_saved:
        try:
            return load_trained_model(), "saved"
        except Exception:
            pass

    return train_model(monthly_sales), "retrained"


def make_forecast(model, periods: int = 6) -> pd.DataFrame:
    """Predict `periods` months beyond the model's training data.

    Uses 'ME' (month-end) instead of the deprecated 'M' alias, and clips
    revenue predictions at 0 since negative revenue isn't meaningful.
    """
    future = model.make_future_dataframe(periods=periods, freq="ME")
    forecast = model.predict(future)
    for col in ["yhat", "yhat_lower", "yhat_upper"]:
        forecast[col] = forecast[col].clip(lower=0)
    return forecast
