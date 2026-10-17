import { verifyAuth } from '@supabase/server/core'

import { validateEnvironment } from './config.mjs'

const json = (body, status = 200) => Response.json(body, {
  status,
  headers: { 'Cache-Control': 'no-store' },
})

export function createHandler({
  env = process.env,
  verify = verifyAuth,
  getUser = getUserFromAuth,
} = {}) {
  return async function handle(request) {
    const url = new URL(request.url)

    if (url.pathname === '/health') {
      if (request.method !== 'GET') {
        return json({ success: false, error: { code: 'METHOD_NOT_ALLOWED' } }, 405)
      }
      const configuration = validateEnvironment(env)
      return json({
        success: configuration.ready,
        service: 'supabase-server',
        adminConfigured: configuration.adminConfigured ?? false,
        ...(!configuration.ready && { error: { code: 'SERVICE_NOT_CONFIGURED' } }),
      }, configuration.ready ? 200 : 503)
    }

    if (url.pathname !== '/v1/auth/verify') {
      return json({ success: false, error: { code: 'NOT_FOUND' } }, 404)
    }
    if (request.method !== 'GET') {
      return json({ success: false, error: { code: 'METHOD_NOT_ALLOWED' } }, 405)
    }

    const configuration = validateEnvironment(env)
    if (!configuration.ready) {
      return json({ success: false, error: { code: 'SERVICE_NOT_CONFIGURED' } }, 503)
    }

    const { data: auth, error } = await verify(request, { auth: 'user' })
    if (error) {
      return json({
        success: false,
        error: { code: error.code, message: error.message },
      }, error.status)
    }

    const claims = auth.userClaims
    let lookup
    try {
      lookup = await getUser({ token: auth.token, env })
    } catch {
      return json({
        success: false,
        error: { code: 'USER_LOOKUP_UNAVAILABLE', message: 'Unable to verify the current user.' },
      }, 503)
    }
    if (lookup?.status === 401 || lookup?.status === 403) {
      return json({
        success: false,
        error: { code: 'INVALID_CREDENTIALS', message: 'Invalid credentials' },
      }, 401)
    }
    if (lookup?.status !== 200 || !lookup.user || typeof lookup.user !== 'object') {
      return json({
        success: false,
        error: { code: 'USER_LOOKUP_UNAVAILABLE', message: 'Unable to verify the current user.' },
      }, 503)
    }

    const user = lookup.user
    const claimId = typeof claims.id === 'string' ? claims.id : ''
    const userId = typeof user.id === 'string' ? user.id : ''
    const claimEmail = typeof claims.email === 'string' ? claims.email.trim().toLowerCase() : ''
    const userEmail = typeof user.email === 'string' ? user.email.trim().toLowerCase() : ''
    if (!claimId || userId !== claimId || !claimEmail || userEmail !== claimEmail) {
      return json({
        success: false,
        error: { code: 'USER_IDENTITY_MISMATCH', message: 'Unable to verify the current user.' },
      }, 503)
    }

    const confirmation = Object.prototype.hasOwnProperty.call(user, 'email_confirmed_at')
      ? user.email_confirmed_at
      : (user.confirmed_at ?? null)
    return json({
      success: true,
      data: {
        id: user.id,
        email: user.email,
        role: claims.role ?? null,
        email_confirmed_at: confirmation,
      },
    })
  }
}

async function getUserFromAuth({ token, env }) {
  const endpoint = new URL('/auth/v1/user', env.SUPABASE_URL)
  const response = await fetch(endpoint, {
    method: 'GET',
    headers: {
      apikey: env.SUPABASE_PUBLISHABLE_KEY,
      Authorization: `Bearer ${token}`,
    },
    signal: AbortSignal.timeout(3000),
  })
  if (!response.ok) {
    return { status: response.status, user: null }
  }

  let user
  try {
    user = await response.json()
  } catch {
    return { status: 503, user: null }
  }
  return { status: response.status, user }
}
