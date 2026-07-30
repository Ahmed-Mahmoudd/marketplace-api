import json
from urllib import response

from ai.llm import ask_llm
from ai.prompts import SYSTEM_PROMPT
from ai import tools

# Returned when the model didn't pick a usable tool. Answered with a fixed
# reply rather than another LLM call: there is nothing retrieved to ground it,
# so a generated answer here is exactly where the assistant would invent facts.
NO_TOOL = {"no_tool": True}

FALLBACK_REPLY = (
    "Hi! I can help you find products, compare two of them, or answer "
    "questions about orders, returns and payments. What are you looking for?"
)


def choose_tool(user_message):

    response = ask_llm(
        SYSTEM_PROMPT,
        user_message
    )

    print("LLM Response:")
    print(repr(response))

    try:
     return json.loads(response)
    except Exception:
     print("Failed to parse JSON")
     return {"tool": "debug", "raw": response} 

def run_agent(user_message):

    action = choose_tool(user_message)

    # Every lookup below is a .get(): the model picks the tool *and* its
    # arguments, and on small talk ("hello") it answers with a bare {}.
    # A missing key must not blow up the whole request.
    tool = action.get("tool")

    if tool == "search_faq":

        result = tools.search_faq(
            action.get("question") or user_message
        )

    elif tool == "best_products":

        result = tools.best_products(
            category=action.get("category")
        )

    elif tool == "search_products":

        result = tools.search_products(
            category=action.get("category"),
            min_price=action.get("min_price"),
            max_price=action.get("max_price"),
            min_rating=action.get("min_rating")
        )

    elif tool == "compare_products":

        product1 = action.get("product1")
        product2 = action.get("product2")

        if not product1 or not product2:
            return NO_TOOL

        result = tools.compare_products(product1, product2)

    else:

        result = NO_TOOL

    return result

def generate_answer(user_message):

    result = run_agent(user_message)

    if result is NO_TOOL:
        return FALLBACK_REPLY

    if (
        isinstance(result, list)
        and len(result) > 0
        and "answer" in result[0]
    ):

        answers = []

        for item in result:
            answers.append(item["answer"])

        return "\n\n".join(answers)

    prompt = f"""
User Question:
{user_message}

Retrieved Data:
{result}

Write a helpful and friendly response using ONLY the retrieved information.
Do not invent any information.
"""

    return ask_llm(
        "You are a helpful E-Commerce shopping assistant.",
        prompt
    )
if __name__ == "__main__":

    print(
        generate_answer(
            "I forgot my password"
        )
    )