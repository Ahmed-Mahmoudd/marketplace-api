"""
HTTP client for the Marketplace API (Laravel).

This is the seam that turns the dashboard from a single-tenant CSV viewer into
a per-vendor SaaS surface. The client authenticates as a vendor with Sanctum
and pulls that vendor's analytics dataset; the API decides what "that vendor"
means from the bearer token, so the dashboard never gets to ask for someone
else's data.

Kept free of Streamlit imports so it stays usable from notebooks and scripts.
"""

from __future__ import annotations

import os
from dataclasses import dataclass, field
from datetime import date

import pandas as pd
import requests

DEFAULT_BASE_URL = os.environ.get("MARKETPLACE_API_URL", "http://localhost:8001/api")
DEFAULT_TIMEOUT = 30

# The column contract the dashboard's views and services rely on. Declared here
# so an empty result set still produces a DataFrame with the right columns —
# a brand new vendor with zero orders must render empty charts, not crash.
TABLE_COLUMNS: dict[str, list[str]] = {
    "products": [
        "product_id", "product_name", "slug", "category", "brand",
        "price", "stock", "status", "rating", "review_count", "created_at",
    ],
    "customers": ["user_id", "name", "email", "gender", "city", "signup_date"],
    # orders and order_items are held to the exact CSV column set: several
    # views merge them together and with products, and any extra shared column
    # name would turn into an `_x`/`_y` suffix pair and break those merges.
    "orders": ["order_id", "user_id", "order_date", "order_status", "total_amount"],
    "order_items": [
        "order_item_id", "order_id", "product_id", "user_id",
        "quantity", "item_price", "item_total",
    ],
    "reviews": [
        "review_id", "order_id", "product_id", "user_id",
        "rating", "review_text", "review_date",
    ],
    "events": [
        "event_id", "user_id", "product_id", "event_type",
        "session_id", "event_timestamp",
    ],
}

DATE_COLUMNS: dict[str, list[str]] = {
    "products": ["created_at"],
    "customers": ["signup_date"],
    "orders": ["order_date"],
    "reviews": ["review_date"],
    "events": ["event_timestamp"],
}

NUMERIC_COLUMNS: dict[str, list[str]] = {
    "products": ["price", "stock", "rating", "review_count"],
    "orders": ["total_amount"],
    "order_items": ["quantity", "item_price", "item_total"],
    "reviews": ["rating"],
}


class MarketplaceError(RuntimeError):
    """Any non-success response from the API, carrying the server's message."""

    def __init__(self, message: str, status_code: int | None = None):
        super().__init__(message)
        self.status_code = status_code


@dataclass
class VendorSession:
    """Who we're logged in as. This is the tenant identity for the whole app."""

    token: str
    user_id: int
    user_name: str
    email: str
    roles: list[str] = field(default_factory=list)
    vendor_id: int | None = None
    store_name: str | None = None
    store_status: str | None = None

    @property
    def is_vendor(self) -> bool:
        return "vendor" in self.roles

    @property
    def is_approved(self) -> bool:
        return self.store_status == "approved"


