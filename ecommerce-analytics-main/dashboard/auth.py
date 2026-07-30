"""
Vendor authentication and tenant state for the dashboard.

The dashboard runs in one of two modes, held in `st.session_state`:

* **live**  — a vendor is logged into the Marketplace API and every page shows
  only that vendor's data. This is the SaaS path.
* **demo**  — the bundled CSV dataset, for presentations and for exercising the
  ML pages without a running API.

`current_tenant()` is the cache key for all data loading, so switching vendors
(or switching to demo mode) can never serve another tenant's cached frames.
"""

from __future__ import annotations

import streamlit as st

from src.api_client import DEFAULT_BASE_URL, MarketplaceClient, MarketplaceError, VendorSession

MODE_LIVE = "live"
MODE_DEMO = "demo"


# --------------------------------------------------------------- session state


def _init_state() -> None:
    st.session_state.setdefault("mode", None)
    st.session_state.setdefault("session", None)
    st.session_state.setdefault("api_base_url", DEFAULT_BASE_URL)
    st.session_state.setdefault("embedded", False)


def is_embedded() -> bool:
    """True when rendered inside the storefront's Analytics tab."""
    _init_state()
    return bool(st.session_state["embedded"])


def consume_handoff() -> None:
    """Sign in from a one-time `?code=` handed over by the storefront.

    Called before anything renders. The code is spent immediately and stripped
    from the URL so a refresh (or a shared link) can't replay it — the API
    would reject it anyway, but it shouldn't linger in the address bar.

    The API base URL is deliberately taken from configuration, never from the
    query string: accepting one would let a crafted link post the code to an
    attacker-controlled host.
    """
    _init_state()

    params = st.query_params

    # Not `embed` — Streamlit reserves that one (and `embed_options`) for its
    # own chrome-hiding and strips it from query_params before we see it.
    if params.get("embedded") in ("1", "true", "True"):
        st.session_state["embedded"] = True

    code = params.get("code")

    if not code or st.session_state["session"] is not None:
        # Nothing to do, or a session already exists — drop a stale code.
        if code:
            del st.query_params["code"]
        return

    del st.query_params["code"]

    client = MarketplaceClient(base_url=st.session_state["api_base_url"])

    try:
        session = client.redeem_handoff(code)
    except MarketplaceError as exc:
        st.session_state["handoff_error"] = str(exc)
        return

    if not session.is_vendor or session.vendor_id is None:
        st.session_state["handoff_error"] = "That account isn't a vendor."
        return

    if not session.is_approved:
        st.session_state["handoff_error"] = (
            f"This store is {session.store_status}; analytics unlock on approval."
        )
        return

    st.session_state["mode"] = MODE_LIVE
    st.session_state["session"] = session
    st.cache_data.clear()
    st.cache_resource.clear()


def mode() -> str | None:
    _init_state()
    return st.session_state["mode"]


def vendor_session() -> VendorSession | None:
    _init_state()
    return st.session_state["session"]


def current_tenant() -> str:
    """Stable identifier for whoever's data is on screen — the cache key."""
    session = vendor_session()

    if mode() == MODE_LIVE and session:
        return f"vendor:{session.vendor_id}"

    return "demo:csv"


def get_client() -> MarketplaceClient:
    session = vendor_session()

    return MarketplaceClient(
        base_url=st.session_state["api_base_url"],
        token=session.token if session else None,
    )


def is_authenticated() -> bool:
    return mode() == MODE_DEMO or (mode() == MODE_LIVE and vendor_session() is not None)


def logout() -> None:
    session = vendor_session()

    if session:
        try:
            get_client().logout()
        except MarketplaceError:
            pass

    st.session_state["mode"] = None
    st.session_state["session"] = None
    st.cache_data.clear()
    st.cache_resource.clear()


# ----------------------------------------------------------------- login screen


def render_login() -> None:
    """Full-page login. Rendered instead of the dashboard until a mode is set."""
    _init_state()

    st.markdown(
        "<h1 style='text-align:center;margin-bottom:0'>🛒 Vendor Analytics</h1>"
        "<p style='text-align:center;color:#8b949e;margin-top:4px'>"
        "Sign in with your marketplace vendor account</p>",
        unsafe_allow_html=True,
    )

    _, middle, _ = st.columns([1, 1.4, 1])

    # A handoff from the storefront that didn't take — say why, here, rather
    # than showing a bare login form that looks like the link did nothing.
    error = st.session_state.pop("handoff_error", None)

    if error:
        with middle:
            st.error(f"{error} Please sign in below.")

    with middle:
        with st.form("vendor_login"):
            email = st.text_input("Email", placeholder="vendor-a@example.com")
            password = st.text_input("Password", type="password")

            with st.expander("API settings"):
                base_url = st.text_input(
                    "Marketplace API URL",
                    value=st.session_state["api_base_url"],
                    help="Base URL of the Laravel API, e.g. http://localhost:8001/api",
                )

            submitted = st.form_submit_button("Sign in", use_container_width=True, type="primary")

        if submitted:
            st.session_state["api_base_url"] = base_url.strip() or DEFAULT_BASE_URL
            _attempt_login(email.strip(), password)

        st.divider()
        st.caption("No API running? Explore the dashboard with the bundled sample dataset.")

        if st.button("Continue in demo mode", use_container_width=True):
            st.session_state["mode"] = MODE_DEMO
            st.session_state["session"] = None
            st.rerun()


def _attempt_login(email: str, password: str) -> None:
    if not email or not password:
        st.error("Enter both an email and a password.")
        return

    client = MarketplaceClient(base_url=st.session_state["api_base_url"])

    try:
        with st.spinner("Signing in..."):
            session = client.login(email, password)
    except MarketplaceError as exc:
        st.error(str(exc))
        return

    if not session.is_vendor:
        st.error(
            "This account isn't a vendor. Apply for a vendor account in the "
            "marketplace first, then sign in here once it's approved."
        )
        return

    if session.vendor_id is None:
        st.error("No vendor profile is attached to this account.")
        return

    if not session.is_approved:
        st.warning(
            f"Your store is **{session.store_status}**. Analytics unlock once an "
            "admin approves the account."
        )
        return

    st.session_state["mode"] = MODE_LIVE
    st.session_state["session"] = session
    # A previous tenant's frames must never survive a login.
    st.cache_data.clear()
    st.cache_resource.clear()
    st.rerun()


def render_sidebar_account() -> None:
    """Account block shown in the sidebar once signed in."""
    session = vendor_session()

    if mode() == MODE_DEMO:
        st.info("**Demo mode**\n\nShowing the bundled sample dataset.")

        if st.button("Sign in as a vendor", use_container_width=True):
            logout()
            st.rerun()

        return

    if session:
        st.markdown(f"**{session.store_name}**")
        st.caption(f"{session.user_name} · {session.email}")

        # Embedded in the storefront, the surrounding app owns the session —
        # signing out here would leave the two sides disagreeing about who is
        # logged in, so the storefront's own account menu handles it.
        if not is_embedded():
            if st.button("Sign out", use_container_width=True):
                logout()
                st.rerun()
