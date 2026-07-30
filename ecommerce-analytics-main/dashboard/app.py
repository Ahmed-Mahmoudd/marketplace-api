"""
E-Commerce AI Dashboard
Run from the project root with:  streamlit run dashboard/app.py
"""

import sys
from pathlib import Path

# Allow `import src...` / `import services...` when Streamlit runs this
# file directly (its own directory is on sys.path, but the project root,
# one level up, is not, by default).
PROJECT_ROOT = Path(__file__).resolve().parent.parent
if str(PROJECT_ROOT) not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT))

import streamlit as st
from streamlit_option_menu import option_menu

from dashboard import auth, data as data_module
from dashboard.views import (
    overview,
    products,
    peer_analysis,
    forecast,
    segmentation,
    recommendations,
    customer_behavior,
    ai_chat,
)

st.set_page_config(
    page_title="Vendor Analytics",
    page_icon="🛒",
    layout="wide",
    initial_sidebar_state="expanded",
)

# A `?code=` from the storefront signs the vendor in before anything renders.
auth.consume_handoff()

# Nothing renders until we know whose data we're allowed to show.
if not auth.is_authenticated():
    auth.render_login()
    st.stop()

PAGES = {
    "Overview": overview,
    "Products": products,
    "Peer Analysis": peer_analysis,
    "Sales Forecast": forecast,
    "Segmentation": segmentation,
    "Recommendations": recommendations,
    "Customer Behavior": customer_behavior,
    "AI Chat": ai_chat,

}

ICONS = [
    "house", "box-seam", "grid-3x3-gap", "graph-up-arrow",
    "people", "stars", "activity", "chat-dots",
]

session = auth.vendor_session()

with st.sidebar:
    st.title("🛒 Vendor Analytics")
    st.caption(
        session.store_name if session else "Analytics · Forecasting · Segmentation"
    )
    st.divider()

    selected = option_menu(
        menu_title=None,
        options=list(PAGES.keys()),
        icons=ICONS,
        default_index=0,
    )

    st.divider()

    if st.button("↻ Refresh data", use_container_width=True):
        data_module.refresh()
        st.rerun()

    auth.render_sidebar_account()

data = data_module.get_data()
PAGES[selected].render(data)
