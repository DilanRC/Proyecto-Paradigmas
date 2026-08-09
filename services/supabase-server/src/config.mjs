const REQUIRED_VARIABLES = [
  'SUPABASE_URL',
  'SUPABASE_PUBLISHABLE_KEY',
  'SUPABASE_JWKS_URL',
]

export function validateEnvironment(env = process.env) {
  const missing = REQUIRED_VARIABLES.filter((name) => !env[name]?.trim())
  if (missing.length > 0) {
    return { ready: false, missing }
  }

  try {
    const projectUrl = new URL(env.SUPABASE_URL)
    const jwksUrl = new URL(env.SUPABASE_JWKS_URL)
    if (projectUrl.protocol !== 'https:' || jwksUrl.protocol !== 'https:') {
      return { ready: false, missing: [], error: 'Supabase URLs must use HTTPS.' }
    }
  } catch {
    return { ready: false, missing: [], error: 'Supabase URLs are invalid.' }
  }

  if (!env.SUPABASE_PUBLISHABLE_KEY.startsWith('sb_publishable_')) {
    return { ready: false, missing: [], error: 'SUPABASE_PUBLISHABLE_KEY has an invalid format.' }
  }

  return {
    ready: true,
    missing: [],
    adminConfigured: Boolean(env.SUPABASE_SECRET_KEY?.trim()),
  }
}
