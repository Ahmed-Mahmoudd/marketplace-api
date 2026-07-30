import { type FormEvent, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { getFirstFieldError, getValidationErrors, getErrorMessage } from '@/api/client'
import { useAuth } from '@/context'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Card, CardBody, CardHeader, CardTitle } from '@/components/ui/Card'
import { Input } from '@/components/ui/Input'

export function RegisterPage() {
  const { register } = useAuth()
  const navigate = useNavigate()

  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    city: '',
  })
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const updateField = (field: keyof typeof form, value: string) => {
    setForm((current) => ({ ...current, [field]: value }))
  }

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setFormError(null)
    setErrors({})
    setIsSubmitting(true)

    try {
      await register({
        ...form,
        city: form.city || undefined,
      })
      navigate('/', { replace: true })
    } catch (error) {
      setErrors(getValidationErrors(error))
      setFormError(getErrorMessage(error, 'Unable to create account.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Create account</CardTitle>
        <p className="mt-1 text-sm text-muted">Register as a customer to start shopping.</p>
      </CardHeader>
      <CardBody>
        <form className="space-y-4" onSubmit={(event) => void handleSubmit(event)}>
          {formError ? <Alert message={formError} /> : null}

          <Input
            label="Full name"
            name="name"
            value={form.name}
            onChange={(event) => updateField('name', event.target.value)}
            error={getFirstFieldError(errors, 'name')}
            required
          />

          <Input
            label="Email"
            name="email"
            type="email"
            autoComplete="email"
            value={form.email}
            onChange={(event) => updateField('email', event.target.value)}
            error={getFirstFieldError(errors, 'email')}
            required
          />

          <Input
            label="City"
            name="city"
            value={form.city}
            onChange={(event) => updateField('city', event.target.value)}
            error={getFirstFieldError(errors, 'city')}
          />

          <Input
            label="Password"
            name="password"
            type="password"
            autoComplete="new-password"
            value={form.password}
            onChange={(event) => updateField('password', event.target.value)}
            error={getFirstFieldError(errors, 'password')}
            hint="Minimum 8 characters"
            required
          />

          <Input
            label="Confirm password"
            name="password_confirmation"
            type="password"
            autoComplete="new-password"
            value={form.password_confirmation}
            onChange={(event) => updateField('password_confirmation', event.target.value)}
            error={getFirstFieldError(errors, 'password_confirmation')}
            required
          />

          <Button type="submit" className="w-full" isLoading={isSubmitting}>
            Create account
          </Button>
        </form>

        <p className="mt-4 text-sm text-muted">
          Already have an account?{' '}
          <Link to="/login" className="font-medium text-accent hover:underline">
            Sign in
          </Link>
        </p>
      </CardBody>
    </Card>
  )
}
