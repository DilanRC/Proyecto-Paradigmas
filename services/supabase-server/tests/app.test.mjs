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
  let receivedLookup
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
  const getUser = async (options) => {
    receivedLookup = options
    return {
      status: 200,
      user: {
        id: 'user-1',
        email: 'user@example.test',
        email_confirmed_at: '2026-01-01T00:00:00Z',
      },
    }
  }
  const response = await createHandler({ env, verify, getUser })(
    new Request('http://service/v1/auth/verify', { headers: { Authorization: 'Bearer signed-jwt' } }),
  )
  assert.equal(response.status, 200)
  assert.deepEqual(receivedOptions, { auth: 'user' })
  assert.equal(receivedLookup.token, 'signed-jwt')
  assert.equal(receivedLookup.env, env)
  assert.deepEqual(await response.json(), {
    success: true,
    data: {
      id: 'user-1',
      email: 'user@example.test',
      role: 'authenticated',
      email_confirmed_at: '2026-01-01T00:00:00Z',
    },
  })
})

test('verify route reads the authoritative user endpoint with the publishable key and verified Bearer', async () => {
  const originalFetch = globalThis.fetch
  let receivedUrl
  let receivedInit
  globalThis.fetch = async (url, init) => {
    receivedUrl = String(url)
    receivedInit = init
    return Response.json({
      id: 'user-1',
      email: 'user@example.test',
      email_confirmed_at: '2026-01-01T00:00:00Z',
    })
  }
  try {
    const response = await createHandler({
      env,
      verify: async () => ({
        data: {
          token: 'signed-jwt',
          keyName: 'default',
          userClaims: { id: 'user-1', email: 'user@example.test', role: 'authenticated' },
        },
        error: null,
      }),
    })(new Request('http://service/v1/auth/verify'))
    assert.equal(response.status, 200)
    assert.equal(receivedUrl, 'https://project.supabase.co/auth/v1/user')
    assert.equal(receivedInit.method, 'GET')
    assert.equal(receivedInit.headers.apikey, 'sb_publishable_test')
    assert.equal(receivedInit.headers.Authorization, 'Bearer signed-jwt')
    assert.equal((await response.json()).data.email_confirmed_at, '2026-01-01T00:00:00Z')
  } finally {
    globalThis.fetch = originalFetch
  }
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

test('verify route forwards unconfirmed state from Auth even when the JWT has no confirmation claim', async () => {
  const response = await createHandler({
    env,
    verify: async () => ({
      data: {
          token: 'signed-jwt',
          keyName: 'default',
          userClaims: {
            id: 'user-2',
            email: 'unconfirmed@example.test',
            role: 'authenticated',
          },
        },
        error: null,
      }),
    getUser: async () => ({
      status: 200,
      user: {
        id: 'user-2',
        email: 'unconfirmed@example.test',
        email_confirmed_at: null,
      },
    }),
  })(new Request('http://service/v1/auth/verify'))
  assert.deepEqual(await response.json(), {
    success: true,
    data: {
      id: 'user-2',
      email: 'unconfirmed@example.test',
      role: 'authenticated',
      email_confirmed_at: null,
    },
  })
})

test('verify route fails closed when the Auth user lookup is unavailable or disagrees with JWT identity', async () => {
  const verifiedJwt = async () => ({
    data: {
      token: 'signed-jwt',
      keyName: 'default',
      userClaims: { id: 'user-3', email: 'user@example.test', role: 'authenticated' },
    },
    error: null,
  })
  const unavailable = await createHandler({
    env,
    verify: verifiedJwt,
    getUser: async () => { throw new Error('Auth unavailable') },
  })(new Request('http://service/v1/auth/verify'))
  assert.equal(unavailable.status, 503)

  const mismatch = await createHandler({
    env,
    verify: verifiedJwt,
    getUser: async () => ({
      status: 200,
      user: { id: 'another-user', email: 'user@example.test', email_confirmed_at: '2026-01-01' },
    }),
  })(new Request('http://service/v1/auth/verify'))
  assert.equal(mismatch.status, 503)
})

test('unknown routes and unsupported methods are rejected', async () => {
  const handler = createHandler({ env })
  assert.equal((await handler(new Request('http://service/missing'))).status, 404)
  assert.equal((await handler(new Request('http://service/health', { method: 'POST' }))).status, 405)
  assert.equal((await handler(new Request('http://service/v1/auth/verify', { method: 'POST' }))).status, 405)
})
