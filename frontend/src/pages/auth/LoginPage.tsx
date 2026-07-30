import { type FormEvent, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { getFirstFieldError, getValidationErrors, getErrorMessage } from '@/api/client'
import { useAuth } from '@/context'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Card, CardBody, CardHeader, CardTitle } from '@/components/ui/Card'
import { Input } from '@/components/ui/Input'

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const redirectTo =
    typeof location.state === 'object' &&
    location.state &&
    'from' in location.state &&
    typeof location.state.from === 'string'
      ? location.state.from
      : '/'

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setFormError(null)
    setErrors({})
    setIsSubmitting(true)

    try {
      await login({ email, password })
      navigate(redirectTo, { replace: true })
    } catch (error) {
      setErrors(getValidationErrors(error))
      setFormError(getErrorMessage(error, 'Unable to sign in.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Sign in</CardTitle>
        <p className="mt-1 text-sm text-muted">Access your account to shop and manage orders.</p>
      </CardHeader>
      <CardBody>
        <form className="space-y-4" onSubmit={(event) => void handleSubmit(event)}>
          {formError ? <Alert message={formError} /> : null}

          <Input
            label="Email"
            name="email"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            error={getFirstFieldError(errors, 'email')}
            required
          />

          <Input
            label="Password"
            name="password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            error={getFirstFieldError(errors, 'password')}
            required
          />

          <Button type="submit" className="w-full" isLoading={isSubmitting}>
            Sign in
          </Button>
        </form>

        <p className="mt-4 text-sm text-muted">
          No account yet?{' '}
          <Link to="/register" className="font-medium text-accent hover:underline">
            Create one
          </Link>
        </p>
      </CardBody>
    </Card>
  )
}
