import axios from 'axios'

// The assistant is a separate Python service, not part of the Laravel API, so
// it gets its own client: different origin, and deliberately no auth token —
// shoppers can ask about the catalog without signing in.
const baseURL = import.meta.env.VITE_CHAT_API_URL ?? 'http://127.0.0.1:8090'

const chatClient = axios.create({
  baseURL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  // The agent picks a tool, queries the catalog, then writes an answer with an
  // LLM — comfortably slower than a normal API call.
  timeout: 45_000,
})

export async function sendChatMessage(message: string): Promise<string> {
  const { data } = await chatClient.post<{ reply: string }>('/chat', { message })
  return data.reply
}

export function getChatErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    if (error.code === 'ECONNABORTED') {
      return 'That took too long to answer. Please try again.'
    }

    const detail = (error.response?.data as { detail?: string } | undefined)?.detail

    return detail ?? 'I could not reach the assistant. Please try again in a moment.'
  }

  return 'Something went wrong. Please try again.'
}
