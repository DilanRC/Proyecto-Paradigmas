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
    const code = payload?.code ?? payload?.error_code ?? null;
    if (code === 'email_not_confirmed') return 'Debes confirmar tu correo antes de entrar.';
    if (status === 400 || status === 401) return 'El correo o la contraseña no son correctos.';
    if (status === 429) return 'Hubo demasiados intentos. Intenta de nuevo más tarde.';
    return fallback;
}

function friendlySignupMessage(status, payload) {
    const code = payload?.code ?? payload?.error_code ?? null;
    const providerMessage = String(payload?.msg || payload?.message || '').toLowerCase();
    if (status === 429) return 'Hubo demasiados intentos. Intenta de nuevo más tarde.';
    if (code === 'user_already_exists' || code === 'email_exists') {
        return 'Ya existe una cuenta con este correo. Entra con tu contraseña para continuar.';
    }
    if (code === 'weak_password' || providerMessage.includes('valid password')) {
        return 'La contraseña debe tener al menos 8 caracteres, una letra mayúscula y un número. Evita secuencias comunes como 12345678.';
    }
    if (providerMessage.includes('email') && providerMessage.includes('valid')) {
        return 'Ingrese un correo electrónico válido.';
    }
    return 'No fue posible crear la cuenta. Revise los datos e intente nuevamente.';
}

async function authConfig() {
    if (!configPromise) {
        configPromise = fetch('api/v1/auth/config', {
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
    const user = payload?.user ?? {};
    const confirmationField = Object.prototype.hasOwnProperty.call(user, 'email_confirmed_at')
        ? user.email_confirmed_at
        : user.confirmed_at;
    if (confirmationField === null || confirmationField === false) {
        throw new AuthError('Debes confirmar tu correo antes de entrar.', {
            status: 400,
            code: 'email_not_confirmed',
        });
    }
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
        userId: typeof user?.id === 'string' ? user.id : previous?.userId ?? null,
        accessToken,
        refreshToken,
        expiresAt,
        startedAt: previous?.startedAt ?? new Date().toISOString(),
    };
}

async function postAuth(path, body, { timeoutMs = 20000 } = {}) {
    const config = await authConfig();
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), Math.max(1, Number(timeoutMs) || 20000));
    let response;
    try {
        response = await fetch(`${config.url}${path}`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                apikey: config.publishableKey,
            },
            body: JSON.stringify(body),
            signal: controller.signal,
        });
    } catch (error) {
        if (error?.name === 'AbortError') {
            throw new AuthError('El servicio de autenticación tardó demasiado. Intenta nuevamente.');
        }
        throw new AuthError('No fue posible contactar el servicio de autenticación.');
    } finally {
        clearTimeout(timeout);
    }
    const payload = await readJsonResponse(response);
    return { response, payload };
}

async function tokenRequest(grantType, body) {
    const { response, payload } = await postAuth(
        `/auth/v1/token?grant_type=${encodeURIComponent(grantType)}`,
        body,
    );
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

/**
 * Crea la cuenta sin inventar una sesión. Si el proyecto exige confirmación de
 * correo, Supabase puede devolver el usuario sin access_token; en ese caso el
 * llamador debe conservar un borrador no sensible y esperar confirmación/login.
 */
export async function signUpWithPassword(email, password, storage = storageAvailable()) {
    const normalizedEmail = String(email ?? '').trim().toLowerCase();
    const { response, payload } = await postAuth('/auth/v1/signup', {
        email: normalizedEmail,
        password: String(password ?? ''),
    });
    if (!response.ok) {
        const code = payload?.code ?? payload?.error_code ?? null;
        throw new AuthError(friendlySignupMessage(response.status, payload), {
            status: response.status,
            code,
        });
    }

    // GoTrue/Supabase ha usado ambas formas en distintas superficies: tokens en
    // el objeto raíz o dentro de session. Admitimos ambas sin depender del SDK.
    const tokenPayload = payload?.session?.access_token
        ? { ...payload.session, user: payload.user ?? payload.session.user }
        : payload;
    if (tokenPayload?.access_token && tokenPayload?.refresh_token) {
        const session = writeAuthSession(sessionFromTokenResponse(tokenPayload), storage);
        return {
            session,
            user: tokenPayload.user ?? payload?.user ?? null,
            requiresEmailConfirmation: false,
        };
    }

    const user = payload?.user ?? (typeof payload?.id === 'string' ? payload : null);
    if (user && (typeof user.email === 'string' || normalizedEmail !== '')) {
        clearAuthSession(storage);
        return {
            session: null,
            user,
            requiresEmailConfirmation: true,
        };
    }

    throw new AuthError('Supabase creó una respuesta de registro que no pudimos interpretar.');
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
