export const PROFILE_KEY = 'tindercows:profile';

export function readPublicProfile(storage = globalThis.sessionStorage) {
    try { return JSON.parse(storage?.getItem?.(PROFILE_KEY) || 'null'); } catch { return null; }
}

export function syncPublicProfile(activityData, storage = globalThis.sessionStorage) {
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
    storage?.setItem?.(PROFILE_KEY, JSON.stringify(profile));
    return profile;
}

export function clearPublicProfile(storage = globalThis.sessionStorage) {
    storage?.removeItem?.(PROFILE_KEY);
}
