import assert from 'node:assert/strict';
import test from 'node:test';

import {
    authSessionIdentity,
    authSessionGeneration,
    authSessionVersion,
    clearAuthSession,
    endExpiredSession,
    getAccessToken,
    migrateLegacyAuthSession,
    readAuthSession,
    SESSION_KEY as AUTH_SESSION_KEY,
    signInWithPassword,
    signOut,
} from '../../Public/js/shared/supabase-auth.js';
import { request } from '../../Public/js/shared/api.js';
import {
    SESSION_KEY as ADMIN_SESSION_KEY,
    watchAuthSessionAcrossTabs,
} from '../../Public/js/shared/auth-gate.js';
import { PROFILE_KEY, publicProfileForSession, readPublicProfile } from '../../Public/js/shared/public-profile.js';

// node:test process has a native LockManager whose lifecycle is unrelated to browser tabs.
// Tests use the explicit lock fixture below when verifying Web Locks coordination.
Object.defineProperty(globalThis, 'navigator', { configurable: true, value: {} });

function memoryStorage(initial = {}) {
    const values = new Map(Object.entries(initial));
    return {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, String(value)),
        removeItem: (key) => values.delete(key),
        has: (key) => values.has(key),
    };
}

function validSession(overrides = {}) {
    return {
        authenticated: true,
        version: 2,
        mode: 'supabase',
        email: 'persona@example.test',
        userId: 'persona-1',
        accessToken: 'access-1',
        refreshToken: 'refresh-1',
        expiresAt: Math.floor(Date.now() / 1000) + 3600,
        startedAt: new Date().toISOString(),
        ...overrides,
    };
}

