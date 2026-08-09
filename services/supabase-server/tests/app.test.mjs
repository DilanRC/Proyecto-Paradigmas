import assert from 'node:assert/strict'
import test from 'node:test'

import { createHandler } from '../src/app.mjs'

const env = {
  SUPABASE_URL: 'https://project.supabase.co',
  SUPABASE_PUBLISHABLE_KEY: 'sb_publishable_test',
  SUPABASE_JWKS_URL: 'https://project.supabase.co/auth/v1/.well-known/jwks.json',
}

test('health exposes readiness but never configuration values', async () => {
  const response = await createHandler({ env })(new Request('http://service/health'))
  const body = await response.json()
  assert.equal(response.status, 200)
  assert.deepEqual(body, {
    success: true,
    service: 'supabase-server',
    adminConfigured: false,
  })
  assert.equal(JSON.stringify(body).includes('sb_publishable_test'), false)
})

test('verify route enforces auth user and maps safe identity fields', async () => {
  let receivedOptions
  let receivedClientOptions
  const verify = async (_request, options) => {
    receivedOptions = options
    return {
      data: {
        token: 'signed-jwt',
        keyName: 'default',
        userClaims: { id: 'user-1', email: 'user@example.test', role: 'authenticated' },
      },
      error: null,
    }
  }
  const createClient = (options) => {
    receivedClientOptions = options
    return {}
  }
  const response = await createHandler({ env, verify, createClient })(
    new Request('http://service/v1/auth/verify', { headers: { Authorization: 'Bearer signed-jwt' } }),
  )
  assert.equal(response.status, 200)
  assert.deepEqual(receivedOptions, { auth: 'user' })
  assert.deepEqual(receivedClientOptions, { auth: { token: 'signed-jwt', keyName: 'default' } })
  assert.deepEqual(await response.json(), {
    success: true,
    data: { id: 'user-1', email: 'user@example.test', role: 'authenticated' },
  })
})

test('verify route preserves the SDK auth error status and code', async () => {
  const verify = async () => ({
    data: null,
    error: { status: 401, code: 'INVALID_CREDENTIALS', message: 'Invalid credentials' },
  })
  const response = await createHandler({ env, verify })(new Request('http://service/v1/auth/verify'))
  assert.equal(response.status, 401)
  assert.deepEqual(await response.json(), {
    success: false,
    error: { code: 'INVALID_CREDENTIALS', message: 'Invalid credentials' },
  })
})

test('unknown routes and unsupported methods are rejected', async () => {
  const handler = createHandler({ env })
  assert.equal((await handler(new Request('http://service/missing'))).status, 404)
  assert.equal((await handler(new Request('http://service/health', { method: 'POST' }))).status, 405)
  assert.equal((await handler(new Request('http://service/v1/auth/verify', { method: 'POST' }))).status, 405)
})
