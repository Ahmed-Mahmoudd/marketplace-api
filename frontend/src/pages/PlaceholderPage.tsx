import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/Alert'

export function PlaceholderPage({
  title,
  description,
}: {
  title: string
  description: string
}) {
  return (
    <div className="container-app py-8">
      <EmptyState
        title={title}
        description={description}
        action={
          <Link to="/">
            <Button variant="secondary">Back to home</Button>
          </Link>
        }
      />
    </div>
  )
}
