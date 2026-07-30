import streamlit as st

from dashboard import auth
from dashboard.data import get_similarity_matrix
from dashboard.utils import PALETTE, fmt_currency, fmt_rating
from services.recommendation_service import recommend_products


def render(data: dict):
    st.header("⭐ Product Recommendations")
    st.caption(
        "Item-based collaborative filtering: products bought by the same customers are 'similar'. "
        "Results are then re-ranked to prefer the same category and brand (see "
        "`notebooks/Product_Recommendation.ipynb`)."
    )

    products = data["products"]

    if products.empty:
        st.info("You have no products listed yet.")
        return

    sim_df = get_similarity_matrix(auth.current_tenant(), data["order_items"])

    if sim_df is None or sim_df.empty:
        st.info(
            "Recommendations are built from co-purchase patterns. Once customers "
            "start buying more than one of your products together, they'll appear here."
        )
        return

    # product_id is a string in the CSV dataset and an integer from the API, so
    # build the labels from an explicit string cast rather than concatenation.
    labels = {
        f"{row.product_id} - {row.product_name}": row.product_id
        for row in products.itertuples()
    }
    choice = st.selectbox("Pick a product", sorted(labels), index=0)
    product_id = labels[choice]
    top_n = st.slider("Number of recommendations", 3, 12, 6)

    selected = products.loc[products["product_id"] == product_id].iloc[0]

    st.divider()
    with st.container(border=True):
        st.markdown(f"**Selected: {selected['product_name']}**")
        s1, s2, s3, s4 = st.columns(4)
        s1.metric("Category", selected["category"])
        s2.metric("Brand", selected["brand"])
        s3.metric("Price", fmt_currency(selected["price"]))
        s4.metric("Rating", fmt_rating(selected["rating"]))

    st.subheader("Customers Who Bought This Also Bought")
    recs = recommend_products(sim_df, products, product_id, top_n=top_n)

    if recs.empty:
        st.info("Not enough purchase overlap in the same category to recommend similar products for this item.")
        return

    cols = st.columns(3)
    for i, (_, row) in enumerate(recs.iterrows()):
        with cols[i % 3]:
            with st.container(border=True):
                st.markdown(f"**{row['product_name']}**")
                st.caption(f"{row['category']} · {row['brand']}")
                st.write(f"{fmt_currency(row['price'])}  ·  {fmt_rating(row['rating'])}")
                st.progress(min(max(row["similarity"], 0), 1.0), text=f"Similarity: {row['similarity']:.2f}")
                if row["brand_match"]:
                    st.caption("🏷️ Same brand")

    with st.expander("See full recommendation table"):
        st.dataframe(
            recs[["product_id", "product_name", "category", "brand", "price", "rating", "similarity", "brand_match"]],
            use_container_width=True, hide_index=True,
        )
