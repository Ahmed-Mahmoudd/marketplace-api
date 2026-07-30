import plotly.express as px
import streamlit as st

from dashboard import auth
from dashboard.data import get_elbow_curve, get_segmentation
from dashboard.utils import PALETTE, fmt_currency, fmt_number, style_fig
from services.segmentation_service import build_customer_features

FEATURE_LABELS = {
    "total_spent": "Total Spent",
    "num_orders": "Number of Orders",
    "avg_order_value": "Avg Order Value",
    "recency_days": "Days Since Last Order",
}


def render(data: dict):
    st.header("👥 Customer Segmentation")
    st.caption("KMeans clustering on customer purchase behavior (see `notebooks/customer_segmentation.ipynb`).")

    tenant = auth.current_tenant()
    features = build_customer_features(data["orders"], data["customers"])

    # KMeans needs more customers than clusters; a young store may not have them.
    max_k = min(8, max(2, len(features) - 1))

    if len(features) < 3:
        st.info(
            f"Segmentation needs at least 3 customers with orders — your store "
            f"currently has {len(features)}."
        )
        return

    c1, c2 = st.columns([1, 2])
    with c1:
        n_clusters = st.slider("Number of clusters (k)", 2, max_k, min(4, max_k))
    with c2:
        feature_cols = st.multiselect(
            "Features used for clustering",
            options=list(FEATURE_LABELS.keys()),
            default=["total_spent", "num_orders"],
            format_func=lambda x: FEATURE_LABELS[x],
        )
    if len(feature_cols) < 2:
        st.info("Pick at least 2 features to cluster on.")
        return

    result, summary = get_segmentation(tenant, data["orders"], data["customers"], n_clusters, tuple(feature_cols))

    st.divider()
    left, right = st.columns([1, 2])
    with left:
        st.subheader("Elbow Method")
        k_range, wcss = get_elbow_curve(tenant, features, tuple(feature_cols))
        fig = px.line(x=list(k_range), y=wcss, markers=True)
        fig.add_vline(x=n_clusters, line_dash="dash", line_color=PALETTE[2])
        fig.update_layout(title="WCSS vs. k", xaxis_title="Number of Clusters (k)", yaxis_title="WCSS")
        st.plotly_chart(style_fig(fig), use_container_width=True)
        st.caption(f"Dashed line marks the current selection (k={n_clusters}). Look for the 'elbow' where WCSS stops dropping fast.")

    with right:
        st.subheader("Customer Segments")
        fig2 = px.scatter(
            result, x="total_spent", y="num_orders", color="persona",
            hover_data=["user_id", "avg_order_value", "recency_days"],
            color_discrete_sequence=PALETTE,
        )
        fig2.update_layout(title="Segments (plotted on Total Spent vs. Orders)", xaxis_title="Total Spent ($)", yaxis_title="Number of Orders")
        st.plotly_chart(style_fig(fig2, height=430), use_container_width=True)

    st.subheader("Segment Profiles")
    summary_display = summary.rename(columns={
        "persona": "Segment", "customers": "Customers", "avg_total_spent": "Avg Spend ($)",
        "avg_num_orders": "Avg Orders", "avg_order_value": "Avg Order Value ($)", "avg_recency_days": "Avg Days Since Last Order",
    }).drop(columns=["cluster"])
    st.dataframe(
        summary_display.style.format({
            "Avg Spend ($)": "${:,.0f}", "Avg Orders": "{:.1f}",
            "Avg Order Value ($)": "${:,.2f}", "Avg Days Since Last Order": "{:.0f}",
        }).background_gradient(subset=["Avg Spend ($)"], cmap="Greens"),
        use_container_width=True, hide_index=True,
    )
    st.caption(
        "Segment names (Champions, Loyal, etc.) are a simple ranking heuristic based on spend x order "
        "frequency, not a separate model — useful shorthand for talking about the clusters."
    )

    st.divider()
    st.subheader("Look Up a Customer's Segment")
    lookup_id = st.selectbox("Customer ID", options=sorted(result["user_id"].unique()))
    row = result.loc[result["user_id"] == lookup_id].iloc[0]
    lc1, lc2, lc3, lc4 = st.columns(4)
    lc1.metric("Segment", row["persona"])
    lc2.metric("Total Spent", fmt_currency(row["total_spent"]))
    lc3.metric("Orders", fmt_number(row["num_orders"]))
    lc4.metric("Days Since Last Order", fmt_number(row["recency_days"]))

    st.download_button(
        "Download full segmented customer list (CSV)",
        result.to_csv(index=False).encode("utf-8"),
        file_name="customer_segments.csv",
        mime="text/csv",
    )
