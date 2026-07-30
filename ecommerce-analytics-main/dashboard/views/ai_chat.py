import streamlit as st

from ai import tools
from ai.chatbot import process_message
from dashboard import auth


def render(data):

    st.header("🤖 AI Shopping Assistant")

    # Point the assistant's tools at the catalog currently on screen, so a
    # signed-in vendor gets answers about their own products.
    tools.set_catalog(data["products"])

    tenant = auth.current_tenant()

    # Chat history is per tenant — switching accounts must not surface the
    # previous vendor's conversation.
    if st.session_state.get("messages_tenant") != tenant:
        st.session_state.messages = []
        st.session_state.messages_tenant = tenant

    if "messages" not in st.session_state:
        st.session_state.messages = []

    for msg in st.session_state.messages:
        with st.chat_message(msg["role"]):
            st.markdown(msg["content"])

    prompt = st.chat_input("Ask me anything about our products...")

    if prompt:

        st.session_state.messages.append(
            {"role": "user", "content": prompt}
        )

        with st.chat_message("user"):
            st.markdown(prompt)

        try:
            response = process_message(prompt)
        except Exception as exc:
            response = f"Sorry — the assistant hit an error: {exc}"

        with st.chat_message("assistant"):
            st.markdown(response)

        st.session_state.messages.append(
            {"role": "assistant", "content": response}
        )
