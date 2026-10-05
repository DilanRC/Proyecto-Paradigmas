import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { signUpWithPassword, SIGNUP_NEXT_STEPS_MESSAGE } from '../../Public/js/shared/supabase-auth.js';

test('el alta traduce respuestas variantes de correo ya registrado', async () => {
    const originalFetch = globalThis.fetch;
    const requested = [];
    const signupErrors = [
        { msg: 'User already registered' },
        { code: 'user_already_exists', message: 'User exists' },
    ];
    globalThis.fetch = async (url) => {
        requested.push(String(url));
        if (String(url).includes('api/v1/auth/config')) {
            return new Response(JSON.stringify({
                success: true,
                data: { url: 'https://auth.example.test', publishableKey: 'sb_publishable_test' },
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        return new Response(JSON.stringify(signupErrors.shift()), {
            status: 422,
            headers: { 'Content-Type': 'application/json' },
        });
    };
    try {
        for (let intento = 0; intento < 2; intento += 1) {
            await assert.rejects(
                signUpWithPassword('docente@example.test', 'UnaClaveFuerte9', null),
                {
                    name: 'AuthError',
                    code: 'account_already_exists',
                    message: SIGNUP_NEXT_STEPS_MESSAGE,
                },
            );
        }
        assert.equal(requested.length, 3);
        assert.ok(requested[1].includes('/auth/v1/signup'));
        assert.ok(requested[2].includes('/auth/v1/signup'));
    } finally {
        globalThis.fetch = originalFetch;
    }
});

// P2-1 (2026-10-04, DEC-REG-001) reabre, por decisión del equipo, la consulta
// del correo que se había retirado el 29/09 (d7b5a88). Ahora solo existe en el
// endpoint con límite por IP; no vuelve el endpoint propio sin límite.
test('el registro solo consulta correos por el endpoint con límite por IP', () => {
    const registration = readFileSync(new URL('../../Public/js/registro.js', import.meta.url), 'utf8');
    const rewriteRules = readFileSync(new URL('../../Public/.htaccess', import.meta.url), 'utf8');
    const endpoint = readFileSync(new URL('../../Public/api/registro-validar-identificacion.php', import.meta.url), 'utf8');

    assert.doesNotMatch(registration, /api\/v1\/registro\/correo/);
    assert.doesNotMatch(rewriteRules, /api\/v1\/registro\/correo/);
    assert.match(endpoint, /RegistroConsulta::ipCliente/);
    assert.match(endpoint, /\], 429\);/);
    // Al crear la cuenta en Supabase, correo nuevo y correo ya registrado
    // siguen yendo al mismo lugar con el mismo aviso neutro.
    assert.match(registration, /if \(error\?\.code === 'account_already_exists'\) \{[\s\S]*?window\.location\.assign\(loginPendiente\(\)\)/);
    assert.match(registration, /if \(!auth\.session\) \{[\s\S]*?window\.location\.assign\(loginPendiente\(\)\)/);
});
