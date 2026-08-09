import { createContextClient, verifyAuth } from '@supabase/server/core'

import { validateEnvironment } from './config.mjs'

const json = (body, status = 200) => Response.json(body, {
  status,
  headers: { 'Cache-Control': 'no-store' },
})

export function createHandler({
  env = process.env,
  verify = verifyAuth,
  createClient = createContextClient,
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

    createClient({ auth: { token: auth.token, keyName: auth.keyName } })
    const claims = auth.userClaims
    return json({
      success: true,
      data: {
        id: claims.id,
        email: claims.email ?? null,
        role: claims.role ?? null,
      },
    })
  }
}
