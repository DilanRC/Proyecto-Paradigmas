import assert from 'node:assert/strict'
import test from 'node:test'

import { validateEnvironment } from '../src/config.mjs'

const validEnvironment = {
  SUPABASE_URL: 'https://project.supabase.co',
  SUPABASE_PUBLISHABLE_KEY: 'sb_publishable_test',
  SUPABASE_JWKS_URL: 'https://project.supabase.co/auth/v1/.well-known/jwks.json',
}

test('accepts the variables required for user JWT verification', () => {
  assert.deepEqual(validateEnvironment(validEnvironment), {
    ready: true,
    missing: [],
    adminConfigured: false,
  })
})

test('reports missing variables without exposing configured values', () => {
  assert.deepEqual(validateEnvironment({}), {
    ready: false,
    missing: ['SUPABASE_URL', 'SUPABASE_PUBLISHABLE_KEY', 'SUPABASE_JWKS_URL'],
  })
})

test('rejects insecure remote URLs and malformed publishable keys', () => {
  assert.equal(validateEnvironment({ ...validEnvironment, SUPABASE_URL: 'http://project.supabase.co' }).ready, false)
  assert.equal(validateEnvironment({ ...validEnvironment, SUPABASE_PUBLISHABLE_KEY: 'not-a-key' }).ready, false)
})
