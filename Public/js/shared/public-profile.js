export const PROFILE_KEY = 'tindercows:profile';

function profileStorage() {
    try { return globalThis.sessionStorage ?? null; } catch { return null; }
}

export function readPublicProfile(storage = profileStorage()) {
    try { return JSON.parse(storage?.getItem?.(PROFILE_KEY) || 'null'); } catch { return null; }
}

/** Nunca presenta datos cacheados si no pertenecen al correo de la sesión activa. */
export function publicProfileForSession(session, storage = profileStorage()) {
    const profile = readPublicProfile(storage);
    const profileEmail = String(profile?.persona?.correoElectronico ?? '').trim().toLowerCase();
    const sessionEmail = String(session?.email ?? '').trim().toLowerCase();
    return profileEmail !== '' && profileEmail === sessionEmail ? profile : null;
}

export function syncPublicProfile(activityData, storage = profileStorage()) {
    const existing = readPublicProfile(storage) ?? {};
    const persona = activityData?.persona ?? null;
    const capacidades = activityData?.capacidades ?? {};
    if (!persona || typeof capacidades !== 'object' || capacidades === null) {
        throw new Error('Mi actividad no devolvió un perfil válido.');
    }

    const capacidadesEstado = Object.fromEntries(
        Object.entries(capacidades).map(([id, detalle]) => [id, detalle?.estado ?? 'NO_CONFIGURADO']),
    );
    const configuradas = Object.entries(capacidadesEstado)
        .filter(([, estado]) => estado !== 'NO_CONFIGURADO')
        .map(([id]) => id);

    const profile = {
        ...existing,
        persona: {
            ...(existing.persona ?? {}),
            ...persona,
        },
        capacidades: configuradas,
        capacidadesEstado,
        fincas: Array.isArray(capacidades?.PRODUCTOR?.fincas)
            ? capacidades.PRODUCTOR.fincas
            : (Array.isArray(existing.fincas) ? existing.fincas : []),
        updatedAt: new Date().toISOString(),
        persistence: 'server-cache',
    };
    try { storage?.setItem?.(PROFILE_KEY, JSON.stringify(profile)); } catch { /* el perfil en caché no es requisito de identidad */ }
    return profile;
}

export function clearPublicProfile(storage = profileStorage()) {
    try { storage?.removeItem?.(PROFILE_KEY); } catch { /* la salida de sesión no depende de esta caché */ }
}
