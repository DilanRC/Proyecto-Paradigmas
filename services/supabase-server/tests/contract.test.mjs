import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'

test('published contract requires bearer auth on the verification route', async () => {
  const contractUrl = new URL('../../../contracts/supabase-auth-v1.openapi.json', import.meta.url)
  const contract = JSON.parse(await readFile(contractUrl, 'utf8'))
  assert.equal(contract.info.version, '1.0.0')
  assert.deepEqual(contract.paths['/v1/auth/verify'].get.security, [{ bearerAuth: [] }])
  assert.equal(contract.components.securitySchemes.bearerAuth.scheme, 'bearer')
})
