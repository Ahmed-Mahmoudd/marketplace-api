import { useEffect, useRef, useState } from 'react'
import { MessageCircle, Send, Sparkles, X } from 'lucide-react'
import { getChatErrorMessage, sendChatMessage } from '@/api/chat'
import { cn } from '@/lib/utils'

interface ChatMessage {
  id: string
  role: 'user' | 'assistant'
  content: string
  isError?: boolean
}

const STORAGE_KEY = 'shop-assistant-thread'

const GREETING =
  "Hi! I'm your shopping assistant. Ask me about products, prices, comparisons, or your order."

const SUGGESTIONS = [
  'What are the best-rated headphones?',
  'Show me laptops under $800',
  'How do I return an item?',
]

// The thread survives a reload but not a new tab or a closed browser — long
// enough to be convenient, short enough that the next shopper on a shared
// machine starts clean.
function loadThread(): ChatMessage[] {
  try {
    const stored = sessionStorage.getItem(STORAGE_KEY)
    return stored ? (JSON.parse(stored) as ChatMessage[]) : []
  } catch {
    return []
  }
}

export function ChatWidget() {
  const [isOpen, setIsOpen] = useState(false)
  const [messages, setMessages] = useState<ChatMessage[]>(loadThread)
  const [input, setInput] = useState('')
  const [isSending, setIsSending] = useState(false)

  const inputRef = useRef<HTMLInputElement>(null)
  const threadEndRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages))
    } catch {
      // A full or disabled sessionStorage must not break the conversation.
    }
  }, [messages])

  useEffect(() => {
    if (isOpen) {
      inputRef.current?.focus()
    }
  }, [isOpen])

  useEffect(() => {
    threadEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages, isSending, isOpen])

  useEffect(() => {
    if (!isOpen) return

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setIsOpen(false)
    }

    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [isOpen])

  const ask = async (question: string) => {
    const trimmed = question.trim()
    if (!trimmed || isSending) return

    setMessages((current) => [
      ...current,
      { id: crypto.randomUUID(), role: 'user', content: trimmed },
    ])
    setInput('')
    setIsSending(true)

    try {
      const reply = await sendChatMessage(trimmed)
      setMessages((current) => [
        ...current,
        { id: crypto.randomUUID(), role: 'assistant', content: reply },
      ])
    } catch (error) {
      setMessages((current) => [
        ...current,
        {
          id: crypto.randomUUID(),
          role: 'assistant',
          content: getChatErrorMessage(error),
          isError: true,
        },
      ])
    } finally {
      setIsSending(false)
      inputRef.current?.focus()
    }
  }

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault()
    void ask(input)
  }

  return (
    <>
      {isOpen && (
        <div
          role="dialog"
          aria-label="Shopping assistant"
          className={cn(
            'fixed z-50 flex flex-col overflow-hidden rounded-lg border border-border bg-surface shadow-md',
            'inset-x-4 bottom-4 top-20 sm:inset-x-auto sm:top-auto sm:right-6 sm:bottom-24 sm:h-[32rem] sm:w-96',
          )}
        >
          <div className="flex items-center justify-between gap-3 border-b border-border bg-accent px-4 py-3 text-accent-foreground">
            <div className="flex items-center gap-2">
              <Sparkles className="size-4" strokeWidth={2.25} />
              <div>
                <p className="text-sm font-semibold leading-tight">Shopping assistant</p>
                <p className="text-xs opacity-80">Ask about products or orders</p>
              </div>
            </div>

            <button
              type="button"
              onClick={() => setIsOpen(false)}
              aria-label="Close chat"
              className="rounded-md p-1 transition-colors hover:bg-accent-hover"
            >
              <X className="size-4" />
            </button>
          </div>

          <div className="flex-1 space-y-3 overflow-y-auto px-4 py-4">
            <Bubble role="assistant">{GREETING}</Bubble>

            {messages.length === 0 && (
              <div className="space-y-2 pt-1">
                {SUGGESTIONS.map((suggestion) => (
                  <button
                    key={suggestion}
                    type="button"
                    onClick={() => void ask(suggestion)}
                    className="block w-full rounded-md border border-border bg-surface-muted px-3 py-2 text-left text-sm text-muted transition-colors hover:border-accent hover:text-foreground"
                  >
                    {suggestion}
                  </button>
                ))}
              </div>
            )}

            {messages.map((message) => (
              <Bubble key={message.id} role={message.role} isError={message.isError}>
                {message.content}
              </Bubble>
            ))}

            {isSending && (
              <Bubble role="assistant">
                <span className="inline-flex gap-1 py-1" aria-label="Assistant is typing">
                  <Dot />
                  <Dot delay="150ms" />
                  <Dot delay="300ms" />
                </span>
              </Bubble>
            )}

            <div ref={threadEndRef} />
          </div>

          <form
            onSubmit={handleSubmit}
            className="flex items-center gap-2 border-t border-border bg-surface px-3 py-3"
          >
            <input
              ref={inputRef}
              value={input}
              onChange={(event) => setInput(event.target.value)}
              maxLength={1000}
              placeholder="Ask me anything…"
              aria-label="Message"
              className="h-10 flex-1 rounded-md border border-border bg-surface px-3 text-sm text-foreground placeholder:text-muted-foreground focus:border-accent focus:outline-none"
            />

            <button
              type="submit"
              disabled={!input.trim() || isSending}
              aria-label="Send message"
              className="inline-flex size-10 shrink-0 items-center justify-center rounded-md bg-accent text-accent-foreground transition-colors hover:bg-accent-hover disabled:pointer-events-none disabled:opacity-50"
            >
              <Send className="size-4" />
            </button>
          </form>
        </div>
      )}

      <button
        type="button"
        onClick={() => setIsOpen((open) => !open)}
        aria-label={isOpen ? 'Close shopping assistant' : 'Open shopping assistant'}
        aria-expanded={isOpen}
        className="fixed bottom-6 right-6 z-50 inline-flex size-14 items-center justify-center rounded-full bg-accent text-accent-foreground shadow-md transition-colors hover:bg-accent-hover"
      >
        {isOpen ? <X className="size-6" /> : <MessageCircle className="size-6" />}
      </button>
    </>
  )
}

function Bubble({
  role,
  isError,
  children,
}: {
  role: ChatMessage['role']
  isError?: boolean
  children: React.ReactNode
}) {
  const isUser = role === 'user'

  return (
    <div className={cn('flex', isUser ? 'justify-end' : 'justify-start')}>
      <div
        className={cn(
          'max-w-[85%] whitespace-pre-wrap rounded-lg px-3 py-2 text-sm',
          isUser && 'bg-accent text-accent-foreground',
          !isUser && !isError && 'bg-surface-muted text-foreground',
          !isUser && isError && 'bg-danger-muted text-danger',
        )}
      >
        {children}
      </div>
    </div>
  )
}

function Dot({ delay = '0ms' }: { delay?: string }) {
  return (
    <span
      className="size-1.5 animate-bounce rounded-full bg-muted-foreground"
      style={{ animationDelay: delay }}
    />
  )
}
