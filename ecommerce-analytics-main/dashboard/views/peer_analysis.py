import plotly.express as px
import plotly.graph_objects as go
import streamlit as st

from dashboard.utils import PALETTE, fmt_currency, fmt_number, fmt_pct, sparkline, style_fig
from services import peer_analysis_service as pas

MAX_PEERS = 6


def render(data: dict):
    st.header("🔍 Peer Analysis Gallery")
    st.caption(
        "Compare products, brands, or categories side-by-side — the same "
        "'pick a few peers and compare' pattern stock dashboards use for "
        "tickers, applied here to the catalog."
    )

    products = data["products"]

    if products.empty:
        st.info("You have no products listed yet.")
        return

    # Within a single vendor's catalog every product carries that vendor's
    # store name as its brand, so brand-vs-brand has nothing to compare.
    levels = ["Category", "Brand", "Product"]
    if products["brand"].nunique() < 2:
        levels.remove("Brand")

    ctrl1, ctrl2 = st.columns([1, 3])
    with ctrl1:
        level = st.radio("Compare by", levels, horizontal=False)

    options = pas.list_options(products, level)

    default_n = 4 if level != "Product" else 3
    defaults = options[:default_n]
    plural = {"Category": "categories", "Brand": "brands", "Product": "products"}[level]

    with ctrl2:
        selected = st.multiselect(
            f"Choose up to {MAX_PEERS} {plural} to compare",
            options=options,
            default=defaults,
            max_selections=MAX_PEERS,
        )

    if len(selected) < 2:
        st.info("Pick at least 2 peers to compare.")
        return

    metrics, trends = pas.compute_peer_metrics(data, level, selected)
    metrics = metrics.sort_values("revenue", ascending=False).reset_index(drop=True)

    st.divider()
    cards = st.columns(len(metrics))
    for col, (_, row) in zip(cards, metrics.iterrows()):
        with col:
            with st.container(border=True):
                st.markdown(f"**{row['label'].split(' - ')[-1] if level == 'Product' else row['label']}**")
                st.metric(
                    "Revenue",
                    fmt_currency(row["revenue"]),
                    delta=fmt_pct(row["growth_pct"]) if row["growth_pct"] is not None else None,
                    delta_color="normal",
                )
                st.caption(f"⭐ {row['avg_rating']:.2f} avg rating &nbsp;|&nbsp; {fmt_currency(row['avg_price'])} avg price")
                st.caption(f"{fmt_number(row['units_sold'])} units · {fmt_number(row['orders'])} orders · {row['revenue_share_pct']:.1f}% of peer group revenue")
                trend = trends[row["label"]]
                if len(trend) > 1:
                    st.plotly_chart(sparkline(trend, "month", "revenue"), use_container_width=True, config={"displayModeBar": False})
                else:
                    st.caption("Not enough monthly history for a trend")

    st.divider()

    bar_l, bar_r = st.columns(2)
    display_labels = metrics["label"].apply(lambda x: x.split(" - ")[-1] if level == "Product" else x)

    with bar_l:
        fig = px.bar(
            metrics.assign(display=display_labels), x="display", y="revenue",
            title="Revenue Comparison", color="display", color_discrete_sequence=PALETTE,
        )
        fig.update_layout(showlegend=False, xaxis_title="", yaxis_title="Revenue ($)")
        st.plotly_chart(style_fig(fig), use_container_width=True)

    with bar_r:
        fig2 = px.bar(
            metrics.assign(display=display_labels), x="display", y="units_sold",
            title="Units Sold Comparison", color="display", color_discrete_sequence=PALETTE,
        )
        fig2.update_layout(showlegend=False, xaxis_title="", yaxis_title="Units Sold")
        st.plotly_chart(style_fig(fig2), use_container_width=True)

    st.subheader("Multi-Metric Shape Comparison")
    radar_cols = ["revenue", "units_sold", "orders", "avg_rating", "review_count"]
    normalized = pas.normalize_for_radar(metrics, radar_cols)

    radar_fig = go.Figure()
    for i, (_, row) in enumerate(normalized.iterrows()):
        label = row["label"]
        display = label.split(" - ")[-1] if level == "Product" else label
        radar_fig.add_trace(
            go.Scatterpolar(
                r=[row[c] for c in radar_cols] + [row[radar_cols[0]]],
                theta=["Revenue", "Units Sold", "Orders", "Avg Rating", "Reviews", "Revenue"],
                fill="toself",
                name=display,
                line=dict(color=PALETTE[i % len(PALETTE)]),
            )
        )
    radar_fig.update_layout(
        polar=dict(radialaxis=dict(visible=True, range=[0, 100], showticklabels=False)),
        showlegend=True,
        height=450,
    )
    st.plotly_chart(style_fig(radar_fig), use_container_width=True)
    st.caption("Each metric is scaled 0-100 across the selected peers so revenue, ratings, and review counts can share one chart. This shows relative shape, not absolute values — see the table below for raw numbers.")

    st.subheader("Full Comparison Table")
    table = metrics.copy()
    table["label"] = display_labels
    table = table.rename(columns={
        "label": level, "revenue": "Revenue ($)", "units_sold": "Units Sold", "orders": "Orders",
        "avg_price": "Avg Price ($)", "avg_rating": "Avg Rating", "review_count": "Reviews",
        "growth_pct": "Growth % (last 3mo vs prior 3mo)", "revenue_share_pct": "Revenue Share %",
    })
    table = table.drop(columns=["id"])
    st.dataframe(
        table.style.format({
            "Revenue ($)": "${:,.0f}", "Avg Price ($)": "${:,.2f}", "Avg Rating": "{:.2f}",
            "Growth % (last 3mo vs prior 3mo)": "{:+.1f}%", "Revenue Share %": "{:.1f}%",
        }, na_rep="n/a").background_gradient(subset=["Revenue ($)"], cmap="Blues"),
        use_container_width=True, hide_index=True,
    )
