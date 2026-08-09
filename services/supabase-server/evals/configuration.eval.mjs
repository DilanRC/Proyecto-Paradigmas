import { readFile } from 'node:fs/promises'

const serviceRoot = new URL('../', import.meta.url)
const projectRoot = new URL('../../../', import.meta.url)
const [source, packageJson, compose, exampleEnv, contract] = await Promise.all([
  readFile(new URL('src/app.mjs', serviceRoot), 'utf8'),
  readFile(new URL('package.json', serviceRoot), 'utf8').then(JSON.parse),
  readFile(new URL('compose.yaml', projectRoot), 'utf8'),
  readFile(new URL('.env.example', projectRoot), 'utf8'),
  readFile(new URL('contracts/supabase-auth-v1.openapi.json', projectRoot), 'utf8').then(JSON.parse),
])

const checks = [
  ['paquete_oficial', packageJson.dependencies?.['@supabase/server'] === '1.4.1'],
  ['cliente_supabase', packageJson.dependencies?.['@supabase/supabase-js'] === '2.112.2'],
  ['auth_usuario', source.includes("verify(request, { auth: 'user' })")],
  ['sin_admin', !source.includes('supabaseAdmin') && !source.includes("auth: 'secret'")],
  ['contrato_bearer', contract.paths['/v1/auth/verify'].get.security?.[0]?.bearerAuth?.length === 0],
  ['compose_aislado', compose.includes('supabase-server:') && compose.includes('127.0.0.1:${SUPABASE_SERVER_PORT:-3001}:3000')],
  ['variables_documentadas', ['SUPABASE_URL', 'SUPABASE_PUBLISHABLE_KEY', 'SUPABASE_SECRET_KEY', 'SUPABASE_JWKS_URL']
    .every((name) => exampleEnv.includes(`${name}=`))],
  ['sin_secreto_versionado', !exampleEnv.match(/SUPABASE_SECRET_KEY=sb_secret_.+/)],
]

const passed = checks.filter(([, result]) => result).length
const score = Math.round((passed / checks.length) * 100)
console.log(JSON.stringify({
  eval: 'supabase_server_sidecar',
  score,
  threshold: 100,
  checks: checks.map(([criterion, passes]) => ({ criterion, passes })),
}, null, 2))
if (score < 100) process.exit(1)
