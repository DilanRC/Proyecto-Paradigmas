import assert from 'node:assert/strict';
import test from 'node:test';
import { SESSION_KEY, clearAuthSession } from '../../Public/js/shared/supabase-auth.js';
import { PROFILE_KEY } from '../../Public/js/shared/public-profile.js';
import { REGISTRATION_DRAFT_KEY } from '../../Public/js/shared/registro-pendiente.js';

function memoria(inicial = {}) {
    const datos = new Map(Object.entries(inicial));
    return { getItem: (k) => datos.get(k) ?? null, setItem: (k, v) => datos.set(k, String(v)), removeItem: (k) => datos.delete(k), has: (k) => datos.has(k) };
}

test('cerrar sesión borra el perfil en caché para que no lo vea la próxima persona', () => {
    const storage = memoria({
        [SESSION_KEY]: '{"accessToken":"x"}',
        [PROFILE_KEY]: '{"persona":{"identificacionNumero":"703110553","telefono":"88888888"}}',
        [REGISTRATION_DRAFT_KEY]: '{"persona":{"correoElectronico":"a@b.c"}}',
    });
    clearAuthSession(storage);
    assert.equal(storage.has(SESSION_KEY), false, 'se borra la sesión');
    assert.equal(storage.has(PROFILE_KEY), false, 'se borra el perfil (cédula, teléfono, correo)');
    // El borrador se conserva: hace falta para terminar el registro tras confirmar el correo.
    assert.equal(storage.has(REGISTRATION_DRAFT_KEY), true);
});
