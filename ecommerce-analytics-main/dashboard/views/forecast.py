import plotly.graph_objects as go
import streamlit as st

from dashboard import auth
from dashboard.data import get_forecast, get_forecast_model
from dashboard.utils import PALETTE, fmt_currency, style_fig
from services.forecast_service import build_monthly_sales

# Prophet needs a couple of seasons of history before a forecast means anything.
MIN_MONTHS = 4


def render(data: dict):
    st.header("📈 Sales Forecast")
    st.caption("Prophet time-series model trained on monthly revenue (see `notebooks/Predictive_Models.ipynb.ipynb`).")

    tenant = auth.current_tenant()
    monthly_sales = build_monthly_sales(data["order_items"], data["orders"])

    if len(monthly_sales) < MIN_MONTHS:
        st.info(
            f"Your store has {len(monthly_sales)} month(s) of sales history. "
            f"Forecasting needs at least {MIN_MONTHS} months — come back once "
            "more orders have come in."
        )
        return

    model, source = get_forecast_model(tenant, monthly_sales)

    if source == "saved":
        st.success("Using the trained model from `models/sales_forecast_model.pkl`.", icon="✅")
    else:
        st.warning("Saved model couldn't be loaded, so a fresh Prophet model was fit on the current data instead.", icon="⚠️")

    horizon = st.slider("Forecast horizon (months)", 1, 12, 6)
    forecast = get_forecast(tenant, model, horizon)

    last_actual_date = monthly_sales["ds"].max()
    history = forecast[forecast["ds"] <= last_actual_date]
    future = forecast[forecast["ds"] > last_actual_date]

    last_actual_value = monthly_sales.loc[monthly_sales["ds"] == last_actual_date, "y"].values[0]
    next_month_forecast = future["yhat"].iloc[0] if len(future) else None
    total_forecast_revenue = future["yhat"].sum()

    c1, c2, c3 = st.columns(3)
    c1.metric("Last Actual Month", fmt_currency(last_actual_value))
    c2.metric(
        "Next Month Forecast",
        fmt_currency(next_month_forecast) if next_month_forecast is not None else "-",
        delta=f"{(next_month_forecast - last_actual_value) / last_actual_value * 100:+.1f}%" if next_month_forecast else None,
    )
    c3.metric(f"Total Forecast ({horizon} mo)", fmt_currency(total_forecast_revenue))

    fig = go.Figure()
    fig.add_trace(go.Scatter(
        x=monthly_sales["ds"], y=monthly_sales["y"], mode="markers+lines",
        name="Actual Revenue", line=dict(color=PALETTE[0]),
    ))
    fig.add_trace(go.Scatter(
        x=future["ds"], y=future["yhat"], mode="lines+markers",
        name="Forecast", line=dict(color=PALETTE[2], dash="dash"),
    ))
    fig.add_trace(go.Scatter(
        x=list(future["ds"]) + list(future["ds"])[::-1],
        y=list(future["yhat_upper"]) + list(future["yhat_lower"])[::-1],
        fill="toself", fillcolor="rgba(255,107,107,0.15)", line=dict(color="rgba(0,0,0,0)"),
        name="Confidence Interval", showlegend=True,
    ))
    fig.update_layout(title="Monthly Revenue: Actual vs. Forecast", xaxis_title="Month", yaxis_title="Revenue ($)")
    st.plotly_chart(style_fig(fig, height=450), use_container_width=True)

    left, right = st.columns([2, 1])
    with left:
        st.subheader("Trend")
        trend_fig = go.Figure(go.Scatter(x=forecast["ds"], y=forecast["trend"], mode="lines", line=dict(color=PALETTE[4])))
        trend_fig.update_layout(title="Underlying Revenue Trend (model component)", xaxis_title="Month", yaxis_title="Revenue ($)")
        st.plotly_chart(style_fig(trend_fig), use_container_width=True)
        st.caption(
            "With ~23 months of history, Prophet didn't detect a reliable yearly seasonal pattern "
            "(needs 2+ years of data), so the forecast is driven by trend only — no seasonal component is shown "
            "because the model doesn't have one."
        )
    with right:
        st.subheader("Forecast Table")
        table = future[["ds", "yhat", "yhat_lower", "yhat_upper"]].rename(
            columns={"ds": "Month", "yhat": "Forecast", "yhat_lower": "Low", "yhat_upper": "High"}
        )
        table["Month"] = table["Month"].dt.strftime("%Y-%m")
        st.dataframe(
            table.style.format({"Forecast": "${:,.0f}", "Low": "${:,.0f}", "High": "${:,.0f}"}),
            use_container_width=True, hide_index=True,
        )
        st.download_button(
            "Download forecast as CSV",
            table.to_csv(index=False).encode("utf-8"),
            file_name="sales_forecast.csv",
            mime="text/csv",
        )
