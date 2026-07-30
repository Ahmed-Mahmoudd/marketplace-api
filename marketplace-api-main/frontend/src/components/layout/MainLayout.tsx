import { Outlet } from 'react-router-dom'
import { Header } from './Header'
import { Footer } from './Footer'
import { ChatWidget } from '@/components/chat/ChatWidget'

export function MainLayout() {
  return (
    <div className="flex min-h-screen flex-col">
      <Header />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
      {/* Mounted on the layout, not a page, so the thread survives navigation. */}
      <ChatWidget />
    </div>
  )
}
