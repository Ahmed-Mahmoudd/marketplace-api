"""
HTTP front door for the shopping assistant.

The assistant itself lives in `ai/` and is written in Python, while the
customer-facing storefront is the React app in the marketplace repo. This
service is the seam between the two: one small POST endpoint the storefront's
chat widget can call from the browser.

Run from the project root:

    uvicorn api.chat_api:app --reload --port 8090

Unlike the vendor dashboard, nothing here calls `tools.set_catalog()`: a
shopper is browsing the whole marketplace, so the tools fall back to the
shared catalog rather than any single vendor's slice.
"""

from __future__ import annotations

import logging
import os

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from ai.chatbot import process_message

logger = logging.getLogger(__name__)

# The storefront runs on Vite's dev server by default — including 5174, which
# is where Vite lands whenever 5173 is already taken. Override in production
# with a comma-separated list of the real origins.
DEFAULT_ORIGINS = (
    "http://localhost:5173,http://127.0.0.1:5173,"
    "http://localhost:5174,http://127.0.0.1:5174"
)

ALLOWED_ORIGINS = [
    origin.strip()
    for origin in os.environ.get("CHAT_CORS_ORIGINS", DEFAULT_ORIGINS).split(",")
    if origin.strip()
]

app = FastAPI(title="Shopping Assistant", version="1.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=ALLOWED_ORIGINS,
    allow_methods=["POST", "GET"],
    allow_headers=["Content-Type"],
)


class ChatRequest(BaseModel):
    # Capped because every message becomes an LLM prompt; a runaway paste
    # should be rejected here rather than billed upstream.
    message: str = Field(min_length=1, max_length=1000)


class ChatResponse(BaseModel):
    reply: str


@app.get("/health")
def health() -> dict[str, object]:
    """Liveness plus the one bit of config that silently breaks everything."""
    return {"status": "ok", "llm_configured": bool(os.getenv("GROK_API_KEY"))}


@app.post("/chat", response_model=ChatResponse)
def chat(request: ChatRequest) -> ChatResponse:
    # Defined with `def`, not `async def`, so Starlette runs it in a worker
    # thread — the agent's LLM call and pandas work are both blocking.
    message = request.message.strip()

    if not message:
        raise HTTPException(status_code=422, detail="Message cannot be empty.")

    try:
        reply = process_message(message)
    except Exception:
        # The underlying error can name the LLM provider or a data file, so it
        # is logged for us and generalised for the shopper.
        logger.exception("Assistant failed to answer: %r", message)
        raise HTTPException(
            status_code=502,
            detail="The assistant is unavailable right now. Please try again.",
        )

    return ChatResponse(reply=str(reply).strip() or "Sorry, I don't have an answer for that.")
