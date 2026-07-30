import os
from dotenv import load_dotenv
from openai import OpenAI

load_dotenv()

client = OpenAI(
    api_key=os.getenv("GROK_API_KEY"),
    base_url="https://api.groq.com/openai/v1",
)


def ask_llm(user_prompt, system_prompt):

    response = client.chat.completions.create(
        model="llama-3.1-8b-instant",
        messages=[
            {
                "role": "user",
                "content": system_prompt
            },
            {
                "role": "system",
                "content": user_prompt
            }
        ],
        temperature=0.3,
    )

    return response.choices[0].message.content