class MarketplaceClient:
    """Thin wrapper over the API's auth and vendor-analytics endpoints."""

    def __init__(self, base_url: str | None = None, token: str | None = None,
                 timeout: int = DEFAULT_TIMEOUT):
        self.base_url = (base_url or DEFAULT_BASE_URL).rstrip("/")
        self.token = token
        self.timeout = timeout

    # ---------------------------------------------------------------- requests

    def _headers(self) -> dict[str, str]:
        headers = {"Accept": "application/json", "Content-Type": "application/json"}

        if self.token:
            headers["Authorization"] = f"Bearer {self.token}"

        return headers

    def _request(self, method: str, path: str, **kwargs) -> dict:
        url = f"{self.base_url}/{path.lstrip('/')}"

        try:
            response = requests.request(
                method, url, headers=self._headers(), timeout=self.timeout, **kwargs
            )
        except requests.exceptions.ConnectionError as exc:
            raise MarketplaceError(
                f"Could not reach the marketplace API at {self.base_url}. "
                "Is `php artisan serve` running?"
            ) from exc
        except requests.exceptions.Timeout as exc:
            raise MarketplaceError(
                f"The marketplace API did not respond within {self.timeout}s."
            ) from exc

        try:
            payload = response.json()
        except ValueError:
            payload = {}

        if not response.ok:
            message = payload.get("message") or f"Request failed ({response.status_code})."
            errors = payload.get("errors")

            if isinstance(errors, dict):
                details = "; ".join(
                    f"{field}: {', '.join(msgs)}" if isinstance(msgs, list) else f"{field}: {msgs}"
                    for field, msgs in errors.items()
                )
                if details:
                    message = f"{message} ({details})"

            raise MarketplaceError(message, response.status_code)

        return payload.get("data") if "data" in payload else payload

    # ------------------------------------------------------------------- auth

    def login(self, email: str, password: str) -> VendorSession:
        """Authenticate, then resolve the vendor profile behind the account."""
        data = self._request("POST", "/auth/login", json={"email": email, "password": password})

        token = data.get("token") or data.get("access_token")

        if not token:
            raise MarketplaceError("The API did not return an auth token.")

        self.token = token

        user = data.get("user", {})
        session = VendorSession(
            token=token,
            user_id=user.get("id"),
            user_name=user.get("name", ""),
            email=user.get("email", email),
            roles=list(user.get("roles") or []),
        )

        # The API eager-loads roles and vendor on login, so this is usually
        # everything we need. /auth/me is the fallback if that ever changes.
        if not session.roles:
            me_user = self._request("GET", "/auth/me")
            session.roles = list(me_user.get("roles") or [])
            session.user_id = session.user_id or me_user.get("id")
            session.user_name = session.user_name or me_user.get("name", "")
            user = me_user

        self._attach_vendor(session, user.get("vendor"))

        return session

    def _attach_vendor(self, session: VendorSession, vendor: dict | None = None) -> None:
        """Best-effort: a customer account simply has no vendor profile."""
        if not isinstance(vendor, dict):
            try:
                vendor = self._request("GET", "/vendor/me")
            except MarketplaceError:
                return

        if not isinstance(vendor, dict):
            return

        session.vendor_id = vendor.get("id")
        session.store_name = vendor.get("store_name")
        session.store_status = vendor.get("status")

    def redeem_handoff(self, code: str) -> VendorSession:
        """Trade a one-time handoff code from the storefront for a session.

        Lets a vendor already signed into the storefront land here without a
        second login. The code is single-use and short-lived; the API rejects
        replays, so a stale URL simply falls back to the login screen.
        """
        data = self._request(
            "POST", "/auth/handoff/redeem", json={"code": code}
        )

        token = data.get("token")

        if not token:
            raise MarketplaceError("The sign-in link did not return a token.")

        self.token = token

        user = data.get("user", {})
        session = VendorSession(
            token=token,
            user_id=user.get("id"),
            user_name=user.get("name", ""),
            email=user.get("email", ""),
            roles=list(user.get("roles") or []),
        )

        self._attach_vendor(session, user.get("vendor"))

        return session

    def logout(self) -> None:
        if not self.token:
            return

        try:
            self._request("POST", "/auth/logout")
        except MarketplaceError:
            pass  # the local session is being dropped either way
        finally:
            self.token = None

    # -------------------------------------------------------------- analytics

    def fetch_dataset(
        self,
        since: date | None = None,
        until: date | None = None,
        tables: list[str] | None = None,
    ) -> dict[str, pd.DataFrame]:
        """Pull this vendor's analytics tables as typed DataFrames.

        The returned dict has the same keys and column names the CSV loader
        produces, so every existing view and service works unchanged.
        """
        params: dict[str, object] = {}

        if since:
            params["since"] = since.isoformat()
        if until:
            params["until"] = until.isoformat()
        if tables:
            params["tables[]"] = tables

        data = self._request("GET", "/vendor/analytics/dataset", params=params)
        raw_tables = data.get("tables", {})

        return {
            name: _to_dataframe(name, raw_tables.get(name, []))
            for name in TABLE_COLUMNS
            if tables is None or name in tables
        }


def _to_dataframe(name: str, rows: list[dict]) -> pd.DataFrame:
    """Build a typed frame, guaranteeing the declared columns exist."""
    columns = TABLE_COLUMNS[name]
    frame = pd.DataFrame(rows)

    for column in columns:
        if column not in frame.columns:
            frame[column] = pd.Series(dtype="object")

    frame = frame[columns]

    for column in DATE_COLUMNS.get(name, []):
        frame[column] = pd.to_datetime(frame[column], errors="coerce", format="mixed")

    for column in NUMERIC_COLUMNS.get(name, []):
        frame[column] = pd.to_numeric(frame[column], errors="coerce")

    return frame
