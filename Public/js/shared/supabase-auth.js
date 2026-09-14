export const SESSION_KEY = 'tindercows:login';

const SESSION_VERSION = 2;
const SESSION_MODE = 'supabase';
const REFRESH_MARGIN_SECONDS = 60;
let configPromise = null;
let refreshPromise = null;

export class AuthError extends Error {
    constructor(message, { status = 0, code = null } = {}) {
        super(message);
        this.name = 'AuthError';
        this.status = status;
        this.code = code;
    }
}

function storageAvailable() {
    return typeof window !== 'undefined' && window.sessionStorage;
}

function parseSession(raw) {
    if (!raw) return null;
    try {
        const session = JSON.parse(raw);
        if (
            session?.authenticated !== true
            || session?.version !== SESSION_VERSION
            || session?.mode !== SESSION_MODE
            || typeof session?.email !== 'string'
            || session.email.trim() === ''
            || typeof session?.accessToken !== 'string'
            || session.accessToken === ''
            || typeof session?.refreshToken !== 'string'
            || session.refreshToken === ''
            || typeof session?.expiresAt !== 'number'
            || !Number.isFinite(session.expiresAt)
            || typeof session?.startedAt !== 'string'
            || Number.isNaN(Date.parse(session.startedAt))
        ) return null;
        return session;
    } catch {
        return null;
    }
}

export function readAuthSession(storage = storageAvailable()) {
    return parseSession(storage?.getItem?.(SESSION_KEY) ?? null);
}

function writeAuthSession(session, storage = storageAvailable()) {
    storage?.setItem?.(SESSION_KEY, JSON.stringify(session));
    return session;
}

export function clearAuthSession(storage = storageAvailable()) {
    storage?.removeItem?.(SESSION_KEY);
}

async function readJsonResponse(response) {
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.toLowerCase().includes('application/json')) return null;
    try { return await response.json(); } catch { return null; }
}

function friendlyAuthMessage(status, payload, fallback) {
    if (status === 400 || status === 401) {
        return 'El correo o la contraseña no son correctos.';
    }
    if (status === 429) return 'Hubo demasiados intentos. Intenta de nuevo más tarde.';
    if (payload?.code === 'email_not_confirmed') return 'Debes confirmar tu correo antes de entrar.';
    return fallback;
}

async function authConfig() {
    if (!configPromise) {
        configPromise = fetch('api/auth-config.php', {
            method: 'GET',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin',
        }).then(async (response) => {
            const payload = await readJsonResponse(response);
            if (!response.ok || payload?.success !== true) {
                throw new AuthError(
                    payload?.message || 'La autenticación no está disponible en este entorno.',
                    { status: response.status },
                );
            }
            const url = String(payload.data?.url ?? '').replace(/\/$/, '');
            const publishableKey = String(payload.data?.publishableKey ?? '');
            if (!url.startsWith('https://') || !publishableKey.startsWith('sb_publishable_')) {
                throw new AuthError('La configuración pública de autenticación no es válida.');
            }
            return { url, publishableKey };
        }).catch((error) => {
            configPromise = null;
            throw error;
        });
    }
    return configPromise;
}

function sessionFromTokenResponse(payload, previous = null) {
    const accessToken = String(payload?.access_token ?? '');
    const refreshToken = String(payload?.refresh_token ?? previous?.refreshToken ?? '');
    const email = String(payload?.user?.email ?? previous?.email ?? '').trim().toLowerCase();
    const expiresIn = Number(payload?.expires_in);
    const expiresAtClaim = Number(payload?.expires_at);
    const expiresAt = Number.isFinite(expiresAtClaim) && expiresAtClaim > 0
        ? expiresAtClaim
        : Math.floor(Date.now() / 1000) + (Number.isFinite(expiresIn) && expiresIn > 0 ? expiresIn : 3600);

    if (!accessToken || !refreshToken || !email) {
        throw new AuthError('Supabase no devolvió una sesión completa.');
    }

    return {
        authenticated: true,
        version: SESSION_VERSION,
        mode: SESSION_MODE,
        email,
        userId: typeof payload?.user?.id === 'string' ? payload.user.id : previous?.userId ?? null,
        accessToken,
        refreshToken,
        expiresAt,
        startedAt: previous?.startedAt ?? new Date().toISOString(),
    };
}

async function tokenRequest(grantType, body) {
    const config = await authConfig();
    let response;
    try {
        response = await fetch(`${config.url}/auth/v1/token?grant_type=${encodeURIComponent(grantType)}`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                apikey: config.publishableKey,
            },
            body: JSON.stringify(body),
        });
    } catch {
        throw new AuthError('No fue posible contactar el servicio de autenticación.');
    }

    const payload = await readJsonResponse(response);
    if (!response.ok) {
        const code = payload?.code ?? payload?.error_code ?? null;
        throw new AuthError(
            friendlyAuthMessage(response.status, payload, payload?.msg || payload?.message || 'No fue posible autenticar la cuenta.'),
            { status: response.status, code },
        );
    }
    return payload;
}

export async function signInWithPassword(email, password, storage = storageAvailable()) {
    const normalizedEmail = String(email ?? '').trim().toLowerCase();
    const payload = await tokenRequest('password', {
        email: normalizedEmail,
        password: String(password ?? ''),
    });
    return writeAuthSession(sessionFromTokenResponse(payload), storage);
}

export async function refreshAuthSession(storage = storageAvailable()) {
    const current = readAuthSession(storage);
    if (!current) {
        clearAuthSession(storage);
        throw new AuthError('La sesión no está disponible.', { status: 401 });
    }
    if (!refreshPromise) {
        refreshPromise = tokenRequest('refresh_token', { refresh_token: current.refreshToken })
            .then((payload) => writeAuthSession(sessionFromTokenResponse(payload, current), storage))
            .catch((error) => {
                if (error.status === 400 || error.status === 401) clearAuthSession(storage);
                throw error;
            })
            .finally(() => { refreshPromise = null; });
    }
    return refreshPromise;
}

export async function getAccessToken({ storage = storageAvailable(), forceRefresh = false } = {}) {
    const session = readAuthSession(storage);
    if (!session) return null;
    const secondsRemaining = session.expiresAt - Math.floor(Date.now() / 1000);
    if (forceRefresh || secondsRemaining <= REFRESH_MARGIN_SECONDS) {
        const refreshed = await refreshAuthSession(storage);
        return refreshed.accessToken;
    }
    return session.accessToken;
}

export async function signOut({ storage = storageAvailable() } = {}) {
    const session = readAuthSession(storage);
    if (!session) {
        clearAuthSession(storage);
        return;
    }

    // Supabase Auth admite POST /auth/v1/logout?scope=local: revoca únicamente
    // la sesión actual. El access token ya emitido puede seguir siendo válido
    // hasta su expiración, por eso igualmente se elimina del navegador siempre.
    try {
        const config = await authConfig();
        await fetch(`${config.url}/auth/v1/logout?scope=local`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                apikey: config.publishableKey,
                Authorization: `Bearer ${session.accessToken}`,
            },
        });
    } finally {
        clearAuthSession(storage);
    }
}
