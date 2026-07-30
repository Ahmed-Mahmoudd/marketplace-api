import plotly.express as px
import streamlit as st

from dashboard.utils import PALETTE, fmt_currency, fmt_number, style_fig


def render(data: dict):
    st.header("Product Catalog")

    products = data["products"]
    order_items = data["order_items"]

    if products.empty:
        st.info("You have no products listed yet. Add products in the marketplace to see them here.")
        return

    low, high = float(products["price"].min()), float(products["price"].max())

    f1, f2, f3 = st.columns([2, 2, 3])
    with f1:
        categories = st.multiselect("Category", sorted(products["category"].dropna().unique()))
    with f2:
        brands = st.multiselect("Brand", sorted(products["brand"].dropna().unique()))
    with f3:
        if low < high:
            price_range = st.slider("Price range ($)", low, high, (low, high))
        else:
            # A single price point leaves the slider no range to span.
            price_range = (low, high)
            st.caption(f"All products are priced at ${low:,.2f}.")

    filtered = products.copy()
    if categories:
        filtered = filtered[filtered["category"].isin(categories)]
    if brands:
        filtered = filtered[filtered["brand"].isin(brands)]
    filtered = filtered[filtered["price"].between(*price_range)]

    sales = order_items.merge(filtered[["product_id"]], on="product_id", how="inner")
    sales = sales.merge(products, on="product_id", how="left")

    c1, c2, c3, c4 = st.columns(4)
    c1.metric("Products in view", fmt_number(len(filtered)))
    c2.metric("Avg Price", fmt_currency(filtered["price"].mean()) if len(filtered) else "-")
    c3.metric("Avg Rating", f"{filtered['rating'].mean():.2f}" if len(filtered) else "-")
    c4.metric("Revenue (filtered)", fmt_currency(sales["item_total"].sum()))

    st.divider()
    left, right = st.columns(2)
    with left:
        fig = px.histogram(filtered, x="price", nbins=30, title="Price Distribution", color_discrete_sequence=[PALETTE[0]])
        fig.update_layout(xaxis_title="Price ($)", yaxis_title="Number of Products")
        st.plotly_chart(style_fig(fig), use_container_width=True)
    with right:
        fig2 = px.histogram(filtered, x="rating", nbins=15, title="Rating Distribution", color_discrete_sequence=[PALETTE[2]])
        fig2.update_layout(xaxis_title="Rating", yaxis_title="Number of Products")
        st.plotly_chart(style_fig(fig2), use_container_width=True)

    st.subheader("Price vs. Rating by Category")
    units_sold = order_items.groupby("product_id")["quantity"].sum().rename("units_sold")
    scatter_df = filtered.merge(units_sold, on="product_id", how="left")
    scatter_df["units_sold"] = scatter_df["units_sold"].fillna(0)
    fig3 = px.scatter(
        scatter_df, x="price", y="rating", color="category", size="units_sold",
        hover_name="product_name", size_max=30, color_discrete_sequence=PALETTE,
    )
    st.plotly_chart(style_fig(fig3, height=450), use_container_width=True)

    left2, right2 = st.columns(2)
    with left2:
        st.subheader("Top Rated Products")
        top_rated = filtered.sort_values("rating", ascending=False).head(10)[["product_name", "category", "brand", "price", "rating"]]
        st.dataframe(top_rated, use_container_width=True, hide_index=True)
    with right2:
        st.subheader("Brand Performance")
        brand_perf = (
            sales.groupby("brand").agg(revenue=("item_total", "sum"), units_sold=("quantity", "sum"))
            .reset_index().sort_values("revenue", ascending=False).head(10)
        )
        brand_perf["revenue"] = brand_perf["revenue"].round(2)
        st.dataframe(brand_perf, use_container_width=True, hide_index=True)

    with st.expander("Browse filtered catalog"):
        st.dataframe(
            filtered[["product_id", "product_name", "category", "brand", "price", "rating"]],
            use_container_width=True, hide_index=True,
        )
