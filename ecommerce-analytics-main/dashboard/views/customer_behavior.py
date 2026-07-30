import pandas as pd
import plotly.express as px
import plotly.graph_objects as go
import streamlit as st

from dashboard.utils import PALETTE, fmt_number, style_fig


def render(data: dict):
    st.header("🧭 Customer Behavior & Funnel")
    st.caption("Product views, cart adds and purchases recorded against your listings.")

    events = data["events"]
    products = data["products"]

    if events.empty:
        st.info(
            "No customer activity recorded yet. Views, cart adds and purchases "
            "are tracked as shoppers browse your products."
        )
        return

    counts = events["event_type"].value_counts()
    views = counts.get("view", 0)
    carts = counts.get("cart", 0)
    wishlists = counts.get("wishlist", 0)
    purchases = counts.get("purchase", 0)

    # `wishlist` only exists in the bundled sample dataset; the marketplace
    # doesn't record it, so the tile is dropped rather than shown as a flat 0.
    if wishlists:
        c1, c2, c3, c4 = st.columns(4)
        c3.metric("Wishlisted", fmt_number(wishlists))
    else:
        c1, c2, c4 = st.columns(3)

    c1.metric("Views", fmt_number(views))
    c2.metric("Added to Cart", fmt_number(carts))
    c4.metric("Purchased", fmt_number(purchases))

    st.divider()
    left, right = st.columns([1, 1])

    with left:
        st.subheader("Conversion Funnel")
        funnel = go.Figure(go.Funnel(
            y=["Viewed", "Added to Cart", "Purchased"],
            x=[views, carts, purchases],
            marker=dict(color=PALETTE[:3]),
        ))
        st.plotly_chart(style_fig(funnel), use_container_width=True)
        view_to_cart = (carts / views * 100) if views else 0
        cart_to_purchase = (purchases / carts * 100) if carts else 0
        view_to_purchase = (purchases / views * 100) if views else 0
        m1, m2, m3 = st.columns(3)
        m1.metric("View → Cart", f"{view_to_cart:.1f}%")
        m2.metric("Cart → Purchase", f"{cart_to_purchase:.1f}%")
        m3.metric("View → Purchase", f"{view_to_purchase:.1f}%")

    with right:
        st.subheader("Events Over Time")
        events_ts = (
            events.groupby([events["event_timestamp"].dt.to_period("M"), "event_type"])
            .size().reset_index(name="count")
        )
        events_ts["event_timestamp"] = events_ts["event_timestamp"].astype(str)
        fig = px.line(events_ts, x="event_timestamp", y="count", color="event_type", markers=True, color_discrete_sequence=PALETTE)
        fig.update_layout(xaxis_title="Month", yaxis_title="Event Count")
        st.plotly_chart(style_fig(fig), use_container_width=True)

    st.divider()
    st.subheader("Interest vs. Conversion by Product")
    st.caption("Products with high views but low purchases are candidates for promotions, pricing review, or better listing content.")

    view_events = events[events["event_type"] == "view"].groupby("product_id").size().rename("views")
    purchase_events = events[events["event_type"] == "purchase"].groupby("product_id").size().rename("purchases")
    interest = (
        products[["product_id", "product_name", "category"]]
        .merge(view_events, on="product_id", how="left")
        .merge(purchase_events, on="product_id", how="left")
        .fillna(0)
    )
    interest["conversion_rate_pct"] = (interest["purchases"] / interest["views"].replace(0, pd.NA) * 100).fillna(0).round(1)
    interest = interest[interest["views"] > 0].sort_values("views", ascending=False).head(15)

    fig2 = px.bar(
        interest, x="product_name", y=["views", "purchases"], barmode="group",
        title="Top 15 Most-Viewed Products: Views vs. Purchases", color_discrete_sequence=PALETTE,
    )
    fig2.update_layout(xaxis_title="", yaxis_title="Count", xaxis_tickangle=-40)
    st.plotly_chart(style_fig(fig2, height=450), use_container_width=True)

    st.dataframe(
        interest[["product_name", "category", "views", "purchases", "conversion_rate_pct"]].rename(
            columns={"product_name": "Product", "category": "Category", "views": "Views", "purchases": "Purchases", "conversion_rate_pct": "Conversion %"}
        ),
        use_container_width=True, hide_index=True,
    )
