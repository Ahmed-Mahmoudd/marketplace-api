import pandas as pd
import plotly.express as px
import streamlit as st

from dashboard.utils import (
    CANCELLED_STATUSES,
    FULFILLED_STATUSES,
    PALETTE,
    RETURNED_STATUSES,
    fmt_currency,
    fmt_number,
    style_fig,
)


def render(data: dict):
    st.header("Business Overview")

    products = data["products"]
    customers = data["customers"]
    orders = data["orders"]
    order_items = data["order_items"]

    if orders.empty:
        st.info(
            "No orders yet. Once customers start buying from your store, your "
            "revenue, order and customer metrics will appear here."
        )
        return

    min_date, max_date = orders["order_date"].min().date(), orders["order_date"].max().date()
    if min_date < max_date:
        date_range = st.slider(
            "Order date range",
            min_value=min_date,
            max_value=max_date,
            value=(min_date, max_date),
            format="MMM YYYY",
        )
    else:
        # A single day of history gives the slider no range to work with.
        date_range = (min_date, max_date)
        st.caption(f"All orders are from {min_date:%d %b %Y}.")

    mask = (orders["order_date"].dt.date >= date_range[0]) & (orders["order_date"].dt.date <= date_range[1])
    orders_f = orders.loc[mask]

    completed_mask = orders_f["order_status"].isin(FULFILLED_STATUSES)
    completed_orders = orders_f.loc[completed_mask]

    order_products = order_items.merge(products, on="product_id", how="left")
    order_products = order_products.merge(
        orders_f[["order_id", "user_id", "order_status"]], on=["order_id", "user_id"], how="inner"
    )

    total_revenue = orders_f["total_amount"].sum()
    net_revenue = completed_orders["total_amount"].sum()
    total_orders = len(orders_f)
    total_customers = customers["user_id"].nunique()
    total_products = products["product_id"].nunique()
    aov = orders_f["total_amount"].mean() if total_orders else 0
    cancel_rate = orders_f["order_status"].isin(CANCELLED_STATUSES).mean() * 100 if total_orders else 0
    return_rate = orders_f["order_status"].isin(RETURNED_STATUSES).mean() * 100 if total_orders else 0

    c1, c2, c3, c4, c5 = st.columns(5)
    c1.metric("Gross Revenue", fmt_currency(total_revenue))
    c2.metric("Net Revenue (fulfilled)", fmt_currency(net_revenue))
    c3.metric("Orders", fmt_number(total_orders))
    c4.metric("Customers", fmt_number(total_customers))
    c5.metric("Avg Order Value", fmt_currency(aov))

    c6, c7, c8 = st.columns(3)
    c6.metric("Products Listed", fmt_number(total_products))
    c7.metric("Cancellation Rate", f"{cancel_rate:.1f}%")
    c8.metric("Return Rate", f"{return_rate:.1f}%")

    st.divider()

    left, right = st.columns(2)

    with left:
        monthly_sales = (
            orders_f.groupby(orders_f["order_date"].dt.to_period("M"))["total_amount"]
            .sum()
            .reset_index()
        )
        monthly_sales["order_date"] = monthly_sales["order_date"].astype(str)
        fig = px.line(
            monthly_sales, x="order_date", y="total_amount", markers=True,
            title="Monthly Revenue", color_discrete_sequence=[PALETTE[0]],
        )
        fig.update_layout(xaxis_title="Month", yaxis_title="Revenue ($)")
        st.plotly_chart(style_fig(fig), use_container_width=True)

    with right:
        category_sales = (
            order_products.groupby("category")["item_total"].sum().reset_index().sort_values("item_total", ascending=False)
        )
        fig2 = px.bar(
            category_sales, x="category", y="item_total", title="Revenue by Category",
            color="category", color_discrete_sequence=PALETTE,
        )
        fig2.update_layout(showlegend=False, xaxis_title="", yaxis_title="Revenue ($)")
        st.plotly_chart(style_fig(fig2), use_container_width=True)

    left2, right2 = st.columns(2)

    with left2:
        status_counts = orders_f["order_status"].value_counts().reset_index()
        status_counts.columns = ["order_status", "count"]
        fig3 = px.pie(
            status_counts, names="order_status", values="count", title="Order Status Breakdown",
            hole=0.5, color_discrete_sequence=PALETTE,
        )
        st.plotly_chart(style_fig(fig3), use_container_width=True)

    with right2:
        top_products = (
            order_products.groupby("product_name")["quantity"].sum().reset_index()
            .sort_values("quantity", ascending=False).head(10)
        )
        fig4 = px.bar(
            top_products, x="quantity", y="product_name", orientation="h", title="Top 10 Selling Products",
            color_discrete_sequence=[PALETTE[1]],
        )
        fig4.update_layout(yaxis=dict(categoryorder="total ascending"), xaxis_title="Units Sold", yaxis_title="")
        st.plotly_chart(style_fig(fig4), use_container_width=True)