test('iniciar sesión guarda el token en almacenamiento compartido entre pestañas', async () => {
    const originalWindow = globalThis.window;
    const originalFetch = globalThis.fetch;
    const localStorage = memoryStorage();
    const sessionStorage = memoryStorage();
    globalThis.window = { localStorage, sessionStorage };
    globalThis.fetch = async (url) => {
        if (String(url) === 'api/v1/auth/config') {
            return new Response(JSON.stringify({
                success: true,
                data: { url: 'https://auth.example.test', publishableKey: 'sb_publishable_test' },
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        return new Response(JSON.stringify({
            access_token: 'access-login',
            refresh_token: 'refresh-login',
            expires_in: 3600,
            user: { id: 'persona-login', email: 'persona@example.test', email_confirmed_at: new Date().toISOString() },
        }), { status: 200, headers: { 'Content-Type': 'application/json' } });
    };

    try {
        await signInWithPassword('persona@example.test', 'Clave-de-prueba-1');
        assert.equal(readAuthSession()?.accessToken, 'access-login');
        assert.equal(JSON.parse(localStorage.getItem(AUTH_SESSION_KEY)).refreshToken, 'refresh-login');
        assert.equal(sessionStorage.getItem(AUTH_SESSION_KEY), null);
    } finally {
        globalThis.fetch = originalFetch;
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('si localStorage rechaza una escritura, inicia sesión en el respaldo temporal', async () => {
    const originalWindow = globalThis.window;
    const originalFetch = globalThis.fetch;
    const localStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(validSession()) });
    localStorage.setItem = () => { throw new Error('quota exceeded'); };
    localStorage.removeItem = () => { throw new Error('storage is read-only'); };
    const sessionStorage = memoryStorage();
    globalThis.window = { localStorage, sessionStorage };
    globalThis.fetch = async (url) => {
        if (String(url) === 'api/v1/auth/config') {
            return new Response(JSON.stringify({
                success: true,
                data: { url: 'https://auth.example.test', publishableKey: 'sb_publishable_test' },
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        return new Response(JSON.stringify({
            access_token: 'access-fallback',
            refresh_token: 'refresh-fallback',
            expires_in: 3600,
            user: { id: 'persona-fallback', email: 'persona@example.test', email_confirmed_at: new Date().toISOString() },
        }), { status: 200, headers: { 'Content-Type': 'application/json' } });
    };
    try {
        await signInWithPassword('persona@example.test', 'Clave-de-prueba-1');
        assert.equal(readAuthSession()?.userId, 'persona-fallback');
        assert.equal(JSON.parse(sessionStorage.getItem(AUTH_SESSION_KEY)).accessToken, 'access-fallback');
        assert.equal(JSON.parse(localStorage.getItem(AUTH_SESSION_KEY)).userId, 'persona-1', 'se simula la sesión antigua que no se pudo borrar');
    } finally {
        globalThis.fetch = originalFetch;
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('si fallan ambos almacenamientos, conserva la sesión anterior', async () => {
    const originalWindow = globalThis.window;
    const originalFetch = globalThis.fetch;
    const localStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(validSession()) });
    localStorage.setItem = () => { throw new Error('persistent storage is read-only'); };
    const sessionStorage = memoryStorage();
    sessionStorage.setItem = () => { throw new Error('session storage is full'); };
    globalThis.window = { localStorage, sessionStorage };
    globalThis.fetch = async (url) => {
        if (String(url) === 'api/v1/auth/config') {
            return new Response(JSON.stringify({
                success: true,
                data: { url: 'https://auth.example.test', publishableKey: 'sb_publishable_test' },
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        return new Response(JSON.stringify({
            access_token: 'access-account-2',
            refresh_token: 'refresh-account-2',
            expires_in: 3600,
            user: { id: 'persona-2', email: 'otra@example.test', email_confirmed_at: new Date().toISOString() },
        }), { status: 200, headers: { 'Content-Type': 'application/json' } });
    };

    try {
        await assert.rejects(signInWithPassword('otra@example.test', 'Clave-de-prueba-2'));
        assert.equal(JSON.parse(localStorage.getItem(AUTH_SESSION_KEY)).userId, 'persona-1');
    } finally {
        globalThis.fetch = originalFetch;
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('un fallo transitorio al renovar no marca como cambio una sesión que sigue vigente', async () => {
    const originalWindow = globalThis.window;
    const originalFetch = globalThis.fetch;
    const localStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(validSession({
        expiresAt: Math.floor(Date.now() / 1000) - 1,
    })) });
    globalThis.window = { localStorage, sessionStorage: memoryStorage() };
    globalThis.fetch = async (url) => {
        if (String(url) === 'api/v1/auth/config') {
            return new Response(JSON.stringify({
                success: true,
                data: { url: 'https://auth.example.test', publishableKey: 'sb_publishable_test' },
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        throw new TypeError('network unavailable');
    };

    try {
        const response = await request('/api/v1/publicaciones', {}, { fetchImpl: async (_url, options) => ({
            ok: true,
            status: 200,
            json: async () => ({ success: true, data: { publicaciones: [] } }),
            options,
        }) });
        assert.deepEqual(response.data.publicaciones, []);
        assert.equal(readAuthSession()?.userId, 'persona-1');
    } finally {
        globalThis.fetch = originalFetch;
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('un 401 anónimo tras un fallo de red no descarta una sesión válida', async () => {
    const originalWindow = globalThis.window;
    const originalFetch = globalThis.fetch;
    const localStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(validSession({
        expiresAt: Math.floor(Date.now() / 1000) - 1,
    })) });
    globalThis.window = { localStorage, sessionStorage: memoryStorage() };
    globalThis.fetch = async (url) => {
        if (String(url) === 'api/v1/auth/config') {
            return new Response(JSON.stringify({
                success: true,
                data: { url: 'https://auth.example.test', publishableKey: 'sb_publishable_test' },
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        throw new TypeError('network unavailable');
    };

    try {
        await assert.rejects(
            request('/api/v1/actividad', {}, { fetchImpl: async () => ({
                ok: false,
                status: 401,
                json: async () => ({ success: false, message: 'Debe iniciar sesión.' }),
            }) }),
            (error) => error.status === 0,
        );
        assert.equal(readAuthSession()?.userId, 'persona-1');
    } finally {
        globalThis.fetch = originalFetch;
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('un refresh tardío no restaura una cuenta que se cambió durante la petición', async () => {
    const originalWindow = globalThis.window;
    const originalFetch = globalThis.fetch;
    const originalNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    const localStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(validSession({
        expiresAt: Math.floor(Date.now() / 1000) - 1,
    })) });
    globalThis.window = { localStorage, sessionStorage: memoryStorage() };
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: {} });

    let refreshStarted;
    const didStartRefresh = new Promise((resolve) => { refreshStarted = resolve; });
    let finishRefresh;
    const pendingRefresh = new Promise((resolve) => { finishRefresh = resolve; });
    globalThis.fetch = async (url) => {
        if (String(url) === 'api/v1/auth/config') {
            return new Response(JSON.stringify({
                success: true,
                data: { url: 'https://auth.example.test', publishableKey: 'sb_publishable_test' },
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        if (String(url).includes('grant_type=refresh_token')) {
            refreshStarted();
            return pendingRefresh;
        }
        return new Response(JSON.stringify({
            access_token: 'access-account-2',
            refresh_token: 'refresh-account-2',
            expires_in: 3600,
            user: { id: 'persona-2', email: 'otra@example.test', email_confirmed_at: new Date().toISOString() },
        }), { status: 200, headers: { 'Content-Type': 'application/json' } });
    };

    try {
        const oldRefresh = getAccessToken({ forceRefresh: true });
        await didStartRefresh;
        await signInWithPassword('otra@example.test', 'Clave-de-prueba-2');
        finishRefresh(new Response(JSON.stringify({
            access_token: 'access-refreshed-old-account',
            refresh_token: 'refresh-refreshed-old-account',
            expires_in: 3600,
            user: { id: 'persona-1', email: 'persona@example.test', email_confirmed_at: new Date().toISOString() },
        }), { status: 200, headers: { 'Content-Type': 'application/json' } }));

        await assert.rejects(oldRefresh, (error) => error.code === 'session_changed');
        assert.equal(readAuthSession()?.userId, 'persona-2');
    } finally {
        globalThis.fetch = originalFetch;
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
        if (originalNavigator) Object.defineProperty(globalThis, 'navigator', originalNavigator);
        else delete globalThis.navigator;
    }
});

test('el cierre tardío de una sesión no borra el login iniciado después', async () => {
    const originalWindow = globalThis.window;
    const originalFetch = globalThis.fetch;
    const originalNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    const localStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(validSession()) });
    globalThis.window = { localStorage, sessionStorage: memoryStorage() };
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: {} });

    let logoutStarted;
    const didStartLogout = new Promise((resolve) => { logoutStarted = resolve; });
    let finishLogout;
    const pendingLogout = new Promise((resolve) => { finishLogout = resolve; });
    globalThis.fetch = async (url) => {
        if (String(url) === 'api/v1/auth/config') {
            return new Response(JSON.stringify({
                success: true,
                data: { url: 'https://auth.example.test', publishableKey: 'sb_publishable_test' },
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        if (String(url).includes('/auth/v1/logout')) {
            logoutStarted();
            return pendingLogout;
        }
        return new Response(JSON.stringify({
            access_token: 'access-account-2',
            refresh_token: 'refresh-account-2',
            expires_in: 3600,
            user: { id: 'persona-2', email: 'otra@example.test', email_confirmed_at: new Date().toISOString() },
        }), { status: 200, headers: { 'Content-Type': 'application/json' } });
    };

    try {
        const oldLogout = signOut();
        await didStartLogout;
        await signInWithPassword('otra@example.test', 'Clave-de-prueba-2');
        finishLogout(new Response(null, { status: 204 }));
        await oldLogout;
        assert.equal(readAuthSession()?.userId, 'persona-2');
    } finally {
        globalThis.fetch = originalFetch;
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
        if (originalNavigator) Object.defineProperty(globalThis, 'navigator', originalNavigator);
        else delete globalThis.navigator;
    }
});

test('el cierre que empezó sin sesión vuelve a comprobar antes de limpiar', async () => {
    const originalWindow = globalThis.window;
    const originalNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    const nuevaCuenta = JSON.stringify(validSession({ userId: 'persona-2', email: 'otra@example.test' }));
    let lecturasDeSesion = 0;
    const localStorage = {
        getItem: (key) => {
            if (key !== AUTH_SESSION_KEY) return null;
            lecturasDeSesion += 1;
            return lecturasDeSesion === 1 ? null : nuevaCuenta;
        },
        setItem() {},
        removeItem() {},
    };
    globalThis.window = { localStorage, sessionStorage: memoryStorage() };
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: {} });

    try {
        await signOut({ storage: localStorage });
        assert.equal(lecturasDeSesion, 2, 'la sesión se vuelve a consultar dentro de la sección crítica');
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
        if (originalNavigator) Object.defineProperty(globalThis, 'navigator', originalNavigator);
        else delete globalThis.navigator;
    }
});

test('una respuesta 401 vieja no cierra la sesión de una nueva cuenta', async () => {
    const originalWindow = globalThis.window;
    const originalLocation = Object.getOwnPropertyDescriptor(globalThis, 'location');
    const localStorage = memoryStorage({
        [AUTH_SESSION_KEY]: JSON.stringify(validSession({ userId: 'persona-2', email: 'otra@example.test' })),
    });
    globalThis.window = { localStorage, sessionStorage: memoryStorage() };
    let reloads = 0;
    let redirects = 0;
    Object.defineProperty(globalThis, 'location', { configurable: true, value: {
        reload: () => { reloads += 1; },
        assign: () => { redirects += 1; },
    } });

    try {
        const versionAnterior = authSessionVersion(JSON.stringify(validSession()));
        assert.equal(await endExpiredSession(versionAnterior), false);
        assert.equal(readAuthSession()?.userId, 'persona-2');
        assert.equal(reloads, 1);
        assert.equal(redirects, 0);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
        if (originalLocation) Object.defineProperty(globalThis, 'location', originalLocation);
        else delete globalThis.location;
    }
});

test('un 401 de un token rotado no borra la sesión renovada de la misma cuenta', async () => {
    const originalWindow = globalThis.window;
    const originalLocation = Object.getOwnPropertyDescriptor(globalThis, 'location');
    const current = validSession({ accessToken: 'access-renovated', refreshToken: 'refresh-renovated', writtenAt: Date.now() + 1000 });
    const localStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(current) });
    globalThis.window = { localStorage, sessionStorage: memoryStorage() };
    let reloads = 0;
    Object.defineProperty(globalThis, 'location', { configurable: true, value: { reload: () => { reloads += 1; } } });

    try {
        const versionAnterior = authSessionVersion(JSON.stringify(validSession()));
        assert.equal(await endExpiredSession(versionAnterior), false);
        assert.equal(readAuthSession()?.accessToken, 'access-renovated');
        assert.equal(reloads, 1);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
        if (originalLocation) Object.defineProperty(globalThis, 'location', originalLocation);
        else delete globalThis.location;
    }
});

test('una solicitud de A que termina con 401 no se reintenta ni se interpreta como logout de B', async () => {
    const originalWindow = globalThis.window;
    const originalFetch = globalThis.fetch;
    const localStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(validSession()) });
    globalThis.window = { localStorage, sessionStorage: memoryStorage() };
    globalThis.fetch = async (url) => {
        if (String(url) === 'api/v1/auth/config') {
            return new Response(JSON.stringify({
                success: true,
                data: { url: 'https://auth.example.test', publishableKey: 'sb_publishable_test' },
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        return new Response(JSON.stringify({
            access_token: 'access-account-2',
            refresh_token: 'refresh-account-2',
            expires_in: 3600,
            user: { id: 'persona-2', email: 'otra@example.test', email_confirmed_at: new Date().toISOString() },
        }), { status: 200, headers: { 'Content-Type': 'application/json' } });
    };

    let requestStarted;
    const didStartRequest = new Promise((resolve) => { requestStarted = resolve; });
    let finishRequest;
    const pendingRequest = new Promise((resolve) => { finishRequest = resolve; });
    try {
        const oldRequest = request('/api/v1/actividad', {}, { fetchImpl: async () => {
            requestStarted();
            return pendingRequest;
        } });
        await didStartRequest;
        await signInWithPassword('otra@example.test', 'Clave-de-prueba-2');
        finishRequest({
            ok: false,
            status: 401,
            json: async () => ({ success: false, message: 'Sesión vencida.' }),
        });

        await assert.rejects(oldRequest, (error) => error.code === 'session_changed' && error.status === 0);
        assert.equal(readAuthSession()?.userId, 'persona-2');
    } finally {
        globalThis.fetch = originalFetch;
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('al iniciar con otra cuenta descarta los datos personales de la cuenta anterior', async () => {
    const originalWindow = globalThis.window;
    const originalFetch = globalThis.fetch;
    const localStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(validSession()) });
    const sessionStorage = memoryStorage({
        [PROFILE_KEY]: JSON.stringify({ persona: { identificacionNumero: 'identificacion-anterior' } }),
    });
    globalThis.window = { localStorage, sessionStorage };
    globalThis.fetch = async (url) => {
        if (String(url) === 'api/v1/auth/config') {
            return new Response(JSON.stringify({
                success: true,
                data: { url: 'https://auth.example.test', publishableKey: 'sb_publishable_test' },
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        return new Response(JSON.stringify({
            access_token: 'access-account-2',
            refresh_token: 'refresh-account-2',
            expires_in: 3600,
            user: { id: 'persona-2', email: 'otra@example.test', email_confirmed_at: new Date().toISOString() },
        }), { status: 200, headers: { 'Content-Type': 'application/json' } });
    };

    try {
        await signInWithPassword('otra@example.test', 'Clave-de-prueba-2');
        assert.equal(readAuthSession()?.userId, 'persona-2');
        assert.equal(sessionStorage.getItem(PROFILE_KEY), null);
    } finally {
        globalThis.fetch = originalFetch;
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('el perfil del menú solo se muestra si coincide con el correo autenticado', () => {
    const profile = { persona: { correoElectronico: 'Persona@Ejemplo.test', nombre: 'Persona A' } };
    const sessionStorage = memoryStorage({ [PROFILE_KEY]: JSON.stringify(profile) });
    assert.equal(publicProfileForSession({ email: 'otra@ejemplo.test' }, sessionStorage), null);
    assert.deepEqual(publicProfileForSession({ email: 'persona@ejemplo.test' }, sessionStorage), profile);
});

test('migra una sesión de pestaña existente y limpia los dos almacenes al salir', async () => {
    const session = validSession();
    const localStorage = memoryStorage();
    const sessionStorage = memoryStorage({
        [AUTH_SESSION_KEY]: JSON.stringify(session),
        [PROFILE_KEY]: JSON.stringify({ persona: { telefono: '88888888' } }),
    });
    const originalWindow = globalThis.window;
    globalThis.window = { localStorage, sessionStorage };

    try {
        await migrateLegacyAuthSession();
        assert.equal(readAuthSession()?.userId, 'persona-1');
        assert.equal(JSON.parse(localStorage.getItem(AUTH_SESSION_KEY)).accessToken, 'access-1');
        assert.equal(sessionStorage.getItem(AUTH_SESSION_KEY), null);

        clearAuthSession();
        assert.equal(localStorage.getItem(AUTH_SESSION_KEY), null);
        assert.equal(sessionStorage.getItem(PROFILE_KEY), null);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('al migrar, descarta perfil de una cuenta legada si gana la sesión primaria', async () => {
    const originalWindow = globalThis.window;
    const primary = validSession({ userId: 'persona-2', email: 'otra@example.test', writtenAt: Date.now() + 1000 });
    const legacy = validSession({ userId: 'persona-1', writtenAt: Date.now() - 1000 });
    const localStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(primary) });
    const sessionStorage = memoryStorage({
        [AUTH_SESSION_KEY]: JSON.stringify(legacy),
        [PROFILE_KEY]: JSON.stringify({ persona: { identificacionNumero: 'identificacion-anterior' } }),
    });
    globalThis.window = { localStorage, sessionStorage };

    try {
        await migrateLegacyAuthSession();
        assert.equal(readAuthSession()?.userId, 'persona-2');
        assert.equal(sessionStorage.getItem(AUTH_SESSION_KEY), null);
        assert.equal(sessionStorage.getItem(PROFILE_KEY), null);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('al migrar una sesión legada más nueva, conserva su propio perfil cacheado', async () => {
    const originalWindow = globalThis.window;
    const primary = validSession({ userId: 'persona-1', writtenAt: Date.now() - 1000 });
    const legacy = validSession({ userId: 'persona-2', email: 'otra@example.test', writtenAt: Date.now() + 1000 });
    const profile = { persona: { correoElectronico: 'otra@example.test', nombre: 'Persona B' } };
    const localStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(primary) });
    const sessionStorage = memoryStorage({
        [AUTH_SESSION_KEY]: JSON.stringify(legacy),
        [PROFILE_KEY]: JSON.stringify(profile),
    });
    globalThis.window = { localStorage, sessionStorage };

    try {
        await migrateLegacyAuthSession();
        assert.equal(readAuthSession()?.userId, 'persona-2');
        assert.deepEqual(JSON.parse(sessionStorage.getItem(PROFILE_KEY)), profile);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('si el navegador bloquea localStorage, la sesión sigue usando sessionStorage', () => {
    const originalWindow = globalThis.window;
    const sessionStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(validSession()) });
    globalThis.window = Object.defineProperties({}, {
        localStorage: { get() { throw new Error('storage blocked'); } },
        sessionStorage: { value: sessionStorage },
    });
    try {
        assert.equal(readAuthSession()?.userId, 'persona-1');
        clearAuthSession();
        assert.equal(sessionStorage.getItem(AUTH_SESSION_KEY), null);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('un perfil de caché bloqueado no rompe la página', () => {
    const descriptor = Object.getOwnPropertyDescriptor(globalThis, 'sessionStorage');
    Object.defineProperty(globalThis, 'sessionStorage', { configurable: true, get() { throw new Error('storage blocked'); } });
    try {
        assert.equal(readPublicProfile(), null);
    } finally {
        if (descriptor) Object.defineProperty(globalThis, 'sessionStorage', descriptor);
        else delete globalThis.sessionStorage;
    }
});

test('coordina el refresh del token entre pestañas con Web Locks', async () => {
    const originalWindow = globalThis.window;
    const originalFetch = globalThis.fetch;
    const originalNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    const localStorage = memoryStorage({ [AUTH_SESSION_KEY]: JSON.stringify(validSession({
        expiresAt: Math.floor(Date.now() / 1000) - 1,
    })) });
    const sessionStorage = memoryStorage({
        [PROFILE_KEY]: JSON.stringify({ persona: { identificacionNumero: 'identificacion-actual' } }),
    });
    globalThis.window = { localStorage, sessionStorage };
    let queued = Promise.resolve();
    const locks = {
        request: (_name, callback) => {
            const result = queued.then(callback);
            queued = result.catch(() => {});
            return result;
        },
    };
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { locks } });
    let refreshRequests = 0;
    globalThis.fetch = async (url) => {
        if (String(url).includes('/auth/v1/token?grant_type=refresh_token')) {
            refreshRequests += 1;
            await new Promise((resolve) => setTimeout(resolve, 20));
            return new Response(JSON.stringify({
                access_token: 'access-refreshed',
                refresh_token: 'refresh-refreshed',
                expires_in: 3600,
                user: { id: 'persona-1', email: 'persona@example.test', email_confirmed_at: new Date().toISOString() },
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        throw new Error(`Solicitud inesperada: ${url}`);
    };

    try {
        const [first, second] = await Promise.all([
            getAccessToken({ forceRefresh: true }),
            getAccessToken({ forceRefresh: true }),
        ]);
        assert.equal(refreshRequests, 1);
        assert.equal(first, 'access-refreshed');
        assert.equal(second, 'access-refreshed');
        assert.notEqual(sessionStorage.getItem(PROFILE_KEY), null, 'renovar la misma identidad conserva el perfil personal');
    } finally {
        globalThis.fetch = originalFetch;
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
        if (originalNavigator) Object.defineProperty(globalThis, 'navigator', originalNavigator);
        else delete globalThis.navigator;
    }
});

test('las pestañas ignoran la rotación de token y recargan ante login, cambio de cuenta o cierre', () => {
    const sharedStorage = memoryStorage({ [ADMIN_SESSION_KEY]: JSON.stringify({ authenticated: true }) });
    const tabStorage = memoryStorage({
        [PROFILE_KEY]: JSON.stringify({ persona: { identificacionNumero: '119290367' } }),
    });
    const listeners = new Map();
    let reloads = 0;
    const targetWindow = {
        localStorage: sharedStorage,
        addEventListener: (name, listener) => listeners.set(name, listener),
        removeEventListener: (name) => listeners.delete(name),
        location: { reload: () => { reloads += 1; } },
    };
    const stop = watchAuthSessionAcrossTabs(targetWindow, tabStorage);
    const onStorage = listeners.get('storage');
    const first = validSession();
    const refreshed = validSession({ accessToken: 'access-2', refreshToken: 'refresh-2' });
    const otherAccount = validSession({ userId: 'persona-2', email: 'otra@example.test' });

    onStorage({ key: AUTH_SESSION_KEY, storageArea: sharedStorage, oldValue: JSON.stringify(first), newValue: JSON.stringify(refreshed) });
    assert.equal(reloads, 0, 'la rotación no vuelve a cargar la página');
    onStorage({ key: AUTH_SESSION_KEY, storageArea: sharedStorage, oldValue: null, newValue: JSON.stringify(first) });
    assert.equal(reloads, 1, 'el login en otra pestaña actualiza la interfaz');
    onStorage({ key: AUTH_SESSION_KEY, storageArea: sharedStorage, oldValue: JSON.stringify(first), newValue: JSON.stringify(otherAccount) });
    assert.equal(reloads, 2, 'cambiar de cuenta actualiza el perfil');
    onStorage({ key: AUTH_SESSION_KEY, storageArea: sharedStorage, oldValue: JSON.stringify(otherAccount), newValue: null });
    assert.equal(reloads, 3, 'el cierre de sesión se refleja en las demás pestañas');
    assert.equal(tabStorage.has(PROFILE_KEY), false, 'se limpia la caché personal de la pestaña');
    assert.equal(sharedStorage.has(ADMIN_SESSION_KEY), false, 'se limpia el marcador administrativo compartido');

    stop();
    assert.equal(listeners.has('storage'), false);
    assert.equal(authSessionIdentity(JSON.stringify(first)), authSessionIdentity(JSON.stringify(refreshed)));
});

test('el navegador sin acceso al almacenamiento compartido no falla al instalar el sincronizador', () => {
    const targetWindow = Object.defineProperties({ addEventListener() {} }, {
        localStorage: { get() { throw new Error('storage blocked'); } },
        sessionStorage: { get() { throw new Error('storage blocked'); } },
    });
    assert.doesNotThrow(() => watchAuthSessionAcrossTabs(targetWindow));
});
