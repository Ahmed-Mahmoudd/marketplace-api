"""
Cached access to raw data and to the (potentially expensive) model
artifacts, kept separate from `src/loader.py` and `services/*` so those
stay plain, framework-agnostic Python that's also usable from notebooks.

Multi-tenancy note: every cached function takes an explicit `tenant` key.
Streamlit does not hash arguments whose name starts with an underscore, so
without that key a model built for one vendor would be handed straight to the
next vendor who asked for it. `tenant` comes from `dashboard.auth`.
"""

import streamlit as st

from dashboard import auth
from src.api_client import MarketplaceError
from src.loader import load_data
from services import forecast_service, segmentation_service, recommendation_service


# --------------------------------------------------------------------- loading


@st.cache_data(show_spinner="Loading sample dataset...")
def _load_demo_data():
    return load_data()


@st.cache_data(show_spinner="Loading your store data...")
def _load_vendor_data(tenant: str, _client):
    # `tenant` is the cache key; `_client` carries the bearer token and is
    # deliberately not hashed (tokens change on every login).
    return _client.fetch_dataset()


def get_data():
    """Return the six analytics tables for whoever is currently signed in."""
    if auth.mode() == auth.MODE_DEMO:
        return _load_demo_data()

    try:
        return _load_vendor_data(auth.current_tenant(), auth.get_client())
    except MarketplaceError as exc:
        st.error(f"Could not load your store data: {exc}")
        st.stop()


def refresh():
    """Drop cached frames and models so the next render refetches."""
    st.cache_data.clear()
    st.cache_resource.clear()


# ---------------------------------------------------------------------- models


@st.cache_resource(show_spinner="Loading sales forecast model...")
def get_forecast_model(tenant: str, _monthly_sales):
    # leading underscore on the param tells st.cache_resource not to
    # hash the (large) dataframe argument, only cache on function identity.
    # The pickled model belongs to the sample dataset, so only demo mode may
    # use it — a vendor always gets a model fit on their own sales.
    model, source = forecast_service.get_model(
        _monthly_sales, allow_saved=tenant.startswith("demo")
    )
    return model, source


@st.cache_data(show_spinner="Generating forecast...")
def get_forecast(tenant: str, _model, periods: int):
    return forecast_service.make_forecast(_model, periods=periods)


@st.cache_data(show_spinner="Building customer segments...")
def get_segmentation(tenant: str, _orders, _customers, n_clusters: int, feature_cols: tuple):
    features = segmentation_service.build_customer_features(_orders, _customers)
    result, summary, _, _ = segmentation_service.run_segmentation(
        features, feature_cols=list(feature_cols), n_clusters=n_clusters
    )
    return result, summary


@st.cache_data(show_spinner="Computing elbow curve...")
def get_elbow_curve(tenant: str, _features, feature_cols: tuple):
    return segmentation_service.elbow_curve(_features, feature_cols=list(feature_cols))


@st.cache_resource(show_spinner="Building product similarity model...")
def get_similarity_matrix(tenant: str, _order_items):
    return recommendation_service.build_similarity_matrix(_order_items)
