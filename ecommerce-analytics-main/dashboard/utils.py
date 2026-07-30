"""Small shared helpers used across dashboard pages."""

import pandas as pd
import plotly.express as px
import plotly.graph_objects as go

# One color palette reused everywhere so the whole dashboard feels
# consistent instead of every chart picking its own default colors.
PALETTE = ["#4C6FFF", "#00C2A8", "#FF6B6B", "#FFB020", "#8B5CF6", "#3AB0FF", "#F472B6", "#22C55E"]
ACCENT = "#4C6FFF"
POSITIVE = "#22C55E"
NEGATIVE = "#FF6B6B"

CHART_TEMPLATE = "plotly_white"

# Order-status vocabulary. The sample CSVs use "completed"/"returned"; the
# marketplace API uses Laravel's lifecycle (pending -> confirmed -> processing
# -> shipped -> delivered, or cancelled). Both are listed so the same view code
# reads correctly against either source.
FULFILLED_STATUSES = ("completed", "delivered", "shipped")
CANCELLED_STATUSES = ("cancelled", "canceled")
RETURNED_STATUSES = ("returned", "refunded")


def style_fig(fig: go.Figure, height: int | None = None) -> go.Figure:
    """Apply consistent margins/fonts to any plotly figure."""
    fig.update_layout(
        template=CHART_TEMPLATE,
        margin=dict(l=10, r=10, t=50, b=10),
        font=dict(family="Inter, sans-serif", size=13),
        legend=dict(orientation="h", yanchor="bottom", y=1.02, xanchor="right", x=1),
    )
    if height:
        fig.update_layout(height=height)
    return fig


def fmt_currency(value: float) -> str:
    if value is None or pd.isna(value):
        return "-"
    if abs(value) >= 1_000_000:
        return f"${value / 1_000_000:,.2f}M"
    if abs(value) >= 1_000:
        return f"${value / 1_000:,.1f}K"
    return f"${value:,.2f}"


def fmt_number(value: float) -> str:
    if value is None or pd.isna(value):
        return "-"
    if abs(value) >= 1_000_000:
        return f"{value / 1_000_000:,.2f}M"
    if abs(value) >= 1_000:
        return f"{value / 1_000:,.1f}K"
    return f"{value:,.0f}"


def fmt_rating(value: float) -> str:
    """Ratings are None until a product has its first review."""
    if value is None or pd.isna(value):
        return "No ratings yet"
    return f"{value:.2f} ⭐"


def fmt_pct(value: float, decimals: int = 1) -> str:
    if value is None or pd.isna(value):
        return "-"
    return f"{value:+.{decimals}f}%"


def _to_rgba(hex_color: str, alpha: float = 0.12) -> str:
    """Convert a '#RRGGBB' hex color to a valid 'rgba(r,g,b,a)' string."""
    hex_color = hex_color.lstrip("#")
    r, g, b = int(hex_color[0:2], 16), int(hex_color[2:4], 16), int(hex_color[4:6], 16)
    return f"rgba({r},{g},{b},{alpha})"


def sparkline(monthly: pd.DataFrame, x_col: str, y_col: str, color: str = ACCENT) -> go.Figure:
    """A tiny, axis-free line chart used inside peer comparison cards."""
    fig = go.Figure(
        go.Scatter(
            x=monthly[x_col],
            y=monthly[y_col],
            mode="lines",
            line=dict(color=color, width=2),
            fill="tozeroy",
            fillcolor=_to_rgba(color),
        )
    )
    fig.update_layout(
        height=70,
        margin=dict(l=0, r=0, t=0, b=0),
        xaxis=dict(visible=False),
        yaxis=dict(visible=False),
        showlegend=False,
        paper_bgcolor="rgba(0,0,0,0)",
        plot_bgcolor="rgba(0,0,0,0)",
    )
    return fig
